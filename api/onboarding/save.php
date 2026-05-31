<?php
// Mirrors the commit block in dashboard/set-goal.php.
// Validates, builds personal plan, saves physical info + weight log + goal.
require_once __DIR__ . '/../_bootstrap.php';
require_once PROJECT_ROOT . 'dashboard/handlers/functions.php';
require_once PROJECT_ROOT . 'dashboard/handlers/goal_plan.php';
require_once PROJECT_ROOT . 'include/handlers/log_attempt.php';

api_require_method('POST');

$pdo = api_connect_db();
$user = api_require_auth($pdo);
$userId = (int) $user['user_id'];

$data = api_request_data();

$gender       = isset($data['gender'])         ? trim($data['gender'])                        : '';
$age          = isset($data['age'])            ? filter_var($data['age'],    FILTER_VALIDATE_INT) : false;
$height       = isset($data['height'])         ? filter_var($data['height'], FILTER_VALIDATE_INT) : false;
$weight       = isset($data['weight'])         ? filter_var($data['weight'], FILTER_VALIDATE_INT) : false;
$activityLevel = isset($data['activity_level']) ? trim($data['activity_level'])                : '';
$goalMode     = isset($data['goal_mode'])      ? trim($data['goal_mode'])                     : '';
$weeklyRate   = isset($data['weekly_rate']) && $data['weekly_rate'] !== ''
    ? filter_var($data['weekly_rate'], FILTER_VALIDATE_FLOAT)
    : null;
$targetWeight = isset($data['target_weight']) && $data['target_weight'] !== ''
    ? filter_var($data['target_weight'], FILTER_VALIDATE_FLOAT)
    : null;

$validGenders   = ['male', 'female', 'other'];
$activityOptions = plan_activity_options();
$goalModes       = plan_goal_modes();

if (!in_array($gender, $validGenders, true)) {
    api_error('Please choose a gender.', 422);
}
if ($age === false || $age === null || $age < 13 || $age > 100) {
    api_error('Please choose a valid age.', 422);
}
if ($height === false || $height === null || $height < 100 || $height > 250) {
    api_error('Please choose a valid height.', 422);
}
if ($weight === false || $weight === null || $weight < 30 || $weight > 300) {
    api_error('Please choose a valid weight.', 422);
}
if (!isset($activityOptions[$activityLevel])) {
    api_error('Please choose an activity level.', 422);
}
if (!isset($goalModes[$goalMode])) {
    api_error('Please choose a goal.', 422);
}
if ($goalMode === 'maintain') {
    $weeklyRate = 0.0;
    $targetWeight = null;
} elseif ($weeklyRate === false || $weeklyRate === null) {
    api_error('Please choose a weekly pace.', 422);
}

try {
    $plan = plan_build_personal_plan(
        (int) $age,
        $gender,
        (float) $weight,
        (float) $height,
        $activityLevel,
        $goalMode,
        $weeklyRate
    );

    $pdo->beginTransaction();

    // Upsert physical info
    $stmt = $pdo->prepare('SELECT userPhysicalStat_id FROM userPhysicalInfo WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    if ($stmt->fetch()) {
        $pdo->prepare('UPDATE userPhysicalInfo SET age=?, gender=?, weight=?, height=? WHERE user_id=?')
            ->execute([$age, $gender, $weight, $height, $userId]);
    } else {
        $pdo->prepare('INSERT INTO userPhysicalInfo (userPhysicalStat_id, user_id, age, gender, weight, height) VALUES (?,?,?,?,?,?)')
            ->execute([$userId, $userId, $age, $gender, $weight, $height]);
    }

    // Weight log, same-day upsert behavior as dashboard/set-goal.php.
    $stmt = $pdo->prepare('SELECT weight_id FROM weight_log WHERE user_id = ? AND date_logged = CURDATE() LIMIT 1');
    $stmt->execute([$userId]);
    $weightId = $stmt->fetchColumn();
    if ($weightId) {
        $pdo->prepare('UPDATE weight_log SET weight = ? WHERE weight_id = ? AND user_id = ?')
            ->execute([(float) $weight, (int) $weightId, $userId]);
    } else {
        $pdo->prepare('INSERT INTO weight_log (user_id, weight, date_logged) VALUES (?, ?, CURDATE())')
            ->execute([$userId, (float) $weight]);
    }

    // Plan preferences
    try {
        plan_save_preferences($pdo, $userId, [
            'goal_mode'      => $goalMode,
            'weekly_rate'    => $weeklyRate,
            'activity_level' => $activityLevel,
            'target_weight'  => $targetWeight,
        ]);
    } catch (PDOException $e) {
        error_log('Onboarding plan prefs save failed: ' . $e->getMessage());
    }

    // Calorie goal
    $pdo->prepare('INSERT INTO userGoal (user_id, calorie_goal, date_set) VALUES (?, ?, NOW())')
        ->execute([$userId, (int) $plan['calorie_goal']]);

    log_attempt($pdo, $userId, 'onboarding_plan_commit', 'Mobile onboarding plan saved', 'userGoal', (int) $pdo->lastInsertId());

    $pdo->commit();

    api_send(true, [
        'calorie_goal' => (int) $plan['calorie_goal'],
        'bmr'          => (int) round($plan['bmr']),
        'tdee'         => (int) round($plan['tdee']),
        'macros'       => $plan['macros'],
        'hydration_ml' => (int) $plan['hydration_ml'],
    ], null);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('API onboarding save error: ' . $e->getMessage());
    api_error('Could not save your plan. Please try again.', 500);
}
