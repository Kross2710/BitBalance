<?php
/**
 * dashboard/handlers/mascot_select.php
 * "Pet Picker" (P2) — set the user's active mascot species.
 *
 * POST: species
 * Returns: { ok: bool, species: string, name: string, error?: string }
 *          where `name` is that species' stored pet name ('' if unnamed).
 *
 * Free choice: any valid species can be selected (no unlock/gating).
 */
require_once __DIR__ . '/../../include/init.php';
require_once __DIR__ . '/../../include/handlers/mascot_species.php';
require_once __DIR__ . '/../../include/handlers/mascot_state.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId = (int) $_SESSION['user']['user_id'];
$species = isset($_POST['species']) ? (string) $_POST['species'] : '';

if (!mascot_species_valid($species)) {
    echo json_encode(['ok' => false, 'error' => 'invalid_species']);
    exit;
}

$saved = mascot_set_active_species($pdo, $userId, $species);
if ($saved === '') {
    echo json_encode(['ok' => false, 'error' => 'save_failed']);
    exit;
}

$name = mascot_get_name($pdo, $userId, $saved);
echo json_encode(['ok' => true, 'species' => $saved, 'name' => $name], JSON_UNESCAPED_UNICODE);
exit;
