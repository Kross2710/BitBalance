// Admin panel — data access + mutations for the user-management slice (Phase 1).
// Ports admin/handlers/admin_data.php (getAllUsers / getUsersBySearchAndFilter),
// admin/view-user.php, admin/handlers/edit_user.php and admin/user-action.php.
// The route layer (routes/admin.js) stays thin; all SQL + validation lives here
// and throws AdminActionError (mapped to 422) for user-facing failures.
import bcrypt from 'bcryptjs';
import { query } from '../db.js';
import { logActivity } from './activity.js';
import { normalizeProfileImage } from './users.js';

export class AdminActionError extends Error {}

export const ROLES = ['regular', 'pt', 'admin'];
export const STATUSES = ['active', 'banned', 'archived'];
// Status action (ban/unban/archive/restore) -> resulting userStatus.status.
const STATUS_ACTIONS = { ban: 'banned', unban: 'active', archive: 'archived', restore: 'active' };
const PAGE_SIZE = 20;
const EMAIL_RE = /^[^@\s]+@[^@\s]+\.[^@\s]+$/;
// Same password policy + cost as auth.js register so admin-created accounts are
// indistinguishable from self-signups (and the $2b$ hash stays PHP-compatible).
const PASSWORD_RE = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/;
const BCRYPT_ROUNDS = 10;

function userRow(r) {
  return {
    user_id: Number(r.user_id),
    user_name: r.user_name ?? '',
    first_name: r.first_name ?? '',
    last_name: r.last_name ?? null,
    email: r.email ?? '',
    role: r.role ?? 'regular',
    status: r.status ?? 'active',
    profile_image: normalizeProfileImage(r.profile_image),
    created_at: r.created_at ?? null,
    last_login: r.last_login ?? null,
  };
}

// Create-or-update the user's userStatus row. Legacy rows may lack one, so we
// can't assume an UPDATE will hit. `status` is always a validated enum value, so
// interpolating the archived_at branch is injection-safe. Mirrors the
// archived_at handling in user-action.php (archive sets NOW(), others clear it).
async function writeStatus(userId, status) {
  const archivedAt = status === 'archived' ? 'COALESCE(archived_at, NOW())' : 'NULL';
  const existing = await query('SELECT 1 FROM userStatus WHERE user_id = ? LIMIT 1', [userId]);
  if (existing.length) {
    await query(`UPDATE userStatus SET status = ?, archived_at = ${archivedAt} WHERE user_id = ?`, [status, userId]);
  } else {
    await query(
      `INSERT INTO userStatus (user_id, status, archived_at) VALUES (?, ?, ${status === 'archived' ? 'NOW()' : 'NULL'})`,
      [userId, status]
    );
  }
}

// GET /users — paginated list with optional text search + role/status filters.
// Mirrors getUsersBySearchAndFilter() in admin_data.php.
export async function listUsers({ q = '', role = '', status = '', page = 1 } = {}) {
  const where = [];
  const params = [];
  const term = String(q).trim();
  if (term) {
    where.push('(u.user_name LIKE ? OR u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)');
    const like = `%${term}%`;
    params.push(like, like, like, like);
  }
  if (ROLES.includes(role)) {
    where.push('u.role = ?');
    params.push(role);
  }
  if (STATUSES.includes(status)) {
    where.push('us.status = ?');
    params.push(status);
  }
  const whereSql = where.length ? `WHERE ${where.join(' AND ')}` : '';

  const countRows = await query(
    `SELECT COUNT(*) AS n FROM user u LEFT JOIN userStatus us ON us.user_id = u.user_id ${whereSql}`,
    params
  );
  const total = Number(countRows[0].n);

  const pageNum = Math.max(1, parseInt(page, 10) || 1);
  const offset = (pageNum - 1) * PAGE_SIZE;
  const rows = await query(
    `SELECT u.user_id, u.user_name, u.first_name, u.last_name, u.email, u.role,
            u.profile_image, u.created_at, u.last_login, COALESCE(us.status, 'active') AS status
       FROM user u
       LEFT JOIN userStatus us ON us.user_id = u.user_id
       ${whereSql}
       ORDER BY u.user_id DESC
       LIMIT ? OFFSET ?`,
    [...params, PAGE_SIZE, offset]
  );

  return {
    users: rows.map(userRow),
    total,
    page: pageNum,
    page_size: PAGE_SIZE,
    pages: Math.max(1, Math.ceil(total / PAGE_SIZE)),
  };
}

