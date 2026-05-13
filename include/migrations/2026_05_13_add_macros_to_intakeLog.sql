-- Migration: add macronutrient columns to intakeLog
-- Date: 2026-05-13
-- Run on phpMyAdmin or via mysql CLI against the project database.

ALTER TABLE `intakeLog`
  ADD COLUMN `protein` DECIMAL(6,2) NOT NULL DEFAULT 0 AFTER `calories`,
  ADD COLUMN `carbs`   DECIMAL(6,2) NOT NULL DEFAULT 0 AFTER `protein`,
  ADD COLUMN `fat`     DECIMAL(6,2) NOT NULL DEFAULT 0 AFTER `carbs`;
