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
    $stmt = $pdo->prepare("
        SELECT intakeLog_id, food_item, meal_category, calories, protein, carbs, fat, date_intake
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
    $stmt = $pdo->prepare("
        SELECT intakeLog_id, food_item, meal_category, calories, protein, carbs, fat, date_intake
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
                (user_id, track_name, artist_name, food_item, calories, archetype,
                 detected_vibe, match_score, energy_sync, comfort, chaos, verdict, rarity)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)"
        );
        $stmt->execute([
            $userId,
            (string) ($r['track_name'] ?? ''),
            (string) ($r['artist_name'] ?? ''),
            (string) ($r['food_item'] ?? ''),
            (int) ($r['calories'] ?? 0),
            (string) ($r['archetype'] ?? ''),
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
?>