-- Meeplify Database Schema - Fixed Version
-- Execute these SQL commands in your MySQL database
-- This schema supports Google OAuth, role-based access, collaboration, audit logging, and templates

-- =====================================================================
-- USERS TABLE - Core user management with Google OAuth integration
-- =====================================================================
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `google_sub` varchar(100) NOT NULL COMMENT 'Google OAuth subject ID',
  `email` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `surname` varchar(255) DEFAULT NULL COMMENT 'User surname from Google',
  `role` enum('user','admin') NOT NULL DEFAULT 'user',
  `active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Account active status',
  `last_login` timestamp NULL DEFAULT NULL COMMENT 'Track user activity',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_google_sub` (`google_sub`) COMMENT 'Unique Google OAuth ID',
  UNIQUE KEY `uk_email` (`email`) COMMENT 'Unique email constraint',
  
  -- Performance indexes for common queries
  INDEX `idx_active_role` (`active`, `role`) COMMENT 'Fast admin/active user lookups',
  INDEX `idx_created_at` (`created_at`) COMMENT 'User registration analytics'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='User accounts with Google OAuth';

-- =====================================================================
-- CHECKLISTS TABLE - Core checklist management with soft delete
-- =====================================================================
CREATE TABLE IF NOT EXISTS `checklists` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `owner_id` int(11) NOT NULL,
  `share_view_token` varchar(64) DEFAULT NULL COMMENT 'Public sharing token for view-only access',
  `is_public` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Public sharing enabled',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL COMMENT 'Soft delete for 30-day recovery',
  
  PRIMARY KEY (`id`),
  
  -- Foreign key with CASCADE for complete user data removal
  CONSTRAINT `fk_checklists_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  
  -- Performance indexes
  INDEX `idx_owner_active` (`owner_id`, `deleted_at`) COMMENT 'Fast user checklist queries',
  INDEX `idx_deleted_recovery` (`deleted_at`) COMMENT 'Trash/recovery operations',
  INDEX `idx_updated_at` (`updated_at`) COMMENT 'Recent activity sorting',
  
  -- Unique constraint for public sharing
  UNIQUE KEY `uk_share_token` (`share_view_token`) COMMENT 'Unique public sharing tokens'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Checklists with soft delete and sharing';

-- =====================================================================
-- SECTIONS TABLE - Checklist organization
-- =====================================================================
CREATE TABLE IF NOT EXISTS `sections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `checklist_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `order_pos` int(11) NOT NULL DEFAULT 0 COMMENT 'Section ordering within checklist',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  
  -- CASCADE delete when parent checklist is deleted
  CONSTRAINT `fk_sections_checklist` FOREIGN KEY (`checklist_id`) REFERENCES `checklists` (`id`) ON DELETE CASCADE,
  
  -- Performance indexes
  INDEX `idx_checklist_order` (`checklist_id`, `order_pos`) COMMENT 'Fast section ordering',
  INDEX `idx_checklist_id` (`checklist_id`) COMMENT 'Checklist section lookup'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Checklist sections for organization';

-- =====================================================================
-- ITEMS TABLE - Individual checklist items
-- =====================================================================
CREATE TABLE IF NOT EXISTS `items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `checklist_id` int(11) NOT NULL COMMENT 'Denormalized for performance',
  `section_id` int(11) NOT NULL,
  `text` text NOT NULL,
  `completed` tinyint(1) NOT NULL DEFAULT 0,
  `order_pos` int(11) NOT NULL DEFAULT 0 COMMENT 'Item ordering within section',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  
  -- CASCADE delete maintains referential integrity
  CONSTRAINT `fk_items_checklist` FOREIGN KEY (`checklist_id`) REFERENCES `checklists` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_items_section` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE,
  
  -- Performance indexes for common queries
  INDEX `idx_checklist_completion` (`checklist_id`, `completed`) COMMENT 'Progress calculation',
  INDEX `idx_section_order` (`section_id`, `order_pos`) COMMENT 'Item ordering',
  INDEX `idx_checklist_id` (`checklist_id`) COMMENT 'Checklist items lookup'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Checklist items with completion status';

-- =====================================================================
-- TAGS TABLE - Item tagging system
-- =====================================================================
CREATE TABLE IF NOT EXISTS `tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `color` varchar(7) NOT NULL DEFAULT '#007bff' COMMENT 'Hex color code',
  `emoji` varchar(10) DEFAULT '🏷️' COMMENT 'Optional emoji for tag',
  `user_id` int(11) NOT NULL COMMENT 'Tag owner for personalization',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  
  -- CASCADE delete when user is deleted
  CONSTRAINT `fk_tags_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  
  -- User-specific unique tag names
  UNIQUE KEY `uk_user_tag_name` (`user_id`, `name`) COMMENT 'Unique tag names per user',
  
  -- Performance indexes
  INDEX `idx_user_tags` (`user_id`) COMMENT 'User tag management'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='User-specific item tags';

-- =====================================================================
-- ITEM_TAGS TABLE - Many-to-many relationship between items and tags
-- =====================================================================
CREATE TABLE IF NOT EXISTS `item_tags` (
  `item_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL,
  
  PRIMARY KEY (`item_id`, `tag_id`),
  
  -- CASCADE delete maintains integrity when items or tags are deleted
  CONSTRAINT `fk_item_tags_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_item_tags_tag` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE,
  
  -- Reverse lookup index
  INDEX `idx_tag_items` (`tag_id`) COMMENT 'Find all items with specific tag'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Item-Tag relationships';

