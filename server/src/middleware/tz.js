// Per-request timezone context. Reads the client's X-Timezone header (the
// browser's resolved IANA zone), validates it, and attaches:
//   req.tz       — the IANA zone (falls back to Asia/Ho_Chi_Minh)
//   req.tzShift  — minutes to add to a +07:00 stored datetime to reach the user's
//                  local day (0 for VN, so all SQL stays byte-identical there)
//   req.todayTz  — the user's local calendar date today (YYYY-MM-DD)
// Runs for every request (guest + authed), like the remember-me middleware.
import { validateTz, shiftMinutesFor, todayForTz, DEFAULT_TZ } from '../lib/tz.js';

export function tzContext(req, res, next) {
  const tz = validateTz(req.get('X-Timezone')) || DEFAULT_TZ;
  req.tz = tz;
  req.tzShift = shiftMinutesFor(tz);
  req.todayTz = todayForTz(tz);
  next();
}
