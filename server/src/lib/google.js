// "Sign in with Google" helpers — ports include/handlers/google_oauth.php.
//
// Server-side Authorization Code flow (same as the PHP app): the browser is
// redirected to Google, comes back to our callback with a code, and we exchange
// it for an access token then read the profile from the userinfo endpoint. We
// never decode the id_token JWT, so there are no extra dependencies — global
// fetch (Node 18+) replaces PHP's curl.
//
// Account linking mirrors PHP exactly: match by (provider, provider_uid) first,
// then by verified email to an existing local account, otherwise create a fresh
// account with an unusable random password so password login can never match.
import bcrypt from 'bcryptjs';
import crypto from 'node:crypto';
import { pool, query } from '../db.js';
import { generateHandle } from './handle.js';

const AUTHORIZE_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
const TOKEN_URL = 'https://oauth2.googleapis.com/token';
const USERINFO_URL = 'https://openidconnect.googleapis.com/v1/userinfo';

const BCRYPT_ROUNDS = 10;

// True only when both credentials are present. Used to hide the Google buttons
// and short-circuit the routes when the feature is not configured yet.
export function googleConfigured() {
  return Boolean(process.env.GOOGLE_CLIENT_ID && process.env.GOOGLE_CLIENT_SECRET);
}

// Absolute redirect URI for the callback. Must match the value registered in
// Google Cloud Console exactly. Prefer an explicit env override (robust behind
// the Vite dev proxy); otherwise derive it from the request like the PHP app,
// honouring the X-Forwarded-Proto header set by a TLS-terminating proxy.
export function googleRedirectUri(req) {
  if (process.env.GOOGLE_REDIRECT_URI) return process.env.GOOGLE_REDIRECT_URI;
  const proto = req.headers['x-forwarded-proto'] || req.protocol || 'http';
  const host = req.headers['x-forwarded-host'] || req.headers.host;
  return `${proto}://${host}/api/auth/google/callback`;
}

// Build the Google consent-screen URL. `state` is our CSRF token (verified in
// the callback against the session copy).
export function googleAuthorizeUrl(redirectUri, state) {
  const params = new URLSearchParams({
    client_id: process.env.GOOGLE_CLIENT_ID,
    redirect_uri: redirectUri,
    response_type: 'code',
    scope: 'openid email profile',
    state,
    access_type: 'online',
    prompt: 'select_account',
  });
  return `${AUTHORIZE_URL}?${params.toString()}`;
}

// Exchange the authorization code for an access token. Throws with a readable
// message on any non-200 / missing-token response.
export async function exchangeCodeForToken(code, redirectUri) {
  const res = await fetch(TOKEN_URL, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({
      code,
      client_id: process.env.GOOGLE_CLIENT_ID,
      client_secret: process.env.GOOGLE_CLIENT_SECRET,
      redirect_uri: redirectUri,
      grant_type: 'authorization_code',
    }),
  });
  const token = await res.json().catch(() => ({}));
  if (!res.ok || !token.access_token) {
    throw new Error(token.error_description || token.error || 'token exchange failed');
  }
  return token.access_token;
}

// Read the user's profile from the OpenID userinfo endpoint and normalise it to
// { sub, email, emailVerified, first, last, picture }. First/last fall back to
// splitting the display name, then to the email local-part (mirrors PHP).
export async function fetchGoogleProfile(accessToken) {
  const res = await fetch(USERINFO_URL, {
    headers: { Authorization: `Bearer ${accessToken}` },
  });
  const profile = await res.json().catch(() => null);
  if (!res.ok || !profile || !profile.sub || !profile.email) {
    throw new Error('Google did not return your account details.');
  }

  let first = (profile.given_name || '').trim();
  let last = (profile.family_name || '').trim();
  if (first === '') {
    const name = (profile.name || '').trim();
    if (name !== '') {
      const parts = name.split(/\s+/);
      first = parts[0];
      if (last === '' && parts.length > 1) last = parts.slice(1).join(' ');
    } else {
      first = profile.email.split('@')[0] || 'Friend';
    }
  }

  const verified = profile.email_verified;
  return {
    sub: String(profile.sub),
    email: String(profile.email),
    emailVerified: verified === true || verified === 'true',
    first,
    last,
    picture: String(profile.picture || ''),
  };
}

// Insert or refresh the user_identity row linking this Google account. `exec`
// is the query runner — the shared pool helper, or a transaction connection's
// .query for the atomic new-account path.
const LINK_IDENTITY_SQL = `INSERT INTO user_identity (user_id, provider, provider_uid, email)
   VALUES (?, 'google', ?, ?)
   ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), email = VALUES(email)`;

function linkIdentity(exec, userId, g, email) {
  return exec(LINK_IDENTITY_SQL, [userId, g.sub, email]);
}

// Resolve a Google profile to a BitBalance user, creating or linking as needed.
// Returns { userId, isNew }. Matching order mirrors google_find_or_create():
//   1. existing (provider, provider_uid)  -> returning Google user
//   2. existing local account, same email -> link Google to it (email verified)
//   3. otherwise create a fresh account and link the Google identity
export async function findOrCreateGoogleUser(g) {
  const email = g.email.toLowerCase().trim();

  // 1) Known Google identity.
  const known = await query(
    `SELECT user_id FROM user_identity WHERE provider = 'google' AND provider_uid = ? LIMIT 1`,
    [g.sub]
  );
  if (known.length) {
    await linkIdentity(query, Number(known[0].user_id), g, email);
    return { userId: Number(known[0].user_id), isNew: false };
  }

  // 2) Existing local account with the same email.
  const local = await query('SELECT user_id FROM user WHERE LOWER(email) = ? LIMIT 1', [email]);
  if (local.length) {
    await linkIdentity(query, Number(local[0].user_id), g, email);
    return { userId: Number(local[0].user_id), isNew: false };
  }

  // 3) Brand-new account — user + userStatus + identity in one transaction.
  const handle = await generateHandle(g.first !== '' ? g.first : 'user');
  // OAuth accounts have no usable password; store a random hash so the NOT NULL
  // column is satisfied and password login can never match.
  const randomPw = await bcrypt.hash(crypto.randomBytes(32).toString('hex'), BCRYPT_ROUNDS);

  const conn = await pool.getConnection();
  try {
    await conn.beginTransaction();
    const [result] = await conn.query(
      `INSERT INTO user (user_name, first_name, last_name, email, password, role, profile_image, created_at)
       VALUES (?, ?, ?, ?, ?, 'regular', ?, NOW())`,
      [handle, g.first, g.last, email, randomPw, g.picture !== '' ? g.picture : null]
    );
    const userId = result.insertId;

    await conn.query(
      `INSERT INTO userStatus (user_id, status, theme_preference, failed_attempts, locked_until)
       VALUES (?, 'active', 'system', 0, NULL)`,
      [userId]
    );

    await linkIdentity((sql, params) => conn.query(sql, params), userId, g, email);
    await conn.commit();
    return { userId, isNew: true };
  } catch (err) {
    await conn.rollback();
    throw err;
  } finally {
    conn.release();
  }
}
