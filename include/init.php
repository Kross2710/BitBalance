<?php
// Define the project root directory
if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', dirname(__DIR__) . DIRECTORY_SEPARATOR);
}

// Define the base URL for the project (automatically detects the user SID if available)
if (!defined('BASE_URL')) {
    if (isset($_SERVER['REQUEST_URI']) && preg_match('#^/(~[^/]+/)?([^/]+)/#', $_SERVER['REQUEST_URI'], $matches)) {
        $prefix = isset($matches[1]) ? $matches[1] : '';
        $project = $matches[2];
        define('BASE_URL', '/' . $prefix . $project . '/');
    } else {
        define('BASE_URL', '/');
    }
}

// Start the session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user']);

if ($isLoggedIn) {
    // Include the database configuration file
    require_once __DIR__ . '/db_config.php';

    // Check the user's status
    $stmt = $pdo->prepare("SELECT status FROM userStatus WHERE user_id = :user_id");
    $stmt->bindParam(':user_id', $_SESSION['user']['user_id'], PDO::PARAM_INT);
    $stmt->execute();
    $userStatus = $stmt->fetchColumn();

    if ($userStatus === 'archived') {
        session_destroy();
        header('Location: ' . BASE_URL . 'login.php?error=Account+archived');
        exit;
    } elseif ($userStatus === 'banned') {
        session_destroy();
        header('Location: ' . BASE_URL . 'login.php?error=Account+banned');
        exit;
    }
}

$user = $isLoggedIn ? $_SESSION['user'] : null;
?>