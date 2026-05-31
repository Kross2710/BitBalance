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
    header('Location: admin-logs.php');
    exit;
}

$admin_id = (int)$_SESSION['user']['user_id'];
$days = max(1, min(365, (int)($_POST['days'] ?? 30)));
$token = $_POST['csrf_token'] ?? '';

function redirect_logs($message, $isError = false)
{
    $key = $isError ? 'error' : 'success';
    header('Location: admin-logs.php?' . $key . '=' . urlencode($message));
    exit;
}

if (!csrf_verify($token)) {
    redirect_logs('Invalid security token. Please retry.', true);
}

try {
    // 1. Count how many logs are about to be deleted
    $cutoff = date('Y-m-d H:i:s', strtotime("-$days days"));
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM activity_log WHERE created_at < ?");
    $stmtCount->execute([$cutoff]);
    $deletedCount = (int)$stmtCount->fetchColumn();

    if ($deletedCount === 0) {
        redirect_logs("No logs found older than $days days to prune.");
    }

    // 2. Perform the deletion
    $stmtDelete = $pdo->prepare("DELETE FROM activity_log WHERE created_at < ?");
    $stmtDelete->execute([$cutoff]);

    // 3. Log the prune action itself so it remains in the audit trail!
    log_attempt($pdo, $admin_id, 'pruning', "Admin pruned $deletedCount logs older than $days days", 'activity_log');

    redirect_logs("Successfully pruned $deletedCount activity logs older than $days days.");
} catch (PDOException $e) {
    error_log('prune-logs error: ' . $e->getMessage());
    redirect_logs('Database error occurred while pruning logs.', true);
}
