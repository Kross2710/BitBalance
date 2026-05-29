<?php
/**
 * Friends system — relationship CRUD + queries.
 *
 * Privacy invariants:
 *   - Search results never include email or real name (only user_name + avatar).
 *   - friend_list / pending_* return stats (level, streak) regardless of
 *     profile_visibility — those fields are always public among friends.
 *     Field-level privacy on the profile page itself is enforced by the
 *     (future) profile rework, not here.
 *
 * Rate limit: 20 outgoing pending requests / 24h per user. Counted by
 *   `created_at >= NOW() - INTERVAL 1 DAY AND status = 'pending'`.
 */

require_once __DIR__ . '/../db_config.php';

const FRIENDS_REQUEST_DAILY_CAP = 20;

// -----------------------------------------------------------------------------
// Relationship lookup
// -----------------------------------------------------------------------------

/**
 * Returns one of:
 *   'self'        — same user
 *   'friends'     — accepted in either direction
 *   'pending_out' — I sent a pending request to them
 *   'pending_in'  — they sent a pending request to me
 *   'blocked_out' — I blocked them
 *   'blocked_in'  — they blocked me
 *   'none'        — no relationship
 */
function friends_relationship_to(PDO $pdo, int $me, int $other): string
{
    if ($me === $other) return 'self';

    // Blocks first — they short-circuit everything else.
    $bk = $pdo->prepare(
        "SELECT blocker_id FROM friend_block
         WHERE (blocker_id = ? AND blocked_id = ?)
            OR (blocker_id = ? AND blocked_id = ?)
         LIMIT 1"
    );
    $bk->execute([$me, $other, $other, $me]);
    $blocker = $bk->fetchColumn();
    if ($blocker !== false) {
        return ((int) $blocker === $me) ? 'blocked_out' : 'blocked_in';
    }

    $req = $pdo->prepare(
        "SELECT requester_id, status FROM friend_request
         WHERE (requester_id = ? AND addressee_id = ?)
            OR (requester_id = ? AND addressee_id = ?)
         ORDER BY created_at DESC
         LIMIT 1"
    );
    $req->execute([$me, $other, $other, $me]);
    $row = $req->fetch(PDO::FETCH_ASSOC);
    if (!$row) return 'none';

    if ($row['status'] === 'accepted') return 'friends';
    if ($row['status'] === 'pending') {
        return ((int) $row['requester_id'] === $me) ? 'pending_out' : 'pending_in';
    }
    return 'none'; // rejected / cancelled → behave like no relationship
}

// -----------------------------------------------------------------------------
// Search
// -----------------------------------------------------------------------------

function friends_search_users(PDO $pdo, int $me, string $q, int $limit = 20): array
{
    $q = trim($q);
    if ($q === '' || strlen($q) < 2) return [];
    $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';

    // Exclude: self, anyone I've blocked, anyone who blocked me.
    $stmt = $pdo->prepare(
        "SELECT u.user_id, u.user_name, u.profile_image,
                COALESCE(ux.current_level, 1) AS current_level,
                COALESCE(us.logging_streak, 0) AS logging_streak
         FROM user u
         LEFT JOIN user_xp ux  ON ux.user_id = u.user_id
         LEFT JOIN userStatus us ON us.user_id = u.user_id
         WHERE u.user_name LIKE ? ESCAPE '\\\\'
           AND u.user_id != ?
           AND u.user_id NOT IN (
               SELECT blocked_id FROM friend_block WHERE blocker_id = ?
               UNION
               SELECT blocker_id FROM friend_block WHERE blocked_id = ?
           )
         ORDER BY (u.user_name = ?) DESC, u.user_name ASC
         LIMIT $limit"
    );
    $stmt->execute([$like, $me, $me, $me, $q]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Annotate each row with the current relationship so UI can pick the right CTA.
    foreach ($users as &$u) {
        $u['relationship'] = friends_relationship_to($pdo, $me, (int) $u['user_id']);
    }
    return $users;
}

// -----------------------------------------------------------------------------
// Mutations
// -----------------------------------------------------------------------------

class FriendsActionException extends RuntimeException {}

function friends_send_request(PDO $pdo, int $me, int $other): array
{
    if ($me === $other) {
        throw new FriendsActionException('Cannot friend yourself.');
    }

    $rel = friends_relationship_to($pdo, $me, $other);
    if ($rel === 'friends')      throw new FriendsActionException('Already friends.');
    if ($rel === 'pending_out')  throw new FriendsActionException('Request already pending.');
    if ($rel === 'pending_in')   throw new FriendsActionException('They already sent you a request — accept it instead.');
    if (strpos($rel, 'blocked') === 0) throw new FriendsActionException('Cannot send request.');

    // Rate limit
    $cap = $pdo->prepare(
        "SELECT COUNT(*) FROM friend_request
         WHERE requester_id = ? AND status = 'pending'
           AND created_at >= NOW() - INTERVAL 1 DAY"
    );
    $cap->execute([$me]);
    if ((int) $cap->fetchColumn() >= FRIENDS_REQUEST_DAILY_CAP) {
        throw new FriendsActionException('Daily friend-request limit reached. Try again tomorrow.');
    }

    // Upsert pattern: a row may exist with status rejected/cancelled from before.
    // We update it back to pending rather than violate the (requester, addressee) unique key.
    $existing = $pdo->prepare(
        "SELECT request_id, status FROM friend_request
         WHERE requester_id = ? AND addressee_id = ? LIMIT 1"
    );
    $existing->execute([$me, $other]);
    $row = $existing->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $upd = $pdo->prepare(
            "UPDATE friend_request
             SET status = 'pending', created_at = NOW(), responded_at = NULL
             WHERE request_id = ?"
        );
        $upd->execute([$row['request_id']]);
        $reqId = (int) $row['request_id'];
    } else {
        $ins = $pdo->prepare(
            "INSERT INTO friend_request (requester_id, addressee_id, status)
             VALUES (?, ?, 'pending')"
        );
        $ins->execute([$me, $other]);
        $reqId = (int) $pdo->lastInsertId();
    }

    return ['request_id' => $reqId];
}

