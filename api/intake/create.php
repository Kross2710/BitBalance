<?php
require_once __DIR__ . '/_helpers.php';

api_require_method('POST');

if (!isset($_SESSION['user']) || !isset($_SESSION['user']['user_id'])) {
    api_error('Authentication required.', 401);
}

$payload = api_intake_validate(api_request_data(), false);
$pdo = api_connect_db();
api_intake_ensure_functions();
require_once PROJECT_ROOT . 'include/handlers/log_attempt.php';
require_once PROJECT_ROOT . 'include/handlers/xp.php';

$user = api_require_auth($pdo);
$userId = (int) $user['user_id'];

try {
    $stmt = $pdo->prepare("
        INSERT INTO intakeLog (user_id, food_item, calories, protein, carbs, fat, meal_category)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $userId,
        $payload['food_item'],
        $payload['calories'],
        $payload['protein'],
        $payload['carbs'],
        $payload['fat'],
        $payload['meal_category']
    ]);

    $newId = (int) $pdo->lastInsertId();

    $xpResult = ['xp_added' => 0, 'leveled_up' => false];
    try {
        $xpResult = xp_award_intake_log($pdo, $userId);
    } catch (Throwable $e) {
        error_log('API intake xp award error: ' . $e->getMessage());
    }

    try {
        updateLoggingStreak($pdo, $userId);
        $streakRow = $pdo->prepare("SELECT logging_streak FROM userStatus WHERE user_id = ?");
        $streakRow->execute([$userId]);
        $newStreak = (int) $streakRow->fetchColumn();
        $milestoneResult = xp_award_streak_milestone($pdo, $userId, $newStreak);
        $xpResult['xp_added'] += isset($milestoneResult['xp_added']) ? (int) $milestoneResult['xp_added'] : 0;
        $xpResult['leveled_up'] = !empty($xpResult['leveled_up']) || !empty($milestoneResult['leveled_up']);
    } catch (Throwable $e) {
        error_log('API intake streak update error: ' . $e->getMessage());
    }

    log_attempt($pdo, $userId, 'log_intake', 'User logged intake via API', 'intakeLog', $newId);

    $entry = api_intake_fetch($pdo, $userId, $newId);
    $xpSummary = xp_get_summary($pdo, $userId);
    $levelUpFlash = function_exists('xp_consume_levelup_flash') ? xp_consume_levelup_flash() : null;

    api_send(true, [
        'entry' => $entry ? api_intake_entry($entry) : null,
        'daily_summary' => api_intake_daily_summary($pdo, $userId),
        'xp' => [
            'added' => (int) (isset($xpResult['xp_added']) ? $xpResult['xp_added'] : 0),
            'summary' => $xpSummary,
            'levelup' => $levelUpFlash
        ]
    ], null, 201);
} catch (Throwable $e) {
    error_log('API intake create error: ' . $e->getMessage());
    api_error('Unable to create intake entry.', 500);
}
