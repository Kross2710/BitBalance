// Discord/Riot-style handle generation — ports include/handlers/username.php.
// Stored in user.user_name as "<slug>#<number>" (e.g. "Hung#2117"). The PHP
// version strips diacritics with iconv//TRANSLIT (RMIT has no mbstring); here
// we use Unicode NFKD normalization plus an explicit đ/Đ map (which NFKD does
// not decompose) so "Hưng" → "Hung", "Đức" → "Duc".
import { randomInt } from 'node:crypto';
import { query } from '../db.js';

export function slugifyName(name) {
  name = (name ?? '').trim();
  if (name === '') return '';

  const ascii = name
    .normalize('NFKD')
    .replace(/[̀-ͯ]/g, '') // combining diacritical marks
    .replace(/đ/g, 'd')
    .replace(/Đ/g, 'D');

  const slug = ascii.replace(/[^A-Za-z0-9]/g, '');
  return slug.slice(0, 20);
}

async function handleExists(candidate) {
  const rows = await query('SELECT 1 FROM user WHERE user_name = ? LIMIT 1', [candidate]);
  return rows.length > 0;
}

// Tries 4-digit numbers, widens to 6 digits if crowded; the UNIQUE key on
// user_name is the final race guard (caller's INSERT retries on the rare dup).
export async function generateHandle(firstName) {
  const base = slugifyName(firstName) || 'user';

  for (let i = 0; i < 25; i++) {
    const candidate = `${base}#${randomInt(1000, 10000)}`;
    if (!(await handleExists(candidate))) return candidate;
  }
  for (let i = 0; i < 25; i++) {
    const candidate = `${base}#${randomInt(100000, 1000000)}`;
    if (!(await handleExists(candidate))) return candidate;
  }
  return `${base.slice(0, 12)}#${randomInt(1000, 10000)}${String(Date.now()).slice(-4)}`;
}
