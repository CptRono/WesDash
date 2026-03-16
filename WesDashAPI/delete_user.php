<?php
session_start();
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json");

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Debugging: Log session
error_log(print_r($_SESSION, true)); // Logs session

$conn = new mysqli(
    getenv('MYSQLHOST'),
    getenv('MYSQLUSER'),
    getenv('MYSQLPASSWORD'),
    getenv('MYSQLDATABASE'),
    (int) getenv('MYSQLPORT')
);
if ($conn->connect_error) {
    die(json_encode(["error" => "Database connection failed: " . $conn->connect_error]));
}
// Ensure user is logged in by checking the session variable
if (!isset($_SESSION['username'])) {
    echo json_encode(["success" => false, "message" => "Unauthorized access."]);
    exit();
}

$username = $_SESSION['username']; // Get the username from the session

// Get the username from the session
$usernameToDelete = $_SESSION['username'];  // This line captures the username from the session

// Handle POST request
$input = json_decode(file_get_contents("php://input"), true);
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($input['password'])) {
    $password = $input['password'];

    // Verify user password
    $sql = "SELECT password FROM users WHERE username = ? AND is_deleted = 0";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $usernameToDelete);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows == 1) {
        $stmt->bind_result($hashedPassword);
        $stmt->fetch();

        if (password_verify($password, $hashedPassword)) {
            // Delete all requests by this user
            $deleteRequests = "DELETE FROM requests WHERE username = ?";
            $stmt = $conn->prepare($deleteRequests);
            $stmt->bind_param("s", $usernameToDelete);
            if (!$stmt->execute()) {
                echo json_encode(["success" => false, "message" => "Error deleting requests."]);
                exit();
            }

            // Mark the user as deleted without removing the row
            $deleteUser = "UPDATE users SET is_deleted = 1 WHERE username = ?";
            $stmt = $conn->prepare($deleteUser);
            if ($stmt->bind_param("s", $usernameToDelete) && $stmt->execute()) {
                session_destroy();
                echo json_encode(["success" => true, "message" => "Account deleted successfully."]);
                exit();
            } else {
                echo json_encode(["success" => false, "message" => "Error deleting account."]);
                exit();
            }
        } else {
            echo json_encode(["success" => false, "message" => "Incorrect password."]);
            exit();
        }
    } else {
        echo json_encode(["success" => false, "message" => "User not found."]);
        exit();
    }
} else {
    echo json_encode(["success" => false, "message" => "Invalid request."]);
    exit();
}
?>
