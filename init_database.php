<?php
require_once 'app/lib/Config.php';
require_once 'app/lib/DB.php';

try {
    $pdo = DB::getPDO();
    
    // Read and execute the schema file
    $schema = file_get_contents('database_schema_optimized.sql');
    
    // Split by statements (rough split by semicolon followed by newline)
    $statements = preg_split('/;\s*\n/', $schema);
    
    echo "🚀 Initializing database schema...\n\n";
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue;
        }
        
        try {
            $pdo->exec($statement);
            
            // Extract table name for progress feedback
            if (preg_match('/CREATE TABLE.*?`([^`]+)`/i', $statement, $matches)) {
                echo "✅ Created table: {$matches[1]}\n";
            } elseif (preg_match('/INSERT INTO.*?`([^`]+)`/i', $statement, $matches)) {
                echo "✅ Inserted data into: {$matches[1]}\n";
            }
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'already exists') !== false) {
                // Table already exists, skip
                if (preg_match('/CREATE TABLE.*?`([^`]+)`/i', $statement, $matches)) {
                    echo "⚠️  Table already exists: {$matches[1]}\n";
                }
            } else {
                echo "❌ Error: " . $e->getMessage() . "\n";
                echo "Statement: " . substr($statement, 0, 100) . "...\n\n";
            }
        }
    }
    
    echo "\n🎉 Database initialization completed!\n";
    echo "📊 Database: " . (DB_DSN) . "\n";
    
} catch (Exception $e) {
    echo "❌ Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}
?>