import { describe, it, expect } from 'vitest';
import { addDays, weekdayLabel, isValidDate, todayVN } from '../../src/lib/dates.js';

describe('addDays', () => {
  it('adds and subtracts days', () => {
    expect(addDays('2026-06-03', 1)).toBe('2026-06-04');
    expect(addDays('2026-06-03', -1)).toBe('2026-06-02');
    expect(addDays('2026-06-03', 0)).toBe('2026-06-03');
  });
  it('crosses month and year boundaries', () => {
    expect(addDays('2026-12-31', 1)).toBe('2027-01-01');
    expect(addDays('2026-01-01', -1)).toBe('2025-12-31');
  });
  it('handles non-leap vs leap February', () => {
    expect(addDays('2026-03-01', -1)).toBe('2026-02-28'); // 2026 not a leap year
    expect(addDays('2024-03-01', -1)).toBe('2024-02-29'); // 2024 is a leap year
  });
});

describe('weekdayLabel', () => {
  it('maps known dates to the right weekday (UTC)', () => {
    expect(weekdayLabel('2021-01-01')).toBe('Fri'); // 1 Jan 2021 was a Friday
    expect(weekdayLabel('2021-01-03')).toBe('Sun');
    expect(weekdayLabel('2021-01-04')).toBe('Mon');
  });
});

describe('isValidDate', () => {
  it('accepts real ISO dates', () => {
    expect(isValidDate('2026-06-03')).toBe(true);
    expect(isValidDate('2024-02-29')).toBe(true); // leap day
  });
  it('rejects bad formats and impossible dates', () => {
    expect(isValidDate('2026-6-3')).toBe(false); // not zero-padded
    expect(isValidDate('2026-13-01')).toBe(false); // month 13
    expect(isValidDate('2026-02-30')).toBe(false); // Feb 30 rolls over
    expect(isValidDate('2025-02-29')).toBe(false); // not a leap year
    expect(isValidDate('not-a-date')).toBe(false);
    expect(isValidDate('')).toBe(false);
  });
});

describe('todayVN', () => {
  it('returns a normalized ISO date string', () => {
    const t = todayVN();
    expect(t).toMatch(/^\d{4}-\d{2}-\d{2}$/);
    // Round-trips through the UTC-based date math without shifting.
    expect(addDays(t, 0)).toBe(t);
  });
});
