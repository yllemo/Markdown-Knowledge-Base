<?php
// config.php - Configuration file for Knowledge Base System

// Site Settings
$config = [
    'site_title' => 'Markdown Knowledge Base (MDKB)',
    'password_protected' => false, // Enable password protection by default
    'password' => 'mdkb', // Default password - change this in setup
    'session_timeout' => 31536000, // 12 months in seconds
    'max_file_size' => 10485760, // 10MB in bytes
    'allowed_file_types' => ['.md', '.txt'],
    'auto_save_interval' => 30000, // 30 seconds in milliseconds
    'theme' => 'dark', // dark or light
    'sidebar_width' => 300, // pixels
    'editor_font_size' => 14, // pixels
    'backup_enabled' => true,
    'backup_interval' => 86400, // 24 hours in seconds
    'max_backups' => 10
];

// Load custom config if exists
$custom_config_file = __DIR__ . '/config.custom.php';
if (file_exists($custom_config_file)) {
    $custom_config = include $custom_config_file;
    $config = array_merge($config, $custom_config);
}

// Helper function to get config value
function getConfig($key, $default = null) {
    global $config;
    return isset($config[$key]) ? $config[$key] : $default;
}

// Helper function to save config
function saveConfig($key, $value) {
    global $config;
    $config[$key] = $value;
    
    // Save to custom config file
    $custom_config_file = __DIR__ . '/config.custom.php';
    $custom_config = "<?php\nreturn " . var_export($config, true) . ";\n";
    
    // Ensure no whitespace or output issues
    $result = file_put_contents($custom_config_file, $custom_config);
    
    // Clear any output buffers to prevent header issues
    if (ob_get_level()) {
        ob_clean();
    }
    
    return $result;
}

// Simple cookie-based authentication functions
function isAuthenticated() {
    // Check if password protection is enabled AND password is set
    if (!getConfig('password_protected') || empty(getConfig('password'))) {
        return true; // No password protection or no password set
    }
    
    // Check for authentication cookie
    $auth_cookie = $_COOKIE['kb_auth'] ?? '';
    $auth_time = $_COOKIE['kb_auth_time'] ?? 0;
    
    if (empty($auth_cookie) || empty($auth_time)) {
        return false; // No authentication cookies found
    }
    
    // Check if cookie has expired
    $session_timeout = getConfig('session_timeout', 3600);
    if ((time() - $auth_time) > $session_timeout) {
        // Clear expired cookies
        setcookie('kb_auth', '', time() - 3600, '/');
        setcookie('kb_auth_time', '', time() - 3600, '/');
        return false;
    }
    
    // Verify the authentication token
    $stored_password = getConfig('password');
    $expected_token = hash('sha256', $stored_password . $auth_time);
    return hash_equals($expected_token, $auth_cookie);
}

function requireAuthentication() {
    if (!isAuthenticated()) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
}

function login($password) {
    $stored_password = getConfig('password');
    
    // Check if password protection is enabled
    if (!getConfig('password_protected') || empty($stored_password)) {
        // Set simple authentication cookies
        $auth_time = time();
        $auth_token = hash('sha256', 'no_password' . $auth_time);
        setcookie('kb_auth', $auth_token, time() + getConfig('session_timeout', 3600), '/');
        setcookie('kb_auth_time', $auth_time, time() + getConfig('session_timeout', 3600), '/');
        return true;
    }
    
    // Verify password (support both hashed and plain text passwords)
    if (password_verify($password, $stored_password) || $password === $stored_password) {
        // Set simple authentication cookies
        $auth_time = time();
        $auth_token = hash('sha256', $stored_password . $auth_time);
        setcookie('kb_auth', $auth_token, time() + getConfig('session_timeout', 3600), '/');
        setcookie('kb_auth_time', $auth_time, time() + getConfig('session_timeout', 3600), '/');
        return true;
    }
    
    return false;
}

function logout() {
    // Clear authentication cookies
    setcookie('kb_auth', '', time() - 3600, '/');
    setcookie('kb_auth_time', '', time() - 3600, '/');
    return true;
}
?> 