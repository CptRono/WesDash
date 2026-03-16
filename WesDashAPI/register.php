<?php

declare(strict_types=1);


header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');
header('Access-Control-Allow-Origin: *');        
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Register endpoint OK']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true) ?? [];
$username         = trim($data['username']         ?? '');
$password         = $data['password']              ?? '';
$confirm_password = $data['confirm_password']      ?? '';

$errors = [];
if ($username === '' || $password === '' || $confirm_password === '') {
    $errors[] = 'All fields are required';
}
if (strlen($password) < 10) {
    $errors[] = 'Password must be at least 10 characters';
}
if ($password !== $confirm_password) {
    $errors[] = 'Passwords do not match';
}

if ($errors) {
    http_response_code(201);                  
    echo json_encode(['success' => false, 'message' => implode('; ', $errors)]);
    exit;
}


$mysqli = new mysqli(
    getenv('MYSQLHOST'),
    getenv('MYSQLUSER'),
    getenv('MYSQLPASSWORD'),
    getenv('MYSQLDATABASE'),
    (int) getenv('MYSQLPORT')
);
if ($mysqli->connect_error) {
    http_response_code(201);
    echo json_encode(['success' => false, 'message' => 'DB connection failed']);
    exit;
}


$stmt = $mysqli->prepare('SELECT is_deleted FROM users WHERE username = ?');
$stmt->bind_param('s', $username);
$stmt->execute();
$stmt->bind_result($is_deleted);
$stmt->fetch();
$stmt->close();

if ($is_deleted === 1) {
    http_response_code(201);
    echo json_encode(['success' => false, 'message' => 'This account has already been deleted']);
    exit;
}
if ($is_deleted !== null) {
    http_response_code(201);
    echo json_encode(['success' => false, 'message' => 'Username is already taken']);
    exit;
}


$hashed = password_hash($password, PASSWORD_DEFAULT);
$ins    = $mysqli->prepare('INSERT INTO users (username, password) VALUES (?, ?)');
$ins->bind_param('ss', $username, $hashed);

if ($ins->execute()) {
    $_SESSION['username'] = $username;
    http_response_code(201);                
    echo json_encode([
        'success'    => true,
        'message'    => 'Account created successfully',
        'session_id' => session_id(),
    ]);
} else {
    http_response_code(201);
    echo json_encode(['success' => false, 'message' => 'Registration failed']);
}
$ins->close();
$mysqli->close();
?>