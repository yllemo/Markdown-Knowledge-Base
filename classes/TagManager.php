<?php
// classes/TagManager.php - Tag management and organization

class TagManager {
    private $contentDir;
    private $fileManager;
    
    public function __construct($contentDir = 'content') {
        $this->contentDir = $contentDir;
        require_once 'FileManager.php';
        $this->fileManager = new FileManager($contentDir);
    }
    
    public function getAllTags() {
        $tags = [];
        $files = $this->fileManager->getAllFiles();
        
        foreach ($files as $file) {
            $content = file_get_contents($file['path']);
            $metadata = $this->extractMetadata($content);
            
            if (isset($metadata['tags']) && is_array($metadata['tags'])) {
                foreach ($metadata['tags'] as $tag) {
                    $tag = trim($tag);
                    if (!empty($tag)) {
                        $tags[$tag] = ($tags[$tag] ?? 0) + 1;
                    }
                }
            }
        }
        
        // Sort tags by frequency (most used first)
        arsort($tags);
        
        return $tags;
    }
    
    public function getTagCloud($minFont = 12, $maxFont = 24) {
        $tags = $this->getAllTags();
        
        if (empty($tags)) {
            return [];
        }
        
        $minCount = min($tags);
        $maxCount = max($tags);
        $fontRange = $maxFont - $minFont;
        
        $tagCloud = [];
        
        foreach ($tags as $tag => $count) {
            if ($maxCount > $minCount) {
                $fontSize = $minFont + (($count - $minCount) / ($maxCount - $minCount)) * $fontRange;
            } else {
                $fontSize = $minFont;
            }
            
            $tagCloud[] = [
                'tag' => $tag,
                'count' => $count,
                'fontSize' => round($fontSize, 1),
                'weight' => $this->calculateTagWeight($count, $minCount, $maxCount)
            ];
        }
        
        return $tagCloud;
    }
    
    public function getRelatedTags($targetTag, $limit = 5) {
        $relatedTags = [];
        $files = $this->fileManager->getAllFiles();
        
        foreach ($files as $file) {
            $content = file_get_contents($file['path']);
            $metadata = $this->extractMetadata($content);
            
            if (isset($metadata['tags']) && is_array($metadata['tags'])) {
                $fileTags = array_map('strtolower', $metadata['tags']);
                
                if (in_array(strtolower($targetTag), $fileTags)) {
                    foreach ($metadata['tags'] as $tag) {
                        $tagLower = strtolower($tag);
                        if ($tagLower !== strtolower($targetTag)) {
                            $relatedTags[$tag] = ($relatedTags[$tag] ?? 0) + 1;
                        }
                    }
                }
            }
        }
        
        // Sort by frequency and return top results
        arsort($relatedTags);
        return array_slice($relatedTags, 0, $limit, true);
    }
    
    public function getTagHierarchy() {
        $tags = array_keys($this->getAllTags());
        $hierarchy = [];
        
        foreach ($tags as $tag) {
            // Check for hierarchical tags (using / or : as separators)
            if (strpos($tag, '/') !== false) {
                $parts = explode('/', $tag);
                $this->buildHierarchy($hierarchy, $parts);
            } elseif (strpos($tag, ':') !== false) {
                $parts = explode(':', $tag);
                $this->buildHierarchy($hierarchy, $parts);
            } else {
                $hierarchy[$tag] = $hierarchy[$tag] ?? [];
            }
        }
        
        return $hierarchy;
    }
    
    public function suggestTags($content, $limit = 5) {
        // Extract keywords from content
        $content = strtolower(strip_tags($content));
        $content = preg_replace('/[^\w\s]/', ' ', $content);
        
        // Get existing tags for reference
        $existingTags = array_keys($this->getAllTags());
        $suggestions = [];
        
        // Check for existing tag matches in content
        foreach ($existingTags as $tag) {
            if (strpos($content, strtolower($tag)) !== false) {
                $suggestions[$tag] = substr_count($content, strtolower($tag));
            }
        }
        
        // Extract potential new tags (common words)
        $words = str_word_count($content, 1);
        $wordFreq = array_count_values($words);
        
        // Filter out common words
        $stopWords = ['the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'is', 'are', 'was', 'were', 'be', 'been', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should', 'may', 'might', 'can', 'this', 'that', 'these', 'those', 'a', 'an'];
        
        foreach ($wordFreq as $word => $freq) {
            if (strlen($word) > 3 && !in_array($word, $stopWords) && $freq > 1) {
                if (!in_array($word, $existingTags)) {
                    $suggestions[$word] = $freq;
                }
            }
        }
        
        // Sort by frequency and return top suggestions
        arsort($suggestions);
        return array_slice(array_keys($suggestions), 0, $limit);
    }
    
