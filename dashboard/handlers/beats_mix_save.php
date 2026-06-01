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
require_once __DIR__ . '/../../include/handlers/beats_identity.php';

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

// Catalog flavor text for the live Pokedex unlock (so the freshly revealed card
// opens a complete detail modal before any reload). Falls back to '' if unknown.
$lang = (function_exists('current_locale') && current_locale() === 'vi') ? 'vi' : 'en';
$archetypeKey = (string) ($pending['archetype_key'] ?? '');
$catalogEntry = $archetypeKey !== '' ? bb_beats_archetype_by_key($archetypeKey) : null;
$voice = ($catalogEntry !== null) ? (string) ($catalogEntry['voice'][$lang] ?? '') : '';

echo json_encode([
    'ok' => true,
    'saved' => true,
    // archetype_key/icon/rarity_tier let the page unlock the matching dex card in
    // place (no reload); voice powers its detail modal.
    'item' => [
        'mix_id'         => $newId,
        'archetype'      => (string) ($pending['archetype'] ?? ''),
        'archetype_key'  => $archetypeKey,
        'archetype_icon' => (string) ($pending['archetype_icon'] ?? 'fa-music'),
        'rarity_tier'    => (string) ($pending['rarity_tier'] ?? 'common'),
        'voice'          => $voice,
        'detected_vibe'  => (string) ($pending['detected_vibe'] ?? ''),
        'track_name'     => (string) ($pending['track_name'] ?? ''),
        'food_item'      => (string) ($pending['food_item'] ?? ''),
        'match_score'    => (int) ($pending['match_score'] ?? 0),
        'rarity'         => (string) ($pending['rarity'] ?? ''),
    ],
]);
exit;
