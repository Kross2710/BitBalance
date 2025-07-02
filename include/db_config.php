<?php
$host = 'talsprddb02.int.its.rmit.edu.au';
$dbname = 'COSC3046_2502_G20';
$username = 'COSC3046_2502_G20';
$password = 'd9BeT2X7NUi9';
$port = 3306;

// XAMPP localhost settings
$host = 'localhost';
$dbname = 'test';
$username = 'root';
$password = '';
$port = 3306;

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>