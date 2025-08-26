<?php
session_start();

// Load dependencies
require_once __DIR__ . '/../lib/Config.php';
require_once __DIR__ . '/../lib/DB.php';
require_once __DIR__ . '/../lib/Utils.php';

// Load API handlers
require_once __DIR__ . '/handlers/ChecklistHandler.php';
require_once __DIR__ . '/handlers/SectionHandler.php';
require_once __DIR__ . '/handlers/ItemHandler.php';
require_once __DIR__ . '/handlers/CollaborationHandler.php';
require_once __DIR__ . '/handlers/TagHandler.php';
require_once __DIR__ . '/handlers/TemplateHandler.php';
require_once __DIR__ . '/handlers/AdminHandler.php';

// Set security headers
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Enable CORS for local development
if ($_SERVER['HTTP_HOST'] === 'localhost' || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false) {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, X-CSRF-Token');
}

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// OAuth helper functions
function exchangeCodeForToken($code) {
    $params = [
        'code' => $code,
        'client_id' => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri' => GOOGLE_REDIRECT_URI,
        'grant_type' => 'authorization_code',
    ];
    
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode != 200) {
        return ['error' => 'Token exchange failed'];
    }
    
    return json_decode($response, true);
}

function getUserInfo($access_token) {
    $ch = curl_init('https://www.googleapis.com/oauth2/v1/userinfo?access_token=' . urlencode($access_token));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode != 200) {
        return ['error' => 'Userinfo failed'];
    }
    
    return json_decode($response, true);
}

