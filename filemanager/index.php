<?php
// filemanager/index.php - Secure File Manager with MDKB authentication

// Start output buffering to prevent header issues
ob_start();

require_once '../config/config.php';
require_once '../classes/SearchEngine.php';

// Check authentication - use same logic as main MDKB
if (!isAuthenticated()) {
    header('Location: ../login.php');
    exit;
}

$baseDir = realpath(__DIR__ . '/../content');
$action = $_POST['action'] ?? null;
$target = $_POST['target'] ?? null;
$searchQuery = $_GET['search'] ?? '';
$response = '';

// Initialize search engine
$searchEngine = new SearchEngine('../content');
$searchResults = [];

// Handle search requests
if (!empty($searchQuery)) {
    $searchResults = $searchEngine->search($searchQuery);
    // Filter only markdown files
    $searchResults = array_filter($searchResults, function($file) {
        return preg_match('/\.(md|markdown)$/i', $file['name']);
    });
}

// Secure path check
function isInBaseDir($path, $base) {
    return strpos(realpath($path), $base) === 0;
}

// Recursive deletion
function deleteRecursive($path) {
    if (is_file($path)) {
        unlink($path);
    } elseif (is_dir($path)) {
        foreach (scandir($path) as $item) {
            if ($item === '.' || $item === '..') continue;
            deleteRecursive($path . DIRECTORY_SEPARATOR . $item);
        }
        rmdir($path);
    }
}

// Handle actions
if ($action && $target && isInBaseDir($target, $baseDir)) {
    if ($action === 'delete') {
        deleteRecursive($target);
        $response = "✅ Deleted: " . htmlspecialchars($target);
    } elseif ($action === 'rename' && isset($_POST['newname'])) {
        $newPath = dirname($target) . DIRECTORY_SEPARATOR . basename($_POST['newname']);
        rename($target, $newPath);
        $response = "✅ Renamed to: " . htmlspecialchars($newPath);
    } elseif ($action === 'save' && isset($_POST['content'])) {
        file_put_contents($target, $_POST['content']);
        $response = "✅ Saved: " . htmlspecialchars($target);
    }
}

// Read file content (AJAX)
if (isset($_GET['read'])) {
    $file = realpath($baseDir . '/' . $_GET['read']);
    if (isInBaseDir($file, $baseDir) && is_file($file)) {
        header('Content-Type: text/plain');
        echo file_get_contents($file);
    } else {
        http_response_code(404);
        echo "File not found.";
    }
    exit;
}

// Recursive folder listing
function listFoldersAndContents($dir, $base, $level = 0) {
    $result = '';
    $items = scandir($dir);
    sort($items);

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $item;

        if (is_dir($path)) {
            $relPath = str_replace($base . DIRECTORY_SEPARATOR, '', $path);
            $safePath = htmlspecialchars($path);
            $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $level);
            $name = htmlspecialchars(basename($item));

            $result .= "<div class='folder'>$indent<span>📁</span> <strong>$name</strong>
                <form method='POST' style='display:inline' onsubmit='return confirm(\"Delete folder and all contents?\")'>
                    <input type='hidden' name='target' value=\"$safePath\">
                    <input type='hidden' name='action' value='delete'>
                    <button type='submit'>Delete</button>
                </form>
            </div>";

            // List subfiles
            $subItems = scandir($path);
            sort($subItems);
            foreach ($subItems as $subItem) {
                if ($subItem === '.' || $subItem === '..') continue;
                $subPath = $path . DIRECTORY_SEPARATOR . $subItem;
                if (is_file($subPath)) {
                    $relFile = str_replace($base . DIRECTORY_SEPARATOR, '', $subPath);
                    $encodedRel = htmlspecialchars($relFile, ENT_QUOTES);
                    $safeSub = htmlspecialchars($subPath);
                    $result .= "<div class='file'>" . str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $level + 1) .
                        "<span>📄</span> " . htmlspecialchars($subItem) . "
                        <button onclick=\"editFile('$encodedRel')\">Edit</button>
                        <button onclick=\"renameFile('$encodedRel')\">Rename</button>
                        <form method='POST' style='display:inline' onsubmit='return confirm(\"Delete file?\")'>
                            <input type='hidden' name='target' value=\"$safeSub\">
                            <input type='hidden' name='action' value='delete'>
                            <button type='submit'>Delete</button>
                        </form>
                    </div>";
                }
            }

            // Recurse into folder
            $result .= listFoldersAndContents($path, $base, $level + 1);
        }
    }

    return $result;
}

