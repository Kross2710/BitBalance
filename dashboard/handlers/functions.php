<?php
require_once __DIR__ . '/../../include/handlers/log_attempt.php';

/**
 * Convert a DB DATETIME string (stored as Vietnam local time, see
 * include/db_config.php SET time_zone = '+07:00') into an ISO 8601 string
 * with explicit +07:00 offset, e.g. "2025-05-26T14:30:00+07:00".
 *
 * The client-side helper formatLocal() (see dashboard/views/local-time-script.php)
 * then converts this ISO string to the visitor's local timezone via the browser's
 * Intl APIs — so users in Tokyo see Tokyo time, users in NYC see NYC time, etc.
 *
 * Returns '' for null/empty input.
 */
function toIsoVN($dbDatetime): string
{
    if (empty($dbDatetime)) return '';
    try {
        $dt = new DateTime($dbDatetime, new DateTimeZone('Asia/Ho_Chi_Minh'));
        return $dt->format('c'); // ISO 8601 with offset
    } catch (Exception $e) {
        return '';
    }
}

function updateLoggingStreak(PDO $pdo, int $userId): void
{
    // Fetch current status row (lock it FOR UPDATE to prevent race conditions)
    $stmt = $pdo->prepare("
        SELECT last_logging_date, logging_streak, longest_logging_streak, streak_freezes, broken_streak
        FROM userStatus 
        WHERE user_id = ? 
        FOR UPDATE
    ");
    $stmt->execute([$userId]);
    $status = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$status) {
        throw new RuntimeException("User status not found for user ID $userId");
    }

    $today = new DateTimeImmutable('today');      // 00:00 today
    $yesterday = $today->modify('-1 day');            // 00:00 yesterday
    $now = new DateTimeImmutable('now', new DateTimeZone('Australia/Sydney')); // configurable

    $lastLogging = isset($status['last_logging_date']) ? new DateTimeImmutable($status['last_logging_date']) : null;
    $streak = isset($status['logging_streak']) ? (int) $status['logging_streak'] : 0; // default to 0
    $longest = isset($status['longest_logging_streak']) ? (int) $status['longest_logging_streak'] : 0;
    $freezes = isset($status['streak_freezes']) ? (int) $status['streak_freezes'] : 0;
    $broken = isset($status['broken_streak']) ? (int) $status['broken_streak'] : 0;

    /* --- decide new streak value --- */
    if ($lastLogging && $lastLogging->format('Y-m-d') === $today->format('Y-m-d')) {
        // Already logged today → nothing to do
        return;
    }

    if ($lastLogging && $lastLogging->format('Y-m-d') === $yesterday->format('Y-m-d')) {
        $streak++;  // consecutive day
        log_attempt($pdo, $userId, 'streak_update', "Streak incremented to $streak");
    } else {
        // Streak is broken (either first login or missed yesterday)
        if ($lastLogging && $freezes > 0) {
            $freezes--;
            $streak++; // keep streak active and increment
            log_attempt($pdo, $userId, 'streak_freeze_consumed', "Streak freeze consumed. Streak preserved and incremented to $streak. Freezes left: $freezes");
        } else {
            if ($streak > 1) {
                $broken = $streak;
            }
            $streak = 1;  // reset / first login
            log_attempt($pdo, $userId, 'streak_reset', "Streak reset to 1. Broken streak stored: $broken");
        }
    }

    // update longest streak if needed
    if ($streak > $longest) {
        $longest = $streak;
    }

    // Persist changes
    $upd = $pdo->prepare("
        UPDATE userStatus 
        SET last_logging_date = ?, logging_streak = ?, longest_logging_streak = ?, streak_freezes = ?, broken_streak = ?
        WHERE user_id = ?
    ");
    $upd->execute([$now->format('Y-m-d H:i:s'), $streak, $longest, $freezes, $broken, $userId]);
}

function getTotalCaloriesToday($userId, $date = null)
{
    global $pdo;
    $date = $date ?: date('Y-m-d');

    // Count total calories for the day
    $stmt = $pdo->prepare("
        SELECT SUM(calories) AS total_calories
        FROM intakeLog
        WHERE user_id = ?
        AND DATE(date_intake) = ?
    ");
    $stmt->execute([$userId, $date]);
    return $stmt->fetchColumn();
}

function getIntakeLogToday(int $userId, $date = null)
{
    global $pdo;
    $date = $date ?: date('Y-m-d');

    // Fetch intake log for the user, including local time (hour:minute)
    // image_path is needed so the "Photo" badge survives a page refresh (rows
    // logged with a chat-bubble photo render it via views/_intake-row.php).
    $stmt = $pdo->prepare("
        SELECT intakeLog_id, food_item, meal_category, calories, protein, carbs, fat, image_path, date_intake
        FROM intakeLog
        WHERE user_id = ?
        AND DATE(date_intake) = ?
        ORDER BY date_intake DESC
    ");
    $stmt->execute([$userId, $date]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getMacroTotalsToday(int $userId, $date = null): array
{
    global $pdo;
    $date = $date ?: date('Y-m-d');

    $stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(protein), 0) AS protein,
            COALESCE(SUM(carbs),   0) AS carbs,
            COALESCE(SUM(fat),     0) AS fat
        FROM intakeLog
        WHERE user_id = ?
        AND DATE(date_intake) = ?
    ");
    $stmt->execute([$userId, $date]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    return [
        'protein' => (float) ($row['protein'] ?? 0),
        'carbs'   => (float) ($row['carbs']   ?? 0),
        'fat'     => (float) ($row['fat']     ?? 0),
    ];
}

// Recommended macro split based on calorie goal: 30% protein, 45% carbs, 25% fat.
// Returns grams for each macro (protein/carbs = 4 kcal/g, fat = 9 kcal/g).
function getMacroGoalsFromCalorieGoal(?int $calorieGoal): array
{
    if (!$calorieGoal || $calorieGoal <= 0) {
        return ['protein' => 0, 'carbs' => 0, 'fat' => 0];
    }
    return [
        'protein' => (int) round(($calorieGoal * 0.30) / 4),
        'carbs'   => (int) round(($calorieGoal * 0.45) / 4),
        'fat'     => (int) round(($calorieGoal * 0.25) / 9),
    ];
}

function getUserIntakeGoal($userId)
{
    global $pdo;

    // Fetch User goal if set
    $stmtGoal = $pdo->prepare("
        SELECT calorie_goal
        FROM userGoal
        WHERE user_id = ?
        ORDER BY date_set DESC
        LIMIT 1
    ");
    $stmtGoal->execute([$userId]);
    return $stmtGoal->fetchColumn();
}

function getPhysicalInfo($userId)
{
    global $pdo;

    $stmt = $pdo->prepare("SELECT age, gender, weight, height FROM userPhysicalInfo WHERE user_id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getUserIntakeHistory($userId)
{
    global $pdo;

    // Fetch intake history for the user
    // image_path included so the "Photo" badge renders on the full History page
    // too (same views/_intake-row.php partial as Today's History).
    $stmt = $pdo->prepare("
        SELECT intakeLog_id, food_item, meal_category, calories, protein, carbs, fat, image_path, date_intake
        FROM intakeLog
        WHERE user_id = ?
        ORDER BY date_intake DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getUserLoggingStreak($userId)
{
    global $pdo;

    // Fetch the user's logging streak and freeze status
    $stmt = $pdo->prepare("
        SELECT logging_streak, longest_logging_streak, streak_freezes, broken_streak
        FROM userStatus
        WHERE user_id = ?
    ");
    $stmt->execute([$userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function calculateCalorieAverage($array)
{
    if (empty($array)) {
        return 0; // Avoid division by zero
    }
    // Calculate the average of the array but ignore zero values
    $filteredArray = array_filter($array, fn($value) => $value > 0); // Arrow function
    $count = count($filteredArray);
    return $count > 0 ? round(array_sum($filteredArray) / $count, 0) : 0; // Avoid division by zero
}

/* =========================================================================
   DIET & BEATS — DJ Mixer history + archetype collection
   ========================================================================= */

/**
 * Lazily create the mix-log table. Guarded by a session flag so the DDL runs
 * at most once per session (the DB is only reachable via Apache here, so a
 * self-bootstrapping CREATE IF NOT EXISTS is the most reliable migration).
 */
function bb_ensure_beats_mix_table(PDO $pdo): void
{
    if (!empty($_SESSION['beats_mix_table_ok'])) {
        return;
    }
    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `beats_mix_log` (
                `mix_id` INT(11) NOT NULL AUTO_INCREMENT,
                `user_id` INT(11) NOT NULL,
                `track_name` VARCHAR(120) NOT NULL,
                `artist_name` VARCHAR(120) NOT NULL DEFAULT '',
                `food_item` VARCHAR(120) NOT NULL,
                `calories` INT(11) NOT NULL DEFAULT 0,
                `archetype` VARCHAR(80) NOT NULL DEFAULT '',
                `archetype_key` VARCHAR(40) NOT NULL DEFAULT '',
                `detected_vibe` VARCHAR(60) NOT NULL DEFAULT '',
                `match_score` TINYINT(4) NOT NULL DEFAULT 0,
                `energy_sync` TINYINT(4) NOT NULL DEFAULT 0,
                `comfort` TINYINT(4) NOT NULL DEFAULT 0,
                `chaos` TINYINT(4) NOT NULL DEFAULT 0,
                `verdict` VARCHAR(255) NOT NULL DEFAULT '',
                `rarity` VARCHAR(60) NOT NULL DEFAULT '',
                `created_at` TIMESTAMP NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`mix_id`),
                KEY `user_created` (`user_id`, `created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        // Backfill archetype_key for tables created before the Pokédex (ignore "duplicate column").
        try {
            $pdo->exec("ALTER TABLE `beats_mix_log` ADD COLUMN `archetype_key` VARCHAR(40) NOT NULL DEFAULT '' AFTER `archetype`");
        } catch (PDOException $e) {
            // Column already exists → fine.
        }
        $_SESSION['beats_mix_table_ok'] = true;
    } catch (PDOException $e) {
        // Leave the flag unset so we retry next time; callers handle absence.
    }
}

/** Persist one freshly computed mix result. Returns the new row id (0 on failure). */
function bb_log_beats_mix(PDO $pdo, int $userId, array $r): int
{
    bb_ensure_beats_mix_table($pdo);
    try {
        $scores = is_array($r['scores'] ?? null) ? $r['scores'] : [];
        $stmt = $pdo->prepare(
            "INSERT INTO `beats_mix_log`
                (user_id, track_name, artist_name, food_item, calories, archetype, archetype_key,
                 detected_vibe, match_score, energy_sync, comfort, chaos, verdict, rarity)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
        );
        $stmt->execute([
            $userId,
            (string) ($r['track_name'] ?? ''),
            (string) ($r['artist_name'] ?? ''),
            (string) ($r['food_item'] ?? ''),
            (int) ($r['calories'] ?? 0),
            (string) ($r['archetype'] ?? ''),
            (string) ($r['archetype_key'] ?? ''),
            (string) ($r['detected_vibe'] ?? ''),
            (int) ($r['match_score'] ?? 0),
            (int) ($scores['energy_sync'] ?? 0),
            (int) ($scores['comfort'] ?? 0),
            (int) ($scores['chaos'] ?? 0),
            (string) ($r['verdict'] ?? ''),
            (string) ($r['rarity'] ?? ''),
        ]);
        return (int) $pdo->lastInsertId();
    } catch (PDOException $e) {
        // Ignore — logging history must never break the mixer response.
        return 0;
    }
}

/** Delete one mix owned by the user. Returns true if a row was removed. */
function bb_delete_beats_mix(PDO $pdo, int $userId, int $mixId): bool
{
    try {
        $stmt = $pdo->prepare("DELETE FROM `beats_mix_log` WHERE mix_id = ? AND user_id = ?");
        $stmt->execute([$mixId, $userId]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/** How many mixes a user still has for a given archetype (for live collection upkeep). */
function bb_count_beats_archetype(PDO $pdo, int $userId, string $archetype): int
{
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `beats_mix_log` WHERE user_id = ? AND archetype = ?");
        $stmt->execute([$userId, $archetype]);
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/** Most recent mixes for a user (newest first). */
function bb_get_beats_mix_history(PDO $pdo, int $userId, int $limit = 12): array
{
    bb_ensure_beats_mix_table($pdo);
    try {
        $limit = max(1, min(50, $limit));
        $stmt = $pdo->prepare(
            "SELECT mix_id, track_name, artist_name, food_item, calories, archetype, detected_vibe,
                    match_score, energy_sync, comfort, chaos, verdict, rarity, created_at
             FROM `beats_mix_log`
             WHERE user_id = ?
             ORDER BY mix_id DESC
             LIMIT {$limit}"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Distinct archetypes a user has unlocked, each with its best score and how
 * many times reached. Powers the "collection" strip.
 */
function bb_get_beats_collection(PDO $pdo, int $userId): array
{
    bb_ensure_beats_mix_table($pdo);
    try {
        $stmt = $pdo->prepare(
            "SELECT archetype, MAX(match_score) AS best_score, COUNT(*) AS hits, MAX(rarity) AS rarity
             FROM `beats_mix_log`
             WHERE user_id = ? AND archetype <> ''
             GROUP BY archetype
             ORDER BY best_score DESC, hits DESC"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Pokédex view: the FULL hand-designed catalog with the user's owned/locked status,
 * per-archetype stats (hits, best score, first-caught, best combo, shiny) and intrinsic
 * rarity tier. Grouped by the stable `archetype_key` so it survives language switches.
 */
function bb_beats_collection_dex(PDO $pdo, int $userId, string $lang = 'en'): array
{
    require_once __DIR__ . '/../../include/handlers/beats_identity.php';
    bb_ensure_beats_mix_table($pdo);
    $lang = ($lang === 'vi') ? 'vi' : 'en';

    // Owned aggregates keyed by archetype_key.
    $owned = array();
    try {
        $stmt = $pdo->prepare(
            "SELECT archetype_key, COUNT(*) AS hits, MAX(match_score) AS best,
                    MIN(created_at) AS first_at, (MAX(match_score) >= 90) AS shiny
             FROM `beats_mix_log`
             WHERE user_id = ? AND archetype_key <> ''
             GROUP BY archetype_key"
        );
        $stmt->execute([$userId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $owned[$row['archetype_key']] = $row;
        }
    } catch (PDOException $e) {
        $owned = array();
    }

    // Highest-scoring combo per archetype_key (the "signature" pairing).
    $combos = array();
    try {
        $stmt = $pdo->prepare(
            "SELECT t.archetype_key, t.track_name, t.food_item
             FROM `beats_mix_log` t
             INNER JOIN (
                 SELECT archetype_key, MAX(match_score) AS mx
                 FROM `beats_mix_log`
                 WHERE user_id = ? AND archetype_key <> ''
                 GROUP BY archetype_key
             ) b ON b.archetype_key = t.archetype_key AND t.match_score = b.mx
             WHERE t.user_id = ?
             GROUP BY t.archetype_key"
        );
        $stmt->execute([$userId, $userId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $combos[$row['archetype_key']] = array($row['track_name'], $row['food_item']);
        }
    } catch (PDOException $e) {
        $combos = array();
    }

    $dex = array();
    foreach (bb_beats_archetype_catalog() as $a) {
        $key = $a['key'];
        $o = isset($owned[$key]) ? $owned[$key] : null;
        $dex[] = array(
            'key'      => $key,
            'name'     => $a['name'][$lang],
            'icon'     => $a['icon'],
            'voice'    => $a['voice'][$lang],
            'rarity'   => bb_beats_archetype_rarity($key),
            'owned'    => ($o !== null),
            'hits'     => $o ? (int) $o['hits'] : 0,
            'best'     => $o ? (int) $o['best'] : 0,
            'first_at' => $o ? $o['first_at'] : null,
            'shiny'    => $o ? ((int) $o['shiny'] === 1) : false,
            'combo'    => isset($combos[$key]) ? $combos[$key] : null,
        );
    }
    return $dex;
}

/* ===================== The Mirror — identity DB cache ===================== */

/** Create the Mirror identity cache table (lazy, once per session). */
function bb_ensure_beats_mirror_cache_table(PDO $pdo): void
{
    if (!empty($_SESSION['beats_mirror_cache_table_ok'])) {
        return;
    }
    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `beats_mirror_cache` (
                `user_id` INT(11) NOT NULL,
                `lang` VARCHAR(5) NOT NULL DEFAULT 'en',
                `cache_key` VARCHAR(64) NOT NULL DEFAULT '',
                `payload_json` MEDIUMTEXT NOT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                PRIMARY KEY (`user_id`, `lang`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $_SESSION['beats_mirror_cache_table_ok'] = true;
    } catch (PDOException $e) {
        // Leave the flag unset so we retry next time; callers tolerate absence.
    }
}

/** Read the cached Mirror payload for a user+lang. Returns ['cache_key','payload'] or null. */
function bb_get_beats_mirror_cache(PDO $pdo, int $userId, string $lang)
{
    bb_ensure_beats_mirror_cache_table($pdo);
    try {
        $stmt = $pdo->prepare("SELECT cache_key, payload_json FROM `beats_mirror_cache` WHERE user_id = ? AND lang = ? LIMIT 1");
        $stmt->execute([$userId, $lang]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $payload = json_decode($row['payload_json'], true);
        if (!is_array($payload)) {
            return null;
        }
        return ['cache_key' => (string) $row['cache_key'], 'payload' => $payload];
    } catch (PDOException $e) {
        return null;
    }
}

/** Upsert the cached Mirror payload. Failures are swallowed — caching must never break the response. */
function bb_save_beats_mirror_cache(PDO $pdo, int $userId, string $lang, string $cacheKey, array $payload): void
{
    bb_ensure_beats_mirror_cache_table($pdo);
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO `beats_mirror_cache` (user_id, lang, cache_key, payload_json)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE cache_key = VALUES(cache_key), payload_json = VALUES(payload_json), created_at = CURRENT_TIMESTAMP"
        );
        $stmt->execute([$userId, $lang, $cacheKey, json_encode($payload)]);
    } catch (PDOException $e) {
        // ignore
    }
}

/* ============ Diet & Beats — AI text call (OpenRouter first, Gemini fallback) ============ */
/**
 * Single AI text call for the Beats handlers. Tries OpenRouter first because the RMIT
 * outbound firewall breaks DIRECT calls to generativelanguage.googleapis.com (this is why
 * mascot_chat.php was migrated to OpenRouter). Falls back to Gemini direct where reachable
 * (e.g. local XAMPP). Returns the model's raw text reply, or null if all providers fail.
 * Callers strip ```json fences and json_decode() as needed.
 */
function bb_beats_ai_text($prompt)
{
    $prompt = (string) $prompt;
    if ($prompt === '') {
        return null;
    }

    // 1. OpenRouter (OpenAI-compatible /chat/completions) — the path that works on RMIT.
    //    Try the configured model, then a couple of free fallbacks: free models 429
    //    ("temporarily rate-limited") intermittently, so one alone is not reliable.
    if (defined('OPENROUTER_API_KEY') && OPENROUTER_API_KEY !== '') {
        $models = array();
        if (defined('OPENROUTER_MODEL') && OPENROUTER_MODEL !== '') {
            $models[] = OPENROUTER_MODEL;
        }
        foreach (array('google/gemma-4-31b-it:free', 'meta-llama/llama-3.3-70b-instruct:free') as $fallbackModel) {
            if (!in_array($fallbackModel, $models, true)) {
                $models[] = $fallbackModel;
            }
        }
        foreach ($models as $model) {
            $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array(
                'model' => $model,
                'messages' => array(array('role' => 'user', 'content' => $prompt)),
            )));
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Authorization: Bearer ' . OPENROUTER_API_KEY,
                'Content-Type: application/json',
            ));
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            $res = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ((int) $code === 200 && $res !== false) {
                $data = json_decode($res, true);
                $text = isset($data['choices'][0]['message']['content']) ? trim((string) $data['choices'][0]['message']['content']) : '';
                if ($text !== '') {
                    return $text;
                }
            }
        }
    }

    // 2. Gemini direct fallback (reachable on local XAMPP; blocked on RMIT).
    if (defined('GEMINI_API_KEY') && GEMINI_API_KEY !== '') {
        $model = defined('AI_COACH_MODEL') ? AI_COACH_MODEL : 'gemini-3.1-flash-lite';
        $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . GEMINI_API_KEY);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array(
            'contents' => array(array('parts' => array(array('text' => $prompt)))),
        )));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 12);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ((int) $code === 200 && $res !== false) {
            $data = json_decode($res, true);
            $text = isset($data['candidates'][0]['content']['parts'][0]['text']) ? (string) $data['candidates'][0]['content']['parts'][0]['text'] : '';
            if (trim($text) !== '') {
                return $text;
            }
        }
    }

    return null;
}

/* ============ The Mirror — global artist→genre cache (Last.fm, #1) ============ */
// Genres for an artist are the same for everyone, so this cache is shared across all
// users → most lookups are free and Last.fm is hit only for never-seen artists.

/** Create the shared artist→genre cache table (lazy, once per session). */
function bb_ensure_beats_artist_genre_table(PDO $pdo): void
{
    if (!empty($_SESSION['beats_artist_genre_table_ok'])) {
        return;
    }
    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `beats_artist_genre_cache` (
                `artist_key` VARCHAR(190) NOT NULL,
                `genres_json` VARCHAR(512) NOT NULL DEFAULT '[]',
                `fetched_at` TIMESTAMP NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                PRIMARY KEY (`artist_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $_SESSION['beats_artist_genre_table_ok'] = true;
    } catch (PDOException $e) {
        // Leave the flag unset so we retry next time.
    }
}

/**
 * Bulk-read cached genres for a list of artist names.
 * Returns map: strtolower(name) => genre[] (an empty array means "fetched, no tags" —
 * negatively cached). Names absent from the map have never been fetched.
 */
function bb_get_artist_genres_bulk(PDO $pdo, array $names): array
{
    bb_ensure_beats_artist_genre_table($pdo);
    $keys = array();
    foreach ($names as $n) {
        $k = strtolower(trim((string) $n));
        if ($k !== '') $keys[$k] = true;
    }
    $keys = array_keys($keys);
    if (empty($keys)) {
        return array();
    }
    try {
        $place = implode(',', array_fill(0, count($keys), '?'));
        $stmt = $pdo->prepare("SELECT artist_key, genres_json FROM `beats_artist_genre_cache` WHERE artist_key IN ($place)");
        $stmt->execute($keys);
        $out = array();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $g = json_decode($row['genres_json'], true);
            $out[$row['artist_key']] = is_array($g) ? $g : array();
        }
        return $out;
    } catch (PDOException $e) {
        return array();
    }
}

/** Cache one artist's genres (store [] too, as a negative cache so we don't re-query). */
function bb_save_artist_genres(PDO $pdo, string $name, array $genres): void
{
    bb_ensure_beats_artist_genre_table($pdo);
    $key = strtolower(trim($name));
    if ($key === '') {
        return;
    }
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO `beats_artist_genre_cache` (artist_key, genres_json)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE genres_json = VALUES(genres_json), fetched_at = CURRENT_TIMESTAMP"
        );
        $stmt->execute([$key, json_encode(array_values($genres))]);
    } catch (PDOException $e) {
        // ignore
    }
}
?>