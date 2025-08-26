-- Meeplify Database Schema - Minimal Version (No Foreign Key Errors)
-- This version creates tables without foreign key constraints to avoid compatibility issues
-- You can add constraints later if needed

SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================================
-- USERS TABLE
-- =====================================================================
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `google_sub` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `surname` varchar(255) DEFAULT NULL,
  `role` enum('user','admin') NOT NULL DEFAULT 'user',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_google_sub` (`google_sub`),
  UNIQUE KEY `uk_email` (`email`),
  INDEX `idx_active_role` (`active`, `role`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- CHECKLISTS TABLE
-- =====================================================================
CREATE TABLE IF NOT EXISTS `checklists` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `owner_id` int(11) NOT NULL,
  `share_view_token` varchar(64) DEFAULT NULL,
  `is_public` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  
  PRIMARY KEY (`id`),
  INDEX `idx_owner_active` (`owner_id`, `deleted_at`),
  INDEX `idx_deleted_recovery` (`deleted_at`),
  INDEX `idx_updated_at` (`updated_at`),
  UNIQUE KEY `uk_share_token` (`share_view_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- SECTIONS TABLE
-- =====================================================================
CREATE TABLE IF NOT EXISTS `sections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `checklist_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `order_pos` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  INDEX `idx_checklist_order` (`checklist_id`, `order_pos`),
  INDEX `idx_checklist_id` (`checklist_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- ITEMS TABLE
-- =====================================================================
CREATE TABLE IF NOT EXISTS `items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `checklist_id` int(11) NOT NULL,
  `section_id` int(11) NOT NULL,
  `text` text NOT NULL,
  `completed` tinyint(1) NOT NULL DEFAULT 0,
  `order_pos` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  INDEX `idx_checklist_completion` (`checklist_id`, `completed`),
  INDEX `idx_section_order` (`section_id`, `order_pos`),
  INDEX `idx_checklist_id` (`checklist_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- TAGS TABLE
-- =====================================================================
CREATE TABLE IF NOT EXISTS `tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `color` varchar(7) NOT NULL DEFAULT '#007bff',
  `emoji` varchar(10) DEFAULT '🏷️',
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_tag_name` (`user_id`, `name`),
  INDEX `idx_user_tags` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- ITEM_TAGS TABLE
-- =====================================================================
CREATE TABLE IF NOT EXISTS `item_tags` (
  `item_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL,
  
  PRIMARY KEY (`item_id`, `tag_id`),
  INDEX `idx_tag_items` (`tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- COLLABORATORS TABLE
-- =====================================================================
CREATE TABLE IF NOT EXISTS `collaborators` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `checklist_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` enum('viewer','collaborator') NOT NULL DEFAULT 'viewer',
  `invited_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_checklist_user` (`checklist_id`, `user_id`),
  INDEX `idx_user_collaborations` (`user_id`),
  INDEX `idx_checklist_collaborators` (`checklist_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- TEMPLATES TABLE
-- =====================================================================
CREATE TABLE IF NOT EXISTS `templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(100) NOT NULL DEFAULT 'general',
  `difficulty_level` enum('beginner','intermediate','advanced') NOT NULL DEFAULT 'beginner',
  `estimated_time` varchar(50) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  INDEX `idx_category_active` (`category`, `active`),
  INDEX `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- TEMPLATE_SECTIONS TABLE
-- =====================================================================
CREATE TABLE IF NOT EXISTS `template_sections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `template_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `order_pos` int(11) NOT NULL DEFAULT 0,
  
  PRIMARY KEY (`id`),
  INDEX `idx_template_order` (`template_id`, `order_pos`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- TEMPLATE_ITEMS TABLE
-- =====================================================================
CREATE TABLE IF NOT EXISTS `template_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `template_id` int(11) NOT NULL,
  `section_id` int(11) NOT NULL,
  `text` text NOT NULL,
  `order_pos` int(11) NOT NULL DEFAULT 0,
  
  PRIMARY KEY (`id`),
  INDEX `idx_template_items` (`template_id`),
  INDEX `idx_section_order` (`section_id`, `order_pos`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- TEMPLATE_TAGS TABLE
-- =====================================================================
CREATE TABLE IF NOT EXISTS `template_tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `template_id` int(11) NOT NULL,
  `tag_name` varchar(100) NOT NULL,
  `tag_color` varchar(7) NOT NULL DEFAULT '#007bff',
  `tag_emoji` varchar(10) DEFAULT '🏷️',
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_template_tag` (`template_id`, `tag_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- AUDIT_LOG TABLE
-- =====================================================================
CREATE TABLE IF NOT EXISTS `audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_type` varchar(100) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `details` json DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  INDEX `idx_event_type` (`event_type`),
  INDEX `idx_user_events` (`user_id`, `created_at`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- ANALYTICS EVENTS TABLE
-- =====================================================================
CREATE TABLE IF NOT EXISTS `analytics_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_name` varchar(100) NOT NULL,
  `user_token` varchar(64) DEFAULT NULL,
  `session_id` varchar(64) DEFAULT NULL,
  `properties` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  INDEX `idx_event_name_time` (`event_name`, `created_at`),
  INDEX `idx_session_events` (`session_id`, `created_at`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- SAMPLE DATA FOR TESTING (Optional)
-- =====================================================================

-- Uncomment these lines to add sample data
-- INSERT IGNORE INTO users (google_sub, email, name, role) VALUES 
-- ('sample_google_id', 'test@meeplify.it', 'Test User', 'user');

-- INSERT IGNORE INTO checklists (title, description, owner_id) VALUES 
-- ('My First Checklist', 'This is a test checklist', 1);

-- INSERT IGNORE INTO sections (checklist_id, name, order_pos) VALUES 
-- (1, 'Getting Started', 1),
-- (1, 'Advanced Tasks', 2);

-- INSERT IGNORE INTO items (checklist_id, section_id, text, completed, order_pos) VALUES 
-- (1, 1, 'Create account', 1, 1),
-- (1, 1, 'Setup project', 0, 2),
-- (1, 2, 'Configure advanced settings', 0, 1),
-- (1, 2, 'Deploy to production', 0, 2);