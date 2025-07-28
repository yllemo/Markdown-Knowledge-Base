<?php
// test.php - Simple test endpoint to check API basics

// Ensure clean output for JSON
ob_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

try {
    require_once '../config.php';
    
    // Check authentication
    requireAuthentication();
    
    // Test basic functionality
    $testData = [
        'status' => 'success',
        'message' => 'API is working',
        'php_version' => phpversion(),
        'current_time' => date('Y-m-d H:i:s'),
        'current_kb' => getConfig('current_knowledgebase', 'empty'),
        'content_dir_exists' => is_dir('../content'),
        'temp_dir' => sys_get_temp_dir(),
        'zip_extension' => extension_loaded('zip') ? 'available' : 'missing'
    ];
    
    // Clear output buffer and send response
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    echo json_encode($testData, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Test failed: ' . $e->getMessage()]);
} catch (Error $e) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    http_response_code(500);
    echo json_encode(['error' => 'PHP Error: ' . $e->getMessage()]);
}
?>