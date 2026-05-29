<?php
require_once __DIR__ . '/_helpers.php';

api_require_method('POST');

$pdo = api_connect_db();
$user = api_require_auth($pdo);
$userId = (int) $user['user_id'];

$data = api_request_data();
$conversationId = isset($data['conversation_id']) ? (int)$data['conversation_id'] : 0;

if ($conversationId <= 0) {
    api_error('Missing conversation_id.', 400);
}

try {
    if (!fetch_owned_conversation($pdo, $userId, $conversationId)) {
        api_error('Conversation not found.', 404);
    }

    // Delete images on disk
    $stmt = $pdo->prepare("SELECT image_path FROM ai_message WHERE conversation_id = ? AND image_path IS NOT NULL");
    $stmt->execute([$conversationId]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $url_path) {
        $fs = PROJECT_ROOT . ltrim($url_path, '/');
        if (is_file($fs)) {
            @unlink($fs);
        }
    }

    $pdo->prepare("DELETE FROM ai_conversation WHERE conversation_id = ?")->execute([$conversationId]);

    api_send(true, ['deleted_id' => $conversationId]);
} catch (Throwable $e) {
    error_log('API ai-coach delete error: ' . $e->getMessage());
    api_error('Unable to delete conversation.', 500);
}
