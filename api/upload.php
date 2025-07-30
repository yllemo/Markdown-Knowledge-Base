<?php
// api/upload.php - API endpoint for file uploads (favicon, header icon)

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/config.php';

// Check authentication
requireAuthentication();

try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }
    
    handleUpload();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function handleUpload() {
    // Debug logging
    error_log("Upload request received");
    error_log("POST data: " . print_r($_POST, true));
    error_log("FILES data: " . print_r($_FILES, true));
    
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        error_log("Upload error: " . ($_FILES['file']['error'] ?? 'No file'));
        http_response_code(400);
        echo json_encode(['error' => 'No file uploaded or upload error']);
        return;
    }
    
    $file = $_FILES['file'];
    $type = $_POST['type'] ?? '';
    
    // Validate upload type
    if (!in_array($type, ['favicon', 'header_icon'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid upload type']);
        return;
    }
    
    // Validate file type
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/svg+xml', 'image/x-icon'];
    $file_type = mime_content_type($file['tmp_name']);
    
    if (!in_array($file_type, $allowed_types)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid file type. Only images are allowed.']);
        return;
    }
    
    // Validate file size (max 2MB)
    if ($file['size'] > 2 * 1024 * 1024) {
        http_response_code(400);
        echo json_encode(['error' => 'File size too large. Maximum 2MB allowed.']);
        return;
    }
    
    // Create uploads directory if it doesn't exist
    $uploads_dir = __DIR__ . '/../uploads';
    if (!is_dir($uploads_dir)) {
        if (!mkdir($uploads_dir, 0755, true)) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create uploads directory']);
            return;
        }
    }
    
    // Generate filename based on type
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = $type . '.' . $extension;
    $filepath = $uploads_dir . '/' . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save uploaded file']);
        return;
    }
    
    // Save the file path to config
    $config_key = $type . '_path';
    error_log("Saving config key: $config_key with value: uploads/$filename");
    
    if (saveConfig($config_key, 'uploads/' . $filename)) {
        error_log("Config saved successfully");
        echo json_encode([
            'success' => true,
            'message' => ucfirst(str_replace('_', ' ', $type)) . ' uploaded successfully',
            'file_path' => 'uploads/' . $filename
        ]);
    } else {
        error_log("Failed to save config");
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save configuration']);
    }
}
?> 