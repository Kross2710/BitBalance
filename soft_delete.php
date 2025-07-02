<?php
session_start();
require_once __DIR__ . '/include/db_config.php';

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$type = $_POST['type'] ?? null;
$id   = $_POST['id'] ?? null;

if (!$type || !$id) {
    http_response_code(400);
    exit('Invalid request');
}

$allowedTypes = ['post', 'comment'];
if (!in_array($type, $allowedTypes)) {
    http_response_code(400);
    exit('Invalid type');
}

$table = $type === 'post' ? 'forumPost' : 'forumComment';
$idCol = $type === 'post' ? 'post_id' : 'comment_id';

$stmt = $pdo->prepare("UPDATE $table SET status = 'archived' WHERE $idCol = ? AND user_id = ?");
$stmt->execute([$id, $_SESSION['user']['user_id']]);

header("Location: forum.php");
exit;
?>
