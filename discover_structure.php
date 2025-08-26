<?php
require_once 'app/lib/Config.php';
require_once 'app/lib/DB.php';

try {
    $pdo = DB::getPDO();
    
    echo "🕵️ Discovering EXACT database structure...\n\n";
    
    // Check what tables exist
    echo "1. Available tables:\n";
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($tables as $table) {
        echo "   - $table\n";
    }
    
    echo "\n" . str_repeat("=", 60) . "\n";
    
    // Focus on checklists table structure
    if (in_array('checklists', $tables)) {
        echo "2. CHECKLISTS table structure:\n";
        echo str_repeat("-", 40) . "\n";
        
        $stmt = $pdo->query("DESCRIBE checklists");
        $columns = $stmt->fetchAll();
        
        foreach ($columns as $column) {
            echo sprintf("%-20s %-20s %-10s %-20s %s\n", 
                $column['Field'], 
                $column['Type'], 
                $column['Null'], 
                $column['Default'] ?: 'NULL',
                $column['Extra']
            );
        }
        
        // Show sample data if any exists
        echo "\n3. Sample data from checklists:\n";
        $stmt = $pdo->query("SELECT * FROM checklists LIMIT 3");
        $samples = $stmt->fetchAll();
        
        if (!empty($samples)) {
            // Show column names
            $columnNames = array_keys($samples[0]);
            echo "Columns: " . implode(', ', $columnNames) . "\n";
            
            foreach ($samples as $index => $sample) {
                echo "Row " . ($index + 1) . ": ";
                $values = [];
                foreach ($sample as $key => $value) {
                    if (!is_numeric($key)) { // Skip numeric indices from PDO
                        $values[] = "$key=" . ($value ?: 'NULL');
                    }
                }
                echo implode(', ', $values) . "\n";
            }
        } else {
            echo "No data found in checklists table.\n";
        }
    }
    
    echo "\n" . str_repeat("=", 60) . "\n";
    
    // Focus on items table structure  
    if (in_array('items', $tables)) {
        echo "4. ITEMS table structure:\n";
        echo str_repeat("-", 40) . "\n";
        
        $stmt = $pdo->query("DESCRIBE items");
        $columns = $stmt->fetchAll();
        
        foreach ($columns as $column) {
            echo sprintf("%-20s %-20s %-10s %-20s %s\n", 
                $column['Field'], 
                $column['Type'], 
                $column['Null'], 
                $column['Default'] ?: 'NULL',
                $column['Extra']
            );
        }
    }
    
    echo "\n" . str_repeat("=", 60) . "\n";
    
    // Check sections table
    if (in_array('sections', $tables)) {
        echo "5. SECTIONS table structure:\n";
        echo str_repeat("-", 40) . "\n";
        
        $stmt = $pdo->query("DESCRIBE sections");
        $columns = $stmt->fetchAll();
        
        foreach ($columns as $column) {
            echo sprintf("%-20s %-20s %-10s %-20s %s\n", 
                $column['Field'], 
                $column['Type'], 
                $column['Null'], 
                $column['Default'] ?: 'NULL',
                $column['Extra']
            );
        }
    }
    
    echo "\n🎯 Discovery complete!\n";
    echo "Now we know exactly what columns exist in each table.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>