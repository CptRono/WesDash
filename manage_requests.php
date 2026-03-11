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

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

if (!isset($_SESSION['username'])) {
    die("Please log in to manage requests.");
}

$username = $_SESSION['username'];

$checkColumn = $conn->query("SHOW COLUMNS FROM requests LIKE 'accepted_by'");
if ($checkColumn->num_rows == 0) {
    $conn->query("ALTER TABLE requests ADD accepted_by VARCHAR(255) DEFAULT NULL");
}

$sql = "SELECT * FROM requests WHERE status = 'pending'";
$result = $conn->query($sql);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['accept'])) {
        $requestId = $_POST['request_id'];
        
        $updateSql = "UPDATE requests SET status = 'accepted', accepted_by = ? WHERE id = ?";
        $stmt = $conn->prepare($updateSql);
        $stmt->bind_param("si", $username, $requestId);
        $stmt->execute();
        
        // Add orders accepted by a dasher to the tasks table
        $stmtFetchRequest = "SELECT id, username, item FROM requests WHERE id=?";
        $stmtFetchRequest = $conn->prepare($stmtFetchRequest);
        $stmtFetchRequest->bind_param("i", $requestId);
        $stmtFetchRequest->execute();
        $stmtFetchRequest = $stmtFetchRequest->get_result()->fetch_assoc();

        $stmtUpdateTasks = "INSERT INTO tasks (request_id, username, dashername, item, status)
                            VALUES (?,?,?,?,'accepted')";
        $stmtUpdateTasks = $conn->prepare($stmtUpdateTasks);
        $stmtUpdateTasks->bind_param("isss", $requestId, $username, $username, $stmtFetchRequest['item']);
        $stmtUpdateTasks->execute();


        header("Location: order_countdown.php?request_id=$requestId");
        exit;
    }
}

?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8" />
    <title>Manage Requests</title>
</head>
<body>
<p style="font-weight: bold; color: blue;">You are logged in as: <?php echo htmlspecialchars($username); ?></p>

<h1>Manage Pending Orders</h1>

<?php
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<p>";
        echo "Request ID: " . $row['id'] . 
        " - Item: " . $row['item'] . 
        " - Delivery Speed: " . ucfirst($row['delivery_speed']) . 
        " - Status: " . $row['status'] . 
        " - Accepted By: " . ($row['accepted_by'] ?? 'N/A');
   
        if ($row['status'] === 'pending') {
            echo " <form method='POST' style='display:inline;'>
                    <input type='hidden' name='request_id' value='" . $row['id'] . "'>
                    <button type='submit' name='accept'>Accept Order</button>
                    </form>";
        }

        echo "</p>";
    }
} else {
    echo "No pending requests.";
}

$conn->close();
?>

<a href="create_requests.php">
    <button>Create New Request</button>
</a>
<a href="delete_requests.php">
    <button>Cancel Order</button>
</a>  
<a href ="read.php">
    <button type="button">View all requests</button>
</body>
<a href="logout.php">
    <button type="button">Logout</button>
</a>

</body>
</html>