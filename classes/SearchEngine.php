<?php
// classes/SearchEngine.php - Advanced search functionality

class SearchEngine {
    private $contentDir;
    private $fileManager;
    
    public function __construct($contentDir = 'content') {
        $this->contentDir = $contentDir;
        require_once 'FileManager.php';
        $this->fileManager = new FileManager($contentDir);
    }
    
    public function search($query, $options = []) {
        $query = trim($query);
        
        if (empty($query)) {
            return [];
        }
        
        // Parse search query for advanced features
        $parsedQuery = $this->parseQuery($query);
        
        // Get all files
        $files = $this->fileManager->getAllFiles();
        $results = [];
        
        foreach ($files as $file) {
            $content = file_get_contents($file['path']);
            $metadata = $this->extractMetadata($content);
            $plainContent = $this->stripFrontmatter($content);
            
            $score = $this->calculateScore($parsedQuery, $file, $metadata, $plainContent);
            
            if ($score > 0) {
                $file['relevance'] = $score;
                $file['excerpt'] = $this->generateExcerpt($plainContent, $parsedQuery['terms']);
                $file['highlights'] = $this->getHighlights($plainContent, $parsedQuery['terms']);
                $file['metadata'] = $metadata;
                $results[] = $file;
            }
        }
        
        // Sort by relevance score
        usort($results, function($a, $b) {
            return $b['relevance'] - $a['relevance'];
        });
        
        return $results;
    }
    
    private function parseQuery($query) {
        $parsed = [
            'terms' => [],
            'tags' => [],
            'title_only' => false,
            'exact_phrase' => false,
            'exclude' => []
        ];
        
        // Handle quoted phrases
        if (preg_match('/"([^"]+)"/', $query, $matches)) {
            $parsed['exact_phrase'] = $matches[1];
            $query = str_replace($matches[0], '', $query);
        }
        
        // Handle special operators
        $tokens = explode(' ', $query);
        
        foreach ($tokens as $token) {
            $token = trim($token);
            if (empty($token)) continue;
            
            if (strpos($token, 'tag:') === 0) {
                // Tag filter
                $parsed['tags'][] = substr($token, 4);
            } elseif (strpos($token, 'title:') === 0) {
                // Title search
                $parsed['terms'][] = substr($token, 6);
                $parsed['title_only'] = true;
            } elseif (strpos($token, '-') === 0) {
                // Exclude term
                $parsed['exclude'][] = substr($token, 1);
            } else {
                // Regular search term
                $parsed['terms'][] = $token;
            }
        }
        
        return $parsed;
    }
    
    private function calculateScore($parsedQuery, $file, $metadata, $content) {
        $score = 0;
        $title = $file['display_name'];
        $contentLower = strtolower($content);
        $titleLower = strtolower($title);
        
        // Tag filtering
        if (!empty($parsedQuery['tags'])) {
            $fileTags = $metadata['tags'] ?? [];
            $hasRequiredTag = false;
            
            foreach ($parsedQuery['tags'] as $requiredTag) {
                if (in_array(strtolower($requiredTag), array_map('strtolower', $fileTags))) {
                    $hasRequiredTag = true;
                    $score += 50; // Bonus for tag match
                    break;
                }
            }
            
            if (!$hasRequiredTag) {
                return 0; // Must have required tag
            }
        }
        
        // Exclude terms
        foreach ($parsedQuery['exclude'] as $excludeTerm) {
            if (strpos($contentLower, strtolower($excludeTerm)) !== false) {
                return 0; // Exclude this file
            }
        }
        
        // Exact phrase matching
        if ($parsedQuery['exact_phrase']) {
            $phrase = strtolower($parsedQuery['exact_phrase']);
            if (strpos($contentLower, $phrase) !== false) {
                $score += 100;
            }
            if (strpos($titleLower, $phrase) !== false) {
                $score += 200;
            }
        }
        
        // Regular term matching
        foreach ($parsedQuery['terms'] as $term) {
            $termLower = strtolower($term);
            
            if ($parsedQuery['title_only']) {
                // Search only in title
                if (strpos($titleLower, $termLower) !== false) {
                    $score += 150;
                }
            } else {
                // Title matches are highly weighted
                $titleMatches = substr_count($titleLower, $termLower);
                $score += $titleMatches * 100;
                
                // Content matches
                $contentMatches = substr_count($contentLower, $termLower);
                $score += $contentMatches * 10;
                
                // Bonus for word boundaries
                if (preg_match('/\b' . preg_quote($termLower, '/') . '\b/', $contentLower)) {
                    $score += 20;
                }
                
                // Bonus for matches in headings
                if (preg_match('/^#{1,6}\s.*' . preg_quote($termLower, '/') . '/im', $content)) {
                    $score += 30;
                }
                
                // Check metadata fields
                if (isset($metadata['description']) && 
                    strpos(strtolower($metadata['description']), $termLower) !== false) {
                    $score += 25;
                }
            }
        }
        
        // Boost recent files slightly
        $daysSinceModified = (time() - $file['modified']) / (24 * 60 * 60);
        if ($daysSinceModified < 7) {
            $score += 5;
        } elseif ($daysSinceModified < 30) {
            $score += 2;
        }
        
        return $score;
    }
    
