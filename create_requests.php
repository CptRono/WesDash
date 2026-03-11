<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

$conn = new mysqli(
    getenv('MYSQLHOST'),
    getenv('MYSQLUSER'),
    getenv('MYSQLPASSWORD'),
    getenv('MYSQLDATABASE'),
    getenv('MYSQLPORT')
);

//must login first
if (!isset($_SESSION['username'])) {
    die("Please log in before creating a request.");
}



if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item              = $_POST['item']              ?? '';
    $dropOffLocation   = $_POST['drop_off_location'] ?? '';
    $deliverySpeed     = $_POST['delivery_speed']     ?? 'common';
    $status            = 'pending'; 
    $createdAt         = date('Y-m-d H:i:s');  

    $username = $_SESSION['username'];

 
    if (empty($item) || empty($dropOffLocation)) {
        die("Item and Drop-off location cannot be empty!");
    }


    $sql = "INSERT INTO requests (username, item, drop_off_location, delivery_speed, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }


    $stmt->bind_param("ssssss", $username, $item, $dropOffLocation, $deliverySpeed, $status, $createdAt);

    if ($stmt->execute()) {
        //Ada: changed the location to a valid new site
        header("Location: delete_requests.php");
        exit;
    } else {
        die("Insert failed: " . $stmt->error);
    }
} 
if (!isset($_SESSION['username'])) {
    die("Please log in before creating a request.");
}
$username = $_SESSION['username'] ?? ''; 

?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8" />
  <title>Create a new request</title>
</head>
<body>
<p style="font-weight: bold; color: blue;">You are logged in as: <?php echo htmlspecialchars($username); ?></p>

<h1>Create a new request</h1>
<form method="POST" action="create_requests.php">
    <label>Item:</label>
    <input type="text" name="item" required><br><br>

    <label>Drop-off location:</label>
    <input type="text" name="drop_off_location" required><br><br>


    <label>Delivery Speed:</label>
    <input type="radio" name="delivery_speed" value="urgent"> Urgent
    <input type="radio" name="delivery_speed" value="common" checked> Common
    <br><br>

    <button type="submit">Create</button>

</form>


<a href="manage_requests.php">
    <button>Accept Orders</button>
</a>

<a href="delete_requests.php">
    <button>Cancel Order</button>
</a>  

<a href ="read.php">
    <button type="button">View all requests</button>
</a>


<a href="logout.php">
    <button type="button">Logout</button>
</a>
</body>
</html>
