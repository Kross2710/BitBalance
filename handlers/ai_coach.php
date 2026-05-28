<?php
/**
 * AI Coach — backend handler (multi-action).
 *
 * Routes via ?action=<name>. All routes require an authenticated session.
 * Responds with JSON: { ok: bool, ... }
 *
 *   ?action=list_conversations       GET  -> { ok, conversations: [...] }
 *   ?action=get_conversation&id=N    GET  -> { ok, conversation: {...}, messages: [...] }
 *   ?action=create_conversation      POST -> { ok, conversation_id }
 *   ?action=send_message             POST (multipart) -> { ok, conversation_id, user_message, assistant_message, usage_today }
 *       form-data: conversation_id (int|empty), message (string), image (file optional), csrf_token
 *   ?action=rename_conversation      POST -> { ok }
 *       form-data: conversation_id, title, csrf_token
 *   ?action=delete_conversation      POST -> { ok }
 *       form-data: conversation_id, csrf_token
 */

require_once __DIR__ . '/../include/init.php';
require_once __DIR__ . '/../include/csrf.php';
require_once __DIR__ . '/../include/handlers/ai_context.php';

header('Content-Type: application/json; charset=utf-8');

// ---- UTF-8 safe string helpers (mbstring extension is NOT available on RMIT host) ----
if (!function_exists('aic_strlen')) {
    function aic_strlen(string $s): int {
        if (function_exists('mb_strlen')) return mb_strlen($s, 'UTF-8');
        // Count UTF-8 codepoints via regex
        $n = preg_match_all('/./us', $s);
        return $n === false ? strlen($s) : $n;
    }
}
if (!function_exists('aic_substr')) {
    function aic_substr(string $s, int $start, ?int $len = null): string {
        if (function_exists('mb_substr')) return mb_substr($s, $start, $len, 'UTF-8');
        if (function_exists('iconv_substr')) {
            return $len === null
                ? (string)iconv_substr($s, $start, PHP_INT_MAX, 'UTF-8')
                : (string)iconv_substr($s, $start, $len, 'UTF-8');
        }
        // Last-resort regex split
        if (!preg_match_all('/./us', $s, $m)) return substr($s, $start, $len ?? PHP_INT_MAX);
        $chars = $m[0];
        return implode('', array_slice($chars, $start, $len));
    }
}

// ---- Auth guard ----
if (!$isLoggedIn) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}
$user_id = (int)$_SESSION['user']['user_id'];
$action  = $_GET['action'] ?? '';

