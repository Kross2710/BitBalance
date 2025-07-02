<?php
/**
 * log_event.php
 * ------------------------------------------------------------
 * Receives JSON from the browser (via fetch) and records a user
 * action in the activity_log table using log_attempt().
 * ------------------------------------------------------------
 * Expected JSON payload, e.g.:
 * {
 *   "action_type": "add_to_cart",
 *   "description": "User added product to cart",
 *   "target_table": "product",
 *   "target_id": 5
 * }
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../init.php';          // starts session & sets up $pdo
require_once __DIR__ . '/log_attempt.php';     // brings in log_attempt()

// 1. Ensure the user is logged in

if (empty($_SESSION['user']['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// 2. Decode the incoming JSON
$payload = json_decode(file_get_contents('php://input'), true);
if (!$payload || empty($payload['action_type'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid payload']);
    exit;
}

// 3. Extract fields
$userId      = (int) $_SESSION['user']['user_id'];
$actionType  = trim($payload['action_type']);
$description = $payload['description']      ?? '';
$targetTable = $payload['target_table']     ?? null;
$targetId    = isset($payload['target_id']) ? (int) $payload['target_id'] : null;

// 4. Insert log entry
$ok = log_attempt($pdo, $userId, $actionType, $description, $targetTable, $targetId);

// 5. Return JSON response
echo json_encode(['success' => $ok]);