-- =====================================================================
-- COLLABORATORS TABLE - Checklist sharing and permissions
-- =====================================================================
CREATE TABLE IF NOT EXISTS `collaborators` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `checklist_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` enum('viewer','collaborator') NOT NULL DEFAULT 'viewer' COMMENT 'Collaboration permissions',
  `invited_by` int(11) DEFAULT NULL COMMENT 'Who invited this collaborator',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  
  -- CASCADE delete when checklist or user is deleted
  CONSTRAINT `fk_collaborators_checklist` FOREIGN KEY (`checklist_id`) REFERENCES `checklists` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_collaborators_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_collaborators_inviter` FOREIGN KEY (`invited_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  
  -- Prevent duplicate collaborations
  UNIQUE KEY `uk_checklist_user` (`checklist_id`, `user_id`) COMMENT 'One collaboration per user per checklist',
  
  -- Performance indexes for access control queries
  INDEX `idx_user_collaborations` (`user_id`) COMMENT 'User shared checklists lookup',
  INDEX `idx_checklist_collaborators` (`checklist_id`) COMMENT 'Checklist collaborator list'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Checklist collaboration permissions';

-- =====================================================================
-- TEMPLATES TABLE - Admin-created checklist templates
-- =====================================================================
CREATE TABLE IF NOT EXISTS `templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(100) NOT NULL DEFAULT 'general' COMMENT 'Template categorization',
  `difficulty_level` enum('beginner','intermediate','advanced') NOT NULL DEFAULT 'beginner',
  `estimated_time` varchar(50) DEFAULT NULL COMMENT 'Estimated completion time',
  `active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Template visibility',
  `created_by` int(11) NOT NULL COMMENT 'Admin who created template',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  
  -- Foreign key constraint - use same data types
  KEY `idx_created_by` (`created_by`),
  
  -- Performance indexes
  INDEX `idx_category_active` (`category`, `active`) COMMENT 'Template browsing'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Admin-created checklist templates';

-- Add foreign key constraint after table creation to avoid circular dependencies
ALTER TABLE `templates` ADD CONSTRAINT `fk_templates_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

-- =====================================================================
-- TEMPLATE_SECTIONS TABLE - Template section structure
-- =====================================================================
CREATE TABLE IF NOT EXISTS `template_sections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `template_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `order_pos` int(11) NOT NULL DEFAULT 0,
  
  PRIMARY KEY (`id`),
  
  -- Foreign key with explicit index
  KEY `idx_template_id` (`template_id`),
  CONSTRAINT `fk_template_sections_template` FOREIGN KEY (`template_id`) REFERENCES `templates` (`id`) ON DELETE CASCADE,
  
  INDEX `idx_template_order` (`template_id`, `order_pos`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Template section definitions';

-- =====================================================================
-- TEMPLATE_ITEMS TABLE - Template item structure
-- =====================================================================
CREATE TABLE IF NOT EXISTS `template_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `template_id` int(11) NOT NULL COMMENT 'Denormalized for performance',
  `section_id` int(11) NOT NULL,
  `text` text NOT NULL,
  `order_pos` int(11) NOT NULL DEFAULT 0,
  
  PRIMARY KEY (`id`),
  
  -- Foreign keys with explicit indexes
  KEY `idx_template_id` (`template_id`),
  KEY `idx_section_id` (`section_id`),
  
  CONSTRAINT `fk_template_items_template` FOREIGN KEY (`template_id`) REFERENCES `templates` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_template_items_section` FOREIGN KEY (`section_id`) REFERENCES `template_sections` (`id`) ON DELETE CASCADE,
  
  INDEX `idx_section_order` (`section_id`, `order_pos`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Template item definitions';

-- =====================================================================
-- TEMPLATE_TAGS TABLE - Template default tags (FIXED)
-- =====================================================================
CREATE TABLE IF NOT EXISTS `template_tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `template_id` int(11) NOT NULL,
  `tag_name` varchar(100) NOT NULL,
  `tag_color` varchar(7) NOT NULL DEFAULT '#007bff',
  `tag_emoji` varchar(10) DEFAULT '🏷️',
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_template_tag` (`template_id`, `tag_name`),
  
  -- Foreign key with explicit index
  KEY `idx_template_id` (`template_id`),
  CONSTRAINT `fk_template_tags_template` FOREIGN KEY (`template_id`) REFERENCES `templates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Template default tags';

