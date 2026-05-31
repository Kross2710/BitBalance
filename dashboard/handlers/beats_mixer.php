<?php
/**
 * dashboard/handlers/beats_mixer.php
 * "AI Mood-Food DJ Mixer" — AJAX endpoint that analyzes a specific track,
 * a food item, and dynamically detects the song's real vibe. Rates compatibility
 * and provides a witty comment from Gemini AI.
 *
 * POST: track_name, artist_name, food_item, calories
 * Returns: { ok: bool, match_score: int, comment: string, detected_vibe: string, cached: bool }
 */
require_once __DIR__ . '/../../include/init.php';
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json');

function mixer_utf8_substr($text, $start, $length)
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

// --- Parse input parameters safely ---
$track = mixer_utf8_substr(trim((string)($_POST['track_name'] ?? '')), 0, 100);
$artist = mixer_utf8_substr(trim((string)($_POST['artist_name'] ?? '')), 0, 100);
$food = mixer_utf8_substr(trim((string)($_POST['food_item'] ?? '')), 0, 100);
$calories = max(0, (int)($_POST['calories'] ?? 0));

if ($track === '' || $food === '') {
    echo json_encode([
        'ok' => false,
        'error' => ($lang === 'vi') 
            ? 'Vui lòng chọn đầy đủ 1 bài hát và 1 món ăn để mix!' 
            : 'Please select both a song and a food to mix!'
    ]);
    exit;
}

