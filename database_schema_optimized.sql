-- =====================================================================
-- BORDLY (MEEPLIFY) DATABASE SCHEMA - OPTIMIZED VERSION
-- =====================================================================
-- Complete MySQL/MariaDB schema for collaborative checklist application
-- Features: Google OAuth, RBAC, soft delete, audit logging, analytics
-- Optimized for: Performance, Scalability, Data Integrity, Privacy
-- =====================================================================

-- =====================================================================
-- 1. USERS TABLE - Core user management with Google OAuth
-- =====================================================================
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `google_sub` varchar(100) NOT NULL COMMENT 'Google OAuth subject ID - unique identifier',
  `email` varchar(320) NOT NULL COMMENT 'RFC 5321 compliant email length',
  `name` varchar(255) NOT NULL COMMENT 'Display name from Google profile',
  `surname` varchar(255) DEFAULT NULL COMMENT 'Family name from Google profile',
  `role` enum('user','admin') NOT NULL DEFAULT 'user' COMMENT 'System role for access control',
  `active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Account status - soft disable',
  `last_login` timestamp NULL DEFAULT NULL COMMENT 'Track user activity for analytics',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  
  -- Unique constraints for authentication
  UNIQUE KEY `uk_google_sub` (`google_sub`) COMMENT 'Prevent duplicate Google accounts',
  UNIQUE KEY `uk_email` (`email`) COMMENT 'Enforce email uniqueness',
  
  -- Performance indexes for common access patterns
  INDEX `idx_active_role` (`active`, `role`) COMMENT 'Fast admin/active user queries',
  INDEX `idx_last_login` (`last_login`) COMMENT 'User activity analytics',
  INDEX `idx_created_at` (`created_at`) COMMENT 'Registration timeline analysis'
  
) ENGINE=InnoDB 
DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='User accounts with Google OAuth authentication';

-- =====================================================================
-- 2. CHECKLISTS TABLE - Core checklist management with sharing
-- =====================================================================
CREATE TABLE IF NOT EXISTS `checklists` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL COMMENT 'Checklist title - required field',
  `description` text DEFAULT NULL COMMENT 'Optional detailed description',
  `owner_id` int(11) NOT NULL COMMENT 'User who owns this checklist',
  `share_view_token` varchar(64) DEFAULT NULL COMMENT 'UUID for public view-only sharing',
  `is_public` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Enable/disable public sharing',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL COMMENT 'Soft delete - 30 day recovery window',
  
  PRIMARY KEY (`id`),
  
  -- Foreign key with CASCADE - remove checklists when user deleted
  CONSTRAINT `fk_checklists_owner` 
    FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) 
    ON DELETE CASCADE ON UPDATE CASCADE,
  
  -- Unique constraint for sharing tokens
  UNIQUE KEY `uk_share_token` (`share_view_token`) 
    COMMENT 'Prevent duplicate sharing tokens',
  
  -- Performance indexes optimized for common queries
  INDEX `idx_owner_active` (`owner_id`, `deleted_at`) 
    COMMENT 'User checklists (exclude deleted)',
  INDEX `idx_deleted_recovery` (`deleted_at`) 
    COMMENT 'Trash/recovery operations',
  INDEX `idx_updated_recent` (`updated_at` DESC) 
    COMMENT 'Recently modified checklists',
  INDEX `idx_public_sharing` (`is_public`, `share_view_token`) 
    COMMENT 'Public checklist access'
    
) ENGINE=InnoDB 
DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Checklists with soft delete and public sharing';

-- =====================================================================
-- 3. SECTIONS TABLE - Checklist organization structure
-- =====================================================================
CREATE TABLE IF NOT EXISTS `sections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `checklist_id` int(11) NOT NULL COMMENT 'Parent checklist reference',
  `name` varchar(255) NOT NULL COMMENT 'Section name for organization',
  `order_pos` int(11) NOT NULL DEFAULT 0 COMMENT 'Display order within checklist',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  
  -- CASCADE delete when parent checklist removed
  CONSTRAINT `fk_sections_checklist` 
    FOREIGN KEY (`checklist_id`) REFERENCES `checklists` (`id`) 
    ON DELETE CASCADE ON UPDATE CASCADE,
  
  -- Performance indexes for ordering and lookup
  INDEX `idx_checklist_order` (`checklist_id`, `order_pos`) 
    COMMENT 'Ordered section retrieval',
  INDEX `idx_checklist_lookup` (`checklist_id`) 
    COMMENT 'All sections for checklist'
    
) ENGINE=InnoDB 
DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Checklist sections for item organization';

