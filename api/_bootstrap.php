<?php
if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', dirname(__DIR__) . DIRECTORY_SEPARATOR);
}

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

if (session_status() == PHP_SESSION_NONE) {
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

// Persistent "remember me" auto-login (mirrors include/init.php): re-establish
// the session from a valid remember cookie BEFORE any endpoint checks auth, so
// API calls survive an expired session just like full page loads do.
if (!isset($_SESSION['user']) && !empty($_COOKIE['bb_remember'])) {
    require_once PROJECT_ROOT . 'include/handlers/remember_token.php';
    remember_login(api_connect_db());
}

function api_send($ok, $data = null, $message = null, $statusCode = 200)
{
    http_response_code($statusCode);
    echo json_encode([
        'ok' => (bool) $ok,
        'data' => $data,
        'message' => $message
    ]);
    exit;
}

function api_error($message, $statusCode = 400, $data = null)
{
    api_send(false, $data, $message, $statusCode);
}

function api_require_method($method)
{
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        header('Allow: ' . $method);
        api_error('Method not allowed.', 405);
    }
}

function api_request_data()
{
    $data = $_POST;
    $contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';

    if (strpos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);
        if (is_array($json)) {
            $data = $json;
        }
    }

    return $data;
}

function api_public_user(array $row)
{
    return [
        'user_id' => (int) $row['user_id'],
        'handle' => isset($row['user_name']) ? $row['user_name'] : '',
        'user_name' => isset($row['user_name']) ? $row['user_name'] : '',
        'first_name' => isset($row['first_name']) ? $row['first_name'] : '',
        'last_name' => isset($row['last_name']) ? $row['last_name'] : null,
        'email' => isset($row['email']) ? $row['email'] : '',
        'role' => isset($row['role']) ? $row['role'] : 'user',
        'profile_image' => isset($row['profile_image']) ? $row['profile_image'] : null,
        'theme_preference' => isset($row['theme_preference']) ? $row['theme_preference'] : 'system',
        'needs_onboarding' => !empty($row['needs_onboarding'])
    ];
}

function api_destroy_session()
{
    // Also drop the persistent remember-me cookie so the next request isn't
    // silently re-authenticated.
    require_once PROJECT_ROOT . 'include/handlers/remember_token.php';
    remember_delete_cookie();

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

function api_current_user_row(PDO $pdo)
{
    if (!isset($_SESSION['user']) || !isset($_SESSION['user']['user_id'])) {
        return null;
    }

    $stmt = $pdo->prepare("
        SELECT u.user_id, u.user_name, u.first_name, u.last_name, u.email, u.role, u.profile_image,
               us.status, us.theme_preference,
               CASE
                   WHEN NOT EXISTS (SELECT 1 FROM userGoal ug WHERE ug.user_id = u.user_id LIMIT 1)
                     OR NOT EXISTS (SELECT 1 FROM userPhysicalInfo upi WHERE upi.user_id = u.user_id LIMIT 1)
                   THEN 1 ELSE 0
               END AS needs_onboarding
        FROM user u
        JOIN userStatus us ON u.user_id = us.user_id
        WHERE u.user_id = ?
        LIMIT 1
    ");
    $stmt->execute([(int) $_SESSION['user']['user_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        api_destroy_session();
        return null;
    }

    if ($row['status'] === 'archived' || $row['status'] === 'banned') {
        require_once PROJECT_ROOT . 'include/handlers/remember_token.php';
        remember_revoke_all($pdo, (int) $row['user_id']);
        api_destroy_session();
        api_error('This account is not active.', 403);
    }

    $_SESSION['user'] = api_public_user($row);
    return $row;
}

function api_require_auth(PDO $pdo)
{
    $row = api_current_user_row($pdo);
    if (!$row) {
        api_error('Authentication required.', 401);
    }

    return $row;
}

function api_connect_db()
{
    global $pdo;

    if (isset($pdo) && $pdo instanceof PDO) {
        return $pdo;
    }

    if (!defined('BITBALANCE_API_REQUEST')) {
        define('BITBALANCE_API_REQUEST', true);
    }

    try {
        require PROJECT_ROOT . 'include/db_config.php';
    } catch (PDOException $e) {
        error_log('API database connection error: ' . $e->getMessage());
        api_error('Database unavailable. Please try again.', 503);
    }

    return $pdo;
}
