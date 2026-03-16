<?php
$dsn  = 'mysql:host=' . getenv('MYSQLHOST') . 
        ';port=' . (int) getenv('MYSQLPORT') . 
        ';dbname=' . getenv('MYSQLDATABASE') . 
        ';charset=utf8mb4';
$user = getenv('MYSQLUSER');
$pass = getenv('MYSQLPASSWORD');

$pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);