-- =====================================================================
-- 4. ITEMS TABLE - Individual checklist items with completion status
-- =====================================================================
CREATE TABLE IF NOT EXISTS `items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `checklist_id` int(11) NOT NULL COMMENT 'Parent checklist - denormalized for performance',
  `section_id` int(11) DEFAULT NULL COMMENT 'Optional section assignment - NULL for unsectioned',
  `text` text NOT NULL COMMENT 'Item description/task text',
  `completed` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Completion status',
  `order_pos` int(11) NOT NULL DEFAULT 0 COMMENT 'Display order within section',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  
  -- Foreign key constraints with proper cascade behavior
  CONSTRAINT `fk_items_checklist` 
    FOREIGN KEY (`checklist_id`) REFERENCES `checklists` (`id`) 
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_items_section` 
    FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) 
    ON DELETE SET NULL ON UPDATE CASCADE,
  
  -- Performance indexes for common query patterns
  INDEX `idx_checklist_completion` (`checklist_id`, `completed`) 
    COMMENT 'Progress calculation queries',
  INDEX `idx_section_order` (`section_id`, `order_pos`) 
    COMMENT 'Ordered item retrieval within section',
  INDEX `idx_checklist_all` (`checklist_id`, `section_id`) 
    COMMENT 'All items for checklist organization',
  INDEX `idx_updated_recent` (`updated_at` DESC) 
    COMMENT 'Recent item changes'
    
) ENGINE=InnoDB 
DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Checklist items with completion tracking';

-- =====================================================================
-- 5. TAGS TABLE - Global tag system for item categorization
-- =====================================================================
CREATE TABLE IF NOT EXISTS `tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL COMMENT 'Tag name - globally unique',
  `color` varchar(7) NOT NULL DEFAULT '#007bff' COMMENT 'Hex color code for UI',
  `emoji` varchar(10) DEFAULT '🏷️' COMMENT 'Optional emoji representation',
  `created_by` int(11) NOT NULL COMMENT 'User who created this tag',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  
  -- SET NULL to preserve tag history when creator deleted
  CONSTRAINT `fk_tags_creator` 
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) 
    ON DELETE SET NULL ON UPDATE CASCADE,
  
  -- Global unique tag names
  UNIQUE KEY `uk_tag_name` (`name`) 
    COMMENT 'Prevent duplicate tag names globally',
  
  -- Performance index
  INDEX `idx_creator` (`created_by`) 
    COMMENT 'Tags created by user'
    
) ENGINE=InnoDB 
DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Global tags for item categorization';

-- =====================================================================
-- 6. ITEM_TAGS TABLE - Many-to-many item-tag relationships
-- =====================================================================
CREATE TABLE IF NOT EXISTS `item_tags` (
  `item_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`item_id`, `tag_id`),
  
  -- CASCADE delete maintains integrity when items or tags removed
  CONSTRAINT `fk_item_tags_item` 
    FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) 
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_item_tags_tag` 
    FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) 
    ON DELETE CASCADE ON UPDATE CASCADE,
  
  -- Reverse lookup index for tag-based queries
  INDEX `idx_tag_items` (`tag_id`, `created_at`) 
    COMMENT 'Find all items with specific tag'
    
) ENGINE=InnoDB 
DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Item-Tag many-to-many relationships';

-- =====================================================================
-- 7. COLLABORATORS TABLE - Checklist sharing and permissions
-- =====================================================================
CREATE TABLE IF NOT EXISTS `collaborators` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `checklist_id` int(11) NOT NULL COMMENT 'Shared checklist reference',
  `user_id` int(11) NOT NULL COMMENT 'Collaborating user',
  `role` enum('viewer','collaborator') NOT NULL DEFAULT 'viewer' 
    COMMENT 'Permission level - viewer (read-only) or collaborator (edit)',
  `invited_by` int(11) DEFAULT NULL COMMENT 'User who sent the invitation',
  `invited_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Invitation timestamp',
  `accepted_at` timestamp NULL DEFAULT NULL COMMENT 'When invitation was accepted',
  
  PRIMARY KEY (`id`),
  
  -- Foreign key constraints with appropriate cascade rules
  CONSTRAINT `fk_collaborators_checklist` 
    FOREIGN KEY (`checklist_id`) REFERENCES `checklists` (`id`) 
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_collaborators_user` 
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) 
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_collaborators_inviter` 
    FOREIGN KEY (`invited_by`) REFERENCES `users` (`id`) 
    ON DELETE SET NULL ON UPDATE CASCADE,
  
  -- Unique constraint prevents duplicate collaborations
  UNIQUE KEY `uk_checklist_user` (`checklist_id`, `user_id`) 
    COMMENT 'One collaboration record per user per checklist',
  
  -- Performance indexes for access control queries
  INDEX `idx_user_collaborations` (`user_id`, `role`) 
    COMMENT 'User shared checklist lookup with permissions',
  INDEX `idx_checklist_collaborators` (`checklist_id`, `role`) 
    COMMENT 'Checklist collaborator management',
  INDEX `idx_pending_invites` (`user_id`, `accepted_at`) 
    COMMENT 'Pending invitation queries'
    
) ENGINE=InnoDB 
DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Checklist collaboration and permission management';

