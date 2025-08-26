&lt;?php

require_once '../../../lib/GoogleOAuth.php';
require_once '../../../lib/DB.php';

session_start();

// Check state for anti-CSRF
if (!isset($_GET['state']) || !isset($_SESSION['oauth_state']) || $_GET['state'] !== $_SESSION['oauth_state']) {
    http_response_code(403);
    exit('Invalid state');
}
unset($_SESSION['oauth_state']);

if (!isset($_GET['code'])) {
    http_response_code(400);
    exit('Missing code');
}

$token = GoogleOAuth::exchangeCodeForToken($_GET['code']);

if (isset($token['error'])) {
    http_response_code(401);
    exit($token['error']);
}

$userinfo = GoogleOAuth::getUserInfo($token['access_token']);

if (isset($userinfo['error'])) {
    http_response_code(401);
    exit($userinfo['error']);
}

$google_sub = $userinfo['id'];
$email = $userinfo['email'];
$name = $userinfo['given_name'];
$surname = $userinfo['family_name'];

// Create or update user
$pdo = DB::getPDO();

$stmt = $pdo-&gt;prepare('SELECT id FROM users WHERE google_sub = ?');
$stmt-&gt;execute([$google_sub]);
$row = $stmt-&gt;fetch();

if ($row) {
    $user_id = $row['id'];
    $stmt = $pdo-&gt;prepare('UPDATE users SET name = ?, surname = ?, email = ?, updated_at = NOW() WHERE id = ?');
    $stmt-&gt;execute([$name, $surname, $email, $user_id]);
} else {
    $stmt = $pdo-&gt;prepare('INSERT INTO users (google_sub, name, surname, email, role, created_at, updated_at) VALUES (?, ?, ?, ?, \'user\', NOW(), NOW())');
    $stmt-&gt;execute([$google_sub, $name, $surname, $email]);
    $user_id = $pdo-&gt;lastInsertId();
}

// Secure session management
session_set_cookie_params([
    'lifetime' =&gt; 0,
    'path' =&gt; '/',
    'secure' =&gt; true,
    'httponly' =&gt; true,
    'samesite' =&gt; 'Lax'
]);

session_regenerate_id(true);
$_SESSION['user_id'] = $user_id;
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// Redirect to app home or return JSON if needed
// For REST, perhaps redirect to frontend with success
header('Location: /app'); // Adjust to your frontend home
exit;

?&gt;