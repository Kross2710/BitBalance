// Integration tests for the admin API: real Express app (supertest) against a
// real MySQL/MariaDB schema. Self-contained — seeds its own throwaway users and
// cleans them up, so it is safe to run against the shared local dev DB. The one
// DESTRUCTIVE assertion (a real prune DELETE) only runs when BB_TEST_DESTRUCTIVE=1
// (set in CI, which uses a fresh throwaway DB), never against the dev DB.
//
// The whole suite SKIPS cleanly when no database is reachable, so `npm test` on
// a machine without a DB still passes (unit tests carry the no-DB coverage).
// Load .env BEFORE db.js so its pool is created with the configured DB_* (locally
// this points at the XAMPP test DB; CI sets DB_* in the job env, which dotenv
// won't override). Without this, importing db.js here (for the gate) would create
// a pool with no database selected.
import 'dotenv/config';
import { describe, it, expect, beforeAll, afterAll } from 'vitest';
import bcrypt from 'bcryptjs';
import crypto from 'node:crypto';
import { pool, query } from '../../src/db.js';

let dbUp = false;
try {
  // Require a real BitBalance schema (a selected DB with the `user` table), not
  // just a reachable server: `SELECT 1` would pass with no database selected and
  // the seed would then fail with "No database selected". This way the suite
  // SKIPS cleanly whenever DB_* is missing or points at an empty database.
  await pool.query('SELECT 1 FROM user LIMIT 1');
  dbUp = true;
} catch {
  try { await pool.end(); } catch { /* ignore */ }
}

const suite = dbUp ? describe : describe.skip;

