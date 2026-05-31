<?php
require_once __DIR__ . '/../../include/init.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../../include/handlers/xp.php';

header('Content-Type: application/json');

// Parse selected date
$selectedDate = date('Y-m-d');
if (isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'])) {
    $selectedDate = $_GET['date'];
}

$selectedTime = strtotime($selectedDate);
$selectedYear = date('Y', $selectedTime);
$selectedMonth = date('m', $selectedTime);

$response = [
    'ok' => true,
    'selectedDate' => $selectedDate,
    'totalCalories' => 0,
    'userGoal' => 0,
    'progressPercentage' => 0,
    'statusClass' => 'unset',
    'welcomeSubtext' => '',
    'averageCalories' => 'N/A',
    'historyLabels' => [],
    'historyData' => [],
    'historyProtein' => [],
    'historyCarbs' => [],
    'historyFat' => [],
    'mealCategoryData' => [
        'Breakfast' => 0,
        'Lunch' => 0,
        'Dinner' => 0,
        'Snack' => 0
    ],
    'rowsHtml' => '',
    'focusTitle' => '',
    'focusTone' => 'neutral',
    'macroFocusText' => '',
    'macroFocusIcon' => 'fa-bullseye',
    'macroFocusKey' => 'neutral',
    'bmi' => 0,
    'bmiClass' => ''
];

