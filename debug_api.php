<?php
// Debug API routing
echo "Debug API Request:\n";
echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'not set') . "\n";
echo "SCRIPT_NAME: " . ($_SERVER['SCRIPT_NAME'] ?? 'not set') . "\n";
echo "PATH_INFO: " . ($_SERVER['PATH_INFO'] ?? 'not set') . "\n";

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
echo "Parsed URI: $uri\n";

$uri_parts = explode('/', trim($uri, '/'));
echo "URI parts: " . json_encode($uri_parts) . "\n";

array_shift($uri_parts); // remove 'api'
echo "After removing first part: " . json_encode($uri_parts) . "\n";

$resource = $uri_parts[0] ?? '';
echo "Resource: '$resource'\n";

if ($resource === 'auth') {
    echo "Auth section matched\n";
    if (isset($uri_parts[1])) {
        echo "Second part: '" . $uri_parts[1] . "'\n";
        if ($uri_parts[1] == 'google') {
            echo "Google auth matched!\n";
        }
    } else {
        echo "No second part found\n";
    }
}
?>