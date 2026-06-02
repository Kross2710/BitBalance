// Tiny fetch wrapper around the { ok, data, message } envelope the API returns.
// credentials:'include' sends the session cookie on every request.
async function request(method, path, body) {
  const res = await fetch(path, {
    method,
    credentials: 'include',
    headers: body ? { 'Content-Type': 'application/json' } : undefined,
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
}

export const api = {
  get: (path) => request('GET', path),
  post: (path, body) => request('POST', path, body),
};
