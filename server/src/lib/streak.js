// Logging streak — ports updateLoggingStreak() from dashboard/handlers/functions.php.
// Increments on a consecutive day, consumes a freeze on a one-day gap, otherwise
// resets to 1 (stashing the broken streak). Transactional with FOR UPDATE to
// avoid double-counting under concurrent logs.
//
// NOTE: the PHP version also writes audit rows via log_attempt (activity_log);
// that audit logging is not ported here (behaviour is unchanged). See MIGRATION.md.
import { pool } from '../db.js';
import { todayVN, addDays } from './dates.js';

// `today` is the user's local calendar date (YYYY-MM-DD). Callers pass req.todayTz
// so the streak advances per the user's local day; defaults to VN for safety.
export async function updateLoggingStreak(userId, today = todayVN()) {
  const conn = await pool.getConnection();
  try {
    await conn.beginTransaction();

    const [rows] = await conn.query(
      `SELECT last_logging_date, logging_streak, longest_logging_streak, streak_freezes, broken_streak
         FROM userStatus WHERE user_id = ? FOR UPDATE`,
      [userId]
    );
    const status = rows[0];
    if (!status) {
      await conn.rollback();
      throw new Error(`User status not found for user ID ${userId}`);
    }

    const yesterday = addDays(today, -1);
    const lastLogging = status.last_logging_date ? String(status.last_logging_date).slice(0, 10) : null;

    let streak = Number(status.logging_streak ?? 0);
    let longest = Number(status.longest_logging_streak ?? 0);
    let freezes = Number(status.streak_freezes ?? 0);
    let broken = Number(status.broken_streak ?? 0);

    if (lastLogging === today) {
      // Already logged today → nothing to do.
      await conn.commit();
      return;
    }

    if (lastLogging === yesterday) {
      streak++; // consecutive day
    } else if (lastLogging && freezes > 0) {
      freezes--; // missed a day but spend a freeze to preserve + increment
      streak++;
    } else {
      if (streak > 1) broken = streak;
      streak = 1; // reset / first log
    }

    if (streak > longest) longest = streak;

    await conn.query(
      `UPDATE userStatus
          SET last_logging_date = ?, logging_streak = ?, longest_logging_streak = ?, streak_freezes = ?, broken_streak = ?
        WHERE user_id = ?`,
      [today, streak, longest, freezes, broken, userId]
    );

    await conn.commit();
  } catch (e) {
    await conn.rollback().catch(() => {});
    throw e;
  } finally {
    conn.release();
  }
}
