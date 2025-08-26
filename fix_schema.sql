-- Fix missing columns in database schema

-- Check if completed column exists in items table, if not add it
ALTER TABLE `items` ADD COLUMN IF NOT EXISTS `completed` tinyint(1) NOT NULL DEFAULT 0;

-- Check if updated_at column exists in items table, if not add it  
ALTER TABLE `items` ADD COLUMN IF NOT EXISTS `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Add missing indexes if they don't exist
ALTER TABLE `items` ADD INDEX IF NOT EXISTS `idx_checklist_completed` (`checklist_id`, `completed`);

-- Verify the structure
DESCRIBE `items`;