// List files directly under root
function listRootFiles($dir, $base) {
    $result = '';
    $items = scandir($dir);
    sort($items);

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_file($path)) {
            $relPath = str_replace($base . DIRECTORY_SEPARATOR, '', $path);
            $safePath = htmlspecialchars($path);
            $encodedRelPath = htmlspecialchars($relPath, ENT_QUOTES);
            $name = htmlspecialchars($item);
            $viewUrl = "../view/?file=" . urlencode($relPath) . "&style=dark";
            $result .= "<div class='file'><span>📄</span> <a href=\"$viewUrl\" target=\"_blank\" style=\"color:#fff;\">$name</a>
                <button onclick=\"editFile('$encodedRelPath')\">Edit</button>
                <button onclick=\"renameFile('$encodedRelPath')\">Rename</button>
                <form method='POST' style='display:inline' onsubmit='return confirm(\"Delete file?\")'>
                    <input type='hidden' name='target' value=\"$safePath\">
                    <input type='hidden' name='action' value='delete'>
                    <button type='submit'>Delete</button>
                </form>
            </div>";
        }
    }

    return $result;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>📂 File Manager</title>
    <style>
        body {
            background: #1e1e1e;
            color: #ccc;
            font-family: sans-serif;
            padding: 20px;
        }
        h1, h3 {
            color: #fff;
        }
        .file, .folder {
            padding: 6px 10px;
            margin: 4px 0;
            background: #2b2b2b;
            border-radius: 5px;
        }
        button {
            margin-left: 10px;
            background: #444;
            border: none;
            color: #fff;
            padding: 4px 8px;
            border-radius: 3px;
            cursor: pointer;
        }
        button:hover {
            background: #666;
        }
        .modal {
            display: none;
            position: fixed;
            background: rgba(0,0,0,0.8);
            top: 0; left: 0; right: 0; bottom: 0;
            align-items: center;
            justify-content: center;
            z-index: 100;
        }
        .modal-content {
            background: #2a2a2a;
            padding: 20px;
            border-radius: 8px;
            width: 80%;
            max-width: 800px;
        }
        textarea {
            width: 100%;
            height: 400px;
            background: #111;
            color: #0f0;
            border: none;
            padding: 10px;
            font-family: monospace;
            font-size: 14px;
        }
        .response {
            background: #2d2d2d;
            padding: 10px;
            margin-bottom: 20px;
            border-left: 4px solid limegreen;
        }
        .search-container {
            background: #2b2b2b;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #444;
        }
        .search-box {
            width: 100%;
            max-width: 500px;
            padding: 10px;
            font-size: 16px;
            background: #1e1e1e;
            color: #ccc;
            border: 1px solid #555;
            border-radius: 5px;
        }
        .search-box:focus {
            outline: none;
            border-color: #007acc;
        }
        .search-results {
            background: #2d2d2d;
            padding: 15px;
            margin: 15px 0;
            border-radius: 8px;
            border-left: 4px solid #007acc;
        }
        .search-result-item {
            background: #3a3a3a;
            padding: 12px;
            margin: 8px 0;
            border-radius: 5px;
            border-left: 3px solid #007acc;
        }
        .search-result-title {
            color: #4db8ff;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .search-result-path {
            color: #888;
            font-size: 12px;
            margin-bottom: 8px;
        }
        .search-result-excerpt {
            color: #ccc;
            line-height: 1.4;
        }
        .file-list {
            transition: opacity 0.3s ease;
        }
        .file-list.filtered {
            opacity: 0.6;
        }
        .highlight {
            background-color: #ffd700;
            color: #000;
            padding: 1px 2px;
            border-radius: 2px;
        }
        .clear-search {
            background: #666;
            margin-left: 10px;
        }
        .clear-search:hover {
            background: #888;
        }
    </style>