// --- Enrich: pull THIS food's real macro profile + typical slot for THIS user ---
// Grounds the analysis in actual logged data so the result feels personal,
// and lets us compute stable, meaningful sub-scores (not random guesses).
$foodProfile = null;
try {
    $stmt = $pdo->prepare(
        "SELECT AVG(protein) AS protein, AVG(carbs) AS carbs, AVG(fat) AS fat,
                AVG(calories) AS calories, COUNT(*) AS times_logged,
                ROUND(AVG(HOUR(date_intake))) AS typical_hour, meal_category
         FROM intakeLog
         WHERE user_id = ? AND food_item = ?
         GROUP BY meal_category
         ORDER BY times_logged DESC
         LIMIT 1"
    );
    $stmt->execute([$userId, $food]);
    $foodProfile = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (PDOException $e) {
    $foodProfile = null;
}

// --- Deterministic food feature extraction (0..100 indices) ---
$p = $foodProfile ? (float) $foodProfile['protein'] : 0.0;
$c = $foodProfile ? (float) $foodProfile['carbs'] : 0.0;
$f = $foodProfile ? (float) $foodProfile['fat'] : 0.0;
$kcal = $foodProfile && $foodProfile['calories'] ? (int) round($foodProfile['calories']) : $calories;
$timesLogged = $foodProfile ? (int) $foodProfile['times_logged'] : 0;
$mealSlot = $foodProfile['meal_category'] ?? '';
$typicalHour = ($foodProfile && $foodProfile['typical_hour'] !== null) ? (int) $foodProfile['typical_hour'] : null;

$macroSum = max(1.0, $p + $c + $f);
$proteinRatio = $p / $macroSum;
$carbRatio = $c / $macroSum;
$fatRatio = $f / $macroSum;
$calBand = min(1.0, $kcal / 800.0);

// Food energy (protein + density = workout fuel) vs comfort (carbs + fat + warmth)
$foodEnergy = (int) round(min(100, $proteinRatio * 60 + $calBand * 40));
$foodComfort = (int) round(min(100, $carbRatio * 50 + $fatRatio * 30 + $calBand * 20));
$lateNight = ($typicalHour !== null && ($typicalHour >= 21 || $typicalHour <= 4));

// Pseudo track-energy for the OFFLINE fallback only (stable per song, varied).
$trackEnergy = (int) (hexdec(substr(md5(mb_strtolower($track . '|' . $artist, 'UTF-8')), 0, 6)) % 101);

// Identifying meta saved alongside every result so the "Keep" action can
// persist exactly what the user is looking at (server-trusted, not client text).
$mixInputMeta = [
    'track_name'  => $track,
    'artist_name' => $artist,
    'food_item'   => $food,
    'calories'    => $kcal,
];

// --- Session cache (bumped to v2 for the new multi-dimensional schema) ---
$cacheKey = md5('v2|' . $track . '|' . $artist . '|' . $food . '|' . $kcal . '|' . $lang);
if (isset($_SESSION['beats_mixer_cache'][$cacheKey])) {
    $cached = $_SESSION['beats_mixer_cache'][$cacheKey];
    // Stash as the pending mix so it can be kept even on a cache hit.
    $_SESSION['beats_last_mix'] = array_merge($cached, $mixInputMeta);
    echo json_encode(array_merge(['ok' => true, 'cached' => true], $cached));
    exit;
}

// --- Build the real-data context block shared by both languages ---
$macroLine = sprintf('%dg protein / %dg carbs / %dg fat', (int) round($p), (int) round($c), (int) round($f));
$slotLabels = [
    'breakfast' => ['vi' => 'bữa sáng', 'en' => 'breakfast'],
    'lunch'     => ['vi' => 'bữa trưa', 'en' => 'lunch'],
    'dinner'    => ['vi' => 'bữa tối', 'en' => 'dinner'],
    'snack'     => ['vi' => 'bữa phụ', 'en' => 'snack'],
];
$slotWord = $mealSlot !== '' ? ($slotLabels[$mealSlot][$lang] ?? $mealSlot) : '';
$firstName = trim((string) ($_SESSION['user']['first_name'] ?? $_SESSION['user']['user_name'] ?? ''));

// --- Construct the Prompt for Gemini (rich, multi-dimensional analysis) ---
if ($lang === 'vi') {
    $systemPrompt = "Bạn là DJ Chef AI cảm thụ âm nhạc cực đỉnh của BitBalance, kể chuyện theo phong cách Spotify Wrapped: cá nhân, sắc sảo, vui.

DỮ LIỆU THẬT của người dùng (HÃY DÙNG để cá nhân hoá, đừng bịa):
- Bài hát: \"{$track}\" — nghệ sĩ \"{$artist}\".
- Món ăn: \"{$food}\", khoảng {$kcal} kcal, macro {$macroLine}.
- Thường ăn vào: " . ($slotWord !== '' ? $slotWord : 'không rõ') . ($lateNight ? ' (hay ăn khuya)' : '') . ".
- Người này đã log \"{$food}\" {$timesLogged} lần.
- Chỉ số món (tính sẵn, thang 0-100): độ-năng-lượng={$foodEnergy}, độ-an-ủi={$foodComfort}.

NHIỆM VỤ — trả về JSON thô (KHÔNG markdown, KHÔNG ```):
{
  \"archetype\": \"Tên 'nhân vật ẩm thực-âm nhạc' sáng tạo, có emoji, <40 ký tự (vd: 'Tay Trống Mì Cay Lúc Nửa Đêm 🤘🍜')\",
  \"detected_vibe\": \"Nhãn vibe nhạc ngắn + emoji (<25 ký tự)\",
  \"tagline\": \"1 câu móc nối nhạc và món, <14 từ\",
  \"scores\": {
    \"energy_sync\": 0-100,  // nhịp/độ bốc của nhạc khớp độ-năng-lượng món ({$foodEnergy}) tới đâu
    \"comfort\":     0-100,  // độ chữa lành; nên bám sát độ-an-ủi món ({$foodComfort}) ±15
    \"chaos\":       0-100   // độ lệch tông 'lạ mà cuốn' — cao = combo bất ngờ vui
  },
  \"verdict\": \"2 câu bình hài hước, nhắc ĐÍCH DANH bài hát/nghệ sĩ + món + 1 con số thật (kcal hoặc macro hoặc số lần log)\",
  \"fun_fact\": \"1 câu 'fun fact' bất ngờ dựa trên dữ liệu thật, <16 từ\",
  \"rarity\": \"Nhãn độ hiếm vui, vd 'Hiếm — chỉ 8% mix kiểu này'\"
}
QUY TẮC: Tuyệt đối KHÔNG body shaming, không phán xét cân nặng/thói quen. Giọng tích cực, khích lệ. Nếu thường ăn khuya hãy chơi đùa duyên dáng với điều đó.";
} else {
    $systemPrompt = "You are BitBalance's expert DJ Chef AI, narrating like Spotify Wrapped: personal, sharp, fun.

REAL user data (USE it to personalize, do NOT invent):
- Song: \"{$track}\" by \"{$artist}\".
- Food: \"{$food}\", ~{$kcal} kcal, macros {$macroLine}.
- Usually eaten at: " . ($slotWord !== '' ? $slotWord : 'unknown') . ($lateNight ? ' (often a late-night bite)' : '') . ".
- They have logged \"{$food}\" {$timesLogged} times.
- Food indices (precomputed, 0-100): energy={$foodEnergy}, comfort={$foodComfort}.

TASK — return RAW JSON (NO markdown, NO ```):
{
  \"archetype\": \"A creative music-food persona name with emoji, <40 chars (e.g. 'The Midnight Ramen Headbanger 🤘🍜')\",
  \"detected_vibe\": \"Short music vibe label + emoji (<25 chars)\",
  \"tagline\": \"One punchy line linking the song and the food, <14 words\",
  \"scores\": {
    \"energy_sync\": 0-100,  // how the song's tempo/intensity matches the food energy ({$foodEnergy})
    \"comfort\":     0-100,  // soothing factor; stay close to the food comfort index ({$foodComfort}) ±15
    \"chaos\":       0-100   // 'weird-but-it-works' clash; high = a delightfully surprising combo
  },
  \"verdict\": \"A 2-sentence witty take that NAMES the song/artist + food + one real number (kcal, a macro, or the log count)\",
  \"fun_fact\": \"One surprising 'fun fact' grounded in the real data, <16 words\",
  \"rarity\": \"A playful rarity label, e.g. 'Rare — only 8% mix it this way'\"
}
RULES: Absolutely NO body shaming, no judging weight or habits. Positive, hype tone. If they often eat late, riff on it playfully.";
}

