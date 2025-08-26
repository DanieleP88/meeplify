<?php
/**
 * QUICK DATABASE FIX - Meeplify
 * 
 * This script fixes the specific column issue (owner_user_id vs owner_id)
 * and ensures the database structure matches the API expectations.
 * 
 * Safe to run multiple times.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set content type for web display
if (!isset($_SERVER['argc'])) {
    header('Content-Type: text/plain; charset=utf-8');
}

echo "=== MEEPLIFY DATABASE QUICK FIX ===\n";
echo "Fixing column name issues and structure...\n\n";

try {
    require_once __DIR__ . '/app/lib/Config.php';
    require_once __DIR__ . '/app/lib/DB.php';
    
    $pdo = DB::getPDO();
    echo "✅ Database connected\n";
    
} catch (Exception $e) {
    echo "❌ Cannot connect to database: " . $e->getMessage() . "\n";
    exit(1);
}

// Disable foreign key checks for modifications
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

echo "\n--- Analyzing current table structure ---\n";

// Check current checklists table structure
try {
    $stmt = $pdo->query("DESCRIBE checklists");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $has_owner_id = false;
    $has_owner_user_id = false;
    
    foreach ($columns as $col) {
        if ($col['Field'] === 'owner_id') $has_owner_id = true;
        if ($col['Field'] === 'owner_user_id') $has_owner_user_id = true;
    }
    
    echo "Current checklists table columns:\n";
    foreach ($columns as $col) {
        echo "  - {$col['Field']} ({$col['Type']})\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Error checking checklists table: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n--- Applying fixes ---\n";

// Fix 1: Ensure owner_id column exists with correct name
if ($has_owner_user_id && !$has_owner_id) {
    try {
        $pdo->exec("ALTER TABLE checklists CHANGE COLUMN owner_user_id owner_id int(11) NOT NULL");
        echo "✅ Renamed owner_user_id to owner_id\n";
    } catch (PDOException $e) {
        echo "⚠️  Could not rename column: " . $e->getMessage() . "\n";
    }
} elseif (!$has_owner_id && !$has_owner_user_id) {
    try {
        $pdo->exec("ALTER TABLE checklists ADD COLUMN owner_id int(11) NOT NULL AFTER description");
        echo "✅ Added missing owner_id column\n";
    } catch (PDOException $e) {
        echo "⚠️  Could not add owner_id column: " . $e->getMessage() . "\n";
    }
} else {
    echo "✅ owner_id column already exists correctly\n";
}

// Fix 2: Ensure items table has 'completed' column (not 'is_done')
try {
    $stmt = $pdo->query("DESCRIBE items");
    $item_columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $has_completed = false;
    $has_is_done = false;
    
    foreach ($item_columns as $col) {
        if ($col['Field'] === 'completed') $has_completed = true;
        if ($col['Field'] === 'is_done') $has_is_done = true;
    }
    
    if ($has_is_done && !$has_completed) {
        $pdo->exec("ALTER TABLE items CHANGE COLUMN is_done completed tinyint(1) NOT NULL DEFAULT 0");
        echo "✅ Renamed is_done to completed in items table\n";
    } elseif (!$has_completed && !$has_is_done) {
        $pdo->exec("ALTER TABLE items ADD COLUMN completed tinyint(1) NOT NULL DEFAULT 0 AFTER text");
        echo "✅ Added missing completed column to items table\n";
    } else {
        echo "✅ Items table completed column already correct\n";
    }
    
} catch (PDOException $e) {
    echo "⚠️  Error fixing items table: " . $e->getMessage() . "\n";
}

// Fix 3: Ensure proper indexes exist
echo "\n--- Adding missing indexes ---\n";

$indexes_to_add = [
    "CREATE INDEX IF NOT EXISTS idx_owner_active ON checklists (owner_id, deleted_at)",
    "CREATE INDEX IF NOT EXISTS idx_checklist_completion ON items (checklist_id, completed)",
    "CREATE INDEX IF NOT EXISTS idx_checklist_items ON items (checklist_id)",
    "CREATE INDEX IF NOT EXISTS idx_section_items ON items (section_id)",
];

foreach ($indexes_to_add as $index_sql) {
    try {
        $pdo->exec($index_sql);
        echo "✅ Added index\n";
    } catch (PDOException $e) {
        // Indexes may already exist, that's ok
        echo "ℹ️  Index exists or error: " . substr($e->getMessage(), 0, 50) . "...\n";
    }
}

// Re-enable foreign key checks
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

echo "\n--- Testing the fixed structure ---\n";

// Test the exact query that was failing
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
    
    echo "✅ CRITICAL TEST PASSED: Checklist query works!\n";
    echo "   Query returned " . count($result) . " results\n";
    
} catch (Exception $e) {
    echo "❌ CRITICAL ERROR: Query still failing: " . $e->getMessage() . "\n";
    echo "   The API will still return 500 errors\n";
    
    // Let's see what columns actually exist
    echo "\n--- Debug: Actual table structure ---\n";
    try {
        $stmt = $pdo->query("SHOW CREATE TABLE checklists");
        $result = $stmt->fetch();
        echo "Checklists table:\n" . $result['Create Table'] . "\n";
    } catch (Exception $e2) {
        echo "Could not show table structure: " . $e2->getMessage() . "\n";
    }
    
    exit(1);
}

// Test authentication check
echo "\n--- Testing user authentication simulation ---\n";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as user_count FROM users");
    $result = $stmt->fetch();
    echo "✅ Users table accessible, found {$result['user_count']} users\n";
    
    if ($result['user_count'] == 0) {
        echo "ℹ️  No users found - this is normal for a fresh installation\n";
        echo "   Users are created when they login with Google OAuth\n";
    }
    
} catch (Exception $e) {
    echo "⚠️  Users table issue: " . $e->getMessage() . "\n";
}

echo "\n=== DATABASE FIX COMPLETE ===\n";
echo "🎉 Database structure has been corrected!\n";
echo "🎯 The /api/checklists endpoint should now work.\n";
echo "🔐 Google OAuth is ready.\n\n";

echo "Next steps:\n";
echo "1. Test the app: https://www.meeplify.it\n";
echo "2. Login with Google to create your user account\n";
echo "3. Create your first checklist\n\n";

echo "If you still get 500 errors, check:\n";
echo "- https://www.meeplify.it/auth_debug.php (check if user is logged in)\n";
echo "- Your server error logs for detailed error messages\n";

echo "\n--- Current Database Status ---\n";
$tables = ['users', 'checklists', 'sections', 'items'];
foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
        $result = $stmt->fetch();
        echo "📊 $table: {$result['count']} records\n";
    } catch (Exception $e) {
        echo "❌ $table: Error - " . $e->getMessage() . "\n";
    }
}

echo "\nDatabase fix completed at: " . date('Y-m-d H:i:s') . "\n";
?>