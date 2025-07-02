<?php
require_once __DIR__ . '/../../include/handlers/log_attempt.php';
function updateLoggingStreak(PDO $pdo, int $userId): void
{
    // Fetch current status row (lock it FOR UPDATE to prevent race conditions)
    $stmt = $pdo->prepare("
        SELECT last_logging_date, logging_streak, longest_logging_streak 
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

    /* --- decide new streak value --- */
    if ($lastLogging && $lastLogging->format('Y-m-d') === $today->format('Y-m-d')) {
        // Already logged today → nothing to do
        return;
    }

    if ($lastLogging && $lastLogging->format('Y-m-d') === $yesterday->format('Y-m-d')) {
        $streak++;  // consecutive day
        log_attempt($pdo, $userId, 'streak_update', "Streak incremented to $streak");
    } else {
        $streak = 1;  // reset / first login
        log_attempt($pdo, $userId, 'streak_reset', "Streak reset to 1");
    }

    // update longest streak if needed
    if ($streak > $longest) {
        $longest = $streak;
    }

    // Persist changes
    $upd = $pdo->prepare("
        UPDATE userStatus 
        SET last_logging_date = ?, logging_streak = ?, longest_logging_streak = ?
        WHERE user_id = ?
    ");
    $upd->execute([$now->format('Y-m-d H:i:s'), $streak, $longest, $userId]);
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
        SELECT intakeLog_id, food_item, meal_category, calories, date_intake
        FROM intakeLog
        WHERE user_id = ?
        AND DATE(date_intake) = CURDATE()
        ORDER BY date_intake DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        SELECT food_item, meal_category, calories, date_intake
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

    // Fetch the user's logging streak
    $stmt = $pdo->prepare("
        SELECT logging_streak, longest_logging_streak
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