if ($isLoggedIn) {
    $userId = $user['user_id'];
    
    // 1. Core Calorie Progress
    $totalCalories = getTotalCaloriesToday($userId, $selectedDate) ?? 0;
    $userGoal = getUserIntakeGoal($userId);
    
    $progressPercentage = 0;
    if ($userGoal > 0) {
        $progressPercentage = round(($totalCalories / $userGoal) * 100);
        $progressPercentage = min($progressPercentage, 100);
        $progressPercentage = max($progressPercentage, 0);
    }
    
    $statusClass = 'ongoing';
    if ($userGoal > 0 && $totalCalories > $userGoal) {
        $statusClass = 'overlimit';
    }

    // 2. Welcome subtext
    if ($userGoal <= 0) {
        $welcomeSubtext = t_raw('dashboard.welcome.set_goal');
    } elseif ($totalCalories > 0) {
        if ($progressPercentage >= 100) {
            $welcomeSubtext = t_raw('dashboard.welcome.achieved');
        } else {
            $welcomeSubtext = t_raw('dashboard.welcome.progress', ['pct' => (int) $progressPercentage]);
        }
    } else {
        $welcomeSubtext = t_raw('dashboard.welcome.no_intake');
    }

    // 3. Last 7 days trend relative to selected date
    $historyData = [];
    $historyProtein = [];
    $historyCarbs   = [];
    $historyFat     = [];
    $historyLabels = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days", strtotime($selectedDate)));
        $stmt = $pdo->prepare("
            SELECT
                COALESCE(SUM(calories), 0) AS total,
                COALESCE(SUM(protein),  0) AS p,
                COALESCE(SUM(carbs),    0) AS c,
                COALESCE(SUM(fat),      0) AS f
            FROM intakeLog
            WHERE user_id = ? AND DATE(date_intake) = ?
        ");
        $stmt->execute([$userId, $date]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $historyData[]    = (int)   ($row['total'] ?? 0);
        $historyProtein[] = (float) ($row['p']     ?? 0);
        $historyCarbs[]   = (float) ($row['c']     ?? 0);
        $historyFat[]     = (float) ($row['f']     ?? 0);
        $historyLabels[]  = date('D', strtotime($date));
    }
    $averageCalories = calculateCalorieAverage($historyData);
    $averageCalories = $averageCalories ? $averageCalories . ' kcal' : 'N/A';

    // 4. Meal Categories Breakdown
    $mealCategories = ['Breakfast', 'Lunch', 'Dinner', 'Snack'];
    $mealCategoryData = [];
    foreach ($mealCategories as $category) {
        $stmt = $pdo->prepare("
            SELECT SUM(calories) as total
            FROM intakeLog
            WHERE user_id = ?
            AND meal_category = ?
            AND DATE(date_intake) = ?
        ");
        $stmt->execute([$userId, $category, $selectedDate]);
        $total = $stmt->fetchColumn();
        $mealCategoryData[$category] = $total ? (int) $total : 0;
    }

    // 5. Eaten foods logs table rows (Capture HTML output buffer)
    $intakeLog = getIntakeLogToday($userId, $selectedDate);
    ob_start();
    if (!empty($intakeLog)) {
        foreach ($intakeLog as $historyEntry) {
            $entry = $historyEntry;
            $showDate = false;
            $hideActions = false;
            include PROJECT_ROOT . 'dashboard/views/_intake-row.php';
        }
    }
    $rowsHtml = ob_get_clean();

    // 6. Today's-focus data (remaining kcal + macro nudge for the "Hôm nay" widget)
    $macroTotals = getMacroTotalsToday($userId, $selectedDate);
    $macroGoals  = getMacroGoalsFromCalorieGoal($userGoal ? (int) $userGoal : null);
    $hasCalorieGoal = !empty($userGoal);
    $calorieDiff = $hasCalorieGoal ? ((int) $userGoal - (int) $totalCalories) : null;

    if (!$hasCalorieGoal) {
        $focusTitle = t_raw('dashboard.focus.title.set_goal');
        $focusTone = 'neutral';
    } elseif ($calorieDiff > 0) {
        $focusTitle = t_raw('dashboard.focus.title.left', ['n' => number_format($calorieDiff)]);
        $focusTone = 'good';
    } elseif ($calorieDiff === 0) {
        $focusTitle = t_raw('dashboard.focus.title.matched');
        $focusTone = 'good';
    } else {
        $focusTitle = t_raw('dashboard.focus.title.over', ['n' => number_format(abs($calorieDiff))]);
        $focusTone = 'alert';
    }

    $macroFocusDefs = [
        'protein' => ['label' => t_raw('dashboard.macros.protein'), 'icon' => 'fa-drumstick-bite'],
        'carbs' => ['label' => t_raw('dashboard.macros.carbs'), 'icon' => 'fa-bread-slice'],
        'fat' => ['label' => t_raw('dashboard.macros.fat'), 'icon' => 'fa-cheese'],
    ];
    $macroFocusKey = null;
    $macroFocusGap = 0;
    $macroFocusRatio = -1;
    foreach ($macroFocusDefs as $key => $def) {
        $goal = (float) ($macroGoals[$key] ?? 0);
        $current = (float) ($macroTotals[$key] ?? 0);
        $gap = max(0, $goal - $current);
        $ratio = $goal > 0 ? $gap / $goal : 0;
        if ($gap > 0 && $ratio > $macroFocusRatio) {
            $macroFocusKey = $key;
            $macroFocusGap = $gap;
            $macroFocusRatio = $ratio;
        }
    }

    if ($macroFocusKey) {
        $macroFocusIcon = $macroFocusDefs[$macroFocusKey]['icon'];
        $macroFocusText = $macroFocusDefs[$macroFocusKey]['label'] . ' +' . number_format((int) round($macroFocusGap)) . 'g';
    } elseif ($hasCalorieGoal) {
        $macroFocusIcon = 'fa-circle-check';
        $macroFocusText = t_raw('dashboard.focus.on_track');
    } else {
        $macroFocusIcon = 'fa-bullseye';
        $macroFocusText = t_raw('dashboard.focus.needs_goal');
    }

    // BMI
    $physical = getPhysicalInfo($userId);
    $userAge = $physical['age'] ?? 25;
    $userWeight = $physical['weight'] ?? 70;
    $userHeight = $physical['height'] ?? 175;

    // Fetch weight logs
    $weightLabels = [];
    $weightData = [];
    try {
        $stmt = $pdo->prepare("SELECT weight, DATE_FORMAT(date_logged, '%d/%m') as date_label FROM weight_log WHERE user_id = ? ORDER BY date_logged ASC LIMIT 7");
        $stmt->execute([$userId]);
        $weights = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($weights as $w) {
            $weightLabels[] = $w['date_label'];
            $weightData[] = $w['weight'];
        }
    } catch (PDOException $e) {}
    $currentWeight = end($weightData) ?: 0;
    $actualWeight = $currentWeight > 0 ? (float)$currentWeight : (!empty($userWeight) ? (float)$userWeight : 0);
    $actualHeight = !empty($userHeight) ? (float)$userHeight : 0;
    
    $bmi = 0;
    $bmiClass = '';
    if ($actualWeight > 0 && $actualHeight > 0) {
        $heightInMeters = $actualHeight / 100;
        $bmi = round($actualWeight / ($heightInMeters * $heightInMeters), 1);
        if ($bmi < 18.5) {
            $bmiClass = t_raw('dashboard.bmi.under');
        } elseif ($bmi < 25.0) {
            $bmiClass = t_raw('dashboard.bmi.normal');
        } elseif ($bmi < 30.0) {
            $bmiClass = t_raw('dashboard.bmi.over');
        } else {
            $bmiClass = t_raw('dashboard.bmi.obese');
        }
    }

    // Compile response
    $response = [
        'ok' => true,
        'selectedDate' => $selectedDate,
        'totalCalories' => (int) $totalCalories,
        'userGoal' => (int) $userGoal,
        'progressPercentage' => (int) $progressPercentage,
        'statusClass' => $statusClass,
        'welcomeSubtext' => $welcomeSubtext,
        'averageCalories' => $averageCalories,
        'historyLabels' => $historyLabels,
        'historyData' => $historyData,
        'historyProtein' => $historyProtein,
        'historyCarbs' => $historyCarbs,
        'historyFat' => $historyFat,
        'mealCategoryData' => [
            'Breakfast' => (int)($mealCategoryData['Breakfast'] ?? 0),
            'Lunch' => (int)($mealCategoryData['Lunch'] ?? 0),
            'Dinner' => (int)($mealCategoryData['Dinner'] ?? 0),
            'Snack' => (int)($mealCategoryData['Snack'] ?? 0)
        ],
        'rowsHtml' => $rowsHtml,
        'focusTitle' => $focusTitle,
        'focusTone' => $focusTone,
        'macroFocusText' => $macroFocusText,
        'macroFocusIcon' => $macroFocusIcon,
        'macroFocusKey' => $macroFocusKey ?? 'neutral',
        'bmi' => $bmi,
        'bmiClass' => $bmiClass
    ];
} else {
    // Guest (Demo) mock response
    $userGoal = 2200;
    
    // Create random or slightly shifted mock data based on the selected day
    $daySeed = (int) date('d', $selectedTime);
    $totalCalories = 1000 + (($daySeed * 73) % 1100);
    $progressPercentage = round(($totalCalories / $userGoal) * 100);
    
    $statusClass = $totalCalories > $userGoal ? 'overlimit' : 'ongoing';
    $welcomeSubtext = t_raw('dashboard.welcome.progress', ['pct' => (int) $progressPercentage]);
    
    // Past 7 days relative to selected date
    $historyLabels = [];
    $historyData = [];
    $historyProtein = [];
    $historyCarbs   = [];
    $historyFat     = [];
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days", $selectedTime));
        $seed = (int) date('d', strtotime($d));
        $historyLabels[] = date('D', strtotime($d));
        $historyData[] = 1100 + (($seed * 59) % 1000);
        $historyProtein[] = 70 + (($seed * 3) % 80);
        $historyCarbs[] = 150 + (($seed * 7) % 150);
        $historyFat[] = 40 + (($seed * 2) % 40);
    }
    $averageCalories = calculateCalorieAverage($historyData);
    $averageCalories = $averageCalories ? $averageCalories . ' kcal' : 'N/A';

    $mealCategoryData = [
        'Breakfast' => round($totalCalories * 0.3),
        'Lunch' => round($totalCalories * 0.4),
        'Dinner' => round($totalCalories * 0.2),
        'Snack' => round($totalCalories * 0.1)
    ];

    // Guest Mock Logs list
    $intakeLog = [
        ['intakeLog_id' => 0, 'food_item' => 'Mock Food A', 'calories' => round($totalCalories * 0.5), 'protein' => 20, 'carbs' => 45, 'fat' => 12, 'meal_category' => 'lunch', 'date_intake' => date('Y-m-d 12:30:00', $selectedTime)],
        ['intakeLog_id' => 0, 'food_item' => 'Mock Food B', 'calories' => round($totalCalories * 0.5), 'protein' => 15, 'carbs' => 38, 'fat' => 8, 'meal_category' => 'dinner', 'date_intake' => date('Y-m-d 19:15:00', $selectedTime)]
    ];
    
    ob_start();
    foreach ($intakeLog as $historyEntry) {
        $entry = $historyEntry;
        $showDate = false;
        $hideActions = true; // locks row actions for demo guest
        include PROJECT_ROOT . 'dashboard/views/_intake-row.php';
    }
    $rowsHtml = ob_get_clean();

    $response = [
        'ok' => true,
        'selectedDate' => $selectedDate,
        'totalCalories' => (int) $totalCalories,
        'userGoal' => (int) $userGoal,
        'progressPercentage' => (int) $progressPercentage,
        'statusClass' => $statusClass,
        'welcomeSubtext' => $welcomeSubtext,
        'averageCalories' => $averageCalories,
        'historyLabels' => $historyLabels,
        'historyData' => $historyData,
        'historyProtein' => $historyProtein,
        'historyCarbs' => $historyCarbs,
        'historyFat' => $historyFat,
        'mealCategoryData' => $mealCategoryData,
        'rowsHtml' => $rowsHtml,
        'focusTitle' => t_raw('dashboard.focus.title.left', ['n' => number_format(max(0, $userGoal - $totalCalories))]),
        'focusTone' => 'good',
        'macroFocusText' => 'Protein +35g',
        'macroFocusIcon' => 'fa-drumstick-bite',
        'macroFocusKey' => 'protein',
        'bmi' => 22.9,
        'bmiClass' => t_raw('dashboard.bmi.normal')
    ];
}

// Re-rendered calendar navbar HTML, so month-change / picker navigation can
// swap the strip in place without a full page reload.
ob_start();
include PROJECT_ROOT . 'dashboard/views/_calendar-navbar.php';
$response['calendarHtml'] = ob_get_clean();

echo json_encode($response);
exit;
