<?php
echo "=== DEBUG ENV LOADING ===\n\n";

// Test 1: Check if .env file exists
$envPath = __DIR__ . '/.env';
echo "1. Testing .env file:\n";
echo "Path: $envPath\n";
echo "Exists: " . (file_exists($envPath) ? "YES" : "NO") . "\n";
echo "Readable: " . (is_readable($envPath) ? "YES" : "NO") . "\n";

if (file_exists($envPath)) {
    echo "File size: " . filesize($envPath) . " bytes\n";
    $content = file_get_contents($envPath);
    echo "Content preview (first 200 chars):\n";
    echo substr($content, 0, 200) . "\n\n";
    
    // Check for OAuth variables specifically
    echo "OAuth variables in file:\n";
    $lines = explode("\n", $content);
    foreach ($lines as $line) {
        if (strpos($line, 'GOOGLE_') === 0) {
            echo "Found: $line\n";
        }
    }
}

echo "\n2. Testing loadEnv function:\n";

// Copy the loadEnv function to test it
function loadEnv($filePath) {
    echo "Loading from: $filePath\n";
    
    if (!file_exists($filePath)) {
        echo "❌ File does not exist\n";
        return;
    }
    
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    echo "Read " . count($lines) . " lines\n";
    
    foreach ($lines as $line) {
        $line = trim($line);
        echo "Processing line: '$line'\n";
        
        if (empty($line) || strpos($line, '#') === 0) {
            echo "  -> Skipped (empty or comment)\n";
            continue;
        }
        if (strpos($line, '=') === false) {
            echo "  -> Skipped (no = sign)\n";
            continue;
        }
        
        $parts = explode('=', $line, 2);
        if (count($parts) < 2) {
            echo "  -> Skipped (invalid format)\n";
            continue;
        }
        
        $name = trim($parts[0]);
        $value = trim($parts[1]);
        
        if (empty($name)) {
            echo "  -> Skipped (empty name)\n";
            continue;
        }
        
        echo "  -> Setting $name = '$value'\n";
        
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
            echo "  -> Set successfully\n";
        } else {
            echo "  -> Already exists, skipped\n";
        }
    }
}

// Test loading
loadEnv($envPath);

echo "\n3. Testing environment variables after loading:\n";
echo "GOOGLE_CLIENT_ID in \$_ENV: " . (isset($_ENV['GOOGLE_CLIENT_ID']) ? ($_ENV['GOOGLE_CLIENT_ID'] ? "SET (len: " . strlen($_ENV['GOOGLE_CLIENT_ID']) . ")" : "EMPTY") : "NOT SET") . "\n";
echo "GOOGLE_CLIENT_SECRET in \$_ENV: " . (isset($_ENV['GOOGLE_CLIENT_SECRET']) ? ($_ENV['GOOGLE_CLIENT_SECRET'] ? "SET" : "EMPTY") : "NOT SET") . "\n";
echo "GOOGLE_REDIRECT_URI in \$_ENV: " . (isset($_ENV['GOOGLE_REDIRECT_URI']) ? $_ENV['GOOGLE_REDIRECT_URI'] : "NOT SET") . "\n";

echo "\n4. Testing after Config.php loading:\n";
try {
    require_once __DIR__ . '/app/lib/Config.php';
    echo "✅ Config.php loaded successfully\n";
} catch (Exception $e) {
    echo "❌ Config.php loading failed: " . $e->getMessage() . "\n";
} catch (Error $e) {
    echo "❌ Config.php fatal error: " . $e->getMessage() . "\n";
}

echo "GOOGLE_CLIENT_ID constant: " . (defined('GOOGLE_CLIENT_ID') ? (GOOGLE_CLIENT_ID ? "SET (len: " . strlen(GOOGLE_CLIENT_ID) . ")" : "EMPTY") : "NOT DEFINED") . "\n";
echo "GOOGLE_CLIENT_SECRET constant: " . (defined('GOOGLE_CLIENT_SECRET') ? (GOOGLE_CLIENT_SECRET ? "SET" : "EMPTY") : "NOT DEFINED") . "\n";
echo "GOOGLE_REDIRECT_URI constant: " . (defined('GOOGLE_REDIRECT_URI') ? GOOGLE_REDIRECT_URI : "NOT DEFINED") . "\n";

// Additional test: simulate what the OAuth endpoint does
echo "\n5. Testing OAuth endpoint logic simulation:\n";
if (empty(GOOGLE_CLIENT_ID) || empty(GOOGLE_CLIENT_SECRET)) {
    echo "❌ OAuth would fail - CLIENT_ID empty: " . (empty(GOOGLE_CLIENT_ID) ? 'YES' : 'NO') . ", CLIENT_SECRET empty: " . (empty(GOOGLE_CLIENT_SECRET) ? 'YES' : 'NO') . "\n";
} else {
    echo "✅ OAuth would succeed - both CLIENT_ID and CLIENT_SECRET are set\n";
}

echo "\n=== END DEBUG ===\n";
?>