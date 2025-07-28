<?php
// export-test.php - Simplified export test

ob_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

try {
    require_once '../config.php';
    requireAuthentication();
    require_once '../classes/FileManager.php';
    
    // Get current knowledgebase selection and determine export path
    $currentKnowledgebase = getConfig('current_knowledgebase', '');
    
    if (empty($currentKnowledgebase) || $currentKnowledgebase === 'root') {
        $currentContentPath = '../content';
    } else {
        $currentContentPath = '../content/' . $currentKnowledgebase;
    }
    
    // Check if content directory exists
    if (!is_dir($currentContentPath)) {
        throw new Exception('Content directory not found: ' . $currentContentPath);
    }
    
    // Try to get files
    $files = glob($currentContentPath . '/*.md');
    $allFiles = glob($currentContentPath . '/*');
    
    $exportingAllKb = (empty($currentKnowledgebase) || $currentKnowledgebase === 'root');
    
    if ($exportingAllKb) {
        // Also check subdirectories
        $subdirs = glob('../content/*', GLOB_ONLYDIR);
        foreach ($subdirs as $subdir) {
            $subFiles = glob($subdir . '/*.md');
            if ($subFiles) {
                $files = array_merge($files, $subFiles);
            }
        }
    }
    
    $testData = [
        'status' => 'success',
        'current_knowledgebase' => $currentKnowledgebase,
        'content_path' => $currentContentPath,
        'content_dir_exists' => is_dir($currentContentPath),
        'exporting_all_kb' => $exportingAllKb,
        'md_files_found' => count($files),
        'all_files_in_dir' => count($allFiles),
        'md_files' => array_slice($files, 0, 5), // First 5 files
        'all_files' => array_slice($allFiles, 0, 10), // First 10 files
        'zip_extension' => extension_loaded('zip'),
        'temp_dir' => sys_get_temp_dir(),
        'temp_dir_writable' => is_writable(sys_get_temp_dir())
    ];
    
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    echo json_encode($testData, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Error $e) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    http_response_code(500);
    echo json_encode(['error' => 'PHP Error: ' . $e->getMessage()]);
}
?>