-- =====================================================================
-- 8. AUDIT_LOG TABLE - Security and compliance logging (optimized)
-- =====================================================================
CREATE TABLE IF NOT EXISTS `audit_log` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT 'Use BIGINT for high-volume logging',
  `event_type` varchar(100) NOT NULL COMMENT 'Categorized event type',
  `user_id` int(11) DEFAULT NULL COMMENT 'User performing action - NULL for system',
  `target_type` varchar(50) DEFAULT NULL COMMENT 'Type of affected resource',
  `target_id` int(11) DEFAULT NULL COMMENT 'ID of affected resource',
  `details` json DEFAULT NULL COMMENT 'Event-specific data - structured',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'IPv4/IPv6 client address',
  `user_agent` varchar(500) DEFAULT NULL COMMENT 'Client browser/app info',
  `session_id` varchar(128) DEFAULT NULL COMMENT 'Session tracking',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  
  -- SET NULL preserves audit trail when user deleted
  CONSTRAINT `fk_audit_log_user` 
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) 
    ON DELETE SET NULL ON UPDATE CASCADE,
  
  -- Optimized indexes for audit queries and compliance
  INDEX `idx_event_time` (`event_type`, `created_at`) 
    COMMENT 'Event filtering with time range',
  INDEX `idx_user_activity` (`user_id`, `created_at`) 
    COMMENT 'User activity timeline',
  INDEX `idx_target_audit` (`target_type`, `target_id`, `created_at`) 
    COMMENT 'Resource-specific audit trail',
  INDEX `idx_created_at` (`created_at`) 
    COMMENT 'Time-based queries and cleanup',
  INDEX `idx_session_events` (`session_id`, `created_at`) 
    COMMENT 'Session-based security analysis'
    
) ENGINE=InnoDB 
DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Security audit log - high performance write-optimized';

