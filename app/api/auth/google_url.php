&lt;?php

require_once '../../../lib/GoogleOAuth.php';

header('Content-Type: application/json');

// Generate a state for anti-CSRF
session_start();
$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;

$url = GoogleOAuth::getAuthUrl($state);

echo json_encode(['success' =&gt; true, 'data' =&gt; ['url' =&gt; $url]]);

?&gt;