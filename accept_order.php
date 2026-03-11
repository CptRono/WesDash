<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, PUT, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Cookie, Accept");
header("Access-Control-Allow-Credentials: true");

$method = $_SERVER['REQUEST_METHOD'];

$conn = new mysqli(
    getenv('MYSQLHOST'),
    getenv('MYSQLUSER'),
    getenv('MYSQLPASSWORD'),
    getenv('MYSQLDATABASE'),
    getenv('MYSQLPORT')
);
if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "DB connection failed: " . $conn->connect_error]);
    exit();
}

// Ensure user is logged in
if (!isset($_SESSION['username'])) {
    echo json_encode(["success" => false, "message" => "User not logged in."]);
    exit();
}
$loggedInUser = $_SESSION['username'];


if ($method === 'GET') {
    $sql = "
        SELECT * 
          FROM requests 
         WHERE (status = 'pending' AND username != ?)
            OR (status = 'accepted' AND accepted_by = ?)
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(["success" => false, "message" => "SQL prepare error: " . $conn->error]);
        exit();
    }
    $stmt->bind_param("ss", $loggedInUser, $loggedInUser);
    $stmt->execute();
    $result = $stmt->get_result();
    $orders = $result->fetch_all(MYSQLI_ASSOC);
    echo json_encode(["success" => true, "orders" => $orders]);
    exit();


// 2) PUT => Accept or DropOff
} elseif ($method === 'PUT') {
    $rawData = file_get_contents("php://input");
    $input   = json_decode($rawData, true);

    if (!$input || !isset($input['id'])) {
        echo json_encode(["success" => false, "message" => "Invalid input: missing id."]);
        exit();
    }
    $id = $input['id'];

    // --- Drop off an order (status=completed) ---
    if (isset($input['action']) && $input['action'] === 'drop_off') {
        $stmt = $conn->prepare("
            UPDATE requests
               SET status = 'completed'
             WHERE id = ?
               AND status = 'accepted'
               AND accepted_by = ?
        ");
        if (!$stmt) {
            echo json_encode(["success" => false, "message" => "SQL prepare error: " . $conn->error]);
            exit();
        }
        $stmt->bind_param("is", $id, $loggedInUser);
        if (!$stmt->execute()) {
            echo json_encode(["success" => false, "message" => "SQL execute error: " . $stmt->error]);
            exit();
        }
        if ($stmt->affected_rows > 0) {
            echo json_encode(["success" => true, "message" => "Order dropped off (completed) successfully"]);
        } else {
            echo json_encode(["success" => false, "message" => "No matching accepted order found or you are not the acceptor."]);
        }
        exit();

    // --- Accept an order (status=pending => accepted) ---
    } else {
        $stmt = $conn->prepare("
            UPDATE requests
               SET status = 'accepted', accepted_by = ?
             WHERE id = ?
               AND status = 'pending'
               AND username != ?
        ");
        if (!$stmt) {
            echo json_encode(["success" => false, "message" => "SQL prepare error: " . $conn->error]);
            exit();
        }
        $stmt->bind_param("sis", $loggedInUser, $id, $loggedInUser);
        if (!$stmt->execute()) {
            echo json_encode(["success" => false, "message" => "SQL execute error: " . $stmt->error]);
            exit();
        }
        if ($stmt->affected_rows > 0) {
            echo json_encode(["success" => true, "message" => "Order accepted successfully"]);
        } else {
            echo json_encode(["success" => false, "message" => "No matching pending order found or you cannot accept your own order."]);
        }
        exit();
    }

// 3) other methods => invalid
} else {
    echo json_encode(["success" => false, "message" => "Invalid request method: $method"]);
    exit();
}

$conn->close();
?>
