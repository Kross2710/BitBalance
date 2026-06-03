// Per-user timezone helpers.
//
// Every datetime in the DB is a +07:00 wall-clock literal (intakeLog.date_intake,
// xp_event.created_at, ...) because the pool forces SET time_zone = '+07:00'
// (see db.js). To present a user's LOCAL day we add a per-request minute shift
// inside DATE() in SQL: shift = offsetMinutes(userTz) - 420 (420 = +07:00).
//
// No MySQL named-timezone tables / CONVERT_TZ are used (the box may not have them
// and a DB re-clone would drop them). The only inaccuracy: a single CURRENT offset
// is applied to historical rows, so a row within ~1h of local midnight on a DST
// transition day may bucket to an adjacent day — acceptable for a calorie tracker.
import { addDays, weekdayLabel, isValidDate } from './dates.js';

const VN_OFFSET_MIN = 420; // +07:00 — the offset the DB stores literals in
export const DEFAULT_TZ = 'Asia/Ho_Chi_Minh';

// UTC offset of an IANA zone at a given instant, in minutes (DST-aware for `at`).
// Formats the instant as wall-clock in `tz`, reinterprets that as UTC, and diffs
// against the true instant. Avoids parsing locale-specific offset strings.
export function offsetMinutes(tz, at = new Date()) {
  try {
    const dtf = new Intl.DateTimeFormat('en-US', {
      timeZone: tz,
      hourCycle: 'h23',
      year: 'numeric', month: '2-digit', day: '2-digit',
      hour: '2-digit', minute: '2-digit', second: '2-digit',
    });
    const p = Object.fromEntries(dtf.formatToParts(at).map((x) => [x.type, x.value]));
    const asUTC = Date.UTC(+p.year, +p.month - 1, +p.day, +p.hour, +p.minute, +p.second);
    return Math.round((asUTC - at.getTime()) / 60000);
  } catch {
    return VN_OFFSET_MIN; // unknown zone → behave like legacy +07:00
  }
}

// Minutes to add to a stored +07:00 datetime to move it into the user's zone.
// 0 for Asia/Ho_Chi_Minh, so all SQL stays byte-identical for VN users.
export function shiftMinutesFor(tz) {
  return offsetMinutes(tz) - VN_OFFSET_MIN;
}

// The user's local calendar date today, as YYYY-MM-DD.
export function todayForTz(tz) {
  const zone = (() => {
    try {
      new Intl.DateTimeFormat('en-US', { timeZone: tz });
      return tz;
    } catch {
      return DEFAULT_TZ;
    }
  })();
  return new Intl.DateTimeFormat('en-CA', {
    timeZone: zone,
    year: 'numeric', month: '2-digit', day: '2-digit',
  }).format(new Date());
}

// Validate + normalize an X-Timezone header value; null if invalid.
export function validateTz(tz) {
  if (typeof tz !== 'string' || tz.length === 0 || tz.length > 64) return null;
  try {
    new Intl.DateTimeFormat('en-US', { timeZone: tz });
    return tz;
  } catch {
    return null;
  }
}

// Bundle for cross-user / server-side reads keyed on a user's STORED tz
// (PT viewing a client, Wrapped, finalizeYesterdayGoals) — never req.tz.
export function ctxForStoredTz(tz) {
  const z = validateTz(tz) || DEFAULT_TZ;
  return { tz: z, shift: shiftMinutesFor(z), today: todayForTz(z) };
}

// The user-local calendar date of a stored +07:00 literal, given the user's shift.
// Mirrors SQL DATE(date_intake + INTERVAL shift MINUTE). Used by /update,/delete.
export function userLocalDateOf(literal, shift = 0) {
  if (!literal) return null;
  const d = new Date(String(literal).replace(' ', 'T') + 'Z'); // wall-clock as UTC for pure math
  if (Number.isNaN(d.getTime())) return null;
  d.setUTCMinutes(d.getUTCMinutes() + shift);
  return d.toISOString().slice(0, 10);
}

// A +07:00 wall-clock literal ('YYYY-MM-DD HH:MM:SS') whose USER-LOCAL date is
// `dateStr`, anchored at the user's local noon — far from any midnight boundary,
// so DATE(literal + INTERVAL shift MINUTE) === dateStr for any reasonable shift.
// Used to stamp backdated intake rows for non-VN users.
export function vnLiteralForUserLocalNoon(tz, dateStr) {
  const [Y, M, D] = dateStr.split('-').map(Number);
  const noonAsUTC = Date.UTC(Y, M - 1, D, 12, 0, 0);
  const off = offsetMinutes(tz, new Date(noonAsUTC)); // offset around that local noon
  const instant = noonAsUTC - off * 60000;            // true UTC instant of user-local noon
  const vn = new Date(instant + VN_OFFSET_MIN * 60000); // express in +07:00 wall-clock
  return vn.toISOString().slice(0, 19).replace('T', ' ');
}

export { addDays, weekdayLabel, isValidDate };
