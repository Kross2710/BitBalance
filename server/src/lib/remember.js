// Persistent "Remember Me" login tokens — ports include/handlers/remember_token.php.
//
// Selector/validator (split-cookie) pattern:
//   - The cookie holds "<selector>:<validator>".
//   - The DB (auth_token) stores the selector in clear and only a SHA-256 hash
//     of the validator, so a DB leak cannot be replayed as a login.
//   - Lookups are by the unique selector (indexed); the validator is compared
//     with crypto.timingSafeEqual to avoid timing attacks.
//
// This signs a returning user back in for up to REMEMBER_LIFETIME, long after
// their short-lived express-session has expired.
import crypto from 'node:crypto';
import { query } from '../db.js';

export const REMEMBER_COOKIE = 'bb_remember';
const REMEMBER_LIFETIME_MS = 1000 * 60 * 60 * 24 * 30; // 30 days

// Mirror the session cookie hardening (httpOnly, Lax, secure in prod).
function cookieOpts() {
  return {
    httpOnly: true,
    sameSite: 'lax',
    secure: process.env.COOKIE_SECURE === 'true',
    path: '/',
  };
}

// Dependency-free cookie read (express-session doesn't expose other cookies and
// we don't pull in cookie-parser just for this).
function readCookie(req, name) {
  const header = req.headers?.cookie;
  if (!header) return null;
  for (const part of header.split(';')) {
    const idx = part.indexOf('=');
    if (idx === -1) continue;
    if (part.slice(0, idx).trim() === name) {
      return decodeURIComponent(part.slice(idx + 1).trim());
    }
  }
  return null;
}

function parseCookie(req) {
  const raw = readCookie(req, REMEMBER_COOKIE);
  if (!raw) return null;
  const idx = raw.indexOf(':');
  if (idx === -1) return null;
  const selector = raw.slice(0, idx);
  const validator = raw.slice(idx + 1);
  if (selector.length !== 24 || validator.length !== 64) return null;
  if (!/^[0-9a-f]+$/.test(selector) || !/^[0-9a-f]+$/.test(validator)) return null;
  return { selector, validator };
}

const sha256 = (s) => crypto.createHash('sha256').update(s).digest('hex');

function setCookie(res, value) {
  res.cookie(REMEMBER_COOKIE, value, { ...cookieOpts(), maxAge: REMEMBER_LIFETIME_MS });
}

export function clearRememberCookie(res) {
  res.clearCookie(REMEMBER_COOKIE, cookieOpts());
}

// Issue a fresh token right after a successful password login when the user
// opted in. A failed token must never block an otherwise good login.
export async function createRemember(res, userId, userAgent) {
  try {
    const selector = crypto.randomBytes(12).toString('hex'); // 24 hex, stored in clear
    const validator = crypto.randomBytes(32).toString('hex'); // 64 hex, secret
    await query(
      `INSERT INTO auth_token (user_id, selector, validator_hash, expires, user_agent)
       VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY), ?)`,
      [userId, selector, sha256(validator), (userAgent || '').slice(0, 255) || null]
    );
    setCookie(res, `${selector}:${validator}`);
    return true;
  } catch (e) {
    console.error('createRemember failed:', e.message);
    return false;
  }
}

// Attempt to sign the user in from their remember-me cookie. On success it
// populates req.session.user (minimal { user_id }; currentUserRow re-hydrates
// the full public shape), slides the token expiry forward, refreshes the
// cookie, and returns true. On any failure it drops the offending cookie.
//
// The validator is intentionally NOT rotated — that keeps parallel requests
// (page + AJAX) from racing each other out.
export async function tryRememberLogin(req, res) {
  if (req.session?.user) return true;
  const parsed = parseCookie(req);
  if (!parsed) return false;

  try {
    const rows = await query(
      `SELECT t.id, t.user_id, t.validator_hash, us.status
         FROM auth_token t
         JOIN userStatus us ON us.user_id = t.user_id
        WHERE t.selector = ? AND t.expires > NOW()
        LIMIT 1`,
      [parsed.selector]
    );
    const row = rows[0];
    if (!row) {
      clearRememberCookie(res); // unknown or expired selector
      return false;
    }

    // Constant-time compare of the secret validator.
    const stored = Buffer.from(row.validator_hash, 'hex');
    const given = Buffer.from(sha256(parsed.validator), 'hex');
    if (stored.length !== given.length || !crypto.timingSafeEqual(stored, given)) {
      // Selector matched but validator did not: corrupt or forged. Revoke it.
      await query('DELETE FROM auth_token WHERE id = ?', [row.id]);
      clearRememberCookie(res);
      return false;
    }

    // Never auto-login a disabled account; revoke all of its tokens.
    if (row.status === 'archived' || row.status === 'banned') {
      await query('DELETE FROM auth_token WHERE user_id = ?', [row.user_id]);
      clearRememberCookie(res);
      return false;
    }

    req.session.user = { user_id: Number(row.user_id) };
    await query('UPDATE auth_token SET expires = DATE_ADD(NOW(), INTERVAL 30 DAY), last_used_at = NOW() WHERE id = ?', [row.id]);
    setCookie(res, `${parsed.selector}:${parsed.validator}`);
    return true;
  } catch (e) {
    console.error('tryRememberLogin failed:', e.message);
    return false;
  }
}

// Revoke every remember-me token for a user — for a future "log out of all
// devices" action (or on ban/password change). Backlog: no UI wired yet.
export async function revokeAllForUser(userId) {
  try {
    await query('DELETE FROM auth_token WHERE user_id = ?', [userId]);
  } catch (e) {
    console.error('revokeAllForUser failed:', e.message);
  }
}

// Revoke the token referenced by the current request's cookie (logout) and drop
// the cookie from the browser.
export async function forgetRemember(req, res) {
  const parsed = parseCookie(req);
  if (parsed) {
    try {
      await query('DELETE FROM auth_token WHERE selector = ?', [parsed.selector]);
    } catch (e) {
      console.error('forgetRemember failed:', e.message);
    }
  }
  clearRememberCookie(res);
}
