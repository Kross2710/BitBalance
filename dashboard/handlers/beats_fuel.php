<?php
/**
 * dashboard/handlers/beats_fuel.php
 * "Suggested Fuel for Your Current Beats" — AI food suggestions based on the
 * user's recent Spotify tracks (passed in from the Beats page, which already
 * fetched them) plus today's remaining calorie budget. Display-only.
 *
 * POST: tracks = JSON array of { track, artist } (the page's recent tracks).
 * Returns: { ok: bool, suggestions: [ { mood, vibe, food, reason, kcal } ] }
 */
require_once __DIR__ . '/../../include/init.php';
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json');

function beats_utf8_substr($text, $start, $length)
{
    $text = (string) $text;
    if ($text === '') {
        return '';
    }
    if (function_exists('iconv_substr')) {
        $slice = @iconv_substr($text, $start, $length, 'UTF-8');
        if ($slice !== false) {
            return $slice;
        }
    }
    if (preg_match_all('/./us', $text, $chars)) {
        return implode('', array_slice($chars[0], (int) $start, (int) $length));
    }
    return substr($text, (int) $start, (int) $length);
}

if (!isset($_SESSION['user'])) {
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId = (int) $_SESSION['user']['user_id'];
$lang = (function_exists('current_locale') && current_locale() === 'vi') ? 'vi' : 'en';

// --- Parse the recent tracks sent from the page ---
$rawTracks = json_decode($_POST['tracks'] ?? '[]', true);
$tracks = [];
if (is_array($rawTracks)) {
    foreach ($rawTracks as $t) {
        $name = trim((string) ($t['track'] ?? ''));
        $artist = trim((string) ($t['artist'] ?? ''));
        if ($name !== '') {
            $tracks[] = ['track' => beats_utf8_substr($name, 0, 120), 'artist' => beats_utf8_substr($artist, 0, 120)];
        }
        if (count($tracks) >= 8) break;
    }
}

if (empty($tracks)) {
    // No listening data → let the page keep its static fallback cards.
    echo json_encode(['ok' => false, 'reason' => 'no_tracks']);
    exit;
}

// --- Today's calorie context (makes suggestions goal-aware) ---
$goal = (int) (getUserIntakeGoal($userId) ?? 0);
$consumed = (int) (getTotalCaloriesToday($userId) ?? 0);
$remaining = $goal > 0 ? max(0, $goal - $consumed) : null;

// --- Session cache: refresh when the day or the top track changes ---
$topKey = $tracks[0]['track'] . '|' . $tracks[0]['artist'];
$cacheKey = date('Y-m-d') . '|' . $lang . '|' . md5($topKey);
if (isset($_SESSION['beats_fuel']) && ($_SESSION['beats_fuel']['key'] ?? null) === $cacheKey) {
    echo json_encode(['ok' => true, 'cached' => true, 'suggestions' => $_SESSION['beats_fuel']['suggestions']]);
    exit;
}

// --- Build track list string for the prompt ---
$trackListStr = '';
foreach ($tracks as $i => $t) {
    $trackListStr .= ($i + 1) . '. "' . $t['track'] . '"' . ($t['artist'] !== '' ? ($lang === 'vi' ? ' của ' : ' by ') . '"' . $t['artist'] . '"' : '') . '; ';
}
$trackListStr = rtrim($trackListStr, '; ');

$calorieLineVi = $remaining !== null
    ? "- Hôm nay người dùng còn khoảng {$remaining} kcal trong hạn mức. Ưu tiên gợi ý phù hợp với lượng calo còn lại này."
    : "- Người dùng chưa đặt mục tiêu calo, cứ gợi ý thoải mái nhưng hợp lý.";
$calorieLineEn = $remaining !== null
    ? "- The user has about {$remaining} kcal left in today's budget. Prefer suggestions that fit this remaining budget."
    : "- The user hasn't set a calorie goal; suggest freely but sensibly.";

if ($lang === 'vi') {
    $systemPrompt = "Bạn là trợ lý ẩm thực dí dỏm của app theo dõi calo BitBalance. Dưới đây là các bài hát người dùng vừa nghe gần đây: [{$trackListStr}]
{$calorieLineVi}

Nhiệm vụ: Cảm nhận TÂM TRẠNG/THỂ LOẠI tổng thể của gu nhạc này, rồi gợi ý ĐÚNG 3 món ăn/thức uống ('fuel') hợp vibe để thưởng thức khi nghe. Mỗi gợi ý gồm:
- mood: chọn 1 trong các giá trị: chill, energetic, sad, focus, happy
- vibe: nhãn ngắn mô tả vibe (vd 'Lo-Fi thư giãn')
- food: tên món ăn/thức uống cụ thể, ngắn gọn
- reason: 1 câu ngắn (dưới 16 từ) hài hước giải thích vì sao món này hợp vibe
- kcal: số nguyên ước tính lượng calo của khẩu phần thông thường

CHỈ trả về DUY NHẤT một JSON thuần (không markdown, không ```), dạng:
{\"suggestions\":[{\"mood\":\"\",\"vibe\":\"\",\"food\":\"\",\"reason\":\"\",\"kcal\":0}]}";
} else {
    $systemPrompt = "You are the witty food assistant of the BitBalance calorie tracker. Here are the user's recently played tracks: [{$trackListStr}]
{$calorieLineEn}

Task: Sense the overall MOOD/GENRE of this music taste, then suggest EXACTLY 3 foods/drinks ('fuel') that match the vibe to enjoy while listening. Each suggestion has:
- mood: one of: chill, energetic, sad, focus, happy
- vibe: a short vibe label (e.g. 'Chill Lo-Fi')
- food: a specific, short food/drink name
- reason: one short witty sentence (under 16 words) on why it fits the vibe
- kcal: an integer estimate of calories for a typical serving

Return ONLY a raw JSON object (no markdown, no ```), shaped:
{\"suggestions\":[{\"mood\":\"\",\"vibe\":\"\",\"food\":\"\",\"reason\":\"\",\"kcal\":0}]}";
}

// --- Call Gemini ---
$suggestions = null;
try {
    if (!defined('GEMINI_API_KEY') || GEMINI_API_KEY === '') {
        throw new Exception('Gemini API key not configured');
    }
    $model = defined('AI_COACH_MODEL') ? AI_COACH_MODEL : 'gemini-3.1-flash-lite';
    $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . GEMINI_API_KEY;

    $body = ['contents' => [['parts' => [['text' => $systemPrompt]]]]];

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $resJson = json_decode($response, true);
        $rawText = $resJson['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $cleanText = trim(str_replace(['```json', '```'], '', $rawText));
        $parsed = json_decode($cleanText, true);
        if (isset($parsed['suggestions']) && is_array($parsed['suggestions'])) {
            $allowedMoods = ['chill', 'energetic', 'sad', 'focus', 'happy'];
            $suggestions = [];
            foreach ($parsed['suggestions'] as $s) {
                $mood = strtolower(trim((string) ($s['mood'] ?? '')));
                $suggestions[] = [
                    'mood'   => in_array($mood, $allowedMoods, true) ? $mood : 'chill',
                    'vibe'   => beats_utf8_substr(trim((string) ($s['vibe'] ?? '')), 0, 60),
                    'food'   => beats_utf8_substr(trim((string) ($s['food'] ?? '')), 0, 80),
                    'reason' => beats_utf8_substr(trim((string) ($s['reason'] ?? '')), 0, 160),
                    'kcal'   => max(0, (int) ($s['kcal'] ?? 0)),
                ];
                if (count($suggestions) >= 3) break;
            }
        }
    }
} catch (Exception $e) {
    $suggestions = null;
}

if (empty($suggestions)) {
    echo json_encode(['ok' => false, 'reason' => 'ai_unavailable']);
    exit;
}

$_SESSION['beats_fuel'] = ['key' => $cacheKey, 'suggestions' => $suggestions];

echo json_encode(['ok' => true, 'cached' => false, 'suggestions' => $suggestions]);
exit;
