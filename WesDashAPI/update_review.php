<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start or resume session
if (isset($_GET['PHPSESSID'])) {
    session_id($_GET['PHPSESSID']);
}
session_start();

// Set headers
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Cookie");
header("Access-Control-Allow-Credentials: true");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Database connection
    $conn = new mysqli(
        getenv('MYSQLHOST'),
        getenv('MYSQLUSER'),
        getenv('MYSQLPASSWORD'),
        getenv('MYSQLDATABASE'),
        (int) getenv('MYSQLPORT')
    );
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
    
    // Check if user is logged in
    if (!isset($_SESSION['username'])) {
        throw new Exception("Please log in before updating a review.");
    }
    
    // Get JSON data
    $json = file_get_contents("php://input");
    $data = json_decode($json, true);
    if (!$data) {
        throw new Exception("Invalid JSON data: " . json_last_error_msg());
    }
    
    // Validate required fields
    if (!isset($data['task_id']) || !isset($data['rating']) || !isset($data['comment'])) {
        throw new Exception("Task ID, rating, and comment are required.");
    }
    
    $taskId = intval($data['task_id']);
    $rating = intval($data['rating']);
    $comment = $data['comment'];
    
    // Validate rating
    if ($rating < 1 || $rating > 5) {
        throw new Exception("Rating must be between 1 and 5.");
    }
    
    // Check if the request/task exists
    $sql = "SELECT id FROM requests WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed (select task): " . $conn->error);
    }
    
    $stmt->bind_param("i", $taskId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Task with ID $taskId not found.");
    }
    $stmt->close();
    
    // Check if a review already exists for this task
    $checkReviewSql = "SELECT id FROM reviews WHERE order_id = ?";
    $checkStmt = $conn->prepare($checkReviewSql);
    if (!$checkStmt) {
        throw new Exception("Prepare failed (check review): " . $conn->error);
    }
    
    $checkStmt->bind_param("i", $taskId);
    $checkStmt->execute();
    $reviewResult = $checkStmt->get_result();
    $reviewExists = $reviewResult->num_rows > 0;
    
    if ($reviewExists) {
        // Get the review ID
        $reviewRow = $reviewResult->fetch_assoc();
        $reviewId = $reviewRow['id'];
        
        // Update the existing review
        $updateSql = "UPDATE reviews SET review_text = ?, rating = ? WHERE id = ?";
        $updateStmt = $conn->prepare($updateSql);
        if (!$updateStmt) {
            throw new Exception("Prepare failed (update review): " . $conn->error);
        }
        
        $updateStmt->bind_param("sii", $comment, $rating, $reviewId);
        if (!$updateStmt->execute()) {
            throw new Exception("Failed to update review: " . $updateStmt->error);
        }
        $updateStmt->close();
    } else {
        // Create a new review if it doesn't exist
        $createSql = "INSERT INTO reviews (order_id, review_text, rating, created_at) VALUES (?, ?, ?, NOW())";
        $createStmt = $conn->prepare($createSql);
        if (!$createStmt) {
            throw new Exception("Prepare failed (create review): " . $conn->error);
        }
        
        $createStmt->bind_param("isi", $taskId, $comment, $rating);
        if (!$createStmt->execute()) {
            throw new Exception("Failed to create review: " . $createStmt->error);
        }
        $createStmt->close();
        
        // Update the review status in the requests table
        $statusSql = "UPDATE requests SET review_prompt_status = 'reviewed' WHERE id = ?";
        $statusStmt = $conn->prepare($statusSql);
        if (!$statusStmt) {
            throw new Exception("Prepare failed (update status): " . $conn->error);
        }
        
        $statusStmt->bind_param("i", $taskId);
        if (!$statusStmt->execute()) {
            throw new Exception("Failed to update request status: " . $statusStmt->error);
        }
        $statusStmt->close();
    }
    
    // Success response
    echo json_encode([
        "success" => true,
        "message" => "Review " . ($reviewExists ? "updated" : "created") . " successfully."
    ]);
    
} catch (Exception $e) {
    // Error response
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
} finally {
    // Close all connections
    if (isset($checkStmt)) $checkStmt->close();
    if (isset($conn)) $conn->close();
}
?>