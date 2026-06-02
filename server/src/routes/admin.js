// Admin panel routes — the Vue/Express port of the PHP admin/ panel.
//
// Authorization: the ENTIRE /api/admin surface is admin-only, so the guard is
// applied once at the mount point in index.js (`requireAuth, requireAdmin`) —
// unlike the PT routes, which guard per-endpoint because clients also hit some
// of them. Every handler here can therefore assume req.user is an active admin.
//
// CSRF: state-changing requests additionally require the X-Requested-With header
// (the SPA's api client sends it on every call). A cross-site <form> cannot set a
// custom header, and our CORS only echoes it for our own origin, so this blocks
// cross-site forgery — the session cookie is sameSite=lax, which alone is not
// enough for mutations.
import { Router } from 'express';
import { query } from '../db.js';
import {
  AdminActionError,
  listUsers,
  getUserDetail,
  updateUser,
  setUserStatus,
  unlockUser,
} from '../lib/admin.js';

const router = Router();
const ok = (res, data, message = null) => res.json({ ok: true, data, message });
const intParam = (v) => {
  const n = parseInt(v, 10);
  return Number.isFinite(n) ? n : 0;
};

// Maps AdminActionError -> 422 (validation/business failure); everything else
// bubbles to the global error handler as a 500.
function handle(fn) {
  return async (req, res, next) => {
    try {
      await fn(req, res);
    } catch (err) {
      if (err instanceof AdminActionError) {
        return res.status(422).json({ ok: false, data: null, message: err.message });
      }
      next(err);
    }
  };
}

// CSRF defence for mutations — see the header note.
router.use((req, res, next) => {
  if (req.method !== 'GET' && req.method !== 'HEAD' && req.get('X-Requested-With') !== 'XMLHttpRequest') {
    return res.status(403).json({ ok: false, data: null, message: 'Invalid request origin.' });
  }
  next();
});

// GET /api/admin/summary → headline counts for the admin landing page.
router.get(
  '/summary',
  handle(async (req, res) => {
    const [usersRows, adminRows, logRows] = await Promise.all([
      query('SELECT COUNT(*) AS n FROM user'),
      query("SELECT COUNT(*) AS n FROM user WHERE role = 'admin'"),
      query('SELECT COUNT(*) AS n FROM activity_log'),
    ]);
    ok(res, {
      users: usersRows[0].n,
      admins: adminRows[0].n,
      activity_log_rows: logRows[0].n,
    });
  })
);

// GET /api/admin/users?q=&role=&status=&page= → paginated user list.
router.get(
  '/users',
  handle(async (req, res) => {
    const { q = '', role = '', status = '', page = '1' } = req.query;
    ok(res, await listUsers({ q, role, status, page }));
  })
);

// GET /api/admin/users/:id → one user's full detail.
router.get(
  '/users/:id',
  handle(async (req, res) => {
    ok(res, await getUserDetail(intParam(req.params.id)));
  })
);

// PATCH /api/admin/users/:id → edit profile / role / status.
router.patch(
  '/users/:id',
  handle(async (req, res) => {
    const data = await updateUser(req.user.user_id, intParam(req.params.id), req.body || {});
    ok(res, data, 'User updated.');
  })
);

// POST /api/admin/users/:id/unlock → clear lockout (thin account recovery).
// Declared BEFORE /:action so "unlock" isn't swallowed by the action param.
router.post(
  '/users/:id/unlock',
  handle(async (req, res) => {
    const id = intParam(req.params.id);
    await unlockUser(req.user.user_id, id);
    ok(res, await getUserDetail(id), 'Account unlocked.');
  })
);

// POST /api/admin/users/:id/:action → ban | unban | archive | restore.
router.post(
  '/users/:id/:action',
  handle(async (req, res) => {
    const id = intParam(req.params.id);
    await setUserStatus(req.user.user_id, id, req.params.action);
    ok(res, await getUserDetail(id), 'Status updated.');
  })
);

export default router;
