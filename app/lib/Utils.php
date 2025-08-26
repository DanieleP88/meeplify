<?php

require_once __DIR__ . '/DB.php';

// Logging function - must be defined before sendJson
function logMessage($level, $message) {
    $date = date('Y-m-d');
    $logDir = __DIR__ . '/../../logs';
    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/' . $date . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $userId = $_SESSION['user_id'] ?? 'anonymous';
    $ip = getRealIpAddress();
    
    $formatted = "[$timestamp] [$level] [User:$userId] [IP:$ip] $message" . PHP_EOL;
    
    // Atomic write
    file_put_contents($logFile, $formatted, FILE_APPEND | LOCK_EX);
}

// IP helper function for logging
function getRealIpAddress() {
    $headers = [
        'HTTP_CF_CONNECTING_IP',     // Cloudflare
        'HTTP_CLIENT_IP',            // Proxy
        'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
        'HTTP_X_FORWARDED',          // Proxy
        'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
        'HTTP_FORWARDED_FOR',        // Proxy
        'HTTP_FORWARDED',            // Proxy
        'REMOTE_ADDR'                // Standard
    ];
    
    foreach ($headers as $header) {
        if (isset($_SERVER[$header]) && !empty($_SERVER[$header])) {
            $ips = explode(',', $_SERVER[$header]);
            $ip = trim($ips[0]);
            
            // Validate IP address
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
            
            // Allow private ranges in development
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    
    return '0.0.0.0';
}

function sendJson($success, $data = null, $errors = [], $status = 200) {
    http_response_code($status);
    if (!$success && $status >= 400) {
        logMessage('ERROR', json_encode($errors));
    }
    
    // Security headers
    header('Content-Type: application/json');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    echo json_encode(['success' => $success, 'data' => $data, 'errors' => $errors]);
    exit;
}

function getInput() {
    $input = file_get_contents('php://input');
    $decoded = json_decode($input, true);
    
    // Additional JSON validation
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendJson(false, null, ['Invalid JSON format'], 400);
    }
    
    return $decoded ?? [];
}

function checkAuth() {
    if (!isset($_SESSION['user_id'])) {
        sendJson(false, null, ['Unauthorized'], 401);
    }
    return $_SESSION['user_id'];
}

function isAdmin($user_id) {
    static $adminCache = [];
    
    if (isset($adminCache[$user_id])) {
        return $adminCache[$user_id];
    }
    
    $pdo = DB::getPDO();
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $role = $stmt->fetchColumn();
    
    $adminCache[$user_id] = ($role === 'admin');
    return $adminCache[$user_id];
}

function getChecklistRole($checklist_id, $user_id) {
    static $roleCache = [];
    $cacheKey = $checklist_id . '_' . $user_id;
    
    if (isset($roleCache[$cacheKey])) {
        return $roleCache[$cacheKey];
    }
    
    $pdo = DB::getPDO();
    
    // Check if user is owner
    $stmt = $pdo->prepare("SELECT owner_id FROM checklists WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$checklist_id]);
    $owner = $stmt->fetchColumn();
    
    if ($owner === $user_id) {
        $roleCache[$cacheKey] = 'owner';
        return 'owner';
    }
    
    // Check collaborator role
    $stmt = $pdo->prepare("SELECT role FROM collaborators WHERE checklist_id = ? AND user_id = ?");
    $stmt->execute([$checklist_id, $user_id]);
    $role = $stmt->fetchColumn();
    
    $roleCache[$cacheKey] = $role ?: false;
    return $roleCache[$cacheKey];
}

function checkPermission($checklist_id, $user_id, $required) {
    // Admin users have full access
    if (isAdmin($user_id)) {
        return true;
    }
    
    $role = getChecklistRole($checklist_id, $user_id);
    
    // Role hierarchy: viewer < collaborator < owner
    $roles = ['viewer' => 1, 'collaborator' => 2, 'owner' => 3];
    
    if (!$role || !isset($roles[$role]) || $roles[$role] < $roles[$required]) {
        sendJson(false, null, ['Forbidden'], 403);
    }
    
    return true;
}

function checkCsrf() {
    if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE'])) {
        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if ($csrf !== ($_SESSION['csrf'] ?? '')) {
            logMessage('SECURITY', 'CSRF validation failed for user: ' . ($_SESSION['user_id'] ?? 'anonymous'));
            sendJson(false, null, ['CSRF validation failed'], 403);
        }
    }
}

function generateCsrf() {
    if (!isset($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
}

function rateLimit($key, $limit = 60, $period = 60) {
    $now = time();
    $rateKey = 'rate_' . $key;
    
    if (!isset($_SESSION[$rateKey])) {
        $_SESSION[$rateKey] = ['count' => 1, 'time' => $now];
        return;
    }
    
    $rateData = $_SESSION[$rateKey];
    
    // Reset counter if period has passed
    if ($now - $rateData['time'] > $period) {
        $_SESSION[$rateKey] = ['count' => 1, 'time' => $now];
        return;
    }
    
    // Increment counter
    $_SESSION[$rateKey]['count']++;
    
    if ($_SESSION[$rateKey]['count'] > $limit) {
        logMessage('SECURITY', "Rate limit exceeded for key: $key, user: " . ($_SESSION['user_id'] ?? 'anonymous'));
        sendJson(false, null, ['Rate limit exceeded'], 429);
    }
}

function logAudit($event_type, $details = [], $user_id = null) {
    $user_id = $user_id ?? ($_SESSION['user_id'] ?? null);
    $pdo = DB::getPDO();
    
    // Ensure audit_log table exists
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS audit_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_type VARCHAR(255) NOT NULL,
            user_id INT,
            details JSON,
            ip_address VARCHAR(45),
            user_agent VARCHAR(500),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_event_type (event_type),
            INDEX idx_user_id (user_id),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    } catch (PDOException $e) {
        // Table creation failed, but don't break the flow
        logMessage('ERROR', 'Failed to create audit_log table: ' . $e->getMessage());
    }
    
    // Add contextual information
    $details['ip_address'] = getRealIpAddress();
    $details['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $details['request_method'] = $_SERVER['REQUEST_METHOD'] ?? '';
    $details['request_uri'] = $_SERVER['REQUEST_URI'] ?? '';
    
    try {
        $stmt = $pdo->prepare("INSERT INTO audit_log (event_type, user_id, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $event_type, 
            $user_id, 
            json_encode($details),
            getRealIpAddress(),
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500)
        ]);
    } catch (PDOException $e) {
        logMessage('ERROR', 'Failed to log audit event: ' . $e->getMessage());
    }
}



function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($email) <= 255;
}

