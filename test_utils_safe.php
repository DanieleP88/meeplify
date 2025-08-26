<?php
// Test sicuro per Utils.php
session_start();
header('Content-Type: text/html; charset=utf-8');

echo "<h2>Test Utils.php Safe</h2>";

// Test se il file esiste
if (!file_exists(__DIR__ . '/app/lib/Utils.php')) {
    echo "❌ Utils.php file not found<br>";
    exit;
}

// Leggi il file Utils.php e cerca errori di sintassi
$utilsContent = file_get_contents(__DIR__ . '/app/lib/Utils.php');
echo "✅ Utils.php file exists (" . strlen($utilsContent) . " bytes)<br>";

// Test syntax usando php -l
$tempFile = tempnam(sys_get_temp_dir(), 'test_utils_');
file_put_contents($tempFile, $utilsContent);
$output = [];
$return_var = 0;
exec("php -l $tempFile 2>&1", $output, $return_var);
unlink($tempFile);

if ($return_var === 0) {
    echo "✅ Utils.php syntax is valid<br>";
} else {
    echo "❌ Utils.php syntax error:<br>";
    echo "<pre>" . implode("\n", $output) . "</pre>";
    exit;
}

// Test caricamento senza eseguire logMessage
echo "<h3>Trying to load Utils.php...</h3>";

// Disabilita gli errori temporaneamente per vedere se si carica
error_reporting(E_ERROR | E_PARSE);

try {
    // Prima carica Config e DB
    require_once __DIR__ . '/app/lib/Config.php';
    require_once __DIR__ . '/app/lib/DB.php';
    echo "✅ Config and DB loaded<br>";
    
    // Poi prova Utils
    require_once __DIR__ . '/app/lib/Utils.php';
    echo "✅ Utils.php loaded successfully<br>";
    
    // Test se le funzioni esistono
    if (function_exists('sendJson')) {
        echo "✅ sendJson function exists<br>";
    } else {
        echo "❌ sendJson function not found<br>";
    }
    
    if (function_exists('logMessage')) {
        echo "✅ logMessage function exists<br>";
    } else {
        echo "❌ logMessage function not found<br>";
    }
    
    // Test directory logs
    $logDir = __DIR__ . '/logs';
    if (is_dir($logDir)) {
        echo "✅ Logs directory exists<br>";
    } else {
        echo "⚠️ Logs directory doesn't exist, trying to create...<br>";
        if (mkdir($logDir, 0755, true)) {
            echo "✅ Logs directory created<br>";
        } else {
            echo "❌ Cannot create logs directory<br>";
        }
    }
    
    // Test write permissions
    if (is_writable(dirname($logDir))) {
        echo "✅ Parent directory is writable<br>";
    } else {
        echo "❌ Parent directory is not writable<br>";
    }
    
} catch (ParseError $e) {
    echo "❌ Parse Error in Utils.php: " . $e->getMessage() . "<br>";
    echo "Line: " . $e->getLine() . "<br>";
} catch (Error $e) {
    echo "❌ Fatal Error: " . $e->getMessage() . "<br>";
    echo "Line: " . $e->getLine() . "<br>";
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "<br>";
}

// Ripristina error reporting
error_reporting(E_ALL);

echo "<h3>Done</h3>";
?>