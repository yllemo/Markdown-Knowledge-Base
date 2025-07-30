<?php
// api/files.php - API endpoint for file operations

// Ensure clean output for JSON
ob_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/config.php';

// Check authentication
requireAuthentication();

try {
    require_once '../classes/FileManager.php';
    
    $contentPath = getCurrentContentPath();
    if (!$contentPath) {
        throw new Exception("Could not determine content path");
    }
    
    $fileManager = new FileManager($contentPath);
    if (!$fileManager) {
        throw new Exception("Could not create FileManager instance");
    }
    
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['error' => 'Initialization error: ' . $e->getMessage()]);
    exit;
}

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
    // Clear any output that might have been generated
    ob_clean();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Error $e) {
    // Catch fatal errors too
    ob_clean();
    http_response_code(500);
    echo json_encode(['error' => 'PHP Error: ' . $e->getMessage()]);
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
            $knowledgebaseContext = $input['knowledgebase_context'] ?? null;
            
            $result = $fileManager->saveFile($fileName, $content, $title, $knowledgebaseContext);
            echo json_encode(['success' => true, 'file' => $result]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}

function handleDelete() {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Debug logging
        error_log("DELETE request received");
        error_log("Raw input: " . file_get_contents('php://input'));
        error_log("Decoded input: " . print_r($input, true));
        
        if (!$input || !isset($input['file'])) {
            error_log("Missing file parameter in delete request");
            http_response_code(400);
            echo json_encode(['error' => 'Missing file name']);
            return;
        }
        
        global $fileManager, $contentPath;
        $fileName = $input['file'];
        
        // Debug logging
        error_log("Delete request for file: " . $fileName);
        error_log("Content path: " . $contentPath);
        error_log("FileManager instance: " . (is_object($fileManager) ? 'exists' : 'null'));
        
        $result = $fileManager->deleteFile($fileName);
        error_log("Delete operation completed successfully");
        echo json_encode(['success' => true]);
        
    } catch (Exception $e) {
        error_log("Delete failed with Exception: " . $e->getMessage());
        error_log("Exception trace: " . $e->getTraceAsString());
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    } catch (Error $e) {
        error_log("Delete failed with PHP Error: " . $e->getMessage());
        error_log("Error trace: " . $e->getTraceAsString());
        http_response_code(500);
        echo json_encode(['error' => 'PHP Error: ' . $e->getMessage()]);
    }
}
?>