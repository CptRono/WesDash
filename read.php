<?php

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);


if (!isset($_SESSION['username'])) {
    die("Please log in to view your requests.");
}
$username = $_SESSION['username'];


$conn = new mysqli(
    getenv('MYSQLHOST'),
    getenv('MYSQLUSER'),
    getenv('MYSQLPASSWORD'),
    getenv('MYSQLDATABASE'),
    getenv('MYSQLPORT')
);
if ($conn->connect_error) {
    die("DB connection failed: " . $conn->connect_error);
}


$sql = "SELECT * FROM requests WHERE username=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>My Requests</title>
</head>
<body>
<p style="font-weight: bold; color: blue;">You are logged in as: <?php echo htmlspecialchars($username); ?></p>
<h2>My WesDash Requests</h2>
<table border="1">
    <tr>
        <th>ID</th>
        <th>Item</th>
        <th>Drop-off Location</th>
        <th>Delivery Speed</th>
        <th>Status</th>
        <th>Created At</th>
        <th>Edit</th>
    </tr>
    <?php
 
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['item'] . "</td>";
        echo "<td>" . $row['drop_off_location'] . "</td>";
        echo "<td>" . $row['delivery_speed'] . "</td>";
        echo "<td>" . $row['status'] . "</td>";
        echo "<td>" . $row['created_at'] . "</td>";

        echo "<td><a href='update.php?id=" . $row['id'] . "'>Edit</a></td>";
        echo "</tr>";
    }
    ?>
</table>
<a href="create_requests.php">
    <button>Create New Request</button>
</a>
<a href="manage_requests.php">
    <button>Accept Orders</button>
</a>
<a href="delete_requests.php">
    <button>Cancel Order</button>
</a>  
<a href="logout.php">
    <button type="button">Logout</button>
</a>
<a href="dashboard.php">
    <button type="button">Dashboard</button>
</a>
</body>
</html>
<?php
$stmt->close();
$conn->close();
?>
