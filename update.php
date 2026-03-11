<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['username'])) {
    die("Please log in before editing a request.");
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
    die("Database connection failed: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id              = $_POST['id']               ?? 0;
    $item            = $_POST['item']             ?? '';
    $dropOffLocation = $_POST['drop_off_location']?? '';
    $deliverySpeed   = $_POST['delivery_speed']   ?? 'common';

    if (empty($item) || empty($dropOffLocation)) {
        die("Item and Drop-off location cannot be empty!");
    }

    $checkSql = "SELECT username FROM requests WHERE id=? LIMIT 1";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("i", $id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows === 1) {
        $checkRow = $checkResult->fetch_assoc();
        if ($checkRow['username'] !== $username) {
            die("You are not the owner of this record!");
        }

        $updateSql = "UPDATE requests
                      SET item=?, drop_off_location=?, delivery_speed=?
                      WHERE id=?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("sssi", $item, $dropOffLocation, $deliverySpeed, $id);

        if ($updateStmt->execute()) {
            header("Location: read.php?msg=updated");
            exit;
        } else {
            die("Update failed: " . $updateStmt->error);
        }

    } else {
        die("Record not found in requests table.");
    }

} else {
    $id = $_GET['id'] ?? 0;
    if (!$id) {
        die("No 'id' parameter provided.");
    }

    $sql = "SELECT * FROM requests WHERE id=? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();

        if ($row['username'] !== $username) {
            die("You don't own this record!");
        }

        ?>
        <!DOCTYPE html>
        <html>
        <head>
          <meta charset="UTF-8">
          <title>Update Request</title>
        </head>
        <body>
        <h2>Update Request</h2>
        <form method="POST" action="update.php">
            <input type="hidden" name="id" value="<?php echo $row['id']; ?>">

            <label>Item:</label>
            <input type="text" name="item" 
                   value="<?php echo htmlspecialchars($row['item']); ?>" required><br><br>

            <label>Drop-off location:</label>
            <input type="text" name="drop_off_location" 
                   value="<?php echo htmlspecialchars($row['drop_off_location']); ?>" required><br><br>

            <label>Delivery Speed:</label>
            <input type="radio" name="delivery_speed" value="urgent"
              <?php if($row['delivery_speed'] === 'urgent') echo 'checked'; ?>> Urgent
            <input type="radio" name="delivery_speed" value="common"
              <?php if($row['delivery_speed'] === 'common') echo 'checked'; ?>> Common
            <br><br>

            <button type="submit">Save Changes</button>
        </form>
        </body>
        </html>
        <?php

    } else {
        die("Record not found in requests table.");
    }
}

$conn->close();
?>
