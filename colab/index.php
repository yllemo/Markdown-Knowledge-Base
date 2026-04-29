<?php
// colab/index.php - Collaboration View with Comments

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

// Include Parsedown + ParsedownExtra
require_once __DIR__ . '/Parsedown.php';
require_once __DIR__ . '/ParsedownExtra.php';

$content = file_get_contents($filePath);

// Remove YAML front matter
$content = preg_replace('/^---[\s\S]*?---\s+/', '', $content, 1);

$Parsedown = new ParsedownExtra();
$Parsedown->setSafeMode(false);
$html = $Parsedown->text($content);

// Convert Mermaid code blocks to divs for rendering with data attribute for code
$html = preg_replace_callback(
    '/<pre><code class="language-mermaid">(.*?)<\/code><\/pre>/s',
    function ($matches) {
        $mermaidCode = htmlspecialchars_decode($matches[1]);
        $trimmedCode = trim($mermaidCode);
        return '<div class="mermaid" data-mermaid-code="' . htmlspecialchars($trimmedCode, ENT_QUOTES) . '">' . $trimmedCode . '</div>';
    },
    $html
);

// Convert Markdown checkboxes to real HTML checkboxes (read-only in colab view)
$checkboxIndex = 0;
$html = preg_replace_callback(
    '/<li>\s*\[([ xX])\]\s*(.*?)<\/li>/',
    function ($matches) use (&$checkboxIndex, $filename) {
        $checked = strtolower($matches[1]) === 'x' ? 'checked disabled' : 'disabled';
        $checkboxId = 'checkbox-' . md5($filename) . '-' . $checkboxIndex++;
        return '<li><input type="checkbox" class="interactive-checkbox" data-checkbox-id="' . $checkboxId . '" ' . $checked . '> ' . $matches[2] . '</li>';
    },
    $html
);

// Process SVG images with inversion hints
$html = preg_replace_callback(
    '/<img([^>]*?)src="([^"]*\.svg)"([^>]*?)title="invert:(white|black)"([^>]*?)>/i',
    function ($matches) {
        $beforeSrc = $matches[1];
        $src = $matches[2];
        $afterSrc = $matches[3];
        $invertType = strtolower($matches[4]);
        $afterTitle = $matches[5];
        $class = 'svg-invertible svg-invert-' . $invertType;
        if (strpos($beforeSrc . $afterSrc . $afterTitle, 'class=') !== false) {
            $combined = $beforeSrc . $afterSrc . $afterTitle;
            $combined = preg_replace('/class="([^"]*)"/', 'class="$1 ' . $class . '"', $combined);
            return '<img' . $combined . 'src="' . $src . '">';
        } else {
            return '<img' . $beforeSrc . 'src="' . $src . '" class="' . $class . '"' . $afterSrc . $afterTitle . '>';
        }
    },
    $html
);

// Wrap block-level elements with comment-block divs
$blockIndex = 0;
$html = preg_replace_callback(
    '/(<(?:h[1-6]|p|ul|ol|blockquote|pre|table|div class="mermaid")[^>]*>)/i',
    function ($matches) use (&$blockIndex) {
        $tag = $matches[1];
        // Determine element type for block ID
        if (preg_match('/^<(h[1-6]|p|ul|ol|blockquote|pre|table|div)/i', $tag, $typeMatch)) {
            $type = strtolower($typeMatch[1]);
            if ($type === 'div') $type = 'mermaid';
            $id = $type . '-' . $blockIndex++;
            return '<div class="comment-block" data-block-id="' . $id . '">' . $tag;
        }
        return $tag;
    },
    $html
);

// Close the comment-block divs after closing tags (non-div elements)
$html = preg_replace(
    '/(<\/(?:h[1-6]|p|ul|ol|blockquote|pre|table)>)/i',
    '$1</div>',
    $html
);

// For mermaid divs wrapped in comment-block, add the closing </div> after the mermaid's </div>
// Pattern: <div class="comment-block" ...><div class="mermaid" ...>...</div> needs </div> appended
$html = preg_replace(
    '/(<div class="comment-block"[^>]*>)(<div class="mermaid"[^>]*>.*?<\/div>)/s',
    '$1$2</div>',
    $html
);

