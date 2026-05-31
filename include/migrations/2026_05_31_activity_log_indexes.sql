-- Migration: Add Performance Indexes for activity_log table
-- Date: 2026-05-31
-- Description: Speeds up dashboard aggregations and pagination queries by indexing action_type and created_at.

-- Add composite index for action_type + created_at (for daily count queries by type)
ALTER TABLE `activity_log` ADD INDEX `idx_action_created` (`action_type`, `created_at`);

-- Add index on created_at (for general daily log aggregations and timeline sorting)
ALTER TABLE `activity_log` ADD INDEX `idx_created_at` (`created_at`);
