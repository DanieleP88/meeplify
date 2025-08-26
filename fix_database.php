<?php
require_once 'app/lib/Config.php';
require_once 'app/lib/DB.php';

try {
    $pdo = DB::getPDO();
    
    echo "🔧 Fixing database schema...\n\n";
    
    // Check current structure of items table
    echo "1. Current items table structure:\n";
    $stmt = $pdo->query("DESCRIBE items");
    $columns = $stmt->fetchAll();
    
    $hasCompleted = false;
    $hasUpdatedAt = false;
    
    foreach ($columns as $column) {
        echo "   - {$column['Field']} ({$column['Type']})\n";
        if ($column['Field'] === 'completed') $hasCompleted = true;
        if ($column['Field'] === 'updated_at') $hasUpdatedAt = true;
    }
    
    echo "\n2. Adding missing columns...\n";
    
    // Add completed column if missing
    if (!$hasCompleted) {
        try {
            $pdo->exec("ALTER TABLE `items` ADD COLUMN `completed` tinyint(1) NOT NULL DEFAULT 0");
            echo "   ✅ Added 'completed' column\n";
        } catch (PDOException $e) {
            echo "   ⚠️ Could not add 'completed' column: " . $e->getMessage() . "\n";
        }
    } else {
        echo "   ✅ 'completed' column already exists\n";
    }
    
    // Add updated_at column if missing
    if (!$hasUpdatedAt) {
        try {
            $pdo->exec("ALTER TABLE `items` ADD COLUMN `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
            echo "   ✅ Added 'updated_at' column\n";
        } catch (PDOException $e) {
            echo "   ⚠️ Could not add 'updated_at' column: " . $e->getMessage() . "\n";
        }
    } else {
        echo "   ✅ 'updated_at' column already exists\n";
    }
    
    // Add index for performance
    try {
        $pdo->exec("ALTER TABLE `items` ADD INDEX `idx_checklist_completed` (`checklist_id`, `completed`)");
        echo "   ✅ Added performance index\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "   ✅ Performance index already exists\n";
        } else {
            echo "   ⚠️ Could not add index: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n3. Final items table structure:\n";
    $stmt = $pdo->query("DESCRIBE items");
    $columns = $stmt->fetchAll();
    
    foreach ($columns as $column) {
        echo "   - {$column['Field']} ({$column['Type']}) {$column['Extra']}\n";
    }
    
    echo "\n🎉 Database schema fix completed!\n";
    
    // Test the problematic query
    echo "\n4. Testing the checklist query...\n";
    
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
        LIMIT 1
    ");
    
    $stmt->execute([1]); // Test with user_id 1
    $result = $stmt->fetchAll();
    
    echo "   ✅ Query executed successfully!\n";
    echo "   📊 Found " . count($result) . " checklist(s)\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "📍 File: " . $e->getFile() . "\n";
    echo "📍 Line: " . $e->getLine() . "\n";
}
?>