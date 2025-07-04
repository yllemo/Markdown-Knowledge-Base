<?php
// classes/FileManager.php - File management operations

class FileManager {
    private $contentDir;
    
    public function __construct($contentDir = 'content') {
        $this->contentDir = $contentDir;
        $this->ensureContentDirectory();
    }
    
    private function ensureContentDirectory() {
        if (!is_dir($this->contentDir)) {
            mkdir($this->contentDir, 0755, true);
        }
    }
    
    public function getAllFiles() {
        $files = [];
        $pattern = $this->contentDir . '/*.md';
        
        foreach (glob($pattern) as $filePath) {
            $fileName = basename($filePath);
            $files[] = [
                'name' => $fileName,
                'display_name' => $this->getDisplayName($fileName),
                'path' => $filePath,
                'modified' => filemtime($filePath),
                'size' => filesize($filePath)
            ];
        }
        
        // Sort by modification time (newest first)
        usort($files, function($a, $b) {
            return $b['modified'] - $a['modified'];
        });
        
        return $files;
    }
    
    public function getFile($fileName) {
        $filePath = $this->getFilePath($fileName);
        
        if (!file_exists($filePath)) {
            throw new Exception("File not found: $fileName");
        }
        
        $content = file_get_contents($filePath);
        
        return [
            'name' => $fileName,
            'content' => $content,
            'modified' => filemtime($filePath),
            'size' => filesize($filePath)
        ];
    }
    
    public function saveFile($fileName, $content, $title = null) {
        // Sanitize filename
        $fileName = $this->sanitizeFileName($fileName);
        
        // If title is provided and different from current filename, rename
        if ($title && $title !== $this->getDisplayName($fileName)) {
            $newFileName = $this->generateFileName($title);
            $fileName = $newFileName;
        }
        
        $filePath = $this->getFilePath($fileName);
        
        // Create backup if file exists
        if (file_exists($filePath)) {
            $this->createBackup($filePath);
        }
        
        $result = file_put_contents($filePath, $content);
        
        if ($result === false) {
            throw new Exception("Failed to save file: $fileName");
        }
        
        return [
            'name' => $fileName,
            'path' => $filePath,
            'size' => $result
        ];
    }
    
    public function deleteFile($fileName) {
        $filePath = $this->getFilePath($fileName);
        
        if (!file_exists($filePath)) {
            throw new Exception("File not found: $fileName");
        }
        
        // Create backup before deletion
        $this->createBackup($filePath);
        
        if (!unlink($filePath)) {
            throw new Exception("Failed to delete file: $fileName");
        }
        
        return true;
    }
    
    public function searchFiles($query) {
        $results = [];
        $files = $this->getAllFiles();
        
        foreach ($files as $file) {
            $content = file_get_contents($file['path']);
            $score = $this->calculateRelevanceScore($content, $file['display_name'], $query);
            
            if ($score > 0) {
                $file['relevance'] = $score;
                $file['excerpt'] = $this->getExcerpt($content, $query);
                $results[] = $file;
            }
        }
        
        // Sort by relevance
        usort($results, function($a, $b) {
            return $b['relevance'] - $a['relevance'];
        });
        
        return $results;
    }
    
    public function getFilesByTag($tag) {
        $results = [];
        $files = $this->getAllFiles();
        
        foreach ($files as $file) {
            $content = file_get_contents($file['path']);
            $metadata = $this->parseFrontmatter($content);
            
            if (isset($metadata['tags']) && in_array($tag, $metadata['tags'])) {
                $results[] = $file;
            }
        }
        
        return $results;
    }
    
    private function getFilePath($fileName) {
        return $this->contentDir . '/' . $fileName;
    }
    
    private function sanitizeFileName($fileName) {
        // Remove or replace unsafe characters
        $fileName = preg_replace('/[^a-zA-Z0-9\-_\.]/', '-', $fileName);
        
        // Ensure .md extension
        if (!preg_match('/\.md$/', $fileName)) {
            $fileName .= '.md';
        }
        
        return $fileName;
    }
    
    private function generateFileName($title) {
        // Convert title to filename
        $fileName = strtolower(trim($title));
        $fileName = preg_replace('/[^a-zA-Z0-9\s\-_]/', '', $fileName);
        $fileName = preg_replace('/\s+/', '-', $fileName);
        $fileName = trim($fileName, '-');
        
        if (empty($fileName)) {
            $fileName = 'untitled-' . time();
        }
        
        return $fileName . '.md';
    }
    
