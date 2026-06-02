// Admin panel routes — the Vue/Express port of the PHP admin/ panel.
//
// Authorization: the ENTIRE /api/admin surface is admin-only, so the guard is
// applied once at the mount point in index.js (`requireAuth, requireAdmin`) —
// unlike the PT routes, which guard per-endpoint because clients also hit some
// of them. Every handler here can therefore assume req.user is an active admin.
//
// CSRF: admin mutations (Phase 1+) will additionally require a custom request
// header set by the SPA's api client, so a cross-site form POST can't trigger
// them — the session cookie is sameSite=lax, which alone is not enough for
// state-changing requests. Phase 0 ships only the read-only summary below.
import { Router } from 'express';
import { query } from '../db.js';

const router = Router();
const ok = (res, data, message = null) => res.json({ ok: true, data, message });

// GET /api/admin/summary → headline counts for the admin landing page.
// Read-only; proves the guard + DB wiring end to end (Phase 0 foundation).
router.get('/summary', async (req, res, next) => {
  try {
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
  } catch (err) {
    next(err);
  }
});

export default router;
