<?php
// api/comments.php - Comments API endpoint for collaboration view
// No authentication required - open for anyone with the link

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$commentsDir = realpath(__DIR__ . '/../comments');
$contentDir = realpath(__DIR__ . '/../content');

if (!$commentsDir || !$contentDir) {
    http_response_code(500);
    echo json_encode(['error' => 'Server configuration error']);
    exit;
}

// Validate and sanitize file path
function validateFilePath($file, $contentDir) {
    if (empty($file)) return false;
    if (!preg_match('/\.md$/i', $file)) return false;
    if (strpos($file, '..') !== false || strpos($file, '\\') !== false) return false;
    // Check that the content file actually exists
    $fullPath = realpath($contentDir . '/' . $file);
    if (!$fullPath || !file_exists($fullPath) || strpos($fullPath, $contentDir) !== 0) return false;
    return true;
}

// Get the comments file path for a given content file
function getCommentsFilePath($file, $commentsDir) {
    return $commentsDir . '/' . $file . '.json';
}

// Read comments from JSON file with file locking
function readComments($commentsFile) {
    if (!file_exists($commentsFile)) {
        return null;
    }
    $handle = fopen($commentsFile, 'r');
    if (!$handle) return null;
    flock($handle, LOCK_SH);
    $content = stream_get_contents($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
    $data = json_decode($content, true);
    return $data;
}

// Write comments to JSON file with file locking
function writeComments($commentsFile, $data) {
    // Auto-create directory structure
    $dir = dirname($commentsFile);
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) {
            return false;
        }
    }
    $handle = fopen($commentsFile, 'c');
    if (!$handle) return false;
    flock($handle, LOCK_EX);
    ftruncate($handle, 0);
    rewind($handle);
    $result = fwrite($handle, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
    return $result !== false;
}

// Generate a unique comment ID
function generateCommentId() {
    return 'c_' . time() . '_' . bin2hex(random_bytes(4));
}

switch ($method) {
    case 'GET':
        // GET ?file=path/to/file.md - Fetch comments for a file
        $file = isset($_GET['file']) ? $_GET['file'] : '';
        if (!validateFilePath($file, $contentDir)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid file path']);
            exit;
        }

        $commentsFile = getCommentsFilePath($file, $commentsDir);
        $data = readComments($commentsFile);

        if ($data === null) {
            // No comments yet - return empty structure
            echo json_encode([
                'file' => $file,
                'comments' => []
            ]);
        } else {
            echo json_encode($data);
        }
        break;

    case 'POST':
        // Read JSON body
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON body']);
            exit;
        }

        $action = isset($input['action']) ? $input['action'] : 'create';
        $file = isset($input['file']) ? $input['file'] : '';

        if (!validateFilePath($file, $contentDir)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid file path']);
            exit;
        }

        $commentsFile = getCommentsFilePath($file, $commentsDir);

        if ($action === 'resolve') {
            // Resolve/unresolve a comment
            $commentId = isset($input['comment_id']) ? trim($input['comment_id']) : '';
            if (empty($commentId)) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing comment_id']);
                exit;
            }

            $data = readComments($commentsFile);
            if ($data === null) {
                http_response_code(404);
                echo json_encode(['error' => 'No comments found for this file']);
                exit;
            }

            $found = false;
            foreach ($data['comments'] as &$comment) {
                if ($comment['id'] === $commentId) {
                    $comment['resolved'] = !$comment['resolved'];
                    $found = true;
                    break;
                }
            }
            unset($comment);

            if (!$found) {
                http_response_code(404);
                echo json_encode(['error' => 'Comment not found']);
                exit;
            }

            if (!writeComments($commentsFile, $data)) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to save comments']);
                exit;
            }

            echo json_encode(['success' => true, 'data' => $data]);

        } else {
            // Create a new comment
            $blockId = isset($input['block_id']) ? trim($input['block_id']) : '';
            $blockText = isset($input['block_text']) ? trim($input['block_text']) : '';
            $author = isset($input['author']) ? trim($input['author']) : '';
            $text = isset($input['text']) ? trim($input['text']) : '';

            // Validate required fields
            if (empty($blockId) || empty($author) || empty($text)) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing required fields: block_id, author, text']);
                exit;
            }

            // Sanitize inputs
            $author = mb_substr($author, 0, 100);
            $text = mb_substr($text, 0, 5000);
            $blockText = mb_substr($blockText, 0, 50);

            // Read existing or create new structure
            $data = readComments($commentsFile);
            if ($data === null) {
                $data = [
                    'file' => $file,
                    'comments' => []
                ];
            }

            // Create new comment
            $newComment = [
                'id' => generateCommentId(),
                'block_id' => $blockId,
                'block_text' => $blockText,
                'author' => htmlspecialchars($author, ENT_QUOTES, 'UTF-8'),
                'text' => htmlspecialchars($text, ENT_QUOTES, 'UTF-8'),
                'created' => gmdate('Y-m-d\TH:i:s\Z'),
                'resolved' => false
            ];

            $data['comments'][] = $newComment;

            if (!writeComments($commentsFile, $data)) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to save comment']);
                exit;
            }

            echo json_encode(['success' => true, 'comment' => $newComment]);
        }
        break;

    case 'DELETE':
        // Delete a comment
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON body']);
            exit;
        }

        $file = isset($input['file']) ? $input['file'] : '';
        $commentId = isset($input['comment_id']) ? trim($input['comment_id']) : '';

        if (!validateFilePath($file, $contentDir)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid file path']);
            exit;
        }

        if (empty($commentId)) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing comment_id']);
            exit;
        }

        $commentsFile = getCommentsFilePath($file, $commentsDir);
        $data = readComments($commentsFile);

        if ($data === null) {
            http_response_code(404);
            echo json_encode(['error' => 'No comments found for this file']);
            exit;
        }

        $originalCount = count($data['comments']);
        $data['comments'] = array_values(array_filter($data['comments'], function($c) use ($commentId) {
            return $c['id'] !== $commentId;
        }));

        if (count($data['comments']) === $originalCount) {
            http_response_code(404);
            echo json_encode(['error' => 'Comment not found']);
            exit;
        }

        if (!writeComments($commentsFile, $data)) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to save comments']);
            exit;
        }

        echo json_encode(['success' => true]);
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}
