<?php
// index.php - Markdown Viewer

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

// Convert Mermaid code blocks to divs for rendering
$html = preg_replace_callback(
    '/<pre><code class="language-mermaid">(.*?)<\/code><\/pre>/s',
    function ($matches) {
        $mermaidCode = htmlspecialchars_decode($matches[1]);
        return '<div class="mermaid">' . trim($mermaidCode) . '</div>';
    },
    $html
);
// Convert Markdown checkboxes to real HTML checkboxes
$html = preg_replace_callback(
    '/<li>\s*\[([ xX])\]\s*(.*?)<\/li>/',
    function ($matches) {
        $checked = strtolower($matches[1]) === 'x' ? 'checked' : '';
        return '<li><input type="checkbox" disabled ' . $checked . '> ' . $matches[2] . '</li>';
    },
    $html
);

$title = htmlspecialchars(pathinfo(basename($filename), PATHINFO_FILENAME));
$darkClass = $style === 'dark' ? 'dark' : 'light';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?></title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    
    <!-- Prism.js for syntax highlighting -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism-tomorrow.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism.min.css" rel="stylesheet" />
    
    <style>
        body {
            font-family: system-ui, sans-serif;
            line-height: 1.6;
            margin: 0 auto;
            max-width: 900px;
            padding: 2rem;
            background: #fff;
            color: #000;
            transition: background 0.3s, color 0.3s;
        }
        body.dark {
            background: #111;
            color: #eee;
        }
        h1, h2, h3, h4, h5 {
            margin-top: 1.4em;
        }
        a { color: #0077cc; }
        body.dark a { color: #66aaff; }
        /* Inline code styling */
        code:not([class*="language-"]) {
            background: rgba(0,0,0,0.05);
            padding: 0.2em 0.4em;
            border-radius: 4px;
            font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
        }
        body.dark code:not([class*="language-"]) { 
            background: rgba(255,255,255,0.1); 
        }
        
        /* Code blocks with syntax highlighting */
        pre[class*="language-"] {
            margin: 1em 0;
            border-radius: 8px;
            overflow: hidden;
        }
        
        pre code[class*="language-"] {
            display: block;
            padding: 1em;
            overflow-x: auto;
            background: transparent !important;
        }
        
        /* Mermaid diagram styling */
        .mermaid {
            text-align: center;
            margin: 1.5em 0;
            background: transparent;
        }
        
        body.dark .mermaid {
            filter: invert(1) hue-rotate(180deg);
        }
        blockquote {
            border-left: 6px solid #66aaff;
            padding: 0.6em 1em;
            margin: 1.2em 0;
            font-style: italic;
            background: rgba(102,170,255,0.08);
            border-radius: 6px;
        }
        blockquote p { margin: 0; }
        table {
            border-collapse: collapse;
            margin: 1em 0;
            width: 100%;
        }
        th, td {
            border: 1px solid rgba(200,200,200,0.2);
            padding: 0.5em 1em;
            text-align: left;
        }
        thead th {
            background: rgba(0,0,0,0.05);
        }
        body.dark thead th {
            background: rgba(255,255,255,0.1);
        }
        input[type=checkbox] {
            transform: scale(1.2);
            margin-right: 0.5em;
        }
        input[type=checkbox] {
    transform: scale(1.3);
    margin-right: 0.4em;
    accent-color: #66aaff; /* Makes it nice in dark mode */
}

    </style>
</head>
<body class="<?= $darkClass ?>">  
    <div class="markdown-body">
        <?= $html ?>
    </div>
    
    <!-- Prism.js for syntax highlighting -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-core.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/plugins/autoloader/prism-autoloader.min.js"></script>
    
    <!-- Mermaid for diagrams -->
    <script src="https://cdn.jsdelivr.net/npm/mermaid@10.6.1/dist/mermaid.min.js"></script>
    
    <script>
        // Configure Prism theme based on dark/light mode
        const isDark = document.body.classList.contains('dark');
        if (isDark) {
            // Switch to dark theme for Prism
            const lightTheme = document.querySelector('link[href*="prism.min.css"]');
            if (lightTheme) lightTheme.disabled = true;
        } else {
            // Switch to light theme for Prism
            const darkTheme = document.querySelector('link[href*="prism-tomorrow.min.css"]');
            if (darkTheme) darkTheme.disabled = true;
        }
        
        // Configure Mermaid
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
            flowchart: {
                useMaxWidth: true,
                htmlLabels: true
            }
        });
        
        // Force Prism to highlight after page load
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof Prism !== 'undefined') {
                Prism.highlightAll();
            }
        });
    </script>
</body>
</html>