$title = htmlspecialchars(pathinfo(basename($filename), PATHINFO_FILENAME));
$darkClass = $style === 'dark' ? 'dark' : 'light';
$filenameJs = htmlspecialchars($filename, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?> - Colab</title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">

    <!-- Prism.js for syntax highlighting -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism-tomorrow.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism.min.css" rel="stylesheet" />

    <style>
        *, *::before, *::after { box-sizing: border-box; }

        body {
            font-family: system-ui, sans-serif;
            line-height: 1.6;
            margin: 0 auto;
            max-width: 900px;
            padding: 2rem;
            padding-right: 3.5rem;
            background: #fff;
            color: #000;
            transition: background 0.3s, color 0.3s;
        }
        body.dark {
            background: #111;
            color: #eee;
        }
        h1, h2, h3, h4, h5 { margin-top: 1.4em; }
        a { color: #0077cc; }
        body.dark a { color: #66aaff; }

        code:not([class*="language-"]) {
            background: rgba(0,0,0,0.05);
            padding: 0.2em 0.4em;
            border-radius: 4px;
            font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
        }
        body.dark code:not([class*="language-"]) { background: rgba(255,255,255,0.1); }

        pre[class*="language-"] { margin: 1em 0; border-radius: 8px; overflow: hidden; }
        pre code[class*="language-"] { display: block; padding: 1em; overflow-x: auto; background: transparent !important; }

        /* Mermaid */
        .mermaid { text-align: center; margin: 1.5em 0; background: transparent; }

        blockquote {
            border-left: 6px solid #66aaff;
            padding: 0.6em 1em;
            margin: 1.2em 0;
            font-style: italic;
            background: rgba(102,170,255,0.08);
            border-radius: 6px;
        }
        blockquote p { margin: 0; }
        table { border-collapse: collapse; margin: 1em 0; width: 100%; }
        th, td { border: 1px solid rgba(200,200,200,0.2); padding: 0.5em 1em; text-align: left; }
        thead th { background: rgba(0,0,0,0.05); }
        body.dark thead th { background: rgba(255,255,255,0.1); }

        input[type=checkbox] {
            transform: scale(1.3);
            margin-right: 0.4em;
            accent-color: #66aaff;
        }
        input[type=checkbox].interactive-checkbox:checked { opacity: 0.5; }
        li:has(input[type=checkbox].interactive-checkbox:checked) {
            color: #888; text-decoration: line-through; opacity: 0.7;
        }
        body.dark li:has(input[type=checkbox].interactive-checkbox:checked) { color: #666; }

        .svg-invertible { transition: filter 0.3s ease; }
        body.dark .svg-invertible.svg-invert-white { filter: invert(1); }
        body.light .svg-invertible.svg-invert-black { filter: invert(1); }

        /* ===== Comment Block Styling ===== */
        .comment-block {
            position: relative;
            transition: background 0.15s ease;
            border-radius: 4px;
        }
        .comment-block:hover {
            background: rgba(102, 170, 255, 0.04);
        }
        .comment-block .comment-trigger {
            position: absolute;
            right: -2.5rem;
            top: 0.2em;
            width: 28px;
            height: 28px;
            border: none;
            background: transparent;
            cursor: pointer;
            opacity: 0;
            transition: opacity 0.15s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            font-size: 16px;
            color: #888;
            padding: 0;
        }
        .comment-block:hover .comment-trigger,
        .comment-block .comment-trigger.has-comments {
            opacity: 1;
        }
        .comment-block .comment-trigger:hover {
            background: rgba(102, 170, 255, 0.15);
            color: #0077cc;
        }
        body.dark .comment-block .comment-trigger:hover {
            background: rgba(102, 170, 255, 0.2);
            color: #66aaff;
        }
        .comment-trigger .comment-badge {
            position: absolute;
            top: -4px;
            right: -4px;
            background: #0077cc;
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            min-width: 16px;
            height: 16px;
            line-height: 16px;
            text-align: center;
            border-radius: 8px;
            padding: 0 4px;
        }
        body.dark .comment-trigger .comment-badge {
            background: #66aaff;
            color: #111;
        }

        /* ===== Comment Panel ===== */
        .comment-panel-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.3);
            z-index: 9998;
        }
        .comment-panel-overlay.open { display: block; }

        .comment-panel {
            position: fixed;
            top: 0;
            right: -420px;
            width: 400px;
            max-width: 90vw;
            height: 100vh;
            background: #fff;
            border-left: 1px solid #ddd;
            box-shadow: -4px 0 20px rgba(0,0,0,0.1);
            z-index: 9999;
            display: flex;
            flex-direction: column;
            transition: right 0.25s ease;
            overflow: hidden;
        }
        .comment-panel.open { right: 0; }

        body.dark .comment-panel {
            background: #1a1a1a;
            border-left-color: #333;
            box-shadow: -4px 0 20px rgba(0,0,0,0.4);
        }

        .comment-panel-header {
            padding: 1rem 1.2rem;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
        }
        
        .comment-panel-toolbar {
            padding: 0.6rem 1.2rem;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
            background: rgba(0,0,0,0.02);
        }
        body.dark .comment-panel-toolbar {
            border-bottom-color: #333;
            background: rgba(255,255,255,0.02);
        }
        
        .comment-stats {
            font-size: 0.75rem;
            color: #888;
        }
        
        .comment-actions-toolbar {
            display: flex;
            gap: 0.5rem;
        }
        
        .toolbar-btn {
            background: none;
            border: 1px solid #ddd;
            padding: 0.3rem 0.6rem;
            border-radius: 4px;
            font-size: 0.75rem;
            cursor: pointer;
            color: #666;
            transition: all 0.15s;
        }
        .toolbar-btn:hover {
            background: #0077cc;
            color: #fff;
            border-color: #0077cc;
        }
        body.dark .toolbar-btn {
            border-color: #444;
            color: #aaa;
        }
        body.dark .toolbar-btn:hover {
            background: #66aaff;
            color: #111;
            border-color: #66aaff;
        }
        body.dark .comment-panel-header { border-bottom-color: #333; }

        .comment-panel-header h3 {
            margin: 0;
            font-size: 0.95rem;
            font-weight: 600;
        }
        .comment-panel-close {
            background: none;
            border: none;
            font-size: 1.4rem;
            cursor: pointer;
            color: #888;
            padding: 0.2rem 0.4rem;
            border-radius: 4px;
            line-height: 1;
        }
        .comment-panel-close:hover { background: rgba(0,0,0,0.05); color: #333; }
        body.dark .comment-panel-close:hover { background: rgba(255,255,255,0.1); color: #eee; }

        .comment-panel-blocktext {
            padding: 0.6rem 1.2rem;
            font-size: 0.8rem;
            color: #888;
            border-bottom: 1px solid #eee;
            background: rgba(0,0,0,0.02);
            flex-shrink: 0;
        }
        body.dark .comment-panel-blocktext {
            border-bottom-color: #333;
            background: rgba(255,255,255,0.02);
        }

        .comment-panel-list {
            flex: 1;
            overflow-y: auto;
            padding: 0.5rem 0;
        }

        .comment-item {
            padding: 0.8rem 1.2rem;
            border-bottom: 1px solid #f0f0f0;
        }
        body.dark .comment-item { border-bottom-color: #2a2a2a; }

        .comment-item.resolved {
            opacity: 0.5;
        }

        .comment-item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.3rem;
        }
        .comment-author {
            font-weight: 600;
            font-size: 0.85rem;
        }
        .comment-date {
            font-size: 0.75rem;
            color: #999;
        }
        .comment-text {
            font-size: 0.9rem;
            line-height: 1.5;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .comment-actions {
            margin-top: 0.4rem;
            display: flex;
            gap: 0.5rem;
        }
        .comment-action-btn {
            background: none;
            border: none;
            font-size: 0.75rem;
            color: #888;
            cursor: pointer;
            padding: 0.15rem 0.3rem;
            border-radius: 3px;
        }
        .comment-action-btn:hover { color: #0077cc; background: rgba(0,119,204,0.08); }
        body.dark .comment-action-btn:hover { color: #66aaff; background: rgba(102,170,255,0.1); }

        .comment-empty {
            padding: 2rem 1.2rem;
            text-align: center;
            color: #999;
            font-size: 0.9rem;
        }
        
        .comment-filter {
            padding: 0.4rem 1.2rem;
            border-bottom: 1px solid #eee;
            flex-shrink: 0;
        }
        body.dark .comment-filter {
            border-bottom-color: #333;
        }
        
        .filter-tabs {
            display: flex;
            gap: 0.5rem;
        }
        
        .filter-tab {
            background: none;
            border: none;
            padding: 0.3rem 0.6rem;
            font-size: 0.75rem;
            cursor: pointer;
            border-radius: 4px;
            color: #888;
            transition: all 0.15s;
        }
        .filter-tab.active {
            background: #0077cc;
            color: #fff;
        }
        body.dark .filter-tab.active {
            background: #66aaff;
            color: #111;
        }
        .filter-tab:hover:not(.active) {
            background: rgba(0,119,204,0.1);
            color: #0077cc;
        }
        body.dark .filter-tab:hover:not(.active) {
            background: rgba(102,170,255,0.1);
            color: #66aaff;
        }

        .comment-panel-form {
            padding: 1rem 1.2rem;
            border-top: 1px solid #eee;
            flex-shrink: 0;
        }
        body.dark .comment-panel-form { border-top-color: #333; }

        .comment-panel-form textarea {
            width: 100%;
            min-height: 70px;
            padding: 0.6rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-family: system-ui, sans-serif;
            font-size: 0.9rem;
            resize: vertical;
            background: #fff;
            color: #000;
        }
        body.dark .comment-panel-form textarea {
            background: #222;
            color: #eee;
            border-color: #444;
        }
        .comment-panel-form textarea:focus {
            outline: none;
            border-color: #0077cc;
            box-shadow: 0 0 0 2px rgba(0,119,204,0.15);
        }
        body.dark .comment-panel-form textarea:focus {
            border-color: #66aaff;
            box-shadow: 0 0 0 2px rgba(102,170,255,0.15);
        }

        .comment-form-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 0.5rem;
        }
        .comment-form-author {
            font-size: 0.8rem;
            color: #888;
            cursor: pointer;
        }
        .comment-form-author:hover { text-decoration: underline; }

        .comment-form-submit {
            padding: 0.4rem 1rem;
            background: #0077cc;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.15s;
        }
        .comment-form-submit:hover { background: #005fa3; }
        .comment-form-submit:disabled { opacity: 0.5; cursor: not-allowed; }
        body.dark .comment-form-submit { background: #66aaff; color: #111; }
        body.dark .comment-form-submit:hover { background: #88bbff; }

        /* ===== Name Dialog ===== */
        .name-dialog-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 10000;
            justify-content: center;
            align-items: center;
        }
        .name-dialog-overlay.open { display: flex; }

        .name-dialog {
            background: #fff;
            border-radius: 12px;
            padding: 1.5rem;
            width: 320px;
            max-width: 90vw;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        body.dark .name-dialog {
            background: #1a1a1a;
            box-shadow: 0 10px 40px rgba(0,0,0,0.5);
        }
        .name-dialog h3 { margin: 0 0 0.5rem 0; font-size: 1.1rem; }
        .name-dialog p { margin: 0 0 1rem 0; font-size: 0.85rem; color: #888; }
        .name-dialog input {
            width: 100%;
            padding: 0.6rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.95rem;
            background: #fff;
            color: #000;
        }
        body.dark .name-dialog input {
            background: #222;
            color: #eee;
            border-color: #444;
        }
        .name-dialog input:focus {
            outline: none;
            border-color: #0077cc;
        }
        .name-dialog-actions {
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        .name-dialog-btn {
            padding: 0.4rem 1rem;
            border: none;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
        }
        .name-dialog-btn.primary {
            background: #0077cc;
            color: #fff;
        }
        .name-dialog-btn.primary:hover { background: #005fa3; }
        body.dark .name-dialog-btn.primary { background: #66aaff; color: #111; }
        .name-dialog-btn.secondary {
            background: #eee;
            color: #333;
        }
        body.dark .name-dialog-btn.secondary { background: #333; color: #eee; }

        /* Colab header bar */
        .colab-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            margin-bottom: 1rem;
            border-bottom: 1px solid #eee;
            font-size: 0.8rem;
            color: #888;
        }
        body.dark .colab-header { border-bottom-color: #333; }
        .colab-header-label {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .colab-header-user {
            cursor: pointer;
        }
        .colab-header-user:hover { text-decoration: underline; }
    </style>
</head>
<body class="<?= $darkClass ?>">
    <!-- Colab header -->
    <div class="colab-header">
        <span class="colab-header-label">Collaboration View</span>
        <span class="colab-header-user" id="colabUser" title="Click to change name"></span>
    </div>

    <div class="markdown-body">
        <?= $html ?>
    </div>

    <!-- Comment Panel (slide-in from right) -->
    <div class="comment-panel-overlay" id="commentOverlay"></div>
    <div class="comment-panel" id="commentPanel">
        <div class="comment-panel-header">
            <h3 id="commentPanelTitle">Comments</h3>
            <button class="comment-panel-close" id="commentPanelClose" title="Close">&times;</button>
        </div>
        <div class="comment-panel-toolbar">
            <div class="comment-stats" id="commentStats">0 comments</div>
            <div class="comment-actions-toolbar">
                <button class="toolbar-btn" id="exportComments" title="Export all comments to text file (Client)">📥 Export</button>
                <button class="toolbar-btn" id="exportCommentsServer" title="Export all comments via server download">📥 Server</button>
                <button class="toolbar-btn" id="resolveAll" title="Mark all comments as resolved">✓ Resolve All</button>
            </div>
        </div>
        <div class="comment-filter">
            <div class="filter-tabs">
                <button class="filter-tab active" data-filter="all">All</button>
                <button class="filter-tab" data-filter="unresolved">Open</button>
                <button class="filter-tab" data-filter="resolved">Resolved</button>
            </div>
        </div>
        <div class="comment-panel-blocktext" id="commentPanelBlockText"></div>
        <div class="comment-panel-list" id="commentPanelList"></div>
        <div class="comment-panel-form">
            <textarea id="commentInput" placeholder="Write a comment..."></textarea>
            <div class="comment-form-footer">
                <span class="comment-form-author" id="commentFormAuthor" title="Click to change name"></span>
                <button class="comment-form-submit" id="commentSubmit">Send</button>
            </div>
        </div>
    </div>

    <!-- Name Dialog -->
    <div class="name-dialog-overlay" id="nameDialogOverlay">
        <div class="name-dialog">
            <h3>Your name</h3>
            <p>Enter your name to leave comments.</p>
            <input type="text" id="nameDialogInput" placeholder="e.g. Anna" maxlength="100" autocomplete="off">
            <div class="name-dialog-actions">
                <button class="name-dialog-btn secondary" id="nameDialogCancel">Cancel</button>
                <button class="name-dialog-btn primary" id="nameDialogSave">Save</button>
            </div>
        </div>
    </div>

    <!-- Prism.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-core.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/plugins/autoloader/prism-autoloader.min.js"></script>

    <!-- Mermaid -->
    <script type="module">
        import mermaid from 'https://cdn.jsdelivr.net/npm/mermaid@11/dist/mermaid.esm.min.mjs';
        const isDark = document.body.classList.contains('dark');
        if (isDark) {
            const lt = document.querySelector('link[href*="prism.min.css"]');
            if (lt) lt.disabled = true;
        } else {
            const dt = document.querySelector('link[href*="prism-tomorrow.min.css"]');
            if (dt) dt.disabled = true;
        }
        mermaid.registerIconPacks([
            { name: 'fa', loader: () => fetch('https://unpkg.com/@iconify-json/fa@1/icons.json').then(r => r.json()) },
            { name: 'mdi', loader: () => fetch('https://unpkg.com/@iconify-json/mdi@1/icons.json').then(r => r.json()) },
            { name: 'logos', loader: () => fetch('https://unpkg.com/@iconify-json/logos@1/icons.json').then(r => r.json()) },
            { name: 'simple-icons', loader: () => fetch('https://unpkg.com/@iconify-json/simple-icons@1/icons.json').then(r => r.json()) },
            { name: 'material-symbols', loader: () => fetch('https://unpkg.com/@iconify-json/material-symbols@1/icons.json').then(r => r.json()) },
        ]);
        mermaid.initialize({
            startOnLoad: true,
            theme: isDark ? 'dark' : 'default',
            themeVariables: {
                background: isDark ? '#111' : '#fff',
                primaryColor: isDark ? '#66aaff' : '#0077cc',
                primaryTextColor: isDark ? '#eee' : '#000',
                primaryBorderColor: isDark ? '#444' : '#ccc',
                lineColor: isDark ? '#666' : '#333',
                secondaryColor: isDark ? '#333' : '#f9f9f9',
                tertiaryColor: isDark ? '#222' : '#f0f0f0'
            },
            securityLevel: 'loose',
            flowchart: { useMaxWidth: true, htmlLabels: true },
            icons: true
        });
        window.mermaid = mermaid;
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof Prism !== 'undefined') Prism.highlightAll();
        });
    </script>

    <!-- Comment System -->
    <script>
    (function() {
        const FILE = '<?= $filenameJs ?>';
        const API_URL = '../api/comments.php';
        const STORAGE_KEY = 'colab_author_name';

        // State
        let authorName = localStorage.getItem(STORAGE_KEY) || '';
        let allComments = []; // flat array of comments
        let currentBlockId = null;

        // DOM refs
        const panel = document.getElementById('commentPanel');
        const overlay = document.getElementById('commentOverlay');
        const panelList = document.getElementById('commentPanelList');
        const panelTitle = document.getElementById('commentPanelTitle');
        const panelBlockText = document.getElementById('commentPanelBlockText');
        const panelClose = document.getElementById('commentPanelClose');
        const commentInput = document.getElementById('commentInput');
        const commentSubmit = document.getElementById('commentSubmit');
        const commentFormAuthor = document.getElementById('commentFormAuthor');
        const colabUser = document.getElementById('colabUser');
        const nameOverlay = document.getElementById('nameDialogOverlay');
        const nameInput = document.getElementById('nameDialogInput');
        const nameSave = document.getElementById('nameDialogSave');
        const nameCancel = document.getElementById('nameDialogCancel');

        // ---- Name management ----
        function updateAuthorDisplay() {
            const display = authorName || 'Anonymous';
            commentFormAuthor.textContent = 'Posting as: ' + display;
            colabUser.textContent = display;
        }

        function showNameDialog(callback) {
            nameInput.value = authorName;
            nameOverlay.classList.add('open');
            nameInput.focus();
            nameInput.select();

            const onSave = () => {
                const val = nameInput.value.trim();
                if (val) {
                    authorName = val;
                    localStorage.setItem(STORAGE_KEY, authorName);
                    updateAuthorDisplay();
                }
                nameOverlay.classList.remove('open');
                cleanup();
                if (callback) callback();
            };
            const onCancel = () => {
                nameOverlay.classList.remove('open');
                cleanup();
            };
            const onKeydown = (e) => {
                if (e.key === 'Enter') onSave();
                if (e.key === 'Escape') onCancel();
            };
            const cleanup = () => {
                nameSave.removeEventListener('click', onSave);
                nameCancel.removeEventListener('click', onCancel);
                nameInput.removeEventListener('keydown', onKeydown);
            };
            nameSave.addEventListener('click', onSave);
            nameCancel.addEventListener('click', onCancel);
            nameInput.addEventListener('keydown', onKeydown);
        }

        colabUser.addEventListener('click', () => showNameDialog());
        commentFormAuthor.addEventListener('click', () => showNameDialog());

        // ---- API calls ----
        async function fetchComments() {
            try {
                const resp = await fetch(API_URL + '?file=' + encodeURIComponent(FILE));
                if (!resp.ok) return;
                const data = await resp.json();
                allComments = data.comments || [];
                updateAllBadges();
                updateCommentStats();
            } catch (e) {
                console.error('Failed to fetch comments:', e);
            }
        }

        async function postComment(blockId, blockText, text) {
            const resp = await fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    file: FILE,
                    block_id: blockId,
                    block_text: blockText,
                    author: authorName || 'Anonymous',
                    text: text
                })
            });
            if (!resp.ok) throw new Error('Failed to post comment');
            const data = await resp.json();
            if (data.comment) {
                allComments.push(data.comment);
            }
            return data;
        }

        async function resolveComment(commentId) {
            const resp = await fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'resolve',
                    file: FILE,
                    comment_id: commentId
                })
            });
            if (!resp.ok) throw new Error('Failed to resolve comment');
            const data = await resp.json();
            if (data.data) {
                allComments = data.data.comments || [];
            }
            return data;
        }

        async function deleteComment(commentId) {
            const resp = await fetch(API_URL, {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    file: FILE,
                    comment_id: commentId
                })
            });
            if (!resp.ok) throw new Error('Failed to delete comment');
            allComments = allComments.filter(c => c.id !== commentId);
        }

        // ---- Badge / trigger buttons ----
        function getBlockComments(blockId) {
            return allComments.filter(c => c.block_id === blockId);
        }

        function updateAllBadges() {
            document.querySelectorAll('.comment-block').forEach(block => {
                const blockId = block.dataset.blockId;
                const trigger = block.querySelector('.comment-trigger');
                if (!trigger) return;
                const count = getBlockComments(blockId).length;
                let badge = trigger.querySelector('.comment-badge');
                if (count > 0) {
                    if (!badge) {
                        badge = document.createElement('span');
                        badge.className = 'comment-badge';
                        trigger.appendChild(badge);
                    }
                    badge.textContent = count;
                    trigger.classList.add('has-comments');
                } else {
                    if (badge) badge.remove();
                    trigger.classList.remove('has-comments');
                }
            });
        }

        function initBlocks() {
            document.querySelectorAll('.comment-block').forEach(block => {
                const btn = document.createElement('button');
                btn.className = 'comment-trigger';
                btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2 2h12v9H5l-3 3V2z"/></svg>';
                btn.title = 'Comment on this block';
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    openPanel(block.dataset.blockId, block);
                });
                block.appendChild(btn);
            });
        }

        // ---- Panel ----
        function getBlockPreview(block) {
            const text = block.textContent || '';
            return text.trim().substring(0, 80).replace(/\s+/g, ' ') + (text.length > 80 ? '...' : '');
        }

        function openPanel(blockId, blockEl) {
            // If no author name, prompt first
            if (!authorName) {
                showNameDialog(() => openPanel(blockId, blockEl));
                return;
            }

            currentBlockId = blockId;
            panelBlockText.textContent = getBlockPreview(blockEl);
            renderPanelComments(blockId);
            panel.classList.add('open');
            overlay.classList.add('open');
            commentInput.value = '';
            commentInput.focus();
        }

        function closePanel() {
            panel.classList.remove('open');
            overlay.classList.remove('open');
            currentBlockId = null;
        }

        panelClose.addEventListener('click', closePanel);
        overlay.addEventListener('click', closePanel);
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && panel.classList.contains('open')) closePanel();
        });

        function formatDate(isoStr) {
            try {
                const d = new Date(isoStr);
                return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' }) +
                       ' ' + d.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
            } catch { return isoStr; }
        }

        function decodeHtml(html) {
            const el = document.createElement('textarea');
            el.innerHTML = html;
            return el.value;
        }

        function renderPanelComments(blockId) {
            const comments = getBlockComments(blockId);
            if (comments.length === 0) {
                panelList.innerHTML = '<div class="comment-empty">No comments yet. Be the first!</div>';
                return;
            }
            panelList.innerHTML = '';
            comments.forEach(c => {
                const item = document.createElement('div');
                item.className = 'comment-item' + (c.resolved ? ' resolved' : '');
                item.innerHTML =
                    '<div class="comment-item-header">' +
                        '<span class="comment-author">' + decodeHtml(c.author) + '</span>' +
                        '<span class="comment-date">' + formatDate(c.created) + '</span>' +
                    '</div>' +
                    '<div class="comment-text">' + decodeHtml(c.text) + '</div>' +
                    '<div class="comment-actions">' +
                        '<button class="comment-action-btn resolve-btn">' + (c.resolved ? 'Unresolve' : 'Resolve') + '</button>' +
                        '<button class="comment-action-btn delete-btn">Delete</button>' +
                    '</div>';

                item.querySelector('.resolve-btn').addEventListener('click', async () => {
                    try {
                        await resolveComment(c.id);
                        renderPanelComments(blockId);
                        updateAllBadges();
                    } catch (e) { console.error(e); }
                });
                item.querySelector('.delete-btn').addEventListener('click', async () => {
                    if (!confirm('Delete this comment?')) return;
                    try {
                        await deleteComment(c.id);
                        renderPanelComments(blockId);
                        updateAllBadges();
                    } catch (e) { console.error(e); }
                });
                panelList.appendChild(item);
            });
        }

        // ---- Submit comment ----
        commentSubmit.addEventListener('click', submitComment);
        commentInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) submitComment();
        });

        async function submitComment() {
            const text = commentInput.value.trim();
            if (!text || !currentBlockId) return;
            if (!authorName) {
                showNameDialog(() => submitComment());
                return;
            }
            commentSubmit.disabled = true;
            try {
                const blockEl = document.querySelector('[data-block-id="' + currentBlockId + '"]');
                const blockText = blockEl ? blockEl.textContent.trim().substring(0, 50) : '';
                await postComment(currentBlockId, blockText, text);
                commentInput.value = '';
                renderPanelComments(currentBlockId);
                updateAllBadges();
            } catch (e) {
                console.error('Failed to submit comment:', e);
                alert('Failed to submit comment. Please try again.');
            } finally {
                commentSubmit.disabled = false;
            }
        }

        // ---- New features: Export, Statistics, Filtering ----
        let currentFilter = 'all';
        
        function updateCommentStats() {
            const total = allComments.length;
            const resolved = allComments.filter(c => c.resolved).length;
            const unresolved = total - resolved;
            const statsEl = document.getElementById('commentStats');
            if (total === 0) {
                statsEl.textContent = 'No comments';
            } else if (total === 1) {
                statsEl.textContent = '1 comment';
            } else {
                statsEl.textContent = `${total} comments (${unresolved} open, ${resolved} resolved)`;
            }
        }
        
        function exportCommentsToFile() {
            if (allComments.length === 0) {
                alert('No comments to export.');
                return;
            }
            
            const now = new Date();
            const dateStr = now.getFullYear() + '-' + 
                            String(now.getMonth() + 1).padStart(2, '0') + '-' + 
                            String(now.getDate()).padStart(2, '0');
            const filename = `comments_${dateStr}.txt`;
            
            let content = `Comments Export - ${FILE}\n`;
            content += `Generated: ${now.toLocaleString()}\n`;
            content += `Total comments: ${allComments.length}\n`;
            content += '='.repeat(50) + '\n\n';
            
            // Group comments by block
            const commentsByBlock = {};
            allComments.forEach(c => {
                if (!commentsByBlock[c.block_id]) {
                    commentsByBlock[c.block_id] = [];
                }
                commentsByBlock[c.block_id].push(c);
            });
            
            Object.keys(commentsByBlock).forEach(blockId => {
                const blockEl = document.querySelector(`[data-block-id="${blockId}"]`);
                const blockText = blockEl ? blockEl.textContent.trim().substring(0, 100) : `Block: ${blockId}`;
                
                content += `📄 ${blockText}${blockText.length >= 100 ? '...' : ''}\n`;
                content += '-'.repeat(30) + '\n';
                
                commentsByBlock[blockId].forEach(comment => {
                    const status = comment.resolved ? '[RESOLVED]' : '[OPEN]';
                    const date = new Date(comment.created).toLocaleString();
                    content += `${status} ${comment.author} (${date}):\n`;
                    content += `${comment.text}\n\n`;
                });
                
                content += '\n';
            });
            
            // Create and download file
            const blob = new Blob([content], { type: 'text/plain;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }
        
        function exportCommentsViaServer() {
            if (allComments.length === 0) {
                alert('No comments to export.');
                return;
            }
            
            const url = '../api/export-comments.php?file=' + encodeURIComponent(FILE) + '&format=txt';
            window.open(url, '_blank');
        }
        
        async function resolveAllComments() {
            const unresolvedComments = allComments.filter(c => !c.resolved);
            if (unresolvedComments.length === 0) {
                alert('No unresolved comments to mark as resolved.');
                return;
            }
            
            if (!confirm(`Mark all ${unresolvedComments.length} unresolved comments as resolved?`)) {
                return;
            }
            
            try {
                for (const comment of unresolvedComments) {
                    await resolveComment(comment.id);
                }
                if (currentBlockId) {
                    renderPanelComments(currentBlockId);
                }
                updateAllBadges();
                updateCommentStats();
            } catch (e) {
                console.error('Failed to resolve all comments:', e);
                alert('Failed to resolve all comments. Please try again.');
            }
        }
        
        function applyCommentFilter(filter) {
            currentFilter = filter;
            
            // Update filter tabs
            document.querySelectorAll('.filter-tab').forEach(tab => {
                tab.classList.toggle('active', tab.dataset.filter === filter);
            });
            
            // If panel is open, re-render with filter
            if (currentBlockId && panel.classList.contains('open')) {
                renderFilteredPanelComments(currentBlockId, filter);
            }
        }
        
        function renderFilteredPanelComments(blockId, filter) {
            let comments = getBlockComments(blockId);
            
            // Apply filter
            if (filter === 'resolved') {
                comments = comments.filter(c => c.resolved);
            } else if (filter === 'unresolved') {
                comments = comments.filter(c => !c.resolved);
            }
            
            if (comments.length === 0) {
                let emptyMessage = 'No comments yet. Be the first!';
                if (filter === 'resolved') emptyMessage = 'No resolved comments.';
                if (filter === 'unresolved') emptyMessage = 'No open comments.';
                panelList.innerHTML = `<div class="comment-empty">${emptyMessage}</div>`;
                return;
            }
            
            panelList.innerHTML = '';
            comments.forEach(c => {
                const item = document.createElement('div');
                item.className = 'comment-item' + (c.resolved ? ' resolved' : '');
                item.innerHTML =
                    '<div class="comment-item-header">' +
                        '<span class="comment-author">' + decodeHtml(c.author) + '</span>' +
                        '<span class="comment-date">' + formatDate(c.created) + '</span>' +
                    '</div>' +
                    '<div class="comment-text">' + decodeHtml(c.text) + '</div>' +
                    '<div class="comment-actions">' +
                        '<button class="comment-action-btn resolve-btn">' + (c.resolved ? 'Unresolve' : 'Resolve') + '</button>' +
                        '<button class="comment-action-btn delete-btn">Delete</button>' +
                    '</div>';

                item.querySelector('.resolve-btn').addEventListener('click', async () => {
                    try {
                        await resolveComment(c.id);
                        renderFilteredPanelComments(blockId, filter);
                        updateAllBadges();
                        updateCommentStats();
                    } catch (e) { console.error(e); }
                });
                item.querySelector('.delete-btn').addEventListener('click', async () => {
                    if (!confirm('Delete this comment?')) return;
                    try {
                        await deleteComment(c.id);
                        renderFilteredPanelComments(blockId, filter);
                        updateAllBadges();
                        updateCommentStats();
                    } catch (e) { console.error(e); }
                });
                panelList.appendChild(item);
            });
        }
        
        // Updated renderPanelComments to use filter
        function renderPanelComments(blockId) {
            updateCommentStats();
            renderFilteredPanelComments(blockId, currentFilter);
        }
        
        // Event listeners for new features
        document.getElementById('exportComments').addEventListener('click', exportCommentsToFile);
        document.getElementById('exportCommentsServer').addEventListener('click', exportCommentsViaServer);
        document.getElementById('resolveAll').addEventListener('click', resolveAllComments);
        
        document.querySelectorAll('.filter-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                applyCommentFilter(tab.dataset.filter);
            });
        });

        // ---- Init ----
        updateAuthorDisplay();
        initBlocks();
        fetchComments();
    })();
    </script>
</body>
</html>
