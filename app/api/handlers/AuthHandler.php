<?php

class AuthHandler {
    public static function handle($action, $method, $uri_parts) {
        switch ($action) {
            case 'google':
                self::handleGoogleAuth($method);
                break;
            case 'me':
                self::handleMe($method);
                break;
            case 'logout':
                self::handleLogout($method);
                break;
            case 'csrf':
                self::handleCsrf($method);
                break;
            default:
                sendJson(false, null, ['Invalid auth action'], 404);
        }
    }

    private static function handleGoogleAuth($method) {
        if ($method !== 'GET') {
            sendJson(false, null, ['Method not allowed'], 405);
        }

        rateLimit('auth_google', 10, 60); // 10 requests per minute
        
        $state = bin2hex(random_bytes(16));
        $_SESSION['oauth_state'] = $state;
        
        $params = [
            'client_id' => GOOGLE_CLIENT_ID,
            'redirect_uri' => GOOGLE_REDIRECT_URI,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'access_type' => 'offline',
            'prompt' => 'consent'
        ];
        
        $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
        
        sendJson(true, ['url' => $authUrl]);
    }

    private static function handleMe($method) {
        if ($method !== 'GET') {
            sendJson(false, null, ['Method not allowed'], 405);
        }

        if (!isset($_SESSION['user_id'])) {
            sendJson(false, null, ['Not authenticated'], 401);
        }

        $pdo = DB::getPDO();
        $stmt = $pdo->prepare("
            SELECT id, email, name, role, created_at 
            FROM users 
            WHERE id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        if (!$user) {
            // User was deleted or deactivated
            session_destroy();
            sendJson(false, null, ['User not found'], 404);
        }

        sendJson(true, [
            'user_id' => $user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'role' => $user['role'],
            'csrf_token' => $_SESSION['csrf'] ?? ''
        ]);
    }

    private static function handleLogout($method) {
        if ($method !== 'POST') {
            sendJson(false, null, ['Method not allowed'], 405);
        }

        $user_id = $_SESSION['user_id'] ?? null;
        
        if ($user_id) {
            logAudit('USER_LOGOUT', ['user_id' => $user_id], $user_id);
        }

        session_destroy();
        session_start(); // Start new clean session
        generateCsrf(); // Generate new CSRF token

        sendJson(true, ['message' => 'Successfully logged out']);
    }

    private static function handleCsrf($method) {
        if ($method !== 'GET') {
            sendJson(false, null, ['Method not allowed'], 405);
        }

        generateCsrf();
        sendJson(true, ['csrf_token' => $_SESSION['csrf']]);
    }
}

?>