// GET /users/:id — full detail for one user. Mirrors view-user.php: the user +
// status row, recent login attempts (keyed by email, like the PHP page), and the
// admin actions taken against this user (from activity_log).
export async function getUserDetail(userId) {
  const rows = await query(
    `SELECT u.user_id, u.user_name, u.first_name, u.last_name, u.email, u.role,
            u.profile_image, u.created_at, u.last_login,
            us.status, us.failed_attempts, us.locked_until, us.archived_at, us.profile_bio,
            us.logging_streak, us.longest_logging_streak, us.last_logging_date
       FROM user u
       LEFT JOIN userStatus us ON us.user_id = u.user_id
      WHERE u.user_id = ? LIMIT 1`,
    [userId]
  );
  const r = rows[0];
  if (!r) throw new AdminActionError('User not found.');

  const [attempts, actions] = await Promise.all([
    query(
      `SELECT ip_address, success, attempted_at
         FROM login_attempts WHERE email = ? ORDER BY attempted_at DESC LIMIT 15`,
      [r.email]
    ),
    query(
      `SELECT a.action_type, a.description, a.created_at, a.user_id AS actor_id, actor.user_name AS actor_name
         FROM activity_log a
         LEFT JOIN user actor ON actor.user_id = a.user_id
        WHERE a.target_table IN ('user', 'userStatus') AND a.target_id = ?
        ORDER BY a.created_at DESC LIMIT 15`,
      [userId]
    ),
  ]);

  return {
    user: {
      ...userRow(r),
      profile_bio: r.profile_bio ?? null,
      failed_attempts: Number(r.failed_attempts ?? 0),
      locked_until: r.locked_until ?? null,
      archived_at: r.archived_at ?? null,
      logging_streak: Number(r.logging_streak ?? 0),
      longest_logging_streak: Number(r.longest_logging_streak ?? 0),
      last_logging_date: r.last_logging_date ?? null,
    },
    login_attempts: attempts.map((a) => ({
      ip_address: a.ip_address,
      success: Boolean(a.success),
      attempted_at: a.attempted_at,
    })),
    admin_actions: actions.map((a) => ({
      action_type: a.action_type,
      description: a.description,
      created_at: a.created_at,
      actor_id: a.actor_id == null ? null : Number(a.actor_id),
      actor_name: a.actor_name ?? null,
    })),
  };
}

