<?php
require_once __DIR__ . '/_helpers.php';

api_require_method('GET');

$pdo = api_connect_db();
$user = api_require_auth($pdo);
$userId = (int) $user['user_id'];

$conversationId = isset($_GET['conversation_id']) ? (int) $_GET['conversation_id'] : 0;
if ($conversationId <= 0) {
    api_error('Missing conversation_id.', 400);
}

try {
    $conv = fetch_owned_conversation($pdo, $userId, $conversationId);
    if (!$conv) {
        api_error('Conversation not found.', 404);
    }

    $stmt = $pdo->prepare("
        SELECT message_id, role, content, image_path, created_at
        FROM ai_message
        WHERE conversation_id = ?
        ORDER BY created_at ASC, message_id ASC
    ");
    $stmt->execute([$conversationId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $messages = [];
    foreach ($rows as $row) {
        $messages[] = api_format_message($row);
    }

    api_send(true, [
        'conversation' => api_format_conversation($conv),
        'messages' => $messages
    ]);
} catch (Throwable $e) {
    error_log('API ai-coach messages error: ' . $e->getMessage());
    api_error('Unable to load messages.', 500);
}
