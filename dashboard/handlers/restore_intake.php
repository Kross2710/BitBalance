<?php
/**
 * Undo a delete: re-insert an intake row that was just removed, preserving its
 * original date/time. Paired with delete_intake.php, which returns the row
 * snapshot the client sends back here.
 *
 * Deliberately does NOT award XP or touch the streak — XP is state-based and was
 * not removed on delete, so re-inserting must not let delete+restore farm points.
 *
 * Returns the same shape as process_intake.php (new_row + today's totals) so the
 * intake page can drop the row straight back in.
 */
ini_set('display_errors', 0);
error_reporting(E_ALL);
ob_start();
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../../include/init.php';
    require_once __DIR__ . '/functions.php';
    require_once __DIR__ . '/../../include/handlers/log_attempt.php';

    if (!isset($_SESSION['user'])) {
        throw new Exception('Not authorised');
    }
    $userId = $_SESSION['user']['user_id'];

    $foodItem = isset($_POST['food_item']) ? trim($_POST['food_item']) : '';
    $calories = isset($_POST['calories']) ? (int) $_POST['calories'] : 0;
    $category = isset($_POST['meal_category']) ? strtolower(trim($_POST['meal_category'])) : '';
    $protein  = isset($_POST['protein']) && $_POST['protein'] !== '' ? (float) $_POST['protein'] : 0;
    $carbs    = isset($_POST['carbs'])   && $_POST['carbs']   !== '' ? (float) $_POST['carbs']   : 0;
    $fat      = isset($_POST['fat'])     && $_POST['fat']     !== '' ? (float) $_POST['fat']     : 0;

    $imagePath = isset($_POST['image_path']) && $_POST['image_path'] !== '' ? trim($_POST['image_path']) : null;
    if ($imagePath !== null && strpos($imagePath, 'uploads/intake/') !== 0) {
        $imagePath = null;
    }

    // Restore the original timestamp; never allow a future one. Fall back to now
    // if it's missing or malformed.
    $dateIntake = isset($_POST['date_intake']) ? trim($_POST['date_intake']) : '';
    if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $dateIntake) || strtotime($dateIntake) > time()) {
        $dateIntake = date('Y-m-d H:i:s');
    }

    if ($foodItem === '' || $calories <= 0 || $calories > 5000) {
        throw new Exception('Invalid entry');
    }
    if (!in_array($category, ['breakfast', 'lunch', 'dinner', 'snack'], true)) {
        throw new Exception('Invalid meal category');
    }
    foreach (['protein' => $protein, 'carbs' => $carbs, 'fat' => $fat] as $value) {
        if ($value < 0 || $value > 999) {
            throw new Exception('Macros out of range');
        }
    }

    $ins = $pdo->prepare("INSERT INTO intakeLog (user_id, food_item, calories, protein, carbs, fat, meal_category, image_path, date_intake)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $ins->execute([$userId, $foodItem, $calories, $protein, $carbs, $fat, $category, $imagePath, $dateIntake]);
    $newId = (int) $pdo->lastInsertId();

    // Render the row through the shared partial so markup matches everywhere.
    $entry = [
        'intakeLog_id'  => $newId,
        'food_item'     => $foodItem,
        'calories'      => $calories,
        'protein'       => $protein,
        'carbs'         => $carbs,
        'fat'           => $fat,
        'meal_category' => $category,
        'image_path'    => $imagePath,
        'date_intake'   => $dateIntake,
    ];
    $showDate  = !empty($_POST['show_date']);
    $timeLabel = null; // keep the original time-of-day
    ob_start();
    include PROJECT_ROOT . 'dashboard/views/_intake-row.php';
    $newRow = ob_get_clean();

    // Recompute TODAY's totals on the same basis as delete_intake.php so the
    // displayed number reverts exactly to its pre-delete value.
    $tot = $pdo->prepare("SELECT COALESCE(SUM(calories),0) FROM intakeLog WHERE user_id = ? AND DATE(date_intake) = CURDATE()");
    $tot->execute([$userId]);
    $totalCalories = (int) $tot->fetchColumn();

    $goalStmt = $pdo->prepare("SELECT calorie_goal FROM userGoal WHERE user_id = ? ORDER BY date_set DESC LIMIT 1");
    $goalStmt->execute([$userId]);
    $goal = (int) ($goalStmt->fetchColumn() ?? 0);
    $percentage = $goal ? min(100, round($totalCalories / $goal * 100)) : 0;

    log_attempt($pdo, $userId, 'restore_intake', 'User undid an intake delete', 'intakeLog', $newId);

    ob_clean();
    echo json_encode([
        'ok' => true,
        'new_row' => $newRow,
        'total' => $totalCalories,
        'percentage' => $percentage,
        'macros' => getMacroTotalsToday($userId),
        'macro_goals' => getMacroGoalsFromCalorieGoal($goal),
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    ob_clean();
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
exit;