// POST /users — create a user from the admin panel. Ports admin/handlers/add_user.php:
// same field set, password policy and uniqueness checks as self-signup, plus the
// admin-only role choice. Creates the user + its default userStatus row, exactly
// like auth.js register, so an admin-created account behaves like a normal one
// (and goes through onboarding on first login).
export async function createUser(adminId, fields = {}) {
  const first = String(fields.first_name ?? '').trim();
  const last = String(fields.last_name ?? '').trim();
  const userName = String(fields.user_name ?? '').trim();
  const email = String(fields.email ?? '').trim().toLowerCase();
  const password = String(fields.password ?? '');
  const confirm = String(fields.confirm_password ?? '');
  const role = fields.role;

  if (!first || !last || !userName || !email || !password || !confirm) {
    throw new AdminActionError('Please fill in all fields.');
  }
  if (password !== confirm) throw new AdminActionError('Passwords do not match.');
  if (password.length < 8) throw new AdminActionError('Password must be at least 8 characters long.');
  if (!PASSWORD_RE.test(password)) {
    throw new AdminActionError('Password must contain at least one uppercase letter, one lowercase letter, and one number.');
  }
  if (!EMAIL_RE.test(email)) throw new AdminActionError('A valid email is required.');
  if (!ROLES.includes(role)) throw new AdminActionError('Invalid role.');

  const dupe = await query(
    'SELECT email FROM user WHERE user_name = ? OR email = ? LIMIT 1',
    [userName, email]
  );
  if (dupe.length) {
    throw new AdminActionError(
      dupe[0].email === email ? 'That email is already in use.' : 'That username is already taken.'
    );
  }

  const hashed = await bcrypt.hash(password, BCRYPT_ROUNDS);
  const result = await query(
    `INSERT INTO user (user_name, first_name, last_name, email, password, role, created_at)
     VALUES (?, ?, ?, ?, ?, ?, NOW())`,
    [userName, first, last, email, hashed, role]
  );
  const userId = result.insertId;

  await query(
    `INSERT INTO userStatus (user_id, status, theme_preference, failed_attempts, locked_until)
     VALUES (?, 'active', 'system', 0, NULL)`,
    [userId]
  );

  await logActivity({
    userId: adminId,
    action: 'admin_create_user',
    targetTable: 'user',
    targetId: userId,
    description: `Created user @${userName} (role=${role})`,
  });

  return getUserDetail(userId);
}

// PATCH /users/:id — edit profile fields, role and status. Mirrors edit_user.php
// including its uniqueness checks and self-protection guards.
export async function updateUser(adminId, userId, fields) {
  if (userId === adminId && fields.role && fields.role !== 'admin') {
    throw new AdminActionError('You cannot remove your own admin role.');
  }
  if (userId === adminId && fields.status && fields.status !== 'active') {
    throw new AdminActionError('You cannot ban or archive your own account.');
  }

  const first = String(fields.first_name ?? '').trim();
  const last = String(fields.last_name ?? '').trim();
  const userName = String(fields.user_name ?? '').trim();
  const email = String(fields.email ?? '').trim().toLowerCase();
  const role = fields.role;
  const status = fields.status;

  if (!userName) throw new AdminActionError('Username is required.');
  if (!EMAIL_RE.test(email)) throw new AdminActionError('A valid email is required.');
  if (!ROLES.includes(role)) throw new AdminActionError('Invalid role.');
  if (!STATUSES.includes(status)) throw new AdminActionError('Invalid status.');

  const exists = await query('SELECT user_id FROM user WHERE user_id = ? LIMIT 1', [userId]);
  if (!exists.length) throw new AdminActionError('User not found.');

  const dupe = await query(
    'SELECT email FROM user WHERE (user_name = ? OR email = ?) AND user_id <> ? LIMIT 1',
    [userName, email, userId]
  );
  if (dupe.length) {
    throw new AdminActionError(
      dupe[0].email === email ? 'That email is already in use.' : 'That username is already taken.'
    );
  }

  await query(
    'UPDATE user SET first_name = ?, last_name = ?, user_name = ?, email = ?, role = ? WHERE user_id = ?',
    [first || null, last || null, userName, email, role, userId]
  );
  await writeStatus(userId, status);
  await logActivity({
    userId: adminId,
    action: 'admin_edit_user',
    targetTable: 'user',
    targetId: userId,
    description: `Edited profile (role=${role}, status=${status})`,
  });
  return getUserDetail(userId);
}

