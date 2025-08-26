<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Load dependencies
try {
    require_once __DIR__ . '/app/lib/Config.php';
    echo "✅ Config loaded\n";
} catch (Exception $e) {
    echo "❌ Config error: " . $e->getMessage() . "\n";
    exit;
}

try {
    require_once __DIR__ . '/app/lib/DB.php';
    echo "✅ DB class loaded\n";
} catch (Exception $e) {
    echo "❌ DB class error: " . $e->getMessage() . "\n";
    exit;
}

try {
    require_once __DIR__ . '/app/lib/Utils.php';
    echo "✅ Utils loaded\n";
} catch (Exception $e) {
    echo "❌ Utils error: " . $e->getMessage() . "\n";
    exit;
}

// Test database connection
try {
    $pdo = DB::getPDO();
    echo "✅ Database connected successfully\n";
    
    // Test if tables exist
    $tables = ['users', 'checklists', 'sections', 'items'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
        $count = $stmt->fetchColumn();
        echo "✅ Table '$table' exists with $count records\n";
    }
    
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
    echo "Check your .env configuration:\n";
    echo "DB_HOST: " . (defined('DB_DSN') ? 'SET' : 'NOT SET') . "\n";
    echo "DB_USER: " . (defined('DB_USER') ? DB_USER : 'NOT SET') . "\n";
    exit;
}

// Test session
echo "\n--- Session Status ---\n";
echo "Session ID: " . session_id() . "\n";
echo "Authenticated: " . (isset($_SESSION['authenticated']) ? ($_SESSION['authenticated'] ? 'YES' : 'NO') : 'NOT SET') . "\n";
echo "User ID: " . ($_SESSION['user_id'] ?? 'NOT SET') . "\n";
echo "User Name: " . ($_SESSION['name'] ?? 'NOT SET') . "\n";

// Test checkAuth function
echo "\n--- Testing checkAuth ---\n";
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    echo "✅ checkAuth would succeed with user_id: $user_id\n";
} else {
    echo "❌ checkAuth would fail - no user_id in session\n";
    echo "This is why the API returns 401 Unauthorized\n";
}

// Test a simple query
if (isset($_SESSION['user_id'])) {
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
            LIMIT 20 OFFSET 0
        ");
        
        $stmt->execute([$_SESSION['user_id']]);
        $result = $stmt->fetchAll();
        
        echo "✅ Query executed successfully. Found " . count($result) . " checklists\n";
        
        if (count($result) > 0) {
            foreach ($result as $row) {
                echo "- {$row['title']} (ID: {$row['id']}, Items: {$row['item_count']}, Completed: {$row['completed_count']})\n";
            }
        }
        
    } catch (Exception $e) {
        echo "❌ Query error: " . $e->getMessage() . "\n";
    }
}

echo "\n=== DEBUG COMPLETE ===\n";
?>