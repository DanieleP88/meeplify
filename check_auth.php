<?php
session_start();

echo "=== AUTHENTICATION STATUS CHECK ===\n\n";

// Raw session data
echo "Raw Session Data:\n";
print_r($_SESSION);

echo "\nSpecific Authentication Variables:\n";
echo "- authenticated: " . var_export($_SESSION['authenticated'] ?? null, true) . "\n";
echo "- user_id: " . var_export($_SESSION['user_id'] ?? null, true) . "\n";
echo "- email: " . var_export($_SESSION['email'] ?? null, true) . "\n";
echo "- name: " . var_export($_SESSION['name'] ?? null, true) . "\n";
echo "- role: " . var_export($_SESSION['role'] ?? null, true) . "\n";

echo "\nAuthentication Check Logic:\n";
if (isset($_SESSION['user_id'])) {
    echo "✅ User ID exists in session\n";
} else {
    echo "❌ No user_id in session - this causes 401 error\n";
}

if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
    echo "✅ Authentication flag is set to true\n";
} else {
    echo "❌ Authentication flag is missing or false\n";
}

// Check Google OAuth configuration
echo "\nGoogle OAuth Configuration:\n";
require_once __DIR__ . '/app/lib/Config.php';
echo "- GOOGLE_CLIENT_ID: " . (empty(GOOGLE_CLIENT_ID) ? "❌ NOT SET" : "✅ SET") . "\n";
echo "- GOOGLE_CLIENT_SECRET: " . (empty(GOOGLE_CLIENT_SECRET) ? "❌ NOT SET" : "✅ SET") . "\n";
echo "- GOOGLE_REDIRECT_URI: " . (empty(GOOGLE_REDIRECT_URI) ? "❌ NOT SET" : "✅ SET (" . GOOGLE_REDIRECT_URI . ")") . "\n";

echo "\n=== END CHECK ===\n";
?>