// POST /users/:id/:action — ban | unban | archive | restore. Only writes the
// status; enforcement is automatic (lib/users.js revokes a banned/archived
// session on its next request).
export async function setUserStatus(adminId, userId, action) {
  const status = STATUS_ACTIONS[action];
  if (!status) throw new AdminActionError('Invalid action.');
  if (userId === adminId && (action === 'ban' || action === 'archive')) {
    throw new AdminActionError('You cannot ban or archive your own account.');
  }
  const exists = await query('SELECT user_id FROM user WHERE user_id = ? LIMIT 1', [userId]);
  if (!exists.length) throw new AdminActionError('User not found.');

  await writeStatus(userId, status);
  await logActivity({
    userId: adminId,
    action: `admin_${action}`,
    targetTable: 'userStatus',
    targetId: userId,
    description: `Status set to ${status}`,
  });
}

// POST /users/:id/password — admin sets a new password for a user (account
// recovery / no self-serve reset flow yet, see DEPLOY.md gotcha #7). Same policy
// and bcrypt cost as signup; the new $2b$ hash stays PHP-compatible.
export async function setUserPassword(adminId, userId, password, confirm) {
  const exists = await query('SELECT user_id FROM user WHERE user_id = ? LIMIT 1', [userId]);
  if (!exists.length) throw new AdminActionError('User not found.');

  const pw = String(password ?? '');
  if (pw !== String(confirm ?? '')) throw new AdminActionError('Passwords do not match.');
  if (pw.length < 8) throw new AdminActionError('Password must be at least 8 characters long.');
  if (!PASSWORD_RE.test(pw)) {
    throw new AdminActionError('Password must contain at least one uppercase letter, one lowercase letter, and one number.');
  }

  const hashed = await bcrypt.hash(pw, BCRYPT_ROUNDS);
  await query('UPDATE user SET password = ? WHERE user_id = ?', [hashed, userId]);
  await logActivity({
    userId: adminId,
    action: 'admin_reset_password',
    targetTable: 'user',
    targetId: userId,
    description: 'Reset password',
  });
}

// POST /users/:id/unlock — thin account recovery: clear the lockout counters so
// a locked-out user can sign in again. (No password reset — that flow is unbuilt;
// see DEPLOY.md gotcha #7.)
export async function unlockUser(adminId, userId) {
  const exists = await query('SELECT user_id FROM user WHERE user_id = ? LIMIT 1', [userId]);
  if (!exists.length) throw new AdminActionError('User not found.');

  const hasStatus = await query('SELECT 1 FROM userStatus WHERE user_id = ? LIMIT 1', [userId]);
  if (hasStatus.length) {
    await query('UPDATE userStatus SET failed_attempts = 0, locked_until = NULL WHERE user_id = ?', [userId]);
  } else {
    await query("INSERT INTO userStatus (user_id, status) VALUES (?, 'active')", [userId]);
  }
  await logActivity({
    userId: adminId,
    action: 'admin_unlock',
    targetTable: 'userStatus',
    targetId: userId,
    description: 'Cleared failed attempts and lock',
  });
}

