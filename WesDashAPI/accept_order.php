<?php
// File: /WesDashAPI/accept_order.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (isset($_GET['PHPSESSID'])) {
    session_id($_GET['PHPSESSID']);
}
session_start();

// CORS headers
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: $origin");
header('Access-Control-Allow-Methods: GET, PUT, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Cookie, Accept');
header('Access-Control-Allow-Credentials: true');


// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Connect
$conn = new mysqli(
    getenv('MYSQLHOST'),
    getenv('MYSQLUSER'),
    getenv('MYSQLPASSWORD'),
    getenv('MYSQLDATABASE'),
    getenv('MYSQLPORT')
);
if ($conn->connect_error) {
    echo json_encode(['success'=>false,'message'=>'DB connection failed: '.$conn->connect_error]);
    exit;
}
$conn->set_charset('utf8mb4');

// Auth check
if (!isset($_SESSION['username'])) {
    echo json_encode(['success'=>false,'message'=>'User not logged in.']);
    exit;
}
$loggedInUser = $_SESSION['username'];

/* ─────────────── GET ─────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $sql = "
      SELECT r.*, cr.id AS room_id
        FROM requests r
        LEFT JOIN chat_rooms cr ON cr.order_id = r.id
       WHERE (r.status='pending'  AND r.username   != ?)
          OR (r.status='accepted' AND r.accepted_by = ?)
       ORDER BY r.id DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $loggedInUser, $loggedInUser);
    $stmt->execute();
    echo json_encode([
        'success' => true,
        'orders'  => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)
    ]);
    exit;
}



/* ════════════════  POST  (dasher drop-off with receipt) ════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 0. Validate required fields: id, action, real_price > 0
    if (
        empty($_POST['id'])
     || empty($_POST['action']) || $_POST['action'] !== 'drop_off'
     || !isset($_POST['real_price']) || (float)$_POST['real_price'] <= 0
    ) {
        echo json_encode(['success'=>false,'message'=>'Missing or invalid fields']);
        exit;
    }

    $id    = (int)$_POST['id'];
    $price = (float)$_POST['real_price'];  // match your JS field name

    // 1. Only drop-off if the order is already accepted by this rider
    $chk = $conn->prepare(
        "SELECT status
           FROM requests
          WHERE id=? AND accepted_by=?
          FOR UPDATE"
    );
    $chk->bind_param('is', $id, $loggedInUser);
    $chk->execute();
    $row = $chk->get_result()->fetch_assoc();
    if (!$row || $row['status'] !== 'accepted') {
        echo json_encode(['success'=>false,'message'=>'Order not in accepted state']);
        exit;
    }

    // 2. (Optional) Save receipt photo if provided
    $receiptFilename = null;
    if (!empty($_FILES['receipt']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK) {
        $dir = __DIR__ . '/receipts';
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $ext  = pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION) ?: 'jpg';
        $file = 'rec_'.$id.'_'.time().'.'.$ext;
        if (!move_uploaded_file($_FILES['receipt']['tmp_name'], "$dir/$file")) {
            echo json_encode(['success'=>false,'message'=>'Receipt upload failed']);
            exit;
        }
        $receiptFilename = $file;
    }

    // 3. Write back the real price and mark completed
    $upd = $conn->prepare(
        "UPDATE requests
            SET est_price=?, status='completed'
          WHERE id=? AND accepted_by=?"
    );
    $upd->bind_param('dis', $price, $id, $loggedInUser);
    $upd->execute();

    // 4. Close the chat room
    $close = $conn->prepare(
        "UPDATE chat_rooms
            SET closed_at=NOW()
          WHERE order_id=?"
    );
    $close->bind_param('i', $id);
    $close->execute();

    echo json_encode(['success'=>true,'message'=>'Drop-off recorded']);
    exit;
}

/* ─────────────── PUT ─────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $in = json_decode(file_get_contents('php://input'), true);
    if (empty($in['id'])) {
        echo json_encode(['success'=>false,'message'=>'Missing id']);
        exit;
    }
    $id = (int)$in['id'];

    // Rider confirms drop_off via PUT
    if (($in['action'] ?? '') === 'drop_off') {
        $stmt = $conn->prepare(
          "UPDATE requests
              SET status='completed'
            WHERE id=? AND status='accepted' AND accepted_by=?"
        );
        $stmt->bind_param('is', $id, $loggedInUser);
        $stmt->execute();

        $close = $conn->prepare(
          "UPDATE chat_rooms
              SET closed_at=NOW()
            WHERE order_id=?"
        );
        $close->bind_param('i', $id);
        $close->execute();

        echo json_encode([
          'success' => $stmt->affected_rows > 0,
          'message' => $stmt->affected_rows > 0
              ? 'Order dropped off successfully'
              : 'No matching accepted order found'

        ]);
        exit;
    }

    // Rider accepts an order: pending → accepted
    $conn->begin_transaction();

    $sel = $conn->prepare(
      "SELECT username
         FROM requests
        WHERE id=? AND status='pending' AND username!=?
        FOR UPDATE"
    );
    $sel->bind_param('is', $id, $loggedInUser);
    $sel->execute();
    $order = $sel->get_result()->fetch_assoc();
    if (!$order) {
      $conn->rollback();
      echo json_encode(['success'=>false,'message'=>'Order not available']);
      exit;
    }

    $upd = $conn->prepare(
      "UPDATE requests
          SET status='accepted', accepted_by=?
        WHERE id=?"
    );
    $upd->bind_param('si', $loggedInUser, $id);
    $upd->execute();

    $chat = $conn->prepare(
      "INSERT INTO chat_rooms (order_id,user_name,dasher_name)
           VALUES (?,?,?)"
    );
    $chat->bind_param('iss', $id, $order['username'], $loggedInUser);
    $chat->execute();
    $roomId = $chat->insert_id;

    $conn->commit();
    echo json_encode([
      'success' => true,
      'message' => 'Order accepted successfully.',
      'room_id' => $roomId
    ]);
    exit;
}

// Fallback
echo json_encode(['success'=>false,'message'=>'Invalid request method']);
$conn->close();
