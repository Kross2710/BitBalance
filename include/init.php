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
    // Hardened session cookie parameters for enhanced security (XSS, CSRF, Secure transport)
    $secure = (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] == 1)) 
              || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user']);

if ($isLoggedIn) {
    // Include the database configuration file
    require_once __DIR__ . '/db_config.php';
    // Include the secrets file
    require_once __DIR__ . '/secrets.php';

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