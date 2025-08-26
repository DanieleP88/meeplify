<?php
// Test diretto dell'endpoint API
session_start();
header('Content-Type: text/html; charset=utf-8');

echo "<h2>Test API Direct</h2>";

echo "<h3>1. PHP Base</h3>";
echo "PHP Version: " . PHP_VERSION . "<br>";
echo "Session ID: " . session_id() . "<br>";

echo "<h3>2. File Structure</h3>";
echo ".env exists: " . (file_exists(__DIR__ . '/.env') ? 'YES' : 'NO') . "<br>";
echo "Config.php exists: " . (file_exists(__DIR__ . '/app/lib/Config.php') ? 'YES' : 'NO') . "<br>";
echo "Utils.php exists: " . (file_exists(__DIR__ . '/app/lib/Utils.php') ? 'YES' : 'NO') . "<br>";

echo "<h3>3. Try Loading Config</h3>";
try {
    require_once __DIR__ . '/app/lib/Config.php';
    echo "✅ Config loaded<br>";
    
    echo "GOOGLE_CLIENT_ID defined: " . (defined('GOOGLE_CLIENT_ID') ? 'YES' : 'NO') . "<br>";
    if (defined('GOOGLE_CLIENT_ID')) {
        echo "GOOGLE_CLIENT_ID value: " . GOOGLE_CLIENT_ID . "<br>";
    }
    
    echo "GOOGLE_CLIENT_SECRET defined: " . (defined('GOOGLE_CLIENT_SECRET') ? 'YES' : 'NO') . "<br>";
    if (defined('GOOGLE_CLIENT_SECRET')) {
        echo "GOOGLE_CLIENT_SECRET empty: " . (empty(GOOGLE_CLIENT_SECRET) ? 'YES' : 'NO') . "<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Config error: " . $e->getMessage() . "<br>";
}

echo "<h3>4. Try Loading Utils</h3>";
try {
    require_once __DIR__ . '/app/lib/Utils.php';
    echo "✅ Utils loaded<br>";
    
    // Test sendJson function
    if (function_exists('sendJson')) {
        echo "✅ sendJson function exists<br>";
    } else {
        echo "❌ sendJson function missing<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Utils error: " . $e->getMessage() . "<br>";
}

echo "<h3>5. Try Manual OAuth URL</h3>";
if (defined('GOOGLE_CLIENT_ID') && !empty(GOOGLE_CLIENT_ID)) {
    $state = bin2hex(random_bytes(16));
    $params = [
        'client_id' => GOOGLE_CLIENT_ID,
        'redirect_uri' => 'http://localhost/api/?callback=google',
        'response_type' => 'code',
        'scope' => 'openid email profile',
        'state' => $state,
    ];
    
    $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    echo "✅ OAuth URL generated<br>";
    echo "URL: <a href='$authUrl' target='_blank'>$authUrl</a><br>";
} else {
    echo "❌ Cannot generate OAuth URL - GOOGLE_CLIENT_ID missing<br>";
}
?>