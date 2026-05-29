-- Migration: Friends system (PR2 — MVP)
-- Date: 2026-05-29
--
-- friend_request:  single source of truth for both "pending" and "accepted"
--                  relationships. Querying "who are my friends?" → WHERE
--                  status='accepted' AND (requester_id=me OR addressee_id=me).
-- friend_block:    one-way block. Blocked user cannot search the blocker,
--                  send requests, or see them in friend lists. Not wired into
--                  UI in PR2 (table only — reserved for PR3).
-- profile_visibility column: gates what friends/strangers see on the profile
--                  page (read by the profile-rework PR, harmless until then).

CREATE TABLE IF NOT EXISTS `friend_request` (
  `request_id`   INT(11) NOT NULL AUTO_INCREMENT,
  `requester_id` INT(11) NOT NULL,
  `addressee_id` INT(11) NOT NULL,
  `status`       ENUM('pending','accepted','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `responded_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`request_id`),
  UNIQUE KEY `uk_pair` (`requester_id`, `addressee_id`),
  KEY `idx_addressee_status` (`addressee_id`, `status`),
  KEY `idx_requester_status` (`requester_id`, `status`),
  CONSTRAINT `fk_fr_requester` FOREIGN KEY (`requester_id`) REFERENCES `user`(`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fr_addressee` FOREIGN KEY (`addressee_id`) REFERENCES `user`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `friend_block` (
  `block_id`    INT(11) NOT NULL AUTO_INCREMENT,
  `blocker_id`  INT(11) NOT NULL,
  `blocked_id`  INT(11) NOT NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`block_id`),
  UNIQUE KEY `uk_block_pair` (`blocker_id`, `blocked_id`),
  KEY `idx_blocked` (`blocked_id`),
  CONSTRAINT `fk_fb_blocker` FOREIGN KEY (`blocker_id`) REFERENCES `user`(`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fb_blocked` FOREIGN KEY (`blocked_id`) REFERENCES `user`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add visibility column to userStatus (gated by profile rework PR).
ALTER TABLE `userStatus`
  ADD COLUMN `profile_visibility` ENUM('private','friends','public') NOT NULL DEFAULT 'friends'
  AFTER `profile_bio`;
