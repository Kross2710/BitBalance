<?php
/**
 * AI Coach API — shared helpers.
 *
 * Every endpoint in api/ai-coach/ requires this file.
 * Provides: UTF-8 polyfills, Gemini caller, conversation helpers, formatters.
 */

require_once __DIR__ . '/../_bootstrap.php';
require_once PROJECT_ROOT . 'include/secrets.php';
require_once PROJECT_ROOT . 'include/handlers/ai_context.php';

/* ── UTF-8 polyfills (safe on RMIT PHP 7.4 without mbstring) ─────────── */

if (!function_exists('aic_strlen')) {
    function aic_strlen(string $s): int {
        if (function_exists('mb_strlen')) return mb_strlen($s, 'UTF-8');
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
        if (!preg_match_all('/./us', $s, $m)) return substr($s, $start, $len ?? PHP_INT_MAX);
        $chars = $m[0];
        return implode('', array_slice($chars, $start, $len));
    }
}

/* ── Client time helper ──────────────────────────────────────────────── */

if (!function_exists('build_client_time_info')) {
    function build_client_time_info(?string $isoNow, $tzOffsetMin): string
    {
        try {
            if ($isoNow) {
                $dt = new DateTime($isoNow, new DateTimeZone('UTC'));
                if (is_numeric($tzOffsetMin)) {
                    $userOffsetMin = -(int)$tzOffsetMin;
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
        $day  = $dt->format('l');
        $date = $dt->format('Y-m-d');
        $hm   = $dt->format('H:i');
        $tz   = $dt->format('P');
        $hour = (int)$dt->format('H');

        if     ($hour >= 5  && $hour < 11) $part = 'morning';
        elseif ($hour >= 11 && $hour < 14) $part = 'midday';
        elseif ($hour >= 14 && $hour < 17) $part = 'afternoon';
        elseif ($hour >= 17 && $hour < 22) $part = 'evening';
        else                                $part = 'late night / very early';

        return "{$day} {$date}, {$hm} local time ({$part}) [UTC{$tz}]";
    }
}

/* ── Gemini system instruction ───────────────────────────────────────── */

if (!function_exists('gemini_system_instruction')) {
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
}

/* ── Build Gemini request body ───────────────────────────────────────── */

if (!function_exists('build_gemini_body')) {
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
                        'mime_type' => $latestImageMime ? $latestImageMime : 'image/jpeg',
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
}

/* ── Call Gemini (non-streaming) ─────────────────────────────────────── */

if (!function_exists('call_gemini')) {
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
            $msg = isset($data['error']['message']) ? $data['error']['message'] : ('HTTP ' . $code);
            return ['ok' => false, 'error' => 'Gemini error: ' . $msg];
        }

        $text = isset($data['candidates'][0]['content']['parts'][0]['text']) ? $data['candidates'][0]['content']['parts'][0]['text'] : '';
        if ($text === '') {
            $finishReason = isset($data['candidates'][0]['finishReason']) ? $data['candidates'][0]['finishReason'] : 'unknown';
            return ['ok' => false, 'error' => 'AI returned empty response (finishReason: ' . $finishReason . ')'];
        }
        return ['ok' => true, 'text' => $text];
    }
}

/* ── Extract [[FOOD_LOG]] block from AI response ─────────────────────── */

if (!function_exists('normalize_food_log_items')) {
    function normalize_food_log_items($rawItems): array
    {
        $items = [];
        if (!is_array($rawItems)) {
            return $items;
        }

        $validCats = ['breakfast', 'lunch', 'dinner', 'snack'];
        foreach ($rawItems as $it) {
            if (!is_array($it)) continue;
            $name = trim((string)(isset($it['food_name']) ? $it['food_name'] : ''));
            $cal  = (int)(isset($it['calories']) ? $it['calories'] : 0);
            $cat  = strtolower(trim((string)(isset($it['meal_category']) ? $it['meal_category'] : 'snack')));
            if ($name === '' || $cal <= 0 || $cal > 5000) continue;
            if (!in_array($cat, $validCats, true)) $cat = 'snack';
            if (aic_strlen($name) > 60) $name = aic_substr($name, 0, 60);

            $items[] = [
                'food_name'     => $name,
                'meal_category' => $cat,
                'calories'      => $cal,
                'protein'       => round((float)(isset($it['protein']) ? $it['protein'] : 0), 2),
                'carbs'         => round((float)(isset($it['carbs'])   ? $it['carbs']   : 0), 2),
                'fat'           => round((float)(isset($it['fat'])     ? $it['fat']     : 0), 2),
            ];
        }

        return $items;
    }
}

if (!function_exists('extract_food_log_block')) {
    function extract_food_log_block(string $text): array
    {
        $items = [];
        $clean = $text;

        if (preg_match('/\[\[FOOD_LOG\]\](.*?)\[\[\/FOOD_LOG\]\]/s', $text, $m)) {
            $clean = trim(str_replace($m[0], '', $text));
            $json  = trim($m[1]);
            $json = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $json);
            $parsed = json_decode($json, true);
            if (is_array($parsed) && isset($parsed['items']) && is_array($parsed['items'])) {
                $items = normalize_food_log_items($parsed['items']);
            }
        }

        return [$clean, $items];
    }
}