$clamp = static fn($n) => max(0, min(100, (int) round((float) $n)));

// --- Result fields (multi-dimensional) ---
$archetype = '';
$detectedVibe = '';
$tagline = '';
$verdict = '';
$funFact = '';
$rarity = '';
$energySync = 0;
$comfortScore = 0;
$chaos = 0;
$success = false;

// --- Call Gemini API ---
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 9);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($httpCode === 200) {
        $resJson = json_decode($response, true);
        $rawText = $resJson['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $cleanText = trim(str_replace(['```json', '```'], '', $rawText));

        $parsed = json_decode($cleanText, true);
        if (is_array($parsed) && isset($parsed['scores']) && is_array($parsed['scores'])) {
            $energySync = $clamp($parsed['scores']['energy_sync'] ?? 50);
            $comfortScore = $clamp($parsed['scores']['comfort'] ?? $foodComfort);
            $chaos = $clamp($parsed['scores']['chaos'] ?? 50);
            $archetype = mixer_utf8_substr(trim((string) ($parsed['archetype'] ?? '')), 0, 60);
            $detectedVibe = mixer_utf8_substr(trim((string) ($parsed['detected_vibe'] ?? '')), 0, 40);
            $tagline = mixer_utf8_substr(trim((string) ($parsed['tagline'] ?? '')), 0, 120);
            $verdict = mixer_utf8_substr(trim((string) ($parsed['verdict'] ?? '')), 0, 240);
            $funFact = mixer_utf8_substr(trim((string) ($parsed['fun_fact'] ?? '')), 0, 160);
            $rarity = mixer_utf8_substr(trim((string) ($parsed['rarity'] ?? '')), 0, 60);
            $success = ($archetype !== '' && $verdict !== '');
        }
    }
} catch (Exception $e) {
    // Keep success false to fall back gracefully
}

