-- Fix items table - add missing columns
-- Execute these one by one, ignore errors if columns already exist

-- Add completed column if missing
ALTER TABLE `items` ADD COLUMN `completed` tinyint(1) NOT NULL DEFAULT 0;

-- Add updated_at column if missing
ALTER TABLE `items` ADD COLUMN `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Add index for completed status queries
ALTER TABLE `items` ADD INDEX `idx_checklist_completed` (`checklist_id`, `completed`);

-- Show the final structure
DESCRIBE `items`;