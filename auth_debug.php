<?php
session_start();

// Set content type to JSON for AJAX calls
if (isset($_GET['json'])) {
    header('Content-Type: application/json');
}

$debug_info = [
    'session_id' => session_id(),
    'session_status' => session_status(),
    'session_data' => $_SESSION,
    'server_vars' => [
        'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? null,
        'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'] ?? null,
        'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? null,
        'HTTPS' => $_SERVER['HTTPS'] ?? null,
    ],
    'auth_check' => [
        'has_user_id' => isset($_SESSION['user_id']),
        'has_authenticated_flag' => isset($_SESSION['authenticated']),
        'authenticated_value' => $_SESSION['authenticated'] ?? null,
        'user_data' => [
            'id' => $_SESSION['user_id'] ?? null,
            'email' => $_SESSION['email'] ?? null,
            'name' => $_SESSION['name'] ?? null,
            'role' => $_SESSION['role'] ?? null,
        ]
    ],
    'google_oauth_config' => []
];

// Load config safely
try {
    require_once __DIR__ . '/app/lib/Config.php';
    $debug_info['google_oauth_config'] = [
        'client_id_set' => !empty(GOOGLE_CLIENT_ID),
        'client_secret_set' => !empty(GOOGLE_CLIENT_SECRET),
        'redirect_uri' => GOOGLE_REDIRECT_URI ?? null,
    ];
} catch (Exception $e) {
    $debug_info['config_error'] = $e->getMessage();
}

// Test database if user is authenticated
if (isset($_SESSION['user_id'])) {
    try {
        require_once __DIR__ . '/app/lib/DB.php';
        $pdo = DB::getPDO();
        
        // Test the exact query that's failing
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
        $checklists = $stmt->fetchAll();
        
        $debug_info['database_test'] = [
            'connection' => 'success',
            'query_executed' => true,
            'checklists_found' => count($checklists),
            'checklists' => $checklists
        ];
        
    } catch (Exception $e) {
        $debug_info['database_test'] = [
            'connection' => 'failed',
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ];
    }
}

if (isset($_GET['json'])) {
    echo json_encode($debug_info, JSON_PRETTY_PRINT);
} else {
    echo "<h1>Authentication Debug Info</h1>";
    echo "<pre>" . json_encode($debug_info, JSON_PRETTY_PRINT) . "</pre>";
    echo "<p><a href='?json=1'>View as JSON</a></p>";
}
?>