-- =====================================================================
-- 9. ANALYTICS_EVENTS TABLE - Privacy-focused user behavior tracking
-- =====================================================================
CREATE TABLE IF NOT EXISTS `analytics_events` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT 'High-volume event logging',
  `event_name` varchar(100) NOT NULL COMMENT 'Standardized event name',
  `user_token` varchar(64) DEFAULT NULL COMMENT 'Pseudonymized user identifier',
  `session_id` varchar(64) DEFAULT NULL COMMENT 'Session grouping',
  `properties` json DEFAULT NULL COMMENT 'Event properties - anonymized data only',
  `referrer` varchar(255) DEFAULT NULL COMMENT 'Traffic source tracking',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  
  -- High-performance indexes for analytics queries
  INDEX `idx_event_analysis` (`event_name`, `created_at`) 
    COMMENT 'Event trend analysis',
  INDEX `idx_session_flow` (`session_id`, `created_at`) 
    COMMENT 'User session analysis',
  INDEX `idx_user_behavior` (`user_token`, `event_name`, `created_at`) 
    COMMENT 'User behavior patterns',
  INDEX `idx_created_at` (`created_at`) 
    COMMENT 'Time-based analytics and cleanup'
    
) ENGINE=InnoDB 
DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Privacy-focused analytics - no PII stored';

-- =====================================================================
-- 10. TEMPLATES TABLE - Admin-created checklist templates
-- =====================================================================
CREATE TABLE IF NOT EXISTS `templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL COMMENT 'Template display name',
  `description` text DEFAULT NULL COMMENT 'Template description and usage',
  `category` varchar(100) NOT NULL DEFAULT 'general' COMMENT 'Template categorization',
  `difficulty_level` enum('beginner','intermediate','advanced') NOT NULL DEFAULT 'beginner',
  `estimated_time` varchar(50) DEFAULT NULL COMMENT 'Estimated completion time',
  `tags` varchar(500) DEFAULT NULL COMMENT 'Comma-separated template tags',
  `active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Template availability',
  `created_by` int(11) NOT NULL COMMENT 'Admin who created template',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  
  -- SET NULL preserves templates when admin deleted
  CONSTRAINT `fk_templates_creator` 
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) 
    ON DELETE SET NULL ON UPDATE CASCADE,
  
  -- Performance indexes for template browsing
  INDEX `idx_category_active` (`category`, `active`, `difficulty_level`) 
    COMMENT 'Template catalog browsing',
  INDEX `idx_creator_templates` (`created_by`) 
    COMMENT 'Admin template management'
    
) ENGINE=InnoDB 
DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Admin-created checklist templates';

-- =====================================================================
-- 11. TEMPLATE_SECTIONS TABLE - Template structure definition
-- =====================================================================
CREATE TABLE IF NOT EXISTS `template_sections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `template_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL COMMENT 'Section name in template',
  `order_pos` int(11) NOT NULL DEFAULT 0 COMMENT 'Section ordering',
  
  PRIMARY KEY (`id`),
  
  CONSTRAINT `fk_template_sections_template` 
    FOREIGN KEY (`template_id`) REFERENCES `templates` (`id`) 
    ON DELETE CASCADE ON UPDATE CASCADE,
  
  INDEX `idx_template_order` (`template_id`, `order_pos`) 
    COMMENT 'Ordered template section retrieval'
    
) ENGINE=InnoDB 
DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Template section definitions';

-- =====================================================================
-- 12. TEMPLATE_ITEMS TABLE - Template item definitions
-- =====================================================================
CREATE TABLE IF NOT EXISTS `template_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `template_id` int(11) NOT NULL COMMENT 'Denormalized for performance',
  `section_id` int(11) NOT NULL COMMENT 'Template section reference',
  `text` text NOT NULL COMMENT 'Template item text',
  `order_pos` int(11) NOT NULL DEFAULT 0 COMMENT 'Item ordering within section',
  
  PRIMARY KEY (`id`),
  
  CONSTRAINT `fk_template_items_template` 
    FOREIGN KEY (`template_id`) REFERENCES `templates` (`id`) 
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_template_items_section` 
    FOREIGN KEY (`section_id`) REFERENCES `template_sections` (`id`) 
    ON DELETE CASCADE ON UPDATE CASCADE,
  
  INDEX `idx_template_lookup` (`template_id`) 
    COMMENT 'All items for template',
  INDEX `idx_section_order` (`section_id`, `order_pos`) 
    COMMENT 'Ordered template item retrieval'
    
) ENGINE=InnoDB 
DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Template item definitions';

