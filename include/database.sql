-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: May 31, 2026 at 01:48 PM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `test`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

CREATE TABLE `activity_log` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `target_table` varchar(50) DEFAULT NULL,
  `target_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ai_conversation`
--

CREATE TABLE `ai_conversation` (
  `conversation_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(120) NOT NULL DEFAULT 'New chat',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ai_message`
--

CREATE TABLE `ai_message` (
  `message_id` int(11) NOT NULL,
  `conversation_id` int(11) NOT NULL,
  `role` enum('user','assistant') NOT NULL,
  `content` text NOT NULL,
  `image_path` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ai_usage_daily`
--

CREATE TABLE `ai_usage_daily` (
  `usage_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `usage_date` date NOT NULL,
  `message_count` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `barcode_products`
--

CREATE TABLE `barcode_products` (
  `barcode` varchar(20) NOT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `brand` varchar(120) DEFAULT NULL,
  `serving_size` varchar(60) DEFAULT NULL,
  `kcal_per_serving` int(11) DEFAULT NULL,
  `kcal_per_100g` decimal(7,2) DEFAULT NULL,
  `protein_per_serving` decimal(6,2) DEFAULT NULL,
  `carbs_per_serving` decimal(6,2) DEFAULT NULL,
  `fat_per_serving` decimal(6,2) DEFAULT NULL,
  `sugar_per_serving` decimal(6,2) DEFAULT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `source` enum('openfoodfacts','user_submitted') NOT NULL DEFAULT 'openfoodfacts',
  `submitted_by_user_id` int(11) DEFAULT NULL,
  `lookup_count` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `barcode_scan_log`
--

CREATE TABLE `barcode_scan_log` (
  `scan_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `barcode` varchar(20) NOT NULL,
  `result` enum('cache_hit','api_found','api_miss','api_error') NOT NULL,
  `latency_ms` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `beats_mix_log`
--

CREATE TABLE `beats_mix_log` (
  `mix_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `track_name` varchar(120) NOT NULL,
  `artist_name` varchar(120) NOT NULL DEFAULT '',
  `food_item` varchar(120) NOT NULL,
  `calories` int(11) NOT NULL DEFAULT 0,
  `archetype` varchar(80) NOT NULL DEFAULT '',
  `detected_vibe` varchar(60) NOT NULL DEFAULT '',
  `match_score` tinyint(4) NOT NULL DEFAULT 0,
  `energy_sync` tinyint(4) NOT NULL DEFAULT 0,
  `comfort` tinyint(4) NOT NULL DEFAULT 0,
  `chaos` tinyint(4) NOT NULL DEFAULT 0,
  `verdict` varchar(255) NOT NULL DEFAULT '',
  `rarity` varchar(60) NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `forumComment`
--

CREATE TABLE `forumComment` (
  `comment_id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `content` text NOT NULL,
  `date_posted` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','archived','banned') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `forumLike`
--

CREATE TABLE `forumLike` (
  `like_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` enum('post','comment') NOT NULL,
  `target_id` int(11) NOT NULL,
  `date_liked` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `forumPost`
--

CREATE TABLE `forumPost` (
  `post_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `content` text NOT NULL,
  `category` varchar(50) DEFAULT NULL,
  `image_path` varchar(500) DEFAULT NULL,
  `date_posted` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','archived','banned') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `friend_block`
--

CREATE TABLE `friend_block` (
  `block_id` int(11) NOT NULL,
  `blocker_id` int(11) NOT NULL,
  `blocked_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `friend_request`
--

CREATE TABLE `friend_request` (
  `request_id` int(11) NOT NULL,
  `requester_id` int(11) NOT NULL,
  `addressee_id` int(11) NOT NULL,
  `status` enum('pending','accepted','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `responded_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `intakeLog`
--

CREATE TABLE `intakeLog` (
  `intakeLog_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `food_item` varchar(100) NOT NULL,
  `meal_category` enum('breakfast','lunch','dinner','snack') NOT NULL DEFAULT 'breakfast',
  `calories` int(11) NOT NULL,
  `protein` decimal(6,2) NOT NULL DEFAULT 0.00,
  `carbs` decimal(6,2) NOT NULL DEFAULT 0.00,
  `fat` decimal(6,2) NOT NULL DEFAULT 0.00,
  `image_path` varchar(255) DEFAULT NULL,
  `date_intake` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `success` tinyint(1) NOT NULL,
  `attempted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `used` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pt_feedback`
--

CREATE TABLE `pt_feedback` (
  `feedback_id` int(11) NOT NULL,
  `trainer_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `date_for` date NOT NULL,
  `content` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `trainer_client`
--

CREATE TABLE `trainer_client` (
  `id` int(11) NOT NULL,
  `trainer_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `status` enum('pending','accepted','rejected','terminated') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `responded_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `user_id` int(11) NOT NULL,
  `user_name` varchar(50) NOT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `timeCreated` timestamp NOT NULL DEFAULT current_timestamp(),
  `role` enum('regular','admin','pt') NOT NULL DEFAULT 'regular',
  `profile_image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL COMMENT 'Most recent login timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `userGoal`
--

CREATE TABLE `userGoal` (
  `userGoal_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `calorie_goal` int(11) NOT NULL,
  `date_set` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `userPhysicalInfo`
--

CREATE TABLE `userPhysicalInfo` (
  `userPhysicalStat_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `age` int(3) DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `weight` decimal(5,2) DEFAULT NULL,
  `height` decimal(5,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `userStatus`
--

CREATE TABLE `userStatus` (
  `userStatus_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` enum('active','banned','archived') DEFAULT 'active',
  `theme_preference` varchar(20) DEFAULT NULL,
  `failed_attempts` int(11) DEFAULT 0,
  `locked_until` timestamp NULL DEFAULT NULL,
  `archived_at` timestamp NULL DEFAULT NULL,
  `profile_bio` text DEFAULT NULL,
  `profile_visibility` enum('private','friends','public') NOT NULL DEFAULT 'friends',
  `show_favorite_food` tinyint(1) NOT NULL DEFAULT 1,
  `last_login_date` date DEFAULT NULL,
  `logging_streak` int(11) DEFAULT 0,
  `longest_logging_streak` int(11) DEFAULT 0,
  `last_logging_date` date DEFAULT NULL,
  `streak_freezes` int(11) NOT NULL DEFAULT 0,
  `broken_streak` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_plan_preferences`
--

CREATE TABLE `user_plan_preferences` (
  `user_id` int(11) NOT NULL,
  `goal_mode` enum('lose','maintain','gain') NOT NULL DEFAULT 'lose',
  `weekly_rate` decimal(4,2) NOT NULL DEFAULT 0.25,
  `activity_level` varchar(32) NOT NULL DEFAULT 'moderately_active',
  `target_weight` decimal(5,1) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_spotify`
--

CREATE TABLE `user_spotify` (
  `user_id` int(11) NOT NULL,
  `access_token` text NOT NULL,
  `refresh_token` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_themes`
--

CREATE TABLE `user_themes` (
  `theme_id` int(11) NOT NULL,
  `theme_name` varchar(50) NOT NULL,
  `theme_display_name` varchar(100) NOT NULL,
  `primary_color` varchar(7) DEFAULT '#007bff',
  `secondary_color` varchar(7) DEFAULT '#6c757d',
  `background_color` varchar(7) DEFAULT '#ffffff',
  `text_color` varchar(7) DEFAULT '#212529',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_xp`
--

CREATE TABLE `user_xp` (
  `user_id` int(11) NOT NULL,
  `total_xp` int(11) NOT NULL DEFAULT 0,
  `current_level` int(11) NOT NULL DEFAULT 1,
  `last_level_up_at` timestamp NULL DEFAULT NULL,
  `last_finalized_date` date DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `weekly_wrapped_cache`
--

CREATE TABLE `weekly_wrapped_cache` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `week_year` varchar(10) NOT NULL,
  `lang` varchar(5) NOT NULL,
  `generated_json` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `weight_log`
--

CREATE TABLE `weight_log` (
  `weight_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `weight` decimal(5,2) NOT NULL,
  `date_logged` date NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `xp_event`
--

CREATE TABLE `xp_event` (
  `event_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `source` varchar(40) NOT NULL,
  `amount` int(11) NOT NULL,
  `ref_table` varchar(40) DEFAULT NULL,
  `ref_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `ai_conversation`
--
ALTER TABLE `ai_conversation`
  ADD PRIMARY KEY (`conversation_id`),
  ADD KEY `idx_user_updated` (`user_id`,`updated_at`);

--
-- Indexes for table `ai_message`
--
ALTER TABLE `ai_message`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `idx_conv_created` (`conversation_id`,`created_at`);

--
-- Indexes for table `ai_usage_daily`
--
ALTER TABLE `ai_usage_daily`
  ADD PRIMARY KEY (`usage_id`),
  ADD UNIQUE KEY `uk_user_date` (`user_id`,`usage_date`);

--
-- Indexes for table `barcode_products`
--
ALTER TABLE `barcode_products`
  ADD PRIMARY KEY (`barcode`),
  ADD KEY `idx_lookup_count` (`lookup_count`),
  ADD KEY `fk_barcode_product_user` (`submitted_by_user_id`);

--
-- Indexes for table `barcode_scan_log`
--
ALTER TABLE `barcode_scan_log`
  ADD PRIMARY KEY (`scan_id`),
  ADD KEY `idx_user_created` (`user_id`,`created_at`),
  ADD KEY `idx_barcode` (`barcode`),
  ADD KEY `idx_result` (`result`);

--
-- Indexes for table `beats_mix_log`
--
ALTER TABLE `beats_mix_log`
  ADD PRIMARY KEY (`mix_id`),
  ADD KEY `user_created` (`user_id`,`created_at`);

--
-- Indexes for table `forumComment`
--
ALTER TABLE `forumComment`
  ADD PRIMARY KEY (`comment_id`),
  ADD KEY `post_id` (`post_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `forumLike`
--
ALTER TABLE `forumLike`
  ADD PRIMARY KEY (`like_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_target` (`type`,`target_id`);

--
-- Indexes for table `forumPost`
--
ALTER TABLE `forumPost`
  ADD PRIMARY KEY (`post_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `friend_block`
--
ALTER TABLE `friend_block`
  ADD PRIMARY KEY (`block_id`),
  ADD UNIQUE KEY `uk_block_pair` (`blocker_id`,`blocked_id`),
  ADD KEY `idx_blocked` (`blocked_id`);

--
-- Indexes for table `friend_request`
--
ALTER TABLE `friend_request`
  ADD PRIMARY KEY (`request_id`),
  ADD UNIQUE KEY `uk_pair` (`requester_id`,`addressee_id`),
  ADD KEY `idx_addressee_status` (`addressee_id`,`status`),
  ADD KEY `idx_requester_status` (`requester_id`,`status`);

--
-- Indexes for table `intakeLog`
--
ALTER TABLE `intakeLog`
  ADD PRIMARY KEY (`intakeLog_id`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_ip` (`ip_address`),
  ADD KEY `idx_attempted` (`attempted_at`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Indexes for table `pt_feedback`
--
ALTER TABLE `pt_feedback`
  ADD PRIMARY KEY (`feedback_id`),
  ADD UNIQUE KEY `uk_trainer_client_date` (`trainer_id`,`client_id`,`date_for`),
  ADD KEY `fk_pf_client` (`client_id`);

--
-- Indexes for table `trainer_client`
--
ALTER TABLE `trainer_client`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_trainer_client` (`trainer_id`,`client_id`),
  ADD KEY `fk_tc_client` (`client_id`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `uk_user_name` (`user_name`);

--
-- Indexes for table `userGoal`
--
ALTER TABLE `userGoal`
  ADD PRIMARY KEY (`userGoal_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `userStatus`
--
ALTER TABLE `userStatus`
  ADD PRIMARY KEY (`userStatus_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `user_plan_preferences`
--
ALTER TABLE `user_plan_preferences`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `user_spotify`
--
ALTER TABLE `user_spotify`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `user_themes`
--
ALTER TABLE `user_themes`
  ADD PRIMARY KEY (`theme_id`),
  ADD UNIQUE KEY `theme_name` (`theme_name`);

--
-- Indexes for table `user_xp`
--
ALTER TABLE `user_xp`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `weekly_wrapped_cache`
--
ALTER TABLE `weekly_wrapped_cache`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_user_week_lang` (`user_id`,`week_year`,`lang`);

--
-- Indexes for table `weight_log`
--
ALTER TABLE `weight_log`
  ADD PRIMARY KEY (`weight_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `xp_event`
--
ALTER TABLE `xp_event`
  ADD PRIMARY KEY (`event_id`),
  ADD KEY `idx_user_date` (`user_id`,`created_at`),
  ADD KEY `idx_user_source_date` (`user_id`,`source`,`created_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ai_conversation`
--
ALTER TABLE `ai_conversation`
  MODIFY `conversation_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ai_message`
--
ALTER TABLE `ai_message`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ai_usage_daily`
--
ALTER TABLE `ai_usage_daily`
  MODIFY `usage_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `barcode_scan_log`
--
ALTER TABLE `barcode_scan_log`
  MODIFY `scan_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `beats_mix_log`
--
ALTER TABLE `beats_mix_log`
  MODIFY `mix_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `forumComment`
--
ALTER TABLE `forumComment`
  MODIFY `comment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `forumLike`
--
ALTER TABLE `forumLike`
  MODIFY `like_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `forumPost`
--
ALTER TABLE `forumPost`
  MODIFY `post_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `friend_block`
--
ALTER TABLE `friend_block`
  MODIFY `block_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `friend_request`
--
ALTER TABLE `friend_request`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `intakeLog`
--
ALTER TABLE `intakeLog`
  MODIFY `intakeLog_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pt_feedback`
--
ALTER TABLE `pt_feedback`
  MODIFY `feedback_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `trainer_client`
--
ALTER TABLE `trainer_client`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `userGoal`
--
ALTER TABLE `userGoal`
  MODIFY `userGoal_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `userStatus`
--
ALTER TABLE `userStatus`
  MODIFY `userStatus_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_themes`
--
ALTER TABLE `user_themes`
  MODIFY `theme_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `weekly_wrapped_cache`
--
ALTER TABLE `weekly_wrapped_cache`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `weight_log`
--
ALTER TABLE `weight_log`
  MODIFY `weight_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `xp_event`
--
ALTER TABLE `xp_event`
  MODIFY `event_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD CONSTRAINT `activity_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `ai_conversation`
--
ALTER TABLE `ai_conversation`
  ADD CONSTRAINT `fk_ai_conv_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `ai_message`
--
ALTER TABLE `ai_message`
  ADD CONSTRAINT `fk_ai_msg_conv` FOREIGN KEY (`conversation_id`) REFERENCES `ai_conversation` (`conversation_id`) ON DELETE CASCADE;

--
-- Constraints for table `ai_usage_daily`
--
ALTER TABLE `ai_usage_daily`
  ADD CONSTRAINT `fk_ai_usage_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `barcode_products`
--
ALTER TABLE `barcode_products`
  ADD CONSTRAINT `fk_barcode_product_user` FOREIGN KEY (`submitted_by_user_id`) REFERENCES `user` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `barcode_scan_log`
--
ALTER TABLE `barcode_scan_log`
  ADD CONSTRAINT `fk_scan_log_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `forumComment`
--
ALTER TABLE `forumComment`
  ADD CONSTRAINT `forumComment_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `forumPost` (`post_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `forumComment_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `forumLike`
--
ALTER TABLE `forumLike`
  ADD CONSTRAINT `forumLike_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `forumPost`
--
ALTER TABLE `forumPost`
  ADD CONSTRAINT `forumPost_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `friend_block`
--
ALTER TABLE `friend_block`
  ADD CONSTRAINT `fk_fb_blocked` FOREIGN KEY (`blocked_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_fb_blocker` FOREIGN KEY (`blocker_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `friend_request`
--
ALTER TABLE `friend_request`
  ADD CONSTRAINT `fk_fr_addressee` FOREIGN KEY (`addressee_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_fr_requester` FOREIGN KEY (`requester_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `pt_feedback`
--
ALTER TABLE `pt_feedback`
  ADD CONSTRAINT `fk_pf_client` FOREIGN KEY (`client_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pf_trainer` FOREIGN KEY (`trainer_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `trainer_client`
--
ALTER TABLE `trainer_client`
  ADD CONSTRAINT `fk_tc_client` FOREIGN KEY (`client_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_tc_trainer` FOREIGN KEY (`trainer_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `userGoal`
--
ALTER TABLE `userGoal`
  ADD CONSTRAINT `userGoal_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `userStatus`
--
ALTER TABLE `userStatus`
  ADD CONSTRAINT `userStatus_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`);

--
-- Constraints for table `user_plan_preferences`
--
ALTER TABLE `user_plan_preferences`
  ADD CONSTRAINT `fk_plan_prefs_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_spotify`
--
ALTER TABLE `user_spotify`
  ADD CONSTRAINT `user_spotify_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_xp`
--
ALTER TABLE `user_xp`
  ADD CONSTRAINT `fk_user_xp_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `weight_log`
--
ALTER TABLE `weight_log`
  ADD CONSTRAINT `weight_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `xp_event`
--
ALTER TABLE `xp_event`
  ADD CONSTRAINT `fk_xp_event_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
