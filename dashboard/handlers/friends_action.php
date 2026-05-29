<?php
/**
 * Friends AJAX dispatcher.
 *
 * Usage (POST, expects JSON or fetch):
 *   action=search    &q=hung
 *   action=send      &target_id=42
 *   action=accept    &request_id=7
 *   action=reject    &request_id=7
 *   action=cancel    &request_id=7
 *   action=unfriend  &target_id=42
 *   action=list_friends
 *   action=list_pending_in
 *   action=list_pending_out
 *   action=leaderboard&period=weekly|all_time
 *
 * Mutations require csrf_token. Read-only actions don't (they don't change state).
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);
ob_start();

header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../../include/init.php';
    require_once __DIR__ . '/../../include/csrf.php';
    require_once __DIR__ . '/../../include/handlers/friends.php';
    require_once __DIR__ . '/../../include/handlers/log_attempt.php';

    if (!isset($_SESSION['user'])) {
        throw new RuntimeException('Not authorised');
    }
    $me     = (int) $_SESSION['user']['user_id'];
    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    $mutations = ['send', 'accept', 'reject', 'cancel', 'unfriend'];
    if (in_array($action, $mutations, true)) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new RuntimeException('Method not allowed.');
        }
        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            throw new RuntimeException('Invalid CSRF token.');
        }
    }

    $response = ['ok' => true];

    switch ($action) {
        case 'search':
            $q = trim((string) ($_POST['q'] ?? $_GET['q'] ?? ''));
            $response['results'] = friends_search_users($pdo, $me, $q, 20);
            break;

        case 'send':
            $target = (int) ($_POST['target_id'] ?? 0);
            if ($target <= 0) throw new RuntimeException('Missing target_id.');
            $r = friends_send_request($pdo, $me, $target);
            friends_invalidate_pending_cache($target);
            log_attempt($pdo, $me, 'friend_request_send', "Sent request to user $target", 'friend_request', $r['request_id']);
            $response['request_id'] = $r['request_id'];
            break;

        case 'accept':
        case 'reject':
            $reqId = (int) ($_POST['request_id'] ?? 0);
            if ($reqId <= 0) throw new RuntimeException('Missing request_id.');
            friends_respond($pdo, $me, $reqId, $action);
            friends_invalidate_pending_cache($me);
            log_attempt($pdo, $me, 'friend_request_' . $action, "Request $reqId $action" . 'ed', 'friend_request', $reqId);
            break;

        case 'cancel':
            $reqId = (int) ($_POST['request_id'] ?? 0);
            if ($reqId <= 0) throw new RuntimeException('Missing request_id.');
            friends_cancel($pdo, $me, $reqId);
            log_attempt($pdo, $me, 'friend_request_cancel', "Cancelled request $reqId", 'friend_request', $reqId);
            break;

        case 'unfriend':
            $target = (int) ($_POST['target_id'] ?? 0);
            if ($target <= 0) throw new RuntimeException('Missing target_id.');
            friends_unfriend($pdo, $me, $target);
            log_attempt($pdo, $me, 'friend_unfriend', "Unfriended $target", 'user', $target);
            break;

        case 'list_friends':
            $response['friends'] = friends_list($pdo, $me);
            break;

        case 'list_pending_in':
            $response['pending'] = friends_pending_incoming($pdo, $me);
            break;

        case 'list_pending_out':
            $response['pending'] = friends_pending_outgoing($pdo, $me);
            break;

        case 'poll':
            // Lightweight snapshot for the friends page's live polling: friends +
            // both pending directions in one request. Read-only (no CSRF needed).
            $response['friends']     = friends_list($pdo, $me);
            $response['pending_in']  = friends_pending_incoming($pdo, $me);
            $response['pending_out'] = friends_pending_outgoing($pdo, $me);
            break;

        case 'leaderboard':
            $period = ($_POST['period'] ?? $_GET['period'] ?? 'weekly') === 'all_time' ? 'all_time' : 'weekly';
            $limit = (int) ($_POST['limit'] ?? $_GET['limit'] ?? 50);
            $response['period'] = $period;
            $response['leaders'] = leaderboard_friends($pdo, $me, $period, $limit);
            break;

        default:
            throw new RuntimeException('Unknown action.');
    }

    ob_clean();
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    ob_clean();
    http_response_code($e instanceof FriendsActionException ? 400 : 500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
exit;
