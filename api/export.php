<?php
// api/export.php - Export all content as ZIP file

// Start output buffering to capture any unwanted output
ob_start();

require_once '../config.php';

// Check authentication
requireAuthentication();

require_once '../classes/FileManager.php';

}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method !== 'GET') {
        // Only output JSON error headers if we're returning an error
        header('Content-Type: application/json');
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }

    // Get current knowledgebase selection and determine export path
    $currentKnowledgebase = getConfig('current_knowledgebase', '');
    
    
    // Use consistent path handling
    if (empty($currentKnowledgebase) || $currentKnowledgebase === 'root') {
        $currentContentPath = '../content';
    } else {
        $currentContentPath = '../content/' . $currentKnowledgebase;
    }
    
    $fileManager = new FileManager($currentContentPath);
    
    // Debug logging
    
    // Check if content directory exists
    if (!is_dir($currentContentPath)) {
        header('Content-Type: application/json');
        http_response_code(404);
        echo json_encode(['error' => 'Content directory not found: ' . $currentContentPath]);
        exit;
    }
    
    // Get files based on current knowledgebase selection
    $files = [];
    $exportingAllKb = (empty($currentKnowledgebase) || $currentKnowledgebase === 'root');
    
    
    if ($exportingAllKb) {
        // Export all knowledgebases - get files from all subfolders
        $files = getAllMarkdownFiles('../content');
        
        // If no files found with getAllMarkdownFiles, try manual approach
        if (empty($files)) {
            
            // Try to get files directly
            $manualFiles = [];
            
            // Root files
            $rootFiles = glob('../content/*.md');
            if ($rootFiles) {
                $manualFiles = array_merge($manualFiles, $rootFiles);
            }
            
            // Subdirectory files
            $subdirs = glob('../content/*', GLOB_ONLYDIR);
            
            foreach ($subdirs as $subdir) {
                $subFiles = glob($subdir . '/*.md');
                if ($subFiles) {
                    $manualFiles = array_merge($manualFiles, $subFiles);
                }
            }
            
            $files = $manualFiles;
        }
    } else {
        // Export specific knowledgebase only
        $files = glob($currentContentPath . '/*.md');
    }
    
    if (empty($files)) {
        // Let's check what's actually in the directory
        $allFilesInDir = glob($currentContentPath . '/*');
        
        header('Content-Type: application/json');
        http_response_code(404);
        echo json_encode(['error' => 'No markdown files found to export in ' . $currentContentPath]);
        exit;
    }
    
    // $exportingAllKb is already defined above
    
    // Create temporary zip file with date/time in filename
    $date = date('Y-m-d_H-i-s');
    $siteTitle = getConfig('site_title', 'Knowledge Base');
    
    // Sanitize site title for filename (preserve international characters like åäö)
    // Only remove characters that are truly unsafe for filenames
    $safeSiteTitle = preg_replace('/[<>:"|*?\\/\\\\]/', '-', $siteTitle); // Remove filesystem unsafe chars
    $safeSiteTitle = preg_replace('/\s+/', '-', $safeSiteTitle); // Replace spaces with dashes
    $safeSiteTitle = preg_replace('/-+/', '-', $safeSiteTitle); // Remove multiple dashes
    $safeSiteTitle = trim($safeSiteTitle, '-'); // Remove leading/trailing dashes
    $safeSiteTitle = mb_strtolower($safeSiteTitle, 'UTF-8'); // Proper Unicode lowercase
    
    // Fallback if title becomes empty after sanitization
    if (empty($safeSiteTitle)) {
        $safeSiteTitle = 'knowledge-base';
    }
    
    // Generate filename based on what's being exported
    if ($exportingAllKb) {
        $zipFilename = "{$safeSiteTitle}-all-knowledgebases_{$date}.zip";
    } else {
        $kbSafeName = preg_replace('/[<>:"|*?\\/\\\\]/', '-', $currentKnowledgebase);
        $zipFilename = "{$safeSiteTitle}-{$kbSafeName}_{$date}.zip";
    }
    $zipPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $zipFilename;
    
    
    // Initialize zip archive
    $zip = new ZipArchive();
    $result = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    
    
    if ($result !== TRUE) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create zip archive: ' . $result]);
        exit;
    }
    
    // Add all markdown files to zip
    $fileCount = 0;
    $fileList = []; // Keep track of files for index generation
    
    
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
                
                $zipResult = $zip->addFromString($filePathInZip, $fileContent);
                $fileCount++;
                
                // Store file info for index
                $fileList[] = [
                    'filename' => $filePathInZip,
                    'filepath' => $file,
                    'size' => strlen($fileContent),
                    'modified' => filemtime($file),
                    'title' => extractTitleFromContent($fileContent, basename($file)),
                    'knowledgebase' => $exportingAllKb ? getKnowledgebaseFromPath($file) : $currentKnowledgebase
                ];
            } else {
            }
        } else {
        }
    }
    
    
    // Create index.md file
    $indexContent = generateIndexMarkdown($fileList, $siteTitle);
    $zip->addFromString('index.md', $indexContent);
    
    // Add metadata file with export info
    $metadata = [
        'export_date' => date('Y-m-d H:i:s'),
        'file_count' => $fileCount,
        'source' => 'Knowledge Base System',
        'version' => '1.0',
        'knowledgebase' => $exportingAllKb ? 'all' : $currentKnowledgebase,
        'export_type' => $exportingAllKb ? 'all_knowledgebases' : 'single_knowledgebase'
    ];
    $zip->addFromString('export_info.json', json_encode($metadata, JSON_PRETTY_PRINT));
    
    
    // Close zip file
    $closeResult = $zip->close();
    
    
    if (!$closeResult) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['error' => 'Failed to finalize zip file']);
        exit;
    }
    
    // Check if zip was created successfully
    $zipExists = file_exists($zipPath);
    $zipSize = $zipExists ? filesize($zipPath) : 0;
    
    
    if (!$zipExists || $zipSize == 0) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create zip file or file is empty. Size: ' . $zipSize . ' bytes']);
        exit;
    }
    
    // Clear any output buffers to prevent corruption
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Verify file size
    $fileSize = filesize($zipPath);
    
    // Set headers for file download
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zipFilename . '"');
    header('Content-Length: ' . $fileSize);
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Flush any remaining output
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
    
    // Output file and clean up
    $handle = fopen($zipPath, 'rb');
    if ($handle) {
        while (!feof($handle)) {
            echo fread($handle, 8192);
            flush();
        }
        fclose($handle);
    }
    unlink($zipPath);
    exit;
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function extractTitleFromContent($content, $filename) {
    // First try to extract title from frontmatter
    $pattern = '/^---\s*\n(.*?)\n---\s*\n/s';
    if (preg_match($pattern, $content, $matches)) {
        $frontmatter = $matches[1];
        $lines = explode("\n", $frontmatter);
        foreach ($lines as $line) {
            if (strpos($line, 'title:') === 0) {
                $title = trim(str_replace('title:', '', $line));
                $title = trim($title, '"\'');
                if (!empty($title)) {
                    return $title;
                }
            }
        }
    }
    
    // Try to extract title from first heading
    if (preg_match('/^#\s+(.+)$/m', $content, $matches)) {
        return trim($matches[1]);
    }
    
    // Fallback to filename without extension
    return preg_replace('/\.md$/', '', basename($filename));
}

