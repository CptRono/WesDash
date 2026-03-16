<?php

session_start();

/* ───────── Headers ───────── */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Cookie, Accept');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

/* ───────── Input ───────── */
$input   = json_decode(file_get_contents('php://input'), true);
$orderId = $input['order_id'] ?? null;
if (!$orderId) {
    echo json_encode(['success' => false, 'message' => 'Missing order ID.']);
    exit;
}

/* ───────── Update ───────── */
$stmt = $conn->prepare(
    "UPDATE requests
        SET review_prompt_status = 'rejected'
      WHERE id = ?
        AND review_prompt_status = 'pending'"
);
$stmt->bind_param('i', $orderId);
$stmt->execute();

/* ───────── Response ───────── */
if ($stmt->affected_rows > 0) {
    echo json_encode(['success' => true, 'message' => 'Review prompt canceled successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'No pending prompt found for this order.']);
}

$stmt->close();
$conn->close();
?>
