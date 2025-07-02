<?php
require_once __DIR__ . '/functions.php';

// Prepare data for the history chart (last 7 days)
$historyData = [];
$historyLabels = [];

// Fetch Intake Log for the logged-in user
if ($isLoggedIn) {
    require_once PROJECT_ROOT . '/include/db_config.php'; // Include database configuration
    $userId = $user['user_id']; // Get user ID from session

    $totalCalories = getTotalCaloriesToday($userId) ?? 0; // Get total calories for today, default to 0 if null

    $intakeLog = getIntakeLogToday($userId); // Get today's intake log

    $userGoal = getUserIntakeGoal($userId); // Get user's calorie goal
    $userStreak = getUserLoggingStreak($userId); // Get user's logging streak

    $progressPercentage = 0; // Default progress percentage
    if ($userGoal) {
        $progressPercentage = round(($totalCalories / $userGoal) * 100, 2);
        $progressPercentage = min($progressPercentage, 100); // Cap at 100%
        $progressPercentage = max($progressPercentage, 0); // Ensure it's not negative
    }

    // Get the past 7 days
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $stmt = $pdo->prepare("SELECT SUM(calories) as total FROM intakeLog WHERE user_id = ? AND DATE(date_intake) = ?");
        $stmt->execute([$userId, $date]);
        $total = $stmt->fetchColumn();
        $historyData[] = $total ? (int) $total : 0; // Default to 0 if no records found
        // For label, show 'Mon', 'Tue', etc.
        $historyLabels[] = date('D', strtotime($date));
    }

    // Prepare data for the meal categories chart
    $mealCategories = ['Breakfast', 'Lunch', 'Dinner', 'Snack'];
    $mealCategoryData = [];
    foreach ($mealCategories as $category) {
        $stmt = $pdo->prepare("
            SELECT SUM(calories) as total
            FROM intakeLog
            WHERE user_id = ?
            AND meal_category = ?
            AND DATE(date_intake) = CURDATE()
        ");
        $stmt->execute([$userId, $category]);
        $total = $stmt->fetchColumn();
        $mealCategoryData[$category] = $total ? (int) $total : 0; // Default to 0 if no records found
    }
} else {
    $intakeLog = []; // Empty array if not logged in
    $totalCalories = 0; // Default to 0 if not logged in
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
?>