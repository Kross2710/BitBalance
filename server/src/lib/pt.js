// Personal Trainer — client-facing queries + mutations.
// Ports the client side of dashboard/handlers/pt_chat.php,
// dashboard/handlers/respond_goal_proposal.php and the dashboard-coach.php page
// queries, reusing the shared pt_* MySQL tables. The chat helpers are written
// role-agnostically (they take an explicit trainerId/clientId/myRole) so the PT
// workspace can reuse them later; the exported client* wrappers resolve the
// caller's single accepted trainer.
import { pool, query } from '../db.js';

// Thrown for user-facing validation failures (mapped to a 422 by the route).
export class PtActionError extends Error {}

const MESSAGE_MAX = 2000; // UTF-8 codepoints, mirrors pt_chat.php

function trainerName(row) {
  const name = `${row.first_name ?? ''} ${row.last_name ?? ''}`.trim();
  return name || row.user_name || 'Your trainer';
}

// -----------------------------------------------------------------------------
// Trainer + feedback + proposal (the My Trainer panel bootstrap)
// -----------------------------------------------------------------------------

// The client's current trainer (most recent accepted link) joined with their
// pt_profile, or null when the user has no accepted trainer. Mirrors the
// trainer + pt_profile lookups in dashboard-coach.php.
export async function myTrainer(clientId) {
  const rows = await query(
    `SELECT u.user_id, u.user_name, u.first_name, u.last_name, u.profile_image,
            p.bio, p.specialties, p.experience_years
       FROM trainer_client tc
       JOIN user u ON tc.trainer_id = u.user_id
       LEFT JOIN pt_profile p ON p.user_id = u.user_id
      WHERE tc.client_id = ? AND tc.status = 'accepted'
      ORDER BY tc.responded_at DESC
      LIMIT 1`,
    [clientId]
  );
  if (!rows.length) return null;
  const r = rows[0];
  return {
    user_id: Number(r.user_id),
    user_name: r.user_name ?? '',
    first_name: r.first_name ?? '',
    last_name: r.last_name ?? null,
    profile_image: r.profile_image ?? null,
    bio: r.bio ?? null,
    specialties: r.specialties ?? null,
    experience_years: r.experience_years == null ? null : Number(r.experience_years),
  };
}

// Advice history (newest first), one entry per day the trainer wrote feedback.
export async function feedbackHistory(clientId, limit = 60) {
  const lim = Math.max(1, Math.min(Number(limit) || 60, 200));
  const rows = await query(
    `SELECT pf.content, pf.date_for, u.user_name, u.first_name, u.last_name
       FROM pt_feedback pf
       JOIN user u ON pf.trainer_id = u.user_id
      WHERE pf.client_id = ?
      ORDER BY pf.date_for DESC
      LIMIT ${lim}`,
    [clientId]
  );
  return rows.map((r) => ({
    content: r.content,
    date_for: r.date_for,
    trainer_name: trainerName(r),
  }));
}

// Clear unseen feedback flags when the client opens the panel (mirrors
// dashboard-coach.php marking feedback seen on view).
export async function markFeedbackSeen(clientId) {
  await query(`UPDATE pt_feedback SET seen_at = NOW() WHERE client_id = ? AND seen_at IS NULL`, [clientId]);
}

// The newest still-pending goal proposal from the client's accepted trainer, or
// null. The IA puts goal proposals inside My Trainer (PHP showed them on the
// dashboard hero instead), so this query mirrors that dashboard.php lookup.
export async function pendingProposal(clientId) {
  const rows = await query(
    `SELECT p.id, p.calorie_goal, p.protein_goal, p.carbs_goal, p.fat_goal, p.note, p.created_at,
            u.user_name, u.first_name, u.last_name
       FROM pt_goal_proposal p
       JOIN trainer_client tc
         ON tc.trainer_id = p.trainer_id AND tc.client_id = p.client_id AND tc.status = 'accepted'
       JOIN user u ON u.user_id = p.trainer_id
      WHERE p.client_id = ? AND p.status = 'pending'
      ORDER BY p.created_at DESC
      LIMIT 1`,
    [clientId]
  );
  if (!rows.length) return null;
  const r = rows[0];
  return {
    id: Number(r.id),
    calorie_goal: Number(r.calorie_goal),
    protein_goal: r.protein_goal == null ? null : Number(r.protein_goal),
    carbs_goal: r.carbs_goal == null ? null : Number(r.carbs_goal),
    fat_goal: r.fat_goal == null ? null : Number(r.fat_goal),
    note: r.note ?? null,
    created_at: r.created_at,
    trainer_name: trainerName(r),
  };
}

