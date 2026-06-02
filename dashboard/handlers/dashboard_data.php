<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../../include/handlers/xp.php';

// Resolve selected date (default to today)
$selectedDate = date('Y-m-d');
if (isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'])) {
    $selectedDate = $_GET['date'];
}
// Rule: never operate on a future day — clamp forward dates back to today.
// Applies to every page that derives data from $selectedDate (Overview, Intake).
if ($selectedDate > date('Y-m-d')) {
    $selectedDate = date('Y-m-d');
}

// Prepare data for the history chart (last 7 days relative to selected date)
$historyData = [];
$historyLabels = [];

// Fetch Intake Log for the logged-in user
if ($isLoggedIn) {
    require_once PROJECT_ROOT . '/include/db_config.php'; // Include database configuration
    $userId = $user['user_id']; // Get user ID from session

    $totalCalories = getTotalCaloriesToday($userId, $selectedDate) ?? 0; // Get total calories for selected date, default to 0 if null

    $intakeLog = getIntakeLogToday($userId, $selectedDate); // Get selected date's intake log

    $userGoal = getUserIntakeGoal($userId); // Get user's calorie goal
    // Fallback khi user chưa có dòng trong userStatus (fetch trả về false)
    $userStreak = getUserLoggingStreak($userId) ?: ['logging_streak' => 0, 'longest_logging_streak' => 0, 'streak_freezes' => 0, 'broken_streak' => 0];

    // XP: lazy-finalize yesterday's goal-hit + re-check streak milestones,
    // then load the summary the header bar renders. Wrapped — XP must never
    // break the dashboard.
    try {
        xp_finalize_yesterday_goals($pdo, $userId);
        xp_award_streak_milestone($pdo, $userId, (int) ($userStreak['logging_streak'] ?? 0));
        $xpSummary = xp_get_summary($pdo, $userId);
    } catch (Throwable $e) {
        error_log('xp dashboard_data: ' . $e->getMessage());
        $xpSummary = ['total_xp' => 0, 'current_level' => 1, 'xp_into_level' => 0, 'xp_for_next' => 100, 'progress_pct' => 0];
    }

    // Macro totals for selected date and recommended macro goals derived from calorie goal
    $macroTotals = getMacroTotalsToday($userId, $selectedDate);
    $macroGoals  = resolveMacroGoals($userId); // explicit PT macros if set, else derived

    $progressPercentage = 0; // Default progress percentage
    if ($userGoal) {
        $progressPercentage = round(($totalCalories / $userGoal) * 100, 2);
        $progressPercentage = min($progressPercentage, 100); // Cap at 100%
        $progressPercentage = max($progressPercentage, 0); // Ensure it's not negative
    }

    // Get the past 7 days leading to the selected date (calories + macros)
    $historyProtein = [];
    $historyCarbs   = [];
    $historyFat     = [];
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
        // For label, show 'Mon', 'Tue', etc.
        $historyLabels[] = date('D', strtotime($date));
    }

    // Prepare data for the meal categories chart for the selected date
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
        $mealCategoryData[$category] = $total ? (int) $total : 0; // Default to 0 if no records found
    }
} else {
    $intakeLog = []; // Empty array if not logged in
    $totalCalories = 0; // Default to 0 if not logged in
    $macroTotals = ['protein' => 0, 'carbs' => 0, 'fat' => 0];
    $macroGoals  = ['protein' => 0, 'carbs' => 0, 'fat' => 0];
    $historyProtein = [0, 0, 0, 0, 0, 0, 0];
    $historyCarbs   = [0, 0, 0, 0, 0, 0, 0];
    $historyFat     = [0, 0, 0, 0, 0, 0, 0];
}

// Calculator.php partial handler
// Automatically fill in user age, weight, and height if available
$userAge = '';
$userGender = '';
$userWeight = '';
$userHeight = '';

// If user is logged in, fetch their physical info
if ($isLoggedIn) {
    $userId = $user['user_id'];
    try {
        $physical = getPhysicalInfo($userId); // Fetch physical info from the database

        if ($physical) {
            $userAge = $physical['age'];
            $userGender = $physical['gender'];
            $userWeight = $physical['weight'];
            $userHeight = $physical['height'];
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }
}

$calculatorResult = null;
if (isset($_SESSION['calculator_result'])) {
    $calculatorResult = $_SESSION['calculator_result'];
    // Optionally clear it so it's not shown on page reload:
    // unset($_SESSION['calculator_result']);
}

// If user select activity level
if ($calculatorResult) {
    $selectedActivity = $calculatorResult['activity_level']; // already stored in session from handler
} else {
    $selectedActivity = '';
}

// Fetch Weight History (Lấy 7 lần cân gần nhất)
$weightLabels = [];
$weightData = [];

if ($isLoggedIn) {
    try {
        $stmt = $pdo->prepare("SELECT weight, DATE_FORMAT(date_logged, '%d/%m') as date_label FROM weight_log WHERE user_id = ? ORDER BY date_logged ASC LIMIT 7");
        $stmt->execute([$user['user_id']]);
        $weights = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($weights as $w) {
            $weightLabels[] = $w['date_label'];
            $weightData[] = $w['weight'];
        }
    } catch (PDOException $e) {
        // Handle error
    }

    // Lấy cân nặng hiện tại (mới nhất)
    $currentWeight = end($weightData) ?: 0;
    // Lấy cân nặng khởi điểm (đầu tiên trong mảng 7 ngày)
    $startWeight = reset($weightData) ?: 0;
    $weightDiff = $currentWeight - $startWeight;
    $weightTrend = ($weightDiff > 0) ? 'up' : (($weightDiff < 0) ? 'down' : 'flat');
} else {
    $currentWeight = 0;
    $startWeight = 0;
    $weightDiff = 0;
    $weightTrend = 'flat';
}
?>