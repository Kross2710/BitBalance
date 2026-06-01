<?php
/**
 * dashboard/handlers/beats_mirror.php
 * "The Mirror" — Diet & Beats personality endpoint (Direction 1).
 *
 * FOOD fingerprint (intakeLog) is fully deterministic. The MUSIC fingerprint is HYBRID:
 *   1) genres from Last.fm (artist.getTopTags), cached globally per artist  → DETERMINISTIC axes
 *   2) if Last.fm coverage is thin (no key / niche or local artists)        → Gemini infers axes
 * Spotify dev-mode no longer returns genres/popularity (Nov-2024 / Feb-2026 API changes), but it
 * still returns top-artist NAMES, which is all either path needs. See BEATS.md & docs/.
 *
 * One Gemini call max: the AI path infers axes + narrates together; the Last.fm path narrates only.
 *
 * GET (?debug=1 adds a _debug block). RMIT rules: PHP 7.4 only, no mbstring, SSL verify off.
 */
require_once __DIR__ . '/../../include/init.php';
require_once __DIR__ . '/../../include/handlers/beats_identity.php';
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(array('ok' => false, 'error' => 'Unauthorized'));
    exit;
}

$userId = (int) $_SESSION['user']['user_id'];
$lang = (function_exists('current_locale') && current_locale() === 'vi') ? 'vi' : 'en';

/** Refresh an expired Spotify access token; returns a fresh token or null. */
function bb_mirror_refresh_token($pdo, $userId, $spotifyRow)
{
    if (!defined('SPOTIFY_CLIENT_ID') || !defined('SPOTIFY_CLIENT_SECRET')) {
        return null;
    }
    $ch = curl_init('https://accounts.spotify.com/api/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array(
        'grant_type' => 'refresh_token',
        'refresh_token' => $spotifyRow['refresh_token'],
    )));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Authorization: Basic ' . base64_encode(SPOTIFY_CLIENT_ID . ':' . SPOTIFY_CLIENT_SECRET),
        'Content-Type: application/x-www-form-urlencoded',
    ));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 9);
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code === 200) {
        $data = json_decode($response, true);
        if (isset($data['access_token'])) {
            $expiresAt = date('Y-m-d H:i:s', time() + (int) $data['expires_in']);
            $stmt = $pdo->prepare("UPDATE user_spotify SET access_token = ?, expires_at = ? WHERE user_id = ?");
            $stmt->execute(array($data['access_token'], $expiresAt, $userId));
            return $data['access_token'];
        }
    }
    return null;
}

/** Authenticated GET against the Spotify Web API. Returns [httpCode, decodedArray|null]. */
function bb_mirror_spotify_get($url, $token)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer ' . $token));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 9);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return array($code, ($res !== false ? json_decode($res, true) : null));
}

/** Clip a UTF-8 string to n characters without relying on mbstring. */
function bb_mirror_clip($s, $n)
{
    $s = trim((string) $s);
    if ($s === '') {
        return '';
    }
    if (preg_match_all('/./us', $s, $m) && count($m[0]) > (int) $n) {
        return implode('', array_slice($m[0], 0, (int) $n));
    }
    return $s;
}

/** Fetch + filter an artist's Last.fm top tags → genre[] (empty on miss/no key). */
function bb_mirror_lastfm_tags($name, $apiKey)
{
    if ($apiKey === '' || trim((string) $name) === '') {
        return array();
    }
    $url = 'https://ws.audioscrobbler.com/2.0/?method=artist.gettoptags'
        . '&artist=' . rawurlencode($name)
        . '&autocorrect=1&format=json&api_key=' . rawurlencode($apiKey);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 4);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code !== 200 || $res === false) {
        return array();
    }
    $data = json_decode($res, true);
    $tags = isset($data['toptags']['tag']) ? $data['toptags']['tag'] : array();
    return bb_beats_filter_lastfm_tags($tags, 5, 10);
}

