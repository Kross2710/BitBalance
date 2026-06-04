// Profile data shaping — ports api/profile/_helpers.php. Reads the user row
// (joined with userStatus), the latest calorie goal, and physical info, and
// assembles the same { user, bio, status, goal, physical } payload the legacy
// PHP API returned so the Vue client maps 1-1.
import { query } from '../db.js';
import { publicUser } from './users.js';

// Joined user + status row (api_profile_fetch_user).
export async function fetchUser(userId) {
  const rows = await query(
    `SELECT u.user_id, u.user_name, u.first_name, u.last_name, u.email, u.role, u.profile_image,
            us.status, us.theme_preference, us.language_preference, us.profile_bio,
            us.profile_visibility, us.show_favorite_food, us.ai_tone, us.ai_persona
       FROM user u
       JOIN userStatus us ON u.user_id = us.user_id
      WHERE u.user_id = ?
      LIMIT 1`,
    [userId]
  );
  return rows[0] ?? null;
}

// Latest calorie goal (api_profile_current_goal).
export async function currentGoal(userId) {
  const rows = await query(
    `SELECT calorie_goal, date_set
       FROM userGoal
      WHERE user_id = ?
      ORDER BY date_set DESC, userGoal_id DESC
      LIMIT 1`,
    [userId]
  );
  const row = rows[0];
  if (!row) return null;
  return {
    calorie_goal: Number(row.calorie_goal),
    date_set: row.date_set ?? null,
  };
}

// Physical info, with null-shaped defaults when the row is absent
// (api_profile_physical).
export async function physical(userId) {
  const rows = await query(
    `SELECT age, gender, weight, height
       FROM userPhysicalInfo
      WHERE user_id = ?
      LIMIT 1`,
    [userId]
  );
  const row = rows[0];
  if (!row) {
    return { age: null, gender: null, weight: null, height: null };
  }
  return {
    age: row.age !== null ? Number(row.age) : null,
    gender: row.gender,
    weight: row.weight !== null ? Number(row.weight) : null,
    height: row.height !== null ? Number(row.height) : null,
  };
}

// Full payload (api_profile_payload).
export async function payload(userRow) {
  const userId = Number(userRow.user_id);
  const [goal, phys] = await Promise.all([currentGoal(userId), physical(userId)]);
  return {
    user: publicUser(userRow),
    bio: userRow.profile_bio ?? '',
    status: userRow.status ?? 'active',
    privacy: {
      visibility: userRow.profile_visibility ?? 'friends',
      show_favorite_food: Number(userRow.show_favorite_food ?? 1) === 1,
    },
    ai: {
      tone: userRow.ai_tone ?? 'formal',
      persona: userRow.ai_persona ?? '',
    },
    goal,
    physical: phys,
  };
}
