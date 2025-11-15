<?php
// index.php - Markdown Viewer

$filename = isset($_GET['file']) ? $_GET['file'] : '';
$style = isset($_GET['style']) ? strtolower($_GET['style']) : 'light';
$resetCheckboxes = isset($_GET['reset']) && ($_GET['reset'] === 'true' || $_GET['reset'] === '1');

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
        // Store original code in data attribute
        return '<div class="mermaid" data-mermaid-code="' . htmlspecialchars($trimmedCode, ENT_QUOTES) . '">' . $trimmedCode . '</div>';
    },
    $html
);
// Convert Markdown checkboxes to real HTML checkboxes with unique IDs
$checkboxIndex = 0;
$html = preg_replace_callback(
    '/<li>\s*\[([ xX])\]\s*(.*?)<\/li>/',
    function ($matches) use (&$checkboxIndex, $filename) {
        $checked = strtolower($matches[1]) === 'x' ? 'checked' : '';
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
            // Add to existing class
            $combined = $beforeSrc . $afterSrc . $afterTitle;
            $combined = preg_replace('/class="([^"]*)"/', 'class="$1 ' . $class . '"', $combined);
            return '<img' . $combined . 'src="' . $src . '">';
        } else {
            // Add new class attribute
            return '<img' . $beforeSrc . 'src="' . $src . '" class="' . $class . '"' . $afterSrc . $afterTitle . '>';
        }
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
            cursor: pointer;
            transition: opacity 0.2s ease;
            position: relative;
        }
        
        .mermaid:hover {
            opacity: 0.8;
        }
        
        .mermaid::after {
            content: '🔍 Click to view fullscreen';
            position: absolute;
            bottom: -25px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 0.7rem;
            color: #888;
            opacity: 0;
            transition: opacity 0.2s ease;
            pointer-events: none;
            white-space: nowrap;
        }
        
        body.dark .mermaid::after {
            color: #666;
        }
        
        .mermaid:hover::after {
            opacity: 1;
        }
        
        /* Mermaid v11 handles dark mode natively, no filter needed */
        
        /* Mermaid Fullscreen Modal */
        .mermaid-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.9);
            z-index: 10000;
            justify-content: center;
            align-items: center;
            padding: 2rem;
            box-sizing: border-box;
        }
        
        .mermaid-modal.show {
            display: flex;
        }
        
        .mermaid-modal-content {
            background-color: #fff;
            border-radius: 12px;
            padding: 2rem;
            max-width: 95vw;
            max-height: 95vh;
            position: relative;
            display: flex;
            flex-direction: column;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
        }
        
        body.dark .mermaid-modal-content {
            background-color: #111;
        }
        
        .mermaid-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }
        
        body.dark .mermaid-modal-header {
            border-bottom-color: rgba(255, 255, 255, 0.1);
        }
        
        .mermaid-modal-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #000;
            margin: 0;
        }
        
        body.dark .mermaid-modal-title {
            color: #eee;
        }
        
        .mermaid-modal-actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        
        .mermaid-modal-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #888;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 4px;
            transition: all 0.2s ease;
            line-height: 1;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .mermaid-modal-btn:hover {
            background-color: rgba(0, 0, 0, 0.1);
            color: #000;
        }
        
        body.dark .mermaid-modal-btn:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: #eee;
        }
        
        .mermaid-modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #888;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 4px;
            transition: all 0.2s ease;
            line-height: 1;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .mermaid-modal-close:hover {
            background-color: rgba(0, 0, 0, 0.1);
            color: #000;
        }
        
        body.dark .mermaid-modal-close:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: #eee;
        }
        
        /* Fullscreen mode */
        .mermaid-modal.fullscreen .mermaid-modal-content {
            max-width: 100vw;
            max-height: 100vh;
            width: 100vw;
            height: 100vh;
            border-radius: 0;
            padding: 1rem;
        }
        
        .mermaid-modal.fullscreen .mermaid-modal-header {
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
        }
        
        .mermaid-modal.fullscreen .mermaid-modal-diagram {
            flex: 1;
            overflow: hidden;
            position: relative;
            cursor: grab;
        }
        
        .mermaid-modal.fullscreen .mermaid-modal-diagram:active {
            cursor: grabbing;
        }
        
        .mermaid-modal.fullscreen .mermaid-modal-diagram .mermaid {
            position: absolute;
            top: 0;
            left: 0;
            transform-origin: 0 0;
            transition: transform 0.1s ease-out;
        }
        
        .mermaid-modal.fullscreen .mermaid-modal-diagram .mermaid svg {
            transform: none;
        }
        
        /* Code viewer */
        .mermaid-code-viewer {
            display: none;
            background-color: #f5f5f5;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 6px;
            padding: 1rem;
            margin-top: 1rem;
            max-height: 300px;
            overflow: auto;
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            font-size: 0.85rem;
            line-height: 1.5;
            white-space: pre-wrap;
            word-wrap: break-word;
            position: relative;
        }
        
        body.dark .mermaid-code-viewer {
            background-color: #1a1a1a;
            border-color: rgba(255, 255, 255, 0.1);
            color: #eee;
        }
        
        .mermaid-code-viewer.show {
            display: block;
        }
        
        .mermaid-code-content {
            margin-bottom: 2.5rem;
            padding-right: 0.5rem;
        }
        
        .mermaid-code-copy-btn {
            position: absolute;
            bottom: 0.75rem;
            right: 0.75rem;
            background-color: rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(0, 0, 0, 0.2);
            border-radius: 4px;
            padding: 0.4rem 0.8rem;
            font-size: 0.75rem;
            color: #333;
            cursor: pointer;
            transition: all 0.2s ease;
            font-family: system-ui, sans-serif;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        
        .mermaid-code-copy-btn:hover {
            background-color: rgba(0, 0, 0, 0.15);
            border-color: rgba(0, 0, 0, 0.3);
        }
        
        body.dark .mermaid-code-copy-btn {
            background-color: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.2);
            color: #eee;
        }
        
        body.dark .mermaid-code-copy-btn:hover {
            background-color: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.3);
        }
        
        .mermaid-code-copy-btn.copied {
            background-color: #2ea043;
            border-color: #2ea043;
            color: white;
        }
        
        body.dark .mermaid-code-copy-btn.copied {
            background-color: #2ea043;
            border-color: #2ea043;
            color: white;
        }
        
        .mermaid-modal-diagram-wrapper {
            display: flex;
            flex-direction: column;
            flex: 1;
            min-height: 0;
        }
        
        .mermaid-modal-diagram {
            flex: 1;
            overflow: auto;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 0;
            padding: 1rem;
        }
        
        .mermaid-modal-diagram .mermaid {
            margin: 0;
            cursor: default;
            max-width: 100%;
            width: 100%;
        }
        
        .mermaid-modal-diagram .mermaid svg {
            max-width: 100%;
            width: 100%;
            height: auto;
        }
        
        /* Fullscreen mode - make diagram larger */
        .mermaid-modal.fullscreen .mermaid-modal-diagram .mermaid {
            width: 100%;
            max-width: 100%;
        }
        
        .mermaid-modal.fullscreen .mermaid-modal-diagram .mermaid svg {
            width: 100%;
            max-width: 100%;
            height: auto;
            transform: scale(1.2);
            transform-origin: center;
        }
        
        @media (max-width: 1200px) {
            .mermaid-modal.fullscreen .mermaid-modal-diagram .mermaid svg {
                transform: scale(1.1);
            }
        }
        
        @media (max-width: 768px) {
            .mermaid-modal.fullscreen .mermaid-modal-diagram .mermaid svg {
                transform: scale(1);
            }
        }
        
        .mermaid-modal-diagram .mermaid::after {
            display: none !important;
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
            transform: scale(1.3);
            margin-right: 0.4em;
            accent-color: #66aaff; /* Makes it nice in dark mode */
            cursor: pointer;
        }
        
        input[type=checkbox].interactive-checkbox {
            cursor: pointer;
        }
        
        /* Styling for checked checkboxes - more discrete */
        input[type=checkbox].interactive-checkbox:checked {
            opacity: 0.5;
        }
        
        /* Style list items with checked checkboxes */
        li:has(input[type=checkbox].interactive-checkbox:checked) {
            color: #888;
            text-decoration: line-through;
            opacity: 0.7;
        }
        
        body.dark li:has(input[type=checkbox].interactive-checkbox:checked) {
            color: #666;
        }
        
        /* Alternative: if :has() is not supported, use class-based approach */
        li.checkbox-checked {
            color: #888;
            text-decoration: line-through;
            opacity: 0.7;
        }
        
        body.dark li.checkbox-checked {
            color: #666;
        }

        /* SVG inversion for dark/light mode */
        .svg-invertible {
            transition: filter 0.3s ease;
        }
        
        /* For SVGs that start as white - invert in dark mode */
        body.dark .svg-invertible.svg-invert-white {
            filter: invert(1);
        }
        
        /* For SVGs that start as black - invert in light mode */
        body.light .svg-invertible.svg-invert-black {
            filter: invert(1);
        }

    </style>