function generateIndexMarkdown($fileList, $siteTitle) {
    $date = date('Y-m-d');
    $time = date('H:i');
    $totalFiles = count($fileList);
    $totalSize = array_sum(array_column($fileList, 'size'));
    
    // Check if this is a multi-knowledgebase export
    $knowledgebases = array_unique(array_column($fileList, 'knowledgebase'));
    $isMultiKb = count($knowledgebases) > 1 || (count($knowledgebases) == 1 && in_array('root', $knowledgebases) && $totalFiles > 0);
    
    // Generate markdown content
    $markdown = "# {$siteTitle}\n\n";
    $markdown .= "## Knowledge Base Export\n\n";
    $markdown .= "This knowledge base was exported on **{$date}** at **{$time}**.\n\n";
    
    if ($isMultiKb) {
        $kbCount = count($knowledgebases);
        $markdown .= "### 📊 Statistics\n\n";
        $markdown .= "- **Total Files:** {$totalFiles}\n";
        $markdown .= "- **Total Size:** " . formatBytes($totalSize) . "\n";
        $markdown .= "- **Knowledge Bases:** {$kbCount}\n";
        $markdown .= "- **Export Date:** {$date} {$time}\n\n";
        
        // Group files by knowledgebase
        $kbGroups = [];
        foreach ($fileList as $file) {
            $kbGroups[$file['knowledgebase']][] = $file;
        }
        
        $markdown .= "### 📚 Knowledge Bases Included\n\n";
        foreach ($kbGroups as $kb => $files) {
            $fileCount = count($files);
            $kbSize = array_sum(array_column($files, 'size'));
            $markdown .= "- **{$kb}**: {$fileCount} files (" . formatBytes($kbSize) . ")\n";
        }
        $markdown .= "\n";
        
        $markdown .= "### 🗂️ File Organization\n\n";
        $markdown .= "Files are organized by knowledge base below. Each knowledge base contains its own collection of documents.\n\n";
        
        $markdown .= "---\n\n";
        $markdown .= "## 📋 File Index by Knowledge Base\n\n";
        
        // Sort knowledgebases
        ksort($kbGroups);
        
        foreach ($kbGroups as $kb => $files) {
            $markdown .= "### 📁 {$kb}\n\n";
            
            // Sort files within KB alphabetically
            usort($files, function($a, $b) {
                return strcasecmp($a['title'], $b['title']);
            });
            
            foreach ($files as $file) {
                $title = $file['title'];
                $filename = $file['filename'];
                $size = formatBytes($file['size']);
                $modified = date('Y-m-d', $file['modified']);
                
                $markdown .= "- **[{$title}]({$filename})** ";
                $markdown .= "*({$size}, modified: {$modified})*\n";
            }
            $markdown .= "\n";
        }
    } else {
        // Single knowledgebase export
        $singleKb = $knowledgebases[0];
        $markdown .= "### 📊 Statistics\n\n";
        $markdown .= "- **Knowledge Base:** {$singleKb}\n";
        $markdown .= "- **Total Files:** {$totalFiles}\n";
        $markdown .= "- **Total Size:** " . formatBytes($totalSize) . "\n";
        $markdown .= "- **Export Date:** {$date} {$time}\n\n";
        
        // Sort files alphabetically by title
        usort($fileList, function($a, $b) {
            return strcasecmp($a['title'], $b['title']);
        });
        
        // Group files by first letter
        $groupedFiles = [];
        foreach ($fileList as $file) {
            $firstLetter = mb_strtoupper(mb_substr($file['title'], 0, 1, 'UTF-8'), 'UTF-8');
            if (!preg_match('/[A-ZÅÄÖ]/u', $firstLetter)) {
                $firstLetter = '#'; // For numbers and symbols
            }
            $groupedFiles[$firstLetter][] = $file;
        }
        
        $markdown .= "### 🗂️ File Organization\n\n";
        $markdown .= "All files are organized alphabetically below. Click on any link to open the corresponding file.\n\n";
        
        $markdown .= "---\n\n";
        $markdown .= "## 📋 File Index\n\n";
        
        // Generate alphabetical index
        foreach ($groupedFiles as $letter => $files) {
            $markdown .= "### {$letter}\n\n";
            foreach ($files as $file) {
                $title = $file['title'];
                $filename = $file['filename'];
                $size = formatBytes($file['size']);
                $modified = date('Y-m-d', $file['modified']);
                
                $markdown .= "- **[{$title}]({$filename})** ";
                $markdown .= "*({$size}, modified: {$modified})*\n";
            }
            $markdown .= "\n";
        }
    }
    
    $markdown .= "---\n\n";
    $markdown .= "### 💡 How to Use\n\n";
    $markdown .= "1. **Open any file** by clicking the links above\n";
    $markdown .= "2. **Search content** using your text editor's search function\n";
    $markdown .= "3. **Edit files** with any markdown editor (VS Code, Typora, etc.)\n";
    $markdown .= "4. **View files** in any markdown viewer or convert to HTML/PDF\n\n";
    
    $markdown .= "### 🔄 Re-importing\n\n";
    $markdown .= "This export can be re-imported back into a Knowledge Base system using the import function.\n\n";
    
    $markdown .= "*Generated by Knowledge Base System*\n";
    
    return $markdown;
}

