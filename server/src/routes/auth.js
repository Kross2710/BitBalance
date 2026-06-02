// Auth routes — ports api/auth/login.php, logout.php and api/me.php, including
// persistent "remember me" tokens (see lib/remember.js). Mirrors the legacy
// lockout behaviour.
import { Router } from 'express';
import crypto from 'node:crypto';
import bcrypt from 'bcryptjs';
import { query } from '../db.js';
import { publicUser, currentUserRow } from '../lib/users.js';
import { generateHandle } from '../lib/handle.js';
import { createRemember, forgetRemember } from '../lib/remember.js';
import {
  googleConfigured,
  googleRedirectUri,
  googleAuthorizeUrl,
  exchangeCodeForToken,
  fetchGoogleProfile,
  findOrCreateGoogleUser,
} from '../lib/google.js';

const router = Router();

// Where the SPA lives, for the full-page redirects the OAuth flow ends with.
const clientUrl = (path) => (process.env.CLIENT_ORIGIN || 'http://localhost:5173') + path;

const MAX_ATTEMPTS = 3;
const LOCK_MINUTES = 60;
const BCRYPT_ROUNDS = 10;
const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const PASSWORD_RE = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/;

router.post('/login', async (req, res, next) => {
  try {
    const email = (req.body?.email ?? '').trim();
    const password = req.body?.password ?? '';
    const remember = req.body?.remember === true || req.body?.remember === 'true' || req.body?.remember === 1;

    if (email === '' || password === '') {
      return res.status(422).json({ ok: false, data: null, message: 'Please fill in all fields.' });
    }

    const rows = await query(
      `SELECT u.user_id, u.user_name, u.first_name, u.last_name, u.email, u.password, u.role, u.profile_image,
              us.status, us.failed_attempts, us.locked_until, us.theme_preference, us.language_preference,
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
    req.session.regenerate(async (err) => {
      if (err) return next(err);
      req.session.user = publicUser(user);
      // Opt-in 30-day persistent login (never blocks the response on failure).
      if (remember) await createRemember(res, user.user_id, req.headers['user-agent']);
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
      language_preference: 'en',
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

router.post('/logout', async (req, res) => {
  // Revoke + drop the remember-me token (if any) before tearing the session down.
  await forgetRemember(req, res);
  req.session.destroy(() => {
    res.clearCookie('bb.sid');
    res.json({ ok: true, data: null, message: null });
  });
});

// Which third-party sign-in providers are available, so the login/signup views
// can hide buttons that are not configured (graceful degradation, like PHP).
router.get('/providers', (req, res) => {
  res.json({ ok: true, data: { google: googleConfigured() }, message: null });
});

// --- Sign in with Google ----------------------------------------------------
// Step 1: build a CSRF state token and redirect to Google's consent screen.
// Ports google_auth.php. These two endpoints are full-page redirects (browser
// navigations), so they speak Location headers, not the JSON envelope.
router.get('/google', (req, res) => {
  if (req.session?.user) return res.redirect(clientUrl('/dashboard'));
  if (!googleConfigured()) {
    return res.redirect(clientUrl('/login?error=' + encodeURIComponent('Google sign-in is not configured yet.')));
  }

  const state = crypto.randomBytes(16).toString('hex');
  const origin = req.query.from === 'signup' ? 'signup' : 'login';
  req.session.googleOAuth = { state, origin };

  res.redirect(googleAuthorizeUrl(googleRedirectUri(req), state));
});

// Step 2: Google redirects here with ?code & ?state. Verify state, exchange the
// code, read the profile, find-or-create the account, then start the session.
// Ports google_callback.php.
router.get('/google/callback', async (req, res, next) => {
  const saved = req.session?.googleOAuth || {};
  if (req.session) delete req.session.googleOAuth;
  const errorPath = saved.origin === 'signup' ? '/signup' : '/login';
  const failRedirect = (msg) => res.redirect(clientUrl(errorPath + '?error=' + encodeURIComponent(msg)));

  try {
    if (req.session?.user) return res.redirect(clientUrl('/dashboard'));
    if (!googleConfigured()) return failRedirect('Google sign-in is not configured yet.');

    const state = String(req.query.state || '');
    if (
      !state ||
      !saved.state ||
      state.length !== saved.state.length ||
      !crypto.timingSafeEqual(Buffer.from(state), Buffer.from(saved.state))
    ) {
      return failRedirect('Sign-in session expired. Please try again.');
    }
    if (req.query.error) return failRedirect('Google sign-in was cancelled.');

    const code = req.query.code || '';
    if (!code) return failRedirect('Authorization code missing. Please try again.');

    const redirectUri = googleRedirectUri(req);
    const accessToken = await exchangeCodeForToken(code, redirectUri);
    const g = await fetchGoogleProfile(accessToken);

    // Google must have verified the email before we trust it for linking.
    if (!g.emailVerified) return failRedirect('Your Google email is not verified, so we cannot sign you in.');

    let userId;
    try {
      ({ userId } = await findOrCreateGoogleUser(g));
    } catch (err) {
      console.error('Google find-or-create:', err);
      return failRedirect('Something went wrong creating your account. Please try again.');
    }

    // Rotate the session id now that we are authenticated (session fixation).
    req.session.regenerate(async (err) => {
      if (err) return next(err);
      try {
        req.session.user = { user_id: userId };
        // Re-hydrate the full public user + read status/onboarding. Handles
        // archived/banned by destroying the session and returning inactive.
        const row = await currentUserRow(req);
        if (!row || row.inactive) return failRedirect('This account is not available. Please contact support.');

        await query('UPDATE user SET last_login = NOW() WHERE user_id = ?', [userId]);

        // New accounts (no goal/physical info) land on onboarding; others go home.
        const dest = row.needs_onboarding ? '/onboarding' : '/dashboard';
        req.session.save(() => res.redirect(clientUrl(dest)));
      } catch (e) {
        next(e);
      }
    });
  } catch (err) {
    console.error('Google callback:', err);
    failRedirect('Google sign-in failed. Please try again.');
  }
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
