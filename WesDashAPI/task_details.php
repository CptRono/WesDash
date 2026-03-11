<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Cookie');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Database connection
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

// Get task ID from query parameters
$taskId = isset($_GET['task_id']) ? intval($_GET['task_id']) : 0;

if (!$taskId) {
    echo json_encode(['success' => false, 'message' => 'Task ID is required']);
    exit;
}

// Query to get task details from the requests table
$stmt = $conn->prepare(
    "SELECT 
        r.id as task_id, 
        r.username, 
        r.item, 
        r.status,
        r.accepted_by as dashername
    FROM 
        requests r
    WHERE 
        r.id = ?"
);

$stmt->bind_param('i', $taskId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Task not found']);
    exit;
}

$task = $result->fetch_assoc();

// Check if there's already a review
$reviewStmt = $conn->prepare(
    "SELECT review_text as comment, rating FROM reviews WHERE order_id = ? LIMIT 1"
);
$reviewStmt->bind_param('i', $taskId);
$reviewStmt->execute();
$reviewResult = $reviewStmt->get_result();

if ($reviewResult->num_rows > 0) {
    $review = $reviewResult->fetch_assoc();
    $task['comment'] = $review['comment'];
    $task['rating'] = $review['rating'];
}

echo json_encode([
    'success' => true,
    'task' => $task
]);

$stmt->close();
$reviewStmt->close();
$conn->close();
?>