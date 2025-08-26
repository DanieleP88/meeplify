<?php
/**
 * Quick Database Verification Script
 * Run this after importing the database schema to verify everything is working
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== MEEPLIFY DATABASE VERIFICATION ===\n\n";

// Load configuration
try {
    require_once __DIR__ . '/app/lib/Config.php';
    echo "✅ Configuration loaded successfully\n";
} catch (Exception $e) {
    echo "❌ Configuration error: " . $e->getMessage() . "\n";
    exit(1);
}

// Test database connection
try {
    require_once __DIR__ . '/app/lib/DB.php';
    $pdo = DB::getPDO();
    echo "✅ Database connection successful\n";
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
    echo "\nCheck your .env file configuration:\n";
    echo "Current settings:\n";
    echo "- DB_HOST: " . ($_ENV['DB_HOST'] ?? 'not set') . "\n";
    echo "- DB_NAME: " . ($_ENV['DB_NAME'] ?? 'not set') . "\n";
    echo "- DB_USER: " . ($_ENV['DB_USER'] ?? 'not set') . "\n";
    exit(1);
}

// Verify required tables
$required_tables = [
    'users', 'checklists', 'sections', 'items', 
    'collaborators', 'tags', 'item_tags', 'templates', 
    'template_sections', 'template_items', 'template_tags', 
    'audit_log', 'analytics_events'
];

$missing_tables = [];
$existing_tables = [];

foreach ($required_tables as $table) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
        $count = $stmt->fetchColumn();
        $existing_tables[] = $table;
        echo "✅ Table '$table': $count records\n";
    } catch (PDOException $e) {
        $missing_tables[] = $table;
        echo "❌ Table '$table': missing or inaccessible\n";
    }
}

if (!empty($missing_tables)) {
    echo "\n❌ Missing tables: " . implode(', ', $missing_tables) . "\n";
    echo "\nTo fix this, run one of these commands:\n";
    echo "1. Import the minimal schema (no foreign keys):\n";
    echo "   mysql -u your_user -p your_database < database_schema_minimal.sql\n\n";
    echo "2. Import the full schema (with foreign keys):\n";
    echo "   mysql -u your_user -p your_database < database_schema_fixed.sql\n\n";
    exit(1);
}

echo "\n✅ All required tables exist!\n";

// Test API functionality
echo "\n--- Testing API Components ---\n";

try {
    require_once __DIR__ . '/app/lib/Utils.php';
    echo "✅ Utils library loaded\n";
} catch (Exception $e) {
    echo "❌ Utils library error: " . $e->getMessage() . "\n";
}

// Test a simple query like the one that was failing
echo "\n--- Testing Checklist Query ---\n";
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
    
    // Test with a dummy user ID
    $stmt->execute([1]);
    $result = $stmt->fetchAll();
    
    echo "✅ Checklist query executed successfully\n";
    echo "   Found " . count($result) . " checklists for user ID 1\n";
    
    if (count($result) > 0) {
        foreach ($result as $row) {
            echo "   - {$row['title']} (Items: {$row['item_count']}, Completed: {$row['completed_count']})\n";
        }
    } else {
        echo "   (No checklists found - this is normal for a fresh installation)\n";
    }
    
} catch (Exception $e) {
    echo "❌ Checklist query failed: " . $e->getMessage() . "\n";
    echo "   This suggests there may still be database structure issues\n";
}

// Test Google OAuth configuration
echo "\n--- Google OAuth Configuration ---\n";
$oauth_configured = !empty(GOOGLE_CLIENT_ID) && !empty(GOOGLE_CLIENT_SECRET);
if ($oauth_configured) {
    echo "✅ Google OAuth configured\n";
    echo "   Client ID: " . substr(GOOGLE_CLIENT_ID, 0, 20) . "...\n";
    echo "   Redirect URI: " . GOOGLE_REDIRECT_URI . "\n";
} else {
    echo "❌ Google OAuth not configured\n";
    echo "   You need to set GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET in .env\n";
}

echo "\n=== VERIFICATION COMPLETE ===\n";

if (empty($missing_tables) && $oauth_configured) {
    echo "🎉 Database is properly configured!\n";
    echo "   Your Meeplify installation should work now.\n";
    echo "   Try accessing: https://www.meeplify.it\n";
} else {
    echo "⚠️  Some configuration is missing:\n";
    if (!empty($missing_tables)) {
        echo "   - Import database schema\n";
    }
    if (!$oauth_configured) {
        echo "   - Configure Google OAuth in .env\n";
    }
}

echo "\nFor debugging, you can also check:\n";
echo "- auth_debug.php - Check authentication status\n";
echo "- setup_database.php - Detailed database analysis\n";
?>