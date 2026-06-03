// Social / Friends routes — port api/social/action.php to REST endpoints.
//   GET  /api/social/poll           → { friends, pending_in, pending_out }
//   GET  /api/social/leaderboard    → { period, leaders }   (?period=weekly|all_time&limit=)
//   GET  /api/social/pending-count  → { count }             (nav badge)
//   POST /api/social/search         → { results }           ({ q })
//   POST /api/social/send           → { request_id }        ({ target_id })
//   POST /api/social/accept|reject  → null                  ({ request_id })
//   POST /api/social/cancel         → null                  ({ request_id })
//   POST /api/social/unfriend       → null                  ({ target_id })
//
// Mutations rely on the session cookie (SameSite=lax) like every other migrated
// route; CSRF is a global TODO tracked in MIGRATION.md, not a per-route concern.
// The legacy log_attempt() audit write is intentionally skipped (not ported).
import { Router } from 'express';
import { requireAuth } from '../middleware/auth.js';
import {
  FriendsActionError,
  relationshipTo,
  searchUsers,
  sendRequest,
  respond,
  cancel,
  unfriend,
  friendsList,
  leaderboard,
  pendingIncoming,
  pendingOutgoing,
  pendingCountIncoming,
  publicProfile,
} from '../lib/friends.js';

const router = Router();

// Map a thrown FriendsActionError to a 422; anything else bubbles to the
// global error handler as a 500.
function handle(fn) {
  return async (req, res, next) => {
    try {
      await fn(req, res);
    } catch (err) {
      if (err instanceof FriendsActionError) {
        return res.status(422).json({ ok: false, data: null, message: err.message });
      }
      next(err);
    }
  };
}

const ok = (res, data, message = null) => res.json({ ok: true, data, message });
const intParam = (v) => {
  const n = parseInt(v, 10);
  return Number.isFinite(n) ? n : 0;
};

router.get(
  '/poll',
  requireAuth,
  handle(async (req, res) => {
    const me = req.user.user_id;
    const [friends, pending_in, pending_out] = await Promise.all([
      friendsList(me),
      pendingIncoming(me),
      pendingOutgoing(me),
    ]);
    ok(res, { friends, pending_in, pending_out });
  })
);

router.get(
  '/leaderboard',
  requireAuth,
  handle(async (req, res) => {
    const period = req.query.period === 'all_time' ? 'all_time' : 'weekly';
    const limit = intParam(req.query.limit) || 50;
    const leaders = await leaderboard(req.user.user_id, period, limit);
    ok(res, { period, leaders });
  })
);

router.get(
  '/pending-count',
  requireAuth,
  handle(async (req, res) => {
    ok(res, { count: await pendingCountIncoming(req.user.user_id) });
  })
);

router.post(
  '/search',
  requireAuth,
  handle(async (req, res) => {
    const q = String(req.body?.q ?? '').trim();
    if (q.length < 2) {
      return res.status(422).json({ ok: false, data: null, message: 'Query must be at least 2 characters.' });
    }
    ok(res, { results: await searchUsers(req.user.user_id, q, 20) });
  })
);

router.post(
  '/send',
  requireAuth,
  handle(async (req, res) => {
    const target = intParam(req.body?.target_id);
    if (target <= 0) return res.status(422).json({ ok: false, data: null, message: 'Missing target_id.' });
    const r = await sendRequest(req.user.user_id, target);
    ok(res, { request_id: r.request_id }, 'Friend request sent.');
  })
);

for (const action of ['accept', 'reject']) {
  router.post(
    `/${action}`,
    requireAuth,
    handle(async (req, res) => {
      const reqId = intParam(req.body?.request_id);
      if (reqId <= 0) return res.status(422).json({ ok: false, data: null, message: 'Missing request_id.' });
      await respond(req.user.user_id, reqId, action);
      ok(res, null, `Request ${action}ed successfully.`);
    })
  );
}

router.post(
  '/cancel',
  requireAuth,
  handle(async (req, res) => {
    const reqId = intParam(req.body?.request_id);
    if (reqId <= 0) return res.status(422).json({ ok: false, data: null, message: 'Missing request_id.' });
    await cancel(req.user.user_id, reqId);
    ok(res, null, 'Request cancelled.');
  })
);

router.post(
  '/unfriend',
  requireAuth,
  handle(async (req, res) => {
    const target = intParam(req.body?.target_id);
    if (target <= 0) return res.status(422).json({ ok: false, data: null, message: 'Missing target_id.' });
    await unfriend(req.user.user_id, target);
    ok(res, null, 'Unfriended successfully.');
  })
);

// Relationship probe (handy for a profile page CTA). Not in the PHP action list
// but trivially useful; safe and read-only.
router.get(
  '/relationship/:userId',
  requireAuth,
  handle(async (req, res) => {
    const other = intParam(req.params.userId);
    if (other <= 0) return res.status(422).json({ ok: false, data: null, message: 'Invalid user id.' });
    ok(res, { relationship: await relationshipTo(req.user.user_id, other) });
  })
);

// Public profile peek — non-sensitive "flex" data, gated by profile_visibility.
// Unknown / blocked / not-permitted users return a minimal card (or 404).
router.get(
  '/profile/:userId',
  requireAuth,
  handle(async (req, res) => {
    const other = intParam(req.params.userId);
    if (other <= 0) return res.status(422).json({ ok: false, data: null, message: 'Invalid user id.' });
    const profile = await publicProfile(req.user.user_id, other);
    if (!profile) return res.status(404).json({ ok: false, data: null, message: 'User not found.' });
    ok(res, profile);
  })
);

export default router;
