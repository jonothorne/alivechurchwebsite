-- User Studies Tables
-- Run this SQL on production to create the required tables for highlights, saved studies, and reading history

CREATE TABLE IF NOT EXISTS `user_highlights` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `study_id` int(11) NOT NULL,
  `highlighted_text` text NOT NULL,
  `start_offset` int(11) NOT NULL,
  `end_offset` int(11) NOT NULL,
  `color` varchar(20) DEFAULT 'yellow',
  `note` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_highlights_user` (`user_id`),
  KEY `idx_user_highlights_study` (`study_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_saved_studies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `study_id` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_study` (`user_id`, `study_id`),
  KEY `idx_user_saved_studies_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_reading_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `study_id` int(11) NOT NULL,
  `time_spent` int(11) DEFAULT 0,
  `scroll_progress` decimal(5,2) DEFAULT 0,
  `completed` tinyint(1) DEFAULT 0,
  `read_count` int(11) DEFAULT 1,
  `last_read_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_study_history` (`user_id`, `study_id`),
  KEY `idx_reading_history_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