try {
    // Parse the request
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $method = $_SERVER['REQUEST_METHOD'];
    
    // Handle Google OAuth callback (special case)
    if ($uri === '/api/google/callback' || (isset($_GET['callback']) && $_GET['callback'] === 'google')) {
        if (isset($_GET['error'])) {
            echo "<h1>Login Error</h1><p>" . htmlspecialchars($_GET['error']) . "</p>";
            exit;
        }
        
        if (!isset($_GET['code']) || !isset($_GET['state'])) {
            echo "<h1>Login Error</h1><p>Missing authorization code or state</p>";
            exit;
        }
        
        if ($_GET['state'] !== ($_SESSION['oauth_state'] ?? '')) {
            echo "<h1>Login Error</h1><p>Invalid state parameter</p>";
            exit;
        }
        
        try {
            $tokenData = exchangeCodeForToken($_GET['code']);
            if (!$tokenData || isset($tokenData['error'])) {
                throw new Exception('Failed to exchange code for token');
            }
            
            $userInfo = getUserInfo($tokenData['access_token']);
            if (!$userInfo || isset($userInfo['error'])) {
                throw new Exception('Failed to get user info');
            }
            
            $pdo = DB::getPDO();
            
            // First try to find user by google_sub
            $stmt = $pdo->prepare("SELECT id, role FROM users WHERE google_sub = ?");
            $stmt->execute([$userInfo['id']]);
            $user = $stmt->fetch();
            
            if (!$user) {
                // If not found by google_sub, try to find by email
                $stmt = $pdo->prepare("SELECT id, role, google_sub FROM users WHERE email = ?");
                $stmt->execute([$userInfo['email']]);
                $user = $stmt->fetch();
                
                if ($user) {
                    // User exists with this email, update their google_sub
                    $stmt = $pdo->prepare("UPDATE users SET google_sub = ?, name = ? WHERE id = ?");
                    $stmt->execute([$userInfo['id'], $userInfo['name'], $user['id']]);
                    $user_id = $user['id'];
                    $role = $user['role'];
                } else {
                    // Create new user
                    $stmt = $pdo->prepare("INSERT INTO users (google_sub, email, name, role) VALUES (?, ?, ?, 'user')");
                    $stmt->execute([$userInfo['id'], $userInfo['email'], $userInfo['name']]);
                    $user_id = $pdo->lastInsertId();
                    $role = 'user';
                }
            } else {
                $user_id = $user['id'];
                $role = $user['role'];
            }
            
            // Set session
            $_SESSION['user_id'] = $user_id;
            $_SESSION['role'] = $role;
            $_SESSION['email'] = $userInfo['email'];
            $_SESSION['name'] = $userInfo['name'];
            $_SESSION['authenticated'] = true;
            
            // Generate CSRF token
            if (!isset($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
            
            // Redirect to app
            header('Location: /#dashboard');
            exit;
            
        } catch (Exception $e) {
            echo "<h1>Login Error</h1><p>Authentication failed: " . htmlspecialchars($e->getMessage()) . "</p>";
            exit;
        }
    }
    
    // Parse API routes
    $pathParts = explode('/', trim($uri, '/'));
    if ($pathParts[0] === 'api') {
        array_shift($pathParts); // remove 'api'
    }
    
    $resource = $pathParts[0] ?? '';
    $action = $pathParts[1] ?? '';
    $id = $pathParts[2] ?? null;
    
    // Handle API endpoints
    if ($resource === 'auth') {
        if ($action === 'google' && $method === 'GET') {
            // Debug: Log configuration values
            error_log("GOOGLE_CLIENT_ID: " . (defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : 'NOT DEFINED'));
            error_log("GOOGLE_CLIENT_SECRET: " . (defined('GOOGLE_CLIENT_SECRET') ? (empty(GOOGLE_CLIENT_SECRET) ? 'EMPTY' : 'SET') : 'NOT DEFINED'));
            error_log("GOOGLE_REDIRECT_URI: " . (defined('GOOGLE_REDIRECT_URI') ? GOOGLE_REDIRECT_URI : 'NOT DEFINED'));
            
            // Check if Google OAuth is configured
            if (empty(GOOGLE_CLIENT_ID) || empty(GOOGLE_CLIENT_SECRET)) {
                error_log("OAuth configuration failed - CLIENT_ID empty: " . (empty(GOOGLE_CLIENT_ID) ? 'YES' : 'NO') . ", CLIENT_SECRET empty: " . (empty(GOOGLE_CLIENT_SECRET) ? 'YES' : 'NO'));
                sendJson(false, null, ['Google OAuth not configured. Please set GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET in .env file'], 500);
                exit;
            }
            
            // Generate Google OAuth URL
            $state = bin2hex(random_bytes(16));
            $_SESSION['oauth_state'] = $state;
            
            $params = [
                'client_id' => GOOGLE_CLIENT_ID,
                'redirect_uri' => GOOGLE_REDIRECT_URI,
                'response_type' => 'code',
                'scope' => 'openid email profile',
                'state' => $state,
            ];
            
            $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
            sendJson(true, ['url' => $authUrl]);
            
        } elseif ($action === 'me' && $method === 'GET') {
            // Check authentication status
            if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
                sendJson(true, [
                    'user_id' => $_SESSION['user_id'] ?? null,
                    'email' => $_SESSION['email'] ?? '',
                    'name' => $_SESSION['name'] ?? '',
                    'role' => $_SESSION['role'] ?? 'user',
                    'csrf_token' => $_SESSION['csrf_token'] ?? ''
                ]);
            } else {
                sendJson(false, null, ['Not authenticated'], 401);
            }
            
        } elseif ($action === 'logout' && $method === 'POST') {
            // Logout
            session_destroy();
            session_start();
            sendJson(true, ['message' => 'Successfully logged out']);
            
        } else {
            sendJson(false, null, ['Invalid auth endpoint'], 404);
        }
        
    } elseif ($resource === 'checklists') {
        ChecklistHandler::handle($action, $method, $pathParts);
        
    } elseif ($resource === 'sections') {
        SectionHandler::handle($action, $method, $pathParts);
        
    } elseif ($resource === 'items') {
        ItemHandler::handle($action, $method, $pathParts);
        
    } elseif ($resource === 'collaborations') {
        CollaborationHandler::handle($action, $method, $pathParts);
        
    } elseif ($resource === 'tags') {
        TagHandler::handle($action, $method, $pathParts);
        
    } elseif ($resource === 'admin') {
        AdminHandler::handle($action, $method, $pathParts);
        
    } elseif ($resource === 'templates') {
        TemplateHandler::handle($action, $method, $pathParts);
        
    } else {
        sendJson(false, null, ['Endpoint not found'], 404);
    }
    
} catch (Exception $e) {
    error_log('API Error: ' . $e->getMessage());
    sendJson(false, null, ['Internal server error'], 500);
}
?>