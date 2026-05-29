<?php
require_once __DIR__ . '/../_bootstrap.php';

function api_intake_ensure_functions()
{
    require_once PROJECT_ROOT . 'dashboard/handlers/functions.php';
}

function api_intake_allowed_categories()
{
    return ['breakfast', 'lunch', 'dinner', 'snack'];
}

function api_intake_float($value, $default = 0)
{
    if ($value === null || $value === '') {
        return $default;
    }

    return (float) $value;
}

function api_intake_validate(array $data, $requireId = false)
{
    $id = isset($data['intake_id']) ? (int) $data['intake_id'] : 0;
    $foodItem = isset($data['food_item']) ? trim($data['food_item']) : '';
    $calories = isset($data['calories']) ? (int) $data['calories'] : 0;
    $category = isset($data['meal_category']) ? strtolower(trim($data['meal_category'])) : '';
    $protein = api_intake_float(isset($data['protein']) ? $data['protein'] : null);
    $carbs = api_intake_float(isset($data['carbs']) ? $data['carbs'] : null);
    $fat = api_intake_float(isset($data['fat']) ? $data['fat'] : null);

    if ($requireId && $id <= 0) {
        api_error('Missing intake ID.', 422);
    }

    if ($foodItem === '' || $calories <= 0 || $category === '') {
        api_error('Please fill in all fields.', 422);
    }

    if ($calories > 5000) {
        api_error('Calories must be a number between 1 and 5000.', 422);
    }

    if (!in_array($category, api_intake_allowed_categories(), true)) {
        api_error('Invalid meal category.', 422);
    }

    foreach (['protein' => $protein, 'carbs' => $carbs, 'fat' => $fat] as $name => $value) {
        if ($value < 0 || $value > 999) {
            api_error(ucfirst($name) . ' must be between 0 and 999 grams.', 422);
        }
    }

    return [
        'id' => $id,
        'food_item' => $foodItem,
        'calories' => $calories,
        'meal_category' => $category,
        'protein' => $protein,
        'carbs' => $carbs,
        'fat' => $fat
    ];
}

function api_intake_iso_vn($dbDatetime)
{
    if (empty($dbDatetime)) {
        return null;
    }

    try {
        $dt = new DateTime($dbDatetime, new DateTimeZone('Asia/Ho_Chi_Minh'));
        return $dt->format('c');
    } catch (Exception $e) {
        return null;
    }
}

function api_intake_entry(array $row)
{
    return [
        'id' => (int) $row['intakeLog_id'],
        'food_item' => isset($row['food_item']) ? $row['food_item'] : '',
        'calories' => (int) $row['calories'],
        'protein' => (float) (isset($row['protein']) ? $row['protein'] : 0),
        'carbs' => (float) (isset($row['carbs']) ? $row['carbs'] : 0),
        'fat' => (float) (isset($row['fat']) ? $row['fat'] : 0),
        'meal_category' => isset($row['meal_category']) ? $row['meal_category'] : '',
        'date_intake' => isset($row['date_intake']) ? $row['date_intake'] : null,
        'iso_date' => api_intake_iso_vn(isset($row['date_intake']) ? $row['date_intake'] : null)
    ];
}

function api_intake_daily_summary(PDO $pdo, $userId)
{
    api_intake_ensure_functions();

    $totalStmt = $pdo->prepare("
        SELECT COALESCE(SUM(calories), 0)
        FROM intakeLog
        WHERE user_id = ? AND DATE(date_intake) = CURDATE()
    ");
    $totalStmt->execute([$userId]);
    $totalCalories = (int) $totalStmt->fetchColumn();

    $goalStmt = $pdo->prepare("
        SELECT calorie_goal
        FROM userGoal
        WHERE user_id = ?
        ORDER BY date_set DESC
        LIMIT 1
    ");
    $goalStmt->execute([$userId]);
    $goal = $goalStmt->fetchColumn();
    $goal = $goal ? (int) $goal : null;

    $percentage = 0;
    if ($goal && $goal > 0) {
        $percentage = round(($totalCalories / $goal) * 100, 2);
        $percentage = min(100, max(0, $percentage));
    }

    return [
        'total_calories' => $totalCalories,
        'calorie_goal' => $goal,
        'progress_percentage' => $percentage,
        'macros' => getMacroTotalsToday((int) $userId),
        'macro_goals' => getMacroGoalsFromCalorieGoal($goal)
    ];
}

function api_intake_fetch(PDO $pdo, $userId, $intakeId)
{
    $stmt = $pdo->prepare("
        SELECT intakeLog_id, food_item, calories, protein, carbs, fat, meal_category, date_intake
        FROM intakeLog
        WHERE user_id = ? AND intakeLog_id = ?
        LIMIT 1
    ");
    $stmt->execute([(int) $userId, (int) $intakeId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
