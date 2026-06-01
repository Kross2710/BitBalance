<?php
require_once __DIR__ . '/_helpers.php';

api_require_method('POST');

if (!isset($_SESSION['user']) || !isset($_SESSION['user']['user_id'])) {
    api_error('Authentication required.', 401);
}

$data = api_request_data();
$intakeId = isset($data['intake_id']) ? (int) $data['intake_id'] : 0;
if ($intakeId <= 0) {
    api_error('Missing intake ID.', 422);
}

$pdo = api_connect_db();
require_once PROJECT_ROOT . 'include/handlers/log_attempt.php';

$user = api_require_auth($pdo);
$userId = (int) $user['user_id'];

try {
    // Snapshot before deleting so clients can offer an Undo (re-insert).
    $rowStmt = $pdo->prepare("SELECT food_item, calories, protein, carbs, fat, meal_category, image_path, date_intake
                              FROM intakeLog WHERE intakeLog_id = ? AND user_id = ?");
    $rowStmt->execute([$intakeId, $userId]);
    $deletedRow = $rowStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    $stmt = $pdo->prepare("DELETE FROM intakeLog WHERE intakeLog_id = ? AND user_id = ?");
    $stmt->execute([$intakeId, $userId]);

    if ($stmt->rowCount() < 1) {
        api_error('Intake entry not found.', 404);
    }

    log_attempt($pdo, $userId, 'delete_intake', 'User deleted intake via API', 'intakeLog', $intakeId);

    api_send(true, [
        'deleted_id' => $intakeId,
        'deleted_row' => $deletedRow,
        'daily_summary' => api_intake_daily_summary($pdo, $userId)
    ], null);
} catch (Throwable $e) {
    error_log('API intake delete error: ' . $e->getMessage());
    api_error('Unable to delete intake entry.', 500);
}
