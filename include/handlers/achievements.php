<?php
/**
 * BitBalance achievements - read-only progress computed from existing data.
 *
 * No persistence yet: achievements are derived from intakeLog, userStatus,
 * user_xp, xp_event, and friend_request so the first release does not need
 * another migration.
 */

require_once __DIR__ . '/xp.php';
require_once __DIR__ . '/friends.php';

function bb_achievement_scalar(PDO $pdo, string $sql, array $params = []): int
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) ($stmt->fetchColumn() ?: 0);
}

function bb_achievement_row(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

function bb_achievement_food_count(PDO $pdo, int $userId, array $terms): int
{
    if (empty($terms)) return 0;

    $clauses = [];
    $params = [$userId];
    foreach ($terms as $term) {
        $clauses[] = 'food_item LIKE ?';
        $params[] = '%' . $term . '%';
    }

    return bb_achievement_scalar(
        $pdo,
        'SELECT COUNT(*) FROM intakeLog WHERE user_id = ? AND (' . implode(' OR ', $clauses) . ')',
        $params
    );
}

function bb_achievement_friend_count(PDO $pdo, int $userId): int
{
    return bb_achievement_scalar(
        $pdo,
        "SELECT COUNT(*) FROM friend_request
         WHERE status = 'accepted'
           AND (requester_id = ? OR addressee_id = ?)",
        [$userId, $userId]
    );
}

function bb_achievement_latest_goal(PDO $pdo, int $userId): int
{
    return bb_achievement_scalar(
        $pdo,
        'SELECT calorie_goal FROM userGoal WHERE user_id = ? ORDER BY date_set DESC LIMIT 1',
        [$userId]
    );
}

function bb_achievement_balanced_days(PDO $pdo, int $userId, int $goal): int
{
    if ($goal <= 0) return 0;

    return bb_achievement_scalar(
        $pdo,
        "SELECT COUNT(*) FROM (
            SELECT DATE(date_intake) AS d, SUM(calories) AS total_calories
            FROM intakeLog
            WHERE user_id = ?
            GROUP BY DATE(date_intake)
            HAVING total_calories BETWEEN ? AND ?
         ) balanced_days",
        [$userId, (int) floor($goal * 0.90), (int) ceil($goal * 1.10)]
    );
}

function bb_achievement_comeback_count(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare(
        "SELECT DISTINCT DATE(date_intake) AS d
         FROM intakeLog
         WHERE user_id = ?
         ORDER BY d ASC"
    );
    $stmt->execute([$userId]);
    $dates = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

    $count = 0;
    $prev = null;
    foreach ($dates as $date) {
        if ($prev !== null) {
            $gap = (int) floor((strtotime($date) - strtotime($prev)) / 86400);
            if ($gap >= 3) $count++;
        }
        $prev = $date;
    }

    return $count;
}

function bb_achievement_full_plate_days(PDO $pdo, int $userId): int
{
    return bb_achievement_scalar(
        $pdo,
        "SELECT COUNT(*) FROM (
            SELECT DATE(date_intake) AS d
            FROM intakeLog
            WHERE user_id = ?
              AND meal_category IN ('breakfast', 'lunch', 'dinner')
            GROUP BY DATE(date_intake)
            HAVING COUNT(DISTINCT meal_category) = 3
         ) full_plate_days",
        [$userId]
    );
}

function bb_achievement_level(array $thresholds, int $value): array
{
    $level = 0;
    foreach ($thresholds as $threshold) {
        if ($value >= $threshold) $level++;
    }

    $maxLevel = count($thresholds);
    $isComplete = $level >= $maxLevel;
    $nextTarget = $isComplete ? $thresholds[$maxLevel - 1] : $thresholds[$level];
    $prevTarget = $level > 0 ? $thresholds[$level - 1] : 0;
    $range = max(1, $nextTarget - $prevTarget);
    $pct = $isComplete ? 100 : (int) min(100, max(0, round(($value - $prevTarget) / $range * 100)));

    return [
        'level' => $level,
        'max_level' => $maxLevel,
        'next_target' => $nextTarget,
        'progress_pct' => $pct,
        'is_complete' => $isComplete,
    ];
}

function bb_achievement_build(
    string $id,
    string $name,
    string $description,
    string $icon,
    string $tone,
    int $value,
    string $unit,
    array $thresholds
): array {
    $level = bb_achievement_level($thresholds, $value);
    return array_merge($level, [
        'id' => $id,
        'name' => $name,
        'description' => $description,
        'icon' => $icon,
        'tone' => $tone,
        'value' => $value,
        'unit' => $unit,
        'thresholds' => $thresholds,
    ]);
}

