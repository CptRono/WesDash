<?php
// register.php
session_start();


header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

error_reporting(E_ALL);
ini_set('display_errors', 1);

// 数据库连接
$conn = new mysqli(
    getenv('MYSQLHOST'),
    getenv('MYSQLUSER'),
    getenv('MYSQLPASSWORD'),
    getenv('MYSQLDATABASE'),
    getenv('MYSQLPORT')
);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database connection failed"]);
    exit;
}

// GET 请求时返回用户列表 
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $result = $conn->query("SELECT id, username FROM users");
    $users  = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    http_response_code(200);
    echo json_encode($users);
    exit;
}


$data = json_decode(file_get_contents("php://input"), true);
if (!$data) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Invalid JSON input"]);
    exit;
}

$username         = trim($data['username'] ?? '');
$password         = $data['password'] ?? '';
$confirm_password = $data['confirm_password'] ?? '';

if (empty($username) || empty($password) || empty($confirm_password)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "All fields are required."]);
    exit;
}
if (strlen($password) < 10) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Password must be at least 10 characters long."]);
    exit;
}
if ($password !== $confirm_password) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Passwords do not match."]);
    exit;
}

// 检查用户名是否存在或被删除
$stmt = $conn->prepare("SELECT is_deleted FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->bind_result($is_deleted);
$stmt->fetch();
$stmt->close();

if ($is_deleted === 1) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "This account has already been deleted."]);
    exit;
} elseif ($is_deleted === null) {
    // 创建新用户
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
    $stmt->bind_param("ss", $username, $hashed_password);
    if ($stmt->execute()) {
        $_SESSION['username'] = $username;
        http_response_code(201);
        echo json_encode([
            "success"    => true,
            "message"    => "Account created successfully!",
            "session_id" => session_id()
        ]);
    } else {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Something went wrong. Please try again."]);
    }
    $stmt->close();
} else {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Username is already taken."]);
}

$conn->close();