    private function generateExcerpt($content, $terms, $length = 200) {
        $content = strip_tags($content);
        $contentLower = strtolower($content);
        
        // Find the best position to start excerpt
        $bestPos = 0;
        $maxMatches = 0;
        
        foreach ($terms as $term) {
            $termLower = strtolower($term);
            $pos = strpos($contentLower, $termLower);
            
            if ($pos !== false) {
                // Count matches in a window around this position
                $windowStart = max(0, $pos - 100);
                $windowEnd = min(strlen($content), $pos + 100);
                $window = substr($contentLower, $windowStart, $windowEnd - $windowStart);
                
                $matches = 0;
                foreach ($terms as $checkTerm) {
                    $matches += substr_count($window, strtolower($checkTerm));
                }
                
                if ($matches > $maxMatches) {
                    $maxMatches = $matches;
                    $bestPos = max(0, $pos - 50);
                }
            }
        }
        
        $excerpt = substr($content, $bestPos, $length);
        
        // Clean up excerpt boundaries
        if ($bestPos > 0) {
            $excerpt = '...' . $excerpt;
        }
        
        if (strlen($content) > $bestPos + $length) {
            $excerpt .= '...';
        }
        
        return trim($excerpt);
    }
    
    private function getHighlights($content, $terms, $maxHighlights = 3) {
        $highlights = [];
        $contentLower = strtolower($content);
        
        foreach ($terms as $term) {
            $termLower = strtolower($term);
            $pos = 0;
            $count = 0;
            
            while (($pos = strpos($contentLower, $termLower, $pos)) !== false && $count < $maxHighlights) {
                $start = max(0, $pos - 30);
                $length = min(60, strlen($content) - $start);
                $highlight = substr($content, $start, $length);
                
                if ($start > 0) {
                    $highlight = '...' . $highlight;
                }
                
                if ($start + $length < strlen($content)) {
                    $highlight .= '...';
                }
                
                $highlights[] = trim($highlight);
                $pos += strlen($term);
                $count++;
            }
        }
        
        return array_unique($highlights);
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
    
    private function stripFrontmatter($content) {
        $pattern = '/^---\s*\n.*?\n---\s*\n/s';
        return preg_replace($pattern, '', $content);
    }
    
    public function getSearchSuggestions($partialQuery, $limit = 5) {
        $suggestions = [];
        
        // Get common terms from all files
        $files = $this->fileManager->getAllFiles();
        $wordFreq = [];
        
        foreach ($files as $file) {
            $content = file_get_contents($file['path']);
            $content = $this->stripFrontmatter($content);
            $content = strtolower(strip_tags($content));
            
            // Extract words
            preg_match_all('/\b\w{3,}\b/', $content, $matches);
            
            foreach ($matches[0] as $word) {
                if (strpos($word, strtolower($partialQuery)) === 0) {
                    $wordFreq[$word] = ($wordFreq[$word] ?? 0) + 1;
                }
            }
        }
        
        // Sort by frequency and return top suggestions
        arsort($wordFreq);
        return array_slice(array_keys($wordFreq), 0, $limit);
    }
    
    public function getPopularSearches() {
        // This could be enhanced to track actual search queries
        // For now, return common words from content
        $files = $this->fileManager->getAllFiles();
        $wordFreq = [];
        
        foreach ($files as $file) {
            $content = file_get_contents($file['path']);
            $metadata = $this->extractMetadata($content);
            
            // Add tags to popular searches
            if (isset($metadata['tags'])) {
                foreach ($metadata['tags'] as $tag) {
                    $wordFreq[$tag] = ($wordFreq[$tag] ?? 0) + 5; // Weight tags higher
                }
            }
            
            // Add title words
            $titleWords = explode(' ', strtolower($file['display_name']));
            foreach ($titleWords as $word) {
                if (strlen($word) > 3) {
                    $wordFreq[$word] = ($wordFreq[$word] ?? 0) + 2;
                }
            }
        }
        
        arsort($wordFreq);
        return array_slice(array_keys($wordFreq), 0, 10);
    }
}