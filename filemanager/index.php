<?php
// filemanager/index.php - Secure File Manager with MDKB authentication

// Start output buffering to prevent header issues
ob_start();

require_once '../config/config.php';

// Check authentication - use same logic as main MDKB
if (!isAuthenticated()) {
    header('Location: ../login.php');
    exit;
}

$baseDir = realpath(__DIR__ . '/../content');
$action = $_POST['action'] ?? null;
$target = $_POST['target'] ?? null;
$response = '';

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
    </style>
</head>
<body>
    <h1>📂 File Manager (../content)</h1>
    <?php if ($response): ?>
        <div class="response"><?= $response ?></div>
    <?php endif; ?>

    <?= listFoldersAndContents($baseDir, $baseDir) ?>

    <hr>
    <h3>📄 Files in root</h3>
    <?= listRootFiles($baseDir, $baseDir) ?>

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
    </script>
</body>
</html>
