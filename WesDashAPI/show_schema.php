<?php
$conn = new mysqli(
    getenv('MYSQLHOST'),
    getenv('MYSQLUSER'),
    getenv('MYSQLPASSWORD'),
    getenv('MYSQLDATABASE'),
    getenv('MYSQLPORT')
);
if ($conn->connect_error) {
    echo 'Connection failed: ' . $conn->connect_error;
    exit;
}

$sql = 'DESCRIBE requests';
$result = $conn->query($sql);

if ($result) {
    echo '<h2>requests table schema:</h2>';
    echo '<table border="1">';
    echo '<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>';
    
    while($row = $result->fetch_assoc()) {
        echo '<tr>';
        echo '<td>' . $row['Field'] . '</td>';
        echo '<td>' . $row['Type'] . '</td>';
        echo '<td>' . $row['Null'] . '</td>';
        echo '<td>' . $row['Key'] . '</td>';
        echo '<td>' . $row['Default'] . '</td>';
        echo '<td>' . $row['Extra'] . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
} else {
    echo 'Error: ' . $conn->error;
}

$conn->close();
?>
