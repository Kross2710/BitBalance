<?php
require_once __DIR__ . '/../../include/init.php';
require_once __DIR__ . '/../../include/csrf.php';
require_once __DIR__ . '/../../include/handlers/log_attempt.php';

if (!isset($_SESSION['user']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../dashboard-plan.php');
    exit();
}

if (!csrf_verify($_POST['csrf_token'] ?? '')) {
    header('Location: ../dashboard-plan.php?error=' . urlencode('Invalid request token.'));
    exit();
}

$userId = (int) $_SESSION['user']['user_id'];
$goal = filter_input(INPUT_POST, 'calorie_goal', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 800, 'max_range' => 10000],
]);

if ($goal === false || $goal === null) {
    header('Location: ../dashboard-plan.php?error=' . urlencode('Please apply a valid calorie goal between 800 and 10,000 kcal.'));
    exit();
}

try {
    $stmt = $pdo->prepare('INSERT INTO userGoal (user_id, calorie_goal, date_set) VALUES (?, ?, NOW())');
    $stmt->execute([$userId, $goal]);

    log_attempt($pdo, $userId, 'apply_goal_plan', 'User applied Goal Planner recommendation', 'userGoal', (int) $pdo->lastInsertId());

    header('Location: ../dashboard-plan.php?success=' . urlencode('Daily calorie goal updated from your plan.'));
} catch (PDOException $e) {
    error_log('Goal Planner apply failed: ' . $e->getMessage());
    header('Location: ../dashboard-plan.php?error=' . urlencode('Could not apply the goal. Please try again.'));
}
exit();
?>
