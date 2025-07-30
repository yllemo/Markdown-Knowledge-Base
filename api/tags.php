<?php
// api/tags.php - Tags API endpoint

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/config.php';

// Check authentication
requireAuthentication();

require_once '../classes/TagManager.php';

try {
    $contentPath = getCurrentContentPath();
    $tagManager = new TagManager($contentPath);
    $tags = $tagManager->getAllTags();
    
    echo json_encode($tags);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>