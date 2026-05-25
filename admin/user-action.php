<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../include/init.php';
require_once __DIR__ . '/../include/db_config.php';
require_once __DIR__ . '/../include/csrf.php';
require_once __DIR__ . '/../include/handlers/log_attempt.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    http_response_code(403);
    exit('Access denied');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin-users.php');
    exit;
}

$admin_id = (int) $_SESSION['user']['user_id'];
$user_id  = (int) ($_POST['user_id'] ?? 0);
$action   = $_POST['action'] ?? '';
$token    = $_POST['csrf_token'] ?? '';

function redirect_users($message, $isError = false)
{
    $key = $isError ? 'error' : 'success';
    header('Location: admin-users.php?' . $key . '=' . urlencode($message));
    exit;
}

if (!csrf_verify($token)) {
    redirect_users('Invalid security token. Please retry.', true);
}

if ($user_id <= 0) {
    redirect_users('Invalid user id.', true);
}

if ($user_id === $admin_id && in_array($action, ['ban', 'archive'], true)) {
    redirect_users('You cannot ban or archive your own account.', true);
}

$stmt = $pdo->prepare("SELECT u.user_name, u.email, u.first_name, s.status
                       FROM user u
                       LEFT JOIN userStatus s ON s.user_id = u.user_id
                       WHERE u.user_id = ?");
$stmt->execute([$user_id]);
$target = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$target) {
    redirect_users('User not found.', true);
}

try {
    switch ($action) {
        case 'ban':
        case 'unban':
        case 'archive':
        case 'restore':
            $statusMap = [
                'ban'     => 'banned',
                'unban'   => 'active',
                'archive' => 'archived',
                'restore' => 'active',
            ];
            $newStatus = $statusMap[$action];

            $upd = $pdo->prepare("UPDATE userStatus SET status = ?, archived_at = CASE WHEN ? = 'archived' THEN NOW() ELSE archived_at END WHERE user_id = ?");
            $upd->execute([$newStatus, $newStatus, $user_id]);

            log_attempt($pdo, $admin_id,
                $action . '_user',
                'Admin ' . $admin_id . ' set status=' . $newStatus . ' for user ' . $user_id,
                'userStatus', $user_id);

            redirect_users('User "' . $target['user_name'] . '" set to ' . $newStatus . '.');
            break;

        case 'reset_password':
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $ins = $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at, used) VALUES (?, ?, ?, 0)");
            $ins->execute([$user_id, $token, $expires]);

            log_attempt($pdo, $admin_id,
                'reset_password',
                'Admin ' . $admin_id . ' issued password reset link for user ' . $user_id,
                'user', $user_id);

            $host = $_SERVER['HTTP_HOST'];
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $resetLink = $scheme . '://' . $host . BASE_URL . 'reset_password.php?token=' . $token;

            $_SESSION['flash_reset_link'] = [
                'user_name' => $target['user_name'],
                'link'      => $resetLink,
                'expires'   => $expires,
            ];

            redirect_users('Reset link generated for ' . $target['user_name'] . '. Copy it from the dialog.');
            break;

        default:
            redirect_users('Unknown action.', true);
    }
} catch (PDOException $e) {
    error_log('user-action error: ' . $e->getMessage());
    redirect_users('Database error.', true);
}