/**
 * One AI call → decoded JSON array, or null on any failure. Delegates to bb_beats_ai_text()
 * (OpenRouter first, then Gemini) so it works through the RMIT outbound firewall.
 */
function bb_mirror_gemini($prompt)
{
    $rawText = bb_beats_ai_text($prompt);
    if ($rawText === null) {
        return null;
    }
    $clean = trim(str_replace(array('```json', '```'), '', $rawText));
    $parsed = json_decode($clean, true);
    return is_array($parsed) ? $parsed : null;
}

// --- 1. Spotify connection ---------------------------------------------------
$spotifyRow = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM user_spotify WHERE user_id = ? LIMIT 1");
    $stmt->execute(array($userId));
    $spotifyRow = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $spotifyRow = null;
}

if (!$spotifyRow) {
    echo json_encode(array('ok' => true, 'connected' => false));
    exit;
}

$accessToken = $spotifyRow['access_token'];
if (strtotime($spotifyRow['expires_at']) <= time() + 30) {
    $accessToken = bb_mirror_refresh_token($pdo, $userId, $spotifyRow);
}
if (!$accessToken) {
    echo json_encode(array('ok' => false, 'connected' => true,
        'error' => ($lang === 'vi') ? 'Không thể cấp quyền Spotify.' : 'Unable to authorize Spotify.'));
    exit;
}

// --- 2. Music: harvest top-artist NAMES (+ any Spotify genres, usually none) --
$artistNames = array();
$genreWeights = array();
$distinctGenres = array();
$popularitySum = 0.0;
$popularityCount = 0;
$recCode = null;

list($topCode, $topData) = bb_mirror_spotify_get(
    'https://api.spotify.com/v1/me/top/artists?time_range=medium_term&limit=30', $accessToken);
$topArtists = ($topCode === 200 && isset($topData['items'])) ? $topData['items'] : array();

if (empty($topArtists)) {
    list($recCode, $recData) = bb_mirror_spotify_get(
        'https://api.spotify.com/v1/me/player/recently-played?limit=50', $accessToken);
    if ($recCode === 200 && isset($recData['items'])) {
        foreach ($recData['items'] as $item) {
            if (!isset($item['track']['artists'])) continue;
            foreach ($item['track']['artists'] as $a) {
                if (!empty($a['name'])) $topArtists[] = array('name' => $a['name'], 'genres' => array());
            }
        }
    }
}

foreach ($topArtists as $artist) {
    if (!is_array($artist)) continue;
    $nm = trim((string) ($artist['name'] ?? ''));
    if ($nm !== '') $artistNames[] = $nm;
    if (isset($artist['popularity'])) {
        $popularitySum += (float) $artist['popularity'];
        $popularityCount++;
    }
    if (!empty($artist['genres']) && is_array($artist['genres'])) {
        foreach ($artist['genres'] as $genre) {
            $g = strtolower((string) $genre);
            if ($g === '') continue;
            if (!isset($genreWeights[$g])) $genreWeights[$g] = 0;
            $genreWeights[$g]++;
            $distinctGenres[$g] = true;
        }
    }
}
$artistNames = array_slice(array_values(array_unique($artistNames)), 0, 30);
$avgPopularity = $popularityCount > 0 ? ($popularitySum / $popularityCount) : 50.0;
$artistCount = count($artistNames);
$debug = isset($_GET['debug']);

// --- 3. Food fingerprint (the personal, deterministic core) ------------------
$foodAxes = bb_beats_food_fingerprint($pdo, $userId, 30, 6);

if ($foodAxes === null || $artistCount === 0) {
    $resp = array(
        'ok' => true, 'connected' => true, 'forming' => true,
        'need' => ($foodAxes === null ? 'food' : 'music'),
    );
    if ($debug) {
        $resp['_debug'] = array('top_artists_http' => $topCode, 'recently_http' => $recCode,
            'artist_count' => $artistCount, 'genre_count' => count($distinctGenres));
    }
    echo json_encode($resp);
    exit;
}

