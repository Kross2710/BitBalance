-- Migration: track who initiated a trainer_client link
-- Date: 2026-06-03
-- Description: PT can now invite a client (PT-initiated), not just the client
--   requesting a PT. Each side must show the right "awaiting your response" item,
--   so we record the direction. Nullable; legacy rows = NULL = client-initiated
--   (the only direction that existed before). Backward compatible: PHP ignores
--   the unknown column.

ALTER TABLE `trainer_client`
  ADD COLUMN `initiated_by` ENUM('client','trainer') NULL DEFAULT NULL AFTER `status`;
