<?php
/* ───── Session ───── */
if (isset($_GET['PHPSESSID'])) session_id($_GET['PHPSESSID']);
session_start();

/* ───── CORS / headers ───── */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Cookie, Accept');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success'=>false,'message'=>'Invalid request method.']); exit;
}

/* ───── Auth ───── */
if (empty($_SESSION['username'])) {
    echo json_encode(['success'=>false,'message'=>'Please log in to view your reviews']); exit;
}
$username = $_SESSION['username'];

/* ───── DB ───── */
try {
    $conn = new mysqli(
        getenv('MYSQLHOST'),
        getenv('MYSQLUSER'),
        getenv('MYSQLPASSWORD'),
        getenv('MYSQLDATABASE'),
        getenv('MYSQLPORT')
    );
    if ($conn->connect_error) throw new Exception('DB connection failed: '.$conn->connect_error);
    $conn->set_charset('utf8mb4');

    /* 1) 用户尚未写评论的 completed / confirmed 订单 */
    $sqlNoReview = "
        SELECT r.id            AS task_id,
               r.username,
               r.item,
               r.status,
               r.accepted_by    AS dashername,
               r.created_at,
               NULL             AS rating,
               NULL             AS comment,
               0                AS has_review,     /* 0 = 没有评论 */
               NULL             AS review_id
        FROM   requests r
        LEFT JOIN reviews rev  ON r.id = rev.order_id
        WHERE  r.username = ?
          AND  LOWER(TRIM(r.status)) IN ('completed','confirmed')
          AND  rev.id IS NULL
    ";
    $st1 = $conn->prepare($sqlNoReview);
    if (!$st1) throw new Exception('Prepare failed (no-review): '.$conn->error);
    $st1->bind_param('s', $username);
    $st1->execute();
    $res1 = $st1->get_result();

    $noReview = [];
    while ($row = $res1->fetch_assoc()) {
        $row['created_at'] = date('M d, Y H:i', strtotime($row['created_at']));
        $noReview[] = $row;
    }
    $st1->close();

    /* 2) 已经写过评论的订单 */
    $sqlWithReview = "
        SELECT r.id            AS task_id,
               r.username,
               r.item,
               r.status,
               r.accepted_by    AS dashername,
               rev.created_at,
               rev.rating,
               rev.review_text  AS comment,
               1                AS has_review,     /* 1 = 已有评论 */
               rev.id           AS review_id
        FROM   reviews  rev
        JOIN   requests r ON rev.order_id = r.id
        WHERE  r.username = ?
          AND  LOWER(TRIM(r.status)) IN ('completed','confirmed')
        ORDER BY rev.created_at DESC
    ";
    $st2 = $conn->prepare($sqlWithReview);
    if (!$st2) throw new Exception('Prepare failed (with-review): '.$conn->error);
    $st2->bind_param('s', $username);
    $st2->execute();
    $res2 = $st2->get_result();

    $withReview = [];
    while ($row = $res2->fetch_assoc()) {
        $row['created_at'] = date('M d, Y H:i', strtotime($row['created_at']));
        $withReview[] = $row;
    }
    $st2->close();

    /* ───── 输出 ───── */
    echo json_encode([
        'success' => true,
        'tasks'   => array_merge($withReview, $noReview)
    ]);

} catch (Exception $e) {
    error_log('get_user_reviews error: '.$e->getMessage());
    echo json_encode(['success'=>false,'message'=>'Failed to load reviews: '.$e->getMessage()]);
} finally {
    if (isset($conn) && $conn instanceof mysqli) $conn->close();
}
?>
