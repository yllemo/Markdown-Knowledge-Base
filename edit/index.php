<?php
// index.php - Markdown Editor (Monaco Editor)

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
            <button class="btn btn-primary" onclick="downloadFile()" title="Download markdown file">Download .md</button>
        </div>
    </div>
    <div id="editor-container"></div>

    <script>
        var fileContent = <?= json_encode($content, JSON_UNESCAPED_UNICODE) ?>;
        var fileName = <?= json_encode(basename($filename)) ?>;
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
    </script>
</body>
</html>
