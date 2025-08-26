<?php
session_start();
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load dependencies
require_once __DIR__ . '/app/lib/Config.php';
require_once __DIR__ . '/app/lib/DB.php';
require_once __DIR__ . '/app/lib/Utils.php';

echo "Testing direct API access...\n\n";

try {
    // Test 1: Database connection
    echo "1. Testing database...\n";
    $pdo = DB::getPDO();
    $stmt = $pdo->query("SELECT COUNT(*) as user_count FROM users");
    $result = $stmt->fetch();
    echo "   Users in database: " . $result['user_count'] . "\n\n";
    
    // Test 2: Simulate session (you might need to adjust user_id)
    echo "2. Simulating user session...\n";
    $stmt = $pdo->query("SELECT id, email, name FROM users ORDER BY id DESC LIMIT 1");
    $user = $stmt->fetch();
    
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['authenticated'] = true;
        $_SESSION['email'] = $user['email'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['role'] = 'user';
        
        echo "   Simulated user: {$user['name']} ({$user['email']}) ID: {$user['id']}\n\n";
        
        // Test 3: Call checkAuth
        echo "3. Testing checkAuth function...\n";
        $auth_user_id = checkAuth();
        echo "   checkAuth returned: $auth_user_id\n\n";
        
    } else {
        echo "   No users found in database. Creating test user...\n";
        $stmt = $pdo->prepare("INSERT INTO users (google_sub, email, name, role) VALUES (?, ?, ?, 'user')");
        $stmt->execute(['test123', 'test@example.com', 'Test User']);
        $user_id = $pdo->lastInsertId();
        
        $_SESSION['user_id'] = $user_id;
        $_SESSION['authenticated'] = true;
        $_SESSION['email'] = 'test@example.com';
        $_SESSION['name'] = 'Test User';
        $_SESSION['role'] = 'user';
        
        echo "   Created test user with ID: $user_id\n\n";
    }
    
    // Test 4: Direct checklist query
    echo "4. Testing checklist query directly...\n";
    $user_id = $_SESSION['user_id'];
    
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
    
    echo "   Query executed successfully\n";
    echo "   Found " . count($checklists) . " checklists\n\n";
    
    if (count($checklists) === 0) {
        echo "5. Creating test checklist...\n";
        $stmt = $pdo->prepare("INSERT INTO checklists (title, description, owner_id) VALUES (?, ?, ?)");
        $stmt->execute(['Test Checklist', 'This is a test checklist', $user_id]);
        $checklist_id = $pdo->lastInsertId();
        echo "   Created test checklist with ID: $checklist_id\n\n";
        
        // Re-run query
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
        echo "   After creation: Found " . count($checklists) . " checklists\n\n";
    }
    
    // Test 5: Simulate API response
    echo "6. Simulating API response format...\n";
    
    // Calculate progress for each checklist
    foreach ($checklists as &$checklist) {
        $total_items = (int)$checklist['item_count'];
        $completed_items = (int)$checklist['completed_count'];
        $checklist['progress'] = $total_items > 0 ? round(($completed_items / $total_items) * 100) : 0;
    }

    // Get total count for pagination
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM checklists c 
        WHERE c.owner_id = ? AND c.deleted_at IS NULL
    ");
    $countStmt->execute([$user_id]);
    $total = $countStmt->fetchColumn();

    $response = [
        'success' => true,
        'data' => [
            'checklists' => $checklists,
            'pagination' => [
                'page' => 1,
                'limit' => 20,
                'total' => (int)$total,
                'pages' => ceil($total / 20)
            ]
        ],
        'errors' => []
    ];
    
    echo "   Response generated successfully\n";
    echo "   JSON: " . json_encode($response, JSON_PRETTY_PRINT) . "\n\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}

echo "Test completed!\n";
?>