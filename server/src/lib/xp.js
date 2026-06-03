// XP & level system — ports include/handlers/xp.php (state-based awards).
//
// Anti-cheat: daily-capped awards are computed by COUNTing rows in the source
// table and subtracting award events already in xp_event for that day, so
// log-and-delete spam can't inflate totals — only the high-water mark counts.
//
// Level curve: xpForLevel(n) = 50 * n * (n - 1)
//   L1=0, L2=100, L3=300, L4=600, ...
//
// The level-up "flash" (toast payload) is stashed on the Express session
// (req.session.xp_levelup_flash), the equivalent of PHP's $_SESSION usage.
import { pool, query } from '../db.js';
import { addDays, todayVN } from './dates.js';

export const XP_RULES = {
  intake_log: { xp: 10, cap: 4 },
  weight_log: { xp: 15, cap: 1 },
  calorie_goal: { xp: 50, cap: 1 },
  macro_goal: { xp: 30, cap: 1 },
};

const XP_STREAK_MILESTONES = { 7: 100, 14: 200, 30: 500, 100: 2000, 365: 10000 };

// --- Level math --------------------------------------------------------------

export function xpForLevel(level) {
  if (level <= 1) return 0;
  return 50 * level * (level - 1);
}

export function xpLevelFor(totalXp) {
  if (totalXp <= 0) return 1;
  // Solve 50*n*(n-1) <= total → n = (1 + sqrt(1 + total/12.5)) / 2
  let n = Math.floor((1 + Math.sqrt(1 + totalXp / 12.5)) / 2);
  if (n < 1) n = 1;
  // Verify (guard against float drift)
  while (xpForLevel(n + 1) <= totalXp) n++;
  while (xpForLevel(n) > totalXp) n--;
  return n;
}

// --- Row bootstrap + read ----------------------------------------------------

export async function ensureRow(userId) {
  await query('INSERT IGNORE INTO user_xp (user_id, total_xp, current_level) VALUES (?, 0, 1)', [userId]);
}

export async function getSummary(userId) {
  await ensureRow(userId);
  const rows = await query('SELECT total_xp, current_level FROM user_xp WHERE user_id = ?', [userId]);
  const r = rows[0] ?? { total_xp: 0, current_level: 1 };

  const total = Number(r.total_xp);
  const level = Number(r.current_level);
  const floor = xpForLevel(level);
  const ceil = xpForLevel(level + 1);
  const into = Math.max(0, total - floor);
  const span = Math.max(1, ceil - floor);
  const pct = Math.min(100, Math.round((into / span) * 100));

  return { total_xp: total, current_level: level, xp_into_level: into, xp_for_next: span, progress_pct: pct };
}

// --- Commit: insert events + bump user_xp + level-up (transactional) ---------

async function commit(userId, source, totalAmount, unitCount, refTable = null, refId = null, session = null) {
  if (totalAmount === 0 || unitCount === 0) return { xp_added: 0, leveled_up: false };
  const perUnit = Math.round(totalAmount / unitCount);

  const conn = await pool.getConnection();
  try {
    await conn.beginTransaction();

    for (let i = 0; i < unitCount; i++) {
      await conn.query('INSERT INTO xp_event (user_id, source, amount, ref_table, ref_id) VALUES (?, ?, ?, ?, ?)', [
        userId,
        source,
        perUnit,
        refTable,
        refId,
      ]);
    }

    const [rows] = await conn.query('SELECT total_xp, current_level FROM user_xp WHERE user_id = ? FOR UPDATE', [userId]);
    const r = rows[0] ?? { total_xp: 0, current_level: 1 };
    const oldLevel = Number(r.current_level);
    const newTotal = Number(r.total_xp) + totalAmount;
    const newLevel = xpLevelFor(newTotal);
    const leveledUp = newLevel > oldLevel;

    await conn.query(
      `UPDATE user_xp
          SET total_xp = ?, current_level = ?,
              last_level_up_at = IF(? > current_level, NOW(), last_level_up_at)
        WHERE user_id = ?`,
      [newTotal, newLevel, newLevel, userId]
    );

    // Each level grants a streak freeze (same as PHP).
    if (leveledUp) {
      await conn.query('UPDATE userStatus SET streak_freezes = streak_freezes + ? WHERE user_id = ?', [
        newLevel - oldLevel,
        userId,
      ]);
    }

    await conn.commit();

    if (leveledUp && session) {
      session.xp_levelup_flash = { from: oldLevel, to: newLevel, xp_added: totalAmount };
    }

    return { xp_added: totalAmount, leveled_up: leveledUp };
  } catch (e) {
    await conn.rollback().catch(() => {});
    return { xp_added: 0, leveled_up: false, error: e.message };
  } finally {
    conn.release();
  }
}

