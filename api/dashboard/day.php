<?php
require_once __DIR__ . '/../_bootstrap.php';
require_once PROJECT_ROOT . 'dashboard/handlers/functions.php';
require_once PROJECT_ROOT . 'include/handlers/xp.php';

api_require_method('GET');

$pdo = api_connect_db();
$user = api_require_auth($pdo);
$userId = (int) $user['user_id'];

$selectedDate = date('Y-m-d');
if (isset($_GET['date']) && $_GET['date'] !== '') {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'])) {
        api_error('Invalid date format. Use YYYY-MM-DD.', 422);
    }
    $selectedDate = $_GET['date'];
}

$selectedTime = strtotime($selectedDate);
if ($selectedTime === false || date('Y-m-d', $selectedTime) !== $selectedDate) {
    api_error('Invalid date.', 422);
}

if ($selectedDate > date('Y-m-d')) {
    api_error('Future dashboard dates are not available.', 422);
}

function api_dashboard_intake_entry(array $row)
{
    $dateIntake = isset($row['date_intake']) ? (string) $row['date_intake'] : null;
    $isoDate = null;
    if ($dateIntake) {
        $timestamp = strtotime($dateIntake);
        if ($timestamp !== false) {
            $isoDate = date('c', $timestamp);
        }
    }

    return [
        'id' => (int) ($row['intakeLog_id'] ?? $row['id'] ?? 0),
        'food_item' => isset($row['food_item']) ? (string) $row['food_item'] : '',
        'calories' => (int) ($row['calories'] ?? 0),
        'protein' => (float) ($row['protein'] ?? 0),
        'carbs' => (float) ($row['carbs'] ?? 0),
        'fat' => (float) ($row['fat'] ?? 0),
        'meal_category' => isset($row['meal_category']) ? (string) $row['meal_category'] : '',
        'date_intake' => $dateIntake,
        'iso_date' => $isoDate
    ];
}

function api_dashboard_bmi_category($bmi)
{
    if ($bmi <= 0) {
        return null;
    }
    if ($bmi < 18.5) {
        return 'Underweight';
    }
    if ($bmi < 25.0) {
        return 'Normal';
    }
    if ($bmi < 30.0) {
        return 'Overweight';
    }
    return 'Obese';
}

function api_dashboard_macro_focus(array $macroTotals, array $macroGoals, $hasCalorieGoal)
{
    $defs = [
        'protein' => ['label' => 'Protein', 'icon' => 'drumstick'],
        'carbs' => ['label' => 'Carbs', 'icon' => 'bread-slice'],
        'fat' => ['label' => 'Fat', 'icon' => 'cheese']
    ];

    $focusKey = null;
    $focusGap = 0.0;
    $focusRatio = -1.0;

    foreach ($defs as $key => $def) {
        $goal = (float) ($macroGoals[$key] ?? 0);
        $current = (float) ($macroTotals[$key] ?? 0);
        $gap = max(0, $goal - $current);
        $ratio = $goal > 0 ? $gap / $goal : 0;
        if ($gap > 0 && $ratio > $focusRatio) {
            $focusKey = $key;
            $focusGap = $gap;
            $focusRatio = $ratio;
        }
    }

    if ($focusKey !== null) {
        return [
            'key' => $focusKey,
            'label' => $defs[$focusKey]['label'],
            'gap' => round($focusGap, 1),
            'icon' => $defs[$focusKey]['icon']
        ];
    }

    if ($hasCalorieGoal) {
        return [
            'key' => 'complete',
            'label' => 'On track',
            'gap' => 0,
            'icon' => 'checkmark-circle'
        ];
    }

    return [
        'key' => 'neutral',
        'label' => 'Set a goal',
        'gap' => 0,
        'icon' => 'target'
    ];
}

