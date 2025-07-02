<?php
require_once __DIR__ . '/../../include/init.php';
require_once __DIR__ . '/../../include/db_config.php';
require_once __DIR__ . '/../../include/handlers/log_attempt.php';

$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'fetch';

if (!isset($_SESSION['user'])) {
    ajaxOrRedirect($isAjax, false, 'Not authorised', '../dashboard-intake.php');
}

$userId   = $_SESSION['user']['user_id'];
$intakeId = $_POST['intake_id'] ?? null;

if (!$intakeId) {
    ajaxOrRedirect($isAjax, false, 'Missing intake ID', '../dashboard-intake.php');
}

$del = $pdo->prepare("DELETE FROM intakeLog WHERE intakeLog_id = ? AND user_id = ?");
$ok  = $del->execute([$intakeId, $userId]);

if (!$ok) {
    ajaxOrRedirect($isAjax, false, 'Delete failed', '../dashboard-intake.php');
}

/* --- recalc today total & % --- */
$tot = $pdo->prepare("
    SELECT COALESCE(SUM(calories),0)
    FROM intakeLog
    WHERE user_id = ? AND DATE(date_intake) = CURDATE()");
$tot->execute([$userId]);
$totalCalories = (int)$tot->fetchColumn();

// fetch the latest goal from userGoal table (if any)
$goalStmt = $pdo->prepare("
    SELECT calorie_goal
    FROM userGoal
    WHERE user_id = ?
    ORDER BY date_set DESC
    LIMIT 1
");
$goalStmt->execute([$userId]);
$goal = (int)($goalStmt->fetchColumn() ?? 0);

$percentage = $goal ? min(100, round($totalCalories / $goal * 100)) : 0;

/* --- log the attempt --- */
log_attempt($pdo, $userId, 'delete_intake', 'User deleted intake','intakeLog', $intakeId);

/* --- respond --- */
ajaxOrRedirect($isAjax, true, '', '../dashboard-intake.php?success=1',
               $totalCalories, $percentage);


/* Helper */
function ajaxOrRedirect($ajax,$ok,$msg,$url,$total=0,$pct=0){
    if ($ajax) {
        header('Content-Type: application/json');
        echo json_encode([
            'ok' => $ok,
            'error' => $msg,
            'total' => $total,
            'percentage' => $pct
        ]);
    } else {
        if (!$ok) $url = "../dashboard-intake.php?error=" . urlencode($msg);
        header("Location: $url");
    }
    exit;
}