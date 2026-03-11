<?php
// Start session
if (isset($_GET['PHPSESSID'])) {
    session_id($_GET['PHPSESSID']);
}
session_start();

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Cookie');
header('Access-Control-Allow-Credentials: true');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Please log in to delete a review'
    ]);
    exit;
}

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['review_id'])) {
    // Check if task_id is provided for backward compatibility
    if (isset($data['task_id'])) {
        $taskId = $data['task_id'];
        // For backward compatibility - delete from tasks table
        try {
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
            
            $stmt = $conn->prepare("UPDATE tasks SET comment = '', rating = NULL WHERE task_id = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $stmt->bind_param("i", $taskId);
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            
            if ($stmt->affected_rows > 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Review deleted successfully'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'No review found for this task'
                ]);
            }
            
            $stmt->close();
            $conn->close();
            exit;
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
            exit;
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Review ID is required'
        ]);
        exit;
    }
}

$reviewId = intval($data['review_id']);
$username = $_SESSION['username'];

try {
    // Connect to database
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
    
    // Verify that the review belongs to the logged-in user
    $query = "
        SELECT r.id 
        FROM reviews r
        JOIN requests req ON r.order_id = req.id
        WHERE r.id = ? AND req.username = ?
    ";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("is", $reviewId, $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("You don't have permission to delete this review");
    }
    $stmt->close();
    
    // Delete the review
    $deleteQuery = "DELETE FROM reviews WHERE id = ?";
    $deleteStmt = $conn->prepare($deleteQuery);
    if (!$deleteStmt) {
        throw new Exception("Prepare failed for delete: " . $conn->error);
    }
    
    $deleteStmt->bind_param("i", $reviewId);
    if (!$deleteStmt->execute()) {
        throw new Exception("Failed to delete review: " . $deleteStmt->error);
    }
    
    // Update the request's review status
    $updateQuery = "
        UPDATE requests
        SET review_prompt_status = 'pending'
        WHERE id = (SELECT order_id FROM reviews WHERE id = ?)
    ";
    $updateStmt = $conn->prepare($updateQuery);
    if (!$updateStmt) {
        throw new Exception("Prepare failed for update: " . $conn->error);
    }
    
    $updateStmt->bind_param("i", $reviewId);
    if (!$updateStmt->execute()) {
        throw new Exception("Failed to update request status: " . $updateStmt->error);
    }
    $updateStmt->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'Review deleted successfully'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    if (isset($deleteStmt)) $deleteStmt->close();
    if (isset($updateStmt)) $updateStmt->close();
    if (isset($conn)) $conn->close();
}
?>