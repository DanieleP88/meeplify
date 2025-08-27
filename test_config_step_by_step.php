<?php
echo "=== STEP BY STEP CONFIG TEST ===\n\n";

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "1. Testing basic PHP functionality:\n";
echo "PHP version: " . phpversion() . "\n";
echo "✅ Basic PHP works\n\n";

echo "2. Testing .env loading manually:\n";
$envPath = __DIR__ . '/.env';

// Manual .env loading (copy from Config.php)
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
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
    echo "✅ Manual .env loading completed\n\n";
} else {
    echo "❌ .env file not found\n\n";
}

echo "3. Testing individual constant definitions:\n";

try {
    if (!defined('GOOGLE_CLIENT_ID')) {
        define('GOOGLE_CLIENT_ID', $_ENV['GOOGLE_CLIENT_ID'] ?? '');
        echo "✅ GOOGLE_CLIENT_ID defined: " . (GOOGLE_CLIENT_ID ? "SET (len: " . strlen(GOOGLE_CLIENT_ID) . ")" : "EMPTY") . "\n";
    } else {
        echo "ℹ️ GOOGLE_CLIENT_ID already defined\n";
    }
} catch (Exception $e) {
    echo "❌ GOOGLE_CLIENT_ID failed: " . $e->getMessage() . "\n";
} catch (Error $e) {
    echo "❌ GOOGLE_CLIENT_ID fatal: " . $e->getMessage() . "\n";
}

try {
    if (!defined('GOOGLE_CLIENT_SECRET')) {
        define('GOOGLE_CLIENT_SECRET', $_ENV['GOOGLE_CLIENT_SECRET'] ?? '');
        echo "✅ GOOGLE_CLIENT_SECRET defined: " . (GOOGLE_CLIENT_SECRET ? "SET" : "EMPTY") . "\n";
    } else {
        echo "ℹ️ GOOGLE_CLIENT_SECRET already defined\n";
    }
} catch (Exception $e) {
    echo "❌ GOOGLE_CLIENT_SECRET failed: " . $e->getMessage() . "\n";
} catch (Error $e) {
    echo "❌ GOOGLE_CLIENT_SECRET fatal: " . $e->getMessage() . "\n";
}

try {
    if (!defined('GOOGLE_REDIRECT_URI')) {
        define('GOOGLE_REDIRECT_URI', $_ENV['GOOGLE_REDIRECT_URI'] ?? '');
        echo "✅ GOOGLE_REDIRECT_URI defined: " . GOOGLE_REDIRECT_URI . "\n";
    } else {
        echo "ℹ️ GOOGLE_REDIRECT_URI already defined\n";
    }
} catch (Exception $e) {
    echo "❌ GOOGLE_REDIRECT_URI failed: " . $e->getMessage() . "\n";
} catch (Error $e) {
    echo "❌ GOOGLE_REDIRECT_URI fatal: " . $e->getMessage() . "\n";
}

echo "\n4. Testing DB constants:\n";

try {
    if (!defined('DB_DSN')) {
        define('DB_DSN', 'mysql:host=' . ($_ENV['DB_HOST'] ?? 'localhost') . ';port=' . ($_ENV['DB_PORT'] ?? '3306') . ';dbname=' . ($_ENV['DB_NAME'] ?? 'meeplify') . ';charset=utf8mb4');
        echo "✅ DB_DSN defined: " . DB_DSN . "\n";
    } else {
        echo "ℹ️ DB_DSN already defined\n";
    }
} catch (Exception $e) {
    echo "❌ DB_DSN failed: " . $e->getMessage() . "\n";
} catch (Error $e) {
    echo "❌ DB_DSN fatal: " . $e->getMessage() . "\n";
}

try {
    if (!defined('DB_USER')) {
        define('DB_USER', $_ENV['DB_USER'] ?? 'root');
        echo "✅ DB_USER defined: " . DB_USER . "\n";
    } else {
        echo "ℹ️ DB_USER already defined\n";
    }
} catch (Exception $e) {
    echo "❌ DB_USER failed: " . $e->getMessage() . "\n";
} catch (Error $e) {
    echo "❌ DB_USER fatal: " . $e->getMessage() . "\n";
}

try {
    if (!defined('DB_PASS')) {
        define('DB_PASS', $_ENV['DB_PASS'] ?? '');
        echo "✅ DB_PASS defined\n";
    } else {
        echo "ℹ️ DB_PASS already defined\n";
    }
} catch (Exception $e) {
    echo "❌ DB_PASS failed: " . $e->getMessage() . "\n";
} catch (Error $e) {
    echo "❌ DB_PASS fatal: " . $e->getMessage() . "\n";
}

echo "\n5. Final OAuth test:\n";
if (empty(GOOGLE_CLIENT_ID) || empty(GOOGLE_CLIENT_SECRET)) {
    echo "❌ OAuth would fail - CLIENT_ID empty: " . (empty(GOOGLE_CLIENT_ID) ? 'YES' : 'NO') . ", CLIENT_SECRET empty: " . (empty(GOOGLE_CLIENT_SECRET) ? 'YES' : 'NO') . "\n";
} else {
    echo "✅ OAuth would succeed - both CLIENT_ID and CLIENT_SECRET are set\n";
}

echo "\n6. Testing actual Config.php require:\n";
try {
    // Reset constants for clean test
    // Note: Can't actually undefine constants in PHP, but we can test if require works
    require_once __DIR__ . '/app/lib/Config.php';
    echo "✅ Config.php require successful\n";
} catch (Exception $e) {
    echo "❌ Config.php require failed: " . $e->getMessage() . "\n";
} catch (Error $e) {
    echo "❌ Config.php require fatal: " . $e->getMessage() . "\n";
} catch (ParseError $e) {
    echo "❌ Config.php parse error: " . $e->getMessage() . "\n";
}

echo "\n=== TEST COMPLETED ===\n";
?>