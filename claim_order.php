<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Must be logged in.
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
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
    die("Database connection failed: " . $conn->connect_error);
}

$username = $_SESSION['username']; // Dasher's username

// Ensure order_id is provided
if (!isset($_GET['order_id'])) {
    die("No order ID provided.");
}
$orderId = intval($_GET['order_id']);

// 1. Fetch the buyer's username and item from the requests table
$sqlFetch = "SELECT username, item FROM requests WHERE id = ? AND status = 'pending' LIMIT 1";
$stmtFetch = $conn->prepare($sqlFetch);
if (!$stmtFetch) {
    die("Fetch prepare failed: " . $conn->error);
}
$stmtFetch->bind_param("i", $orderId);
$stmtFetch->execute();
$res = $stmtFetch->get_result();
$row = $res->fetch_assoc();
$stmtFetch->close();

if ($row) {
    $buyerUsername = $row['username'];
    $itemName = $row['item'];

    // 2. Insert into tasks table (including the new 'item' column)
    $sqlInsert = "INSERT INTO tasks (username, request_id, dashername, item, status) VALUES (?, ?, ?, ?, 'claimed')";
    $stmtInsert = $conn->prepare($sqlInsert);
    if (!$stmtInsert) {
        die("Insert prepare failed: " . $conn->error);
    }
    $stmtInsert->bind_param("siss", $buyerUsername, $orderId, $username, $itemName);

    if (!$stmtInsert->execute()) {
        die("Insert failed: " . $stmtInsert->error);
    }
    $stmtInsert->close();

    // 3. Update the requests table to mark the order as claimed.
    $sqlClaim = "UPDATE requests SET status = 'claimed' WHERE id = ? AND status = 'pending'";
    $stmtClaim = $conn->prepare($sqlClaim);
    if (!$stmtClaim) {
        die("Update prepare failed: " . $conn->error);
    }
    $stmtClaim->bind_param("i", $orderId);
    $stmtClaim->execute();
    $stmtClaim->close();
} else {
    die("Order not found or already claimed.");
}

$conn->close();
header("Location: dashboard.php");
exit;
?>
