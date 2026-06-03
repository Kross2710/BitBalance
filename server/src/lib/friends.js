// Friends system — relationship CRUD + queries.
// Ports include/handlers/friends.php (PHP app) to the Express layer, reusing the
// same friend_request / friend_block tables and the XP/streak data already in
// the shared MySQL schema.
//
// Privacy invariants (mirrors the PHP handler):
//   - Search results never include email or real name (only user_name + avatar).
//   - friends_list / pending_* expose stats (level, streak) among friends;
//     field-level profile_visibility is NOT enforced here (PHP doesn't either).
//
// Rate limit: 20 outgoing pending requests / 24h per user, counted by
//   created_at >= NOW() - INTERVAL 1 DAY AND status = 'pending'.
import { pool, query } from '../db.js';
import { normalizeProfileImage } from './users.js';
import { ctxForStoredTz } from './tz.js';

export const FRIENDS_REQUEST_DAILY_CAP = 20;

// Thrown for user-facing validation failures (mapped to a 422 by the route).
export class FriendsActionError extends Error {}

// Cast the numeric columns SQL returns (SUM/COALESCE can arrive as strings).
function num(v) {
  return Number(v ?? 0);
}

// -----------------------------------------------------------------------------
// Relationship lookup
// -----------------------------------------------------------------------------

// Returns one of: 'self' | 'friends' | 'pending_out' | 'pending_in'
//                 | 'blocked_out' | 'blocked_in' | 'none'
export async function relationshipTo(me, other) {
  if (me === other) return 'self';

  // Blocks short-circuit everything else.
  const bk = await query(
    `SELECT blocker_id FROM friend_block
      WHERE (blocker_id = ? AND blocked_id = ?)
         OR (blocker_id = ? AND blocked_id = ?)
      LIMIT 1`,
    [me, other, other, me]
  );
  if (bk.length) {
    return Number(bk[0].blocker_id) === me ? 'blocked_out' : 'blocked_in';
  }

  const req = await query(
    `SELECT requester_id, status FROM friend_request
      WHERE (requester_id = ? AND addressee_id = ?)
         OR (requester_id = ? AND addressee_id = ?)
      ORDER BY created_at DESC
      LIMIT 1`,
    [me, other, other, me]
  );
  if (!req.length) return 'none';

  const row = req[0];
  if (row.status === 'accepted') return 'friends';
  if (row.status === 'pending') {
    return Number(row.requester_id) === me ? 'pending_out' : 'pending_in';
  }
  return 'none'; // rejected / cancelled behave like no relationship
}

// -----------------------------------------------------------------------------
// Search
// -----------------------------------------------------------------------------

export async function searchUsers(me, q, limit = 20) {
  const term = String(q ?? '').trim();
  if (term.length < 2) return [];
  const like = '%' + term.replace(/([%_])/g, '\\$1') + '%';
  const lim = Math.max(1, Math.min(Number(limit) || 20, 50));

  // Exclude self, anyone I've blocked, and anyone who blocked me.
  const users = await query(
    `SELECT u.user_id, u.user_name, u.profile_image,
            COALESCE(ux.current_level, 1)  AS current_level,
            COALESCE(us.logging_streak, 0) AS logging_streak
       FROM user u
       LEFT JOIN user_xp    ux ON ux.user_id = u.user_id
       LEFT JOIN userStatus us ON us.user_id = u.user_id
      WHERE u.user_name LIKE ? ESCAPE '\\\\'
        AND u.user_id != ?
        AND u.user_id NOT IN (
            SELECT blocked_id FROM friend_block WHERE blocker_id = ?
            UNION
            SELECT blocker_id FROM friend_block WHERE blocked_id = ?
        )
      ORDER BY (u.user_name = ?) DESC, u.user_name ASC
      LIMIT ${lim}`,
    [like, me, me, me, term]
  );

  // Annotate each row with the relationship so the UI can pick the right CTA.
  for (const u of users) {
    u.user_id = Number(u.user_id);
    u.profile_image = normalizeProfileImage(u.profile_image);
    u.current_level = num(u.current_level);
    u.logging_streak = num(u.logging_streak);
    u.relationship = await relationshipTo(me, u.user_id);
  }
  return users;
}

