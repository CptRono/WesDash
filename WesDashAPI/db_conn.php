<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "HOST: " . getenv('MYSQLHOST') . "<br>";
echo "PORT: " . getenv('MYSQLPORT') . "<br>";
echo "DB: " . getenv('MYSQLDATABASE') . "<br>";
echo "USER: " . getenv('MYSQLUSER') . "<br>";

$dsn  = 'mysql:host=' . getenv('MYSQLHOST') . 
        ';port=' . getenv('MYSQLPORT') . 
        ';dbname=' . getenv('MYSQLDATABASE') . 
        ';charset=utf8mb4';
$user = getenv('MYSQLUSER');
$pass = getenv('MYSQLPASSWORD');

$pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);