// Accept / decline a pending proposal. Ports respond_goal_proposal.php: on
// accept, write a new userGoal row (latest-wins) with source='pt'; on decline,
// just mark the proposal. The accepted-link + pending check happens in one query
// so a stale proposal id can't write a goal.
export async function respondProposal(clientId, proposalId, decision) {
  const rows = await query(
    `SELECT p.id, p.trainer_id, p.calorie_goal, p.protein_goal, p.carbs_goal, p.fat_goal
       FROM pt_goal_proposal p
       JOIN trainer_client tc
         ON tc.trainer_id = p.trainer_id AND tc.client_id = p.client_id AND tc.status = 'accepted'
      WHERE p.id = ? AND p.client_id = ? AND p.status = 'pending'
      LIMIT 1`,
    [proposalId, clientId]
  );
  if (!rows.length) throw new PtActionError('Proposal not found or no longer active.');
  const p = rows[0];

  if (decision === 'decline') {
    await query(`UPDATE pt_goal_proposal SET status = 'declined', responded_at = NOW() WHERE id = ?`, [proposalId]);
    return { accepted: false };
  }

  // accept — write the goal + mark accepted atomically.
  const hasMacros = p.protein_goal != null && p.carbs_goal != null && p.fat_goal != null;
  const conn = await pool.getConnection();
  try {
    await conn.beginTransaction();
    await conn.query(
      `INSERT INTO userGoal (user_id, calorie_goal, protein_goal, carbs_goal, fat_goal, set_by, source, date_set)
       VALUES (?, ?, ?, ?, ?, ?, 'pt', NOW())`,
      [
        clientId,
        Number(p.calorie_goal),
        hasMacros ? Number(p.protein_goal) : null,
        hasMacros ? Number(p.carbs_goal) : null,
        hasMacros ? Number(p.fat_goal) : null,
        Number(p.trainer_id),
      ]
    );
    await conn.query(`UPDATE pt_goal_proposal SET status = 'accepted', responded_at = NOW() WHERE id = ?`, [proposalId]);
    await conn.commit();
  } catch (err) {
    await conn.rollback();
    throw err;
  } finally {
    conn.release();
  }
  return { accepted: true, calorie_goal: Number(p.calorie_goal) };
}

// -----------------------------------------------------------------------------
// Chat — role-agnostic (reused by the PT workspace later)
// -----------------------------------------------------------------------------

async function getThread(trainerId, clientId, create) {
  const rows = await query(`SELECT thread_id FROM pt_thread WHERE trainer_id = ? AND client_id = ? LIMIT 1`, [
    trainerId,
    clientId,
  ]);
  if (rows.length) return Number(rows[0].thread_id);
  if (!create) return null;
  const r = await query(`INSERT INTO pt_thread (trainer_id, client_id) VALUES (?, ?)`, [trainerId, clientId]);
  return Number(r.insertId);
}

// Fetch messages (optionally since a cursor) and mark the counterpart's unseen
// messages as read. Mirrors pt_chat.php action=fetch.
export async function chatFetch(trainerId, clientId, myRole, since = 0) {
  const thread = await getThread(trainerId, clientId, false);
  if (!thread) return { messages: [], my_role: myRole };

  const sinceId = Number(since) || 0;
  const messages = sinceId
    ? await query(
        `SELECT message_id, sender_role, content, created_at
           FROM pt_message WHERE thread_id = ? AND message_id > ?
          ORDER BY created_at ASC, message_id ASC`,
        [thread, sinceId]
      )
    : await query(
        `SELECT message_id, sender_role, content, created_at
           FROM pt_message WHERE thread_id = ?
          ORDER BY created_at ASC, message_id ASC`,
        [thread]
      );

  const otherRole = myRole === 'trainer' ? 'client' : 'trainer';
  await query(`UPDATE pt_message SET seen_at = NOW() WHERE thread_id = ? AND sender_role = ? AND seen_at IS NULL`, [
    thread,
    otherRole,
  ]);

  return {
    messages: messages.map((m) => ({
      message_id: Number(m.message_id),
      sender_role: m.sender_role,
      content: m.content,
      created_at: m.created_at,
    })),
    my_role: myRole,
  };
}

// Send a message, lazy-creating the thread. Mirrors pt_chat.php action=send.
export async function chatSend(trainerId, clientId, myRole, content) {
  let text = String(content ?? '').trim();
  if (text === '') throw new PtActionError('Message is empty.');
  const cp = [...text];
  if (cp.length > MESSAGE_MAX) text = cp.slice(0, MESSAGE_MAX).join('');

  const thread = await getThread(trainerId, clientId, true);
  const ins = await query(`INSERT INTO pt_message (thread_id, sender_role, content) VALUES (?, ?, ?)`, [
    thread,
    myRole,
    text,
  ]);
  await query(`UPDATE pt_thread SET updated_at = NOW() WHERE thread_id = ?`, [thread]);

  const rows = await query(
    `SELECT message_id, sender_role, content, created_at FROM pt_message WHERE message_id = ? LIMIT 1`,
    [ins.insertId]
  );
  const m = rows[0];
  return {
    message: {
      message_id: Number(m.message_id),
      sender_role: m.sender_role,
      content: m.content,
      created_at: m.created_at,
    },
  };
}

// Client-perspective wrappers: resolve the caller's single accepted trainer.
export async function clientChatFetch(clientId, since = 0) {
  const t = await myTrainer(clientId);
  if (!t) return { messages: [], my_role: 'client' };
  return chatFetch(t.user_id, clientId, 'client', since);
}

export async function clientChatSend(clientId, content) {
  const t = await myTrainer(clientId);
  if (!t) throw new PtActionError('No trainer connected.');
  return chatSend(t.user_id, clientId, 'client', content);
}
