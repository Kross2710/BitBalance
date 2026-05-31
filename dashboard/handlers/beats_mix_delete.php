<?php
/**
 * dashboard/handlers/beats_mix_delete.php
 * Removes one mix from the user's Diet & Beats history (swipe-to-delete).
 * Scoped to the owning user. Returns the refreshed collection state so the
 * client can keep the archetype strip + count in sync.
 *
 * POST: mix_id
 * Returns: { ok, deleted, archetype, archetype_remaining, collection_count }
 */
require_once __DIR__ . '/../../include/init.php';
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId = (int) $_SESSION['user']['user_id'];
$mixId = (int) ($_POST['mix_id'] ?? 0);

if ($mixId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Invalid mix id']);
    exit;
}

// Grab the archetype first so we can report whether any copies remain.
$archetype = '';
try {
    $stmt = $pdo->prepare("SELECT archetype FROM `beats_mix_log` WHERE mix_id = ? AND user_id = ?");
    $stmt->execute([$mixId, $userId]);
    $archetype = (string) ($stmt->fetchColumn() ?: '');
} catch (PDOException $e) {
    // fall through
}

$deleted = bb_delete_beats_mix($pdo, $userId, $mixId);

$archetypeRemaining = ($archetype !== '') ? bb_count_beats_archetype($pdo, $userId, $archetype) : 0;
$collectionCount = count(bb_get_beats_collection($pdo, $userId));

echo json_encode([
    'ok' => true,
    'deleted' => $deleted,
    'archetype' => $archetype,
    'archetype_remaining' => $archetypeRemaining,
    'collection_count' => $collectionCount,
]);
exit;
