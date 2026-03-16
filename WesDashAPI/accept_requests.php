<?php
if (isset($_GET['PHPSESSID'])) {
    session_id($_GET['PHPSESSID']);
} elseif (isset($_COOKIE['PHPSESSID'])) {
    session_id($_COOKIE['PHPSESSID']);
}
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

/* ─────────────── CORS ─────────────── */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Cookie, Accept');
header('Access-Control-Allow-Credentials: true');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

/* ──────────── 登录校验 ──────────── */
if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in.']); exit;
}
$me = $_SESSION['username'];

/* ──────────── 数据库连接 ──────────── */
$conn = new mysqli(
    getenv('MYSQLHOST'),
    getenv('MYSQLUSER'),
    getenv('MYSQLPASSWORD'),
    getenv('MYSQLDATABASE'),
    (int) getenv('MYSQLPORT')
);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'DB connection failed: '.$conn->connect_error]); exit;
}
$conn->set_charset('utf8mb4');

/* ═══════════════════ GET ═══════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $sql = "
        SELECT  r.*,
                cr.id AS room_id
          FROM  requests r
          LEFT JOIN chat_rooms cr ON cr.order_id = r.id
         WHERE  r.username = ?
           AND  r.status <> 'confirmed'
         ORDER BY r.id DESC";
    $st = $conn->prepare($sql);
    $st->bind_param('s', $me);
    $st->execute();
    echo json_encode(['success' => true,
                      'requests' => $st->get_result()->fetch_all(MYSQLI_ASSOC)]);
    exit;
}

/* ═════════════════ DELETE ═════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $in = json_decode(file_get_contents('php://input'), true);
    if (empty($in['delete_id'])) {
        echo json_encode(['success'=>false,'message'=>'Missing delete_id']); exit;
    }
    $id = (int)$in['delete_id'];
    $st = $conn->prepare("DELETE FROM requests WHERE id=? AND username=?");
    $st->bind_param('is', $id, $me);
    $ok = $st->execute();
    echo json_encode(['success'=>$ok,'message'=>$ok ? 'Request deleted' : 'Delete failed']); exit;
}

/* ═══════════════════ PUT ═══════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $in = json_decode(file_get_contents('php://input'), true);

    /* —— 1) 用户确认收货：completed → confirmed —— */
    if (!empty($in['request_id'])) {
        $reqId = (int)$in['request_id'];

        $conn->begin_transaction();

        /* ① 锁单并取信息 */
        $sel = $conn->prepare(
            "SELECT status, accepted_by, est_price, delivery_speed
               FROM requests
              WHERE id=? AND username=? FOR UPDATE");
        $sel->bind_param('is', $reqId, $me);
        $sel->execute();
        $row = $sel->get_result()->fetch_assoc();

        if (!$row) { $conn->rollback();
            echo json_encode(['success'=>false,'message'=>'Request not found.']); exit; }

        if ($row['status'] !== 'completed') { $conn->rollback();
            echo json_encode(['success'=>false,'message'=>'Only completed requests can be confirmed.']); exit; }

        /* ② 计算支付给 dasher 的金额（¢） */
        $est   = (float)$row['est_price'];
        $rate  = $row['delivery_speed']==='urgent' ? 0.20 : 0.05;
        $fee   = $est * $rate;
        $tipRs = $conn->query("SELECT COALESCE(SUM(amount),0) AS t FROM tips WHERE request_id={$reqId}");
        $tip   = (int)$tipRs->fetch_assoc()['t'];                // 已是分
        $totalCents = (int) round(($est + $fee) * 100) + $tip;   // 商品+配送+小费

        /* ③ 更新订单状态 */
        $upd = $conn->prepare("UPDATE requests SET status='confirmed' WHERE id=?");
        $upd->bind_param('i', $reqId);
        $upd->execute();

        /* ④ 关闭聊天室 */
        $conn->prepare("DELETE FROM chat_rooms WHERE order_id=?")
             ->bind_param('i', $reqId)->execute();

        /* ⑤ 打钱给 dasher */
        if (!empty($row['accepted_by'])) {
            $dasher = $row['accepted_by'];
            $add = $conn->prepare(
                "UPDATE users SET balance = balance + ? WHERE username=?");
            $add->bind_param('is', $totalCents, $dasher);
            $add->execute();
        }

        $conn->commit();
        echo json_encode(['success'=>true,'message'=>'Request confirmed & payment released.']); exit;
    }

    /* —— 2) 用户编辑自己的请求 —— */
    if (empty($in['id'])          || empty($in['item']) ||
        empty($in['drop_off_location']) || empty($in['delivery_speed']) ||
        empty($in['status'])) {
        echo json_encode(['success'=>false,'message'=>'Missing fields.']); exit;
    }

    $st = $conn->prepare(
        "UPDATE requests
            SET item=?, drop_off_location=?, delivery_speed=?, status=?
          WHERE id=? AND username=?");
    $st->bind_param(
        'ssssis',
        $in['item'],
        $in['drop_off_location'],
        $in['delivery_speed'],
        $in['status'],
        $in['id'],
        $me
    );
    $ok = $st->execute();
    echo json_encode(['success'=>$ok,'message'=>$ok ? 'Request updated.' : 'Update failed.']); exit;
}

/* ─────────── 其它方法 ─────────── */
echo json_encode(['success'=>false,'message'=>'Unsupported method.']);
$conn->close();
?>