// --- Generic state-based awarder --------------------------------------------

// `countParams` are the bind params for `countSql` (so the caller can include the
// tz shift). `shift` (minutes) reinterprets the +07:00 xp_event.created_at in the
// user's local day for the once-per-day dedupe. shift 0 (VN) == DATE(created_at)=CURDATE().
async function awardForCount(userId, source, xpPerUnit, cap, countSql, countParams, shift, session) {
  await ensureRow(userId);

  const actualRows = await query(countSql, countParams);
  const actual = Number(Object.values(actualRows[0])[0]);

  const awardedRows = await query(
    'SELECT COUNT(*) AS c FROM xp_event WHERE user_id = ? AND source = ? AND DATE(created_at + INTERVAL ? MINUTE) = DATE(NOW() + INTERVAL ? MINUTE)',
    [userId, source, shift, shift]
  );
  const awarded = Number(awardedRows[0].c);

  const target = Math.min(actual, cap);
  const toAward = target - awarded;
  if (toAward <= 0) return { xp_added: 0, leveled_up: false };

  return commit(userId, source, xpPerUnit * toAward, toAward, null, null, session);
}

// --- Source-specific awarders ------------------------------------------------

export function awardIntakeLog(userId, session, shift = 0) {
  return awardForCount(
    userId,
    'intake_log',
    XP_RULES.intake_log.xp,
    XP_RULES.intake_log.cap,
    'SELECT COUNT(*) AS c FROM intakeLog WHERE user_id = ? AND DATE(date_intake + INTERVAL ? MINUTE) = DATE(NOW() + INTERVAL ? MINUTE)',
    [userId, shift, shift],
    shift,
    session
  );
}

// weight_log.date_logged is a DATE stored as the user's local day (see onboarding/
// profile), so compare it directly to today-in-user-tz — no shift on the column.
export function awardWeightLog(userId, session, shift = 0, today = todayVN()) {
  return awardForCount(
    userId,
    'weight_log',
    XP_RULES.weight_log.xp,
    XP_RULES.weight_log.cap,
    'SELECT COUNT(*) AS c FROM weight_log WHERE user_id = ? AND date_logged = ?',
    [userId, today],
    shift,
    session
  );
}

// Idempotent one-off milestone award, keyed by ref_id = milestone value.
export async function awardStreakMilestone(userId, streak, session) {
  if (streak <= 0) return { xp_added: 0, leveled_up: false };
  await ensureRow(userId);

  const result = { xp_added: 0, leveled_up: false };
  for (const [milestoneStr, xp] of Object.entries(XP_STREAK_MILESTONES)) {
    const milestone = Number(milestoneStr);
    if (streak < milestone) continue;

    const existing = await query(
      "SELECT 1 FROM xp_event WHERE user_id = ? AND source = 'streak_milestone' AND ref_id = ? LIMIT 1",
      [userId, milestone]
    );
    if (existing.length) continue;

    const r = await commit(userId, 'streak_milestone', xp, 1, 'userStatus', milestone, session);
    result.xp_added += r.xp_added ?? 0;
    if (r.leveled_up) result.leveled_up = true;
  }
  return result;
}

