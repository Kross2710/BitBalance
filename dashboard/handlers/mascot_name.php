<?php
/**
 * dashboard/handlers/mascot_name.php
 * "Name your owl" (P1) — AJAX endpoint that stores the user-chosen pet name.
 *
 * POST: name
 * Returns: { ok: bool, name: string, error?: string }
 *
 * The name is sanitized server-side (see mascot_sanitize_name); an empty /
 * invalid name is rejected and nothing is written.
 */
require_once __DIR__ . '/../../include/init.php';
require_once __DIR__ . '/../../include/handlers/mascot_state.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId = (int) $_SESSION['user']['user_id'];
$raw = isset($_POST['name']) ? (string) $_POST['name'] : '';

$name = mascot_set_name($pdo, $userId, $raw);
if ($name === '') {
    echo json_encode(['ok' => false, 'error' => 'invalid_name']);
    exit;
}

echo json_encode(['ok' => true, 'name' => $name], JSON_UNESCAPED_UNICODE);
exit;
