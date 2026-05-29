<?php
require_once __DIR__ . '/../_bootstrap.php';
require_once PROJECT_ROOT . 'dashboard/handlers/functions.php';
require_once PROJECT_ROOT . 'include/handlers/xp.php';

api_require_method('GET');

if (!isset($_SESSION['user']) || !isset($_SESSION['user']['user_id'])) {
    api_error('Authentication required.', 401);
}

$pdo = api_connect_db();
$user = api_require_auth($pdo);
$userId = (int) $user['user_id'];

try {
    $totalCalories = (int) (getTotalCaloriesToday($userId) ?: 0);
    $calorieGoal = getUserIntakeGoal($userId);
    $calorieGoal = $calorieGoal ? (int) $calorieGoal : null;
    $macroTotals = getMacroTotalsToday($userId);
    $macroGoals = getMacroGoalsFromCalorieGoal($calorieGoal);
    $userStreak = getUserLoggingStreak($userId);

    if (!$userStreak) {
        $userStreak = [
            'logging_streak' => 0,
            'longest_logging_streak' => 0,
            'streak_freezes' => 0,
            'broken_streak' => 0
        ];
    }

    try {
        xp_finalize_yesterday_goals($pdo, $userId);
        xp_award_streak_milestone($pdo, $userId, (int) $userStreak['logging_streak']);
        $xpSummary = xp_get_summary($pdo, $userId);
    } catch (Throwable $e) {
        error_log('API dashboard XP error: ' . $e->getMessage());
        $xpSummary = [
            'total_xp' => 0,
            'current_level' => 1,
            'xp_into_level' => 0,
            'xp_for_next' => 100,
            'progress_pct' => 0
        ];
    }

    $progressPercentage = 0;
    if ($calorieGoal && $calorieGoal > 0) {
        $progressPercentage = round(($totalCalories / $calorieGoal) * 100, 2);
        $progressPercentage = min($progressPercentage, 100);
        $progressPercentage = max($progressPercentage, 0);
    }

    $history = [
        'labels' => [],
        'calories' => [],
        'protein' => [],
        'carbs' => [],
        'fat' => []
    ];

    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $stmt = $pdo->prepare("
            SELECT
                COALESCE(SUM(calories), 0) AS total,
                COALESCE(SUM(protein), 0) AS protein,
                COALESCE(SUM(carbs), 0) AS carbs,
                COALESCE(SUM(fat), 0) AS fat
            FROM intakeLog
            WHERE user_id = ? AND DATE(date_intake) = ?
        ");
        $stmt->execute([$userId, $date]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $history['labels'][] = date('D', strtotime($date));
        $history['calories'][] = (int) ($row ? $row['total'] : 0);
        $history['protein'][] = (float) ($row ? $row['protein'] : 0);
        $history['carbs'][] = (float) ($row ? $row['carbs'] : 0);
        $history['fat'][] = (float) ($row ? $row['fat'] : 0);
    }

    $mealCategories = ['Breakfast', 'Lunch', 'Dinner', 'Snack'];
    $mealCategoryData = [];
    foreach ($mealCategories as $category) {
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(calories), 0) AS total
            FROM intakeLog
            WHERE user_id = ?
            AND meal_category = ?
            AND DATE(date_intake) = CURDATE()
        ");
        $stmt->execute([$userId, $category]);
        $mealCategoryData[$category] = (int) $stmt->fetchColumn();
    }

    api_send(true, [
        'total_calories' => $totalCalories,
        'calorie_goal' => $calorieGoal,
        'progress_percentage' => $progressPercentage,
        'protein' => (float) $macroTotals['protein'],
        'carbs' => (float) $macroTotals['carbs'],
        'fat' => (float) $macroTotals['fat'],
        'macro_goals' => $macroGoals,
        'current_level' => (int) $xpSummary['current_level'],
        'total_xp' => (int) $xpSummary['total_xp'],
        'xp_into_level' => (int) $xpSummary['xp_into_level'],
        'xp_for_next' => (int) $xpSummary['xp_for_next'],
        'xp_progress_percentage' => (int) $xpSummary['progress_pct'],
        'streak' => [
            'current' => (int) $userStreak['logging_streak'],
            'longest' => (int) $userStreak['longest_logging_streak'],
            'freezes' => (int) $userStreak['streak_freezes'],
            'broken' => (int) $userStreak['broken_streak']
        ],
        'history' => $history,
        'meal_categories' => $mealCategoryData
    ], null);
} catch (Throwable $e) {
    error_log('API dashboard summary error: ' . $e->getMessage());
    api_error('Unable to load dashboard summary.', 500);
}