// GET /logs — paginated activity_log viewer with text search + action_type
// filter, joined with the actor. Mirrors getActivityLogsPaginated() in
// admin_data.php. Also returns the distinct action_type list (computed over the
// whole table, independent of the current filter) so the UI can offer a dropdown.
export async function getActivityLogs({ q = '', action = '', page = 1 } = {}) {
  const where = [];
  const params = [];
  const term = String(q).trim();
  if (term) {
    where.push('(a.description LIKE ? OR a.action_type LIKE ? OR u.user_name LIKE ?)');
    const like = `%${term}%`;
    params.push(like, like, like);
  }
  if (String(action).trim()) {
    where.push('a.action_type = ?');
    params.push(String(action).trim());
  }
  const whereSql = where.length ? `WHERE ${where.join(' AND ')}` : '';

  const countRows = await query(
    `SELECT COUNT(*) AS n FROM activity_log a LEFT JOIN user u ON u.user_id = a.user_id ${whereSql}`,
    params
  );
  const total = Number(countRows[0].n);

  const pageNum = Math.max(1, parseInt(page, 10) || 1);
  const offset = (pageNum - 1) * PAGE_SIZE;
  const rows = await query(
    `SELECT a.log_id, a.action_type, a.target_table, a.target_id, a.description, a.created_at,
            a.user_id AS actor_id, u.user_name AS actor_name, u.role AS actor_role
       FROM activity_log a
       LEFT JOIN user u ON u.user_id = a.user_id
       ${whereSql}
       ORDER BY a.created_at DESC, a.log_id DESC
       LIMIT ? OFFSET ?`,
    [...params, PAGE_SIZE, offset]
  );

  const types = await query('SELECT DISTINCT action_type FROM activity_log ORDER BY action_type');

  return {
    logs: rows.map((r) => ({
      log_id: Number(r.log_id),
      action_type: r.action_type,
      target_table: r.target_table ?? null,
      target_id: r.target_id == null ? null : Number(r.target_id),
      description: r.description ?? null,
      created_at: r.created_at,
      actor_id: r.actor_id == null ? null : Number(r.actor_id),
      actor_name: r.actor_name ?? null,
      actor_role: r.actor_role ?? null,
    })),
    total,
    page: pageNum,
    page_size: PAGE_SIZE,
    pages: Math.max(1, Math.ceil(total / PAGE_SIZE)),
    action_types: types.map((t) => t.action_type),
  };
}

// GET /logs/prune-preview — count how many activity_log rows a prune of `days`
// WOULD delete, without touching anything. Lets the UI show the blast radius in
// the confirm dialog before the destructive POST. Same validation as pruneLogs.
export async function previewPrune(days) {
  const d = parseInt(days, 10);
  if (!Number.isFinite(d) || d < 1 || d > 365) {
    throw new AdminActionError('Days must be a whole number between 1 and 365.');
  }
  const countRows = await query(
    `SELECT COUNT(*) AS n FROM activity_log WHERE created_at < (NOW() - INTERVAL ${d} DAY)`
  );
  return { count: Number(countRows[0].n), days: d };
}

// POST /logs/prune — delete activity_log rows older than `days` (1..365, default
// 30 enforced by the route) and record the prune itself. Mirrors
// prune-logs-action.php. `days` is validated to a small integer and interpolated
// directly (parameterising the INTERVAL unit is awkward and this is injection-
// safe once validated). DESTRUCTIVE + irreversible.
export async function pruneLogs(adminId, days) {
  const d = parseInt(days, 10);
  if (!Number.isFinite(d) || d < 1 || d > 365) {
    throw new AdminActionError('Days must be a whole number between 1 and 365.');
  }
  const countRows = await query(
    `SELECT COUNT(*) AS n FROM activity_log WHERE created_at < (NOW() - INTERVAL ${d} DAY)`
  );
  const deleted = Number(countRows[0].n);
  await query(`DELETE FROM activity_log WHERE created_at < (NOW() - INTERVAL ${d} DAY)`);
  await logActivity({
    userId: adminId,
    action: 'admin_prune_logs',
    targetTable: 'activity_log',
    targetId: null,
    description: `Pruned ${deleted} log(s) older than ${d} day(s)`,
  });
  return { deleted, days: d };
}

// ---- Barcode cache (Phase 3) ----------------------------------------------
// Read + manage the shared barcode_products cache and its barcode_scan_log
// analytics, both created in include/migrations/2026_05_27_add_barcode_scanner.sql
// and written by server/src/lib/barcode.js. Lets an admin inspect what
// OpenFoodFacts data has been cached, see scan coverage, and evict a bad/stale
// entry (the next scan of that barcode then re-fetches from OpenFoodFacts).
export const BARCODE_SOURCES = ['openfoodfacts', 'user_submitted'];
// Whitelisted sort keys -> ORDER BY clause. The value is never user-controlled
// (we look it up by key), so interpolating it is injection-safe.
const BARCODE_SORTS = {
  popular: 'p.lookup_count DESC, p.updated_at DESC',
  recent: 'p.updated_at DESC',
  oldest: 'p.created_at ASC',
  name: 'p.product_name ASC, p.barcode ASC',
};