// -----------------------------------------------------------------------------
// Mutations
// -----------------------------------------------------------------------------

export async function sendRequest(me, other) {
  if (me === other) throw new FriendsActionError('Cannot friend yourself.');

  const rel = await relationshipTo(me, other);
  if (rel === 'friends') throw new FriendsActionError('Already friends.');
  if (rel === 'pending_out') throw new FriendsActionError('Request already pending.');
  if (rel === 'pending_in')
    throw new FriendsActionError('They already sent you a request — accept it instead.');
  if (rel.startsWith('blocked')) throw new FriendsActionError('Cannot send request.');

  // Rate limit: outgoing pending requests in the last 24h.
  const cap = await query(
    `SELECT COUNT(*) AS n FROM friend_request
      WHERE requester_id = ? AND status = 'pending'
        AND created_at >= NOW() - INTERVAL 1 DAY`,
    [me]
  );
  if (num(cap[0].n) >= FRIENDS_REQUEST_DAILY_CAP) {
    throw new FriendsActionError('Daily friend-request limit reached. Try again tomorrow.');
  }

  // Upsert: a rejected/cancelled row may exist; revive it to pending rather than
  // violate the (requester_id, addressee_id) unique key.
  const existing = await query(
    `SELECT request_id FROM friend_request
      WHERE requester_id = ? AND addressee_id = ? LIMIT 1`,
    [me, other]
  );
  if (existing.length) {
    const reqId = Number(existing[0].request_id);
    await query(
      `UPDATE friend_request
          SET status = 'pending', created_at = NOW(), responded_at = NULL
        WHERE request_id = ?`,
      [reqId]
    );
    return { request_id: reqId };
  }

  const [result] = await pool.query(
    `INSERT INTO friend_request (requester_id, addressee_id, status)
     VALUES (?, ?, 'pending')`,
    [me, other]
  );
  return { request_id: Number(result.insertId) };
}

export async function respond(me, requestId, action) {
  if (action !== 'accept' && action !== 'reject') {
    throw new FriendsActionError('Invalid action.');
  }
  const rows = await query(
    `SELECT addressee_id, status FROM friend_request WHERE request_id = ? LIMIT 1`,
    [requestId]
  );
  const row = rows[0];
  if (!row) throw new FriendsActionError('Request not found.');
  if (Number(row.addressee_id) !== me) throw new FriendsActionError('Not authorised.');
  if (row.status !== 'pending') throw new FriendsActionError('Request is not pending.');

  const newStatus = action === 'accept' ? 'accepted' : 'rejected';
  await query(
    `UPDATE friend_request SET status = ?, responded_at = NOW() WHERE request_id = ?`,
    [newStatus, requestId]
  );
}

export async function cancel(me, requestId) {
  const rows = await query(
    `SELECT requester_id, status FROM friend_request WHERE request_id = ? LIMIT 1`,
    [requestId]
  );
  const row = rows[0];
  if (!row) throw new FriendsActionError('Request not found.');
  if (Number(row.requester_id) !== me) throw new FriendsActionError('Not authorised.');
  if (row.status !== 'pending') throw new FriendsActionError('Request is not pending.');

  await query(
    `UPDATE friend_request SET status = 'cancelled', responded_at = NOW() WHERE request_id = ?`,
    [requestId]
  );
}

export async function unfriend(me, other) {
  const [result] = await pool.query(
    `UPDATE friend_request
        SET status = 'cancelled', responded_at = NOW()
      WHERE status = 'accepted'
        AND ((requester_id = ? AND addressee_id = ?)
          OR (requester_id = ? AND addressee_id = ?))`,
    [me, other, other, me]
  );
  if (result.affectedRows === 0) throw new FriendsActionError('Not friends.');
}

// -----------------------------------------------------------------------------
// Queries
// -----------------------------------------------------------------------------

