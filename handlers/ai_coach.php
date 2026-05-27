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
$writeActions = ['create_conversation', 'send_message', 'rename_conversation', 'delete_conversation'];
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
    $conversation_id = (int)($_POST['conversation_id'] ?? 0);
    $message         = trim((string)($_POST['message'] ?? ''));
    $imageFile       = $_FILES['image'] ?? null;

    if ($message === '' && empty($imageFile['name'])) {
        echo json_encode(['ok' => false, 'error' => 'Message is empty']);
        return;
    }

    // ---- Rate limit ----
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT message_count FROM ai_usage_daily WHERE user_id = ? AND usage_date = ?");
    $stmt->execute([$user_id, $today]);
    $used = (int)($stmt->fetchColumn() ?: 0);
    if ($used >= AI_COACH_DAILY_LIMIT) {
        http_response_code(429);
        echo json_encode([
            'ok' => false,
            'error' => 'Daily AI Coach limit reached (' . AI_COACH_DAILY_LIMIT . ' messages). Please try again tomorrow.',
            'usage_today' => $used,
            'daily_limit' => AI_COACH_DAILY_LIMIT,
        ]);
        return;
    }

    // ---- Create conversation if needed ----
    if ($conversation_id <= 0) {
        $stmt = $pdo->prepare("INSERT INTO ai_conversation (user_id, title) VALUES (?, 'New chat')");
        $stmt->execute([$user_id]);
        $conversation_id = (int)$pdo->lastInsertId();
        $isNewConversation = true;
    } else {
        $conv = fetch_owned_conversation($pdo, $user_id, $conversation_id);
        if (!$conv) {
            echo json_encode(['ok' => false, 'error' => 'Conversation not found']);
            return;
        }
        $isNewConversation = false;
    }

    // ---- Handle image upload (if any) ----
    $image_url_path = null;
    $image_fs_path  = null;
    if ($imageFile && $imageFile['error'] === UPLOAD_ERR_OK) {
        [$image_url_path, $image_fs_path] = save_uploaded_image($imageFile, $user_id);
        if (!$image_url_path) {
            echo json_encode(['ok' => false, 'error' => 'Image upload failed (only JPG/PNG/WEBP up to 5MB allowed)']);
            return;
        }
    }

    // ---- Save user message ----
    $stmt = $pdo->prepare("
        INSERT INTO ai_message (conversation_id, role, content, image_path)
        VALUES (?, 'user', ?, ?)
    ");
    $stmt->execute([$conversation_id, $message, $image_url_path]);
    $user_message_id = (int)$pdo->lastInsertId();

    // ---- Build history (last N turns) ----
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

    // ---- Call Gemini ----
    $userContext = build_user_context($pdo, $user_id);
    $clientTimeInfo = build_client_time_info($_POST['client_now'] ?? null, $_POST['client_tz_offset'] ?? null);
    $result = call_gemini($history, $userContext, $clientTimeInfo, $image_fs_path, $imageFile['type'] ?? null);

    if (!$result['ok']) {
        // Roll back: keep user msg but report error
        echo json_encode([
            'ok' => false,
            'error' => $result['error'],
            'conversation_id' => $conversation_id,
        ]);
        return;
    }

    $assistantTextRaw = $result['text'];

    // Strip & extract FOOD_LOG block from assistant text
    [$assistantText, $foodLogSuggestions] = extract_food_log_block($assistantTextRaw);

    // ---- Save assistant message (sanitized text only) ----
    $stmt = $pdo->prepare("
        INSERT INTO ai_message (conversation_id, role, content)
        VALUES (?, 'assistant', ?)
    ");
    $stmt->execute([$conversation_id, $assistantText]);
    $assistant_message_id = (int)$pdo->lastInsertId();

    // ---- Bump conversation updated_at + auto-title if new ----
    if ($isNewConversation || true) {
        $pdo->prepare("UPDATE ai_conversation SET updated_at = CURRENT_TIMESTAMP WHERE conversation_id = ?")
            ->execute([$conversation_id]);
    }
    if ($isNewConversation) {
        $autoTitle = aic_substr(($message !== '' ? $message : 'Image chat'), 0, 60);
        $pdo->prepare("UPDATE ai_conversation SET title = ? WHERE conversation_id = ?")
            ->execute([$autoTitle, $conversation_id]);
    }

    // ---- Bump usage counter ----
    $pdo->prepare("
        INSERT INTO ai_usage_daily (user_id, usage_date, message_count)
        VALUES (?, ?, 1)
        ON DUPLICATE KEY UPDATE message_count = message_count + 1
    ")->execute([$user_id, $today]);

    // ---- Respond ----
    echo json_encode([
        'ok' => true,
        'conversation_id' => $conversation_id,
        'user_message' => [
            'message_id' => $user_message_id,
            'role'       => 'user',
            'content'    => $message,
            'image_path' => $image_url_path,
        ],
        'assistant_message' => [
            'message_id' => $assistant_message_id,
            'role'       => 'assistant',
            'content'    => $assistantText,
        ],
        'food_log_suggestions' => $foodLogSuggestions,
        'usage_today' => $used + 1,
        'daily_limit' => AI_COACH_DAILY_LIMIT,
    ]);
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
 * Build Gemini request body, call API, return ['ok' => bool, 'text' => string, 'error' => string].
 *
 * Each history entry: ['role' => 'user'|'assistant', 'content' => string, 'image_path' => ?string]
 * Gemini wants role = 'user' or 'model'.
 */
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

function call_gemini(array $history, string $userContext, string $clientTimeInfo, ?string $latestImageFs, ?string $latestImageMime): array
{
    if (!defined('GEMINI_API_KEY') || GEMINI_API_KEY === '') {
        return ['ok' => false, 'error' => 'Gemini API key not configured'];
    }

    $systemInstruction =
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
        "Instead, when emitting a card, say something brief like:\n" .
        "  * English: 'Here's a quick log card — tap Add to Log to save it.' / 'Tap below to log it.'\n" .
        "  * Vietnamese: 'Đây là thẻ ghi nhanh — bấm Add to Log để lưu nhé.' / 'Bấm nút bên dưới để lưu.'\n" .
        "===\n\n" .
        "FOOD LOG SUGGESTIONS (very important):\n" .
        "Whenever you discuss specific food items with concrete nutrition values — whether the user reports eating them, " .
        "asks you to analyze a meal/photo, or you suggest a meal — append a structured block at the END of your reply. " .
        "Use this EXACT format (no markdown code fence):\n" .
        "[[FOOD_LOG]]\n" .
        "{\"items\":[{\"food_name\":\"Grilled chicken breast\",\"meal_category\":\"lunch\",\"calories\":230,\"protein\":43,\"carbs\":0,\"fat\":5}]}\n" .
        "[[/FOOD_LOG]]\n" .
        "Rules for the block:\n" .
        "- meal_category MUST be one of: breakfast, lunch, dinner, snack.\n" .
        "- calories MUST be a positive integer (1-5000).\n" .
        "- protein/carbs/fat are grams as numbers (0 if unknown).\n" .
        "- food_name is concise (under 60 chars), in the same language as the user.\n" .
        "- Include multiple items if the user mentions multiple foods.\n" .
        "- ONLY include the block when nutrition values are concrete; SKIP it for general questions, advice, or vague topics.\n" .
        "- The block is hidden from the user — do NOT reference it in your prose.\n\n" .
        "RESPONSE LENGTH when emitting a card:\n" .
        "- Keep prose SHORT (1-3 sentences). The card itself shows the nutrition; do not repeat numbers in prose.\n" .
        "- If the user asked a quick question like 'log X for me' or 'just had X', a single sentence + the card is best.\n" .
        "- Save longer advice for when the user explicitly asks for analysis or recommendations.\n\n" .
        "MEAL CATEGORY INFERENCE (apply in this priority order):\n" .
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

    // Build "contents" — Gemini uses role 'user' and 'model'
    $contents = [];
    $lastIndex = count($history) - 1;
    foreach ($history as $i => $msg) {
        $role = ($msg['role'] === 'assistant') ? 'model' : 'user';
        $parts = [];
        if (trim((string)$msg['content']) !== '') {
            $parts[] = ['text' => $msg['content']];
        }
        // For the LATEST user message, if it has an image, attach the actual file bytes
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

    $body = [
        'system_instruction' => ['parts' => [['text' => $systemInstruction]]],
        'contents'           => $contents,
        'generationConfig'   => [
            'temperature'     => 0.7,
            'maxOutputTokens' => 1024,
        ],
    ];

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . AI_COACH_MODEL . ':generateContent?key=' . GEMINI_API_KEY;

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
        // Could be blocked by safety filter
        $finishReason = $data['candidates'][0]['finishReason'] ?? 'unknown';
        return ['ok' => false, 'error' => 'AI returned empty response (finishReason: ' . $finishReason . ')'];
    }
    return ['ok' => true, 'text' => $text];
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
