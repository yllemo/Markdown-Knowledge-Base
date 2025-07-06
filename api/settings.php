<?php
// api/settings.php - API endpoint for settings management

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config.php';

// Check authentication
requireAuthentication();

try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            handleGet();
            break;
            
        case 'POST':
            handlePost();
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function handleGet() {
    // Return current settings
    $settings = [
        'site_title' => getConfig('site_title'),
        'password_protected' => getConfig('password_protected'),
        'session_timeout' => getConfig('session_timeout'),
        'theme' => getConfig('theme'),
        'sidebar_width' => getConfig('sidebar_width'),
        'editor_font_size' => getConfig('editor_font_size'),
        'show_line_numbers' => getConfig('show_line_numbers'),
        'enable_syntax_highlighting' => getConfig('enable_syntax_highlighting'),
        'enable_auto_complete' => getConfig('enable_auto_complete'),
        'auto_save_interval' => getConfig('auto_save_interval'),
        'backup_enabled' => getConfig('backup_enabled'),
        'backup_interval' => getConfig('backup_interval'),
        'max_backups' => getConfig('max_backups'),
        'favicon_path' => getConfig('favicon_path'),
        'header_icon_path' => getConfig('header_icon_path')
    ];
    
    echo json_encode($settings);
}

function handlePost() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON input']);
        return;
    }
    
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'update':
            handleUpdate($input);
            break;
            
        case 'change_password':
            handlePasswordChange($input);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}

function handleUpdate($input) {
    $updates = $input['settings'] ?? [];
    $success = true;
    $errors = [];
    
    foreach ($updates as $key => $value) {
        try {
            // Validate and sanitize values
            switch ($key) {
                case 'site_title':
                    if (empty(trim($value))) {
                        throw new Exception('Site title cannot be empty');
                    }
                    $value = trim($value);
                    break;
                    
                case 'session_timeout':
                    $value = intval($value);
                    if ($value < 300 || $value > 86400) { // 5 minutes to 24 hours
                        throw new Exception('Session timeout must be between 5 minutes and 24 hours');
                    }
                    break;
                    
                case 'sidebar_width':
                    $value = intval($value);
                    if ($value < 200 || $value > 500) {
                        throw new Exception('Sidebar width must be between 200 and 500 pixels');
                    }
                    break;
                    
                case 'editor_font_size':
                    $value = intval($value);
                    if ($value < 10 || $value > 24) {
                        throw new Exception('Editor font size must be between 10 and 24 pixels');
                    }
                    break;
                    
                case 'auto_save_interval':
                    $value = intval($value);
                    if ($value < 5000 || $value > 300000) { // 5 seconds to 5 minutes
                        throw new Exception('Auto-save interval must be between 5 seconds and 5 minutes');
                    }
                    break;
                    
                case 'backup_interval':
                    $value = intval($value);
                    if ($value < 3600 || $value > 604800) { // 1 hour to 1 week
                        throw new Exception('Backup interval must be between 1 hour and 1 week');
                    }
                    break;
                    
                case 'max_backups':
                    $value = intval($value);
                    if ($value < 1 || $value > 100) {
                        throw new Exception('Max backups must be between 1 and 100');
                    }
                    break;
                    
                case 'theme':
                    if (!in_array($value, ['dark', 'light'])) {
                        throw new Exception('Theme must be either "dark" or "light"');
                    }
                    break;
                    
                case 'show_line_numbers':
                case 'enable_syntax_highlighting':
                case 'enable_auto_complete':
                case 'backup_enabled':
                case 'password_protected':
                    $value = (bool) $value;
                    break;
            }
            
            if (saveConfig($key, $value)) {
                // Success
            } else {
                throw new Exception("Failed to save setting: $key");
            }
            
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
            $success = false;
        }
    }
    
    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Settings updated successfully']);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Some settings could not be updated', 'details' => $errors]);
    }
}

function handlePasswordChange($input) {
    $current_password = $input['current_password'] ?? '';
    $new_password = $input['new_password'] ?? '';
    $confirm_password = $input['confirm_password'] ?? '';
    
    // Validate input
    if (empty($new_password)) {
        http_response_code(400);
        echo json_encode(['error' => 'New password cannot be empty']);
        return;
    }
    
    if ($new_password !== $confirm_password) {
        http_response_code(400);
        echo json_encode(['error' => 'New passwords do not match']);
        return;
    }
    
    if (strlen($new_password) < 6) {
        http_response_code(400);
        echo json_encode(['error' => 'Password must be at least 6 characters long']);
        return;
    }
    
    // Check current password if password protection is enabled
    $stored_password = getConfig('password');
    if (getConfig('password_protected') && !empty($stored_password)) {
        if (!password_verify($current_password, $stored_password) && $current_password !== $stored_password) {
            http_response_code(400);
            echo json_encode(['error' => 'Current password is incorrect']);
            return;
        }
    }
    
    // Hash the new password
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    // Save the new password
    if (saveConfig('password', $hashed_password)) {
        echo json_encode(['success' => true, 'message' => 'Password updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update password']);
    }
}
?> 