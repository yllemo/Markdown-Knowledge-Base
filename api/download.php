<?php
// api/download.php - API endpoint for file downloads

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config.php';

// Check authentication
requireAuthentication();

require_once '../classes/FileManager.php';

$fileManager = new FileManager('../content');

try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }
    
    if (!isset($_GET['file'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing file parameter']);
        exit;
    }
    
    $fileName = $_GET['file'];
    
    // Ensure the file has .md extension
    if (!preg_match('/\.md$/i', $fileName)) {
        $fileName .= '.md';
    }
    
    // Get the file content
    $file = $fileManager->getFile($fileName);
    
    if (!$file) {
        http_response_code(404);
        echo json_encode(['error' => 'File not found']);
        exit;
    }
    
    // Get the display name for the download filename
    $displayName = $file['display_name'] ?? $fileName;
    $downloadFileName = $displayName;
    if (!preg_match('/\.md$/i', $downloadFileName)) {
        $downloadFileName .= '.md';
    }
    
    // Force download as binary for all file types
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $downloadFileName . '"');
    header('Content-Length: ' . strlen($file['content']));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    
    // Output the file content
    echo $file['content'];
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?> 