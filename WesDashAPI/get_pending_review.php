<?php

session_start();

/* ───────── CORS & headers ───────── */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Cookie, Accept');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

/* ───────── DB ───────── */
$conn = new mysqli(
    getenv('MYSQLHOST'),
    getenv('MYSQLUSER'),
    getenv('MYSQLPASSWORD'),
    getenv('MYSQLDATABASE'),
    (int) getenv('MYSQLPORT')
);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'DB connection failed: ' . $conn->connect_error]);
    exit;
}
$conn->set_charset('utf8mb4');

/* ───────── Auth ───────── */
if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
    exit;
}
$username = $_SESSION['username'];

/* ───────── Query ───────── */
$stmt = $conn->prepare(
    "SELECT id, item, accepted_by
       FROM requests
      WHERE username = ?
        AND review_prompt_status = 'pending'
        AND status IN ('completed','confirmed')"      /* ← 改动处 */
);
$stmt->bind_param('s', $username);
$stmt->execute();
$result = $stmt->get_result();

$orders = [];
while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}

/* ───────── Response ───────── */
echo json_encode(['success' => true, 'orders' => $orders]);

$stmt->close();
$conn->close();
?>
