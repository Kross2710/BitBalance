<?php
require_once __DIR__ . '/_helpers.php';

api_require_method('GET');

if (!isset($_SESSION['user']) || !isset($_SESSION['user']['user_id'])) {
    api_error('Authentication required.', 401);
}

$pdo = api_connect_db();
$user = api_require_auth($pdo);
$userId = (int) $user['user_id'];
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 50;
$limit = max(1, min(100, $limit));

try {
    $stmt = $pdo->prepare("
        SELECT intakeLog_id, food_item, calories, protein, carbs, fat, meal_category, date_intake
        FROM intakeLog
        WHERE user_id = ?
        ORDER BY date_intake DESC, intakeLog_id DESC
        LIMIT " . $limit
    );
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $entries = [];
    foreach ($rows as $row) {
        $entries[] = api_intake_entry($row);
    }

    api_send(true, [
        'entries' => $entries,
        'daily_summary' => api_intake_daily_summary($pdo, $userId)
    ], null);
} catch (Throwable $e) {
    error_log('API intake history error: ' . $e->getMessage());
    api_error('Unable to load intake history.', 500);
}