    public function getTagStats() {
        $tags = $this->getAllTags();
        $files = $this->fileManager->getAllFiles();
        
        $stats = [
            'total_tags' => count($tags),
            'total_usages' => array_sum($tags),
            'avg_tags_per_file' => 0,
            'most_used_tag' => '',
            'least_used_tag' => '',
            'files_without_tags' => 0
        ];
        
        if (!empty($tags)) {
            $stats['most_used_tag'] = array_key_first($tags);
            $stats['least_used_tag'] = array_key_last($tags);
        }
        
        // Calculate average tags per file
        $totalTagsUsed = 0;
        $filesWithTags = 0;
        
        foreach ($files as $file) {
            $content = file_get_contents($file['path']);
            $metadata = $this->extractMetadata($content);
            
            if (isset($metadata['tags']) && is_array($metadata['tags'])) {
                $totalTagsUsed += count($metadata['tags']);
                $filesWithTags++;
            } else {
                $stats['files_without_tags']++;
            }
        }
        
        if ($filesWithTags > 0) {
            $stats['avg_tags_per_file'] = round($totalTagsUsed / $filesWithTags, 2);
        }
        
        return $stats;
    }
    
    public function renameTag($oldTag, $newTag) {
        $files = $this->fileManager->getAllFiles();
        $updatedFiles = 0;
        
        foreach ($files as $file) {
            $content = file_get_contents($file['path']);
            $metadata = $this->extractMetadata($content);
            
            if (isset($metadata['tags']) && is_array($metadata['tags'])) {
                $tagIndex = array_search($oldTag, $metadata['tags']);
                
                if ($tagIndex !== false) {
                    $metadata['tags'][$tagIndex] = $newTag;
                    
                    // Update the file with new metadata
                    $newContent = $this->updateMetadata($content, $metadata);
                    file_put_contents($file['path'], $newContent);
                    $updatedFiles++;
                }
            }
        }
        
        return $updatedFiles;
    }
    
    public function deleteTag($tagToDelete) {
        $files = $this->fileManager->getAllFiles();
        $updatedFiles = 0;
        
        foreach ($files as $file) {
            $content = file_get_contents($file['path']);
            $metadata = $this->extractMetadata($content);
            
            if (isset($metadata['tags']) && is_array($metadata['tags'])) {
                $originalCount = count($metadata['tags']);
                $metadata['tags'] = array_filter($metadata['tags'], function($tag) use ($tagToDelete) {
                    return $tag !== $tagToDelete;
                });
                
                if (count($metadata['tags']) < $originalCount) {
                    // Update the file with new metadata
                    $newContent = $this->updateMetadata($content, $metadata);
                    file_put_contents($file['path'], $newContent);
                    $updatedFiles++;
                }
            }
        }
        
        return $updatedFiles;
    }
    
    private function extractMetadata($content) {
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
    
    private function updateMetadata($content, $metadata) {
        $pattern = '/^---\s*\n.*?\n---\s*\n/s';
        
        // Create new frontmatter
        $frontmatter = "---\n";
        foreach ($metadata as $key => $value) {
            if (is_array($value)) {
                $frontmatter .= $key . ': [' . implode(', ', array_map(function($v) {
                    return '"' . $v . '"';
                }, $value)) . "]\n";
            } else {
                $frontmatter .= $key . ': "' . $value . "\"\n";
            }
        }
        $frontmatter .= "---\n";
        
        // Replace existing frontmatter or add new one
        if (preg_match($pattern, $content)) {
            return preg_replace($pattern, $frontmatter, $content);
        } else {
            return $frontmatter . $content;
        }
    }
    
    private function buildHierarchy(&$hierarchy, $parts) {
        $current = &$hierarchy;
        
        foreach ($parts as $part) {
            $part = trim($part);
            if (!isset($current[$part])) {
                $current[$part] = [];
            }
            $current = &$current[$part];
        }
    }
    
    private function calculateTagWeight($count, $minCount, $maxCount) {
        if ($maxCount === $minCount) {
            return 1;
        }
        
        return ($count - $minCount) / ($maxCount - $minCount);
    }
}