function formatBytes($size, $precision = 2) {
    if ($size == 0) return '0 B';
    
    $units = ['B', 'KB', 'MB', 'GB'];
    $base = log($size, 1024);
    $index = floor($base);
    
    return round(pow(1024, $base - $index), $precision) . ' ' . $units[$index];
}

function getAllMarkdownFiles($contentDir) {
    $files = [];
    
    
    // Get files from root content directory
    $rootPattern = $contentDir . '/*.md';
    $rootFiles = glob($rootPattern);
    if ($rootFiles) {
        $files = array_merge($files, $rootFiles);
    }
    
    // Get files from all subdirectories (knowledgebases)
    $subdirPattern = $contentDir . '/*';
    $subdirs = glob($subdirPattern, GLOB_ONLYDIR);
    
    foreach ($subdirs as $subdir) {
        $subPattern = $subdir . '/*.md';
        $subFiles = glob($subPattern);
        if ($subFiles) {
            $files = array_merge($files, $subFiles);
        }
    }
    
    return $files;
}

function getKnowledgebaseFromPath($filepath) {
    // Extract knowledgebase name from file path
    // e.g., ../content/my-kb/file.md -> my-kb
    $relativePath = str_replace('../content/', '', $filepath);
    $pathParts = explode('/', $relativePath);
    
    if (count($pathParts) > 1) {
        return $pathParts[0]; // First part is the knowledgebase name
    }
    
    return 'root'; // Files in root content directory
}
?>