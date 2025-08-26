<?php
// Test server configuration
header('Content-Type: application/json');

echo json_encode([
    'success' => true,
    'message' => 'Server is working',
    'php_version' => PHP_VERSION,
    'env_loaded' => file_exists(__DIR__ . '/.env'),
    'config_loaded' => class_exists('Config') || defined('GOOGLE_CLIENT_ID'),
    'session_started' => session_status() === PHP_SESSION_ACTIVE,
    'timestamp' => date('Y-m-d H:i:s')
]);
?>