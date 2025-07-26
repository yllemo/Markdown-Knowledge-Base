<?php
// api/import.php - Import content from ZIP file

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config.php';

// Check authentication
requireAuthentication();

require_once '../classes/FileManager.php';

try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }

    $fileManager = new FileManager('../content');
    $contentDir = '../content';
    
    // Ensure content directory exists
    if (!is_dir($contentDir)) {
        mkdir($contentDir, 0755, true);
    }
    
    // Handle different request types
    $requestType = $_POST['action'] ?? '';
    
    if ($requestType === 'upload') {
        handleUpload($contentDir);
    } elseif ($requestType === 'confirm') {
        handleConfirmImport($contentDir);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function handleUpload($contentDir) {
    // Check if file was uploaded
    if (!isset($_FILES['zipFile']) || $_FILES['zipFile']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['error' => 'No zip file uploaded or upload error']);
        return;
    }
    
    $uploadedFile = $_FILES['zipFile'];
    
    // Validate file type
    $fileInfo = pathinfo($uploadedFile['name']);
    if (strtolower($fileInfo['extension']) !== 'zip') {
        http_response_code(400);
        echo json_encode(['error' => 'Only ZIP files are allowed']);
        return;
    }
    
    // Validate file size (max 50MB)
    if ($uploadedFile['size'] > 50 * 1024 * 1024) {
        http_response_code(400);
        echo json_encode(['error' => 'File too large. Maximum size is 50MB']);
        return;
    }
    
    // Create temporary directory for extraction
    $tempDir = sys_get_temp_dir() . '/kb_import_' . uniqid();
    if (!mkdir($tempDir, 0755, true)) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create temporary directory']);
        return;
    }
    
    // Extract zip file
    $zip = new ZipArchive();
    $result = $zip->open($uploadedFile['tmp_name']);
    
    if ($result !== TRUE) {
        http_response_code(400);
        echo json_encode(['error' => 'Failed to open zip file: ' . $result]);
        return;
    }
    
    // Extract to temporary directory
    $zip->extractTo($tempDir);
    $zip->close();
    
    // Get list of markdown files from extracted content
    $extractedFiles = glob($tempDir . '/*.md');
    $conflicts = [];
    $newFiles = [];
    
    foreach ($extractedFiles as $file) {
        $filename = basename($file);
        $targetPath = $contentDir . '/' . $filename;
        
        if (file_exists($targetPath)) {
            $conflicts[] = [
                'filename' => $filename,
                'existing_size' => filesize($targetPath),
                'existing_modified' => date('Y-m-d H:i:s', filemtime($targetPath)),
                'new_size' => filesize($file),
                'new_modified' => date('Y-m-d H:i:s', filemtime($file))
            ];
        } else {
            $newFiles[] = $filename;
        }
    }
    
    // Store temp directory path for later use
    file_put_contents($tempDir . '/.import_session', json_encode([
        'temp_dir' => $tempDir,
        'content_dir' => $contentDir,
        'extracted_files' => $extractedFiles,
        'timestamp' => time()
    ]));
    
    // Return analysis results
    echo json_encode([
        'success' => true,
        'session_id' => basename($tempDir),
        'total_files' => count($extractedFiles),
        'new_files' => $newFiles,
        'conflicts' => $conflicts,
        'new_count' => count($newFiles),
        'conflict_count' => count($conflicts)
    ]);
}

function handleConfirmImport($contentDir) {
    $sessionId = $_POST['session_id'] ?? '';
    $overwriteAll = $_POST['overwrite_all'] ?? false;
    $removeAllFiles = $_POST['remove_all_files'] ?? false;
    $selectedFilesJson = $_POST['selected_files'] ?? '[]';
    $selectedFiles = json_decode($selectedFilesJson, true) ?: [];
    
    // Debug logging
    error_log("Import debug - Session ID: " . $sessionId);
    error_log("Import debug - Remove all files: " . ($removeAllFiles ? 'true' : 'false'));
    error_log("Import debug - POST data: " . print_r($_POST, true));
    
    if (empty($sessionId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid session ID']);
        return;
    }
    
    $tempDir = sys_get_temp_dir() . '/kb_import_' . $sessionId;
    $sessionFile = $tempDir . '/.import_session';
    
    error_log("Import debug - Temp dir: " . $tempDir);
    error_log("Import debug - Session file: " . $sessionFile);
    error_log("Import debug - Session file exists: " . (file_exists($sessionFile) ? 'yes' : 'no'));
    
    if (!file_exists($sessionFile)) {
        // List all temp directories for debugging
        $tempBasePath = sys_get_temp_dir();
        $existingDirs = glob($tempBasePath . '/kb_import_*', GLOB_ONLYDIR);
        error_log("Import debug - Existing temp dirs: " . print_r($existingDirs, true));
        
        http_response_code(400);
        echo json_encode(['error' => 'Import session expired or invalid. Session ID: ' . $sessionId]);
        return;
    }
    
    $sessionData = json_decode(file_get_contents($sessionFile), true);
    $extractedFiles = $sessionData['extracted_files'];
    
    $imported = [];
    $skipped = [];
    $errors = [];
    $removedFiles = [];
    
    // Remove all existing files if requested
    if ($removeAllFiles === 'true' || $removeAllFiles === true) {
        $existingFiles = glob($contentDir . '/*.md');
        foreach ($existingFiles as $existingFile) {
            if (unlink($existingFile)) {
                $removedFiles[] = basename($existingFile);
            }
        }
    }
    
    foreach ($extractedFiles as $file) {
        $filename = basename($file);
        $targetPath = $contentDir . '/' . $filename;
        
        // Check if we should process this file
        $shouldImport = true;
        
        // If we removed all files, we can import everything without conflict checks
        if (!$removeAllFiles && file_exists($targetPath)) {
            if (!$overwriteAll && !in_array($filename, $selectedFiles)) {
                $shouldImport = false;
                $skipped[] = $filename;
            }
        }
        
        if ($shouldImport) {
            if (copy($file, $targetPath)) {
                $imported[] = $filename;
            } else {
                $errors[] = $filename;
            }
        }
    }
    
    // Clean up temporary directory
    removeDirectory($tempDir);
    
    // Clean up old temp directories (older than 1 hour)
    cleanupOldTempDirs();
    
    echo json_encode([
        'success' => true,
        'imported' => $imported,
        'skipped' => $skipped,
        'errors' => $errors,
        'removed' => $removedFiles,
        'imported_count' => count($imported),
        'skipped_count' => count($skipped),
        'error_count' => count($errors),
        'removed_count' => count($removedFiles)
    ]);
}

function cleanupOldTempDirs() {
    $tempBasePath = sys_get_temp_dir();
    $pattern = $tempBasePath . '/kb_import_*';
    $dirs = glob($pattern, GLOB_ONLYDIR);
    
    foreach ($dirs as $dir) {
        $sessionFile = $dir . '/.import_session';
        if (file_exists($sessionFile)) {
            $sessionData = json_decode(file_get_contents($sessionFile), true);
            if (isset($sessionData['timestamp']) && time() - $sessionData['timestamp'] > 3600) { // 1 hour
                removeDirectory($dir);
            }
        }
    }
}

function removeDirectory($dir) {
    if (!is_dir($dir)) {
        return false;
    }
    
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            removeDirectory($path);
        } else {
            unlink($path);
        }
    }
    return rmdir($dir);
}
?>