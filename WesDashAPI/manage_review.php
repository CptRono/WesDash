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
header("Access-Control-Allow-Methods: GET, OPTIONS");
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
    echo json_encode(["success" => false, "message" => "Please log in before accessing reviews."]);
    exit;
}

$username = $_SESSION['username'];

// Query to get all completed orders from the tasks table
$sql = "SELECT task_id, request_id, username AS buyer, dashername, item, comment, rating, status 
        FROM tasks 
        WHERE status = 'completed' 
        ORDER BY task_id DESC";
$result = $conn->query($sql);

if (!$result) {
    echo json_encode(["success" => false, "message" => "Query failed: " . $conn->error]);
    exit;
}

$tasks = [];
while ($row = $result->fetch_assoc()) {
    $tasks[] = $row;
}

// Return the tasks
echo json_encode([
    "success" => true,
    "tasks" => $tasks
]);

$conn->close();
?>