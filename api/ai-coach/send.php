<?php
require_once __DIR__ . '/_helpers.php';

api_require_method('POST');

$pdo = api_connect_db();
$user = api_require_auth($pdo);
$userId = (int) $user['user_id'];

$data = api_request_data();
$message = isset($data['message']) ? trim((string)$data['message']) : '';
$conversationId = isset($data['conversation_id']) ? (int)$data['conversation_id'] : 0;

if ($message === '') {
    api_error('Message is empty.', 400);
}

try {
    // Rate limit check
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT message_count FROM ai_usage_daily WHERE user_id = ? AND usage_date = ?");
    $stmt->execute([$userId, $today]);
    $usedRaw = $stmt->fetchColumn();
    $used = (int)($usedRaw ? $usedRaw : 0);
    if ($used >= AI_COACH_DAILY_LIMIT) {
        api_error('Daily AI Coach limit reached (' . AI_COACH_DAILY_LIMIT . ' messages). Please try again tomorrow.', 429);
    }

    // Create or verify conversation
    $isNewConversation = false;
    if ($conversationId <= 0) {
        $stmt = $pdo->prepare("INSERT INTO ai_conversation (user_id, title) VALUES (?, 'New chat')");
        $stmt->execute([$userId]);
        $conversationId = (int)$pdo->lastInsertId();
        $isNewConversation = true;
    } else {
        if (!fetch_owned_conversation($pdo, $userId, $conversationId)) {
            api_error('Conversation not found.', 404);
        }
    }

    // Handle optional image upload
    $imageFile = $_FILES['image'] ?? null;
    $imageUrlPath = null;
    $imageFsPath = null;
    $imageMime = null;
    if ($imageFile && $imageFile['error'] === UPLOAD_ERR_OK) {
        [$imageUrlPath, $imageFsPath] = save_uploaded_image($imageFile, $userId);
        if (!$imageUrlPath) {
            api_error('Image upload failed (only JPG/PNG/WEBP up to 5MB allowed)', 400);
        }
        $imageMime = $imageFile['type'] ?? null;
    }

    // Save user message with optional image path
    $stmt = $pdo->prepare("
        INSERT INTO ai_message (conversation_id, role, content, image_path)
        VALUES (?, 'user', ?, ?)
    ");
    $stmt->execute([$conversationId, $message, $imageUrlPath]);
    $userMessageId = (int)$pdo->lastInsertId();

    // Load conversation history for context
    $historyLimit = defined('AI_COACH_HISTORY_TURNS') ? AI_COACH_HISTORY_TURNS * 2 : 20;
    $stmt = $pdo->prepare("
        SELECT role, content, image_path
        FROM ai_message
        WHERE conversation_id = ?
        ORDER BY created_at DESC, message_id DESC
        LIMIT ?
    ");
    $stmt->bindValue(1, $conversationId, PDO::PARAM_INT);
    $stmt->bindValue(2, $historyLimit, PDO::PARAM_INT);
    $stmt->execute();
    $history = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));

    // Build context and call Gemini
    $userContext = build_user_context($pdo, $userId);
    $clientNow = isset($data['client_now']) ? $data['client_now'] : ($_POST['client_now'] ?? null);
    $clientTzOffset = isset($data['client_tz_offset']) ? $data['client_tz_offset'] : ($_POST['client_tz_offset'] ?? null);
    $clientTimeInfo = build_client_time_info($clientNow, $clientTzOffset);

    // Call Gemini with the image payload
    $result = call_gemini($history, $userContext, $clientTimeInfo, $imageFsPath, $imageMime);

    if (!$result['ok']) {
        api_error(isset($result['error']) ? $result['error'] : 'AI error', 502);
    }

    // Extract food log block and clean text
    list($assistantText, $foodLogSuggestions) = extract_food_log_block($result['text']);

    // Save assistant message
    $stmt = $pdo->prepare("
        INSERT INTO ai_message (conversation_id, role, content)
        VALUES (?, 'assistant', ?)
    ");
    $stmt->execute([$conversationId, $assistantText]);
    $assistantMessageId = (int)$pdo->lastInsertId();

    // Update conversation timestamp
    $pdo->prepare("UPDATE ai_conversation SET updated_at = CURRENT_TIMESTAMP WHERE conversation_id = ?")
        ->execute([$conversationId]);

    // Auto-title new conversations
    if ($isNewConversation) {
        $autoTitle = aic_substr($message, 0, 60);
        $pdo->prepare("UPDATE ai_conversation SET title = ? WHERE conversation_id = ?")
            ->execute([$autoTitle, $conversationId]);
    }

    // Bump usage counter
    $pdo->prepare("
        INSERT INTO ai_usage_daily (user_id, usage_date, message_count)
        VALUES (?, ?, 1)
        ON DUPLICATE KEY UPDATE message_count = message_count + 1
    ")->execute([$userId, $today]);

    // Fetch the full message rows to return proper created_at timestamps
    $stmt = $pdo->prepare("SELECT message_id, role, content, image_path, created_at FROM ai_message WHERE message_id = ?");
    $stmt->execute([$userMessageId]);
    $userMsgRow = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt->execute([$assistantMessageId]);
    $assistantMsgRow = $stmt->fetch(PDO::FETCH_ASSOC);

    api_send(true, [
        'conversation_id' => $conversationId,
        'user_message' => api_format_message($userMsgRow),
        'assistant_message' => api_format_message($assistantMsgRow),
        'food_log_suggestions' => $foodLogSuggestions,
        'usage_today' => $used + 1,
        'daily_limit' => AI_COACH_DAILY_LIMIT,
    ], null, 201);
} catch (Throwable $e) {
    error_log('API ai-coach send error: ' . $e->getMessage());
    api_error('Unable to send message.', 500);
}
