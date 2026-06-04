// User shaping + session helpers. Ports api_public_user() and
// api_current_user_row() from api/_bootstrap.php so the JSON contract the
// Vue client sees is identical to the legacy PHP API.
import { query } from '../db.js';

export function normalizeProfileImage(value) {
  const v = String(value ?? '').trim();
  if (!v) return null;
  if (/^(https?:|data:)/i.test(v)) return v;
  if (v.startsWith('/')) return v;
  if (v.startsWith('./uploads/')) return v.slice(1);
  if (v.startsWith('uploads/')) return `/${v}`;
  return v;
}

export function publicUser(row) {
  return {
    user_id: Number(row.user_id),
    handle: row.user_name ?? '',
    user_name: row.user_name ?? '',
    first_name: row.first_name ?? '',
    last_name: row.last_name ?? null,
    email: row.email ?? '',
    role: row.role ?? 'regular',
    profile_image: normalizeProfileImage(row.profile_image),
    theme_preference: row.theme_preference ?? 'system',
    language_preference: row.language_preference ?? 'en',
    time_zone: row.time_zone ?? 'Asia/Ho_Chi_Minh',
    needs_onboarding: Boolean(row.needs_onboarding),
  };
}

// Re-fetch the logged-in user on each request (mirrors api_current_user_row):
// keeps role/status/onboarding fresh and revokes the session if the account
// disappeared or is no longer active.
export async function currentUserRow(req) {
  const userId = req.session?.user?.user_id;
  if (!userId) return null;

  const rows = await query(
    `SELECT u.user_id, u.user_name, u.first_name, u.last_name, u.email, u.role, u.profile_image,
            us.status, us.theme_preference, us.language_preference, us.time_zone,
            us.ai_tone, us.ai_persona,
            CASE
                WHEN NOT EXISTS (SELECT 1 FROM userGoal ug WHERE ug.user_id = u.user_id LIMIT 1)
                  OR NOT EXISTS (SELECT 1 FROM userPhysicalInfo upi WHERE upi.user_id = u.user_id LIMIT 1)
                THEN 1 ELSE 0
            END AS needs_onboarding
       FROM user u
       JOIN userStatus us ON u.user_id = us.user_id
      WHERE u.user_id = ?
      LIMIT 1`,
    [userId]
  );

  const row = rows[0];
  if (!row) {
    req.session.destroy(() => {});
    return null;
  }

  if (row.status === 'archived' || row.status === 'banned') {
    req.session.destroy(() => {});
    return { inactive: true };
  }

  // Best-effort: persist the browser-reported timezone so cross-user / server-side
  // reads (PT viewing a client, Wrapped, finalizeYesterdayGoals) can use the
  // user's real zone. Only writes on an actual change; never breaks the request.
  if (req.tz && req.tz !== row.time_zone) {
    query('UPDATE userStatus SET time_zone = ? WHERE user_id = ?', [req.tz, userId]).catch(() => {});
    row.time_zone = req.tz;
  }

  req.session.user = publicUser(row);
  return row;
}
