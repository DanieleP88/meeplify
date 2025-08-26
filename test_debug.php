<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load dependencies
require_once __DIR__ . '/app/lib/Config.php';
require_once __DIR__ . '/app/lib/DB.php';
require_once __DIR__ . '/app/lib/Utils.php';

echo "🔍 Debug API Endpoint\n\n";

// Test database connection
echo "1. Testing database connection...\n";
try {
    $pdo = DB::getPDO();
    echo "✅ Database connection: OK\n";
    
    // Test database name
    $stmt = $pdo->query("SELECT DATABASE() as db_name");
    $result = $stmt->fetch();
    echo "📊 Connected to database: " . $result['db_name'] . "\n";
    
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Test table existence
echo "\n2. Testing table structure...\n";
$requiredTables = ['users', 'checklists', 'sections', 'items', 'collaborators'];
foreach ($requiredTables as $table) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
        $count = $stmt->fetchColumn();
        echo "✅ Table '$table': $count rows\n";
    } catch (Exception $e) {
        echo "❌ Table '$table': " . $e->getMessage() . "\n";
    }
}

// Test authentication simulation
echo "\n3. Testing authentication simulation...\n";
$_SESSION['user_id'] = 1;
$_SESSION['authenticated'] = true;
echo "📝 Simulated user_id: 1\n";

// Test checkAuth function
echo "\n4. Testing checkAuth function...\n";
try {
    $user_id = checkAuth();
    echo "✅ checkAuth returned: $user_id\n";
} catch (Exception $e) {
    echo "❌ checkAuth failed: " . $e->getMessage() . "\n";
}

// Test sanitizeString function
echo "\n5. Testing sanitizeString function...\n";
$testString = "  Test <script>alert('xss')</script>  ";
$sanitized = sanitizeString($testString, 100);
echo "✅ sanitizeString: '$testString' -> '$sanitized'\n";

// Test the actual checklist query
echo "\n6. Testing checklist query...\n";
try {
    $user_id = 1;
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
        LIMIT 20
    ");
    
    $stmt->execute([$user_id]);
    $checklists = $stmt->fetchAll();
    
    echo "✅ Query executed successfully\n";
    echo "📊 Found " . count($checklists) . " checklists\n";
    
    if (count($checklists) > 0) {
        echo "📝 Sample checklist: " . print_r($checklists[0], true) . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Query failed: " . $e->getMessage() . "\n";
}

echo "\n🎯 Debug completed!\n";
?>