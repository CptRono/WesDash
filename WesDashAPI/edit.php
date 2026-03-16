<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Methods: POST, GET, OPTIONS, DELETE, PUT");
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

if (!isset($_SESSION['username'])) {
    echo json_encode([
        "success" => false,
        "message" => "Unauthorized access. Please log in."
    ]);
    exit;
}

$username = $_SESSION['username'];

$conn = new mysqli(
    getenv('MYSQLHOST'),
    getenv('MYSQLUSER'),
    getenv('MYSQLPASSWORD'),
    getenv('MYSQLDATABASE'),
    (int) getenv('MYSQLPORT')
);
if ($conn->connect_error) {
    echo json_encode([
        "success" => false,
        "message" => "DB connection failed: " . $conn->connect_error
    ]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    if (empty($data['id']) || empty($data['item']) || empty($data['drop_off_location']) || empty($data['delivery_speed']) || empty($data['status'])) {
        echo json_encode([
            "success" => false,
            "message" => "Missing required fields."
        ]);
        exit;
    }

    $id = (int)$data['id'];
    $item = $data['item'];
    $drop_off_location = $data['drop_off_location'];
    $delivery_speed = $data['delivery_speed'];
    $status = $data['status'];

    $sql = "UPDATE requests
            SET item = ?,
                drop_off_location = ?,
                delivery_speed = ?,
                status = ?
            WHERE id = ? AND username = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode([
            "success" => false,
            "message" => "Failed to prepare statement: " . $conn->error
        ]);
        exit;
    }

    $stmt->bind_param("ssssis", $item, $drop_off_location, $delivery_speed, $status, $id, $username);

    if ($stmt->execute()) {
        echo json_encode([
            "success" => true,
            "message" => "Request updated successfully."
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => "Failed to update request: " . $stmt->error
        ]);
    }
    
    $stmt->close();
} else {
    echo json_encode([
        "success" => false,
        "message" => "Invalid request method."
    ]);
}

$conn->close();
?>