if (!function_exists('pack_food_log_suggestions')) {
    function pack_food_log_suggestions(string $content, array $items): string
    {
        if (empty($items)) {
            return $content;
        }

        return rtrim($content) .
            "\n\n[[BITBALANCE_FOOD_LOG_SUGGESTIONS]]\n" .
            json_encode(['items' => array_values($items)]) .
            "\n[[/BITBALANCE_FOOD_LOG_SUGGESTIONS]]";
    }
}

if (!function_exists('unpack_food_log_suggestions')) {
    function unpack_food_log_suggestions($content): array
    {
        $text = (string) $content;
        $items = [];
        $clean = $text;

        if (preg_match('/\[\[BITBALANCE_FOOD_LOG_SUGGESTIONS\]\](.*?)\[\[\/BITBALANCE_FOOD_LOG_SUGGESTIONS\]\]/s', $text, $m)) {
            $clean = trim(str_replace($m[0], '', $text));
            $parsed = json_decode(trim($m[1]), true);
            if (is_array($parsed) && isset($parsed['items']) && is_array($parsed['items'])) {
                $items = normalize_food_log_items($parsed['items']);
            }
        }

        return [$clean, $items];
    }
}

/* ── Fetch a conversation only if owned by user ──────────────────────── */

if (!function_exists('fetch_owned_conversation')) {
    function fetch_owned_conversation(PDO $pdo, int $user_id, int $id): ?array
    {
        $stmt = $pdo->prepare("
            SELECT conversation_id, title, created_at, updated_at
            FROM ai_conversation
            WHERE conversation_id = ? AND user_id = ?
        ");
        $stmt->execute([$id, $user_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row : null;
    }
}

/* ── API response formatters ─────────────────────────────────────────── */

if (!function_exists('api_format_message')) {
    function api_format_message(array $row): array
    {
        list($content, $foodLogSuggestions) = unpack_food_log_suggestions(isset($row['content']) ? $row['content'] : '');

        return [
            'id'         => (int)$row['message_id'],
            'role'       => $row['role'],
            'content'    => $content,
            'image_path' => isset($row['image_path']) ? $row['image_path'] : null,
            'created_at' => $row['created_at'],
            'food_log_suggestions' => $foodLogSuggestions,
        ];
    }
}

if (!function_exists('api_format_conversation')) {
    function api_format_conversation(array $row): array
    {
        return [
            'id'         => (int)$row['conversation_id'],
            'title'      => $row['title'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        ];
    }
}

if (!function_exists('save_uploaded_image')) {
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
        $baseFs   = __DIR__ . '/../../images/ai_coach';
        $userFs   = $baseFs . '/' . $user_id;
        $baseUrl  = 'images/ai_coach';
        $userUrl  = $baseUrl . '/' . $user_id;

        if (!is_dir($userFs)) {
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
}
