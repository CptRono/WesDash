<?php
if (isset($_GET['PHPSESSID'])) {
    session_id($_GET['PHPSESSID']);
}
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set headers for JSON API
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
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
    echo json_encode(["success" => false, "message" => "Database connection failed: " . $conn->connect_error]);
    exit;
}

// Must login first
if (!isset($_SESSION['username'])) {
    echo json_encode(["success" => false, "message" => "Please log in before creating a review."]);
    exit;
}

// Get JSON data
$data = json_decode(file_get_contents("php://input"), true);

// Validate required fields
if (!isset($data['task_id']) || !isset($data['rating']) || !isset($data['comment'])) {
    echo json_encode(["success" => false, "message" => "Task ID, rating, and comment are required."]);
    exit;
}

$taskId = intval($data['task_id']);
$rating = intval($data['rating']);
$comment = $data['comment'];

// Validate rating
if ($rating < 1 || $rating > 5) {
    echo json_encode(["success" => false, "message" => "Rating must be between 1 and 5."]);
    exit;
}

// Check if a review already exists for this task
$sql = "SELECT comment FROM tasks WHERE task_id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(["success" => false, "message" => "Prepare failed: " . $conn->error]);
    exit;
}

$stmt->bind_param("i", $taskId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["success" => false, "message" => "Task not found."]);
    exit;
}

$row = $result->fetch_assoc();
if ($row && !empty(trim($row['comment']))) {
    echo json_encode(["success" => false, "message" => "A review already exists for this task. Please use update instead."]);
    exit;
}

// Update the tasks table with the new review
$updateSql = "UPDATE tasks SET comment = ?, rating = ? WHERE task_id = ?";
$updateStmt = $conn->prepare($updateSql);
if (!$updateStmt) {
    echo json_encode(["success" => false, "message" => "Prepare failed: " . $conn->error]);
    exit;
}

$updateStmt->bind_param("sii", $comment, $rating, $taskId);
if ($updateStmt->execute()) {
    echo json_encode(["success" => true, "message" => "Review created successfully."]);
} else {
    echo json_encode(["success" => false, "message" => "Failed to create review: " . $updateStmt->error]);
}

$updateStmt->close();
$stmt->close();
$conn->close();
?>