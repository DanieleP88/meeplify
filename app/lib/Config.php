<?php

// Load environment variables
function loadEnv($filePath) {
    if (!file_exists($filePath)) {
        return;
    }
    
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }
        
        $parts = explode('=', $line, 2);
        if (count($parts) < 2) {
            continue;
        }
        
        $name = trim($parts[0]);
        $value = trim($parts[1]);
        
        if (empty($name)) {
            continue;
        }
        
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

loadEnv(__DIR__ . '/../../.env');

// Google OAuth2 Configuration (with conditional define to avoid redefinition errors)
if (!defined('GOOGLE_CLIENT_ID')) {
    define('GOOGLE_CLIENT_ID', $_ENV['GOOGLE_CLIENT_ID'] ?? '');
}
if (!defined('GOOGLE_CLIENT_SECRET')) {
    define('GOOGLE_CLIENT_SECRET', $_ENV['GOOGLE_CLIENT_SECRET'] ?? '');
}
if (!defined('GOOGLE_REDIRECT_URI')) {
    define('GOOGLE_REDIRECT_URI', $_ENV['GOOGLE_REDIRECT_URI'] ?? '');
}

// Database Configuration (with conditional define)
if (!defined('DB_DSN')) {
    define('DB_DSN', 'mysql:host=' . ($_ENV['DB_HOST'] ?? 'localhost') . ';port=' . ($_ENV['DB_PORT'] ?? '3306') . ';dbname=' . ($_ENV['DB_NAME'] ?? 'meeplify') . ';charset=utf8mb4');
}
if (!defined('DB_USER')) {
    define('DB_USER', $_ENV['DB_USER'] ?? 'root');
}
if (!defined('DB_PASS')) {
    define('DB_PASS', $_ENV['DB_PASS'] ?? '');
}

?>