// User shaping + session helpers. Ports api_public_user() and
// api_current_user_row() from api/_bootstrap.php so the JSON contract the
// Vue client sees is identical to the legacy PHP API.
import { query } from '../db.js';

export function publicUser(row) {
  return {
    user_id: Number(row.user_id),
    handle: row.user_name ?? '',
    user_name: row.user_name ?? '',
    first_name: row.first_name ?? '',
    last_name: row.last_name ?? null,
    email: row.email ?? '',
    role: row.role ?? 'regular',
    profile_image: row.profile_image ?? null,
    theme_preference: row.theme_preference ?? 'system',
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
            us.status, us.theme_preference,
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

  req.session.user = publicUser(row);
  return row;
}
