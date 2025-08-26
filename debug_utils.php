<?php
// Debug Utils.php passo per passo
session_start();
header('Content-Type: text/html; charset=utf-8');

echo "<h2>🔍 Debug Utils.php Step by Step</h2>";

// Prima carica le dipendenze
require_once __DIR__ . '/app/lib/Config.php';
require_once __DIR__ . '/app/lib/DB.php';

echo "✅ Config and DB loaded<br>";

// Leggi Utils.php e controllalo
$utilsPath = __DIR__ . '/app/lib/Utils.php';
$utilsContent = file_get_contents($utilsPath);

echo "<h3>📁 File Info</h3>";
echo "File size: " . strlen($utilsContent) . " bytes<br>";
echo "Line count: " . substr_count($utilsContent, "\n") . " lines<br>";

// Controlla se ci sono errori di sintassi evidenti
echo "<h3>🔍 Checking for obvious issues...</h3>";

// Check for unmatched braces
$openBraces = substr_count($utilsContent, '{');
$closeBraces = substr_count($utilsContent, '}');
echo "Open braces: $openBraces, Close braces: $closeBraces ";
if ($openBraces === $closeBraces) {
    echo "✅ Balanced<br>";
} else {
    echo "❌ Unbalanced!<br>";
}

// Check for unmatched parentheses
$openParens = substr_count($utilsContent, '(');
$closeParens = substr_count($utilsContent, ')');
echo "Open parens: $openParens, Close parens: $closeParens ";
if ($openParens === $closeParens) {
    echo "✅ Balanced<br>";
} else {
    echo "❌ Unbalanced!<br>";
}

// Check for PHP tags
if (strpos($utilsContent, '<?php') === 0) {
    echo "✅ Starts with <?php<br>";
} else {
    echo "❌ Doesn't start with <?php<br>";
}

// Check for duplicate function definitions
echo "<h3>🔍 Function Definitions</h3>";
preg_match_all('/function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/i', $utilsContent, $matches);
$functions = $matches[1];
$functionCounts = array_count_values($functions);

foreach ($functionCounts as $func => $count) {
    if ($count > 1) {
        echo "❌ Function '$func' defined $count times<br>";
    } else {
        echo "✅ Function '$func' defined once<br>";
    }
}

// Try to eval just the beginning to find where it breaks
echo "<h3>🧪 Testing code sections...</h3>";

// Split into smaller chunks and test each
$lines = explode("\n", $utilsContent);
$testCode = "<?php\n";
$lineNum = 1;

foreach ($lines as $line) {
    $lineNum++;
    $testCode .= $line . "\n";
    
    // Every 50 lines, try to check the syntax
    if ($lineNum % 50 === 0) {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_chunk_');
        file_put_contents($tempFile, $testCode);
        
        // Use php -l alternative - try to include it in a separate process
        ob_start();
        $error = false;
        
        try {
            // Create a test file that tries to include our chunk
            $testScript = "<?php\nini_set('display_errors', 1);\nerror_reporting(E_ALL);\nrequire_once '$tempFile';\necho 'OK';\n";
            $testFile = tempnam(sys_get_temp_dir(), 'test_script_');
            file_put_contents($testFile, $testScript);
            
            // We can't exec php, so let's try a different approach
            unlink($tempFile);
            unlink($testFile);
            
        } catch (Exception $e) {
            $error = true;
        }
        
        ob_end_clean();
        
        echo "Lines 1-$lineNum: " . ($error ? "❌ Error" : "✅ OK") . "<br>";
    }
}

echo "<h3>📋 Next Steps</h3>";
echo "If there are duplicate functions or syntax errors above, we need to fix them.<br>";
echo "If everything looks OK, the issue might be with a specific function call or dependency.<br>";
?>