// --- Lazy-finalize yesterday's calorie/macro goals ---------------------------

async function awardOneOffForDate(userId, source, xp, date, session) {
  const refId = Number(date.replace(/-/g, '')); // e.g. 20260528
  const existing = await query('SELECT 1 FROM xp_event WHERE user_id = ? AND source = ? AND ref_id = ? LIMIT 1', [
    userId,
    source,
    refId,
  ]);
  if (existing.length) return { xp_added: 0, leveled_up: false };
  return commit(userId, source, xp, 1, 'intakeLog', refId, session);
}

// Award goal-hit XP for yesterday, idempotently. Only finalizes yesterday (a
// week-long gap does not retroactively grant XP). Mirrors xp_finalize_yesterday_goals.
export async function finalizeYesterdayGoals(userId, session, shift = 0, today = todayVN()) {
  await ensureRow(userId);

  const lastRows = await query('SELECT last_finalized_date FROM user_xp WHERE user_id = ?', [userId]);
  const last = lastRows[0]?.last_finalized_date ? String(lastRows[0].last_finalized_date).slice(0, 10) : null;
  const yesterday = addDays(today, -1);

  if (last && last >= yesterday) return { xp_added: 0, leveled_up: false };

  const sumRows = await query(
    `SELECT COALESCE(SUM(calories),0) AS cal, COALESCE(SUM(protein),0) AS p,
            COALESCE(SUM(carbs),0) AS c, COALESCE(SUM(fat),0) AS f
       FROM intakeLog WHERE user_id = ? AND DATE(date_intake + INTERVAL ? MINUTE) = ?`,
    [userId, shift, yesterday]
  );
  const t = sumRows[0];

  const goalRows = await query(
    'SELECT calorie_goal FROM userGoal WHERE user_id = ? AND DATE(date_set) <= ? ORDER BY date_set DESC LIMIT 1',
    [userId, yesterday]
  );
  const calGoal = Number(goalRows[0]?.calorie_goal ?? 0);

  let totalXp = 0;
  let leveledUp = false;

  if (calGoal > 0 && Number(t.cal) > 0) {
    const cal = Number(t.cal);
    if (cal >= calGoal * 0.9 && cal <= calGoal * 1.1) {
      const r = await awardOneOffForDate(userId, 'calorie_goal', XP_RULES.calorie_goal.xp, yesterday, session);
      totalXp += r.xp_added;
      if (r.leveled_up) leveledUp = true;
    }

    // Macro hit: each macro within ±15% of target; need all three.
    const macroGoals = {
      protein: Math.round((calGoal * 0.3) / 4),
      carbs: Math.round((calGoal * 0.45) / 4),
      fat: Math.round((calGoal * 0.25) / 9),
    };
    const within = (actual, target) => target > 0 && actual >= target * 0.85 && actual <= target * 1.15;
    if (
      within(Number(t.p), macroGoals.protein) &&
      within(Number(t.c), macroGoals.carbs) &&
      within(Number(t.f), macroGoals.fat)
    ) {
      const r = await awardOneOffForDate(userId, 'macro_goal', XP_RULES.macro_goal.xp, yesterday, session);
      totalXp += r.xp_added;
      if (r.leveled_up) leveledUp = true;
    }
  }

  // Mark finalized regardless — never retry this date.
  await query('UPDATE user_xp SET last_finalized_date = ? WHERE user_id = ?', [yesterday, userId]);

  return { xp_added: totalXp, leveled_up: leveledUp };
}

// --- Toast flash -------------------------------------------------------------

export function consumeLevelupFlash(session) {
  if (!session?.xp_levelup_flash) return null;
  const f = session.xp_levelup_flash;
  delete session.xp_levelup_flash;
  return f;
}
