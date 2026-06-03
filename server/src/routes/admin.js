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
  createUser,
  updateUser,
  setUserStatus,
  setUserPassword,
  unlockUser,
  getActivityLogs,
  previewPrune,
  pruneLogs,
  listBarcodeCache,
  getBarcodeDetail,
  evictBarcode,
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

// POST /api/admin/users → create a new user (admin-only). Declared before the
// /:id routes (different method, but keep the user-collection handlers together).
router.post(
  '/users',
  handle(async (req, res) => {
    const data = await createUser(req.user.user_id, req.body || {});
    ok(res, data, 'User created.');
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

// POST /api/admin/users/:id/password → set a new password (account recovery).
// Declared BEFORE /:action so "password" isn't swallowed by the action param.
router.post(
  '/users/:id/password',
  handle(async (req, res) => {
    const id = intParam(req.params.id);
    await setUserPassword(req.user.user_id, id, req.body?.password, req.body?.confirm_password);
    ok(res, await getUserDetail(id), 'Password updated.');
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

// GET /api/admin/logs?q=&action=&page= → paginated activity_log + action types.
router.get(
  '/logs',
  handle(async (req, res) => {
    const { q = '', action = '', page = '1' } = req.query;
    ok(res, await getActivityLogs({ q, action, page }));
  })
);

// GET /api/admin/logs/prune-preview?days= → count rows a prune would remove.
router.get(
  '/logs/prune-preview',
  handle(async (req, res) => {
    ok(res, await previewPrune(req.query.days ?? 30));
  })
);

// POST /api/admin/logs/prune → delete logs older than { days } (default 30).
router.post(
  '/logs/prune',
  handle(async (req, res) => {
    const days = req.body?.days ?? 30;
    ok(res, await pruneLogs(req.user.user_id, days), 'Logs pruned.');
  })
);

// GET /api/admin/barcodes?q=&source=&sort=&page= → paginated barcode cache + stats.
router.get(
  '/barcodes',
  handle(async (req, res) => {
    const { q = '', source = '', sort = 'popular', page = '1' } = req.query;
    ok(res, await listBarcodeCache({ q, source, sort, page }));
  })
);

// GET /api/admin/barcodes/:barcode → one cached product + its recent scans.
router.get(
  '/barcodes/:barcode',
  handle(async (req, res) => {
    ok(res, await getBarcodeDetail(req.params.barcode));
  })
);

// POST /api/admin/barcodes/:barcode/evict → drop a cached product (next scan
// re-fetches from OpenFoodFacts). POST (not DELETE) to reuse the SPA api client,
// which only sends GET/POST/PATCH with the CSRF header.
router.post(
  '/barcodes/:barcode/evict',
  handle(async (req, res) => {
    ok(res, await evictBarcode(req.user.user_id, req.params.barcode), 'Cached product evicted.');
  })
);

export default router;
