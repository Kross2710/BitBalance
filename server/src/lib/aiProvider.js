// LLM provider abstraction for the AI Coach. Selected via AI_PROVIDER
// ("gemini" | "openrouter"). Both take the same provider-agnostic input —
// a system instruction string + a [{ role, content }] history — and return
// { ok, text } or { ok: false, error }. Ports the call_gemini() of
// api/ai-coach/_helpers.php and adds an OpenAI-compatible OpenRouter path.
//
// Image/vision input is intentionally NOT wired in this version (text-only) —
// tracked in MIGRATION.md. Non-streaming, mirroring the legacy PHP behaviour.

const TIMEOUT_MS = 60_000;
const TEMPERATURE = 0.7;
const MAX_TOKENS = 1024;

function provider() {
  return (process.env.AI_PROVIDER || 'gemini').toLowerCase();
}

async function postJson(url, { headers = {}, body }) {
  const ctrl = new AbortController();
  const timer = setTimeout(() => ctrl.abort(), TIMEOUT_MS);
  try {
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', ...headers },
      body: JSON.stringify(body),
      signal: ctrl.signal,
    });
    let data = null;
    try {
      data = await res.json();
    } catch {
      data = null;
    }
    return { status: res.status, data };
  } finally {
    clearTimeout(timer);
  }
}

// ---- Gemini (Google Generative Language API) ----
async function callGemini({ system, history }) {
  const key = process.env.GEMINI_API_KEY || '';
  if (key === '') return { ok: false, error: 'Gemini API key not configured' };
  const model = process.env.AI_COACH_MODEL || 'gemini-3.1-flash-lite';

  const contents = history.map((m) => ({
    role: m.role === 'assistant' ? 'model' : 'user',
    parts: [{ text: m.content?.trim() ? m.content : '(empty)' }],
  }));

  const body = {
    system_instruction: { parts: [{ text: system }] },
    contents,
    generationConfig: { temperature: TEMPERATURE, maxOutputTokens: MAX_TOKENS },
  };

  let res;
  try {
    res = await postJson(
      `https://generativelanguage.googleapis.com/v1beta/models/${model}:generateContent?key=${key}`,
      { body }
    );
  } catch (e) {
    return { ok: false, error: 'Connection error: ' + (e.name === 'AbortError' ? 'timeout' : e.message) };
  }

  if (res.status !== 200) {
    const msg = res.data?.error?.message || `HTTP ${res.status}`;
    return { ok: false, error: 'Gemini error: ' + msg };
  }

  const text = res.data?.candidates?.[0]?.content?.parts?.[0]?.text ?? '';
  if (text === '') {
    const reason = res.data?.candidates?.[0]?.finishReason || 'unknown';
    return { ok: false, error: `AI returned empty response (finishReason: ${reason})` };
  }
  return { ok: true, text };
}

// ---- OpenRouter (OpenAI-compatible chat completions) ----
async function callOpenRouter({ system, history }) {
  const key = process.env.OPENROUTER_API_KEY || '';
  if (key === '') return { ok: false, error: 'OpenRouter API key not configured' };
  const model = process.env.OPENROUTER_MODEL || 'google/gemma-4-31b-it:free';

  const messages = [
    { role: 'system', content: system },
    ...history.map((m) => ({
      role: m.role === 'assistant' ? 'assistant' : 'user',
      content: m.content?.trim() ? m.content : '(empty)',
    })),
  ];

  let res;
  try {
    res = await postJson('https://openrouter.ai/api/v1/chat/completions', {
      headers: {
        Authorization: `Bearer ${key}`,
        // Optional attribution headers OpenRouter recommends.
        'X-Title': 'BitBalance AI Coach',
      },
      body: { model, messages, temperature: TEMPERATURE, max_tokens: MAX_TOKENS },
    });
  } catch (e) {
    return { ok: false, error: 'Connection error: ' + (e.name === 'AbortError' ? 'timeout' : e.message) };
  }

  if (res.status !== 200) {
    const msg = res.data?.error?.message || `HTTP ${res.status}`;
    return { ok: false, error: 'OpenRouter error: ' + msg };
  }

  const text = res.data?.choices?.[0]?.message?.content ?? '';
  if (text === '') return { ok: false, error: 'AI returned empty response' };
  return { ok: true, text };
}

export async function chatCompletion({ system, history }) {
  return provider() === 'openrouter'
    ? callOpenRouter({ system, history })
    : callGemini({ system, history });
}
