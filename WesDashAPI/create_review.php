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

// Log to PHP error log instead of file
error_log("Request method: " . $_SERVER['REQUEST_METHOD']);
error_log("Session username: " . ($_SESSION['username'] ?? 'not set'));

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
        getenv('MYSQLPORT')
    );
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
    
    // Check if user is logged in
    if (!isset($_SESSION['username'])) {
        throw new Exception("Please log in before creating a review.");
    }
    
    // Get JSON data
    $json = file_get_contents("php://input");
    error_log("Raw input: " . $json);
    
    $data = json_decode($json, true);
    if (!$data) {
        throw new Exception("Invalid JSON data: " . json_last_error_msg());
    }
    
    // Log decoded data
    error_log("Decoded data: " . print_r($data, true));
    
    // Validate required fields
    if (!isset($data['task_id']) || !isset($data['rating']) || !isset($data['comment'])) {
        throw new Exception("Task ID, rating, and comment are required.");
    }
    
    $taskId = intval($data['task_id']);
    $rating = intval($data['rating']);
    $comment = $data['comment'];
    
    error_log("Processing review for Task ID: $taskId, Rating: $rating");
    
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
    
    error_log("Task exists, checking for existing reviews");
    
    // Check if the reviews table exists
    $tableCheckResult = $conn->query("SHOW TABLES LIKE 'reviews'");
    if ($tableCheckResult->num_rows === 0) {
        // Create the reviews table if it doesn't exist
        $createTableSql = "CREATE TABLE IF NOT EXISTS reviews (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            review_text TEXT NOT NULL,
            rating INT NOT NULL,
            created_at DATETIME NOT NULL,
            FOREIGN KEY (order_id) REFERENCES requests(id)
        )";
        
        if (!$conn->query($createTableSql)) {
            throw new Exception("Could not create reviews table: " . $conn->error);
        }
        error_log("Created reviews table");
    }
    
    // Check if a review already exists for this task
    $checkReviewSql = "SELECT * FROM reviews WHERE order_id = ?";
    $checkStmt = $conn->prepare($checkReviewSql);
    if (!$checkStmt) {
        throw new Exception("Prepare failed (check review): " . $conn->error);
    }
    
    $checkStmt->bind_param("i", $taskId);
    $checkStmt->execute();
    $reviewResult = $checkStmt->get_result();
    
    if ($reviewResult->num_rows > 0) {
        throw new Exception("A review already exists for this task. Please use update instead.");
    }
    
    error_log("No existing review found, creating new review");
    
    // Create a new review
    $createSql = "INSERT INTO reviews (order_id, review_text, rating, created_at) VALUES (?, ?, ?, NOW())";
    $createStmt = $conn->prepare($createSql);
    if (!$createStmt) {
        throw new Exception("Prepare failed (create review): " . $conn->error);
    }
    
    $createStmt->bind_param("isi", $taskId, $comment, $rating);
    if (!$createStmt->execute()) {
        throw new Exception("Failed to create review: " . $createStmt->error);
    }
    
    error_log("Review inserted, updating request status");
    
    // Check if review_prompt_status column exists in requests table
    $columnCheckResult = $conn->query("SHOW COLUMNS FROM requests LIKE 'review_prompt_status'");
    if ($columnCheckResult->num_rows === 0) {
        // Add the column if it doesn't exist
        $addColumnSql = "ALTER TABLE requests ADD COLUMN review_prompt_status ENUM('pending', 'rejected', 'reviewed') DEFAULT 'pending'";
        if (!$conn->query($addColumnSql)) {
            error_log("Warning: Could not add review_prompt_status column: " . $conn->error);
        } else {
            error_log("Added review_prompt_status column to requests table");
        }
    }
    
    // Update the review status in the requests table
    $updateSql = "UPDATE requests SET review_prompt_status = 'reviewed' WHERE id = ?";
    $updateStmt = $conn->prepare($updateSql);
    if (!$updateStmt) {
        throw new Exception("Prepare failed (update status): " . $conn->error);
    }
    
    $updateStmt->bind_param("i", $taskId);
    if (!$updateStmt->execute()) {
        throw new Exception("Failed to update request status: " . $updateStmt->error);
    }
    
    // Success response
    $response = ["success" => true, "message" => "Review created successfully."];
    error_log("Success: Review created for task ID: $taskId");
    echo json_encode($response);
    
} catch (Exception $e) {
    // Error response
    $errorResponse = [
        "success" => false,
        "message" => $e->getMessage()
    ];
    error_log("Error: " . $e->getMessage());
    echo json_encode($errorResponse);
} finally {
    // Close all connections
    if (isset($updateStmt)) $updateStmt->close();
    if (isset($createStmt)) $createStmt->close();
    if (isset($checkStmt)) $checkStmt->close();
    if (isset($stmt)) $stmt->close();
    if (isset($conn)) $conn->close();
}
?>