-- =====================================================================
-- PERFORMANCE OPTIMIZATION VIEWS
-- =====================================================================

-- Efficient checklist statistics view
CREATE OR REPLACE VIEW `checklist_stats` AS
SELECT 
    c.id,
    c.title,
    c.owner_id,
    c.is_public,
    c.share_view_token,
    COUNT(DISTINCT s.id) as section_count,
    COUNT(DISTINCT i.id) as total_items,
    COUNT(DISTINCT CASE WHEN i.completed = 1 THEN i.id END) as completed_items,
    CASE 
        WHEN COUNT(DISTINCT i.id) = 0 THEN 0
        ELSE ROUND((COUNT(DISTINCT CASE WHEN i.completed = 1 THEN i.id END) * 100.0) / COUNT(DISTINCT i.id), 2)
    END as completion_percentage,
    c.updated_at,
    c.created_at
FROM checklists c
LEFT JOIN sections s ON c.id = s.checklist_id
LEFT JOIN items i ON c.id = i.checklist_id
WHERE c.deleted_at IS NULL
GROUP BY c.id, c.title, c.owner_id, c.is_public, c.share_view_token, c.updated_at, c.created_at;

-- User collaboration summary view
CREATE OR REPLACE VIEW `user_collaboration_summary` AS
SELECT 
    u.id as user_id,
    u.name,
    u.email,
    COUNT(DISTINCT CASE WHEN c.owner_id = u.id THEN c.id END) as owned_checklists,
    COUNT(DISTINCT CASE WHEN col.role = 'collaborator' THEN c.id END) as collaborative_checklists,
    COUNT(DISTINCT CASE WHEN col.role = 'viewer' THEN c.id END) as viewer_checklists,
    MAX(c.updated_at) as last_activity
FROM users u
LEFT JOIN checklists c ON u.id = c.owner_id AND c.deleted_at IS NULL
LEFT JOIN collaborators col ON u.id = col.user_id
WHERE u.active = 1
GROUP BY u.id, u.name, u.email;

-- =====================================================================
-- AUTOMATED MAINTENANCE TRIGGERS
-- =====================================================================

-- Update checklist timestamp when items change
DELIMITER //
CREATE TRIGGER `update_checklist_on_item_change` 
AFTER UPDATE ON `items`
FOR EACH ROW
BEGIN
    UPDATE checklists 
    SET updated_at = CURRENT_TIMESTAMP 
    WHERE id = NEW.checklist_id;
END//

-- Update checklist timestamp when sections change  
CREATE TRIGGER `update_checklist_on_section_change`
AFTER UPDATE ON `sections`
FOR EACH ROW
BEGIN
    UPDATE checklists 
    SET updated_at = CURRENT_TIMESTAMP 
    WHERE id = NEW.checklist_id;
END//
DELIMITER ;

-- =====================================================================
-- DATA VALIDATION CONSTRAINTS (Additional Business Rules)
-- =====================================================================

-- Ensure share tokens are properly formatted (UUIDs or secure random strings)
ALTER TABLE `checklists` 
ADD CONSTRAINT `chk_share_token_format` 
CHECK (`share_view_token` IS NULL OR LENGTH(`share_view_token`) >= 32);

-- Ensure order positions are non-negative
ALTER TABLE `sections` 
ADD CONSTRAINT `chk_section_order_positive` 
CHECK (`order_pos` >= 0);

ALTER TABLE `items` 
ADD CONSTRAINT `chk_item_order_positive` 
CHECK (`order_pos` >= 0);

-- Ensure completion status is boolean (0 or 1)
ALTER TABLE `items` 
ADD CONSTRAINT `chk_completion_boolean` 
CHECK (`completed` IN (0, 1));

-- =====================================================================
-- INITIAL CONFIGURATION AND OPTIMIZATION
-- =====================================================================