function sanitizeEmail($email) {
    $email = trim(strtolower($email));
    return validateEmail($email) ? $email : '';
}


function sanitizeString($input, $maxLength = null) {
    $sanitized = trim($input);
    $sanitized = strip_tags($sanitized);
    
    if ($maxLength && strlen($sanitized) > $maxLength) {
        $sanitized = substr($sanitized, 0, $maxLength);
    }
    
    return $sanitized;
}

function validateId($id) {
    return is_numeric($id) && $id > 0 && $id <= PHP_INT_MAX;
}

function validateDateFormat($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

function validateColorHex($color) {
    return preg_match('/^#[0-9A-Fa-f]{6}$/', $color);
}

function enforceHttps() {
    if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
        if (!in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1'])) {
            $redirectURL = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            header("Location: $redirectURL", true, 301);
            exit;
        }
    }
}

function validateUserAgent() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Block empty user agents
    if (empty($userAgent)) {
        logMessage('SECURITY', 'Blocked request with empty user agent');
        sendJson(false, null, ['Invalid request'], 403);
    }
    
    // Block suspicious patterns
    $suspiciousPatterns = [
        '/bot/i',
        '/crawler/i',
        '/spider/i',
        '/scraper/i',
        '/wget/i',
        '/curl/i'
    ];
    
    foreach ($suspiciousPatterns as $pattern) {
        if (preg_match($pattern, $userAgent)) {
            logMessage('SECURITY', "Blocked suspicious user agent: $userAgent");
            sendJson(false, null, ['Forbidden'], 403);
        }
    }
}

function checkMaintenanceMode() {
    $maintenanceFile = __DIR__ . '/../../.maintenance';
    
    if (file_exists($maintenanceFile)) {
        // Allow admin access during maintenance
        if (isset($_SESSION['user_id']) && isAdmin($_SESSION['user_id'])) {
            return;
        }
        
        http_response_code(503);
        header('Retry-After: 3600'); // 1 hour
        sendJson(false, null, ['Service temporarily unavailable for maintenance'], 503);
    }
}

function cleanupSessions() {
    // Clean up old rate limit data (older than 1 hour)
    $now = time();
    foreach ($_SESSION as $key => $value) {
        if (strpos($key, 'rate_') === 0 && is_array($value)) {
            if (isset($value['time']) && ($now - $value['time']) > 3600) {
                unset($_SESSION[$key]);
            }
        }
    }
}

// Security middleware functions
function applySecurityHeaders() {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\'; style-src \'self\' \'unsafe-inline\';');
}

function preventClickjacking() {
    header('X-Frame-Options: DENY');
    header('Content-Security-Policy: frame-ancestors \'none\';');
}

// Initialize security measures
function initializeSecurity() {
    // Start with security headers
    applySecurityHeaders();
    
    // Check for maintenance mode
    checkMaintenanceMode();
    
    // Validate user agent for API endpoints
    if (strpos($_SERVER['REQUEST_URI'], '/api/') === 0) {
        validateUserAgent();
    }
    
    // Clean up old session data
    cleanupSessions();
    
    // Generate CSRF token if needed
    generateCsrf();
}

?>