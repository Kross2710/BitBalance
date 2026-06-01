<?php
/**
 * dashboard/handlers/beats_fuel.php
 * "Suggested Fuel for Your Current Beats" — now DETERMINISTIC (no Gemini call).
 *
 * The Beats page reads fuel suggestions straight from beats_mirror.php's response,
 * so this endpoint is no longer hit on page load. It is kept for any direct caller
 * and derives suggestions from the cached Mirror fingerprint + today's budget via
 * the shared engine. See docs/ai-cost-optimization.md (killed the duplicate AI call).
 *
 * Returns: { ok: bool, suggestions: [ { mood, vibe, food, reason, kcal } ] }
 */
require_once __DIR__ . '/../../include/init.php';
require_once __DIR__ . '/../../include/handlers/beats_identity.php';
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId = (int) $_SESSION['user']['user_id'];
$lang = (function_exists('current_locale') && current_locale() === 'vi') ? 'vi' : 'en';

// The Mirror's cached fingerprint is the single source of music vibe.
$cached = bb_get_beats_mirror_cache($pdo, $userId, $lang);
if ($cached === null || empty($cached['payload']['congruence']['per_axis'])) {
    // No fingerprint computed yet → let the page keep its static fallback cards.
    echo json_encode(['ok' => false, 'reason' => 'no_fingerprint']);
    exit;
}

// Reconstruct the music axes (0..1) from the cached per-axis music values.
$perAxis = $cached['payload']['congruence']['per_axis'];
$musicAxes = ['top_genre' => (string) ($cached['payload']['music']['top_genre'] ?? '')];
foreach (['energy', 'comfort', 'diversity', 'nocturnal'] as $ax) {
    $musicAxes[$ax] = isset($perAxis[$ax]['music']) ? ($perAxis[$ax]['music'] / 100.0) : 0.5;
}

// Today's remaining budget keeps suggestions goal-aware.
$goal = (int) (getUserIntakeGoal($userId) ?? 0);
$consumed = (int) (getTotalCaloriesToday($userId) ?? 0);
$remaining = $goal > 0 ? max(0, $goal - $consumed) : null;

$suggestions = bb_beats_fuel_suggestions($musicAxes, $remaining, $lang);

echo json_encode(['ok' => true, 'suggestions' => $suggestions]);
exit;
