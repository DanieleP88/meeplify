# Meeplify - Database Reference

## Database Structure Overview

Meeplify utilizza MySQL/MariaDB con le seguenti tabelle principali:

### Core Tables

#### `users` - User Management
```sql
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `google_sub` varchar(100) NOT NULL,     -- Google OAuth Subject ID  
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
  UNIQUE KEY `uk_email` (`email`)
)
```

#### `checklists` - Main Checklist Entities
```sql
CREATE TABLE `checklists` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `owner_id` int(11) NOT NULL,              -- References users.id
  `share_view_token` varchar(64) DEFAULT NULL,
  `is_public` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL, -- Soft delete
  
  PRIMARY KEY (`id`),
  KEY `idx_owner_id` (`owner_id`),
  KEY `idx_owner_active` (`owner_id`, `deleted_at`)
)
```

#### `sections` - Checklist Organization
```sql
CREATE TABLE `sections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `checklist_id` int(11) NOT NULL,         -- References checklists.id
  `name` varchar(255) NOT NULL,
  `order_pos` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  KEY `idx_checklist_id` (`checklist_id`)
)
```

#### `items` - Individual Checklist Items
```sql
CREATE TABLE `items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `checklist_id` int(11) NOT NULL,         -- References checklists.id
  `section_id` int(11) NOT NULL,           -- References sections.id
  `text` text NOT NULL,
  `completed` tinyint(1) NOT NULL DEFAULT 0,  -- IMPORTANT: Field name is 'completed', NOT 'is_done'
  `order_pos` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  KEY `idx_checklist_id` (`checklist_id`),
  KEY `idx_checklist_completion` (`checklist_id`, `completed`)
)
```

### Feature Tables

#### `tags` - Item Tagging System
```sql
CREATE TABLE `tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `color` varchar(7) NOT NULL DEFAULT '#007bff',
  `emoji` varchar(10) DEFAULT '🏷️',
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_tag_name` (`user_id`, `name`)
)
```

#### `item_tags` - Many-to-Many Item-Tag Relationship
```sql
CREATE TABLE `item_tags` (
  `item_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL,
  
  PRIMARY KEY (`item_id`, `tag_id`)
)
```

#### `collaborators` - Checklist Sharing
```sql
CREATE TABLE `collaborators` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `checklist_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` enum('viewer','collaborator') NOT NULL DEFAULT 'viewer',
  `invited_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_checklist_user` (`checklist_id`, `user_id`)
)
```

### Template System

#### `templates` - Admin-Created Templates
```sql
CREATE TABLE `templates` (
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
  
  PRIMARY KEY (`id`)
)
```

#### `template_sections` & `template_items` & `template_tags`
Similar structure to main tables but for template definitions.

### Audit & Analytics

#### `audit_log` - Security Audit Trail
```sql
CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_type` varchar(100) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `details` json DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  KEY `idx_event_type` (`event_type`)
)
```

#### `analytics_events` - Privacy-Focused Analytics
```sql
CREATE TABLE `analytics_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_name` varchar(100) NOT NULL,
  `user_token` varchar(64) DEFAULT NULL,
  `session_id` varchar(64) DEFAULT NULL,
  `properties` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`)
)
```

## Important Notes

### Column Naming Conventions
- **CRITICAL**: Use `owner_id` NOT `owner_user_id` in checklists table
- **CRITICAL**: Use `completed` NOT `is_done` in items table
- These naming conventions must match the API queries in ChecklistHandler.php

### Key Relationships
- `checklists.owner_id` → `users.id`
- `sections.checklist_id` → `checklists.id`
- `items.checklist_id` → `checklists.id`
- `items.section_id` → `sections.id`

### Soft Delete
- Checklists use soft delete via `deleted_at` timestamp
- Items are hard-deleted when parent checklist is soft-deleted

### Performance Indexes
- Primary focus on `owner_id` and `checklist_id` lookups
- Completion tracking via `checklist_id + completed` composite index

## Database Creation

Use the nuclear_database_reset.php script for fresh installations:
```bash
php nuclear_database_reset.php
```

Or manually import one of:
- `database_schema_minimal.sql` (recommended - no foreign keys)
- `database_schema_fixed.sql` (with foreign key constraints)

## API Integration

The main API query pattern used by ChecklistHandler::getChecklists():

```sql
SELECT c.id, c.title, c.description, c.created_at, c.updated_at,
       COUNT(DISTINCT s.id) as section_count,
       COUNT(DISTINCT i.id) as item_count,
       COUNT(DISTINCT CASE WHEN i.completed = 1 THEN i.id END) as completed_count
FROM checklists c
LEFT JOIN sections s ON c.id = s.checklist_id
LEFT JOIN items i ON c.id = i.checklist_id
WHERE c.owner_id = ? AND c.deleted_at IS NULL
GROUP BY c.id
ORDER BY c.updated_at DESC
```

This query MUST work for the app to function correctly.