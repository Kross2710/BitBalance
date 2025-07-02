<?php
require_once __DIR__ . '/../db_config.php';

// Function to log user log in attempts
function log_attempt($pdo, $user_id, $action_type, $description = '', $targetTable = null, $targetId = null)
{
    $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action_type, description, target_table, target_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $action_type, $description, $targetTable, $targetId]);
    if ($stmt->rowCount() > 0) {
        return true;
    } else {
        error_log("Failed to log attempt for user ID: $user_id");
        return false;
    }
}
?>