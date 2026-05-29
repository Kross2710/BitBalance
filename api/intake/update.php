<?php
require_once __DIR__ . '/_helpers.php';

api_require_method('POST');

if (!isset($_SESSION['user']) || !isset($_SESSION['user']['user_id'])) {
    api_error('Authentication required.', 401);
}

$payload = api_intake_validate(api_request_data(), true);
$pdo = api_connect_db();
require_once PROJECT_ROOT . 'include/handlers/log_attempt.php';

$user = api_require_auth($pdo);
$userId = (int) $user['user_id'];

try {
    $stmt = $pdo->prepare("
        UPDATE intakeLog
        SET food_item = ?, calories = ?, protein = ?, carbs = ?, fat = ?, meal_category = ?
        WHERE intakeLog_id = ? AND user_id = ?
    ");
    $stmt->execute([
        $payload['food_item'],
        $payload['calories'],
        $payload['protein'],
        $payload['carbs'],
        $payload['fat'],
        $payload['meal_category'],
        $payload['id'],
        $userId
    ]);

    $entry = api_intake_fetch($pdo, $userId, $payload['id']);
    if (!$entry) {
        api_error('Intake entry not found.', 404);
    }

    log_attempt($pdo, $userId, 'edit_intake', 'User edited intake via API', 'intakeLog', $payload['id']);

    api_send(true, [
        'entry' => api_intake_entry($entry),
        'daily_summary' => api_intake_daily_summary($pdo, $userId)
    ], null);
} catch (Throwable $e) {
    error_log('API intake update error: ' . $e->getMessage());
    api_error('Unable to update intake entry.', 500);
}
