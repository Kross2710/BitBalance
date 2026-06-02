-- Migration: Explicit macro goals (PT goal Phase 2)
-- Date: 2026-06-02
-- Description: Cho phep luu macro target (P/C/F) ro rang thay vi luon suy ra tu calo.
--              userGoal: them macro cols (nullable -> fallback ve cong thuc cu khi null),
--              set_by + source de biet ai dat muc tieu. pt_goal_proposal: them macro cols
--              de PT de xuat kem macro. Tuong thich nguoc: cac row cu co macro = NULL.

ALTER TABLE `userGoal`
  ADD COLUMN `protein_goal` INT(11) NULL DEFAULT NULL AFTER `calorie_goal`,
  ADD COLUMN `carbs_goal`   INT(11) NULL DEFAULT NULL AFTER `protein_goal`,
  ADD COLUMN `fat_goal`     INT(11) NULL DEFAULT NULL AFTER `carbs_goal`,
  ADD COLUMN `set_by`       INT(11) NULL DEFAULT NULL AFTER `fat_goal`,
  ADD COLUMN `source`       ENUM('self','pt','plan') NOT NULL DEFAULT 'self' AFTER `set_by`;

ALTER TABLE `pt_goal_proposal`
  ADD COLUMN `protein_goal` INT(11) NULL DEFAULT NULL AFTER `calorie_goal`,
  ADD COLUMN `carbs_goal`   INT(11) NULL DEFAULT NULL AFTER `protein_goal`,
  ADD COLUMN `fat_goal`     INT(11) NULL DEFAULT NULL AFTER `carbs_goal`;
