<?php
// Test finale dopo le correzioni
session_start();
header('Content-Type: text/html; charset=utf-8');

echo "<h2>🔧 Test Finale - Post Fix</h2>";

try {
    echo "1. Loading Config...<br>";
    require_once __DIR__ . '/app/lib/Config.php';
    echo "✅ Config loaded<br>";
    
    echo "2. Loading DB...<br>";
    require_once __DIR__ . '/app/lib/DB.php';
    echo "✅ DB loaded<br>";
    
    echo "3. Loading Utils (fixed)...<br>";
    require_once __DIR__ . '/app/lib/Utils.php';
    echo "✅ Utils loaded successfully!<br>";
    
    echo "<h3>✅ All files loaded! Now testing API endpoint...</h3>";
    
    // Simulate the exact API call
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/api/auth/google';
    
    echo "<h3>🌐 Simulating /api/auth/google call...</h3>";
    
    // Check OAuth configuration
    if (empty(GOOGLE_CLIENT_ID) || empty(GOOGLE_CLIENT_SECRET)) {
        echo "❌ Google OAuth not configured<br>";
        echo "CLIENT_ID empty: " . (empty(GOOGLE_CLIENT_ID) ? 'YES' : 'NO') . "<br>";
        echo "CLIENT_SECRET empty: " . (empty(GOOGLE_CLIENT_SECRET) ? 'YES' : 'NO') . "<br>";
    } else {
        echo "✅ Google OAuth is configured<br>";
        
        // Generate OAuth URL like the API does
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
        
        echo "✅ OAuth URL generated successfully<br>";
        echo "URL: <a href='$authUrl' target='_blank'>Test OAuth URL</a><br>";
        
        // Test sendJson function
        echo "<h3>📤 Testing sendJson function...</h3>";
        
        ob_start();
        
        // This should work now
        $testData = ['url' => $authUrl];
        
        // We can't call sendJson directly because it exits, so let's test the JSON encoding
        $jsonTest = json_encode(['success' => true, 'data' => $testData, 'errors' => []]);
        if ($jsonTest !== false) {
            echo "✅ JSON encoding works: " . htmlspecialchars($jsonTest) . "<br>";
        } else {
            echo "❌ JSON encoding failed<br>";
        }
        
        ob_end_clean();
    }
    
    echo "<h3>🎯 Direct API Test</h3>";
    echo "<a href='/api/auth/google' target='_blank'>🔗 Test /api/auth/google directly</a><br>";
    echo "<small>This should return JSON with the OAuth URL</small><br>";
    
} catch (ParseError $e) {
    echo "❌ Parse Error: " . $e->getMessage() . " in " . $e->getFile() . " line " . $e->getLine() . "<br>";
} catch (Error $e) {
    echo "❌ Fatal Error: " . $e->getMessage() . " in " . $e->getFile() . " line " . $e->getLine() . "<br>";
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . " in " . $e->getFile() . " line " . $e->getLine() . "<br>";
}

echo "<hr>";
echo "<h3>📊 System Info</h3>";
echo "PHP Version: " . PHP_VERSION . "<br>";
echo "Session ID: " . session_id() . "<br>";
echo "Current Time: " . date('Y-m-d H:i:s') . "<br>";

if (defined('GOOGLE_CLIENT_ID')) {
    echo "Google Client ID: " . substr(GOOGLE_CLIENT_ID, 0, 20) . "..." . "<br>";
}
if (defined('GOOGLE_REDIRECT_URI')) {
    echo "Redirect URI: " . GOOGLE_REDIRECT_URI . "<br>";
}
?>