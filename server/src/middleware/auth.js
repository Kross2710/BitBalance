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