// Friends with public stats, sorted by weekly XP then friendship date.
export async function friendsList(me) {
  const rows = await query(
    `SELECT
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
      ORDER BY weekly_xp DESC, friends_since DESC`,
    [me, me, me]
  );
  return rows.map((r) => ({
    user_id: Number(r.user_id),
    user_name: r.user_name,
    profile_image: normalizeProfileImage(r.profile_image),
    current_level: num(r.current_level),
    total_xp: num(r.total_xp),
    logging_streak: num(r.logging_streak),
    weekly_xp: num(r.weekly_xp),
    request_id: Number(r.request_id),
    friends_since: r.friends_since,
  }));
}

// Leaderboard for the signed-in user + accepted friends.
//   period: 'weekly' (last 7 days of xp_event) | 'all_time' (total_xp)
export async function leaderboard(me, period = 'weekly', limit = 50) {
  const p = period === 'all_time' ? 'all_time' : 'weekly';
  const lim = Math.max(1, Math.min(Number(limit) || 50, 500));
  const orderSql =
    p === 'all_time'
      ? 'total_xp DESC, logging_streak DESC, u.user_name ASC'
      : 'weekly_xp DESC, total_xp DESC, logging_streak DESC, u.user_name ASC';

  const rows = await query(
    `SELECT
        u.user_id, u.user_name, u.profile_image,
        COALESCE(ux.current_level, 1)  AS current_level,
        COALESCE(ux.total_xp, 0)       AS total_xp,
        COALESCE(us.logging_streak, 0) AS logging_streak,
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
      ORDER BY ${orderSql}
      LIMIT ${lim}`,
    [me, me, me]
  );

  return rows.map((r, i) => {
    const total_xp = num(r.total_xp);
    const weekly_xp = num(r.weekly_xp);
    const user_id = Number(r.user_id);
    return {
      user_id,
      user_name: r.user_name,
      profile_image: normalizeProfileImage(r.profile_image),
      current_level: num(r.current_level),
      total_xp,
      logging_streak: num(r.logging_streak),
      weekly_xp,
      score_xp: p === 'all_time' ? total_xp : weekly_xp,
      rank: i + 1,
      is_current_user: user_id === me,
    };
  });
}

export async function pendingIncoming(me) {
  const rows = await query(
    `SELECT fr.request_id, fr.created_at,
            u.user_id, u.user_name, u.profile_image,
            COALESCE(ux.current_level, 1)  AS current_level,
            COALESCE(us.logging_streak, 0) AS logging_streak
       FROM friend_request fr
       JOIN user u ON u.user_id = fr.requester_id
       LEFT JOIN user_xp    ux ON ux.user_id = u.user_id
       LEFT JOIN userStatus us ON us.user_id = u.user_id
      WHERE fr.addressee_id = ? AND fr.status = 'pending'
      ORDER BY fr.created_at DESC`,
    [me]
  );
  return rows.map((r) => ({
    request_id: Number(r.request_id),
    created_at: r.created_at,
    user_id: Number(r.user_id),
    user_name: r.user_name,
    profile_image: normalizeProfileImage(r.profile_image),
    current_level: num(r.current_level),
    logging_streak: num(r.logging_streak),
  }));
}

export async function pendingOutgoing(me) {
  const rows = await query(
    `SELECT fr.request_id, fr.created_at,
            u.user_id, u.user_name, u.profile_image,
            COALESCE(ux.current_level, 1) AS current_level
       FROM friend_request fr
       JOIN user u ON u.user_id = fr.addressee_id
       LEFT JOIN user_xp ux ON ux.user_id = u.user_id
      WHERE fr.requester_id = ? AND fr.status = 'pending'
      ORDER BY fr.created_at DESC`,
    [me]
  );
  return rows.map((r) => ({
    request_id: Number(r.request_id),
    created_at: r.created_at,
    user_id: Number(r.user_id),
    user_name: r.user_name,
    profile_image: normalizeProfileImage(r.profile_image),
    current_level: num(r.current_level),
  }));
}

