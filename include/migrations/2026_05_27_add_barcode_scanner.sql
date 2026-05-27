-- Migration: Barcode scanner feature
-- Adds product cache (shared across users) + scan attempt log (analytics)
-- Date: 2026-05-27

-- ---------------------------------------------------------
-- barcode_products: cache one row per barcode
-- Source can be 'openfoodfacts' (auto) or 'user_submitted' (community, Phase 2)
-- lookup_count helps identify hot products for future optimization
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `barcode_products` (
  `barcode`              VARCHAR(20) NOT NULL,
  `product_name`         VARCHAR(255) DEFAULT NULL,
  `brand`                VARCHAR(120) DEFAULT NULL,
  `serving_size`         VARCHAR(60)  DEFAULT NULL,
  `kcal_per_serving`     INT(11)        DEFAULT NULL,
  `kcal_per_100g`        DECIMAL(7,2)   DEFAULT NULL,
  `protein_per_serving`  DECIMAL(6,2)   DEFAULT NULL,
  `carbs_per_serving`    DECIMAL(6,2)   DEFAULT NULL,
  `fat_per_serving`      DECIMAL(6,2)   DEFAULT NULL,
  `sugar_per_serving`    DECIMAL(6,2)   DEFAULT NULL,
  `image_url`            VARCHAR(500) DEFAULT NULL,
  `source`               ENUM('openfoodfacts','user_submitted') NOT NULL DEFAULT 'openfoodfacts',
  `submitted_by_user_id` INT(11)        DEFAULT NULL,
  `lookup_count`         INT(11)        NOT NULL DEFAULT 1,
  `created_at`           TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`barcode`),
  KEY `idx_lookup_count` (`lookup_count`),
  CONSTRAINT `fk_barcode_product_user` FOREIGN KEY (`submitted_by_user_id`) REFERENCES `user`(`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- barcode_scan_log: every scan attempt (hit/miss/error)
-- Lets us measure real coverage before deciding on community fallback
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `barcode_scan_log` (
  `scan_id`     INT(11)      NOT NULL AUTO_INCREMENT,
  `user_id`     INT(11)      NOT NULL,
  `barcode`     VARCHAR(20)  NOT NULL,
  `result`      ENUM('cache_hit','api_found','api_miss','api_error') NOT NULL,
  `latency_ms`  INT(11)      DEFAULT NULL,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`scan_id`),
  KEY `idx_user_created` (`user_id`, `created_at`),
  KEY `idx_barcode` (`barcode`),
  KEY `idx_result` (`result`),
  CONSTRAINT `fk_scan_log_user` FOREIGN KEY (`user_id`) REFERENCES `user`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
