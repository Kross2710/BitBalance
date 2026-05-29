<?php
require_once __DIR__ . '/_helpers.php';

api_require_method('GET');

$pdo = api_connect_db();
$user = api_require_auth($pdo);
$userId = (int) $user['user_id'];

try {
    $stmt = $pdo->prepare("
        SELECT conversation_id, title, created_at, updated_at
        FROM ai_conversation
        WHERE user_id = ?
        ORDER BY updated_at DESC
        LIMIT 100
    ");
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $conversations = [];
    foreach ($rows as $row) {
        $conversations[] = api_format_conversation($row);
    }

    api_send(true, $conversations);
} catch (Throwable $e) {
    error_log('API ai-coach conversations error: ' . $e->getMessage());
    api_error('Unable to load conversations.', 500);
}