-- =====================================================================
-- AUDIT_LOG TABLE - Security and compliance logging
-- =====================================================================
CREATE TABLE IF NOT EXISTS `audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_type` varchar(100) NOT NULL COMMENT 'Type of audited event',
  `user_id` int(11) DEFAULT NULL COMMENT 'User who performed action',
  `details` json DEFAULT NULL COMMENT 'Event-specific details',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'IPv4/IPv6 address',
  `user_agent` varchar(500) DEFAULT NULL COMMENT 'Browser/client information',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  
  -- Foreign key with explicit index
  KEY `idx_user_id` (`user_id`),
  CONSTRAINT `fk_audit_log_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  
  -- Performance indexes for audit queries and analytics
  INDEX `idx_event_type` (`event_type`) COMMENT 'Event type filtering',
  INDEX `idx_user_events` (`user_id`, `created_at`) COMMENT 'User activity timeline',
  INDEX `idx_created_at` (`created_at`) COMMENT 'Time-based audit queries',
  INDEX `idx_event_user_time` (`event_type`, `user_id`, `created_at`) COMMENT 'Complex audit filtering'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Security and compliance audit log';

-- =====================================================================
-- ANALYTICS EVENTS TABLE - Privacy-focused user analytics
-- =====================================================================
CREATE TABLE IF NOT EXISTS `analytics_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_name` varchar(100) NOT NULL COMMENT 'Analytics event name',
  `user_token` varchar(64) DEFAULT NULL COMMENT 'Pseudonymized user identifier',
  `session_id` varchar(64) DEFAULT NULL COMMENT 'Session tracking',
  `properties` json DEFAULT NULL COMMENT 'Event properties (anonymized)',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  
  -- Indexes for analytics queries (high volume, write-heavy)
  INDEX `idx_event_name_time` (`event_name`, `created_at`) COMMENT 'Event analysis',
  INDEX `idx_session_events` (`session_id`, `created_at`) COMMENT 'Session analytics',
  INDEX `idx_created_at` (`created_at`) COMMENT 'Time-based analytics'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Privacy-focused analytics events';

-- =====================================================================
-- PERFORMANCE OPTIMIZATION VIEWS
-- =====================================================================

-- Create view for efficient checklist statistics
CREATE OR REPLACE VIEW checklist_stats AS
SELECT 
    c.id,
    c.title,
    c.owner_id,
    COUNT(DISTINCT s.id) as section_count,
    COUNT(DISTINCT i.id) as total_items,
    COUNT(DISTINCT CASE WHEN i.completed = 1 THEN i.id END) as completed_items,
    CASE 
        WHEN COUNT(DISTINCT i.id) = 0 THEN 0
        ELSE ROUND((COUNT(DISTINCT CASE WHEN i.completed = 1 THEN i.id END) * 100.0) / COUNT(DISTINCT i.id), 2)
    END as completion_percentage,
    c.updated_at
FROM checklists c
LEFT JOIN sections s ON c.id = s.checklist_id
LEFT JOIN items i ON c.id = i.checklist_id
WHERE c.deleted_at IS NULL
GROUP BY c.id, c.title, c.owner_id, c.updated_at;

-- Create view for user collaboration overview
CREATE OR REPLACE VIEW user_collaborations AS
SELECT 
    u.id as user_id,
    u.name as user_name,
    u.email,
    c.id as checklist_id,
    c.title as checklist_title,
    c.owner_id,
    CASE 
        WHEN c.owner_id = u.id THEN 'owner'
        ELSE col.role
    END as user_role,
    c.updated_at
FROM users u
LEFT JOIN collaborators col ON u.id = col.user_id
LEFT JOIN checklists c ON (c.id = col.checklist_id OR c.owner_id = u.id)
WHERE c.deleted_at IS NULL OR c.deleted_at IS NULL;

-- =====================================================================
-- INSERT SAMPLE DATA (Optional - for testing)
-- =====================================================================

-- Sample admin user (you should update this with real Google OAuth data)
-- INSERT IGNORE INTO users (google_sub, email, name, role) VALUES 
-- ('google_sub_id_here', 'admin@meeplify.it', 'Admin User', 'admin');

-- Sample template categories
-- INSERT IGNORE INTO templates (name, description, category, difficulty_level, created_by) VALUES
-- ('Progetto Web Base', 'Template per iniziare un nuovo progetto web', 'development', 'beginner', 1),
-- ('Checklist Viaggio', 'Lista completa per preparare un viaggio', 'travel', 'beginner', 1),
-- ('Setup Server', 'Procedura per configurare un nuovo server', 'sysadmin', 'advanced', 1);