    private function getDisplayName($fileName) {
        return preg_replace('/\.md$/', '', basename($fileName));
    }
    
    private function createBackup($filePath) {
        $backupDir = dirname($this->contentDir) . '/.backups';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        $fileName = basename($filePath);
        $timestamp = date('Y-m-d_H-i-s');
        $backupPath = $backupDir . '/' . $timestamp . '_' . $fileName;
        
        copy($filePath, $backupPath);
        
        // Clean old backups (keep only last 10 per file)
        $this->cleanOldBackups($fileName);
    }
    
    private function cleanOldBackups($fileName) {
        $backupDir = dirname($this->contentDir) . '/.backups';
        $pattern = $backupDir . '/*_' . $fileName;
        $backups = glob($pattern);
        
        if (count($backups) > 10) {
            // Sort by modification time
            usort($backups, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            
            // Remove oldest backups
            $toRemove = array_slice($backups, 0, count($backups) - 10);
            foreach ($toRemove as $backup) {
                unlink($backup);
            }
        }
    }
    
    private function calculateRelevanceScore($content, $title, $query) {
        $score = 0;
        $query = strtolower($query);
        $content = strtolower($content);
        $title = strtolower($title);
        
        // Title matches are highly relevant
        if (strpos($title, $query) !== false) {
            $score += 100;
        }
        
        // Count occurrences in content
        $contentMatches = substr_count($content, $query);
        $score += $contentMatches * 10;
        
        // Bonus for exact phrase matches
        if (strpos($content, $query) !== false) {
            $score += 20;
        }
        
        // Check for word matches
        $queryWords = explode(' ', $query);
        foreach ($queryWords as $word) {
            $word = trim($word);
            if (strlen($word) > 2) {
                $wordMatches = substr_count($content, $word);
                $score += $wordMatches * 5;
            }
        }
        
        return $score;
    }
    
    private function getExcerpt($content, $query, $length = 150) {
        $content = strip_tags($content);
        $query = strtolower($query);
        $contentLower = strtolower($content);
        
        $pos = strpos($contentLower, $query);
        
        if ($pos !== false) {
            $start = max(0, $pos - 50);
            $excerpt = substr($content, $start, $length);
            
            if ($start > 0) {
                $excerpt = '...' . $excerpt;
            }
            
            if (strlen($content) > $start + $length) {
                $excerpt .= '...';
            }
            
            return $excerpt;
        }
        
        return substr($content, 0, $length) . '...';
    }
    
    private function parseFrontmatter($content) {
        $pattern = '/^---\s*\n(.*?)\n---\s*\n/s';
        
        if (preg_match($pattern, $content, $matches)) {
            $frontmatter = $matches[1];
            $metadata = [];
            
            $lines = explode("\n", $frontmatter);
            foreach ($lines as $line) {
                if (strpos($line, ':') !== false) {
                    list($key, $value) = explode(':', $line, 2);
                    $key = trim($key);
                    $value = trim($value);
                    
                    // Parse arrays
                    if (preg_match('/^\[(.*)\]$/', $value, $arrayMatches)) {
                        $arrayItems = explode(',', $arrayMatches[1]);
                        $value = array_map(function($item) {
                            return trim($item, ' "\'');
                        }, $arrayItems);
                        $value = array_filter($value);
                    } else {
                        // Remove quotes
                        $value = trim($value, '"\'');
                    }
                    
                    $metadata[$key] = $value;
                }
            }
            
            return $metadata;
        }
        
        return [];
    }
    
    public function getStats() {
        $files = $this->getAllFiles();
        $totalSize = 0;
        $tagCount = [];
        
        foreach ($files as $file) {
            $totalSize += $file['size'];
            $content = file_get_contents($file['path']);
            $metadata = $this->parseFrontmatter($content);
            
            if (isset($metadata['tags'])) {
                foreach ($metadata['tags'] as $tag) {
                    $tagCount[$tag] = ($tagCount[$tag] ?? 0) + 1;
                }
            }
        }
        
        return [
            'file_count' => count($files),
            'total_size' => $totalSize,
            'tag_count' => count($tagCount),
            'most_used_tags' => array_slice($tagCount, 0, 5, true)
        ];
    }
}