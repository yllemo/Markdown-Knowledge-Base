<?php
// api/load.php - API endpoint for loading .md files

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/config.php';

// Check authentication
requireAuthentication();

require_once '../classes/FileManager.php';

$fileManager = new FileManager('../content');

try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }
    
    handleLoad();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function handleLoad() {
    global $fileManager;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON input']);
        return;
    }
    
    if (!isset($input['fileName']) || !isset($input['content'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing file name or content']);
        return;
    }
    
    $fileName = $input['fileName'];
    $content = $input['content'];
    $title = $input['title'] ?? null;
    
    // Validate file name - allow more characters for better compatibility
    if (!preg_match('/^[a-zA-Z0-9._\-\s]+\.(md|markdown)$/', $fileName)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid file name. Only .md and .markdown files are allowed.']);
        return;
    }
    
    try {
        $result = $fileManager->saveFile($fileName, $content, $title);
        echo json_encode(['success' => true, 'file' => $result]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?> 