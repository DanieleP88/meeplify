<?php
session_start();

// Load configuration
require_once __DIR__ . '/app/lib/Config.php';

echo "=== GOOGLE OAUTH DEBUG ===\n\n";

echo "Environment Variables:\n";
echo "GOOGLE_CLIENT_ID: " . (defined('GOOGLE_CLIENT_ID') ? (empty(GOOGLE_CLIENT_ID) ? '[EMPTY]' : '[SET - ' . substr(GOOGLE_CLIENT_ID, 0, 20) . '...]') : '[NOT DEFINED]') . "\n";
echo "GOOGLE_CLIENT_SECRET: " . (defined('GOOGLE_CLIENT_SECRET') ? (empty(GOOGLE_CLIENT_SECRET) ? '[EMPTY]' : '[SET]') : '[NOT DEFINED]') . "\n";
echo "GOOGLE_REDIRECT_URI: " . (defined('GOOGLE_REDIRECT_URI') ? GOOGLE_REDIRECT_URI : '[NOT DEFINED]') . "\n\n";

echo "Raw .env file content:\n";
if (file_exists(__DIR__ . '/.env')) {
    $env_content = file_get_contents(__DIR__ . '/.env');
    echo $env_content . "\n\n";
} else {
    echo ".env file not found!\n\n";
}

echo "Configuration Status:\n";
if (empty(GOOGLE_CLIENT_ID) || empty(GOOGLE_CLIENT_SECRET)) {
    echo "❌ OAuth NOT configured - this causes the 500 error\n";
    echo "You need to:\n";
    echo "1. Go to Google Cloud Console: https://console.cloud.google.com/\n";
    echo "2. Create OAuth 2.0 credentials\n";
    echo "3. Set authorized redirect URI: https://www.meeplify.it/api/google/callback\n";
    echo "4. Copy Client ID and Secret to .env file\n";
} else {
    echo "✅ OAuth configured correctly\n";
}

echo "\n=== SIMULATING API CALL ===\n";
try {
    // Simulate the exact API call that's failing
    if (empty(GOOGLE_CLIENT_ID) || empty(GOOGLE_CLIENT_SECRET)) {
        echo "❌ Would return 500: Google OAuth not configured\n";
    } else {
        $state = bin2hex(random_bytes(16));
        $params = [
            'client_id' => GOOGLE_CLIENT_ID,
            'redirect_uri' => GOOGLE_REDIRECT_URI,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
        ];
        $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
        echo "✅ Would return auth URL: $authUrl\n";
    }
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n";
}
?>