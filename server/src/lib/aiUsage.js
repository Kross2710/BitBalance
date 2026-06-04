// Unified per-user, per-local-day AI budget. Shared across every AI feature
// (Coach chat in routes/aiCoach.js + Intake photo/chat vision in routes/intake.js)
// through the existing ai_usage_daily table, so a single user's total Gemini cost
// per day is bounded no matter which surface they use. The day is the user's
// local day (req.todayTz), matching how the Coach already counts.
//
// Limit = AI_DAILY_LIMIT, falling back to AI_COACH_DAILY_LIMIT (the value the
// Coach already enforces) so behaviour is continuous without new config.
import { query } from '../db.js';

export const AI_DAILY_LIMIT = Number(
  process.env.AI_DAILY_LIMIT || process.env.AI_COACH_DAILY_LIMIT || 20
);

export async function aiUsageCount(userId, day) {
  const rows = await query(
    'SELECT message_count FROM ai_usage_daily WHERE user_id = ? AND usage_date = ?',
    [userId, day]
  );
  return Number(rows[0]?.message_count ?? 0);
}

// True if the user is at/over the daily AI budget for `day`.
export async function aiQuotaExceeded(userId, day, limit = AI_DAILY_LIMIT) {
  return (await aiUsageCount(userId, day)) >= limit;
}

// Count one successful AI call against the user's daily budget (upsert).
export async function bumpAiUsage(userId, day) {
  await query(
    `INSERT INTO ai_usage_daily (user_id, usage_date, message_count)
     VALUES (?, ?, 1)
     ON DUPLICATE KEY UPDATE message_count = message_count + 1`,
    [userId, day]
  );
}
