import { describe, it, expect } from 'vitest';
import { buildPersonalPlan, ACTIVITY_FACTORS, GOAL_MODES } from '../../src/lib/plan.js';
import { macroGoalsFromCalories } from '../../src/lib/intake.js';

// Reference case: 30y male, 80kg, 180cm, sedentary, maintain.
//   BMR  = 10*80 + 6.25*180 - 5*30 + 5 = 1780
//   TDEE = 1780 * 1.2 = 2136 (sedentary, maintain => no adjustment)
describe('buildPersonalPlan', () => {
  it('computes the Mifflin-St Jeor reference case', () => {
    const p = buildPersonalPlan(30, 'male', 80, 180, 'sedentary', 'maintain');
    expect(p.bmr).toBe(1780);
    expect(p.tdee).toBeCloseTo(2136, 5);
    expect(p.daily_adjustment).toBe(0);
    expect(p.calorie_goal).toBe(2136);
    expect(p.macros).toEqual({ protein: 160, carbs: 240, fat: 59 });
    expect(p.hydration_ml).toBe(2750); // round(80*35/250)*250
  });

  it('applies the gender offsets to BMR', () => {
    // base (no offset) for 80kg/180cm/30y = 1775
    expect(buildPersonalPlan(30, 'male', 80, 180, 'sedentary', 'maintain').bmr).toBe(1780); // +5
    expect(buildPersonalPlan(30, 'female', 80, 180, 'sedentary', 'maintain').bmr).toBe(1614); // -161
    expect(buildPersonalPlan(30, 'other', 80, 180, 'sedentary', 'maintain').bmr).toBe(1697); // -78
  });

  it('falls back to safe defaults for invalid inputs', () => {
    // bogus goal mode -> maintain (no adjustment)
    expect(buildPersonalPlan(30, 'male', 80, 180, 'sedentary', 'bogus').daily_adjustment).toBe(0);
    // bogus activity -> moderately_active (1.55): 1780 * 1.55 = 2759
    expect(buildPersonalPlan(30, 'male', 80, 180, 'bogus', 'maintain').tdee).toBeCloseTo(2759, 5);
  });

  it('clamps the calorie goal to a sane floor', () => {
    // Tiny TDEE with an aggressive deficit would go negative -> floored to 800.
    const p = buildPersonalPlan(80, 'female', 35, 140, 'sedentary', 'lose', 1.5);
    expect(p.calorie_goal).toBe(800);
  });

  it('exposes the activity factors and goal modes', () => {
    expect(ACTIVITY_FACTORS.sedentary).toBe(1.2);
    expect(ACTIVITY_FACTORS.extra_active).toBe(1.9);
    expect(GOAL_MODES).toEqual(['lose', 'maintain', 'gain']);
  });
});

describe('macroGoalsFromCalories', () => {
  it('splits 30/45/25 by calories-per-gram', () => {
    expect(macroGoalsFromCalories(2000)).toEqual({ protein: 150, carbs: 225, fat: 56 });
  });
  it('returns zeros for a non-positive goal', () => {
    expect(macroGoalsFromCalories(0)).toEqual({ protein: 0, carbs: 0, fat: 0 });
    expect(macroGoalsFromCalories(-100)).toEqual({ protein: 0, carbs: 0, fat: 0 });
  });
});
