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
            $metadata = $this->fileManager->parseFrontmatter($content);
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
        
        // Handle special operators (collapse whitespace)
        $tokens = preg_split('/\s+/', $query, -1, PREG_SPLIT_NO_EMPTY);
        
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
    
    private function strLower($s) {
        if ($s === null || $s === '') {
            return '';
        }
        return function_exists('mb_strtolower') ? mb_strtolower((string) $s, 'UTF-8') : strtolower((string) $s);
    }
    
    private function strPos($haystack, $needle, $offset = 0) {
        if ($needle === '') {
            return false;
        }
        // Byte offsets only (matches strlen/substr on UTF-8 strings)
        return strpos($haystack, $needle, $offset);
    }
    
    private function strContains($haystack, $needle) {
        return $this->strPos($haystack, $needle) !== false;
    }
    
    private function substrCountUnicode($haystack, $needle) {
        if ($needle === '') {
            return 0;
        }
        if (function_exists('mb_substr_count')) {
            return mb_substr_count($haystack, $needle, 'UTF-8');
        }
        return substr_count($haystack, $needle);
    }
    
    private function normalizeTags($tags) {
        if ($tags === null || $tags === '') {
            return [];
        }
        if (is_array($tags)) {
            return $tags;
        }
        return [$tags];
    }
    
    private function calculateScore($parsedQuery, $file, $metadata, $content) {
        $score = 0;
        $title = $file['display_name'];
        $contentLower = $this->strLower($content);
        $titleLower = $this->strLower($title);
        
        // Tag filtering
        if (!empty($parsedQuery['tags'])) {
            $fileTags = $this->normalizeTags($metadata['tags'] ?? []);
            $hasRequiredTag = false;
            
            foreach ($parsedQuery['tags'] as $requiredTag) {
                $reqLower = $this->strLower($requiredTag);
                foreach ($fileTags as $ft) {
                    if ($this->strLower($ft) === $reqLower) {
                        $hasRequiredTag = true;
                        $score += 50; // Bonus for tag match
                        break 2;
                    }
                }
            }
            
            if (!$hasRequiredTag) {
                return 0; // Must have required tag
            }
        }
        
        // Exclude terms
        foreach ($parsedQuery['exclude'] as $excludeTerm) {
            if ($this->strContains($contentLower, $this->strLower($excludeTerm))) {
                return 0; // Exclude this file
            }
        }
        
        // Exact phrase matching
        if ($parsedQuery['exact_phrase']) {
            $phrase = $this->strLower($parsedQuery['exact_phrase']);
            if ($this->strContains($contentLower, $phrase)) {
                $score += 100;
            }
            if ($this->strContains($titleLower, $phrase)) {
                $score += 200;
            }
        }
        
        // Regular term matching - improved fulltext search
        $termMatchScore = 0;
        $matchedTerms = 0;
        $totalTerms = count($parsedQuery['terms']);
        
        foreach ($parsedQuery['terms'] as $term) {
            $termLower = $this->strLower($term);
            $termMatched = false;
            
            if ($parsedQuery['title_only']) {
                // Search only in title
                if ($this->strContains($titleLower, $termLower)) {
                    $termMatchScore += 150;
                    $termMatched = true;
                }
            } else {
                // Title matches are highly weighted
                if ($this->strContains($titleLower, $termLower)) {
                    $titleMatches = $this->substrCountUnicode($titleLower, $termLower);
                    $termMatchScore += $titleMatches * 100;
                    $termMatched = true;
                }
                
                // Content matches - search in full content
                if ($this->strContains($contentLower, $termLower)) {
                    $contentMatches = $this->substrCountUnicode($contentLower, $termLower);
                    $termMatchScore += $contentMatches * 10;
                    $termMatched = true;
                    
                    // Bonus for word boundaries (Unicode-aware word match)
                    if (preg_match('/\b' . preg_quote($termLower, '/') . '\b/iu', $content)) {
                        $termMatchScore += 20;
                    }
                }
                
                // Bonus for matches in headings
                if (preg_match('/^#{1,6}\s.*' . preg_quote($termLower, '/') . '/imu', $content)) {
                    $termMatchScore += 30;
                }
                
                // Check metadata fields (description, tags, etc.)
                if (isset($metadata['description']) && is_string($metadata['description']) &&
                    $this->strContains($this->strLower($metadata['description']), $termLower)) {
                    $termMatchScore += 25;
                    $termMatched = true;
                }
                
                // Search in tags
                $metaTags = $this->normalizeTags($metadata['tags'] ?? []);
                foreach ($metaTags as $tag) {
                    if ($this->strContains($this->strLower($tag), $termLower)) {
                        $termMatchScore += 30;
                        $termMatched = true;
                        break;
                    }
                }
                
                // Search in code blocks (for technical terms)
                if (preg_match('/```[\s\S]*?' . preg_quote($termLower, '/') . '[\s\S]*?```/iu', $content)) {
                    $termMatchScore += 15;
                    $termMatched = true;
                }
            }
            
            if ($termMatched) {
                $matchedTerms++;
            }
        }
        
        // All non-empty terms must match (AND), same expectation as most search UIs
        if ($totalTerms > 0 && $matchedTerms < $totalTerms) {
            return 0;
        }
        
        // Quoted phrase must appear when user included quotes
        if (!empty($parsedQuery['exact_phrase'])) {
            $phraseNeedle = $this->strLower($parsedQuery['exact_phrase']);
            if (!$this->strContains($contentLower, $phraseNeedle) && !$this->strContains($titleLower, $phraseNeedle)) {
                return 0;
            }
        }
        
        // Add to score if we found any term matches (or there were no text terms)
        if ($termMatchScore > 0 || $matchedTerms > 0) {
            $score += $termMatchScore;
            
            // Bonus for matching more terms
            if ($totalTerms > 0) {
                $matchRatio = $matchedTerms / $totalTerms;
                $score += $matchRatio * 50; // Up to 50 bonus points for matching all terms
            }
            
            // Boost recent files slightly (only for files that match search terms)
            $daysSinceModified = (time() - $file['modified']) / (24 * 60 * 60);
            if ($daysSinceModified < 7) {
                $score += 5;
            } elseif ($daysSinceModified < 30) {
                $score += 2;
            }
        }
        
        return $score;
    }
    
    private function generateExcerpt($content, $terms, $length = 200) {
        $content = strip_tags($content);
        $contentLower = $this->strLower($content);
        
        // Find the best position to start excerpt
        $bestPos = 0;
        $maxMatches = 0;
        
        foreach ($terms as $term) {
            $termLower = $this->strLower($term);
            $pos = $this->strPos($contentLower, $termLower);
            
            if ($pos !== false) {
                // Count matches in a window around this position
                $windowStart = max(0, $pos - 100);
                $windowEnd = min(strlen($content), $pos + 100);
                $window = substr($contentLower, $windowStart, $windowEnd - $windowStart);
                
                $matches = 0;
                foreach ($terms as $checkTerm) {
                    $matches += $this->substrCountUnicode($window, $this->strLower($checkTerm));
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
        $contentLower = $this->strLower($content);
        
        foreach ($terms as $term) {
            $termLower = $this->strLower($term);
            $pos = 0;
            $count = 0;
            
            while (($pos = $this->strPos($contentLower, $termLower, $pos)) !== false && $count < $maxHighlights) {
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
                $pos += strlen($termLower);
                $count++;
            }
        }
        
        return array_unique($highlights);
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
            $content = $this->strLower(strip_tags($content));
            
            // Extract words
            preg_match_all('/\b\w{3,}\b/u', $content, $matches);
            
            foreach ($matches[0] as $word) {
                if ($this->strPos($word, $this->strLower($partialQuery)) === 0) {
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
            $metadata = $this->fileManager->parseFrontmatter($content);
            
            // Add tags to popular searches
            if (isset($metadata['tags'])) {
                foreach ($metadata['tags'] as $tag) {
                    $wordFreq[$tag] = ($wordFreq[$tag] ?? 0) + 5; // Weight tags higher
                }
            }
            
            // Add title words
            $titleWords = preg_split('/\s+/', $this->strLower($file['display_name']), -1, PREG_SPLIT_NO_EMPTY);
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