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
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("SET time_zone = '+07:00';");
} catch(PDOException $e) {
    if (defined('BITBALANCE_API_REQUEST') && BITBALANCE_API_REQUEST) {
        throw $e;
    }
    die("Connection failed: " . $e->getMessage());
}
?>
