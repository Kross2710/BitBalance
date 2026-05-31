-- Migration: PT notification groundwork (Task #4)
-- Date: 2026-06-01
-- Description: Cho phép học viên biết khi có "góp ý mới" chưa đọc. Chat đã có
--   sẵn pt_message.seen_at (Task #3); ở đây bổ sung seen_at cho pt_feedback.

ALTER TABLE `pt_feedback`
  ADD COLUMN `seen_at` TIMESTAMP NULL DEFAULT NULL AFTER `updated_at`;