// --- Personalized deterministic fallback (AI offline / bad format) ---
// Even offline this stays grounded in the user's real macros, slot & history,
// so the result never reads like a generic template.
if (!$success) {
    $energySync = $clamp(100 - abs($trackEnergy - $foodEnergy) * 0.8);
    $comfortScore = $foodComfort > 0 ? $foodComfort : $clamp(40 + $calBand * 30);
    $chaos = $clamp(min(100, abs($trackEnergy - $foodEnergy) * 0.9 + 25));

    if ($trackEnergy >= 67) {
        $adj = $lang === 'vi' ? 'Bùng Nổ' : 'High-Voltage';
        $vibeWord = $lang === 'vi' ? 'cháy hết mình' : 'high-octane';
    } elseif ($trackEnergy >= 34) {
        $adj = $lang === 'vi' ? 'Groovy' : 'Groovy';
        $vibeWord = $lang === 'vi' ? 'bắt nhịp' : 'in-the-pocket';
    } else {
        $adj = $lang === 'vi' ? 'Thư Giãn' : 'Mellow';
        $vibeWord = $lang === 'vi' ? 'nhẹ nhàng' : 'laid-back';
    }

    $personaMap = $lateNight
        ? ['vi' => 'Cú Đêm', 'en' => 'Night Owl']
        : ([
            'breakfast' => ['vi' => 'Bình Minh', 'en' => 'Sunriser'],
            'lunch'     => ['vi' => 'Trưa Năng Lượng', 'en' => 'Midday Maestro'],
            'dinner'    => ['vi' => 'Thực Khách', 'en' => 'Diner'],
            'snack'     => ['vi' => 'Thợ Săn Bữa Phụ', 'en' => 'Snack Bandit'],
        ][$mealSlot] ?? ['vi' => 'Tín Đồ', 'en' => 'Devotee']);
    $persona = $personaMap[$lang];

    $emoji = $trackEnergy >= 67 ? '🔥' : ($trackEnergy >= 34 ? '🎶' : '🌙');
    $archetype = $lang === 'vi'
        ? "{$persona} {$food} {$adj} {$emoji}"
        : "The {$adj} {$food} {$persona} {$emoji}";
    $detectedVibe = $lang === 'vi' ? "{$adj} {$emoji}" : "{$adj} Beats {$emoji}";

    $hasMacro = ($p + $c + $f) > 0;
    if ($lang === 'vi') {
        $tagline = "\"{$track}\" gặp {$food} — chất {$vibeWord}.";
        $verdict = "\"{$track}\" của {$artist} quyện cùng {$food} ({$kcal} kcal) cực {$vibeWord}."
            . ($timesLogged > 1 ? " Bạn đã chọn món này {$timesLogged} lần rồi đấy!" : '');
        $funFact = $lateNight
            ? "Combo khuya: {$food} + {$artist} đúng gu 'cú đêm' của bạn."
            : ($timesLogged >= 3
                ? "{$food} gần như là 'bài hát tủ' của bạn — log {$timesLogged} lần."
                : ($hasMacro ? "{$macroLine} chưa bao giờ nghe đã tai đến thế." : "{$kcal} kcal nghe cuốn bất ngờ."));
    } else {
        $tagline = "\"{$track}\" meets {$food} — pure {$vibeWord} energy.";
        $verdict = "\"{$track}\" by {$artist} blends with {$food} ({$kcal} kcal) in a {$vibeWord} way."
            . ($timesLogged > 1 ? " You've spun this fuel {$timesLogged} times!" : '');
        $funFact = $lateNight
            ? "Late-night {$food} with {$artist} on repeat — iconic."
            : ($timesLogged >= 3
                ? "{$food} is basically your signature track — logged {$timesLogged} times."
                : ($hasMacro ? "{$macroLine} never sounded this good." : "{$kcal} kcal never sounded this good."));
    }
}

// --- Headline score = average of the three dimensions (ring matches bars) ---
$matchScore = $clamp(($energySync + $comfortScore + $chaos) / 3);

if ($rarity === '') {
    if ($matchScore >= 80) {
        $rarity = $lang === 'vi' ? 'Huyền thoại — top 5% combo' : 'Legendary — top 5% of mixes';
    } elseif ($matchScore >= 60) {
        $rarity = $lang === 'vi' ? 'Hiếm — 12% mix kiểu này' : 'Rare — only 12% mix it this way';
    } else {
        $rarity = $lang === 'vi' ? 'Combo thử nghiệm táo bạo' : 'A bold experimental blend';
    }
}
if ($detectedVibe === '') {
    $detectedVibe = $lang === 'vi' ? 'Vibe Nghệ Thuật 🎵' : 'Artistic Vibe 🎵';
}

// --- Assemble result (new multi-dimensional fields + back-compat aliases) ---
$resultData = [
    'match_score'   => $matchScore,
    'archetype'     => $archetype,
    'detected_vibe' => $detectedVibe !== '' ? $detectedVibe : $archetype,
    'tagline'       => $tagline,
    'scores'        => [
        'energy_sync' => $energySync,
        'comfort'     => $comfortScore,
        'chaos'       => $chaos,
    ],
    'verdict'       => $verdict,
    'comment'       => $verdict, // back-compat: older UI reads `comment`
    'fun_fact'      => $funFact,
    'rarity'        => $rarity,
    'ai'            => $success, // lets the UI show a subtle "AI offline" hint
];

if (!isset($_SESSION['beats_mixer_cache']) || !is_array($_SESSION['beats_mixer_cache'])) {
    $_SESSION['beats_mixer_cache'] = [];
}
$_SESSION['beats_mixer_cache'][$cacheKey] = $resultData;

// --- Stash as the pending mix (NOT saved yet) ---
// The user decides on the result card whether to Keep (→ beats_mix_save.php
// persists this) or Discard. We trust this server-side copy, not client text.
$_SESSION['beats_last_mix'] = array_merge($resultData, $mixInputMeta);

// --- Return Response ---
echo json_encode(array_merge(['ok' => true, 'cached' => false], $resultData));
exit;
