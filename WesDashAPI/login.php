<?php

if (isset($_SERVER['HTTP_ORIGIN'])) {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
    header('Access-Control-Allow-Credentials: true');
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => false,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

ini_set('display_errors', 0);
error_reporting(E_ALL);

$mysqli = new mysqli(
    getenv('MYSQLHOST'),
    getenv('MYSQLUSER'),
    getenv('MYSQLPASSWORD'),
    getenv('MYSQLDATABASE'),
    getenv('MYSQLPORT')
);
if ($mysqli->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

try {
    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Malformed JSON body']);
    exit;
}

if (!isset($data['username'], $data['password'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing username or password']);
    exit;
}

$username = trim($data['username']);
$password = $data['password'];

$stmt = $mysqli->prepare(
    'SELECT password, is_deleted, role FROM users WHERE username = ?'
);
$stmt->bind_param('s', $username);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    http_response_code(201);                                 
    echo json_encode(['success' => false, 'message' => 'Username not found']);
    exit;
}

$stmt->bind_result($hashedPwd, $isDeleted, $role);
$stmt->fetch();

if ($isDeleted == 1) {
    http_response_code(201);                                 
    echo json_encode(['success' => false, 'message' => 'Account is deleted']);
    exit;
}

if (!password_verify($password, $hashedPwd)) {
    http_response_code(201);                                  
    echo json_encode(['success' => false, 'message' => 'Invalid password']);
    exit;
}

$_SESSION['username'] = $username;
$_SESSION['role']     = $role;


$logFile = __DIR__ . '/session_debug.log';
$logLine = sprintf(
    "[%s] Session ID: %s  User: %s\n",
    date('Y-m-d H:i:s'),
    session_id(),
    $username
);
if (is_writable(dirname($logFile))) {
    @file_put_contents($logFile, $logLine, FILE_APPEND);
} else {
    error_log('Cannot write to session_debug.log');
}

http_response_code(201);        
echo json_encode([
    'success'    => true,
    'message'    => 'Login successful',
    'session_id' => session_id(),
    'role'       => $role
]);

$stmt->close();
$mysqli->close();
?>