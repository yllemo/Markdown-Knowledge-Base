<?php
// api/files.php - API endpoint for file operations

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config.php';

// Check authentication
requireAuthentication();

require_once '../classes/FileManager.php';

$fileManager = new FileManager('../content');

try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            handleGet();
            break;
            
        case 'POST':
            handlePost();
            break;
            
        case 'DELETE':
            handleDelete();
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
    global $fileManager;
    
    if (isset($_GET['action']) && $_GET['action'] === 'get' && isset($_GET['file'])) {
        // Get specific file content
        $fileName = $_GET['file'];
        $file = $fileManager->getFile($fileName);
        echo json_encode($file);
        
    } elseif (isset($_GET['tag'])) {
        // Get files by tag
        $tag = $_GET['tag'];
        $files = $fileManager->getFilesByTag($tag);
        echo json_encode($files);
        
    } else {
        // Get all files
        $files = $fileManager->getAllFiles();
        echo json_encode($files);
    }
}

function handlePost() {
    global $fileManager;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON input']);
        return;
    }
    
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'save':
            if (!isset($input['file']) || !isset($input['content'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing file name or content']);
                return;
            }
            
            $fileName = $input['file'];
            $content = $input['content'];
            $title = $input['title'] ?? null;
            
            $result = $fileManager->saveFile($fileName, $content, $title);
            echo json_encode(['success' => true, 'file' => $result]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}

function handleDelete() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['file'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing file name']);
        return;
    }
    
    global $fileManager;
    $fileName = $input['file'];
    
    $fileManager->deleteFile($fileName);
    echo json_encode(['success' => true]);
}
?>