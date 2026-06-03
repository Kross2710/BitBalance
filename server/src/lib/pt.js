// Personal Trainer — client-facing queries + mutations.
// Ports the client side of dashboard/handlers/pt_chat.php,
// dashboard/handlers/respond_goal_proposal.php and the dashboard-coach.php page
// queries, reusing the shared pt_* MySQL tables. The chat helpers are written
// role-agnostically (they take an explicit trainerId/clientId/myRole) so the PT
// workspace can reuse them later; the exported client* wrappers resolve the
// caller's single accepted trainer.
import { pool, query } from '../db.js';
import { addDays, weekdayLabel, isValidDate } from './dates.js';
import { ctxForStoredTz } from './tz.js';
import { normalizeProfileImage } from './users.js';

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
    profile_image: normalizeProfileImage(r.profile_image),
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

// -----------------------------------------------------------------------------
// Trainer discovery + request (client-initiated) — ports profile.php
// -----------------------------------------------------------------------------

// The client's pending OUTGOING request (trainer they asked but who hasn't
// responded), or null. Mirrors the trainer_connection lookup in profile.php.
export async function pendingTrainer(clientId) {
  const rows = await query(
    `SELECT u.user_id, u.user_name, u.first_name, u.last_name, u.profile_image
       FROM trainer_client tc
       JOIN user u ON tc.trainer_id = u.user_id
      WHERE tc.client_id = ? AND tc.status = 'pending'
        AND (tc.initiated_by = 'client' OR tc.initiated_by IS NULL)
      ORDER BY tc.created_at DESC
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
    profile_image: normalizeProfileImage(r.profile_image),
  };
}

// Incoming INVITES from trainers awaiting the client's accept/decline
// (PT-initiated pending links). Counterpart of pendingTrainer.
export async function incomingInvites(clientId) {
  const rows = await query(
    `SELECT tc.id AS request_id, u.user_id, u.user_name, u.first_name, u.last_name, u.profile_image,
            p.specialties, p.experience_years
       FROM trainer_client tc
       JOIN user u ON tc.trainer_id = u.user_id
       LEFT JOIN pt_profile p ON p.user_id = u.user_id
      WHERE tc.client_id = ? AND tc.status = 'pending' AND tc.initiated_by = 'trainer'
      ORDER BY tc.created_at DESC`,
    [clientId]
  );
  return rows.map((r) => ({
    request_id: Number(r.request_id),
    trainer_id: Number(r.user_id),
    user_name: r.user_name ?? '',
    trainer_name: trainerName(r),
    profile_image: normalizeProfileImage(r.profile_image),
    specialties: r.specialties ?? null,
    experience_years: r.experience_years == null ? null : Number(r.experience_years),
  }));
}

// Client accepts / declines a trainer's invite. Accept flips to accepted (and
// clears any other in-flight links so the one-trainer rule holds); decline deletes.
export async function respondInvite(clientId, requestId, action) {
  const rows = await query(
    `SELECT id FROM trainer_client
      WHERE id = ? AND client_id = ? AND status = 'pending' AND initiated_by = 'trainer'
      LIMIT 1`,
    [requestId, clientId]
  );
  if (!rows.length) throw new PtActionError('Invite not found or no longer active.');

  if (action === 'decline') {
    await query(`DELETE FROM trainer_client WHERE id = ?`, [requestId]);
    return { accepted: false };
  }

  const accepted = await query(
    `SELECT id FROM trainer_client WHERE client_id = ? AND status = 'accepted' LIMIT 1`,
    [clientId]
  );
  if (accepted.length) throw new PtActionError('You already have a trainer.');

  const conn = await pool.getConnection();
  try {
    await conn.beginTransaction();
    await conn.query(`UPDATE trainer_client SET status = 'accepted', responded_at = NOW() WHERE id = ?`, [requestId]);
    // Drop any other in-flight links for this client (their own requests / other invites).
    await conn.query(`DELETE FROM trainer_client WHERE client_id = ? AND status = 'pending' AND id <> ?`, [
      clientId,
      requestId,
    ]);
    await conn.commit();
  } catch (err) {
    await conn.rollback();
    throw err;
  } finally {
    conn.release();
  }
  return { accepted: true };
}

// Browsable directory of onboarded trainers (role 'pt' WITH a pt_profile), least
// busy first. Mirrors the $pt_directory query in profile.php.
export async function ptDirectory(clientId) {
  const rows = await query(
    `SELECT u.user_id, u.user_name, u.first_name, u.last_name, u.profile_image,
            p.bio, p.specialties, p.experience_years, p.max_clients,
            (SELECT COUNT(*) FROM trainer_client tc WHERE tc.trainer_id = u.user_id AND tc.status = 'accepted') AS client_count
       FROM user u
       JOIN pt_profile p ON p.user_id = u.user_id
      WHERE u.role = 'pt' AND u.user_id != ?
      ORDER BY client_count ASC, u.first_name ASC
      LIMIT 60`,
    [clientId]
  );
  return rows.map((r) => {
    const max = r.max_clients == null ? null : Number(r.max_clients);
    const count = Number(r.client_count ?? 0);
    return {
      user_id: Number(r.user_id),
      user_name: r.user_name ?? '',
      first_name: r.first_name ?? '',
      last_name: r.last_name ?? null,
      profile_image: normalizeProfileImage(r.profile_image),
      bio: r.bio ?? null,
      specialties: r.specialties ?? null,
      experience_years: r.experience_years == null ? null : Number(r.experience_years),
      client_count: count,
      max_clients: max,
      is_full: max != null && count >= max,
    };
  });
}

// Send a pending connection request to a trainer. Ports send_trainer_request,
// plus a capacity guard and a "already have a trainer" guard the PHP lacked.
export async function sendTrainerRequest(clientId, trainerId) {
  const tid = Number(trainerId);
  if (!Number.isInteger(tid) || tid <= 0) throw new PtActionError('Invalid trainer.');
  if (tid === clientId) throw new PtActionError('You cannot link with yourself.');

  const trainerRows = await query(`SELECT user_id, role FROM user WHERE user_id = ? LIMIT 1`, [tid]);
  if (!trainerRows.length) throw new PtActionError('Trainer not found.');
  if (trainerRows[0].role !== 'pt') throw new PtActionError('This user is not a personal trainer.');

  // Already have (or pending with) a trainer? Keep it to one at a time.
  const existing = await query(
    `SELECT status FROM trainer_client WHERE client_id = ? AND status IN ('accepted','pending') LIMIT 1`,
    [clientId]
  );
  if (existing.length) {
    throw new PtActionError(
      existing[0].status === 'accepted' ? 'You already have a trainer.' : 'You already have a pending request.'
    );
  }

  // Capacity guard.
  const capRows = await query(
    `SELECT p.max_clients,
            (SELECT COUNT(*) FROM trainer_client tc WHERE tc.trainer_id = ? AND tc.status = 'accepted') AS cnt
       FROM pt_profile p WHERE p.user_id = ? LIMIT 1`,
    [tid, tid]
  );
  const cap = capRows[0];
  if (cap && cap.max_clients != null && Number(cap.cnt) >= Number(cap.max_clients)) {
    throw new PtActionError('This trainer is at capacity.');
  }

  await query(
    `INSERT INTO trainer_client (trainer_id, client_id, status, initiated_by)
     VALUES (?, ?, 'pending', 'client')
     ON DUPLICATE KEY UPDATE status = 'pending', initiated_by = 'client', responded_at = NULL`,
    [tid, clientId]
  );
  return { requested: true };
}

// Cancel the client's own pending request(s).
export async function cancelTrainerRequest(clientId) {
  await query(`DELETE FROM trainer_client WHERE client_id = ? AND status = 'pending'`, [clientId]);
  return { cancelled: true };
}

// Client leaves their (accepted) trainer. Mirrors disconnect_trainer in profile.php.
export async function disconnectTrainer(clientId) {
  await query(`DELETE FROM trainer_client WHERE client_id = ? AND status = 'accepted'`, [clientId]);
  return { disconnected: true };
}

// -----------------------------------------------------------------------------
// Trainer workspace (role 'pt') — ports dashboard-pt.php + pt_action.php
// -----------------------------------------------------------------------------

// Guard: the (trainer, client) pair must be an accepted link. Throws otherwise.
async function requireAcceptedClient(trainerId, clientId) {
  const rows = await query(
    `SELECT id FROM trainer_client WHERE trainer_id = ? AND client_id = ? AND status = 'accepted' LIMIT 1`,
    [trainerId, clientId]
  );
  if (!rows.length) throw new PtActionError('Not your client.');
}

// The trainer's accepted clients + today's stats, unread counts, and a flag for
// who already has a pending goal proposal. Mirrors the clients/unread/proposals
// queries in dashboard-pt.php (kept as separate queries + merged, like the PHP).
export async function trainerClients(trainerId) {
  const clients = await query(
    `SELECT u.user_id, u.user_name, u.first_name, u.last_name, u.profile_image,
            us.logging_streak, us.time_zone,
            (SELECT calorie_goal FROM userGoal WHERE user_id = u.user_id ORDER BY date_set DESC LIMIT 1) AS calorie_goal,
            (SELECT weight FROM weight_log WHERE user_id = u.user_id ORDER BY date_logged DESC LIMIT 1) AS last_weight
       FROM trainer_client tc
       JOIN user u ON tc.client_id = u.user_id
       JOIN userStatus us ON u.user_id = us.user_id
      WHERE tc.trainer_id = ? AND tc.status = 'accepted'
      ORDER BY u.first_name ASC`,
    [trainerId]
  );

  // Today's totals are scoped to each CLIENT's own timezone (not the trainer's),
  // so a coach in a different zone still sees the client's local day. N is small.
  for (const c of clients) {
    const { shift, today } = ctxForStoredTz(c.time_zone);
    const [s] = await query(
      `SELECT COALESCE(SUM(calories),0) AS cal, COALESCE(SUM(protein),0) AS pro
         FROM intakeLog WHERE user_id = ? AND DATE(date_intake + INTERVAL ? MINUTE) = ?`,
      [c.user_id, shift, today]
    );
    c.calories_today = Number(s.cal);
    c.protein_today = Number(s.pro);
  }

  const unreadRows = await query(
    `SELECT t.client_id, COUNT(*) AS unread
       FROM pt_message m
       JOIN pt_thread t ON m.thread_id = t.thread_id
      WHERE t.trainer_id = ? AND m.sender_role = 'client' AND m.seen_at IS NULL
      GROUP BY t.client_id`,
    [trainerId]
  );
  const unread = new Map(unreadRows.map((r) => [Number(r.client_id), Number(r.unread)]));

  const propRows = await query(
    `SELECT DISTINCT client_id FROM pt_goal_proposal WHERE trainer_id = ? AND status = 'pending'`,
    [trainerId]
  );
  const pending = new Set(propRows.map((r) => Number(r.client_id)));

  return clients.map((c) => ({
    user_id: Number(c.user_id),
    user_name: c.user_name ?? '',
    first_name: c.first_name ?? '',
    last_name: c.last_name ?? null,
    profile_image: normalizeProfileImage(c.profile_image),
    logging_streak: Number(c.logging_streak ?? 0),
    calorie_goal: c.calorie_goal == null ? null : Number(c.calorie_goal),
    last_weight: c.last_weight == null ? null : Number(c.last_weight),
    calories_today: Number(c.calories_today ?? 0),
    protein_today: Number(c.protein_today ?? 0),
    unread: unread.get(Number(c.user_id)) ?? 0,
    has_pending_proposal: pending.has(Number(c.user_id)),
  }));
}

// Detail for one client: today's diary, a filled 7-day trend, current calorie
// goal, and this trainer's feedback history for them (for the editor).
export async function clientDetail(trainerId, clientId) {
  await requireAcceptedClient(trainerId, clientId);

  // Scope the client's day to THEIR timezone, not the trainer's.
  const tzRow = await query('SELECT time_zone FROM userStatus WHERE user_id = ?', [clientId]);
  const { shift, today } = ctxForStoredTz(tzRow[0]?.time_zone);
  const from = addDays(today, -6);

  const diary = await query(
    `SELECT food_item, meal_category, calories, protein, carbs, fat, image_path, date_intake
       FROM intakeLog
      WHERE user_id = ? AND DATE(date_intake + INTERVAL ? MINUTE) = ?
      ORDER BY date_intake DESC`,
    [clientId, shift, today]
  );

  const trendRows = await query(
    `SELECT DATE(date_intake + INTERVAL ? MINUTE) AS d, SUM(calories) AS cal, SUM(protein) AS pro
       FROM intakeLog
      WHERE user_id = ? AND DATE(date_intake + INTERVAL ? MINUTE) >= ?
      GROUP BY DATE(date_intake + INTERVAL ? MINUTE)`,
    [shift, clientId, shift, from, shift]
  );
  const byDate = new Map(trendRows.map((r) => [String(r.d), { cal: Number(r.cal ?? 0), pro: Number(r.pro ?? 0) }]));
  const trend = [];
  for (let i = 6; i >= 0; i--) {
    const date = addDays(today, -i);
    const v = byDate.get(date) || { cal: 0, pro: 0 };
    trend.push({ date, weekday: weekdayLabel(date), cal: v.cal, pro: v.pro });
  }

  const goalRows = await query(
    `SELECT calorie_goal FROM userGoal WHERE user_id = ? ORDER BY date_set DESC LIMIT 1`,
    [clientId]
  );
  const calorie_goal = goalRows.length ? Number(goalRows[0].calorie_goal) : null;

  const feedback = await query(
    `SELECT date_for, content FROM pt_feedback WHERE trainer_id = ? AND client_id = ? ORDER BY date_for DESC LIMIT 60`,
    [trainerId, clientId]
  );

  return {
    diary: diary.map((r) => ({
      food_item: r.food_item,
      meal_category: r.meal_category,
      calories: Number(r.calories ?? 0),
      protein: Number(r.protein ?? 0),
      carbs: Number(r.carbs ?? 0),
      fat: Number(r.fat ?? 0),
      image_path: r.image_path ?? null,
      date_intake: r.date_intake,
    })),
    trend,
    calorie_goal,
    feedback: feedback.map((f) => ({ date_for: f.date_for, content: f.content })),
  };
}

// Upsert (or delete when blank) the per-day feedback. Mirrors save_feedback.
export async function saveFeedback(trainerId, clientId, dateFor, content) {
  await requireAcceptedClient(trainerId, clientId);
  if (!isValidDate(String(dateFor))) throw new PtActionError('Invalid date.');
  const text = String(content ?? '').trim();

  if (text === '') {
    await query(`DELETE FROM pt_feedback WHERE trainer_id = ? AND client_id = ? AND date_for = ?`, [
      trainerId,
      clientId,
      dateFor,
    ]);
    return { saved: false };
  }

  await query(
    `INSERT INTO pt_feedback (trainer_id, client_id, date_for, content)
     VALUES (?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE content = ?, updated_at = NOW()`,
    [trainerId, clientId, dateFor, text, text]
  );
  return { saved: true };
}

// Propose a calorie (+ optional all-or-none macros) goal. Supersedes any prior
// pending proposal for the pair. Mirrors propose_goal.
export async function proposeGoal(trainerId, clientId, payload) {
  await requireAcceptedClient(trainerId, clientId);

  const calorie = Number(payload?.calorie_goal);
  if (!Number.isInteger(calorie) || calorie < 800 || calorie > 10000) {
    throw new PtActionError('Calorie goal must be between 800 and 10000.');
  }

  const macros = {};
  let macroSet = 0;
  for (const key of ['protein', 'carbs', 'fat']) {
    const raw = payload?.[key];
    if (raw === '' || raw === null || raw === undefined) {
      macros[key] = null;
      continue;
    }
    const iv = Number(raw);
    if (!Number.isInteger(iv) || iv < 0 || iv > 999) {
      throw new PtActionError('Macro values must be whole numbers between 0 and 999.');
    }
    macros[key] = iv;
    macroSet++;
  }
  if (macroSet > 0 && macroSet < 3) {
    throw new PtActionError('Provide all three macros (protein, carbs, fat) or leave them all empty.');
  }

  let note = String(payload?.note ?? '').trim();
  if (note.length > 255) note = note.slice(0, 255);

  await query(
    `UPDATE pt_goal_proposal SET status = 'superseded', responded_at = NOW()
      WHERE trainer_id = ? AND client_id = ? AND status = 'pending'`,
    [trainerId, clientId]
  );
  await query(
    `INSERT INTO pt_goal_proposal (trainer_id, client_id, calorie_goal, protein_goal, carbs_goal, fat_goal, note, status)
     VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')`,
    [trainerId, clientId, calorie, macros.protein, macros.carbs, macros.fat, note === '' ? null : note]
  );
  return { proposed: true };
}

// Pending connection requests addressed to this trainer.
export async function pendingRequests(trainerId) {
  const rows = await query(
    `SELECT tc.id AS request_id, tc.created_at,
            u.user_id, u.user_name, u.first_name, u.last_name, u.profile_image,
            us.logging_streak
       FROM trainer_client tc
       JOIN user u ON tc.client_id = u.user_id
       JOIN userStatus us ON u.user_id = us.user_id
      WHERE tc.trainer_id = ? AND tc.status = 'pending'
        AND (tc.initiated_by = 'client' OR tc.initiated_by IS NULL)
      ORDER BY tc.created_at DESC`,
    [trainerId]
  );
  return rows.map((r) => ({
    request_id: Number(r.request_id),
    created_at: r.created_at,
    user_id: Number(r.user_id),
    user_name: r.user_name ?? '',
    first_name: r.first_name ?? '',
    last_name: r.last_name ?? null,
    profile_image: normalizeProfileImage(r.profile_image),
    logging_streak: Number(r.logging_streak ?? 0),
  }));
}

// Accept (UPDATE) or reject (DELETE) a pending request. Mirrors accept/reject.
export async function respondRequest(trainerId, requestId, action) {
  let r;
  if (action === 'accept') {
    r = await query(
      `UPDATE trainer_client SET status = 'accepted', responded_at = NOW()
        WHERE id = ? AND trainer_id = ? AND status = 'pending'`,
      [requestId, trainerId]
    );
  } else {
    r = await query(`DELETE FROM trainer_client WHERE id = ? AND trainer_id = ? AND status = 'pending'`, [
      requestId,
      trainerId,
    ]);
  }
  if (!r.affectedRows) throw new PtActionError('Request not found or already handled.');
  return { [action === 'accept' ? 'accepted' : 'rejected']: true };
}

// Trainer removes an accepted client. Mirrors pt_action.php action=terminate.
export async function terminateClient(trainerId, clientId) {
  await requireAcceptedClient(trainerId, clientId);
  await query(`DELETE FROM trainer_client WHERE trainer_id = ? AND client_id = ? AND status = 'accepted'`, [
    trainerId,
    clientId,
  ]);
  return { terminated: true };
}

// Trainer-perspective chat wrappers (role 'trainer', explicit client).
export async function trainerChatFetch(trainerId, clientId, since = 0) {
  await requireAcceptedClient(trainerId, clientId);
  return chatFetch(trainerId, clientId, 'trainer', since);
}

export async function trainerChatSend(trainerId, clientId, content) {
  await requireAcceptedClient(trainerId, clientId);
  return chatSend(trainerId, clientId, 'trainer', content);
}

// -----------------------------------------------------------------------------
// Trainer-initiated invites (PT connects with a client; client must accept)
// -----------------------------------------------------------------------------

// Search regular users the trainer can invite, annotated with their relationship
// to THIS trainer (and whether they already have a trainer elsewhere) so the UI
// can show the right action.
export async function searchInvitableClients(trainerId, q) {
  const term = String(q ?? '').trim();
  if (term.length < 2) return [];
  const like = '%' + term.replace(/([%_\\])/g, '\\$1') + '%';

  const rows = await query(
    `SELECT u.user_id, u.user_name, u.profile_image,
            tc.status AS my_status, tc.initiated_by AS my_init,
            (SELECT COUNT(*) FROM trainer_client x WHERE x.client_id = u.user_id AND x.status = 'accepted') AS any_accepted,
            (SELECT COUNT(*) FROM trainer_client x WHERE x.client_id = u.user_id AND x.status = 'pending')  AS any_pending
       FROM user u
       LEFT JOIN trainer_client tc
         ON tc.client_id = u.user_id AND tc.trainer_id = ? AND tc.status IN ('accepted','pending')
      WHERE u.user_name LIKE ? ESCAPE '\\\\' AND u.user_id <> ? AND u.role = 'regular'
      ORDER BY (u.user_name = ?) DESC, u.user_name ASC
      LIMIT 20`,
    [trainerId, like, trainerId, term]
  );

  return rows.map((r) => {
    let state;
    if (r.my_status === 'accepted') state = 'client';
    else if (r.my_status === 'pending' && r.my_init === 'trainer') state = 'invited';
    else if (r.my_status === 'pending') state = 'requested_me'; // they requested me
    else if (Number(r.any_accepted) > 0) state = 'has_trainer';
    else if (Number(r.any_pending) > 0) state = 'busy'; // pending elsewhere
    else state = 'invitable';
    return {
      user_id: Number(r.user_id),
      user_name: r.user_name ?? '',
      profile_image: normalizeProfileImage(r.profile_image),
      state,
    };
  });
}

// Invite a client (PT-initiated). One relationship in flight per client, plus a
// capacity guard on the trainer. Writes a pending link with initiated_by='trainer'.
export async function inviteClient(trainerId, clientId) {
  const cid = Number(clientId);
  if (!Number.isInteger(cid) || cid <= 0) throw new PtActionError('Invalid client.');
  if (cid === trainerId) throw new PtActionError('You cannot invite yourself.');

  const userRows = await query(`SELECT user_id, role FROM user WHERE user_id = ? LIMIT 1`, [cid]);
  if (!userRows.length) throw new PtActionError('User not found.');
  if (userRows[0].role !== 'regular') throw new PtActionError('Only regular users can be invited as clients.');

  const links = await query(
    `SELECT trainer_id, status, initiated_by FROM trainer_client WHERE client_id = ? AND status IN ('accepted','pending')`,
    [cid]
  );
  if (links.some((l) => l.status === 'accepted')) throw new PtActionError('This user already has a trainer.');
  const pending = links.find((l) => l.status === 'pending');
  if (pending) {
    if (Number(pending.trainer_id) === trainerId) {
      throw new PtActionError(
        pending.initiated_by === 'client' ? 'They already requested you — accept it in Requests.' : 'Already invited.'
      );
    }
    throw new PtActionError('This user already has a pending connection.');
  }

  // Capacity: trainer's own accepted clients vs their max_clients.
  const capRows = await query(
    `SELECT p.max_clients,
            (SELECT COUNT(*) FROM trainer_client tc WHERE tc.trainer_id = ? AND tc.status = 'accepted') AS cnt
       FROM pt_profile p WHERE p.user_id = ? LIMIT 1`,
    [trainerId, trainerId]
  );
  const cap = capRows[0];
  if (cap && cap.max_clients != null && Number(cap.cnt) >= Number(cap.max_clients)) {
    throw new PtActionError("You're at capacity.");
  }

  await query(
    `INSERT INTO trainer_client (trainer_id, client_id, status, initiated_by)
     VALUES (?, ?, 'pending', 'trainer')
     ON DUPLICATE KEY UPDATE status = 'pending', initiated_by = 'trainer', responded_at = NULL`,
    [trainerId, cid]
  );
  return { invited: true };
}

// Trainer cancels their own outgoing invite.
export async function cancelInvite(trainerId, clientId) {
  await query(
    `DELETE FROM trainer_client WHERE trainer_id = ? AND client_id = ? AND status = 'pending' AND initiated_by = 'trainer'`,
    [trainerId, Number(clientId)]
  );
  return { cancelled: true };
}

// The trainer's pending outgoing invites (for the Find-clients tab).
export async function outgoingInvites(trainerId) {
  const rows = await query(
    `SELECT u.user_id, u.user_name, u.first_name, u.last_name, u.profile_image
       FROM trainer_client tc
       JOIN user u ON tc.client_id = u.user_id
      WHERE tc.trainer_id = ? AND tc.status = 'pending' AND tc.initiated_by = 'trainer'
      ORDER BY tc.created_at DESC`,
    [trainerId]
  );
  return rows.map((r) => ({
    user_id: Number(r.user_id),
    user_name: r.user_name ?? '',
    first_name: r.first_name ?? '',
    last_name: r.last_name ?? null,
    profile_image: normalizeProfileImage(r.profile_image),
  }));
}
