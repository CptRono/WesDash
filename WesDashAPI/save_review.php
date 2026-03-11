<?php
session_start();
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

$conn = new mysqli(
    getenv('MYSQLHOST'),
    getenv('MYSQLUSER'),
    getenv('MYSQLPASSWORD'),
    getenv('MYSQLDATABASE'),
    getenv('MYSQLPORT')
);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'DB connection failed: ' . $conn->connect_error]);
    exit;
}
$conn->set_charset('utf8mb4');

if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$orderId = $input['orderId'] ?? null;
$reviewText = $input['reviewText'] ?? null;
$rating = $input['rating'] ?? null;

if (!$orderId || !$reviewText || !$rating) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

$stmt = $conn->prepare(
    "INSERT INTO reviews (order_id, review_text, rating, created_at) VALUES (?, ?, ?, NOW())"
);
$stmt->bind_param('isi', $orderId, $reviewText, $rating);

if ($stmt->execute()) {
    $update = $conn->prepare(
        "UPDATE requests SET review_prompt_status = 'reviewed' WHERE id = ?"
    );
    $update->bind_param('i', $orderId);
    $update->execute();

    echo json_encode(['success' => true, 'message' => 'Review saved successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to save review.']);
}

$stmt->close();
$conn->close();
?>