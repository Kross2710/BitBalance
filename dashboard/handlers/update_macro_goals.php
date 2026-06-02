<?php
// dashboard/handlers/update_macro_goals.php
// Save a user's own explicit macro split. Writes a new userGoal row (history,
// latest-wins) carrying the current calorie goal + the chosen P/C/F grams,
// attributed source='self'. resolveMacroGoals() then uses these instead of the
// derived split everywhere.
require_once __DIR__ . '/../../include/init.php';
require_once __DIR__ . '/../../include/db_config.php';
require_once __DIR__ . '/../../include/csrf.php';

header('Content-Type: application/json');

if (!$isLoggedIn) {
    echo json_encode(['ok' => false, 'error' => 'Not logged in.']);
    exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Invalid method.']);
    exit();
}
if (!csrf_verify($_POST['csrf_token'] ?? '')) {
    echo json_encode(['ok' => false, 'error' => 'CSRF verification failed.']);
    exit();
}

$userId = (int) $user['user_id'];

// Macros must all be present and valid (the editor always sends a full split).
$macros = [];
foreach (['protein', 'carbs', 'fat'] as $mk) {
    $iv = filter_var($_POST[$mk] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 999]]);
    if ($iv === false || $iv === null) {
        echo json_encode(['ok' => false, 'error' => 'Macro values must be whole numbers between 0 and 999.']);
        exit();
    }
    $macros[$mk] = $iv;
}

try {
    // Carry the current calorie goal onto the new row (macros attach to a goal row).
    $stmt = $pdo->prepare("SELECT calorie_goal FROM userGoal WHERE user_id = ? ORDER BY date_set DESC LIMIT 1");
    $stmt->execute([$userId]);
    $calorie = (int) $stmt->fetchColumn();
    if ($calorie <= 0) {
        echo json_encode(['ok' => false, 'error' => 'No calorie goal set yet.']);
        exit();
    }

    $ins = $pdo->prepare("
        INSERT INTO userGoal (user_id, calorie_goal, protein_goal, carbs_goal, fat_goal, set_by, source, date_set)
        VALUES (?, ?, ?, ?, ?, ?, 'self', NOW())
    ");
    $ins->execute([$userId, $calorie, $macros['protein'], $macros['carbs'], $macros['fat'], $userId]);

    echo json_encode(['ok' => true, 'calorie_goal' => $calorie, 'macros' => $macros]);
    exit();
} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    exit();
}
