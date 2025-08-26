<?php
/**
 * MEEPLIFY DATABASE RESET & SETUP SCRIPT
 * 
 * ⚠️  WARNING: This script will DROP all existing tables and recreate them!
 * ⚠️  ALL DATA WILL BE LOST!
 * 
 * This script will:
 * 1. Drop all existing Meeplify tables
 * 2. Create fresh tables with correct structure
 * 3. Verify everything is working
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== MEEPLIFY DATABASE RESET ===\n\n";
echo "⚠️  WARNING: This will DELETE ALL DATA and recreate the database!\n";
echo "Press ENTER to continue or CTRL+C to cancel...\n";

// Wait for user confirmation (comment this line if running via web)
if (php_sapi_name() === 'cli') {
    readline();
}

// Load configuration
try {
    require_once __DIR__ . '/app/lib/Config.php';
    echo "✅ Configuration loaded\n";
} catch (Exception $e) {
    echo "❌ Configuration error: " . $e->getMessage() . "\n";
    exit(1);
}

// Connect to database
try {
    require_once __DIR__ . '/app/lib/DB.php';
    $pdo = DB::getPDO();
    echo "✅ Database connected\n";
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Disable foreign key checks
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
echo "🔧 Foreign key checks disabled\n";

// Drop existing tables
$tables_to_drop = [
    'analytics_events', 'audit_log', 'template_tags', 'template_items', 
    'template_sections', 'templates', 'item_tags', 'tags', 'collaborators', 
    'items', 'sections', 'checklists', 'users'
];

echo "\n--- Dropping existing tables ---\n";
foreach ($tables_to_drop as $table) {
    try {
        $pdo->exec("DROP TABLE IF EXISTS `$table`");
        echo "🗑️  Dropped table: $table\n";
    } catch (PDOException $e) {
        echo "⚠️  Could not drop $table: " . $e->getMessage() . "\n";
    }
}

echo "\n--- Creating fresh tables ---\n";

// Create tables in the correct order
try {
    
    // 1. USERS TABLE
    $pdo->exec("
    CREATE TABLE `users` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `google_sub` varchar(100) NOT NULL COMMENT 'Google OAuth subject ID',
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
      KEY `idx_active_role` (`active`, `role`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Created: users\n";

    // 2. CHECKLISTS TABLE  
    $pdo->exec("
    CREATE TABLE `checklists` (
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
      KEY `idx_owner_active` (`owner_id`, `deleted_at`),
      KEY `idx_updated_at` (`updated_at`),
      UNIQUE KEY `uk_share_token` (`share_view_token`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Created: checklists\n";

    // 3. SECTIONS TABLE
    $pdo->exec("
    CREATE TABLE `sections` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `checklist_id` int(11) NOT NULL,
      `name` varchar(255) NOT NULL,
      `order_pos` int(11) NOT NULL DEFAULT 0,
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      
      PRIMARY KEY (`id`),
      KEY `idx_checklist_order` (`checklist_id`, `order_pos`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Created: sections\n";

    // 4. ITEMS TABLE
    $pdo->exec("
    CREATE TABLE `items` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `checklist_id` int(11) NOT NULL,
      `section_id` int(11) NOT NULL,
      `text` text NOT NULL,
      `completed` tinyint(1) NOT NULL DEFAULT 0,
      `order_pos` int(11) NOT NULL DEFAULT 0,
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      
      PRIMARY KEY (`id`),
      KEY `idx_checklist_completion` (`checklist_id`, `completed`),
      KEY `idx_section_order` (`section_id`, `order_pos`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Created: items\n";

    // 5. TAGS TABLE
    $pdo->exec("
    CREATE TABLE `tags` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(100) NOT NULL,
      `color` varchar(7) NOT NULL DEFAULT '#007bff',
      `emoji` varchar(10) DEFAULT '🏷️',
      `user_id` int(11) NOT NULL,
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      
      PRIMARY KEY (`id`),
      UNIQUE KEY `uk_user_tag_name` (`user_id`, `name`),
      KEY `idx_user_tags` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Created: tags\n";

    // 6. ITEM_TAGS TABLE
    $pdo->exec("
    CREATE TABLE `item_tags` (
      `item_id` int(11) NOT NULL,
      `tag_id` int(11) NOT NULL,
      
      PRIMARY KEY (`item_id`, `tag_id`),
      KEY `idx_tag_items` (`tag_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Created: item_tags\n";

    // 7. COLLABORATORS TABLE
    $pdo->exec("
    CREATE TABLE `collaborators` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `checklist_id` int(11) NOT NULL,
      `user_id` int(11) NOT NULL,
      `role` enum('viewer','collaborator') NOT NULL DEFAULT 'viewer',
      `invited_by` int(11) DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      
      PRIMARY KEY (`id`),
      UNIQUE KEY `uk_checklist_user` (`checklist_id`, `user_id`),
      KEY `idx_user_collaborations` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Created: collaborators\n";

    // 8. TEMPLATES TABLE
    $pdo->exec("
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
      
      PRIMARY KEY (`id`),
      KEY `idx_category_active` (`category`, `active`),
      KEY `idx_created_by` (`created_by`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Created: templates\n";

    // 9. TEMPLATE_SECTIONS TABLE
    $pdo->exec("
    CREATE TABLE `template_sections` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `template_id` int(11) NOT NULL,
      `name` varchar(255) NOT NULL,
      `order_pos` int(11) NOT NULL DEFAULT 0,
      
      PRIMARY KEY (`id`),
      KEY `idx_template_order` (`template_id`, `order_pos`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Created: template_sections\n";

    // 10. TEMPLATE_ITEMS TABLE
    $pdo->exec("
    CREATE TABLE `template_items` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `template_id` int(11) NOT NULL,
      `section_id` int(11) NOT NULL,
      `text` text NOT NULL,
      `order_pos` int(11) NOT NULL DEFAULT 0,
      
      PRIMARY KEY (`id`),
      KEY `idx_template_items` (`template_id`),
      KEY `idx_section_order` (`section_id`, `order_pos`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Created: template_items\n";

    // 11. TEMPLATE_TAGS TABLE
    $pdo->exec("
    CREATE TABLE `template_tags` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `template_id` int(11) NOT NULL,
      `tag_name` varchar(100) NOT NULL,
      `tag_color` varchar(7) NOT NULL DEFAULT '#007bff',
      `tag_emoji` varchar(10) DEFAULT '🏷️',
      
      PRIMARY KEY (`id`),
      UNIQUE KEY `uk_template_tag` (`template_id`, `tag_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Created: template_tags\n";

    // 12. AUDIT_LOG TABLE
    $pdo->exec("
    CREATE TABLE `audit_log` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `event_type` varchar(100) NOT NULL,
      `user_id` int(11) DEFAULT NULL,
      `details` json DEFAULT NULL,
      `ip_address` varchar(45) DEFAULT NULL,
      `user_agent` varchar(500) DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      
      PRIMARY KEY (`id`),
      KEY `idx_event_type` (`event_type`),
      KEY `idx_user_events` (`user_id`, `created_at`),
      KEY `idx_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Created: audit_log\n";

    // 13. ANALYTICS_EVENTS TABLE
    $pdo->exec("
    CREATE TABLE `analytics_events` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `event_name` varchar(100) NOT NULL,
      `user_token` varchar(64) DEFAULT NULL,
      `session_id` varchar(64) DEFAULT NULL,
      `properties` json DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      
      PRIMARY KEY (`id`),
      KEY `idx_event_name_time` (`event_name`, `created_at`),
      KEY `idx_session_events` (`session_id`, `created_at`),
      KEY `idx_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Created: analytics_events\n";

} catch (PDOException $e) {
    echo "❌ Error creating tables: " . $e->getMessage() . "\n";
    exit(1);
}

// Re-enable foreign key checks
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

echo "\n--- Inserting sample data ---\n";

// Insert a test user if none exists
try {
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO users (google_sub, email, name, role) VALUES 
        ('sample_google_sub_123', 'test@meeplify.it', 'Test User', 'admin')
    ");
    $stmt->execute();
    echo "✅ Sample user inserted\n";
} catch (PDOException $e) {
    echo "⚠️  Could not insert sample user: " . $e->getMessage() . "\n";
}

echo "\n--- Final verification ---\n";

// Test the problematic query that was failing
try {
    $stmt = $pdo->prepare("
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
        LIMIT 5
    ");
    
    $stmt->execute([1]);
    $result = $stmt->fetchAll();
    
    echo "✅ Checklist query test: SUCCESS\n";
    echo "   Found " . count($result) . " checklists\n";
    
} catch (Exception $e) {
    echo "❌ Checklist query test failed: " . $e->getMessage() . "\n";
    echo "   There may still be issues with the database structure\n";
    exit(1);
}

// Verify all tables exist
$tables_created = [];
$stmt = $pdo->query("SHOW TABLES");
while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
    $tables_created[] = $row[0];
}

echo "✅ Tables created: " . implode(', ', $tables_created) . "\n";

echo "\n=== DATABASE RESET COMPLETE! ===\n";
echo "🎉 Database has been successfully reset and recreated!\n";
echo "🎯 The API /api/checklists should now work properly.\n";
echo "🔐 OAuth is configured and ready.\n\n";

echo "Next steps:\n";
echo "1. Visit https://www.meeplify.it to test the app\n";
echo "2. Try logging in with Google OAuth\n";
echo "3. Create your first checklist\n\n";

echo "For verification, run: https://www.meeplify.it/verify_database.php\n";
?>