<?php
// api/settings.php - API endpoint for settings management

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/config.php';

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
        'current_knowledgebase' => getConfig('current_knowledgebase'),
        'knowledgebases' => getAvailableKnowledgebases(),
        'password_protected' => getConfig('password_protected'),
        'session_timeout' => getConfig('session_timeout'),
        'theme' => getConfig('theme'),
        'sidebar_width' => getConfig('sidebar_width'),
        'editor_font_size' => getConfig('editor_font_size'),
        'show_line_numbers' => getConfig('show_line_numbers'),
        'enable_syntax_highlighting' => getConfig('enable_syntax_highlighting'),
        'enable_auto_complete' => getConfig('enable_auto_complete'),
        'auto_save_interval' => getConfig('auto_save_interval'),
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
        case 'create_root':
            handleCreateRoot($input);
            break;

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
                    if ($value < 300 || $value > 31536000) { // 5 minutes to 1 year
                        throw new Exception('Session timeout must be between 5 minutes and 1 year');
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

                case 'current_knowledgebase':
                    if (!is_string($value) || !array_key_exists($value, getAvailableKnowledgebases())) {
                        throw new Exception('Invalid content root selected');
                    }
                    break;

                case 'password_protected':
                    if ($value !== true) throw new Exception('Password protection cannot be disabled');
                    break;

                case 'theme':
                    if (!in_array($value, ['dark', 'light'])) {
                        throw new Exception('Theme must be either "dark" or "light"');
                    }
                    break;

                case 'show_line_numbers':
                case 'enable_syntax_highlighting':
                case 'enable_auto_complete':
                    $value = (bool) $value;
                    break;

                default:
                    // Log unknown settings but don't fail
                    error_log("Unknown setting attempted to save: $key = " . var_export($value, true));
                    throw new Exception("Unknown setting: $key");
                    break;
            }

            if (saveConfig($key, $value)) {
                // Success
            } else {
                throw new Exception("Failed to save setting: $key");
            }

        } catch (Exception $e) {
            $errorMsg = "Setting '$key' failed: " . $e->getMessage();
            $errors[] = $errorMsg;
            error_log($errorMsg); // Log to PHP error log
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

function handleCreateRoot($input) {
    $name = $input['name'] ?? '';
    if (!is_string($name) || !preg_match('/^[\p{L}\p{N}][\p{L}\p{N}_ -]{0,63}$/u', $name) || $name !== trim($name) || strtolower($name) === 'root') {
        http_response_code(400);
        echo json_encode(['error' => 'Use a folder name such as 2026, without slashes or dots.']);
        return;
    }
    $path = dirname(__DIR__) . '/content/' . $name;
    if (file_exists($path) || is_link($path)) {
        http_response_code(400);
        echo json_encode(['error' => 'A folder with that name already exists. Select it from the list.']);
        return;
    }
    if (!mkdir($path, 0755, true)) throw new Exception('Could not create content root');
    echo json_encode(['success' => true, 'name' => $name]);
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