</head>
<body class="<?= $darkClass ?>">  
    <div class="markdown-body">
        <?= $html ?>
    </div>
    
    <!-- Mermaid Fullscreen Modal -->
    <div id="mermaidModal" class="mermaid-modal">
        <div class="mermaid-modal-content">
            <div class="mermaid-modal-header">
                <h3 class="mermaid-modal-title">Mermaid Diagram</h3>
                <div class="mermaid-modal-actions">
                    <button class="mermaid-modal-btn" id="mermaidCodeToggle" aria-label="Show Code" title="Show Code">📄</button>
                    <button class="mermaid-modal-btn" id="mermaidFullscreenToggle" aria-label="Enter Fullscreen" title="Enter Fullscreen">⛶</button>
                    <button class="mermaid-modal-close" aria-label="Close" title="Close">×</button>
                </div>
            </div>
            <div class="mermaid-modal-diagram" id="mermaidModalDiagram">
                <div class="mermaid-modal-diagram-wrapper">
                    <!-- Mermaid diagram will be inserted here -->
                    <div class="mermaid-code-viewer" id="mermaidCodeViewer">
                        <div class="mermaid-code-content" id="mermaidCodeContent"></div>
                        <button class="mermaid-code-copy-btn" id="mermaidCodeCopyBtn" title="Copy code">
                            📋 Copy
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Prism.js for syntax highlighting -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-core.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/plugins/autoloader/prism-autoloader.min.js"></script>
    
    <!-- Mermaid for diagrams - Latest version with icon support -->
    <script type="module">
        import mermaid from 'https://cdn.jsdelivr.net/npm/mermaid@11/dist/mermaid.esm.min.mjs';
        
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
        
        // Register popular icon packs from Iconify for use in Mermaid diagrams
        // Users can use icons like: :fa:user, :mdi:github, :logos:javascript, etc.
        mermaid.registerIconPacks([
            {
                name: 'fa',
                loader: () =>
                    fetch('https://unpkg.com/@iconify-json/fa@1/icons.json').then((res) => res.json()),
            },
            {
                name: 'mdi',
                loader: () =>
                    fetch('https://unpkg.com/@iconify-json/mdi@1/icons.json').then((res) => res.json()),
            },
            {
                name: 'logos',
                loader: () =>
                    fetch('https://unpkg.com/@iconify-json/logos@1/icons.json').then((res) => res.json()),
            },
            {
                name: 'simple-icons',
                loader: () =>
                    fetch('https://unpkg.com/@iconify-json/simple-icons@1/icons.json').then((res) => res.json()),
            },
            {
                name: 'material-symbols',
                loader: () =>
                    fetch('https://unpkg.com/@iconify-json/material-symbols@1/icons.json').then((res) => res.json()),
            },
        ]);
        
        // Configure Mermaid with enhanced settings
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
            },
            // Enable icon support
            icons: true
        });
        
        // Make mermaid available globally for compatibility
        window.mermaid = mermaid;
        
        // Force Prism to highlight after page load
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof Prism !== 'undefined') {
                Prism.highlightAll();
            }
            
            // Setup Mermaid fullscreen modal functionality
            setupMermaidModal();
        });
        
        // Setup Mermaid fullscreen modal
        function setupMermaidModal() {
            const modal = document.getElementById('mermaidModal');
            const modalDiagram = document.getElementById('mermaidModalDiagram');
            const modalDiagramWrapper = modalDiagram.querySelector('.mermaid-modal-diagram-wrapper');
            const closeBtn = document.querySelector('.mermaid-modal-close');
            const fullscreenToggle = document.getElementById('mermaidFullscreenToggle');
            const codeToggle = document.getElementById('mermaidCodeToggle');
            const codeViewer = document.getElementById('mermaidCodeViewer');
            const codeContent = document.getElementById('mermaidCodeContent');
            const codeCopyBtn = document.getElementById('mermaidCodeCopyBtn');
            
            let currentMermaidCode = '';
            let isPanning = false;
            let startX = 0;
            let startY = 0;
            let currentX = 0;
            let currentY = 0;
            let scale = 1;
            let currentMermaidElement = null;
            let panZoomHandlers = {
                wheel: null,
                mousedown: null,
                mousemove: null,
                mouseup: null
            };
            
            // Function to copy code to clipboard
            async function copyCodeToClipboard() {
                if (!currentMermaidCode) return;
                
                // Find the copy button (might be dynamically created)
                const copyBtn = document.getElementById('mermaidCodeCopyBtn');
                if (!copyBtn) return;
                
                try {
                    await navigator.clipboard.writeText(currentMermaidCode);
                    
                    // Visual feedback
                    const originalText = copyBtn.innerHTML;
                    copyBtn.classList.add('copied');
                    copyBtn.innerHTML = '✓ Copied!';
                    copyBtn.title = 'Copied!';
                    
                    // Reset after 2 seconds
                    setTimeout(() => {
                        copyBtn.classList.remove('copied');
                        copyBtn.innerHTML = originalText;
                        copyBtn.title = 'Copy code';
                    }, 2000);
                } catch (err) {
                    console.error('Failed to copy code:', err);
                    // Fallback for older browsers
                    const textArea = document.createElement('textarea');
                    textArea.value = currentMermaidCode;
                    textArea.style.position = 'fixed';
                    textArea.style.opacity = '0';
                    document.body.appendChild(textArea);
                    textArea.select();
                    try {
                        document.execCommand('copy');
                        copyBtn.classList.add('copied');
                        copyBtn.innerHTML = '✓ Copied!';
                        setTimeout(() => {
                            copyBtn.classList.remove('copied');
                            copyBtn.innerHTML = '📋 Copy';
                        }, 2000);
                    } catch (fallbackErr) {
                        console.error('Fallback copy failed:', fallbackErr);
                    }
                    document.body.removeChild(textArea);
                }
            }
            
            // Function to toggle code viewer
            function toggleCodeViewer() {
                const viewer = document.getElementById('mermaidCodeViewer');
                if (!viewer) return;
                
                const content = document.getElementById('mermaidCodeContent');
                if (!content) return;
                
                const isShowing = viewer.classList.contains('show');
                if (isShowing) {
                    viewer.classList.remove('show');
                    codeToggle.textContent = '📄';
                    codeToggle.title = 'Show Code';
                    codeToggle.setAttribute('aria-label', 'Show Code');
                } else {
                    // Always update the content element
                    content.textContent = currentMermaidCode;
                    viewer.classList.add('show');
                    codeToggle.textContent = '📄';
                    codeToggle.title = 'Hide Code';
                    codeToggle.setAttribute('aria-label', 'Hide Code');
                }
            }
            
            // Function to remove pan/zoom handlers
            function removePanZoomHandlers() {
                if (panZoomHandlers.wheel) {
                    modalDiagram.removeEventListener('wheel', panZoomHandlers.wheel);
                    panZoomHandlers.wheel = null;
                }
                if (panZoomHandlers.mousedown) {
                    modalDiagram.removeEventListener('mousedown', panZoomHandlers.mousedown);
                    panZoomHandlers.mousedown = null;
                }
                if (panZoomHandlers.mousemove) {
                    document.removeEventListener('mousemove', panZoomHandlers.mousemove);
                    panZoomHandlers.mousemove = null;
                }
                if (panZoomHandlers.mouseup) {
                    document.removeEventListener('mouseup', panZoomHandlers.mouseup);
                    panZoomHandlers.mouseup = null;
                }
            }
            
            // Function to setup pan and zoom for fullscreen
            function setupPanZoom() {
                if (!currentMermaidElement || !modal.classList.contains('fullscreen')) {
                    return;
                }
                
                // Remove existing handlers first
                removePanZoomHandlers();
                
                const svg = currentMermaidElement.querySelector('svg');
                if (!svg) return;
                
                // Reset transform
                currentX = 0;
                currentY = 0;
                scale = 1;
                updateTransform();
                
                // Mouse wheel zoom
                panZoomHandlers.wheel = function(e) {
                    if (!modal.classList.contains('fullscreen')) return;
                    e.preventDefault();
                    
                    const delta = e.deltaY > 0 ? 0.9 : 1.1;
                    const newScale = Math.max(0.5, Math.min(3, scale * delta));
                    
                    // Zoom towards mouse position
                    const rect = modalDiagram.getBoundingClientRect();
                    const x = e.clientX - rect.left;
                    const y = e.clientY - rect.top;
                    
                    const scaleChange = newScale / scale;
                    currentX = x - (x - currentX) * scaleChange;
                    currentY = y - (y - currentY) * scaleChange;
                    scale = newScale;
                    
                    updateTransform();
                };
                modalDiagram.addEventListener('wheel', panZoomHandlers.wheel, { passive: false });
                
                // Mouse drag pan
                panZoomHandlers.mousedown = function(e) {
                    if (!modal.classList.contains('fullscreen')) return;
                    if (e.target.closest('.mermaid-code-viewer')) return;
                    isPanning = true;
                    startX = e.clientX - currentX;
                    startY = e.clientY - currentY;
                    modalDiagram.style.cursor = 'grabbing';
                };
                modalDiagram.addEventListener('mousedown', panZoomHandlers.mousedown);
                
                panZoomHandlers.mousemove = function(e) {
                    if (!isPanning || !modal.classList.contains('fullscreen')) return;
                    currentX = e.clientX - startX;
                    currentY = e.clientY - startY;
                    updateTransform();
                };
                document.addEventListener('mousemove', panZoomHandlers.mousemove);
                
                panZoomHandlers.mouseup = function() {
                    if (isPanning) {
                        isPanning = false;
                        modalDiagram.style.cursor = 'grab';
                    }
                };
                document.addEventListener('mouseup', panZoomHandlers.mouseup);
            }
            
            // Update transform for pan and zoom
            function updateTransform() {
                if (currentMermaidElement) {
                    currentMermaidElement.style.transform = `translate(${currentX}px, ${currentY}px) scale(${scale})`;
                }
            }
            
            // Function to toggle fullscreen mode
            function toggleFullscreen() {
                modal.classList.toggle('fullscreen');
                const isFullscreen = modal.classList.contains('fullscreen');
                fullscreenToggle.textContent = isFullscreen ? '⛶' : '⛶';
                fullscreenToggle.title = isFullscreen ? 'Exit Fullscreen' : 'Enter Fullscreen';
                fullscreenToggle.setAttribute('aria-label', isFullscreen ? 'Exit Fullscreen' : 'Enter Fullscreen');
                
                if (isFullscreen) {
                    setupPanZoom();
                } else {
                    // Remove pan/zoom handlers when exiting fullscreen
                    removePanZoomHandlers();
                    // Reset pan/zoom when exiting fullscreen
                    if (currentMermaidElement) {
                        currentMermaidElement.style.transform = '';
                    }
                    currentX = 0;
                    currentY = 0;
                    scale = 1;
                    modalDiagram.style.cursor = '';
                }
            }
            
            // Function to open modal with a specific diagram
            function openMermaidModal(mermaidElement) {
                // Get original code from data attribute
                currentMermaidCode = mermaidElement.getAttribute('data-mermaid-code') || mermaidElement.textContent.trim();
                
                // Clear previous content
                modalDiagramWrapper.innerHTML = '';
                
                // Reset fullscreen state when opening new diagram
                modal.classList.remove('fullscreen');
                fullscreenToggle.textContent = '⛶';
                fullscreenToggle.title = 'Toggle Fullscreen';
                
                // Clone the entire Mermaid element (including rendered SVG)
                const clonedElement = mermaidElement.cloneNode(true);
                currentMermaidElement = clonedElement;
                
                // Remove hover effects from cloned element
                clonedElement.style.cursor = 'default';
                clonedElement.style.opacity = '1';
                
                // Create code viewer structure
                const newCodeViewer = document.createElement('div');
                newCodeViewer.className = 'mermaid-code-viewer';
                newCodeViewer.id = 'mermaidCodeViewer';
                
                const newCodeContent = document.createElement('div');
                newCodeContent.className = 'mermaid-code-content';
                newCodeContent.id = 'mermaidCodeContent';
                
                const newCopyBtn = document.createElement('button');
                newCopyBtn.className = 'mermaid-code-copy-btn';
                newCopyBtn.id = 'mermaidCodeCopyBtn';
                newCopyBtn.innerHTML = '📋 Copy';
                newCopyBtn.title = 'Copy code';
                newCopyBtn.addEventListener('click', copyCodeToClipboard);
                
                newCodeViewer.appendChild(newCodeContent);
                newCodeViewer.appendChild(newCopyBtn);
                
                // Set the code content immediately
                newCodeContent.textContent = currentMermaidCode;
                
                // Add elements to wrapper
                modalDiagramWrapper.appendChild(clonedElement);
                modalDiagramWrapper.appendChild(newCodeViewer);
                
                // Reset code viewer state
                newCodeViewer.classList.remove('show');
                codeToggle.textContent = '📄';
                codeToggle.title = 'Show Code';
                codeToggle.setAttribute('aria-label', 'Show Code');
                
                // Show modal
                modal.classList.add('show');
                
                // Reset pan/zoom
                currentX = 0;
                currentY = 0;
                scale = 1;
            }
            
            // Add click handlers to all Mermaid diagrams
            document.querySelectorAll('.mermaid').forEach(function(mermaidEl) {
                // Only add click handler if it's not already in the modal
                if (!mermaidEl.closest('.mermaid-modal')) {
                    mermaidEl.addEventListener('click', function(e) {
                        e.preventDefault();
                        openMermaidModal(this);
                    });
                }
            });
            
            // Close modal handlers
            function closeModal() {
                modal.classList.remove('show');
                modal.classList.remove('fullscreen');
                modalDiagramWrapper.innerHTML = '';
                fullscreenToggle.textContent = '⛶';
                fullscreenToggle.title = 'Toggle Fullscreen';
                codeToggle.textContent = '📄';
                codeToggle.title = 'Show Code';
                codeToggle.setAttribute('aria-label', 'Show Code');
                currentMermaidElement = null;
                currentMermaidCode = '';
                // Remove pan/zoom handlers
                removePanZoomHandlers();
                // Reset pan/zoom
                currentX = 0;
                currentY = 0;
                scale = 1;
                modalDiagram.style.cursor = '';
            }
            
            // Code toggle handler
            codeToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                toggleCodeViewer();
            });
            
            // Copy button handler (for initial button)
            if (codeCopyBtn) {
                codeCopyBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    copyCodeToClipboard();
                });
            }
            
            // Fullscreen toggle handler
            fullscreenToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                toggleFullscreen();
            });
            
            closeBtn.addEventListener('click', closeModal);
            
            // Close on background click (but not when clicking inside content)
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    closeModal();
                }
            });
            
            // Close on Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && modal.classList.contains('show')) {
                    if (modal.classList.contains('fullscreen')) {
                        // First exit fullscreen
                        toggleFullscreen();
                    } else {
                        // Then close modal
                        closeModal();
                    }
                }
            });
        }
    </script>
    
    <!-- Interactive Checkbox Handler -->
    <script>
        (function() {
            const filename = '<?= addslashes($filename) ?>';
            const storageKey = 'mdkb_checkboxes_' + filename;
            const shouldReset = <?= $resetCheckboxes ? 'true' : 'false' ?>;
            
            // Clear checkbox cache if reset is requested
            if (shouldReset) {
                try {
                    localStorage.removeItem(storageKey);
                    console.log('Checkbox cache cleared for:', filename);
                } catch (e) {
                    console.error('Error clearing checkbox cache:', e);
                }
            }
            
            // Update checkbox visual state (add/remove class on parent li)
            function updateCheckboxVisualState(checkbox) {
                const listItem = checkbox.closest('li');
                if (checkbox.checked) {
                    listItem.classList.add('checkbox-checked');
                } else {
                    listItem.classList.remove('checkbox-checked');
                }
            }
            
            // Load saved checkbox states
            function loadCheckboxStates() {
                try {
                    // If reset was requested, skip loading from localStorage
                    if (shouldReset) {
                        // Load from original file state (HTML checked attribute)
                        document.querySelectorAll('.interactive-checkbox').forEach(function(checkbox) {
                            updateCheckboxVisualState(checkbox);
                        });
                        return;
                    }
                    
                    const saved = localStorage.getItem(storageKey);
                    if (saved) {
                        const states = JSON.parse(saved);
                        document.querySelectorAll('.interactive-checkbox').forEach(function(checkbox) {
                            const checkboxId = checkbox.getAttribute('data-checkbox-id');
                            if (states[checkboxId] !== undefined) {
                                checkbox.checked = states[checkboxId];
                            }
                            updateCheckboxVisualState(checkbox);
                        });
                    } else {
                        // Even if no saved states, update visual state for initial checkboxes
                        document.querySelectorAll('.interactive-checkbox').forEach(function(checkbox) {
                            updateCheckboxVisualState(checkbox);
                        });
                    }
                } catch (e) {
                    console.error('Error loading checkbox states:', e);
                }
            }
            
            // Save checkbox state
            function saveCheckboxState(checkboxId, checked) {
                try {
                    const saved = localStorage.getItem(storageKey);
                    const states = saved ? JSON.parse(saved) : {};
                    states[checkboxId] = checked;
                    localStorage.setItem(storageKey, JSON.stringify(states));
                } catch (e) {
                    console.error('Error saving checkbox state:', e);
                }
            }
            
            // Initialize when DOM is ready
            document.addEventListener('DOMContentLoaded', function() {
                // Load saved states
                loadCheckboxStates();
                
                // Add event listeners to all checkboxes
                document.querySelectorAll('.interactive-checkbox').forEach(function(checkbox) {
                    checkbox.addEventListener('change', function() {
                        const checkboxId = this.getAttribute('data-checkbox-id');
                        saveCheckboxState(checkboxId, this.checked);
                        updateCheckboxVisualState(this);
                    });
                });
            });
        })();
    </script>
</body>
</html>
