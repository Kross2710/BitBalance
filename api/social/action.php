<?php
/**
 * Mobile API for Friends, Social & Leaderboard.
 */
require_once __DIR__ . '/../_bootstrap.php';

$pdo = api_connect_db();
$user = api_require_auth($pdo);
$me = (int) $user['user_id'];

// Load backend friend handlers
require_once PROJECT_ROOT . 'include/handlers/friends.php';
require_once PROJECT_ROOT . 'include/handlers/log_attempt.php';

$requestData = api_request_data();
$action = isset($requestData['action']) ? $requestData['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

try {
    switch ($action) {
        case 'poll':
            api_require_method('GET');
            $response = [
                'friends' => friends_list($pdo, $me),
                'pending_in' => friends_pending_incoming($pdo, $me),
                'pending_out' => friends_pending_outgoing($pdo, $me)
            ];
            api_send(true, $response);
            break;

        case 'leaderboard':
            api_require_method('GET');
            $period = (isset($_GET['period']) && $_GET['period'] === 'all_time') ? 'all_time' : 'weekly';
            $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 50;
            $response = [
                'period' => $period,
                'leaders' => leaderboard_friends($pdo, $me, $period, $limit)
            ];
            api_send(true, $response);
            break;

        case 'search':
            api_require_method('POST');
            $q = isset($requestData['q']) ? trim((string) $requestData['q']) : '';
            if (strlen($q) < 2) {
                api_error('Query must be at least 2 characters.');
            }
            $results = friends_search_users($pdo, $me, $q, 20);
            api_send(true, ['results' => $results]);
            break;

        case 'send':
            api_require_method('POST');
            $target = isset($requestData['target_id']) ? (int) $requestData['target_id'] : 0;
            if ($target <= 0) {
                api_error('Missing target_id.');
            }
            $r = friends_send_request($pdo, $me, $target);
            friends_invalidate_pending_cache($target);
            log_attempt($pdo, $me, 'friend_request_send', "Sent request to user $target", 'friend_request', $r['request_id']);
            api_send(true, ['request_id' => $r['request_id']], 'Friend request sent.');
            break;

        case 'accept':
        case 'reject':
            api_require_method('POST');
            $reqId = isset($requestData['request_id']) ? (int) $requestData['request_id'] : 0;
            if ($reqId <= 0) {
                api_error('Missing request_id.');
            }
            friends_respond($pdo, $me, $reqId, $action);
            friends_invalidate_pending_cache($me);
            log_attempt($pdo, $me, 'friend_request_' . $action, "Request $reqId " . $action . "ed", 'friend_request', $reqId);
            api_send(true, null, 'Request ' . $action . 'ed successfully.');
            break;

        case 'cancel':
            api_require_method('POST');
            $reqId = isset($requestData['request_id']) ? (int) $requestData['request_id'] : 0;
            if ($reqId <= 0) {
                api_error('Missing request_id.');
            }
            friends_cancel($pdo, $me, $reqId);
            log_attempt($pdo, $me, 'friend_request_cancel', "Cancelled request $reqId", 'friend_request', $reqId);
            api_send(true, null, 'Request cancelled.');
            break;

        case 'unfriend':
            api_require_method('POST');
            $target = isset($requestData['target_id']) ? (int) $requestData['target_id'] : 0;
            if ($target <= 0) {
                api_error('Missing target_id.');
            }
            friends_unfriend($pdo, $me, $target);
            log_attempt($pdo, $me, 'friend_unfriend', "Unfriended $target", 'user', $target);
            api_send(true, null, 'Unfriended successfully.');
            break;

        default:
            api_error('Unknown action: ' . $action);
    }
} catch (FriendsActionException $e) {
    api_error($e->getMessage());
} catch (Throwable $e) {
    error_log('Social API Error: ' . $e->getMessage());
    api_error('An unexpected server error occurred.', 500);
}