// --- 3b. Genre enrichment via Last.fm (#1), shared global cache --------------
$lastfmKey = defined('LASTFM_API_KEY') ? LASTFM_API_KEY : '';
$enrichNames = array_slice($artistNames, 0, 15);          // bound work; cache warms over time
$cachedGenres = bb_get_artist_genres_bulk($pdo, $enrichNames);
$artistsTagged = 0;
$liveFetched = 0;
$maxLive = 12;                                            // cap live Last.fm calls per request

foreach ($enrichNames as $nm) {
    $k = strtolower(trim($nm));
    $genres = null;
    if (array_key_exists($k, $cachedGenres)) {
        $genres = $cachedGenres[$k];
    } elseif ($lastfmKey !== '' && $liveFetched < $maxLive) {
        $genres = bb_mirror_lastfm_tags($nm, $lastfmKey);
        bb_save_artist_genres($pdo, $nm, $genres);        // store [] too (negative cache)
        $liveFetched++;
    }
    if (is_array($genres) && !empty($genres)) {
        $artistsTagged++;
        foreach ($genres as $g) {
            $g = (string) $g;
            if ($g === '') continue;
            if (!isset($genreWeights[$g])) $genreWeights[$g] = 0;
            $genreWeights[$g]++;
            $distinctGenres[$g] = true;
        }
    }
}
$genreCoverage = !empty($enrichNames) ? ($artistsTagged / count($enrichNames)) : 0.0;
$useGenres = (!empty($genreWeights) && $genreCoverage >= 0.4);
$musicSource = $useGenres ? 'lastfm' : 'ai';

$diag = $debug ? array(
    'top_artists_http' => $topCode,
    'recently_http'    => $recCode,
    'artist_count'     => $artistCount,
    'genre_count'      => count($distinctGenres),
    'genre_coverage'   => round($genreCoverage, 2),
    'lastfm_live'      => $liveFetched,
    'lastfm_key_set'   => ($lastfmKey !== ''),
    'music_source'     => $musicSource,
) : null;

// --- 4. Input-based cache key (genre signature flips it to deterministic results) ---
$namesForKey = $artistNames;
sort($namesForKey);
$foodSig = '';
foreach (bb_beats_axes() as $ax) {
    $foodSig .= round($foodAxes[$ax], 1) . ',';
}
$genreKeys = array_keys($genreWeights);
sort($genreKeys);
$cacheKey = md5('mirror|v6|' . $lang . '|' . md5(implode('|', $namesForKey)) . '|' . $foodSig . '|' . md5(implode(',', $genreKeys)));

// Today's budget (fuel is recomputed live — deterministic, no AI cost).
$goal = (int) (getUserIntakeGoal($userId) ?? 0);
$consumedToday = (int) (getTotalCaloriesToday($userId) ?? 0);
$remainingKcal = $goal > 0 ? max(0, $goal - $consumedToday) : null;

$cached = bb_get_beats_mirror_cache($pdo, $userId, $lang);
if ($cached !== null && $cached['cache_key'] === $cacheKey) {
    $perAxis = isset($cached['payload']['congruence']['per_axis']) ? $cached['payload']['congruence']['per_axis'] : array();
    $mAxes = array('top_genre' => isset($cached['payload']['music']['top_genre']) ? $cached['payload']['music']['top_genre'] : '');
    foreach (bb_beats_axes() as $ax) {
        $mAxes[$ax] = isset($perAxis[$ax]['music']) ? ($perAxis[$ax]['music'] / 100.0) : 0.5;
    }
    $cached['payload']['fuel_suggestions'] = bb_beats_fuel_suggestions($mAxes, $remainingKcal, $lang);
    $out = array_merge(array('ok' => true, 'cached' => true), $cached['payload']);
    if ($debug) { $out['_debug'] = $diag; }
    echo json_encode($out);
    exit;
}

