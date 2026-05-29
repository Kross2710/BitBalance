-- Migration: Streak Freeze & Save Streak system
-- Date: 2026-05-29
--
-- Adds columns to userStatus table:
--   streak_freezes: equipped freezes count
--   broken_streak: temporarily stores broken streak length

ALTER TABLE `userStatus`
ADD COLUMN `streak_freezes` INT NOT NULL DEFAULT 0,
ADD COLUMN `broken_streak` INT NOT NULL DEFAULT 0;
