// Auth guard for protected routes. Ports api_require_auth() from
// api/_bootstrap.php: 401 when no valid session, 403 when the account is
// no longer active. On success it attaches the fresh DB row to req.user.
import { currentUserRow } from '../lib/users.js';

export async function requireAuth(req, res, next) {
  try {
    const row = await currentUserRow(req);
    if (!row) {
      return res.status(401).json({ ok: false, data: null, message: 'Authentication required.' });
    }
    if (row.inactive) {
      return res.status(403).json({ ok: false, data: null, message: 'This account is not active.' });
    }
    req.user = row;
    next();
  } catch (err) {
    next(err);
  }
}

// Role guard for the trainer workspace. Runs AFTER requireAuth (needs req.user).
// Mirrors the `role !== 'pt'` gate in dashboard-pt.php / pt_action.php.
export function requirePt(req, res, next) {
  if (!req.user || req.user.role !== 'pt') {
    return res.status(403).json({ ok: false, data: null, message: 'Trainer access only.' });
  }
  next();
}

// Role guard for the admin panel. Runs AFTER requireAuth (needs req.user).
// Mirrors the `role === 'admin'` gate on every page in the PHP admin/ dir, but
// applied once at the /api/admin mount point since the whole surface is
// admin-only (unlike the per-endpoint PT guard).
export function requireAdmin(req, res, next) {
  if (!req.user || req.user.role !== 'admin') {
    return res.status(403).json({ ok: false, data: null, message: 'Admin access only.' });
  }
  next();
}
