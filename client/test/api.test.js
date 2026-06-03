import { describe, it, expect, afterEach, vi } from 'vitest';
import { api } from '../src/lib/api.js';

// Build a fake fetch returning the given status + JSON body. Pass body === THROW
// to simulate a non-JSON response (res.json() rejects).
const THROW = Symbol('non-json');
function fakeFetch(status, body) {
  return vi.fn(async () => ({
    status,
    json: async () => {
      if (body === THROW) throw new Error('not json');
      return body;
    },
  }));
}

describe('api client', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('GET unwraps data and always sends X-Requested-With + credentials', async () => {
    const f = fakeFetch(200, { ok: true, data: { hello: 'world' }, message: null });
    vi.stubGlobal('fetch', f);

    const data = await api.get('/api/x');
    expect(data).toEqual({ hello: 'world' });

    const [path, opts] = f.mock.calls[0];
    expect(path).toBe('/api/x');
    expect(opts.method).toBe('GET');
    expect(opts.credentials).toBe('include');
    expect(opts.headers['X-Requested-With']).toBe('XMLHttpRequest');
    expect(opts.headers['Content-Type']).toBeUndefined(); // no body => no content-type
    expect(opts.body).toBeUndefined();
  });

  it('POST JSON-stringifies the body and sets Content-Type', async () => {
    const f = fakeFetch(200, { ok: true, data: 1, message: null });
    vi.stubGlobal('fetch', f);

    await api.post('/api/y', { a: 1, b: 'two' });
    const [path, opts] = f.mock.calls[0];
    expect(path).toBe('/api/y');
    expect(opts.method).toBe('POST');
    expect(opts.headers['Content-Type']).toBe('application/json');
    expect(opts.body).toBe(JSON.stringify({ a: 1, b: 'two' }));
  });

  it('PATCH uses the PATCH verb', async () => {
    const f = fakeFetch(200, { ok: true, data: null, message: null });
    vi.stubGlobal('fetch', f);
    await api.patch('/api/z', { b: 2 });
    expect(f.mock.calls[0][1].method).toBe('PATCH');
  });

  it('throws an Error carrying message + HTTP status when ok is false', async () => {
    const f = fakeFetch(422, { ok: false, data: null, message: 'Bad input.' });
    vi.stubGlobal('fetch', f);
    await expect(api.get('/api/bad')).rejects.toMatchObject({ message: 'Bad input.', status: 422 });
  });

  it('surfaces a friendly error when the response is not JSON', async () => {
    const f = fakeFetch(500, THROW);
    vi.stubGlobal('fetch', f);
    await expect(api.get('/api/html')).rejects.toThrow(/Unexpected server response/);
  });
});
