<?php
// api/export-comments.php - Export comments to text file
// Usage: export-comments.php?file=path/to/file.md&format=txt

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'GET') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$file = isset($_GET['file']) ? $_GET['file'] : '';
$format = isset($_GET['format']) ? strtolower($_GET['format']) : 'txt';

if (empty($file)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Missing file parameter']);
    exit;
}

// Validate file path
if (!preg_match('/\.md$/i', $file)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid file parameter. Must be a .md file.']);
    exit;
}

if (strpos($file, '..') !== false || strpos($file, '\\') !== false) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid file path.']);
    exit;
}

$commentsDir = realpath(__DIR__ . '/../comments');
$contentDir = realpath(__DIR__ . '/../content');

if (!$commentsDir || !$contentDir) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Server configuration error']);
    exit;
}

// Check that the content file exists
$contentPath = realpath($contentDir . '/' . $file);
if (!$contentPath || !file_exists($contentPath) || strpos($contentPath, $contentDir) !== 0) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Content file not found']);
    exit;
}

// Load comments
$commentsFile = $commentsDir . '/' . $file . '.json';
$comments = [];

if (file_exists($commentsFile)) {
    $handle = fopen($commentsFile, 'r');
    if ($handle) {
        flock($handle, LOCK_SH);
        $content = stream_get_contents($handle);
        flock($handle, LOCK_UN);
        fclose($handle);
        $data = json_decode($content, true);
        if ($data && isset($data['comments'])) {
            $comments = $data['comments'];
        }
    }
}

if (empty($comments)) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No comments found for this file']);
    exit;
}

// Generate filename
$now = new DateTime();
$dateStr = $now->format('Y-m-d');
$baseFilename = pathinfo($file, PATHINFO_FILENAME);
$filename = "comments_{$baseFilename}_{$dateStr}.txt";

// Generate content
$content = "Comments Export - {$file}\n";
$content .= "Generated: " . $now->format('Y-m-d H:i:s T') . "\n";
$content .= "Total comments: " . count($comments) . "\n";

$resolved = array_filter($comments, function($c) { return !empty($c['resolved']); });
$unresolved = count($comments) - count($resolved);

$content .= "Open comments: {$unresolved}\n";
$content .= "Resolved comments: " . count($resolved) . "\n";
$content .= str_repeat('=', 50) . "\n\n";

// Group comments by block_id
$commentsByBlock = [];
foreach ($comments as $comment) {
    $blockId = $comment['block_id'] ?? 'unknown';
    if (!isset($commentsByBlock[$blockId])) {
        $commentsByBlock[$blockId] = [];
    }
    $commentsByBlock[$blockId][] = $comment;
}

// Sort comments within each block by creation date
foreach ($commentsByBlock as $blockId => &$blockComments) {
    usort($blockComments, function($a, $b) {
        return strtotime($a['created'] ?? '1970-01-01') - strtotime($b['created'] ?? '1970-01-01');
    });
}
unset($blockComments);

// Generate content for each block
foreach ($commentsByBlock as $blockId => $blockComments) {
    $blockText = $blockComments[0]['block_text'] ?? "Block: {$blockId}";
    if (strlen($blockText) > 100) {
        $blockText = substr($blockText, 0, 100) . '...';
    }
    
    $content .= "📄 {$blockText}\n";
    $content .= str_repeat('-', 30) . "\n";
    
    foreach ($blockComments as $comment) {
        $status = (!empty($comment['resolved'])) ? '[RESOLVED]' : '[OPEN]';
        $author = htmlspecialchars_decode($comment['author'] ?? 'Anonymous');
        $text = htmlspecialchars_decode($comment['text'] ?? '');
        $created = $comment['created'] ?? '';
        
        try {
            $date = new DateTime($created);
            $dateFormatted = $date->format('Y-m-d H:i');
        } catch (Exception $e) {
            $dateFormatted = $created;
        }
        
        $content .= "{$status} {$author} ({$dateFormatted}):\n";
        $content .= "{$text}\n\n";
    }
    
    $content .= "\n";
}

$content .= str_repeat('=', 50) . "\n";
$content .= "End of export\n";

// Set headers for download
header('Content-Type: text/plain; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($content));
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

echo $content;
?>