<?php
// index.php - Markdown Editor (Monaco Editor) - Admin Version

// Require authentication for admin access
require_once '../config/config.php';
if (!isAuthenticated()) {
    // Show not authorized page instead of redirecting
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Access Denied - Admin Area</title>
        <style>
            body {
                font-family: system-ui, sans-serif;
                background: #f5f5f5;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                margin: 0;
            }
            .access-denied {
                background: white;
                padding: 40px;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                text-align: center;
                max-width: 400px;
            }
            .access-denied h1 {
                color: #dc3545;
                margin-bottom: 20px;
                font-size: 24px;
            }
            .access-denied p {
                color: #666;
                margin-bottom: 30px;
                line-height: 1.5;
            }
            .login-btn {
                display: inline-block;
                padding: 10px 20px;
                background: #0078d4;
                color: white;
                text-decoration: none;
                border-radius: 4px;
                font-weight: 500;
                transition: background 0.15s;
            }
            .login-btn:hover {
                background: #006cbd;
            }
        </style>
    </head>
    <body>
        <div class="access-denied">
            <h1>🔒 Access Denied</h1>
            <p>You must be logged in to access the admin area. Please authenticate to continue.</p>
            <a href="../login.php" class="login-btn">Go to Login</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$filename = isset($_GET['file']) ? $_GET['file'] : '';
$style = isset($_GET['style']) ? strtolower($_GET['style']) : 'light';

if (!$filename || !preg_match('/\.md$/i', $filename)) {
    die('Invalid file parameter. Must be a .md file.');
}

// Security check: prevent directory traversal attacks
if (strpos($filename, '..') !== false || strpos($filename, '\\') !== false) {
    die('Invalid file path.');
}

$contentDir = realpath(__DIR__ . '/../content');
$filePath = realpath($contentDir . '/' . $filename);

// Ensure the file is within the content directory and exists
if (!$filePath || !file_exists($filePath) || strpos($filePath, $contentDir) !== 0) {
    die('File not found.');
}

$content = file_get_contents($filePath);
$title = htmlspecialchars(pathinfo(basename($filename), PATHINFO_FILENAME));
$monacoTheme = $style === 'dark' ? 'vs-dark' : 'vs';
$isDark = $style === 'dark';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit: <?= $title ?></title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { height: 100%; overflow: hidden; }
        body {
            font-family: system-ui, sans-serif;
            background: <?= $isDark ? '#1e1e1e' : '#fff' ?>;
            color: <?= $isDark ? '#eee' : '#000' ?>;
            display: flex;
            flex-direction: column;
        }
        .toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 16px;
            background: <?= $isDark ? '#252526' : '#f3f3f3' ?>;
            border-bottom: 1px solid <?= $isDark ? '#3c3c3c' : '#ddd' ?>;
            flex-shrink: 0;
        }
        .toolbar-filename {
            font-size: 14px;
            font-weight: 500;
            color: <?= $isDark ? '#ccc' : '#333' ?>;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .toolbar-actions {
            display: flex;
            gap: 8px;
        }
        .btn {
            padding: 6px 14px;
            border: 1px solid <?= $isDark ? '#555' : '#ccc' ?>;
            border-radius: 4px;
            background: <?= $isDark ? '#3c3c3c' : '#fff' ?>;
            color: <?= $isDark ? '#eee' : '#333' ?>;
            font-size: 13px;
            cursor: pointer;
            transition: background 0.15s;
        }
        .btn:hover {
            background: <?= $isDark ? '#4c4c4c' : '#e8e8e8' ?>;
        }
        .btn-primary {
            background: #0078d4;
            color: #fff;
            border-color: #0078d4;
        }
        .btn-primary:hover {
            background: #006cbd;
        }
        .btn-success {
            background: #107c10;
            color: #fff;
            border-color: #107c10;
        }
        .btn-success:hover {
            background: #0e6e0e;
        }
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .status-indicator {
            display: inline-block;
            margin-left: 8px;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
        }
        .status-saved {
            background: #dff6dd;
            color: #0e5814;
            border: 1px solid #b3d9b3;
        }
        .status-modified {
            background: #fff4ce;
            color: #8a6d3b;
            border: 1px solid #faebcc;
        }
        .status-saving {
            background: #d9edf7;
            color: #31708f;
            border: 1px solid #bce8f1;
        }
        #editor-container {
            flex: 1;
            overflow: hidden;
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <div class="toolbar-filename"><?= htmlspecialchars($filename) ?></div>
        <div class="toolbar-actions">
            <button class="btn btn-success" onclick="saveFile()" title="Save changes to file" id="save-btn">Save</button>
            <button class="btn btn-primary" onclick="downloadFile()" title="Download markdown file">Download .md</button>
        </div>
    </div>
    <div id="editor-container"></div>

    <script>
        var fileContent = <?= json_encode($content, JSON_UNESCAPED_UNICODE) ?>;
        var fileName = <?= json_encode(basename($filename)) ?>;
        var filePath = <?= json_encode($filename) ?>;
        var originalContent = fileContent;
        var isModified = false;
        var isSaving = false;
    </script>
    <script src="https://cdn.jsdelivr.net/npm/monaco-editor@0.52.2/min/vs/loader.js"></script>
    <script>
        require.config({ paths: { vs: 'https://cdn.jsdelivr.net/npm/monaco-editor@0.52.2/min/vs' } });

        var editor;

        require(['vs/editor/editor.main'], function () {
            editor = monaco.editor.create(document.getElementById('editor-container'), {
                value: fileContent,
                language: 'markdown',
                theme: '<?= $monacoTheme ?>',
                wordWrap: 'on',
                minimap: { enabled: true },
                lineNumbers: 'on',
                fontSize: 14,
                automaticLayout: true,
                scrollBeyondLastLine: false,
                padding: { top: 10 }
            });
            
            // Track changes to show save status
            editor.onDidChangeModelContent(function() {
                var currentContent = editor.getValue();
                isModified = currentContent !== originalContent;
                updateSaveStatus();
            });
            
            updateSaveStatus();
        });

        function downloadFile() {
            var content = editor ? editor.getValue() : fileContent;
            var blob = new Blob([content], { type: 'text/markdown;charset=utf-8' });
            var url = URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = fileName;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }
        
        function saveFile() {
            if (!editor || isSaving) return;
            
            var content = editor.getValue();
            isSaving = true;
            updateSaveStatus();
            
            fetch('../api/files.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'save',
                    file: filePath,
                    content: content
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    originalContent = content;
                    isModified = false;
                } else {
                    alert('Error saving file: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                alert('Error saving file: ' + error.message);
            })
            .finally(() => {
                isSaving = false;
                updateSaveStatus();
            });
        }
        
        function updateSaveStatus() {
            var saveBtn = document.getElementById('save-btn');
            var toolbarFilename = document.querySelector('.toolbar-filename');
            
            // Remove existing status indicators
            var existingIndicator = document.querySelector('.status-indicator');
            if (existingIndicator) {
                existingIndicator.remove();
            }
            
            if (isSaving) {
                saveBtn.disabled = true;
                saveBtn.textContent = 'Saving...';
                var indicator = document.createElement('span');
                indicator.className = 'status-indicator status-saving';
                indicator.textContent = 'Saving...';
                toolbarFilename.appendChild(indicator);
            } else if (isModified) {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save';
                var indicator = document.createElement('span');
                indicator.className = 'status-indicator status-modified';
                indicator.textContent = 'Modified';
                toolbarFilename.appendChild(indicator);
            } else {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save';
                var indicator = document.createElement('span');
                indicator.className = 'status-indicator status-saved';
                indicator.textContent = 'Saved';
                toolbarFilename.appendChild(indicator);
            }
        }
        
        // Add keyboard shortcut for save (Ctrl+S)
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                saveFile();
            }
        });
    </script>
</body>
</html>
