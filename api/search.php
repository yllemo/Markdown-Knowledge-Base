<?php
// api/search.php - Search API endpoint

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/config.php';

// Check authentication
requireAuthentication();

require_once '../classes/FileManager.php';
require_once '../classes/SearchEngine.php';

try {
    if (!isset($_GET['q'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing search query']);
        exit;
    }
    
    $query = $_GET['q'];
    $contentPath = getCurrentContentPath();
    $searchEngine = new SearchEngine($contentPath);
    
    // Check if it's a tag search
    if (preg_match('/^tag:(.+)$/', $query, $matches)) {
        $tag = trim($matches[1]);
        $exactMatch = false;
        
        // Check if user wants exact match (quoted tag)
        if (preg_match('/^"(.+)"$/', $tag, $quotedMatches)) {
            $tag = $quotedMatches[1];
            $exactMatch = true;
        }
        
        $fileManager = new FileManager($contentPath);
        $results = $fileManager->getFilesByTag($tag, $exactMatch);
    } else {
        $results = $searchEngine->search($query);
    }
    
    echo json_encode($results);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>