<?php
/**
 * Food suggestions from the user's own logging history.
 *
 * GET /api/intake/suggest.php?q=<text>
 *   - q present  -> foods whose name contains <q> (autocomplete-as-you-type)
 *   - q empty    -> the user's most-frequently logged foods (recent chips)
 *
 * The app has no master food database, so suggestions come from intakeLog.
 * Each item carries the macros from the MOST RECENT time the user logged that
 * food (latest intakeLog_id per name) so selecting one pre-fills the form the
 * way they usually log it.
 *
 * Response: { ok, data: { items: [{ food_item, calories, protein, carbs, fat, freq }] }, message }
 */
require_once __DIR__ . '/_helpers.php';

api_require_method('GET');

if (!isset($_SESSION['user']) || !isset($_SESSION['user']['user_id'])) {
    api_error('Authentication required.', 401);
}

$pdo = api_connect_db();
$user = api_require_auth($pdo);
$userId = (int) $user['user_id'];

$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
// Cap to 100 chars, UTF-8-safe. mbstring is absent on RMIT (mb_substr would fatal),
// and no mb_* polyfill exists in this repo, so use iconv with a regex fallback.
if (function_exists('iconv_substr') && ($qCut = @iconv_substr($q, 0, 100, 'UTF-8')) !== false) {
    $q = $qCut;
} elseif (preg_match_all('/./us', $q, $qm)) {
    $q = implode('', array_slice($qm[0], 0, 100));
} else {
    $q = substr($q, 0, 100);
}

// Escape LIKE wildcards in user input so % and _ are matched literally.
$like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';

try {
    // Pick the latest row per distinct food name (MAX(intakeLog_id)), with how
    // often it was logged (freq) for ranking. No window functions, so it works
    // on older MySQL/MariaDB.
    $stmt = $pdo->prepare("
        SELECT i.food_item, i.calories, i.protein, i.carbs, i.fat, c.freq
        FROM intakeLog i
        JOIN (
            SELECT food_item, COUNT(*) AS freq, MAX(intakeLog_id) AS last_id
            FROM intakeLog
            WHERE user_id = :uid AND food_item LIKE :likeq
            GROUP BY food_item
        ) c ON c.last_id = i.intakeLog_id
        ORDER BY c.freq DESC, i.intakeLog_id DESC
        LIMIT 8
    ");
    $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':likeq', $like, PDO::PARAM_STR);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $items = [];
    foreach ($rows as $row) {
        $items[] = [
            'food_item' => $row['food_item'],
            'calories' => (int) $row['calories'],
            'protein' => round((float) $row['protein'], 1),
            'carbs' => round((float) $row['carbs'], 1),
            'fat' => round((float) $row['fat'], 1),
            'freq' => (int) $row['freq'],
        ];
    }

    api_send(true, ['items' => $items], null);
} catch (Throwable $e) {
    error_log('API intake suggest error: ' . $e->getMessage());
    api_error('Unable to load suggestions.', 500);
}