// Count of incoming pending requests — drives the nav badge.
export async function pendingCountIncoming(me) {
  const rows = await query(
    `SELECT COUNT(*) AS n FROM friend_request WHERE addressee_id = ? AND status = 'pending'`,
    [me]
  );
  return num(rows[0].n);
}

// -----------------------------------------------------------------------------
// Public profile peek (the /friends profile sheet)
// -----------------------------------------------------------------------------

// Non-sensitive "flex" profile of another user, gated by their profile_visibility.
// Returns null (→ 404) for unknown or blocked users so we never reveal them.
// Always returns a minimal card; the rich block (streak/xp/badges/favorite/bio)
// is attached ONLY when the viewer is allowed to see it. Never exposes calories,
// macros, weight, goal, email, or real name.
export async function publicProfile(me, targetId) {
  const target = Number(targetId);
  if (!Number.isFinite(target) || target <= 0) return null;

  const rel = await relationshipTo(me, target);
  if (rel === 'blocked_in' || rel === 'blocked_out') return null; // don't reveal

  const rows = await query(
    `SELECT u.user_id, u.user_name, u.profile_image,
            COALESCE(ux.current_level, 1)           AS current_level,
            COALESCE(ux.total_xp, 0)                AS total_xp,
            COALESCE(us.logging_streak, 0)          AS logging_streak,
            COALESCE(us.longest_logging_streak, 0)  AS longest_streak,
            us.profile_bio,
            COALESCE(us.profile_visibility, 'friends')   AS visibility,
            COALESCE(us.show_favorite_food, 1)           AS show_favorite_food,
            COALESCE(us.time_zone, 'Asia/Ho_Chi_Minh')   AS time_zone
       FROM user u
       LEFT JOIN user_xp    ux ON ux.user_id = u.user_id
       LEFT JOIN userStatus us ON us.user_id = u.user_id
      WHERE u.user_id = ?
      LIMIT 1`,
    [target]
  );
  if (!rows.length) return null;
  const r = rows[0];
  const visibility = r.visibility;

  const card = {
    user_id: Number(r.user_id),
    user_name: r.user_name,
    profile_image: normalizeProfileImage(r.profile_image),
    current_level: num(r.current_level),
    relationship: rel,
    visibility,
    can_view_full:
      target === me || visibility === 'public' || (visibility === 'friends' && rel === 'friends'),
  };
  if (!card.can_view_full) return card;

  // Rich block. achievementsProgress is dynamically imported to sidestep the
  // friends <-> achievements circular import (achievements.js imports leaderboard).
  const { achievementsProgress } = await import('./achievements.js');
  const { shift } = ctxForStoredTz(r.time_zone);
  const prog = await achievementsProgress(target, shift);

  const weekly = await query(
    `SELECT COALESCE(SUM(amount), 0) AS n FROM xp_event
      WHERE user_id = ? AND created_at >= NOW() - INTERVAL 7 DAY`,
    [target]
  );

  const topBadges = prog.achievements
    .filter((a) => a.level > 0)
    .sort((a, b) => b.level - a.level)
    .slice(0, 6)
    .map((a) => ({ id: a.id, name: a.name, icon: a.icon, tone: a.tone, level: a.level }));

  // Favorite food is opt-out per user. Dedicated query gives a clean numeric count.
  let favorite_food = null;
  if (Number(r.show_favorite_food) === 1) {
    const fav = await query(
      `SELECT food_item, COUNT(*) AS c FROM intakeLog WHERE user_id = ?
        GROUP BY food_item ORDER BY c DESC, food_item ASC LIMIT 1`,
      [target]
    );
    if (fav.length && fav[0].food_item) favorite_food = { food: fav[0].food_item, logs: num(fav[0].c) };
  }

  return {
    ...card,
    bio: r.profile_bio ?? '',
    total_xp: num(r.total_xp),
    weekly_xp: num(weekly[0]?.n),
    current_streak: num(r.logging_streak),
    longest_streak: num(r.longest_streak),
    unlocked: prog.summary.unlocked,
    total_achievements: prog.summary.total_achievements,
    top_badges: topBadges,
    favorite_food,
  };
}
