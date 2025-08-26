<?php
// Test OAuth with corrected redirect URI
session_start();
header('Content-Type: text/html; charset=utf-8');

echo "<h2>🔧 OAuth Final Test - Production Configuration</h2>";

try {
    echo "1. Loading Config...<br>";
    require_once __DIR__ . '/app/lib/Config.php';
    echo "✅ Config loaded<br>";
    
    echo "<h3>📋 Configuration Check</h3>";
    
    if (defined('GOOGLE_CLIENT_ID')) {
        echo "✅ GOOGLE_CLIENT_ID: " . substr(GOOGLE_CLIENT_ID, 0, 20) . "...<br>";
    } else {
        echo "❌ GOOGLE_CLIENT_ID not defined<br>";
    }
    
    if (defined('GOOGLE_CLIENT_SECRET')) {
        echo "✅ GOOGLE_CLIENT_SECRET: " . (empty(GOOGLE_CLIENT_SECRET) ? 'EMPTY' : 'SET') . "<br>";
    } else {
        echo "❌ GOOGLE_CLIENT_SECRET not defined<br>";
    }
    
    if (defined('GOOGLE_REDIRECT_URI')) {
        echo "✅ GOOGLE_REDIRECT_URI: " . GOOGLE_REDIRECT_URI . "<br>";
    } else {
        echo "❌ GOOGLE_REDIRECT_URI not defined<br>";
    }
    
    echo "<h3>🌐 OAuth URL Generation Test</h3>";
    
    // Generate OAuth URL exactly as the API does
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
    echo "State stored in session: " . $_SESSION['oauth_state'] . "<br>";
    echo "<br>";
    
    echo "<strong>Generated URL:</strong><br>";
    echo "<a href='$authUrl' target='_blank' style='color: #2563eb; word-break: break-all;'>$authUrl</a><br><br>";
    
    echo "<h3>🎯 API Endpoints Test</h3>";
    echo "<a href='/api/auth/google' target='_blank' style='background: #2563eb; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>🚀 Test /api/auth/google</a>";
    echo "<a href='/' style='background: #059669; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🏠 Go to Homepage</a><br><br>";
    
    echo "<h3>✅ Expected Flow</h3>";
    echo "1. Click 'Test /api/auth/google' → Should return JSON with OAuth URL<br>";
    echo "2. Copy OAuth URL and test in browser → Should redirect to Google<br>";
    echo "3. After Google login → Should redirect to <code>/api/google/callback</code><br>";
    echo "4. Callback should authenticate and redirect to <code>/#dashboard</code><br>";
    
} catch (Exception $e) {
    echo "❌ Error: " . htmlspecialchars($e->getMessage()) . "<br>";
}
?>