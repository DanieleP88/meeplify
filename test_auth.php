<?php
session_start();

// Load dependencies
require_once __DIR__ . '/app/lib/Config.php';
require_once __DIR__ . '/app/lib/DB.php';
require_once __DIR__ . '/app/lib/Utils.php';

header('Content-Type: application/json');

try {
    // Test configuration loading
    $config_test = [
        'env_file_exists' => file_exists(__DIR__ . '/.env'),
        'google_client_id_defined' => defined('GOOGLE_CLIENT_ID'),
        'google_client_id_value' => defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : 'NOT DEFINED',
        'google_client_secret_defined' => defined('GOOGLE_CLIENT_SECRET'),
        'google_client_secret_empty' => defined('GOOGLE_CLIENT_SECRET') ? empty(GOOGLE_CLIENT_SECRET) : true,
        'google_redirect_uri' => defined('GOOGLE_REDIRECT_URI') ? GOOGLE_REDIRECT_URI : 'NOT DEFINED',
    ];
    
    // Test OAuth URL generation
    if (!empty(GOOGLE_CLIENT_ID) && !empty(GOOGLE_CLIENT_SECRET)) {
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
        $config_test['oauth_url_generated'] = true;
        $config_test['oauth_url'] = $authUrl;
    } else {
        $config_test['oauth_url_generated'] = false;
        $config_test['error'] = 'Google OAuth credentials not configured';
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Auth test completed',
        'data' => $config_test
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Auth test failed',
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT);
}
?>