function bb_achievements_progress(PDO $pdo, int $userId): array
{
    $xp = xp_get_summary($pdo, $userId);
    $goal = bb_achievement_latest_goal($pdo, $userId);

    $status = bb_achievement_row(
        $pdo,
        'SELECT logging_streak, longest_logging_streak FROM userStatus WHERE user_id = ? LIMIT 1',
        [$userId]
    );

    $dailyLogger = bb_achievement_scalar(
        $pdo,
        'SELECT COUNT(DISTINCT DATE(date_intake)) FROM intakeLog WHERE user_id = ?',
        [$userId]
    );
    $totalFoods = bb_achievement_scalar($pdo, 'SELECT COUNT(*) FROM intakeLog WHERE user_id = ?', [$userId]);
    $fullPlateDays = bb_achievement_full_plate_days($pdo, $userId);
    $balancedDays = bb_achievement_balanced_days($pdo, $userId, $goal);
    $friends = bb_achievement_friend_count($pdo, $userId);
    $comebacks = bb_achievement_comeback_count($pdo, $userId);

    $riceCount = bb_achievement_food_count($pdo, $userId, ['rice', 'cơm', 'com tam', 'com ga', 'com suon', 'com rang']);
    $phoCount = bb_achievement_food_count($pdo, $userId, ['pho', 'phở']);
    $banhMiCount = bb_achievement_food_count($pdo, $userId, ['banh mi', 'ban mi', 'bánh mì', 'bánh mỳ', 'banh my']);

    $weeklyRankOne = 0;
    if ($friends > 0) {
        $weeklyLeaders = leaderboard_friends($pdo, $userId, 'weekly', 1);
        $weeklyRankOne = !empty($weeklyLeaders[0]) && (int) $weeklyLeaders[0]['user_id'] === $userId ? 1 : 0;
    }

    $achievements = [
        bb_achievement_build('first_bite', 'First Bite', 'Log your first food. The fork has entered the chat.', 'fa-utensils', 'primary', $totalFoods, 'food logged', [1]),
        bb_achievement_build('daily_logger', 'Daily Logger', 'Log food on different days.', 'fa-calendar-check', 'secondary', $dailyLogger, 'logged days', [1, 3, 7, 14, 30]),
        bb_achievement_build('streak_cooker', 'Streak Cooker', 'Keep your logging streak hot.', 'fa-fire', 'accent', (int) ($status['longest_logging_streak'] ?? 0), 'best streak days', [3, 7, 14, 30, 60, 100]),
        bb_achievement_build('full_plate', 'Full Plate', 'Log breakfast, lunch, and dinner in the same day.', 'fa-clipboard-check', 'success', $fullPlateDays, 'full days', [1, 5, 15, 30]),
        bb_achievement_build('balanced_bowl', 'Balanced Bowl', 'Finish a day within 10% of your calorie goal.', 'fa-bullseye', 'primary', $balancedDays, 'balanced days', [1, 7, 30]),
        bb_achievement_build('xp_grinder', 'XP Grinder', 'Earn XP across BitBalance.', 'fa-bolt', 'warning', (int) $xp['total_xp'], 'total XP', [100, 500, 1000, 5000, 10000, 30000]),
        bb_achievement_build('rice_goddess', 'Rice Goddess', 'Log rice dishes until the bowl starts recognizing you.', 'fa-bowl-rice', 'warning', $riceCount, 'rice logs', [5, 20, 50, 100]),
        bb_achievement_build('pho_real', 'Pho Real', 'Log pho enough times that the broth becomes a personality trait.', 'fa-bowl-food', 'secondary', $phoCount, 'pho logs', [3, 10, 25]),
        bb_achievement_build('banh_mi_baron', 'Banh Mi Baron', 'Log banh mi in any spelling. Diacritics are optional; devotion is not.', 'fa-bread-slice', 'accent', $banhMiCount, 'banh mi logs', [3, 10, 25, 50, 100]),
        bb_achievement_build('friend_fuel', 'Friend Fuel', 'Build a crew for accountability and leaderboard chaos.', 'fa-user-friends', 'secondary', $friends, 'friends', [1, 3, 10]),
        bb_achievement_build('leaderboard_menace', 'Leaderboard Menace', 'Hold rank 1 on your current weekly friend leaderboard.', 'fa-trophy', 'warning', $weeklyRankOne, 'rank 1 status', [1]),
        bb_achievement_build('comeback_meal', 'Comeback Meal', 'Return after a 2+ day logging gap. No drama, just dinner.', 'fa-rotate-left', 'success', $comebacks, 'comebacks', [1, 3, 10]),
    ];

    $unlocked = 0;
    foreach ($achievements as $achievement) {
        if ($achievement['level'] > 0) $unlocked++;
    }

    $favorite = bb_achievement_row(
        $pdo,
        "SELECT food_item, COUNT(*) AS c
         FROM intakeLog
         WHERE user_id = ?
         GROUP BY food_item
         ORDER BY c DESC, food_item ASC
         LIMIT 1",
        [$userId]
    );

    $mostFoodsDay = bb_achievement_scalar(
        $pdo,
        "SELECT COALESCE(MAX(day_count), 0) FROM (
            SELECT COUNT(*) AS day_count
            FROM intakeLog
            WHERE user_id = ?
            GROUP BY DATE(date_intake)
         ) food_days",
        [$userId]
    );

    $mostXpDay = bb_achievement_scalar(
        $pdo,
        "SELECT COALESCE(MAX(day_xp), 0) FROM (
            SELECT SUM(amount) AS day_xp
            FROM xp_event
            WHERE user_id = ?
            GROUP BY DATE(created_at)
         ) xp_days",
        [$userId]
    );

    return [
        'summary' => [
            'xp' => $xp,
            'unlocked' => $unlocked,
            'total_achievements' => count($achievements),
            'current_streak' => (int) ($status['logging_streak'] ?? 0),
            'longest_streak' => (int) ($status['longest_logging_streak'] ?? 0),
            'total_foods' => $totalFoods,
            'logged_days' => $dailyLogger,
            'goal' => $goal,
        ],
        'records' => [
            ['label' => 'Longest streak', 'value' => (int) ($status['longest_logging_streak'] ?? 0), 'unit' => 'days', 'icon' => 'fa-fire'],
            ['label' => 'Most XP in a day', 'value' => $mostXpDay, 'unit' => 'XP', 'icon' => 'fa-bolt'],
            ['label' => 'Most foods in a day', 'value' => $mostFoodsDay, 'unit' => 'foods', 'icon' => 'fa-utensils'],
            ['label' => 'Favorite food', 'value' => $favorite['food_item'] ?? 'Not enough data', 'unit' => !empty($favorite['c']) ? ((int) $favorite['c']) . ' logs' : '', 'icon' => 'fa-star'],
        ],
        'achievements' => $achievements,
    ];
}
