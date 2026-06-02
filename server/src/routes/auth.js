// Auth routes — ports api/auth/login.php, logout.php and api/me.php.
// NOTE: persistent "remember me" tokens are intentionally NOT ported yet
// (they need include/handlers/remember_token.php + its DB table). Tracked in
// MIGRATION.md. Everything else mirrors the legacy lockout behaviour.
import { Router } from 'express';
import bcrypt from 'bcryptjs';
import { query } from '../db.js';
import { publicUser, currentUserRow } from '../lib/users.js';
import { generateHandle } from '../lib/handle.js';

const router = Router();

const MAX_ATTEMPTS = 3;
const LOCK_MINUTES = 60;
const BCRYPT_ROUNDS = 10;
const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const PASSWORD_RE = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/;

router.post('/login', async (req, res, next) => {
  try {
    const email = (req.body?.email ?? '').trim();
    const password = req.body?.password ?? '';

    if (email === '' || password === '') {
      return res.status(422).json({ ok: false, data: null, message: 'Please fill in all fields.' });
    }

    const rows = await query(
      `SELECT u.user_id, u.user_name, u.first_name, u.last_name, u.email, u.password, u.role, u.profile_image,
              us.status, us.failed_attempts, us.locked_until, us.theme_preference,
              CASE
                  WHEN NOT EXISTS (SELECT 1 FROM userGoal ug WHERE ug.user_id = u.user_id LIMIT 1)
                    OR NOT EXISTS (SELECT 1 FROM userPhysicalInfo upi WHERE upi.user_id = u.user_id LIMIT 1)
                  THEN 1 ELSE 0
              END AS needs_onboarding
         FROM user u
         JOIN userStatus us ON u.user_id = us.user_id
        WHERE u.email = ?
        LIMIT 1`,
      [email]
    );

    const fail = (msg, code) => res.status(code).json({ ok: false, data: null, message: msg });

    const user = rows[0];
    if (!user) return fail('Invalid email or password.', 401);

    const now = new Date();
    let lockedUntil = user.locked_until ? new Date(user.locked_until) : null;

    if (lockedUntil && now < lockedUntil) {
      const diffMs = lockedUntil - now;
      const minutes = Math.floor(diffMs / 60000);
      const seconds = Math.floor((diffMs % 60000) / 1000);
      return fail(`Account is locked. Try again in ${minutes} minutes and ${seconds} seconds.`, 423);
    }

    if (user.status === 'archived') return fail('This account has been archived. Please contact support.', 403);
    if (user.status === 'banned') return fail('This account has been banned. Please contact support.', 403);

    // Lock window elapsed — reset the counter before re-checking the password.
    if (lockedUntil && now >= lockedUntil) {
      await query('UPDATE userStatus SET failed_attempts = 0, locked_until = NULL WHERE user_id = ?', [user.user_id]);
      user.failed_attempts = 0;
    }

    const ok = await bcrypt.compare(password, user.password);
    if (!ok) {
      const attempts = Number(user.failed_attempts) + 1;
      if (attempts >= MAX_ATTEMPTS) {
        const until = new Date(now.getTime() + LOCK_MINUTES * 60000);
        await query('UPDATE userStatus SET failed_attempts = ?, locked_until = ? WHERE user_id = ?', [
          attempts,
          until.toISOString().slice(0, 19).replace('T', ' '),
          user.user_id,
        ]);
        return fail('Account locked due to 3 failed login attempts. Try again in 1 hour.', 423);
      }
      await query('UPDATE userStatus SET failed_attempts = ? WHERE user_id = ?', [attempts, user.user_id]);
      return fail(`Invalid email or password. ${MAX_ATTEMPTS - attempts} attempts remaining.`, 401);
    }

    await query('UPDATE userStatus SET failed_attempts = 0, locked_until = NULL WHERE user_id = ?', [user.user_id]);
    await query('UPDATE user SET last_login = NOW() WHERE user_id = ?', [user.user_id]);

    // Mirror session_regenerate_id(true): rotate the session id on privilege change.
    req.session.regenerate((err) => {
      if (err) return next(err);
      req.session.user = publicUser(user);
      res.json({ ok: true, data: publicUser(user), message: null });
    });
  } catch (err) {
    next(err);
  }
});

// Ports api/auth/register.php. CAPTCHA is intentionally dropped for the API
// (mirrors the PHP note) — add token-based abuse control later. bcryptjs hashes
// with $2b$, which the legacy PHP password_verify reads fine, and vice versa.
router.post('/register', async (req, res, next) => {
  try {
    const firstName = (req.body?.first_name ?? '').trim();
    const lastName = (req.body?.last_name ?? '').trim();
    const email = (req.body?.email ?? '').trim();
    const password = req.body?.password ?? '';
    const confirmPassword = req.body?.confirm_password ?? '';

    const fail = (msg, code) => res.status(code).json({ ok: false, data: null, message: msg });

    if (!firstName || !lastName || !email || !password || !confirmPassword) {
      return fail('Please fill in all fields.', 422);
    }
    if (password !== confirmPassword) return fail('Passwords do not match.', 422);
    if (password.length < 8) return fail('Password must be at least 8 characters long.', 422);
    if (!PASSWORD_RE.test(password)) {
      return fail('Password must contain at least one uppercase letter, one lowercase letter, and one number.', 422);
    }
    if (!EMAIL_RE.test(email)) return fail('Please enter a valid email address.', 422);

    const existing = await query('SELECT user_id FROM user WHERE email = ?', [email]);
    if (existing.length) return fail('An account with this email already exists.', 409);

    const username = await generateHandle(firstName);
    const hashed = await bcrypt.hash(password, BCRYPT_ROUNDS);

    const result = await query(
      `INSERT INTO user (user_name, first_name, last_name, email, password, role, created_at)
       VALUES (?, ?, ?, ?, ?, 'regular', NOW())`,
      [username, firstName, lastName, email, hashed]
    );
    const userId = result.insertId;

    await query(
      `INSERT INTO userStatus (user_id, status, theme_preference, failed_attempts, locked_until)
       VALUES (?, 'active', 'system', 0, NULL)`,
      [userId]
    );

    const userRow = {
      user_id: userId,
      user_name: username,
      first_name: firstName,
      last_name: lastName,
      email,
      role: 'regular',
      profile_image: null,
      theme_preference: 'system',
      needs_onboarding: 1,
    };

    req.session.regenerate((err) => {
      if (err) return next(err);
      req.session.user = publicUser(userRow);
      res.json({ ok: true, data: publicUser(userRow), message: null });
    });
  } catch (err) {
    next(err);
  }
});

router.post('/logout', (req, res) => {
  req.session.destroy(() => {
    res.clearCookie('bb.sid');
    res.json({ ok: true, data: null, message: null });
  });
});

// Ports api/me.php — who am I? Returns null data (not 401) for guests so the
// SPA can boot without throwing on first load.
router.get('/me', async (req, res, next) => {
  try {
    const row = await currentUserRow(req);
    if (!row || row.inactive) {
      return res.json({ ok: true, data: null, message: null });
    }
    res.json({ ok: true, data: publicUser(row), message: null });
  } catch (err) {
    next(err);
  }
});

export default router;