// ---- CSRF guard for state-changing actions ----
$writeActions = ['create_conversation', 'send_message', 'stream_message', 'rename_conversation', 'delete_conversation'];
if (in_array($action, $writeActions, true)) {
    $token = $_POST['csrf_token'] ?? '';
    if (!csrf_verify($token)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
}

try {
    switch ($action) {
        case 'list_conversations':   list_conversations($pdo, $user_id); break;
        case 'get_conversation':     get_conversation($pdo, $user_id);   break;
        case 'create_conversation':  create_conversation($pdo, $user_id); break;
        case 'send_message':         send_message($pdo, $user_id);       break;
        case 'stream_message':       stream_message($pdo, $user_id);     break;
        case 'rename_conversation':  rename_conversation($pdo, $user_id); break;
        case 'delete_conversation':  delete_conversation($pdo, $user_id); break;
        default:
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Unknown action']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}

// =========================================================
// Actions
// =========================================================

function list_conversations(PDO $pdo, int $user_id): void
{
    $stmt = $pdo->prepare("
        SELECT conversation_id, title, created_at, updated_at
        FROM ai_conversation
        WHERE user_id = ?
        ORDER BY updated_at DESC
        LIMIT 100
    ");
    $stmt->execute([$user_id]);
    echo json_encode([
        'ok' => true,
        'conversations' => $stmt->fetchAll(PDO::FETCH_ASSOC),
    ]);
}

function get_conversation(PDO $pdo, int $user_id): void
{
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Missing conversation id']);
        return;
    }

    $conv = fetch_owned_conversation($pdo, $user_id, $id);
    if (!$conv) {
        echo json_encode(['ok' => false, 'error' => 'Not found']);
        return;
    }

    $stmt = $pdo->prepare("
        SELECT message_id, role, content, image_path, created_at
        FROM ai_message
        WHERE conversation_id = ?
        ORDER BY created_at ASC, message_id ASC
    ");
    $stmt->execute([$id]);
    echo json_encode([
        'ok' => true,
        'conversation' => $conv,
        'messages'     => $stmt->fetchAll(PDO::FETCH_ASSOC),
    ]);
}

function create_conversation(PDO $pdo, int $user_id): void
{
    $stmt = $pdo->prepare("INSERT INTO ai_conversation (user_id, title) VALUES (?, 'New chat')");
    $stmt->execute([$user_id]);
    echo json_encode(['ok' => true, 'conversation_id' => (int)$pdo->lastInsertId()]);
}

function rename_conversation(PDO $pdo, int $user_id): void
{
    $id    = (int)($_POST['conversation_id'] ?? 0);
    $title = trim((string)($_POST['title'] ?? ''));
    if ($id <= 0 || $title === '') {
        echo json_encode(['ok' => false, 'error' => 'Missing id or title']);
        return;
    }
    if (aic_strlen($title) > 120) {
        $title = aic_substr($title, 0, 120);
    }
    if (!fetch_owned_conversation($pdo, $user_id, $id)) {
        echo json_encode(['ok' => false, 'error' => 'Not found']);
        return;
    }
    $stmt = $pdo->prepare("UPDATE ai_conversation SET title = ? WHERE conversation_id = ?");
    $stmt->execute([$title, $id]);
    echo json_encode(['ok' => true]);
}

function delete_conversation(PDO $pdo, int $user_id): void
{
    $id = (int)($_POST['conversation_id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Missing id']);
        return;
    }
    if (!fetch_owned_conversation($pdo, $user_id, $id)) {
        echo json_encode(['ok' => false, 'error' => 'Not found']);
        return;
    }

    // Delete on-disk images for messages in this conversation before DB cascade
    $stmt = $pdo->prepare("SELECT image_path FROM ai_message WHERE conversation_id = ? AND image_path IS NOT NULL");
    $stmt->execute([$id]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $url_path) {
        $fs = __DIR__ . '/../' . ltrim($url_path, '/');
        if (is_file($fs)) {
            @unlink($fs);
        }
    }

    $pdo->prepare("DELETE FROM ai_conversation WHERE conversation_id = ?")->execute([$id]);
    echo json_encode(['ok' => true]);
}

function send_message(PDO $pdo, int $user_id): void
{
    $prep = prepare_message_request($pdo, $user_id);
    if (!$prep['ok']) {
        if (!empty($prep['status'])) http_response_code($prep['status']);
        echo json_encode([
            'ok' => false,
            'error' => $prep['error'],
            'usage_today' => $prep['usage_today'] ?? null,
            'daily_limit' => $prep['daily_limit'] ?? null,
            'conversation_id' => $prep['conversation_id'] ?? null,
        ]);
        return;
    }

    // ---- Call Gemini (non-streaming) ----
    $userContext = build_user_context($pdo, $user_id);
    $clientTimeInfo = build_client_time_info($_POST['client_now'] ?? null, $_POST['client_tz_offset'] ?? null);
    $result = call_gemini(
        $prep['history'], $userContext, $clientTimeInfo,
        $prep['image_fs_path'], $prep['image_mime']
    );

    if (!$result['ok']) {
        echo json_encode([
            'ok' => false,
            'error' => $result['error'],
            'conversation_id' => $prep['conversation_id'],
        ]);
        return;
    }

    [$assistantText, $foodLogSuggestions] = extract_food_log_block($result['text']);
    $assistant_message_id = finalize_assistant_message(
        $pdo, $prep['conversation_id'], $prep['is_new_conversation'],
        $prep['message'], $assistantText, $user_id, $prep['today']
    );

    echo json_encode([
        'ok' => true,
        'conversation_id' => $prep['conversation_id'],
        'user_message' => [
            'message_id' => $prep['user_message_id'],
            'role'       => 'user',
            'content'    => $prep['message'],
            'image_path' => $prep['image_url_path'],
        ],
        'assistant_message' => [
            'message_id' => $assistant_message_id,
            'role'       => 'assistant',
            'content'    => $assistantText,
        ],
        'food_log_suggestions' => $foodLogSuggestions,
        'usage_today' => $prep['used'] + 1,
        'daily_limit' => AI_COACH_DAILY_LIMIT,
    ]);
}

/**
 * Streaming variant of send_message. Responds with Server-Sent Events:
 *   event: meta   {conversation_id, user_message_id, image_path}
 *   event: chunk  {text}                          (zero or more)
 *   event: done   {assistant_message_id, food_log_suggestions, usage_today, daily_limit}
 *   event: error  {error}                         (terminal)
 *
 * Why SSE: lets us re-use the standard HTTP/POST/multipart pipeline (image
 * uploads still work) while pushing token deltas as they arrive from Gemini.
 * The FOOD_LOG block is stripped server-side with a hold-back buffer so the
 * raw [[FOOD_LOG]] marker never reaches the browser mid-stream.
 */
function stream_message(PDO $pdo, int $user_id): void
{
    // Switch response from JSON (set globally up top) to SSE before any echo.
    @ini_set('zlib.output_compression', '0');
    @ini_set('output_buffering', 'off');
    @ini_set('implicit_flush', '1');
    while (ob_get_level() > 0) @ob_end_flush();
    @ob_implicit_flush(1);

    header('Content-Type: text/event-stream; charset=utf-8');
    header('Cache-Control: no-cache, no-transform');
    header('X-Accel-Buffering: no'); // nginx hint; harmless on Apache
    header('Connection: keep-alive');

    $prep = prepare_message_request($pdo, $user_id);
    if (!$prep['ok']) {
        sse_send('error', ['error' => $prep['error']]);
        return;
    }

    // Release session lock so the user can keep navigating during the stream.
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    sse_send('meta', [
        'conversation_id' => $prep['conversation_id'],
        'user_message_id' => $prep['user_message_id'],
        'image_path'      => $prep['image_url_path'],
    ]);

    $userContext    = build_user_context($pdo, $user_id);
    $clientTimeInfo = build_client_time_info($_POST['client_now'] ?? null, $_POST['client_tz_offset'] ?? null);

    // Accumulator + hold-back state — closed over by the write callback.
    $accumulator = '';
    $emittedLen  = 0;
    $dropping    = false;
    $marker      = '[[FOOD_LOG]]';
    $markerLen   = strlen($marker);

    $onDelta = function (string $delta) use (&$accumulator, &$emittedLen, &$dropping, $marker, $markerLen) {
        if ($delta === '' || $dropping) {
            $accumulator .= $delta;
            return;
        }
        $accumulator .= $delta;
        $pos = strpos($accumulator, $marker, $emittedLen);
        if ($pos !== false) {
            $toEmit = substr($accumulator, $emittedLen, $pos - $emittedLen);
            if ($toEmit !== '') sse_send('chunk', ['text' => $toEmit]);
            $emittedLen = $pos;
            $dropping = true;
            return;
        }
        // Hold back trailing chars in case the marker is split across deltas.
        $safeEnd = strlen($accumulator) - $markerLen;
        if ($safeEnd > $emittedLen) {
            $toEmit = substr($accumulator, $emittedLen, $safeEnd - $emittedLen);
            sse_send('chunk', ['text' => $toEmit]);
            $emittedLen = $safeEnd;
        }
    };

    $streamResult = call_gemini_stream(
        $prep['history'], $userContext, $clientTimeInfo,
        $prep['image_fs_path'], $prep['image_mime'], $onDelta
    );

    if (!$streamResult['ok']) {
        sse_send('error', ['error' => $streamResult['error']]);
        return;
    }

    // Flush remaining held-back chars (if no FOOD_LOG block was seen).
    if (!$dropping && $emittedLen < strlen($accumulator)) {
        $tail = substr($accumulator, $emittedLen);
        if ($tail !== '') sse_send('chunk', ['text' => $tail]);
        $emittedLen = strlen($accumulator);
    }

    [$assistantText, $foodLogSuggestions] = extract_food_log_block($accumulator);
    if ($assistantText === '') {
        sse_send('error', ['error' => 'AI returned empty response']);
        return;
    }

    $assistant_message_id = finalize_assistant_message(
        $pdo, $prep['conversation_id'], $prep['is_new_conversation'],
        $prep['message'], $assistantText, $user_id, $prep['today']
    );

    sse_send('done', [
        'assistant_message_id' => $assistant_message_id,
        'food_log_suggestions' => $foodLogSuggestions,
        'usage_today'          => $prep['used'] + 1,
        'daily_limit'          => AI_COACH_DAILY_LIMIT,
    ]);
}

/**
 * Shared prep for send_message + stream_message:
 *   - validate input
 *   - enforce daily rate limit
 *   - create/load conversation
 *   - save uploaded image
 *   - save user message
 *   - build history for the model
 *
 * Returns ['ok' => true, ...payload] on success, or
 *         ['ok' => false, 'error' => string, 'status' => ?int, ...].
 */
function prepare_message_request(PDO $pdo, int $user_id): array
{
    $conversation_id = (int)($_POST['conversation_id'] ?? 0);
    $message         = trim((string)($_POST['message'] ?? ''));
    $imageFile       = $_FILES['image'] ?? null;

    if ($message === '' && empty($imageFile['name'])) {
        return ['ok' => false, 'error' => 'Message is empty'];
    }

    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT message_count FROM ai_usage_daily WHERE user_id = ? AND usage_date = ?");
    $stmt->execute([$user_id, $today]);
    $used = (int)($stmt->fetchColumn() ?: 0);
    if ($used >= AI_COACH_DAILY_LIMIT) {
        return [
            'ok' => false,
            'status' => 429,
            'error' => 'Daily AI Coach limit reached (' . AI_COACH_DAILY_LIMIT . ' messages). Please try again tomorrow.',
            'usage_today' => $used,
            'daily_limit' => AI_COACH_DAILY_LIMIT,
        ];
    }

    if ($conversation_id <= 0) {
        $stmt = $pdo->prepare("INSERT INTO ai_conversation (user_id, title) VALUES (?, 'New chat')");
        $stmt->execute([$user_id]);
        $conversation_id = (int)$pdo->lastInsertId();
        $isNewConversation = true;
    } else {
        if (!fetch_owned_conversation($pdo, $user_id, $conversation_id)) {
            return ['ok' => false, 'error' => 'Conversation not found'];
        }
        $isNewConversation = false;
    }

    $image_url_path = null;
    $image_fs_path  = null;
    $image_mime     = null;
    if ($imageFile && $imageFile['error'] === UPLOAD_ERR_OK) {
        [$image_url_path, $image_fs_path] = save_uploaded_image($imageFile, $user_id);
        if (!$image_url_path) {
            return [
                'ok' => false,
                'error' => 'Image upload failed (only JPG/PNG/WEBP up to 5MB allowed)',
                'conversation_id' => $conversation_id,
            ];
        }
        $image_mime = $imageFile['type'] ?? null;
    }

    $stmt = $pdo->prepare("
        INSERT INTO ai_message (conversation_id, role, content, image_path)
        VALUES (?, 'user', ?, ?)
    ");
    $stmt->execute([$conversation_id, $message, $image_url_path]);
    $user_message_id = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare("
        SELECT role, content, image_path
        FROM ai_message
        WHERE conversation_id = ?
        ORDER BY created_at DESC, message_id DESC
        LIMIT ?
    ");
    $limit = AI_COACH_HISTORY_TURNS * 2;
    $stmt->bindValue(1, $conversation_id, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    $history = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));

    return [
        'ok' => true,
        'conversation_id'     => $conversation_id,
        'is_new_conversation' => $isNewConversation,
        'user_message_id'     => $user_message_id,
        'image_url_path'      => $image_url_path,
        'image_fs_path'       => $image_fs_path,
        'image_mime'          => $image_mime,
        'history'             => $history,
        'message'             => $message,
        'today'               => $today,
        'used'                => $used,
    ];
}

/**
 * Save the assistant message, bump conversation timestamps + auto-title,
 * bump the daily usage counter. Returns the new assistant message id.
 */
function finalize_assistant_message(
    PDO $pdo, int $conversation_id, bool $isNewConversation,
    string $userMessage, string $assistantText, int $user_id, string $today
): int {
    $stmt = $pdo->prepare("
        INSERT INTO ai_message (conversation_id, role, content)
        VALUES (?, 'assistant', ?)
    ");
    $stmt->execute([$conversation_id, $assistantText]);
    $assistant_message_id = (int)$pdo->lastInsertId();

    $pdo->prepare("UPDATE ai_conversation SET updated_at = CURRENT_TIMESTAMP WHERE conversation_id = ?")
        ->execute([$conversation_id]);

    if ($isNewConversation) {
        $autoTitle = aic_substr(($userMessage !== '' ? $userMessage : 'Image chat'), 0, 60);
        $pdo->prepare("UPDATE ai_conversation SET title = ? WHERE conversation_id = ?")
            ->execute([$autoTitle, $conversation_id]);
    }

    $pdo->prepare("
        INSERT INTO ai_usage_daily (user_id, usage_date, message_count)
        VALUES (?, ?, 1)
        ON DUPLICATE KEY UPDATE message_count = message_count + 1
    ")->execute([$user_id, $today]);

    return $assistant_message_id;
}

function sse_send(string $event, array $data): void
{
    echo "event: {$event}\n";
    echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
    @flush();
}

// =========================================================
// Helpers
// =========================================================

function fetch_owned_conversation(PDO $pdo, int $user_id, int $id): ?array
{
    $stmt = $pdo->prepare("
        SELECT conversation_id, title, created_at, updated_at
        FROM ai_conversation
        WHERE conversation_id = ? AND user_id = ?
    ");
    $stmt->execute([$id, $user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Save an uploaded image into images/ai_coach/{user_id}/
 * Returns [url_path, fs_path] on success or [null, null] on failure.
 */
function save_uploaded_image(array $file, int $user_id): array
{
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];
    $mime = $file['type'] ?? '';
    if (!isset($allowed[$mime])) {
        return [null, null];
    }
    if (($file['size'] ?? 0) > AI_COACH_MAX_IMAGE_BYTES) {
        return [null, null];
    }

    $ext      = $allowed[$mime];
    $baseFs   = __DIR__ . '/../images/ai_coach';
    $userFs   = $baseFs . '/' . $user_id;
    $baseUrl  = 'images/ai_coach';
    $userUrl  = $baseUrl . '/' . $user_id;

    if (!is_dir($userFs)) {
        // Parent (images/ai_coach) must already exist with chmod 777 (created via SSH once)
        @mkdir($userFs, 0755, true);
    }
    if (!is_dir($userFs) || !is_writable($userFs)) {
        return [null, null];
    }

    $filename = uniqid('m_', true) . '.' . $ext;
    $fsPath   = $userFs . '/' . $filename;
    $urlPath  = $userUrl . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $fsPath)) {
        return [null, null];
    }
    return [$urlPath, $fsPath];
}

/**
 * Convert browser-supplied ISO time + tz offset into a human-readable string
 * for the AI prompt. Falls back to server time if missing/invalid.
 *
 * IMPORTANT: JS `new Date().toISOString()` returns UTC (suffix 'Z').
 * To get the user's actual local time we must apply the tz offset that
 * JS `getTimezoneOffset()` sent (in minutes, opposite sign — positive = west of UTC).
 * Example: Vietnam (UTC+7) reports -420 → user's UTC offset is +420 minutes (+07:00).
 */
function build_client_time_info(?string $isoNow, $tzOffsetMin): string
{
    try {
        if ($isoNow) {
            // ISO string from JS is UTC — parse as UTC
            $dt = new DateTime($isoNow, new DateTimeZone('UTC'));
            // Re-anchor to user's local timezone if we got a numeric offset
            if (is_numeric($tzOffsetMin)) {
                $userOffsetMin = -(int)$tzOffsetMin; // flip sign (JS convention)
                $h = intdiv(abs($userOffsetMin), 60);
                $m = abs($userOffsetMin) % 60;
                $sign = $userOffsetMin >= 0 ? '+' : '-';
                $tzName = sprintf('%s%02d:%02d', $sign, $h, $m);
                $dt->setTimezone(new DateTimeZone($tzName));
            }
        } else {
            $dt = new DateTime('now');
        }
    } catch (Throwable $e) {
        $dt = new DateTime('now');
    }
    // Build a friendly string: "Tuesday 2026-05-26, 19:45 local time (evening) [UTC+07:00]"
    $day  = $dt->format('l');
    $date = $dt->format('Y-m-d');
    $hm   = $dt->format('H:i');
    $tz   = $dt->format('P'); // e.g. +07:00
    $hour = (int)$dt->format('H');

    // Map hour to a meal-time hint
    if     ($hour >= 5  && $hour < 11) $part = 'morning';
    elseif ($hour >= 11 && $hour < 14) $part = 'midday';
    elseif ($hour >= 14 && $hour < 17) $part = 'afternoon';
    elseif ($hour >= 17 && $hour < 22) $part = 'evening';
    else                                $part = 'late night / very early';

    return "{$day} {$date}, {$hm} local time ({$part}) [UTC{$tz}]";
}

/**
 * Build the JSON request body for Gemini (system instruction + contents +
 * generation config). Shared by call_gemini() and call_gemini_stream().
 */
function build_gemini_body(array $history, string $userContext, string $clientTimeInfo, ?string $latestImageFs, ?string $latestImageMime): array
{
    $systemInstruction = gemini_system_instruction($userContext, $clientTimeInfo);

    $contents = [];
    $lastIndex = count($history) - 1;
    foreach ($history as $i => $msg) {
        $role = ($msg['role'] === 'assistant') ? 'model' : 'user';
        $parts = [];
        if (trim((string)$msg['content']) !== '') {
            $parts[] = ['text' => $msg['content']];
        }
        if ($i === $lastIndex && $role === 'user' && $latestImageFs && is_file($latestImageFs)) {
            $parts[] = [
                'inline_data' => [
                    'mime_type' => $latestImageMime ?: 'image/jpeg',
                    'data'      => base64_encode(file_get_contents($latestImageFs)),
                ],
            ];
        }
        if (!$parts) {
            $parts[] = ['text' => '(empty)'];
        }
        $contents[] = ['role' => $role, 'parts' => $parts];
    }

    return [
        'system_instruction' => ['parts' => [['text' => $systemInstruction]]],
        'contents'           => $contents,
        'generationConfig'   => [
            'temperature'     => 0.7,
            'maxOutputTokens' => 1024,
        ],
    ];
}

function call_gemini(array $history, string $userContext, string $clientTimeInfo, ?string $latestImageFs, ?string $latestImageMime): array
{
    if (!defined('GEMINI_API_KEY') || GEMINI_API_KEY === '') {
        return ['ok' => false, 'error' => 'Gemini API key not configured'];
    }

    $body = build_gemini_body($history, $userContext, $clientTimeInfo, $latestImageFs, $latestImageMime);
    $url  = 'https://generativelanguage.googleapis.com/v1beta/models/' . AI_COACH_MODEL . ':generateContent?key=' . GEMINI_API_KEY;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode($body),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT        => 60,
    ]);
    $resp = curl_exec($ch);
    if (curl_errno($ch)) {
        $err = curl_error($ch);
        curl_close($ch);
        return ['ok' => false, 'error' => 'Connection error: ' . $err];
    }
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($resp, true);
    if ($code !== 200) {
        $msg = $data['error']['message'] ?? ('HTTP ' . $code);
        return ['ok' => false, 'error' => 'Gemini error: ' . $msg];
    }

    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    if ($text === '') {
        $finishReason = $data['candidates'][0]['finishReason'] ?? 'unknown';
        return ['ok' => false, 'error' => 'AI returned empty response (finishReason: ' . $finishReason . ')'];
    }
    return ['ok' => true, 'text' => $text];
}

/**
 * Streaming variant — hits Gemini's :streamGenerateContent?alt=sse endpoint
 * and calls $onDelta(string $textDelta) for each text fragment as it arrives.
 *
 * Returns ['ok' => true] on completion, or ['ok' => false, 'error' => ...].
 * The caller is responsible for accumulating the full text from the deltas.
 */
function call_gemini_stream(
    array $history, string $userContext, string $clientTimeInfo,
    ?string $latestImageFs, ?string $latestImageMime, callable $onDelta
): array {
    if (!defined('GEMINI_API_KEY') || GEMINI_API_KEY === '') {
        return ['ok' => false, 'error' => 'Gemini API key not configured'];
    }

    $body = build_gemini_body($history, $userContext, $clientTimeInfo, $latestImageFs, $latestImageMime);
    $url  = 'https://generativelanguage.googleapis.com/v1beta/models/'
          . AI_COACH_MODEL . ':streamGenerateContent?alt=sse&key=' . GEMINI_API_KEY;

    // SSE chunk parser state. Gemini delivers events as "data: {json}\n\n"
    // (or "\r\n\r\n" depending on the upstream). Network reads may split
    // mid-event, so we buffer until we see a blank-line separator.
    $sseBuf      = '';
    $upstreamErr = null;
    $rawTotal    = '';   // raw upstream bytes — kept for diagnostics on 0-delta runs
    $deltaCount  = 0;

    $writeCb = function ($ch, string $data) use (&$sseBuf, &$upstreamErr, &$rawTotal, &$deltaCount, $onDelta) {
        $len = strlen($data);
        if (connection_aborted()) return 0;
        $rawTotal .= $data;

        // Normalize line endings so we only have to split on "\n\n".
        $sseBuf .= str_replace("\r\n", "\n", $data);

        while (($nlnl = strpos($sseBuf, "\n\n")) !== false) {
            $event  = substr($sseBuf, 0, $nlnl);
            $sseBuf = substr($sseBuf, $nlnl + 2);

            // Collect data: lines (an event can have multiple, concatenated).
            $dataStr = '';
            foreach (explode("\n", $event) as $line) {
                if (strncmp($line, 'data:', 5) === 0) {
                    $piece = substr($line, 5);
                    if (strlen($piece) > 0 && $piece[0] === ' ') $piece = substr($piece, 1);
                    $dataStr .= $piece;
                }
            }
            if ($dataStr === '' || $dataStr === '[DONE]') continue;

            $parsed = json_decode($dataStr, true);
            if (!is_array($parsed)) continue;

            // Gemini SSE shape mirrors non-stream: candidates[0].content.parts[*].text
            $parts = $parsed['candidates'][0]['content']['parts'] ?? [];
            foreach ($parts as $p) {
                if (isset($p['text']) && $p['text'] !== '') {
                    $deltaCount++;
                    $onDelta((string)$p['text']);
                }
            }
            if (isset($parsed['error']['message'])) {
                $upstreamErr = (string)$parsed['error']['message'];
            }
        }
        return $len;
    };

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: text/event-stream'],
        CURLOPT_POSTFIELDS     => json_encode($body),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_WRITEFUNCTION  => $writeCb,
    ]);
    $ok = curl_exec($ch);
    if ($ok === false && !connection_aborted()) {
        $err = curl_error($ch);
        curl_close($ch);
        return ['ok' => false, 'error' => 'Connection error: ' . $err];
    }
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($upstreamErr !== null) {
        return ['ok' => false, 'error' => 'Gemini error: ' . $upstreamErr];
    }
    if ($code !== 200) {
        // Surface upstream body so we can see what went wrong (auth, model
        // name, quota, etc.). Trim hard so the SSE error stays readable.
        $preview = trim(substr($rawTotal, 0, 500));
        return ['ok' => false, 'error' => "Gemini HTTP {$code}: {$preview}"];
    }
    if ($deltaCount === 0) {
        // Fallback: some upstream variants return a single JSON array of
        // chunks instead of SSE. Try to parse it that way before giving up.
        $arr = json_decode($rawTotal, true);
        if (is_array($arr)) {
            foreach ($arr as $chunk) {
                $parts = $chunk['candidates'][0]['content']['parts'] ?? [];
                foreach ($parts as $p) {
                    if (isset($p['text']) && $p['text'] !== '') {
                        $deltaCount++;
                        $onDelta((string)$p['text']);
                    }
                }
            }
        }
        if ($deltaCount === 0) {
            $preview = trim(substr($rawTotal, 0, 400));
            if ($preview === '') $preview = '(empty body)';
            return ['ok' => false, 'error' => "No deltas parsed from upstream. Raw: {$preview}"];
        }
    }
    return ['ok' => true];
}

/**
 * Build the long system prompt sent to Gemini. Extracted so the streaming
 * and non-streaming call paths share an identical instruction string.
 */
function gemini_system_instruction(string $userContext, string $clientTimeInfo): string
{
    return
        "You are an AI nutrition and fitness coach for a user of BitBalance (a calorie-tracking web app). " .
        "Give specific, evidence-based, actionable advice in a warm, encouraging tone. " .
        "ALWAYS reference the user's actual data below when relevant — calorie goal, today's intake, trends, weight. " .
        "Be concise (under 200 words unless the user asks for detail).\n\n" .
        "LANGUAGE RULE (CRITICAL): Detect the language of the user's MOST RECENT message and reply in that exact language. " .
        "If the latest user message is in English, reply ONLY in English. " .
        "If it is in Vietnamese, reply ONLY in Vietnamese. " .
        "Do NOT infer language from the user's name or previous messages — always mirror the latest message.\n\n" .
        "FORMATTING: You may use **bold**, bullet lists (lines starting with '* '), and short paragraphs. " .
        "Do not use headings or tables.\n\n" .
        "CURRENT TIME (the user's local time, treat as authoritative):\n" . $clientTimeInfo . "\n\n" .
        "=== CAPABILITIES (READ CAREFULLY) ===\n" .
        "You CANNOT directly log, save, or add entries to the user's intake log. " .
        "Your ONLY way to help them log food is to emit a FOOD_LOG suggestion block (see below) — " .
        "which renders as a card with an 'Add to Log' button that the USER must tap to actually save it.\n" .
        "NEVER say things like 'I've logged it', 'I added it for you', 'Done, saved!', 'Logged successfully', " .
        "or any phrase that implies the entry is already saved. It is NOT saved until the user taps the button.\n" .
        "===\n\n" .
        "FOOD LOG SUGGESTIONS — WHEN TO EMIT THE CARD (very important):\n" .
        "There are TWO modes for any food-related reply, and you MUST pick the right one:\n\n" .
        "MODE A — LOG MODE (emit the FOOD_LOG block):\n" .
        "Only when the user has clearly ALREADY EATEN something, or explicitly tells you to log/save it. Examples:\n" .
        "  * 'I just had a chicken sandwich' / 'I ate 2 eggs for breakfast'\n" .
        "  * 'Log a banana for me' / 'Add 200g of rice to lunch' / 'Just log it'\n" .
        "  * A food photo where the user clearly ate or is eating it\n" .
        "In LOG MODE: keep prose SHORT (1-3 sentences), and add a brief pointer to the card such as:\n" .
        "  * English: 'Tap Add to Log to save it.'\n" .
        "  * Vietnamese: 'Bấm Add to Log để lưu nhé.'\n\n" .
        "MODE B — SUGGEST / ADVISE MODE (DO NOT emit the FOOD_LOG block):\n" .
        "When the user is asking what to eat, asking for ideas, comparing options, or asking advice. Examples:\n" .
        "  * 'What should I eat?' / 'Suggest a high-protein dinner' / 'Any snack ideas?'\n" .
        "  * 'Is X healthy?' / 'How many calories should I have left today?'\n" .
        "  * Photo of a menu / grocery shelf / something the user has NOT eaten yet\n" .
        "In SUGGEST MODE you have NOT been told they're eating it — emitting a log card would be wrong.\n" .
        "Recommend the food normally, mention macros in prose if useful, and end with an OFFER such as:\n" .
        "  * English: 'If you decide to have it, just say \"log it\" and I'll prep the card.'\n" .
        "  * Vietnamese: 'Nếu bạn ăn món này, nhắn \"log nhé\" mình lên thẻ ghi liền.'\n" .
        "Do NOT say 'tap below to log it' in SUGGEST MODE — there is no card to tap.\n\n" .
        "If the user replies to your suggestion with confirmation like 'ok I'll have that' / 'sounds good, log it' / " .
        "'going to eat it now' → switch to LOG MODE and emit the card on the NEXT turn.\n\n" .
        "FORMAT of the FOOD_LOG block (LOG MODE only, no markdown code fence, at the very END of the reply):\n" .
        "[[FOOD_LOG]]\n" .
        "{\"items\":[{\"food_name\":\"Grilled chicken breast\",\"meal_category\":\"lunch\",\"calories\":230,\"protein\":43,\"carbs\":0,\"fat\":5}]}\n" .
        "[[/FOOD_LOG]]\n" .
        "Rules for the block:\n" .
        "- meal_category MUST be one of: breakfast, lunch, dinner, snack.\n" .
        "- calories MUST be a positive integer (1-5000).\n" .
        "- protein/carbs/fat are grams as numbers (0 if unknown).\n" .
        "- food_name is concise (under 60 chars), in the same language as the user.\n" .
        "- Include multiple items if the user mentions multiple foods.\n" .
        "- The block is hidden from the user — do NOT reference it in your prose.\n\n" .
        "MEAL CATEGORY INFERENCE (LOG MODE only — apply in this priority order):\n" .
        "1. If the user explicitly says 'for breakfast/lunch/dinner/as a snack' or names a meal → use that.\n" .
        "2. Otherwise infer from the CURRENT LOCAL TIME shown above:\n" .
        "   * 05:00-10:30  → breakfast\n" .
        "   * 10:30-14:30  → lunch\n" .
        "   * 17:00-21:30  → dinner\n" .
        "   * 14:30-17:00 or 21:30-05:00 → snack\n" .
        "3. If the user explicitly says 'log it' / 'log X for me' / 'just log it', DO NOT ask clarifying questions — " .
        "just pick the best meal_category from rule 1-2 and emit the card.\n" .
        "4. Otherwise, if the situation is genuinely ambiguous (e.g., user describes a full meal at 3am, " .
        "or food that doesn't match the time slot — like a heavy steak at 9am, AND the user hasn't said 'log it'), " .
        "ask a SHORT clarifying question and OMIT the FOOD_LOG block this turn. After the user answers, include the block.\n\n" .
        "If asked something outside nutrition/fitness, gently redirect.\n\n" .
        "=== USER DATA SNAPSHOT ===\n" . $userContext . "\n=== END USER DATA ===";
}

/**
 * Pull the [[FOOD_LOG]]...[[/FOOD_LOG]] block out of the assistant text.
 * Returns [cleaned_text, [items...]]. If block missing/invalid, items = [].
 *
 * Each valid item is normalized to:
 *   { food_name, meal_category, calories, protein, carbs, fat }
 */
function extract_food_log_block(string $text): array
{
    $items = [];
    $clean = $text;

    if (preg_match('/\[\[FOOD_LOG\]\](.*?)\[\[\/FOOD_LOG\]\]/s', $text, $m)) {
        // Remove block from displayed text
        $clean = trim(str_replace($m[0], '', $text));
        $json  = trim($m[1]);
        // Allow models that wrap with code fences anyway
        $json = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $json);
        $parsed = json_decode($json, true);
        if (is_array($parsed) && isset($parsed['items']) && is_array($parsed['items'])) {
            $validCats = ['breakfast', 'lunch', 'dinner', 'snack'];
            foreach ($parsed['items'] as $it) {
                if (!is_array($it)) continue;
                $name = trim((string)($it['food_name'] ?? ''));
                $cal  = (int)($it['calories'] ?? 0);
                $cat  = strtolower(trim((string)($it['meal_category'] ?? 'snack')));
                if ($name === '' || $cal <= 0 || $cal > 5000) continue;
                if (!in_array($cat, $validCats, true)) $cat = 'snack';
                if (aic_strlen($name) > 60) $name = aic_substr($name, 0, 60);

                $items[] = [
                    'food_name'     => $name,
                    'meal_category' => $cat,
                    'calories'      => $cal,
                    'protein'       => round((float)($it['protein'] ?? 0), 2),
                    'carbs'         => round((float)($it['carbs']   ?? 0), 2),
                    'fat'           => round((float)($it['fat']     ?? 0), 2),
                ];
            }
        }
    }

    return [$clean, $items];
}