// --- 5. Resolve music axes + narration --------------------------------------
$macro = $foodAxes['macro_g'];
$macroLine = sprintf('%dg protein / %dg carbs / %dg fat', $macro['protein'], $macro['carbs'], $macro['fat']);
$topFood = $foodAxes['top_food'];
$fe = (int) round($foodAxes['energy'] * 100);
$fc = (int) round($foodAxes['comfort'] * 100);
$fd = (int) round($foodAxes['diversity'] * 100);
$fn = (int) round($foodAxes['nocturnal'] * 100);

$musicAxes = null;
$verdict = ''; $tagline = ''; $funFact = '';
$aiOk = false;

if ($musicSource === 'lastfm') {
    // Deterministic axes from real genres → compute scoring, then narrate (cites the score).
    $musicAxes = bb_beats_music_axes($genreWeights, $avgPopularity, count($distinctGenres));
    $congruence = bb_beats_congruence($musicAxes, $foodAxes);
    $score = (int) $congruence['score'];
    $topGenre = $musicAxes['top_genre'];
    $combined = bb_beats_combine($musicAxes, $foodAxes);
    $archetype = bb_beats_assign_archetype($combined, $lang);

    if ($lang === 'vi') {
        $prompt = "Bạn là người kể chuyện AI của BitBalance, giọng Spotify Wrapped: cá nhân, dí dỏm, tích cực.\n\n"
            . "SỐ LIỆU THẬT đã tính sẵn (ĐỪNG bịa, hãy diễn giải hay):\n"
            . "- Độ Đồng Điệu (nhạc khớp món) = {$score}/100.\n"
            . "- Hình mẫu: \"{$archetype['name']}\" ({$archetype['voice']}).\n"
            . "- Genre tủ: \"{$topGenre}\". Món tủ: \"{$topFood}\" (macro {$macroLine}).\n"
            . "- Trục NHẠC: năng lượng {$congruence['per_axis']['energy']['music']}, an ủi {$congruence['per_axis']['comfort']['music']}, đa dạng {$congruence['per_axis']['diversity']['music']}, cú đêm {$congruence['per_axis']['nocturnal']['music']}.\n"
            . "- Trục MÓN: năng lượng {$congruence['per_axis']['energy']['food']}, an ủi {$congruence['per_axis']['comfort']['food']}, đa dạng {$congruence['per_axis']['diversity']['food']}, cú đêm {$congruence['per_axis']['nocturnal']['food']}.\n\n"
            . "Trả JSON THÔ (không markdown, không ```):\n"
            . "{ \"verdict\": \"2 câu hài hước nhắc ĐÍCH DANH genre tủ + món tủ + nhắc số {$score}\", \"tagline\": \"1 câu <12 từ\", \"fun_fact\": \"1 fun fact <16 từ\" }\n"
            . "QUY TẮC: không body shaming, không phán xét cân nặng/calo. Đồng Điệu thấp = 'song trùng thú vị', đừng chê.";
    } else {
        $prompt = "You are BitBalance's AI storyteller, Spotify-Wrapped voice: personal, witty, positive.\n\n"
            . "PRE-COMPUTED REAL numbers (do NOT invent, narrate them well):\n"
            . "- Congruence (music matches food) = {$score}/100.\n"
            . "- Archetype: \"{$archetype['name']}\" ({$archetype['voice']}).\n"
            . "- Top genre: \"{$topGenre}\". Top food: \"{$topFood}\" (macros {$macroLine}).\n"
            . "- MUSIC axes: energy {$congruence['per_axis']['energy']['music']}, comfort {$congruence['per_axis']['comfort']['music']}, diversity {$congruence['per_axis']['diversity']['music']}, nocturnal {$congruence['per_axis']['nocturnal']['music']}.\n"
            . "- FOOD axes: energy {$congruence['per_axis']['energy']['food']}, comfort {$congruence['per_axis']['comfort']['food']}, diversity {$congruence['per_axis']['diversity']['food']}, nocturnal {$congruence['per_axis']['nocturnal']['food']}.\n\n"
            . "Return RAW JSON (no markdown, no ```):\n"
            . "{ \"verdict\": \"2 witty sentences naming the top genre + top food + citing {$score}\", \"tagline\": \"one line <12 words\", \"fun_fact\": \"one fun fact <16 words\" }\n"
            . "RULES: no body shaming, no judging weight/calories. Low Congruence = 'delightful duality', never a flaw.";
    }

    $parsed = bb_mirror_gemini($prompt);
    if (is_array($parsed)) {
        $verdict = bb_mirror_clip($parsed['verdict'] ?? '', 240);
        $tagline = bb_mirror_clip($parsed['tagline'] ?? '', 120);
        $funFact = bb_mirror_clip($parsed['fun_fact'] ?? '', 160);
        $aiOk = ($verdict !== '');
    }
    if (!$aiOk) {
        // Deterministic narration fallback (card still renders from real genre data).
        if ($score >= 75) {
            $verdict = ($lang === 'vi')
                ? "Tai và miệng bạn đúng là một cặp: \"{$topGenre}\" quyện với \"{$topFood}\" đạt {$score}% đồng điệu."
                : "Your ears and your plate are clearly the same person — \"{$topGenre}\" and \"{$topFood}\" hit {$score}% in sync.";
        } elseif ($score >= 50) {
            $verdict = ($lang === 'vi')
                ? "Khá ăn rơ: gu \"{$topGenre}\" và món \"{$topFood}\" gặp nhau ở mức {$score}%."
                : "A solid match: your \"{$topGenre}\" taste meets \"{$topFood}\" at {$score}%.";
        } else {
            $verdict = ($lang === 'vi')
                ? "Hai con người thú vị: \"{$topGenre}\" và \"{$topFood}\" chỉ {$score}% — một tương phản duyên dáng."
                : "A delightful duality: \"{$topGenre}\" vs \"{$topFood}\" land at just {$score}% — and that's charming.";
        }
        $funFact = ($lang === 'vi') ? "Món tủ của bạn có macro {$macroLine}." : "Your go-to fuel carries {$macroLine}.";
    }
    if ($tagline === '') { $tagline = $archetype['voice']; }

} else {
    // No usable genres → infer the music axes from artist names AND narrate in one call.
    $artistListStr = implode(', ', $artistNames);
    if ($lang === 'vi') {
        $prompt = "Bạn là người kể chuyện AI của BitBalance, giọng Spotify Wrapped: cá nhân, dí dỏm, tích cực.\n\n"
            . "Nghệ sĩ nghe nhiều nhất (giảm dần): {$artistListStr}.\n"
            . "Vân tay ĂN UỐNG (0-100): năng lượng {$fe}, an ủi {$fc}, đa dạng {$fd}, cú đêm {$fn}. Món tủ: \"{$topFood}\" (macro {$macroLine}).\n\n"
            . "NHIỆM VỤ — dựa trên hiểu biết về các nghệ sĩ trên, ước lượng gu NHẠC. Trả JSON THÔ (không markdown, không ```):\n"
            . "{ \"music\": { \"energy\": 0-100, \"comfort\": 0-100, \"diversity\": 0-100, \"nocturnal\": 0-100, \"top_genre\": \"nhãn genre ngắn\" }, "
            . "\"verdict\": \"2 câu hài hước so sánh CÁCH NGHE vs CÁCH ĂN, nhắc genre + món tủ, KHÔNG nêu phần trăm\", \"tagline\": \"1 câu <12 từ\", \"fun_fact\": \"1 fun fact <16 từ\" }\n"
            . "Ý nghĩa trục: energy=độ bốc; comfort=dịu/êm; diversity=đa dạng thể loại; nocturnal=thiên về đêm/lo-fi.\n"
            . "QUY TẮC: không body shaming, không phán xét cân nặng/calo.";
    } else {
        $prompt = "You are BitBalance's AI storyteller (Spotify-Wrapped voice: personal, witty, positive).\n\n"
            . "Most-listened artists (most first): {$artistListStr}.\n"
            . "EATING fingerprint (0-100): energy {$fe}, comfort {$fc}, diversity {$fd}, nocturnal {$fn}. Top food: \"{$topFood}\" (macros {$macroLine}).\n\n"
            . "TASK — using your knowledge of those artists, estimate their MUSIC taste. Return RAW JSON (no markdown, no ```):\n"
            . "{ \"music\": { \"energy\": 0-100, \"comfort\": 0-100, \"diversity\": 0-100, \"nocturnal\": 0-100, \"top_genre\": \"short genre label\" }, "
            . "\"verdict\": \"2 witty sentences comparing how they LISTEN vs EAT, naming genre + top food, no percentage\", \"tagline\": \"one line <12 words\", \"fun_fact\": \"one fun fact <16 words\" }\n"
            . "Axis meaning: energy=intensity; comfort=soothing; diversity=genre breadth; nocturnal=late-night/lo-fi lean.\n"
            . "RULES: no body shaming, no judging weight/calories.";
    }

    $parsed = bb_mirror_gemini($prompt);
    if (is_array($parsed) && isset($parsed['music']) && is_array($parsed['music'])) {
        $m = $parsed['music'];
        $musicAxes = array(
            'energy'    => bb_beats_clamp01(((float) ($m['energy'] ?? 50)) / 100.0),
            'comfort'   => bb_beats_clamp01(((float) ($m['comfort'] ?? 50)) / 100.0),
            'diversity' => bb_beats_clamp01(((float) ($m['diversity'] ?? 50)) / 100.0),
            'nocturnal' => bb_beats_clamp01(((float) ($m['nocturnal'] ?? 50)) / 100.0),
            'top_genre' => bb_mirror_clip($m['top_genre'] ?? '', 40),
        );
        $verdict = bb_mirror_clip($parsed['verdict'] ?? '', 240);
        $tagline = bb_mirror_clip($parsed['tagline'] ?? '', 120);
        $funFact = bb_mirror_clip($parsed['fun_fact'] ?? '', 160);
        $aiOk = ($verdict !== '');
    }

    if (!$aiOk || $musicAxes === null) {
        $resp = array('ok' => true, 'connected' => true, 'forming' => true, 'need' => 'music_unavailable');
        if ($debug) { $resp['_debug'] = $diag; }
        echo json_encode($resp);
        exit;
    }

    $topGenre = $musicAxes['top_genre'] !== '' ? $musicAxes['top_genre'] : ($lang === 'vi' ? 'gu nhạc của bạn' : 'your taste');
    $congruence = bb_beats_congruence($musicAxes, $foodAxes);
    $combined = bb_beats_combine($musicAxes, $foodAxes);
    $archetype = bb_beats_assign_archetype($combined, $lang);
    if ($tagline === '') { $tagline = $archetype['voice']; }
}

$fuelSuggestions = bb_beats_fuel_suggestions($musicAxes, $remainingKcal, $lang);

// --- 6. Assemble + cache + respond ------------------------------------------
$payload = array(
    'connected'  => true,
    'forming'    => false,
    'congruence' => $congruence,
    'archetype'  => array('name' => $archetype['name'], 'icon' => $archetype['icon'], 'key' => $archetype['key']),
    'music'      => array('top_genre' => $topGenre, 'source' => $musicSource),
    'food'       => array('top_food' => $topFood, 'avg_kcal' => $foodAxes['avg_kcal'],
                          'distinct_foods' => $foodAxes['distinct_foods'], 'total_logs' => $foodAxes['total_logs']),
    'narration'  => array('verdict' => $verdict, 'tagline' => $tagline, 'fun_fact' => $funFact),
    'fuel_suggestions' => $fuelSuggestions,
    'ai'         => $aiOk,
);

bb_save_beats_mirror_cache($pdo, $userId, $lang, $cacheKey, $payload);

$out = array_merge(array('ok' => true, 'cached' => false), $payload);
if ($debug) { $out['_debug'] = $diag; }
echo json_encode($out);
exit;