function barcodeRow(r) {
  const num = (v) => (v == null ? null : Number(v));
  return {
    barcode: r.barcode,
    product_name: r.product_name ?? null,
    brand: r.brand ?? null,
    serving_size: r.serving_size ?? null,
    kcal_per_serving: num(r.kcal_per_serving),
    kcal_per_100g: num(r.kcal_per_100g),
    protein_per_serving: num(r.protein_per_serving),
    carbs_per_serving: num(r.carbs_per_serving),
    fat_per_serving: num(r.fat_per_serving),
    sugar_per_serving: num(r.sugar_per_serving),
    image_url: r.image_url ?? null,
    source: r.source ?? 'openfoodfacts',
    submitted_by_user_id: num(r.submitted_by_user_id),
    lookup_count: Number(r.lookup_count ?? 0),
    created_at: r.created_at ?? null,
    updated_at: r.updated_at ?? null,
  };
}

// Cache-wide headline numbers for the list page's stats strip: how many products
// are cached, and the scan-result breakdown (cache_hit / api_found / api_miss /
// api_error) with a derived cache hit-rate. The scan-log half is wrapped in
// try/catch so the product list still loads on a DB where barcode_scan_log is
// missing or empty (it's analytics, not core data).
async function barcodeCacheStats() {
  const productRows = await query('SELECT COUNT(*) AS n FROM barcode_products');
  const stats = {
    products: Number(productRows[0].n),
    total_scans: 0,
    cache_hit: 0,
    api_found: 0,
    api_miss: 0,
    api_error: 0,
    hit_rate: null,
  };
  try {
    const byResult = await query('SELECT result, COUNT(*) AS n FROM barcode_scan_log GROUP BY result');
    for (const row of byResult) {
      const n = Number(row.n);
      stats.total_scans += n;
      if (Object.prototype.hasOwnProperty.call(stats, row.result)) stats[row.result] = n;
    }
    if (stats.total_scans > 0) stats.hit_rate = Math.round((stats.cache_hit / stats.total_scans) * 100);
  } catch (e) {
    console.error('barcode_scan_log stats failed:', e.message);
  }
  return stats;
}

// GET /barcodes — paginated cache list with optional text search (barcode /
// product name / brand), source filter and sort. Returns the available sources +
// cache-wide stats alongside the page so the view can render filters and a
// headline strip without a second round-trip.
export async function listBarcodeCache({ q = '', source = '', sort = 'popular', page = 1 } = {}) {
  const where = [];
  const params = [];
  const term = String(q).trim();
  if (term) {
    where.push('(p.barcode LIKE ? OR p.product_name LIKE ? OR p.brand LIKE ?)');
    const like = `%${term}%`;
    params.push(like, like, like);
  }
  if (BARCODE_SOURCES.includes(source)) {
    where.push('p.source = ?');
    params.push(source);
  }
  const whereSql = where.length ? `WHERE ${where.join(' AND ')}` : '';
  const orderSql = BARCODE_SORTS[sort] || BARCODE_SORTS.popular;

  const countRows = await query(`SELECT COUNT(*) AS n FROM barcode_products p ${whereSql}`, params);
  const total = Number(countRows[0].n);

  const pageNum = Math.max(1, parseInt(page, 10) || 1);
  const offset = (pageNum - 1) * PAGE_SIZE;
  const rows = await query(
    `SELECT p.barcode, p.product_name, p.brand, p.serving_size,
            p.kcal_per_serving, p.kcal_per_100g,
            p.protein_per_serving, p.carbs_per_serving, p.fat_per_serving, p.sugar_per_serving,
            p.image_url, p.source, p.submitted_by_user_id, p.lookup_count, p.created_at, p.updated_at
       FROM barcode_products p
       ${whereSql}
       ORDER BY ${orderSql}
       LIMIT ? OFFSET ?`,
    [...params, PAGE_SIZE, offset]
  );

  return {
    products: rows.map(barcodeRow),
    total,
    page: pageNum,
    page_size: PAGE_SIZE,
    pages: Math.max(1, Math.ceil(total / PAGE_SIZE)),
    sources: BARCODE_SOURCES,
    stats: await barcodeCacheStats(),
  };
}

