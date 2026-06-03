import { describe, it, expect } from 'vitest';
import { xpForLevel, xpLevelFor } from '../../src/lib/xp.js';

// Level curve: xpForLevel(n) = 50 * n * (n - 1)
describe('xpForLevel', () => {
  it('matches the documented curve', () => {
    expect(xpForLevel(1)).toBe(0);
    expect(xpForLevel(2)).toBe(100);
    expect(xpForLevel(3)).toBe(300);
    expect(xpForLevel(4)).toBe(600);
    expect(xpForLevel(5)).toBe(1000);
  });
  it('treats level <= 1 as 0 XP', () => {
    expect(xpForLevel(0)).toBe(0);
    expect(xpForLevel(-3)).toBe(0);
  });
});

describe('xpLevelFor', () => {
  it('maps totals to the right level at and around thresholds', () => {
    expect(xpLevelFor(-50)).toBe(1);
    expect(xpLevelFor(0)).toBe(1);
    expect(xpLevelFor(99)).toBe(1);
    expect(xpLevelFor(100)).toBe(2);
    expect(xpLevelFor(299)).toBe(2);
    expect(xpLevelFor(300)).toBe(3);
    expect(xpLevelFor(599)).toBe(3);
    expect(xpLevelFor(600)).toBe(4);
  });

  it('is the inverse of xpForLevel across many levels', () => {
    for (let n = 1; n <= 60; n++) {
      // Exactly at the threshold => level n.
      expect(xpLevelFor(xpForLevel(n))).toBe(n);
      // One XP below the NEXT threshold => still level n.
      expect(xpLevelFor(xpForLevel(n + 1) - 1)).toBe(n);
    }
  });
});
