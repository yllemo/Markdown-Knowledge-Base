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
    } elseif ($requestType === 'direct_import') {
        handleDirectImport($contentDir);
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
    $knowledgebaseName = $_POST['knowledgebase_name'] ?? '';
    
    // Validate file type
    $fileInfo = pathinfo($uploadedFile['name']);
    if (strtolower($fileInfo['extension']) !== 'zip') {
        http_response_code(400);
        echo json_encode(['error' => 'Only ZIP files are allowed']);
        return;
    }
    
    // Use filename as default knowledgebase name if not provided
    if (empty($knowledgebaseName)) {
        $knowledgebaseName = pathinfo($uploadedFile['name'], PATHINFO_FILENAME);
    }
    
    // Sanitize knowledgebase name
    $knowledgebaseName = sanitizeKnowledgebaseName($knowledgebaseName);
    
    // Validate file size (max 50MB)
    if ($uploadedFile['size'] > 50 * 1024 * 1024) {
        http_response_code(400);
        echo json_encode(['error' => 'File too large. Maximum size is 50MB']);
        return;
    }
    
    // Create temporary directory for extraction
    // Try multiple temp directory options for web servers (prioritize /tmp for cgi-fcgi)
    $possibleTempDirs = [
        '/tmp',  // Most common for cgi-fcgi
        sys_get_temp_dir(),
        '../temp',
        './temp',
        ini_get('upload_tmp_dir')
    ];
    
    $sysTempDir = null;
    foreach ($possibleTempDirs as $dir) {
        if ($dir && is_dir($dir) && is_writable($dir)) {
            $sysTempDir = $dir;
            break;
        }
    }
    
    // If no temp dir found, create one
    if (!$sysTempDir) {
        $sysTempDir = '../temp';
        if (!is_dir($sysTempDir)) {
            mkdir($sysTempDir, 0755, true);
        }
    }
    
    $tempDir = $sysTempDir . '/kb_import_' . uniqid();
    
    
    // Ensure the temp directory is writable
    if (!is_writable($sysTempDir)) {
        http_response_code(500);
        echo json_encode(['error' => 'No writable temp directory found. Tried: ' . implode(', ', $possibleTempDirs)]);
        return;
    }
    
    if (!mkdir($tempDir, 0755, true)) {
        $error = error_get_last();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create temporary directory: ' . $tempDir . ' - ' . ($error['message'] ?? 'Unknown error')]);
        return;
    }
    
    
    // Copy uploaded file to our temp directory first (web servers may clean up uploaded files quickly)
    $permanentZipPath = $tempDir . '/uploaded.zip';
    if (!move_uploaded_file($uploadedFile['tmp_name'], $permanentZipPath)) {
        // Fallback: try copy if move fails
        if (!copy($uploadedFile['tmp_name'], $permanentZipPath)) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to save uploaded file to temp directory']);
            return;
        }
    }
    
    
    // Extract zip file
    $zip = new ZipArchive();
    $result = $zip->open($permanentZipPath);
    
    if ($result !== TRUE) {
        http_response_code(400);
        echo json_encode(['error' => 'Failed to open zip file: ' . $result]);
        return;
    }
    
    // Extract to temporary directory
    $zip->extractTo($tempDir);
    $zip->close();
    
    
    // Create knowledgebase directory if it doesn't exist
    $targetDir = $contentDir . '/' . $knowledgebaseName;
    if (!is_dir($targetDir)) {
        if (!mkdir($targetDir, 0755, true)) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create knowledgebase directory: ' . $knowledgebaseName]);
            return;
        }
    }
    
    // Get list of markdown files from extracted content
    $extractedFiles = glob($tempDir . '/*.md');
    $conflicts = [];
    $newFiles = [];
    
    foreach ($extractedFiles as $file) {
        $filename = basename($file);
        $targetPath = $targetDir . '/' . $filename;
        
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
    $sessionData = json_encode([
        'temp_dir' => $tempDir,
        'content_dir' => $contentDir,
        'target_dir' => $targetDir,
        'knowledgebase_name' => $knowledgebaseName,
        'extracted_files' => $extractedFiles,
        'zip_file' => $permanentZipPath,
        'timestamp' => time(),
        'php_sapi' => php_sapi_name(),
        'session_timeout' => 3600  // 1 hour timeout for web servers
    ]);
    
    $sessionFile = $tempDir . '/.import_session';
    
    $result = file_put_contents($sessionFile, $sessionData);
    if ($result === false) {
        $error = error_get_last();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create import session file: ' . ($error['message'] ?? 'Unknown error')]);
        return;
    }
    
    
    // Return analysis results - extract just the unique ID part
    $sessionId = str_replace('kb_import_', '', basename($tempDir));
    echo json_encode([
        'success' => true,
        'session_id' => $sessionId,
        'knowledgebase_name' => $knowledgebaseName,
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
    
    if (empty($sessionId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid session ID']);
        return;
    }
    
    // Find the temp directory using the same logic as upload (prioritize /tmp for cgi-fcgi)
    $possibleTempDirs = [
        '/tmp',  // Most common for cgi-fcgi
        sys_get_temp_dir(),
        '../temp',
        './temp',
        ini_get('upload_tmp_dir')
    ];
    
    $sysTempDir = null;
    $tempDir = null;
    $sessionFile = null;
    
    // Try to find the session in any of the possible temp directories
    foreach ($possibleTempDirs as $dir) {
        if ($dir && is_dir($dir)) {
            // Handle both cases: plain ID and ID that already has kb_import_ prefix
            $cleanSessionId = str_replace('kb_import_', '', $sessionId);
            $candidateTempDir = $dir . '/kb_import_' . $cleanSessionId;
            $candidateSessionFile = $candidateTempDir . '/.import_session';
            
            if (file_exists($candidateSessionFile)) {
                // Verify session file is readable and valid
                $sessionContent = @file_get_contents($candidateSessionFile);
                if ($sessionContent !== false) {
                    $testData = @json_decode($sessionContent, true);
                    if (is_array($testData) && isset($testData['timestamp'])) {
                        // Check if session is not expired (use session_timeout or default 1 hour)
                        $timeout = isset($testData['session_timeout']) ? $testData['session_timeout'] : 3600;
                        if (time() - $testData['timestamp'] <= $timeout) {
                            $sysTempDir = $dir;
                            $tempDir = $candidateTempDir;
                            $sessionFile = $candidateSessionFile;
                            break;
                        } else {
                            $elapsed = time() - $testData['timestamp'];
                        }
                    } else {
                    }
                } else {
                }
            }
        }
    }
    
    
    if ($tempDir && is_dir($tempDir)) {
        $files = scandir($tempDir);
    }
    
    if (!file_exists($sessionFile)) {
        // Extended search for session files across all possible temp locations
        $allSearchPaths = [];
        $foundSessions = [];
        
        foreach ($possibleTempDirs as $searchDir) {
            if ($searchDir && is_dir($searchDir)) {
                $pattern = $searchDir . '/kb_import_*';
                $existingDirs = glob($pattern, GLOB_ONLYDIR);
                $allSearchPaths[] = $pattern;
                
                foreach ($existingDirs as $dir) {
                    $sessionInDir = $dir . '/.import_session';
                    if (file_exists($sessionInDir)) {
                        $foundSessions[] = $sessionInDir;
                    }
                }
            }
        }
        
        
        // Clean up any old temp directories
        cleanupOldTempDirs();
        
        http_response_code(400);
        echo json_encode([
            'error' => 'Import session expired or invalid. Please try uploading the file again.',
            'debug_info' => [
                'session_id' => $sessionId,
                'searched_patterns' => $allSearchPaths,
                'found_sessions' => count($foundSessions),
                'php_sapi' => php_sapi_name(),
                'upload_tmp_dir' => ini_get('upload_tmp_dir') ?: 'not_set'
            ]
        ]);
        return;
    }
    
    $sessionData = json_decode(file_get_contents($sessionFile), true);
    
    if (!$sessionData || !isset($sessionData['extracted_files']) || !isset($sessionData['target_dir'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid session data. Please try uploading the file again.']);
        return;
    }
    
    $extractedFiles = $sessionData['extracted_files'];
    $targetDir = $sessionData['target_dir'];
    $knowledgebaseName = $sessionData['knowledgebase_name'];
    
    $imported = [];
    $skipped = [];
    $errors = [];
    $removedFiles = [];
    
    // Remove all existing files if requested
    if ($removeAllFiles === 'true' || $removeAllFiles === true) {
        $existingFiles = glob($targetDir . '/*.md');
        foreach ($existingFiles as $existingFile) {
            if (unlink($existingFile)) {
                $removedFiles[] = basename($existingFile);
            }
        }
    }
    
    foreach ($extractedFiles as $file) {
        $filename = basename($file);
        $targetPath = $targetDir . '/' . $filename;
        
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

function handleDirectImport($contentDir) {
    // Direct import that bypasses session system for web servers with short timeouts
    
    // Check if file was uploaded
    if (!isset($_FILES['zipFile']) || $_FILES['zipFile']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['error' => 'No zip file uploaded or upload error']);
        return;
    }
    
    $uploadedFile = $_FILES['zipFile'];
    $knowledgebaseName = $_POST['knowledgebase_name'] ?? '';
    $overwriteAll = $_POST['overwrite_all'] ?? false;
    $removeAllFiles = $_POST['remove_all_files'] ?? false;
    
    // Validate file type
    $fileInfo = pathinfo($uploadedFile['name']);
    if (strtolower($fileInfo['extension']) !== 'zip') {
        http_response_code(400);
        echo json_encode(['error' => 'Only ZIP files are allowed']);
        return;
    }
    
    // Use filename as default knowledgebase name if not provided
    if (empty($knowledgebaseName)) {
        $knowledgebaseName = pathinfo($uploadedFile['name'], PATHINFO_FILENAME);
    }
    
    // Sanitize knowledgebase name
    $knowledgebaseName = sanitizeKnowledgebaseName($knowledgebaseName);
    
    // Create temporary directory
    $tempDir = '/tmp/kb_direct_import_' . uniqid();
    if (!mkdir($tempDir, 0755, true)) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create temporary directory']);
        return;
    }
    
    
    try {
        // Extract zip file directly
        $zip = new ZipArchive();
        $result = $zip->open($uploadedFile['tmp_name']);
        
        if ($result !== TRUE) {
            removeDirectory($tempDir);
            http_response_code(400);
            echo json_encode(['error' => 'Failed to open zip file: ' . $result]);
            return;
        }
        
        $zip->extractTo($tempDir);
        $zip->close();
        
        // Create knowledgebase directory
        $targetDir = $contentDir . '/' . $knowledgebaseName;
        if (!is_dir($targetDir)) {
            if (!mkdir($targetDir, 0755, true)) {
                removeDirectory($tempDir);
                http_response_code(500);
                echo json_encode(['error' => 'Failed to create knowledgebase directory: ' . $knowledgebaseName]);
                return;
            }
        }
        
        // Remove all existing files if requested
        if ($removeAllFiles === 'true' || $removeAllFiles === true) {
            $existingFiles = glob($targetDir . '/*.md');
            foreach ($existingFiles as $existingFile) {
                unlink($existingFile);
            }
        }
        
        // Get markdown files and import them
        $extractedFiles = glob($tempDir . '/*.md');
        $imported = [];
        $skipped = [];
        $errors = [];
        
        foreach ($extractedFiles as $file) {
            $filename = basename($file);
            $targetPath = $targetDir . '/' . $filename;
            
            if (file_exists($targetPath) && !$overwriteAll) {
                $skipped[] = $filename;
                continue;
            }
            
            if (copy($file, $targetPath)) {
                $imported[] = $filename;
            } else {
                $errors[] = $filename;
            }
        }
        
        // Clean up temp directory
        removeDirectory($tempDir);
        
        
        echo json_encode([
            'success' => true,
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
            'imported_count' => count($imported),
            'skipped_count' => count($skipped),
            'error_count' => count($errors),
            'knowledgebase_name' => $knowledgebaseName
        ]);
        
    } catch (Exception $e) {
        removeDirectory($tempDir);
        http_response_code(500);
        echo json_encode(['error' => 'Import failed: ' . $e->getMessage()]);
    }
}

function cleanupOldTempDirs() {
    try {
        $possibleTempDirs = [
            '/tmp',  // Most common for cgi-fcgi
            sys_get_temp_dir(),
            '../temp',
            './temp',
            ini_get('upload_tmp_dir')
        ];
        
        foreach ($possibleTempDirs as $tempBasePath) {
            if (!$tempBasePath || !is_dir($tempBasePath)) {
                continue;
            }
            
            $pattern = $tempBasePath . '/kb_import_*';
            $dirs = glob($pattern, GLOB_ONLYDIR);
            
            if (!$dirs) {
                continue; // No directories to clean in this location
            }
            
            foreach ($dirs as $dir) {
                try {
                    $sessionFile = $dir . '/.import_session';
                    if (file_exists($sessionFile)) {
                        $sessionData = json_decode(file_get_contents($sessionFile), true);
                        // Increased timeout to 24 hours to prevent premature cleanup
                        if (isset($sessionData['timestamp']) && time() - $sessionData['timestamp'] > 86400) { // 24 hours
                            removeDirectory($dir);
                        }
                    } else {
                        // No session file, probably an old broken directory - remove it
                        if (time() - filemtime($dir) > 86400) { // 24 hours old
                            removeDirectory($dir);
                        }
                    }
                } catch (Exception $e) {
                }
            }
        }
    } catch (Exception $e) {
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

function sanitizeKnowledgebaseName($name) {
    // Remove unsafe characters and convert to lowercase
    $name = preg_replace('/[^a-zA-Z0-9\-_\s]/', '', $name);
    $name = preg_replace('/\s+/', '-', trim($name));
    $name = preg_replace('/-+/', '-', $name);
    $name = trim($name, '-');
    $name = strtolower($name);
    
    // Ensure it's not empty
    if (empty($name)) {
        $name = 'knowledgebase-' . time();
    }
    
    return $name;
}
?>