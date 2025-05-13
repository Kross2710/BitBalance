<?php
session_start(); // Start the session

// Simulate user login
if (!isset($_SESSION['user'])) {
    // $_SESSION['user'] = ['firstname' => 'Alice'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BitBalance</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://kit.fontawesome.com/b94f65ead2.js" crossorigin="anonymous"></script>
</head>

<body>
    <?php
    // Include the header file
    include 'header.php';
    ?>