<?php
require_once __DIR__ . '/include/init.php';
require_once __DIR__ . '/include/handlers/log_attempt.php';
require_once __DIR__ . '/include/db_config.php';

if (!isset($_SESSION['user'])) {
    die("Not logged in.");
}

$userId = $_SESSION['user']['user_id'];
$type = $_POST['type'] ?? '';
$targetId = $_POST['id'] ?? null;

if ($type && $targetId) {
    try {
        // Check if already liked
        $checkStmt = $pdo->prepare("SELECT * FROM forumLike WHERE user_id = ? AND type = ? AND target_id = ?");
        $checkStmt->execute([$userId, $type, $targetId]);

        if ($checkStmt->rowCount() > 0) { 
            // Unlike
            $delStmt = $pdo->prepare("DELETE FROM forumLike WHERE user_id = ? AND type = ? AND target_id = ?");
            $delStmt->execute([$userId, $type, $targetId]);

            // Log the unlike action
            log_attempt($pdo, $userId, 'unlike', "User $userId unliked $type with ID $targetId", 'forumLike', null);
        } else {
            // Like
            $insertStmt = $pdo->prepare("INSERT INTO forumLike (user_id, type, target_id) VALUES (?, ?, ?)");
            $insertStmt->execute([$userId, $type, $targetId]);

            // Log the like action
            log_attempt($pdo, $userId, 'like', "User $userId liked $type with ID $targetId", 'forumLike', null);
        }

        header('Location: forum.php');
        exit;
    } catch (PDOException $e) {
        die("Database error: " . $e->getMessage());
    }
} else {
    die("Invalid request.");
}
