<?php
/**
 * dashboard/handlers/beats_mix_save.php
 * Persists the user's most recent DJ mix to their history / archetype
 * collection — but only when they tap "Keep" on the result card.
 *
 * The mix is read from $_SESSION['beats_last_mix'] (written by beats_mixer.php),
 * so we save exactly the analyzed result, never client-supplied text. The
 * pending entry is cleared after saving to prevent accidental double-keeps.
 *
 * POST (no body needed). Returns: { ok, saved, item? }
 */
require_once __DIR__ . '/../../include/init.php';
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId = (int) $_SESSION['user']['user_id'];
$pending = $_SESSION['beats_last_mix'] ?? null;

if (!is_array($pending) || empty($pending['track_name']) || empty($pending['food_item'])) {
    // Nothing to keep (already kept, discarded, or session expired).
    echo json_encode(['ok' => true, 'saved' => false]);
    exit;
}

$newId = bb_log_beats_mix($pdo, $userId, $pending);

// One-shot: clear so the same result can't be kept twice.
unset($_SESSION['beats_last_mix']);

$scores = is_array($pending['scores'] ?? null) ? $pending['scores'] : [];
echo json_encode([
    'ok' => true,
    'saved' => true,
    'item' => [
        'mix_id'        => $newId,
        'archetype'     => (string) ($pending['archetype'] ?? ''),
        'detected_vibe' => (string) ($pending['detected_vibe'] ?? ''),
        'track_name'    => (string) ($pending['track_name'] ?? ''),
        'food_item'     => (string) ($pending['food_item'] ?? ''),
        'match_score'   => (int) ($pending['match_score'] ?? 0),
        'rarity'        => (string) ($pending['rarity'] ?? ''),
    ],
]);
exit;
