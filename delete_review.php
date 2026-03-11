<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
$conn = new mysqli(
    getenv('MYSQLHOST'),
    getenv('MYSQLUSER'),
    getenv('MYSQLPASSWORD'),
    getenv('MYSQLDATABASE'),
    getenv('MYSQLPORT')
);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Ensure the user is logged in
if (!isset($_SESSION['username'])) {
    die("Please log in.");
}

// Process the deletion if a task_id is provided
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['task_id'])) {
    $task_id = $_POST['task_id'];

    // Delete the review by clearing the comment field
    $empty = "";
    $stmt = $conn->prepare("UPDATE tasks SET comment = ? WHERE task_id = ?");
    $stmt->bind_param("si", $empty, $task_id);
    $stmt->execute();
    $stmt->close();

    // Redirect back to manage_reviews.php so the page remains the same
    header("Location: manage_review.php");
    exit;
} else {
    die("No task id provided.");
}
?>
