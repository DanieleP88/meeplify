<?php
// Test PHP più semplice possibile
echo "PHP is working!<br>";
echo "PHP Version: " . PHP_VERSION . "<br>";
echo "Current directory: " . __DIR__ . "<br>";
echo "Date: " . date('Y-m-d H:i:s') . "<br>";

// Test se il file .env esiste
if (file_exists(__DIR__ . '/.env')) {
    echo ".env file exists<br>";
} else {
    echo ".env file NOT found<br>";
}

// Test caricamento Config.php
try {
    require_once __DIR__ . '/app/lib/Config.php';
    echo "Config.php loaded successfully<br>";
    
    if (defined('GOOGLE_CLIENT_ID')) {
        echo "GOOGLE_CLIENT_ID is defined: " . GOOGLE_CLIENT_ID . "<br>";
    } else {
        echo "GOOGLE_CLIENT_ID is NOT defined<br>";
    }
} catch (Exception $e) {
    echo "ERROR loading Config.php: " . $e->getMessage() . "<br>";
}
?>