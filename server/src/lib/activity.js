// Audit trail helper — writes one row to `activity_log`, the same table the PHP
// admin panel reads. Ported from the PHP app's logging: every admin mutation
// records who did what to which row (user_id, action_type, target_table,
// target_id, description). Best-effort by design: an audit-write failure must
// never break the action that triggered it, so a DB hiccup is logged to stderr
// and swallowed rather than thrown.
import { query } from '../db.js';

export async function logActivity({ userId, action, targetTable = null, targetId = null, description = null }) {
  try {
    await query(
      `INSERT INTO activity_log (user_id, action_type, target_table, target_id, description)
       VALUES (?, ?, ?, ?, ?)`,
      [userId, action, targetTable, targetId, description]
    );
  } catch (err) {
    console.error('logActivity failed:', err.message);
  }
}
