<?php
// Test diretto del caricamento di Utils.php
session_start();
header('Content-Type: text/html; charset=utf-8');

echo "<h2>Test Utils.php Direct Loading</h2>";

// Crea la directory logs se non esiste
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    if (mkdir($logDir, 0755, true)) {
        echo "✅ Created logs directory<br>";
    } else {
        echo "⚠️ Could not create logs directory<br>";
    }
}

echo "<h3>Loading files step by step...</h3>";

try {
    echo "1. Loading Config.php...<br>";
    require_once __DIR__ . '/app/lib/Config.php';
    echo "✅ Config.php loaded<br>";
    
    echo "2. Loading DB.php...<br>";
    require_once __DIR__ . '/app/lib/DB.php';
    echo "✅ DB.php loaded<br>";
    
    echo "3. Loading Utils.php...<br>";
    
    // Cattura tutti gli errori
    set_error_handler(function($errno, $errstr, $errfile, $errline) {
        echo "❌ PHP Error [$errno]: $errstr in $errfile on line $errline<br>";
        return true;
    });
    
    require_once __DIR__ . '/app/lib/Utils.php';
    echo "✅ Utils.php loaded successfully<br>";
    
    // Ripristina error handler
    restore_error_handler();
    
    echo "<h3>Testing functions...</h3>";
    
    if (function_exists('sendJson')) {
        echo "✅ sendJson function exists<br>";
    } else {
        echo "❌ sendJson function missing<br>";
    }
    
    if (function_exists('logMessage')) {
        echo "✅ logMessage function exists<br>";
    } else {
        echo "❌ logMessage function missing<br>";
    }
    
    if (function_exists('checkAuth')) {
        echo "✅ checkAuth function exists<br>";
    } else {
        echo "❌ checkAuth function missing<br>";
    }
    
    echo "<h3>Testing simple sendJson call...</h3>";
    
    // Test sendJson in modo sicuro senza terminare lo script
    ob_start();
    
    // Redefine sendJson temporaneamente per test
    if (!function_exists('sendJson_test')) {
        function sendJson_test($success, $data = null, $errors = [], $status = 200) {
            http_response_code($status);
            header('Content-Type: application/json');
            echo json_encode(['success' => $success, 'data' => $data, 'errors' => $errors]);
            // NON esce, così possiamo continuare il test
        }
    }
    
    sendJson_test(true, ['test' => 'success']);
    $output = ob_get_clean();
    
    echo "✅ sendJson test output: " . htmlspecialchars($output) . "<br>";
    
} catch (ParseError $e) {
    echo "❌ Parse Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() . "<br>";
} catch (Error $e) {
    echo "❌ Fatal Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() . "<br>";
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() . "<br>";
}

echo "<h3>System Info</h3>";
echo "PHP Version: " . PHP_VERSION . "<br>";
echo "Current working directory: " . getcwd() . "<br>";
echo "Script directory: " . __DIR__ . "<br>";
echo "Logs directory writable: " . (is_writable($logDir) ? 'YES' : 'NO') . "<br>";

if (function_exists('get_loaded_extensions')) {
    echo "Loaded extensions: " . implode(', ', get_loaded_extensions()) . "<br>";
}
?>