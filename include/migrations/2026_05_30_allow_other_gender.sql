-- Migration: allow neutral gender option for onboarding plans
-- Date: 2026-05-30

ALTER TABLE `userPhysicalInfo`
  MODIFY `gender` ENUM('male','female','other') DEFAULT NULL;