suite('admin API (integration)', () => {
  let supertest, app, sessionStore, agent;
  const tag = crypto.randomBytes(4).toString('hex');
  const adminEmail = `bbtest_admin_${tag}@citest.local`;
  const targetEmail = `bbtest_target_${tag}@citest.local`;
  const regularEmail = `bbtest_regular_${tag}@citest.local`;
  const PASSWORD = 'Test1234!';
  const ids = [];
  let adminId, targetId, regularId;

  async function seedUser(role, label) {
    const hash = await bcrypt.hash(PASSWORD, 10);
    const userName = `bbt_${label}_${tag}`;
    const email = `bbtest_${label}_${tag}@citest.local`;
    const r = await query(
      `INSERT INTO user (user_name, first_name, last_name, email, password, role, created_at)
       VALUES (?, 'BB', 'Test', ?, ?, ?, NOW())`,
      [userName, email, hash, role]
    );
    const id = r.insertId;
    await query(
      `INSERT INTO userStatus (user_id, status, theme_preference, failed_attempts, locked_until)
       VALUES (?, 'active', 'system', 0, NULL)`,
      [id]
    );
    ids.push(id);
    return id;
  }

  beforeAll(async () => {
    supertest = (await import('supertest')).default;
    const appMod = await import('../../src/app.js');
    app = appMod.default;
    sessionStore = appMod.sessionStore;

    adminId = await seedUser('admin', 'admin');
    targetId = await seedUser('regular', 'target'); // a regular user we mutate
    regularId = await seedUser('regular', 'regular');

    agent = supertest.agent(app);
    const res = await agent
      .post('/api/auth/login')
      .set('X-Requested-With', 'XMLHttpRequest')
      .send({ email: adminEmail, password: PASSWORD });
    expect(res.status).toBe(200);
    expect(res.body.data.role).toBe('admin');
  });

  afterAll(async () => {
    if (ids.length) {
      const ph = ids.map(() => '?').join(',');
      await query(`DELETE FROM activity_log WHERE target_id IN (${ph}) OR user_id IN (${ph})`, [...ids, ...ids]).catch(() => {});
      await query(`DELETE FROM userStatus WHERE user_id IN (${ph})`, ids).catch(() => {});
      await query(`DELETE FROM user WHERE user_id IN (${ph})`, ids).catch(() => {});
    }
    await query('DELETE FROM login_attempts WHERE email IN (?, ?, ?)', [adminEmail, targetEmail, regularEmail]).catch(() => {});
    await sessionStore.close().catch(() => {});
    await pool.end().catch(() => {});
  });

  it('GET /summary returns headline counts', async () => {
    const res = await agent.get('/api/admin/summary');
    expect(res.status).toBe(200);
    expect(res.body.ok).toBe(true);
    expect(res.body.data.users).toBeGreaterThanOrEqual(3);
    expect(res.body.data.admins).toBeGreaterThanOrEqual(1);
    expect(typeof res.body.data.activity_log_rows).toBe('number');
  });

  it('GET /users finds a seeded user via search', async () => {
    const res = await agent.get(`/api/admin/users?q=bbtest_target_${tag}`);
    expect(res.status).toBe(200);
    const found = res.body.data.users.find((u) => u.user_id === targetId);
    expect(found).toBeTruthy();
    expect(found.email).toBe(targetEmail);
    expect(found.status).toBe('active');
  });

  it('bans then unbans a user (status writes round-trip)', async () => {
    const ban = await agent.post(`/api/admin/users/${targetId}/ban`).set('X-Requested-With', 'XMLHttpRequest');
    expect(ban.status).toBe(200);
    expect(ban.body.data.user.status).toBe('banned');

    const unban = await agent.post(`/api/admin/users/${targetId}/unban`).set('X-Requested-With', 'XMLHttpRequest');
    expect(unban.status).toBe(200);
    expect(unban.body.data.user.status).toBe('active');
  });

  it('unlocks a locked-out account', async () => {
    await query(
      'UPDATE userStatus SET failed_attempts = 3, locked_until = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE user_id = ?',
      [targetId]
    );
    const res = await agent.post(`/api/admin/users/${targetId}/unlock`).set('X-Requested-With', 'XMLHttpRequest');
    expect(res.status).toBe(200);
    expect(res.body.data.user.failed_attempts).toBe(0);
    expect(res.body.data.user.locked_until).toBeNull();
  });

  it('blocks an admin from removing their own admin role (self-guard)', async () => {
    const res = await agent
      .patch(`/api/admin/users/${adminId}`)
      .set('X-Requested-With', 'XMLHttpRequest')
      .send({ user_name: `bbt_admin_${tag}`, email: adminEmail, first_name: 'BB', last_name: 'Test', role: 'regular', status: 'active' });
    expect(res.status).toBe(422);
    expect(res.body.message).toMatch(/admin role/i);
  });

  it('rejects a mutation missing the CSRF header', async () => {
    const res = await agent.post(`/api/admin/users/${targetId}/ban`); // no X-Requested-With
    expect(res.status).toBe(403);
    expect(res.body.message).toMatch(/origin/i);
  });

  it('records admin actions in the activity log', async () => {
    const res = await agent.get('/api/admin/logs');
    expect(res.status).toBe(200);
    const mine = res.body.data.logs.filter((l) => l.target_id === targetId);
    expect(mine.length).toBeGreaterThan(0);
    expect(res.body.data.action_types).toContain('admin_ban');
  });

  it('validates the prune day range (1..365)', async () => {
    const lo = await agent.post('/api/admin/logs/prune').set('X-Requested-With', 'XMLHttpRequest').send({ days: 0 });
    expect(lo.status).toBe(422);
    const hi = await agent.post('/api/admin/logs/prune').set('X-Requested-With', 'XMLHttpRequest').send({ days: 999 });
    expect(hi.status).toBe(422);
  });

  it('previews a prune without deleting', async () => {
    await query(
      `INSERT INTO activity_log (user_id, action_type, target_table, target_id, description, created_at)
       VALUES (?, 'bbtest_old', 'user', ?, 'old row', DATE_SUB(NOW(), INTERVAL 400 DAY))`,
      [adminId, targetId]
    );
    const res = await agent.get('/api/admin/logs/prune-preview?days=365');
    expect(res.status).toBe(200);
    expect(res.body.data.count).toBeGreaterThanOrEqual(1);
    const still = await query("SELECT COUNT(*) AS n FROM activity_log WHERE action_type = 'bbtest_old' AND target_id = ?", [targetId]);
    expect(Number(still[0].n)).toBeGreaterThanOrEqual(1);
  });

  // DESTRUCTIVE: real DELETE. Only against a throwaway DB (CI sets the flag).
  const destructive = process.env.BB_TEST_DESTRUCTIVE === '1' ? it : it.skip;
  destructive('prune deletes only rows older than the window', async () => {
    const res = await agent.post('/api/admin/logs/prune').set('X-Requested-With', 'XMLHttpRequest').send({ days: 365 });
    expect(res.status).toBe(200);
    expect(res.body.data.deleted).toBeGreaterThanOrEqual(1);
    const old = await query("SELECT COUNT(*) AS n FROM activity_log WHERE action_type = 'bbtest_old'");
    expect(Number(old[0].n)).toBe(0);
    // A fresh row (the prune logged itself) survives.
    const recent = await query("SELECT COUNT(*) AS n FROM activity_log WHERE action_type = 'admin_prune_logs'");
    expect(Number(recent[0].n)).toBeGreaterThanOrEqual(1);
  });

  it('denies a non-admin user (role guard)', async () => {
    const reg = supertest.agent(app);
    const login = await reg.post('/api/auth/login').set('X-Requested-With', 'XMLHttpRequest').send({ email: regularEmail, password: PASSWORD });
    expect(login.status).toBe(200);
    expect(login.body.data.role).toBe('regular');
    const res = await reg.get('/api/admin/summary');
    expect(res.status).toBe(403);
    expect(res.body.message).toMatch(/admin/i);
  });
});