try {
    $totalCalories = (int) (getTotalCaloriesToday($userId, $selectedDate) ?: 0);
    $calorieGoal = getUserIntakeGoal($userId);
    $calorieGoal = $calorieGoal ? (int) $calorieGoal : null;
    $hasCalorieGoal = $calorieGoal !== null && $calorieGoal > 0;

    $macroTotals = getMacroTotalsToday($userId, $selectedDate);
    $macroGoals = getMacroGoalsFromCalorieGoal($calorieGoal);

    $progressPercentage = 0;
    if ($hasCalorieGoal) {
        $progressPercentage = round(($totalCalories / $calorieGoal) * 100, 2);
        $progressPercentage = min($progressPercentage, 100);
        $progressPercentage = max($progressPercentage, 0);
    }

    $statusClass = 'unset';
    if ($hasCalorieGoal) {
        $statusClass = $totalCalories > $calorieGoal ? 'overlimit' : 'ongoing';
    }

    $history = [
        'labels' => [],
        'calories' => [],
        'protein' => [],
        'carbs' => [],
        'fat' => []
    ];

    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days", $selectedTime));
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
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $history['labels'][] = date('D', strtotime($date));
        $history['calories'][] = (int) ($row['total'] ?? 0);
        $history['protein'][] = (float) ($row['protein'] ?? 0);
        $history['carbs'][] = (float) ($row['carbs'] ?? 0);
        $history['fat'][] = (float) ($row['fat'] ?? 0);
    }

    $mealCategories = ['Breakfast', 'Lunch', 'Dinner', 'Snack'];
    $mealCategoryData = [];
    foreach ($mealCategories as $category) {
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(calories), 0) AS total
            FROM intakeLog
            WHERE user_id = ?
              AND meal_category = ?
              AND DATE(date_intake) = ?
        ");
        $stmt->execute([$userId, $category, $selectedDate]);
        $mealCategoryData[strtolower($category)] = (int) $stmt->fetchColumn();
    }

    $entries = [];
    $intakeLog = getIntakeLogToday($userId, $selectedDate);
    foreach ($intakeLog as $entry) {
        $entries[] = api_dashboard_intake_entry($entry);
    }

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
        error_log('API dashboard day XP error: ' . $e->getMessage());
        $xpSummary = [
            'total_xp' => 0,
            'current_level' => 1,
            'xp_into_level' => 0,
            'xp_for_next' => 100,
            'progress_pct' => 0
        ];
    }

    $physical = getPhysicalInfo($userId);
    if (!$physical) {
        $physical = ['age' => null, 'gender' => null, 'weight' => null, 'height' => null];
    }

    $weightHistory = [];
    try {
        $stmt = $pdo->prepare("
            SELECT weight_id, weight, date_logged
            FROM weight_log
            WHERE user_id = ?
            ORDER BY date_logged DESC, weight_id DESC
            LIMIT 7
        ");
        $stmt->execute([$userId]);
        $rows = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
        foreach ($rows as $row) {
            $dateLogged = (string) ($row['date_logged'] ?? '');
            $weightHistory[] = [
                'id' => (int) ($row['weight_id'] ?? 0),
                'weight' => (float) ($row['weight'] ?? 0),
                'date_logged' => $dateLogged,
                'label' => $dateLogged ? date('d/m', strtotime($dateLogged)) : ''
            ];
        }
    } catch (PDOException $e) {
        $weightHistory = [];
    }

    $latestWeight = 0;
    if (!empty($weightHistory)) {
        $lastWeightPoint = $weightHistory[count($weightHistory) - 1];
        $latestWeight = (float) $lastWeightPoint['weight'];
    }

    $actualWeight = $latestWeight > 0 ? $latestWeight : (float) ($physical['weight'] ?? 0);
    $actualHeight = (float) ($physical['height'] ?? 0);
    $bmi = null;
    if ($actualWeight > 0 && $actualHeight > 0) {
        $heightInMeters = $actualHeight / 100;
        $bmi = round($actualWeight / ($heightInMeters * $heightInMeters), 1);
    }

    $calorieRemaining = null;
    $calorieOverBy = null;
    $focusTone = 'neutral';
    $focusStatus = 'setup';
    if ($hasCalorieGoal) {
        $diff = $calorieGoal - $totalCalories;
        if ($diff >= 0) {
            $calorieRemaining = $diff;
            $focusTone = 'good';
            $focusStatus = 'active';
        } else {
            $calorieOverBy = abs($diff);
            $focusTone = 'alert';
            $focusStatus = 'adjust';
        }
    }

    api_send(true, [
        'selected_date' => $selectedDate,
        'total_calories' => $totalCalories,
        'calorie_goal' => $calorieGoal,
        'progress_percentage' => $progressPercentage,
        'status_class' => $statusClass,
        'macros' => [
            'protein' => (float) ($macroTotals['protein'] ?? 0),
            'carbs' => (float) ($macroTotals['carbs'] ?? 0),
            'fat' => (float) ($macroTotals['fat'] ?? 0)
        ],
        'macro_goals' => [
            'protein' => (float) ($macroGoals['protein'] ?? 0),
            'carbs' => (float) ($macroGoals['carbs'] ?? 0),
            'fat' => (float) ($macroGoals['fat'] ?? 0)
        ],
        'history' => $history,
        'average_calories' => calculateCalorieAverage($history['calories']) ?: null,
        'meal_categories' => $mealCategoryData,
        'entries' => $entries,
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
        'focus' => [
            'tone' => $focusTone,
            'status' => $focusStatus,
            'calorie_remaining' => $calorieRemaining,
            'calorie_over_by' => $calorieOverBy,
            'macro_focus' => api_dashboard_macro_focus($macroTotals, $macroGoals, $hasCalorieGoal)
        ],
        'bmi' => [
            'value' => $bmi,
            'category' => $bmi !== null ? api_dashboard_bmi_category($bmi) : null
        ],
        'physical' => [
            'age' => isset($physical['age']) ? ($physical['age'] !== null ? (int) $physical['age'] : null) : null,
            'gender' => isset($physical['gender']) ? $physical['gender'] : null,
            'weight' => isset($physical['weight']) ? ($physical['weight'] !== null ? (float) $physical['weight'] : null) : null,
            'height' => isset($physical['height']) ? ($physical['height'] !== null ? (float) $physical['height'] : null) : null
        ],
        'weight_history' => $weightHistory
    ], null);
} catch (Throwable $e) {
    error_log('API dashboard day error: ' . $e->getMessage());
    api_error('Unable to load dashboard day.', 500);
}