-- Set optimal MySQL settings for the application
-- These should be added to your my.cnf configuration file:

-- # InnoDB Optimization for collaborative application
-- innodb_buffer_pool_size = 256M          # Adjust based on available RAM
-- innodb_log_file_size = 64M              # Large enough for batch operations
-- innodb_flush_log_at_trx_commit = 2      # Better performance, slight durability trade-off
-- innodb_file_per_table = ON              # Better space management
-- 
-- # Query optimization
-- query_cache_type = ON                    # Enable query caching
-- query_cache_size = 32M                   # Reasonable cache size
-- 
-- # Connection handling
-- max_connections = 200                    # Adjust based on concurrent users
-- thread_cache_size = 16                   # Reduce thread creation overhead

-- =====================================================================
-- CLEANUP PROCEDURES (Run via scheduled events)
-- =====================================================================

-- Procedure to permanently delete old trash items
DELIMITER //
CREATE PROCEDURE `CleanupDeletedChecklists`()
BEGIN
    -- Delete checklists that have been in trash for more than 30 days
    DELETE FROM checklists 
    WHERE deleted_at IS NOT NULL 
    AND deleted_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
    
    SELECT ROW_COUNT() as deleted_checklists;
END//

-- Procedure to archive old audit logs
CREATE PROCEDURE `ArchiveOldAuditLogs`()
BEGIN
    -- Archive audit logs older than 1 year to separate table
    -- This procedure would need additional archive table creation
    
    -- For now, just delete very old logs (adjust retention as needed)
    DELETE FROM audit_log 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 2 YEAR);
    
    SELECT ROW_COUNT() as archived_audit_records;
END//

-- Procedure to clean old analytics events
CREATE PROCEDURE `CleanupAnalyticsEvents`()
BEGIN
    -- Keep only 6 months of analytics data
    DELETE FROM analytics_events 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 6 MONTH);
    
    SELECT ROW_COUNT() as cleaned_analytics_events;
END//
DELIMITER ;

-- =====================================================================
-- SECURITY AND MONITORING SETUP
-- =====================================================================

-- Enable general query log for monitoring (temporarily during setup)
-- SET GLOBAL general_log = 'ON';
-- SET GLOBAL log_output = 'TABLE';

-- Enable slow query log for performance monitoring
-- SET GLOBAL slow_query_log = 'ON';
-- SET GLOBAL long_query_time = 2;

-- =====================================================================
-- VERIFICATION QUERIES
-- =====================================================================

-- Verify all tables were created successfully
SELECT 
    table_name, 
    table_rows, 
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size (MB)',
    engine,
    table_collation
FROM information_schema.tables 
WHERE table_schema = DATABASE()
ORDER BY table_name;

-- Verify foreign key relationships
SELECT 
    constraint_name, 
    table_name, 
    column_name, 
    referenced_table_name, 
    referenced_column_name,
    delete_rule,
    update_rule
FROM information_schema.key_column_usage 
WHERE table_schema = DATABASE() 
AND referenced_table_name IS NOT NULL
ORDER BY table_name, constraint_name;

-- Verify indexes for performance
SELECT 
    table_name, 
    index_name, 
    column_name, 
    seq_in_index,
    cardinality
FROM information_schema.statistics 
WHERE table_schema = DATABASE() 
AND index_name != 'PRIMARY'
ORDER BY table_name, index_name, seq_in_index;

-- =====================================================================
-- SAMPLE ADMIN USER (UPDATE BEFORE USE)
-- =====================================================================

-- Insert initial admin user - REPLACE WITH YOUR ACTUAL GOOGLE ACCOUNT DATA
-- INSERT INTO users (google_sub, email, name, surname, role, active) 
-- VALUES (
--     'YOUR_GOOGLE_SUB_ID_HERE', 
--     'admin@yourdomain.com', 
--     'Admin', 
--     'User', 
--     'admin', 
--     1
-- );

-- =====================================================================
-- END OF OPTIMIZED SCHEMA
-- =====================================================================