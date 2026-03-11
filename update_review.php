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
$username = $_SESSION['username'];

// Ensure task_id is provided
if (!isset($_GET['task_id'])) {
    die("No task ID provided.");
}
$task_id = $_GET['task_id'];

// Retrieve current review and rating for the task
$stmt = $conn->prepare("SELECT comment, rating FROM tasks WHERE task_id = ?");
$stmt->bind_param("i", $task_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

// If no review exists, advise the user to use the create functionality
if (!$row || trim($row['comment']) === "") {
    die("No existing review found for this task. Please use the create review functionality.");
}

$currentReview = $row['comment'];
$currentRating = $row['rating'];

// Process form submission to update review
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newRating = $_POST['rating'] ?? '';
    $newReview = $_POST['review'] ?? '';

    // Basic validation: ensure both fields are provided
    if (empty($newRating) || empty($newReview)) {
        die("Both rating and review are required.");
    }
    $newRating = (int)$newRating;

    // Update the tasks table with the new review and rating
    $stmtUpdate = $conn->prepare("UPDATE tasks SET comment = ?, rating = ? WHERE task_id = ?");
    if (!$stmtUpdate) {
        die("Prepare failed: " . $conn->error);
    }
    $stmtUpdate->bind_param("sii", $newReview, $newRating, $task_id);
    $stmtUpdate->execute();

    if ($stmtUpdate->affected_rows > 0) {
        header("Location: manage_review.php");
        exit;
    } else {
        echo "Failed to update review.";
    }
    $stmtUpdate->close();
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Update Review</title>
    <style>
        label {
            font-weight: bold;
        }
    </style>
</head>
<body>
    <h1>Update Review for Task <?php echo htmlspecialchars($task_id); ?></h1>
    <form method="POST" action="update_review.php?task_id=<?php echo urlencode($task_id); ?>">
        <label for="rating">Rating (1-5):</label><br>
        <input type="number" name="rating" id="rating" min="1" max="5" required value="<?php echo htmlspecialchars($currentRating); ?>"><br><br>
        
        <label for="review">Review:</label><br>
        <textarea name="review" id="review" rows="5" cols="50" required><?php echo htmlspecialchars($currentReview); ?></textarea><br><br>
        
        <button type="submit">Update Review</button>
    </form>
    <p><a href="manage_review.php">Back to Manage Review</a></p>
</body>
</html>

<?php
$conn->close();
?>