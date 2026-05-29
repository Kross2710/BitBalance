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

// i18n bootstrap. Safe to load for both guests and logged-in users.
// Keep a tiny fallback so a partial RMIT deploy does not fatal before the
// include/i18n directory has been uploaded.
$i18nPath = __DIR__ . '/i18n/i18n.php';
if (is_file($i18nPath)) {
    require_once $i18nPath;
}

if (!function_exists('t_raw')) {
    function t_raw($key, $vars = [])
    {
        $msg = (string) $key;
        if (is_array($vars)) {
            foreach ($vars as $name => $value) {
                $msg = str_replace('{' . $name . '}', (string) $value, $msg);
            }
        }
        return $msg;
    }
}

if (!function_exists('t')) {
    function t($key, $vars = [])
    {
        return htmlspecialchars(t_raw($key, $vars), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('html_lang_attr')) {
    function html_lang_attr()
    {
        return 'en';
    }
}

if (function_exists('apply_locale') && function_exists('resolve_locale')) {
    apply_locale(resolve_locale($pdo ?? null, $user));
}
