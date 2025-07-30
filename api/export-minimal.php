<?php
// export-minimal.php - Minimal export without complex functions

ob_start();

try {
    require_once '../config/config.php';
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
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
        http_response_code(404);
        echo json_encode(['error' => 'Content directory not found: ' . $currentContentPath]);
        exit;
    }
    
    // Get files
    $files = [];
    $exportingAllKb = (empty($currentKnowledgebase) || $currentKnowledgebase === 'root');
    
    if ($exportingAllKb) {
        // Get files from root
        $rootFiles = glob('../content/*.md');
        if ($rootFiles) {
            $files = array_merge($files, $rootFiles);
        }
        
        // Get files from subdirectories
        $subdirs = glob('../content/*', GLOB_ONLYDIR);
        foreach ($subdirs as $subdir) {
            $subFiles = glob($subdir . '/*.md');
            if ($subFiles) {
                $files = array_merge($files, $subFiles);
            }
        }
    } else {
        $files = glob($currentContentPath . '/*.md');
    }
    
    if (empty($files)) {
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
        http_response_code(404);
        echo json_encode(['error' => 'No markdown files found to export']);
        exit;
    }
    
    // Create zip file
    $date = date('Y-m-d_H-i-s');
    $zipFilename = "knowledge-base-export_{$date}.zip";
    $zipPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $zipFilename;
    
    // Initialize zip archive
    $zip = new ZipArchive();
    $result = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    
    if ($result !== TRUE) {
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create zip archive: ' . $result]);
        exit;
    }
    
    // Add all markdown files to zip
    $fileCount = 0;
    
    foreach ($files as $file) {
        if (file_exists($file) && is_readable($file)) {
            $fileContent = file_get_contents($file);
            
            if ($fileContent !== false) {
                // Determine filename/path in zip
                if ($exportingAllKb) {
                    // For all knowledgebases export, preserve folder structure
                    $relativePath = str_replace('../content/', '', $file);
                    $filePathInZip = $relativePath;
                } else {
                    // For single knowledgebase export, use just filename
                    $filePathInZip = basename($file);
                }
                
                $zip->addFromString($filePathInZip, $fileContent);
                $fileCount++;
            }
        }
    }
    
    // Add simple metadata
    $metadata = json_encode([
        'export_date' => date('Y-m-d H:i:s'),
        'file_count' => $fileCount,
        'source' => 'Knowledge Base System'
    ], JSON_PRETTY_PRINT);
    
    $zip->addFromString('export_info.json', $metadata);
    
    // Close zip file
    $closeResult = $zip->close();
    
    if (!$closeResult) {
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['error' => 'Failed to finalize zip file']);
        exit;
    }
    
    // Check if zip was created successfully
    if (!file_exists($zipPath) || filesize($zipPath) == 0) {
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create zip file or file is empty']);
        exit;
    }
    
    // Clear any output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Set headers for file download
    $fileSize = filesize($zipPath);
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zipFilename . '"');
    header('Content-Length: ' . $fileSize);
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Output file
    readfile($zipPath);
    
    // Clean up
    unlink($zipPath);
    exit;
    
} catch (Exception $e) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Error $e) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'PHP Error: ' . $e->getMessage()]);
}
?>