<?php
session_start();

/* ---------- Headers ---------- */
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");


error_reporting(E_ALL);
ini_set('display_errors', 1);


$mysqli = $conn = new mysqli(
    getenv('MYSQLHOST'),
    getenv('MYSQLUSER'),
    getenv('MYSQLPASSWORD'),
    getenv('MYSQLDATABASE'),
    getenv('MYSQLPORT')
);
if ($mysqli->connect_error) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database connection failed"]);
    exit;
}


$raw  = file_get_contents("php://input");
$data = json_decode($raw, true);

if ($data === null || !isset($data['username'], $data['password'])) {
    http_response_code(201);          // ← per assignment
    echo json_encode(["success" => false, "message" => "Invalid JSON or missing fields."]);
    exit;
}

$username = trim($data['username']);
$password = $data['password'];


$stmt = $mysqli->prepare("SELECT password, is_deleted FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    // user not found → still return 201 (negative test)
    http_response_code(201);          // ← per assignment
    echo json_encode(["success" => false, "message" => "Username not found."]);
    exit;
}

$stmt->bind_result($hashedPwd, $isDeleted);
$stmt->fetch();

if ($isDeleted == 1) {
    http_response_code(201);          // ← per assignment
    echo json_encode(["success" => false, "message" => "Account has been deleted."]);
    exit;
}


if (password_verify($password, $hashedPwd)) {
    // success
    $_SESSION['username'] = $username;
    http_response_code(201);          // ← per assignment
    echo json_encode([
        "success"    => true,
        "message"    => "Login successful!",
        "session_id" => session_id()
    ]);
} else {
    // wrong password
    http_response_code(201);          // ← per assignment
    echo json_encode(["success" => false, "message" => "Invalid password."]);
}


$stmt->close();
$mysqli->close();
