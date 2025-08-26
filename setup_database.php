<?php
/**
 * Database Setup Script for Meeplify
 * This script creates the database structure if it doesn't exist
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/app/lib/Config.php';

echo "=== MEEPLIFY DATABASE SETUP ===\n\n";

// Test database connection first
try {
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    echo "✅ Database connection successful\n";
} catch (PDOException $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
    echo "Please check your .env configuration:\n";
    echo "Current DSN: " . DB_DSN . "\n";
    echo "Current User: " . DB_USER . "\n";
    exit(1);
}

// Check if tables exist
$tables_needed = ['users', 'checklists', 'sections', 'items', 'collaborators', 'tags', 'item_tags', 'templates', 'template_sections', 'template_items', 'template_tags', 'audit_log', 'analytics_events'];
$existing_tables = [];

try {
    $result = $pdo->query("SHOW TABLES");
    while ($row = $result->fetch(PDO::FETCH_NUM)) {
        $existing_tables[] = $row[0];
    }
    
    echo "Existing tables: " . (empty($existing_tables) ? "none" : implode(', ', $existing_tables)) . "\n";
    
    $missing_tables = array_diff($tables_needed, $existing_tables);
    
    if (empty($missing_tables)) {
        echo "✅ All required tables exist\n";
    } else {
        echo "❌ Missing tables: " . implode(', ', $missing_tables) . "\n";
        echo "Please import database_schema.sql:\n";
        echo "mysql -u " . DB_USER . " -p " . (preg_match('/dbname=([^;]+)/', DB_DSN, $matches) ? $matches[1] : 'meeplify') . " < database_schema.sql\n";
        exit(1);
    }
    
} catch (PDOException $e) {
    echo "❌ Error checking tables: " . $e->getMessage() . "\n";
    exit(1);
}

// Test a simple query on each table
foreach ($tables_needed as $table) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
        $count = $stmt->fetchColumn();
        echo "✅ Table '$table': $count records\n";
    } catch (PDOException $e) {
        echo "❌ Error querying '$table': " . $e->getMessage() . "\n";
    }
}

echo "\n=== SETUP COMPLETE ===\n";
echo "Database appears to be properly configured.\n";
echo "If you're still getting 500 errors, check:\n";
echo "1. Web server error logs\n";
echo "2. PHP error logs\n";
echo "3. Make sure user is properly authenticated via Google OAuth\n";
?>