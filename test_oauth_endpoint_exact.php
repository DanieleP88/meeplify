<?php
echo "=== EXACT OAUTH ENDPOINT TEST ===\n\n";

// Enable all error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "1. Starting session (like endpoint does):\n";
try {
    session_start();
    echo "✅ Session started\n";
} catch (Exception $e) {
    echo "❌ Session failed: " . $e->getMessage() . "\n";
}

echo "\n2. Loading dependencies in exact order:\n";

echo "Loading Config.php...\n";
try {
    require_once __DIR__ . '/app/lib/Config.php';
    echo "✅ Config.php loaded\n";
} catch (Exception $e) {
    echo "❌ Config.php failed: " . $e->getMessage() . "\n";
    exit;
} catch (Error $e) {
    echo "❌ Config.php fatal: " . $e->getMessage() . "\n";
    exit;
}

echo "Loading DB.php...\n";
try {
    require_once __DIR__ . '/app/lib/DB.php';
    echo "✅ DB.php loaded\n";
} catch (Exception $e) {
    echo "❌ DB.php failed: " . $e->getMessage() . "\n";
} catch (Error $e) {
    echo "❌ DB.php fatal: " . $e->getMessage() . "\n";
}

echo "Loading Utils.php...\n";
try {
    require_once __DIR__ . '/app/lib/Utils.php';
    echo "✅ Utils.php loaded\n";
} catch (Exception $e) {
    echo "❌ Utils.php failed: " . $e->getMessage() . "\n";
} catch (Error $e) {
    echo "❌ Utils.php fatal: " . $e->getMessage() . "\n";
}

echo "\n3. Checking constants after all dependencies loaded:\n";
echo "GOOGLE_CLIENT_ID: " . (defined('GOOGLE_CLIENT_ID') ? (GOOGLE_CLIENT_ID ? "SET (len: " . strlen(GOOGLE_CLIENT_ID) . ")" : "EMPTY") : "NOT DEFINED") . "\n";
echo "GOOGLE_CLIENT_SECRET: " . (defined('GOOGLE_CLIENT_SECRET') ? (GOOGLE_CLIENT_SECRET ? "SET" : "EMPTY") : "NOT DEFINED") . "\n";
echo "GOOGLE_REDIRECT_URI: " . (defined('GOOGLE_REDIRECT_URI') ? GOOGLE_REDIRECT_URI : "NOT DEFINED") . "\n";

echo "\n4. Loading API handlers (like endpoint does):\n";

$handlers = [
    'ChecklistHandler.php',
    'SectionHandler.php', 
    'ItemHandler.php',
    'CollaborationHandler.php',
    'TagHandler.php',
    'TemplateHandler.php',
    'AdminHandler.php'
];

foreach ($handlers as $handler) {
    echo "Loading $handler...\n";
    try {
        $path = __DIR__ . '/app/api/handlers/' . $handler;
        if (file_exists($path)) {
            require_once $path;
            echo "✅ $handler loaded\n";
        } else {
            echo "⚠️ $handler not found (skipping)\n";
        }
    } catch (Exception $e) {
        echo "❌ $handler failed: " . $e->getMessage() . "\n";
    } catch (Error $e) {
        echo "❌ $handler fatal: " . $e->getMessage() . "\n";
    }
}

echo "\n5. Checking constants after handlers loaded:\n";
echo "GOOGLE_CLIENT_ID: " . (defined('GOOGLE_CLIENT_ID') ? (GOOGLE_CLIENT_ID ? "SET (len: " . strlen(GOOGLE_CLIENT_ID) . ")" : "EMPTY") : "NOT DEFINED") . "\n";
echo "GOOGLE_CLIENT_SECRET: " . (defined('GOOGLE_CLIENT_SECRET') ? (GOOGLE_CLIENT_SECRET ? "SET" : "EMPTY") : "NOT DEFINED") . "\n";
echo "GOOGLE_REDIRECT_URI: " . (defined('GOOGLE_REDIRECT_URI') ? GOOGLE_REDIRECT_URI : "NOT DEFINED") . "\n";

echo "\n6. Setting headers (like endpoint does):\n";
try {
    // Simulate the headers from index.php
    header('Content-Type: application/json');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    echo "✅ Headers set\n";
} catch (Exception $e) {
    echo "❌ Headers failed: " . $e->getMessage() . "\n";
}

echo "\n7. Testing OAuth logic (exact same as endpoint):\n";
echo "empty(GOOGLE_CLIENT_ID): " . (empty(GOOGLE_CLIENT_ID) ? 'TRUE' : 'FALSE') . "\n";
echo "empty(GOOGLE_CLIENT_SECRET): " . (empty(GOOGLE_CLIENT_SECRET) ? 'TRUE' : 'FALSE') . "\n";
echo "Condition result: " . ((empty(GOOGLE_CLIENT_ID) || empty(GOOGLE_CLIENT_SECRET)) ? 'WOULD FAIL' : 'WOULD SUCCEED') . "\n";

if (empty(GOOGLE_CLIENT_ID) || empty(GOOGLE_CLIENT_SECRET)) {
    echo "❌ OAuth check would fail - this is the problem!\n";
    echo "  GOOGLE_CLIENT_ID value: '" . (defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : 'NOT DEFINED') . "'\n";
    echo "  GOOGLE_CLIENT_SECRET value: '" . (defined('GOOGLE_CLIENT_SECRET') ? (GOOGLE_CLIENT_SECRET ? '[SET]' : '[EMPTY]') : 'NOT DEFINED') . "'\n";
} else {
    echo "✅ OAuth check would succeed\n";
    
    // Test the actual OAuth URL generation
    echo "\n8. Testing OAuth URL generation:\n";
    try {
        $state = bin2hex(random_bytes(16));
        $_SESSION['oauth_state'] = $state;
        
        $params = [
            'client_id' => GOOGLE_CLIENT_ID,
            'redirect_uri' => GOOGLE_REDIRECT_URI,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
        ];
        
        $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
        echo "✅ OAuth URL generated successfully\n";
        echo "URL length: " . strlen($authUrl) . " chars\n";
        
        // Test sendJson function
        echo "\n9. Testing sendJson response:\n";
        if (function_exists('sendJson')) {
            echo "✅ sendJson function available\n";
            // Don't actually call sendJson as it would exit, just confirm it exists
        } else {
            echo "❌ sendJson function not available\n";
        }
        
    } catch (Exception $e) {
        echo "❌ OAuth URL generation failed: " . $e->getMessage() . "\n";
    }
}

echo "\n=== TEST COMPLETED ===\n";
?>