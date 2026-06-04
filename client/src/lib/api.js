// The browser's resolved IANA timezone (e.g. 'Australia/Sydney'). Sent on every
// request so the server groups "days" in the user's local zone. Exported for the
// few call sites that bypass this wrapper with a raw fetch (multipart uploads).
export function browserTz() {
  try {
    return Intl.DateTimeFormat().resolvedOptions().timeZone || '';
  } catch {
    return '';
  }
}

import { reqStart, reqDone } from './loadingBar.js';

// Tiny fetch wrapper around the { ok, data, message } envelope the API returns.
// credentials:'include' sends the session cookie on every request. Foreground
// calls drive the global top loading bar; pass { background: true } for periodic
// polls so the bar doesn't flash on the 15s timers.
async function request(method, path, body, opts = {}) {
  // X-Requested-With on every call doubles as the CSRF token the admin API
  // checks on mutations: a cross-site <form> can't set a custom header. Harmless
  // for the other endpoints.
  const headers = { 'X-Requested-With': 'XMLHttpRequest', 'X-Timezone': browserTz() };
  if (body) headers['Content-Type'] = 'application/json';
  const track = !opts.background;
  if (track) reqStart();
  try {
    const res = await fetch(path, {
      method,
      credentials: 'include',
      headers,
      body: body ? JSON.stringify(body) : undefined,
    });

    let json;
    try {
      json = await res.json();
    } catch {
      json = { ok: false, data: null, message: 'Unexpected server response.' };
    }

    if (!json.ok) {
      const err = new Error(json.message || 'Request failed.');
      err.status = res.status;
      throw err;
    }
    return json.data;
  } finally {
    if (track) reqDone();
  }
}

export const api = {
  get: (path, opts) => request('GET', path, undefined, opts),
  post: (path, body, opts) => request('POST', path, body, opts),
  patch: (path, body, opts) => request('PATCH', path, body, opts),
};
