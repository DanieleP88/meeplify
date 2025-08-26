<?php
require_once 'app/lib/Config.php';
require_once 'app/lib/DB.php';

try {
    $pdo = DB::getPDO();
    
    echo "🔍 Checking actual database schema...\n\n";
    
    // Check all tables
    $tables = ['users', 'checklists', 'sections', 'items', 'collaborators'];
    
    foreach ($tables as $table) {
        echo "📋 Table: $table\n";
        echo str_repeat('-', 50) . "\n";
        
        try {
            $stmt = $pdo->query("DESCRIBE `$table`");
            $columns = $stmt->fetchAll();
            
            foreach ($columns as $column) {
                $null = $column['Null'] === 'YES' ? 'NULL' : 'NOT NULL';
                $default = $column['Default'] ? "DEFAULT '{$column['Default']}'" : '';
                $extra = $column['Extra'] ? $column['Extra'] : '';
                
                echo sprintf("   %-20s %-20s %-10s %-15s %s\n", 
                    $column['Field'], 
                    $column['Type'], 
                    $null,
                    $default,
                    $extra
                );
            }
        } catch (PDOException $e) {
            echo "   ❌ Table not found: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }
    
    // Test specific problematic columns
    echo "🔍 Testing specific queries...\n\n";
    
    // Test checklists table structure
    echo "1. Checklists table - looking for owner/user columns:\n";
    try {
        $stmt = $pdo->query("DESCRIBE checklists");
        $columns = $stmt->fetchAll();
        $userColumns = [];
        
        foreach ($columns as $column) {
            if (strpos($column['Field'], 'user') !== false || strpos($column['Field'], 'owner') !== false) {
                $userColumns[] = $column['Field'];
            }
        }
        
        if (!empty($userColumns)) {
            echo "   Found user/owner columns: " . implode(', ', $userColumns) . "\n";
        } else {
            echo "   ❌ No user/owner columns found!\n";
        }
        
    } catch (PDOException $e) {
        echo "   ❌ Error: " . $e->getMessage() . "\n";
    }
    
    // Test items table - completion columns
    echo "\n2. Items table - completion status columns:\n";
    try {
        $stmt = $pdo->query("DESCRIBE items");
        $columns = $stmt->fetchAll();
        $completionColumns = [];
        
        foreach ($columns as $column) {
            if (strpos($column['Field'], 'complete') !== false || 
                strpos($column['Field'], 'done') !== false || 
                strpos($column['Field'], 'finish') !== false) {
                $completionColumns[] = $column['Field'];
            }
        }
        
        if (!empty($completionColumns)) {
            echo "   Found completion columns: " . implode(', ', $completionColumns) . "\n";
        } else {
            echo "   ❌ No completion columns found!\n";
        }
        
    } catch (PDOException $e) {
        echo "   ❌ Error: " . $e->getMessage() . "\n";
    }
    
    // Test a simple SELECT to see what works
    echo "\n3. Testing simple queries:\n";
    
    // Test users table
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users LIMIT 1");
        $result = $stmt->fetch();
        echo "   ✅ Users table: {$result['count']} records\n";
    } catch (PDOException $e) {
        echo "   ❌ Users table error: " . $e->getMessage() . "\n";
    }
    
    // Test checklists table with different possible column names
    $possibleUserColumns = ['owner_id', 'user_id', 'creator_id', 'author_id'];
    foreach ($possibleUserColumns as $col) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM checklists WHERE $col IS NOT NULL LIMIT 1");
            $result = $stmt->fetch();
            echo "   ✅ Checklists table with '$col': works\n";
            break;
        } catch (PDOException $e) {
            echo "   ❌ Checklists table with '$col': column not found\n";
        }
    }
    
    echo "\n🎯 Schema check completed!\n";
    
} catch (Exception $e) {
    echo "❌ Fatal Error: " . $e->getMessage() . "\n";
    echo "📍 File: " . $e->getFile() . "\n";
    echo "📍 Line: " . $e->getLine() . "\n";
}
?>