import { describe, it, expect } from 'vitest';
import { slugifyName } from '../../src/lib/handle.js';

describe('slugifyName', () => {
  it('strips Vietnamese diacritics (incl. the d-bar NFKD misses)', () => {
    expect(slugifyName('Hưng')).toBe('Hung');
    expect(slugifyName('Đức')).toBe('Duc');
    expect(slugifyName('Café')).toBe('Cafe');
    expect(slugifyName('Nguyễn')).toBe('Nguyen');
  });

  it('removes spaces and non-alphanumerics', () => {
    expect(slugifyName('John Doe!')).toBe('JohnDoe');
    expect(slugifyName('a_b-c.d')).toBe('abcd');
  });

  it('truncates to 20 characters', () => {
    expect(slugifyName('a'.repeat(30))).toBe('a'.repeat(20));
  });

  it('returns an empty string for empty/blank/nullish input', () => {
    expect(slugifyName('')).toBe('');
    expect(slugifyName('   ')).toBe('');
    expect(slugifyName(null)).toBe('');
    expect(slugifyName(undefined)).toBe('');
  });
});
