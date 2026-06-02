<?php
/**
 * AJAX handler to clone a historical food entry into today's log.
 */
ini_set('display_errors', 0); // Disable raw text errors to keep AJAX JSON response clean
error_reporting(E_ALL);

require_once __DIR__ . '/../../include/init.php';
require_once __DIR__ . '/../../include/db_config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../../include/handlers/log_attempt.php';
require_once __DIR__ . '/../../include/handlers/xp.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Invalid request method.']);
    exit;
}

if (!$isLoggedIn) {
    echo json_encode(['ok' => false, 'error' => 'You must be logged in.']);
    exit;
}

$userId = $_SESSION['user']['user_id'];
$intakeId = isset($_POST['intake_id']) ? (int) $_POST['intake_id'] : 0;
$customDate = isset($_POST['custom_date']) ? $_POST['custom_date'] : null;

if ($intakeId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Invalid entry ID.']);
    exit;
}

$targetDate = date('Y-m-d');
if ($customDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $customDate)) {
    $targetDate = $customDate;
}

try {
    // 1. Fetch historical record to clone
    $stmt = $pdo->prepare("SELECT food_item, calories, protein, carbs, fat, meal_category FROM intakeLog WHERE intakeLog_id = ? AND user_id = ?");
    $stmt->execute([$intakeId, $userId]);
    $historyEntry = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$historyEntry) {
        echo json_encode(['ok' => false, 'error' => 'Entry not found.']);
        exit;
    }

    $foodItem = $historyEntry['food_item'];
    $calories = (int) $historyEntry['calories'];
    $protein  = (float) ($historyEntry['protein'] ?? 0);
    $carbs    = (float) ($historyEntry['carbs'] ?? 0);
    $fat      = (float) ($historyEntry['fat'] ?? 0);
    $mealCategory = $historyEntry['meal_category'];

    // 2. Insert new record for target date
    $dateIntake = $targetDate . ' ' . date('H:i:s');
    $insertStmt = $pdo->prepare("INSERT INTO intakeLog (user_id, food_item, calories, protein, carbs, fat, meal_category, date_intake) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$insertStmt->execute([$userId, $foodItem, $calories, $protein, $carbs, $fat, $mealCategory, $dateIntake])) {
        echo json_encode(['ok' => false, 'error' => 'Failed to clone entry in database.']);
        exit;
    }

    $newIntakeId = (int) $pdo->lastInsertId();

    // 3. Award XP (state-based, duplicate prevention)
    $xpResult = ['xp_added' => 0, 'leveled_up' => false];
    try {
        $xpResult = xp_award_intake_log($pdo, $userId);
    } catch (Throwable $e) {
        error_log('xp_award_intake_log error: ' . $e->getMessage());
    }

    // 4. Update logging streak
    try {
        updateLoggingStreak($pdo, $userId);
        $streakRow = $pdo->prepare("SELECT logging_streak FROM userStatus WHERE user_id = ?");
        $streakRow->execute([$userId]);
        $newStreak = (int) $streakRow->fetchColumn();
        $milestoneRes = xp_award_streak_milestone($pdo, $userId, $newStreak);
        $xpResult['xp_added']   += $milestoneRes['xp_added'] ?? 0;
        $xpResult['leveled_up'] = $xpResult['leveled_up'] || !empty($milestoneRes['leveled_up']);
    } catch (Throwable $e) {
        error_log('streak update error: ' . $e->getMessage());
    }

    // 5. Retrieve target date's updated calorie progress & macros breakdown for UI sync
    $caloriesRow = $pdo->prepare("SELECT SUM(calories) FROM intakeLog WHERE user_id = ? AND DATE(date_intake) = ?");
    $caloriesRow->execute([$userId, $targetDate]);
    $totalCalories = (int) $caloriesRow->fetchColumn();

    $goalStmt = $pdo->prepare("
        SELECT calorie_goal
        FROM userGoal
        WHERE user_id = ?
        ORDER BY date_set DESC
        LIMIT 1
    ");
    $goalStmt->execute([$userId]);
    $userGoal = (int) ($goalStmt->fetchColumn() ?? 0);
    if ($userGoal <= 0) $userGoal = 2000; // default backup

    $percentage = ($totalCalories / $userGoal) * 100;
    if ($percentage > 100) $percentage = 100;

    // Macro breakdowns
    $macros = getMacroTotalsToday($userId, $targetDate);
    $macroGoals = resolveMacroGoals($userId);

    // 6. Render the new row markup via the shared view component
    $entry = [
        'intakeLog_id'  => $newIntakeId,
        'food_item'     => $foodItem,
        'calories'      => $calories,
        'protein'       => $protein,
        'carbs'         => $carbs,
        'fat'           => $fat,
        'meal_category' => $mealCategory,
        'date_intake'   => $dateIntake,
    ];
    $showDate  = isset($_POST['show_date']) ? (bool)$_POST['show_date'] : false;
    $timeLabel = 'Just now';

    ob_start();
    include PROJECT_ROOT . 'dashboard/views/_intake-row.php';
    $newRowMarkup = ob_get_clean();

    // Log the successful quick clone action
    log_attempt($pdo, $userId, 'quick_log', 'User cloned food entry from history', 'intakeLog', $newIntakeId);

    $xpSummary = xp_get_summary($pdo, $userId);
    $levelUpFlash = xp_consume_levelup_flash();

    // 7. Return complete response
    echo json_encode([
        'ok' => true,
        'new_row' => $newRowMarkup,
        'food_item' => $foodItem,
        'calories' => $calories,
        'total' => $totalCalories,
        'percentage' => $percentage,
        'macros' => $macros,
        'macro_goals' => $macroGoals,
        'xp' => [
            'added'   => (int) ($xpResult['xp_added'] ?? 0),
            'summary' => $xpSummary,
            'levelup' => $levelUpFlash,
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => 'Server error: ' . $e->getMessage()]);
    exit;
}
