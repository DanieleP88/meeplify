<?php
// Test Utils.php dopo aver rimosso checkCsrf duplicata
session_start();
header('Content-Type: text/html; charset=utf-8');

echo "<h2>✅ Test Utils.php - Post Duplicate Fix</h2>";

try {
    echo "1. Loading Config...<br>";
    require_once __DIR__ . '/app/lib/Config.php';
    echo "✅ Config loaded<br>";
    
    echo "2. Loading DB...<br>";
    require_once __DIR__ . '/app/lib/DB.php';
    echo "✅ DB loaded<br>";
    
    echo "3. Loading Utils (checkCsrf duplicate removed)...<br>";
    require_once __DIR__ . '/app/lib/Utils.php';
    echo "🎉 <strong>Utils loaded successfully!</strong><br>";
    
    echo "<h3>📋 Function Check</h3>";
    $functions = ['sendJson', 'logMessage', 'checkAuth', 'checkCsrf', 'getInput'];
    foreach ($functions as $func) {
        if (function_exists($func)) {
            echo "✅ $func exists<br>";
        } else {
            echo "❌ $func missing<br>";
        }
    }
    
    echo "<h3>🌐 Test API Endpoint Logic</h3>";
    
    // Test OAuth configuration
    if (!empty(GOOGLE_CLIENT_ID) && !empty(GOOGLE_CLIENT_SECRET)) {
        echo "✅ Google OAuth configured<br>";
        
        // Generate OAuth URL (same as API)
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
        
        echo "✅ OAuth URL generated<br>";
        
        // Test JSON response (like sendJson but without exit)
        $response = [
            'success' => true,
            'data' => ['url' => $authUrl],
            'errors' => []
        ];
        
        $json = json_encode($response);
        if ($json !== false) {
            echo "✅ JSON encoding works<br>";
            echo "Sample JSON: <code>" . htmlspecialchars(substr($json, 0, 100)) . "...</code><br>";
        } else {
            echo "❌ JSON encoding failed<br>";
        }
        
    } else {
        echo "❌ Google OAuth not configured<br>";
    }
    
    echo "<h3>🔗 Ready to Test</h3>";
    echo "<a href='/api/auth/google' target='_blank' style='background: #2563eb; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🚀 Test /api/auth/google</a><br><br>";
    echo "<small>This should now return proper JSON instead of HTTP 500</small>";
    
} catch (Error $e) {
    echo "❌ Fatal Error: " . $e->getMessage() . " in " . $e->getFile() . " line " . $e->getLine() . "<br>";
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "<br>";
}
?>