import { describe, it, expect } from 'vitest';
import en from '../src/i18n/en.js';
import vi from '../src/i18n/vi.js';

// The flat `area.section.element` catalogs must stay in lockstep: every key in
// one locale must exist in the other, with a non-empty string value. This is the
// automated version of the "keep en.js and vi.js growing together" rule and
// catches the missing-translation drift that crept in across parallel sessions.
describe('i18n locale parity', () => {
  it('en and vi expose exactly the same keys', () => {
    const missingInVi = Object.keys(en).filter((k) => !(k in vi));
    const missingInEn = Object.keys(vi).filter((k) => !(k in en));
    expect(missingInVi, `keys present in en but missing in vi: ${missingInVi.join(', ')}`).toEqual([]);
    expect(missingInEn, `keys present in vi but missing in en: ${missingInEn.join(', ')}`).toEqual([]);
  });

  it('every value is a non-empty string', () => {
    for (const [k, v] of Object.entries(en)) {
      expect(typeof v, `en[${k}] must be a string`).toBe('string');
      expect(v.trim().length, `en[${k}] must not be empty`).toBeGreaterThan(0);
    }
    for (const [k, v] of Object.entries(vi)) {
      expect(typeof v, `vi[${k}] must be a string`).toBe('string');
      expect(v.trim().length, `vi[${k}] must not be empty`).toBeGreaterThan(0);
    }
  });
});