function friends_respond(PDO $pdo, int $me, int $requestId, string $action): void
{
    if (!in_array($action, ['accept', 'reject'], true)) {
        throw new FriendsActionException('Invalid action.');
    }
    $req = $pdo->prepare(
        "SELECT addressee_id, status FROM friend_request WHERE request_id = ? LIMIT 1"
    );
    $req->execute([$requestId]);
    $row = $req->fetch(PDO::FETCH_ASSOC);
    if (!$row)                                throw new FriendsActionException('Request not found.');
    if ((int) $row['addressee_id'] !== $me)   throw new FriendsActionException('Not authorised.');
    if ($row['status'] !== 'pending')         throw new FriendsActionException('Request is not pending.');

    $newStatus = $action === 'accept' ? 'accepted' : 'rejected';
    $upd = $pdo->prepare(
        "UPDATE friend_request SET status = ?, responded_at = NOW() WHERE request_id = ?"
    );
    $upd->execute([$newStatus, $requestId]);
}

function friends_cancel(PDO $pdo, int $me, int $requestId): void
{
    $req = $pdo->prepare(
        "SELECT requester_id, status FROM friend_request WHERE request_id = ? LIMIT 1"
    );
    $req->execute([$requestId]);
    $row = $req->fetch(PDO::FETCH_ASSOC);
    if (!$row)                              throw new FriendsActionException('Request not found.');
    if ((int) $row['requester_id'] !== $me) throw new FriendsActionException('Not authorised.');
    if ($row['status'] !== 'pending')       throw new FriendsActionException('Request is not pending.');

    $upd = $pdo->prepare(
        "UPDATE friend_request SET status = 'cancelled', responded_at = NOW() WHERE request_id = ?"
    );
    $upd->execute([$requestId]);
}

function friends_unfriend(PDO $pdo, int $me, int $other): void
{
    $upd = $pdo->prepare(
        "UPDATE friend_request
         SET status = 'cancelled', responded_at = NOW()
         WHERE status = 'accepted'
           AND ((requester_id = ? AND addressee_id = ?)
             OR (requester_id = ? AND addressee_id = ?))"
    );
    $upd->execute([$me, $other, $other, $me]);
    if ($upd->rowCount() === 0) {
        throw new FriendsActionException('Not friends.');
    }
}

// -----------------------------------------------------------------------------
// Queries
// -----------------------------------------------------------------------------

/**
 * Friends with their public stats. Sorted by weekly XP DESC then by friendship date.
 */