// GET /barcodes/:barcode — one cached product (plus the submitter's handle for
// user_submitted rows) with its recent scans and per-result scan counts. The
// scan-log half is best-effort (see barcodeCacheStats).
export async function getBarcodeDetail(barcode) {
  const code = String(barcode).trim();
  const rows = await query(
    `SELECT p.barcode, p.product_name, p.brand, p.serving_size,
            p.kcal_per_serving, p.kcal_per_100g,
            p.protein_per_serving, p.carbs_per_serving, p.fat_per_serving, p.sugar_per_serving,
            p.image_url, p.source, p.submitted_by_user_id, p.lookup_count, p.created_at, p.updated_at,
            sub.user_name AS submitted_by_name
       FROM barcode_products p
       LEFT JOIN user sub ON sub.user_id = p.submitted_by_user_id
      WHERE p.barcode = ? LIMIT 1`,
    [code]
  );
  const r = rows[0];
  if (!r) throw new AdminActionError('Cached product not found.');

  let scans = [];
  const scanStats = { total: 0, cache_hit: 0, api_found: 0, api_miss: 0, api_error: 0 };
  try {
    scans = await query(
      `SELECT s.scan_id, s.user_id, s.result, s.latency_ms, s.created_at, u.user_name AS user_name
         FROM barcode_scan_log s
         LEFT JOIN user u ON u.user_id = s.user_id
        WHERE s.barcode = ?
        ORDER BY s.created_at DESC, s.scan_id DESC
        LIMIT 20`,
      [code]
    );
    const counts = await query(
      'SELECT result, COUNT(*) AS n FROM barcode_scan_log WHERE barcode = ? GROUP BY result',
      [code]
    );
    for (const c of counts) {
      const n = Number(c.n);
      scanStats.total += n;
      if (Object.prototype.hasOwnProperty.call(scanStats, c.result)) scanStats[c.result] = n;
    }
  } catch (e) {
    console.error('barcode_scan_log detail failed:', e.message);
  }

  return {
    product: { ...barcodeRow(r), submitted_by_name: r.submitted_by_name ?? null },
    scans: scans.map((s) => ({
      scan_id: Number(s.scan_id),
      user_id: s.user_id == null ? null : Number(s.user_id),
      user_name: s.user_name ?? null,
      result: s.result,
      latency_ms: s.latency_ms == null ? null : Number(s.latency_ms),
      created_at: s.created_at,
    })),
    scan_stats: scanStats,
  };
}

// POST /barcodes/:barcode/evict — drop a cached product so the next scan re-fetches
// fresh data from OpenFoodFacts (e.g. when OFF returned wrong/stale nutrition).
// The barcode_scan_log history is deliberately KEPT — those scans really happened
// and the FK there is on user, not barcode, so nothing cascades.
export async function evictBarcode(adminId, barcode) {
  const code = String(barcode).trim();
  const rows = await query('SELECT barcode, product_name FROM barcode_products WHERE barcode = ? LIMIT 1', [code]);
  if (!rows.length) throw new AdminActionError('Cached product not found.');

  await query('DELETE FROM barcode_products WHERE barcode = ?', [code]);
  await logActivity({
    userId: adminId,
    action: 'admin_evict_barcode',
    targetTable: 'barcode_products',
    targetId: null,
    description: `Evicted cached barcode ${code}${rows[0].product_name ? ` (${rows[0].product_name})` : ''}`,
  });
  return { barcode: code };
}