</head>
<body>
    <h1>📂 File Manager (../content)</h1>
    <?php if ($response): ?>
        <div class="response"><?= $response ?></div>
    <?php endif; ?>

    <!-- Search Container -->
    <div class="search-container">
        <h3>🔍 Search Markdown Files</h3>
        <form method="GET" style="display: flex; align-items: center; gap: 10px;">
            <input type="text" name="search" class="search-box" 
                   placeholder="Search in markdown files (filename or content)..." 
                   value="<?= htmlspecialchars($searchQuery) ?>"
                   id="searchInput">
            <button type="submit">Search</button>
            <?php if (!empty($searchQuery)): ?>
                <button type="button" class="clear-search" onclick="clearSearch()">Clear</button>
            <?php endif; ?>
        </form>
        
        <div style="margin-top: 10px; color: #888; font-size: 14px;">
            <strong>Live Filter:</strong> Type below to filter visible files in real-time
        </div>
        <input type="text" id="liveFilter" class="search-box" 
               placeholder="Filter visible files by name..." 
               style="margin-top: 8px; max-width: 300px;">
    </div>

    <!-- Search Results -->
    <?php if (!empty($searchQuery)): ?>
        <div class="search-results">
            <h3>Search Results for "<?= htmlspecialchars($searchQuery) ?>"</h3>
            <?php if (empty($searchResults)): ?>
                <p>No markdown files found matching your search.</p>
            <?php else: ?>
                <p>Found <?= count($searchResults) ?> markdown file(s):</p>
                <?php foreach ($searchResults as $result): ?>
                    <div class="search-result-item">
                        <div class="search-result-title">
                            <?php 
                            $viewUrl = "../view/?file=" . urlencode($result['relative_path']) . "&style=dark";
                            ?>
                            <a href="<?= $viewUrl ?>" target="_blank" style="color: #4db8ff; text-decoration: none;">
                                📄 <?= htmlspecialchars($result['name']) ?>
                            </a>
                        </div>
                        <div class="search-result-path">
                            <?= htmlspecialchars($result['relative_path']) ?>
                        </div>
                        <?php if (!empty($result['excerpt'])): ?>
                            <div class="search-result-excerpt">
                                <?= $result['excerpt'] ?>
                            </div>
                        <?php endif; ?>
                        <div style="margin-top: 8px;">
                            <button onclick="editFile('<?= htmlspecialchars($result['relative_path'], ENT_QUOTES) ?>')" 
                                    style="font-size: 12px;">Edit</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="file-list" id="fileList">
        <?= listFoldersAndContents($baseDir, $baseDir) ?>

        <hr>
        <h3>📄 Files in root</h3>
        <?= listRootFiles($baseDir, $baseDir) ?>
    </div>

    <!-- Modal -->
    <div class="modal" id="editorModal">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="target" id="editTarget">
                <input type="hidden" name="action" value="save">
                <h3 id="filename"></h3>
                <textarea name="content" id="fileContent"></textarea>
                <button type="submit">Save</button>
                <button type="button" onclick="closeModal()">Cancel</button>
            </form>
        </div>
    </div>

    <form method="POST" id="renameForm" style="display:none;">
        <input type="hidden" name="target" id="renameTarget">
        <input type="hidden" name="action" value="rename">
        <input type="text" name="newname" placeholder="New filename">
        <button type="submit">Rename</button>
    </form>

    <script>
        function editFile(relPath) {
            const fullPath = "<?= $baseDir ?>/" + relPath;
            fetch("?read=" + encodeURIComponent(relPath))
                .then(res => res.ok ? res.text() : Promise.reject("File not found"))
                .then(text => {
                    document.getElementById('editTarget').value = fullPath;
                    document.getElementById('fileContent').value = text;
                    document.getElementById('filename').innerText = relPath;
                    document.getElementById('editorModal').style.display = 'flex';
                })
                .catch(err => alert(err));
        }

        function renameFile(relPath) {
            const fullPath = "<?= $baseDir ?>/" + relPath;
            const form = document.getElementById('renameForm');
            document.getElementById('renameTarget').value = fullPath;
            const newName = prompt("New name:", relPath.split('/').pop());
            if (newName) {
                form.querySelector('input[name="newname"]').value = newName;
                form.submit();
            }
        }

        function closeModal() {
            document.getElementById('editorModal').style.display = 'none';
        }

        function clearSearch() {
            window.location.href = window.location.pathname;
        }

        // Live filtering functionality
        document.getElementById('liveFilter').addEventListener('input', function(e) {
            const filterText = e.target.value.toLowerCase();
            const fileList = document.getElementById('fileList');
            const fileItems = fileList.querySelectorAll('.file, .folder');
            let visibleCount = 0;

            fileItems.forEach(function(item) {
                const text = item.textContent.toLowerCase();
                const isMarkdown = text.includes('.md') || text.includes('.markdown');
                
                if (filterText === '' || (text.includes(filterText) && isMarkdown)) {
                    item.style.display = 'block';
                    if (isMarkdown || item.classList.contains('folder')) {
                        visibleCount++;
                    }
                } else {
                    item.style.display = 'none';
                }
            });

            // Visual feedback
            if (filterText !== '') {
                fileList.classList.add('filtered');
            } else {
                fileList.classList.remove('filtered');
            }
        });

        // Highlight search terms in results
        function highlightSearchTerms() {
            const searchQuery = '<?= addslashes($searchQuery) ?>';
            if (searchQuery) {
                const terms = searchQuery.split(' ');
                const excerpts = document.querySelectorAll('.search-result-excerpt');
                
                excerpts.forEach(function(excerpt) {
                    let html = excerpt.innerHTML;
                    terms.forEach(function(term) {
                        if (term.length > 2) {
                            const regex = new RegExp('(' + term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
                            html = html.replace(regex, '<span class="highlight">$1</span>');
                        }
                    });
                    excerpt.innerHTML = html;
                });
            }
        }

        // Initialize highlighting on page load
        document.addEventListener('DOMContentLoaded', highlightSearchTerms);

        // Focus search box on Ctrl+F
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'f') {
                e.preventDefault();
                document.getElementById('searchInput').focus();
            }
        });
    </script>
</body>
</html>