function friends_list(PDO $pdo, int $me): array
{
    $stmt = $pdo->prepare(
        "SELECT
            u.user_id, u.user_name, u.profile_image,
            COALESCE(ux.current_level, 1)  AS current_level,
            COALESCE(ux.total_xp, 0)       AS total_xp,
            COALESCE(us.logging_streak, 0) AS logging_streak,
            COALESCE((
                SELECT SUM(xe.amount) FROM xp_event xe
                WHERE xe.user_id = u.user_id
                  AND xe.created_at >= NOW() - INTERVAL 7 DAY
            ), 0) AS weekly_xp,
            fr.request_id,
            GREATEST(fr.responded_at, fr.created_at) AS friends_since
         FROM friend_request fr
         JOIN user u ON u.user_id = CASE WHEN fr.requester_id = ? THEN fr.addressee_id ELSE fr.requester_id END
         LEFT JOIN user_xp    ux ON ux.user_id = u.user_id
         LEFT JOIN userStatus us ON us.user_id = u.user_id
         WHERE fr.status = 'accepted'
           AND (fr.requester_id = ? OR fr.addressee_id = ?)
         ORDER BY weekly_xp DESC, friends_since DESC"
    );
    $stmt->execute([$me, $me, $me]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Leaderboard for the signed-in user and accepted friends.
 *
 * $period accepts:
 *   - weekly   : last 7 days of xp_event
 *   - all_time : total_xp from user_xp
 */
function leaderboard_friends(PDO $pdo, int $me, string $period = 'weekly', int $limit = 50): array
{
    $period = $period === 'all_time' ? 'all_time' : 'weekly';
    $limit = max(1, min($limit, 500));

    $orderSql = $period === 'all_time'
        ? 'total_xp DESC, logging_streak DESC, u.user_name ASC'
        : 'weekly_xp DESC, total_xp DESC, logging_streak DESC, u.user_name ASC';

    $stmt = $pdo->prepare(
        "SELECT
            u.user_id, u.user_name, u.profile_image,
            COALESCE(ux.current_level, 1)   AS current_level,
            COALESCE(ux.total_xp, 0)        AS total_xp,
            COALESCE(us.logging_streak, 0)  AS logging_streak,
            COALESCE((
                SELECT SUM(xe.amount) FROM xp_event xe
                WHERE xe.user_id = u.user_id
                  AND xe.created_at >= NOW() - INTERVAL 7 DAY
            ), 0) AS weekly_xp
         FROM user u
         LEFT JOIN user_xp    ux ON ux.user_id = u.user_id
         LEFT JOIN userStatus us ON us.user_id = u.user_id
         WHERE u.user_id = ?
            OR u.user_id IN (
                SELECT CASE WHEN requester_id = ? THEN addressee_id ELSE requester_id END
                FROM friend_request
                WHERE status = 'accepted'
                  AND (? IN (requester_id, addressee_id))
            )
         ORDER BY $orderSql
         LIMIT $limit"
    );
    $stmt->execute([$me, $me, $me]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $rank = 1;
    foreach ($rows as &$row) {
        $row['user_id'] = (int) $row['user_id'];
        $row['current_level'] = (int) $row['current_level'];
        $row['total_xp'] = (int) $row['total_xp'];
        $row['logging_streak'] = (int) $row['logging_streak'];
        $row['weekly_xp'] = (int) $row['weekly_xp'];
        $row['score_xp'] = $period === 'all_time' ? $row['total_xp'] : $row['weekly_xp'];
        $row['rank'] = $rank++;
        $row['is_current_user'] = $row['user_id'] === $me;
    }
    unset($row);

    return $rows;
}

function friends_pending_incoming(PDO $pdo, int $me): array
{
    $stmt = $pdo->prepare(
        "SELECT fr.request_id, fr.created_at,
                u.user_id, u.user_name, u.profile_image,
                COALESCE(ux.current_level, 1)  AS current_level,
                COALESCE(us.logging_streak, 0) AS logging_streak
         FROM friend_request fr
         JOIN user u ON u.user_id = fr.requester_id
         LEFT JOIN user_xp    ux ON ux.user_id = u.user_id
         LEFT JOIN userStatus us ON us.user_id = u.user_id
         WHERE fr.addressee_id = ? AND fr.status = 'pending'
         ORDER BY fr.created_at DESC"
    );
    $stmt->execute([$me]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function friends_pending_outgoing(PDO $pdo, int $me): array
{
    $stmt = $pdo->prepare(
        "SELECT fr.request_id, fr.created_at,
                u.user_id, u.user_name, u.profile_image,
                COALESCE(ux.current_level, 1)  AS current_level
         FROM friend_request fr
         JOIN user u ON u.user_id = fr.addressee_id
         LEFT JOIN user_xp ux ON ux.user_id = u.user_id
         WHERE fr.requester_id = ? AND fr.status = 'pending'
         ORDER BY fr.created_at DESC"
    );
    $stmt->execute([$me]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Quick count for the sidebar badge. Cached in session for 60s to avoid
 * hitting the DB on every page load.
 */
function friends_pending_count_incoming(PDO $pdo, int $me): int
{
    $key = 'friends_pending_count_' . $me;
    $ts  = $key . '_ts';
    $now = time();
    if (isset($_SESSION[$ts]) && $now - $_SESSION[$ts] < 60) {
        return (int) ($_SESSION[$key] ?? 0);
    }
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM friend_request WHERE addressee_id = ? AND status = 'pending'"
    );
    $stmt->execute([$me]);
    $count = (int) $stmt->fetchColumn();
    $_SESSION[$key] = $count;
    $_SESSION[$ts]  = $now;
    return $count;
}

/**
 * Invalidate the cached badge count. Call after any mutation that changes
 * pending state (send / accept / reject / cancel).
 */
function friends_invalidate_pending_cache(int $me): void
{
    unset($_SESSION['friends_pending_count_' . $me]);
    unset($_SESSION['friends_pending_count_' . $me . '_ts']);
}
