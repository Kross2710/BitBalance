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

function getTotalCaloriesToday($userId)
{
    global $pdo;

    // Count total calories for the day
    $stmt = $pdo->prepare("
        SELECT SUM(calories) AS total_calories
        FROM intakeLog
        WHERE user_id = ?
        AND DATE(date_intake) = CURDATE()
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchColumn();
}

function getIntakeLogToday(int $userId)
{
    global $pdo;

    // Fetch intake log for the user, including local time (hour:minute)
    $stmt = $pdo->prepare("
        SELECT intakeLog_id, food_item, meal_category, calories, protein, carbs, fat, date_intake
        FROM intakeLog
        WHERE user_id = ?
        AND DATE(date_intake) = CURDATE()
        ORDER BY date_intake DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getMacroTotalsToday(int $userId): array
{
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(protein), 0) AS protein,
            COALESCE(SUM(carbs),   0) AS carbs,
            COALESCE(SUM(fat),     0) AS fat
        FROM intakeLog
        WHERE user_id = ?
        AND DATE(date_intake) = CURDATE()
    ");
    $stmt->execute([$userId]);
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
?>