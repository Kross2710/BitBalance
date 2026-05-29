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
                $validCats = ['breakfast', 'lunch', 'dinner', 'snack'];
                foreach ($parsed['items'] as $it) {
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
        return [
            'id'         => (int)$row['message_id'],
            'role'       => $row['role'],
            'content'    => $row['content'],
            'image_path' => isset($row['image_path']) ? $row['image_path'] : null,
            'created_at' => $row['created_at'],
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

