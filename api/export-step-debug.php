<?php
// export-step-debug.php - Step by step export debugging

ob_start();
header('Content-Type: application/json');

try {
    require_once '../config/config.php';
    
    $debug = [];
    $debug['step1'] = 'Config loaded';
    
    // Check authentication
    requireAuthentication();
    $debug['step2'] = 'Authentication passed';
    
    require_once '../classes/FileManager.php';
    $debug['step3'] = 'FileManager loaded';
    
    // Get current knowledgebase selection
    $currentKnowledgebase = getConfig('current_knowledgebase', '');
    $debug['current_knowledgebase'] = $currentKnowledgebase;
    $debug['step4'] = 'Got current knowledgebase';
    
    // Determine export path
    if (empty($currentKnowledgebase) || $currentKnowledgebase === 'root') {
        $currentContentPath = '../content';
    } else {
        $currentContentPath = '../content/' . $currentKnowledgebase;
    }
    $debug['content_path'] = $currentContentPath;
    $debug['step5'] = 'Determined content path';
    
    // Check if content directory exists
    if (!is_dir($currentContentPath)) {
        throw new Exception('Content directory not found: ' . $currentContentPath);
    }
    $debug['step6'] = 'Content directory exists';
    
    // Create FileManager
    $fileManager = new FileManager($currentContentPath);
    $debug['step7'] = 'FileManager created';
    
    // Get files
    $files = [];
    $exportingAllKb = (empty($currentKnowledgebase) || $currentKnowledgebase === 'root');
    $debug['exporting_all_kb'] = $exportingAllKb;
    
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
    
    $debug['files_found'] = count($files);
    $debug['first_5_files'] = array_slice($files, 0, 5);
    $debug['step8'] = 'Files collected';
    
    if (empty($files)) {
        throw new Exception('No markdown files found to export');
    }
    
    // Create zip filename
    $date = date('Y-m-d_H-i-s');
    $siteTitle = getConfig('site_title', 'Knowledge Base');
    $safeSiteTitle = preg_replace('/[<>:"|*?\\/\\\\]/', '-', $siteTitle);
    $safeSiteTitle = preg_replace('/\s+/', '-', $safeSiteTitle);
    $safeSiteTitle = preg_replace('/-+/', '-', $safeSiteTitle);
    $safeSiteTitle = trim($safeSiteTitle, '-');
    $safeSiteTitle = mb_strtolower($safeSiteTitle, 'UTF-8');
    
    if (empty($safeSiteTitle)) {
        $safeSiteTitle = 'knowledge-base';
    }
    
    $zipFilename = $exportingAllKb ? 
        "{$safeSiteTitle}-all-knowledgebases_{$date}.zip" :
        "{$safeSiteTitle}-{$currentKnowledgebase}_{$date}.zip";
    
    $zipPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $zipFilename;
    
    $debug['zip_filename'] = $zipFilename;
    $debug['zip_path'] = $zipPath;
    $debug['step9'] = 'Zip path created';
    
    // Initialize zip archive
    $zip = new ZipArchive();
    $result = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    
    $debug['zip_open_result'] = $result;
    $debug['zip_open_success'] = ($result === TRUE);
    $debug['step10'] = 'Zip opened';
    
    if ($result !== TRUE) {
        throw new Exception('Failed to create zip archive: ' . $result);
    }
    
    // Add first file as test
    if (!empty($files)) {
        $firstFile = $files[0];
        if (file_exists($firstFile) && is_readable($firstFile)) {
            $fileContent = file_get_contents($firstFile);
            if ($fileContent !== false) {
                $relativePath = $exportingAllKb ? 
                    str_replace('../content/', '', $firstFile) : 
                    basename($firstFile);
                
                $addResult = $zip->addFromString($relativePath, $fileContent);
                $debug['first_file_added'] = $addResult;
                $debug['first_file_path'] = $relativePath;
                $debug['first_file_size'] = strlen($fileContent);
            }
        }
    }
    $debug['step11'] = 'Test file added';
    
    // Close zip
    $closeResult = $zip->close();
    $debug['zip_close_result'] = $closeResult;
    $debug['step12'] = 'Zip closed';
    
    // Check if zip was created
    $zipExists = file_exists($zipPath);
    $zipSize = $zipExists ? filesize($zipPath) : 0;
    
    $debug['zip_exists'] = $zipExists;
    $debug['zip_size'] = $zipSize;
    $debug['step13'] = 'Zip verified';
    
    // Clean up test zip
    if (file_exists($zipPath)) {
        unlink($zipPath);
        $debug['cleanup'] = 'Test zip deleted';
    }
    
    $debug['final_step'] = 'All steps completed successfully';
    
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    echo json_encode($debug, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    echo json_encode(['error' => $e->getMessage(), 'debug_info' => isset($debug) ? $debug : []]);
} catch (Error $e) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    echo json_encode(['error' => 'PHP Error: ' . $e->getMessage(), 'debug_info' => isset($debug) ? $debug : []]);
}
?>