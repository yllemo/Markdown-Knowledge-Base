<?php
/**
 * Standalone draw.io editor — opened from view/index.php diagram blocks.
 * Query: style=light|dark, block=<id>
 * Payload: sessionStorage key drawio-edit-payload (JSON)
 */
$style = isset($_GET['style']) ? strtolower($_GET['style']) : 'light';
$themeClass = $style === 'dark' ? 'dark' : 'light';
$blockId = isset($_GET['block']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['block']) : '';

// Minimal valid draw.io model: empty page (root layer only).
$defaultDrawio = <<<'DRAWIO_XML'
<mxGraphModel dx="1180" dy="610" grid="1" gridSize="10" guides="1" tooltips="1" connect="1" arrows="1" fold="1" page="1" pageScale="1" pageWidth="827" pageHeight="1169" math="0" shadow="0">
  <root>
    <mxCell id="0"/>
    <mxCell id="1" parent="0"/>
  </root>
</mxGraphModel>
DRAWIO_XML;

$iframeSrc = 'https://embed.diagrams.net/?embed=1&ui=min&spin=1&proto=json&noSaveBtn=1&noExitBtn=1&libraries=1&lang=sv'
    . ($themeClass === 'dark' ? '&dark=1' : '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>draw.io Editor</title>
    <style>
        :root {
            --bg: #fafaf8;
            --surface: #fff;
            --surface2: #f3f2ee;
            --border: #e0ddd8;
            --border-active: #3d2c82;
            --text: #1a1a1a;
            --text-muted: #666;
            --accent: #3d2c82;
            --accent2: #1e7e34;
            --danger: #c0392b;
            --canvas-bg: #f5f5f5;
            --radius: 6px;
            --mono: 'Consolas', 'Monaco', 'Courier New', monospace;
            --sans: system-ui, sans-serif;
        }

        html.dark {
            --bg: #0d1117;
            --surface: #161b22;
            --surface2: #1c2128;
            --border: #30363d;
            --border-active: #a371f7;
            --text: #e6edf3;
            --text-muted: #8b949e;
            --accent: #a371f7;
            --accent2: #3fb950;
            --danger: #f85149;
            --canvas-bg: #f5f5f5;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        html, body {
            height: 100%;
            font-family: var(--sans);
            background: var(--bg);
            color: var(--text);
            overflow: hidden;
        }

        #app {
            display: grid;
            grid-template-rows: 48px 1fr;
            height: 100vh;
        }

        #topbar {
            display: flex;
            align-items: center;
            gap: 8px;
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 0 12px;
            user-select: none;
        }

        #logo {
            font-family: var(--mono);
            font-size: 13px;
            font-weight: 600;
            color: var(--accent);
            margin-right: 4px;
            white-space: nowrap;
        }

        #logo span { color: var(--text-muted); font-weight: 400; }

        .sep {
            width: 1px;
            height: 24px;
            background: var(--border);
            margin: 0 4px;
        }

        .spacer { flex: 1; }

        .btn {
            font-family: var(--sans);
            font-size: 12px;
            font-weight: 500;
            padding: 5px 12px;
            border-radius: var(--radius);
            border: 1px solid var(--border);
            cursor: pointer;
            background: transparent;
            color: var(--text-muted);
            transition: all 0.15s;
            white-space: nowrap;
        }

        .btn:hover {
            color: var(--text);
            border-color: var(--border-active);
        }

        .btn.primary {
            background: var(--accent);
            border-color: var(--accent);
            color: #fff;
            font-weight: 600;
        }

        html.dark .btn.primary { color: #0d1117; }

        .btn.success {
            color: var(--accent2);
            border-color: var(--accent2);
        }

        .btn.success:hover {
            background: var(--accent2);
            color: #fff;
        }

        #main {
            display: grid;
            grid-template-columns: 1fr;
            position: relative;
            overflow: hidden;
        }

        #main.split { grid-template-columns: 1fr min(38vw, 400px); }

        #editor-wrap {
            position: relative;
            background: var(--canvas-bg);
            overflow: hidden;
        }

        #drawio-iframe {
            width: 100%;
            height: 100%;
            border: none;
            display: block;
        }

        #loading {
            position: absolute;
            inset: 0;
            background: var(--canvas-bg);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 12px;
            z-index: 10;
            transition: opacity 0.3s;
        }

        #loading.hidden {
            opacity: 0;
            pointer-events: none;
        }

        .spinner {
            width: 32px;
            height: 32px;
            border: 3px solid var(--border);
            border-top-color: var(--accent);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        #loading p {
            font-family: var(--mono);
            font-size: 12px;
            color: var(--text-muted);
        }

        #xml-panel {
            display: none;
            flex-direction: column;
            background: var(--surface);
            border-left: 1px solid var(--border);
        }

        #xml-panel.visible { display: flex; }

        #xml-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 12px;
            border-bottom: 1px solid var(--border);
            font-family: var(--mono);
            font-size: 11px;
            color: var(--text-muted);
        }

        #xml-textarea {
            flex: 1;
            font-family: var(--mono);
            font-size: 11px;
            line-height: 1.6;
            background: var(--bg);
            color: var(--text);
            border: none;
            outline: none;
            padding: 12px;
            resize: none;
            tab-size: 2;
        }

        #xml-actions {
            display: flex;
            gap: 6px;
            padding: 8px;
            border-top: 1px solid var(--border);
        }

        #statusbar {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 26px;
            background: var(--surface2);
            border-top: 1px solid var(--border);
            display: flex;
            align-items: center;
            padding: 0 10px;
            font-family: var(--mono);
            font-size: 10px;
            color: var(--text-muted);
            z-index: 5;
            pointer-events: none;
        }

        #status-msg { flex: 1; }
        #status-msg.ok { color: var(--accent2); }
        #status-msg.err { color: var(--danger); }

        #markdown-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.55);
            z-index: 200;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }

        #markdown-modal.visible { display: flex; }

        #markdown-dialog {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            width: min(640px, 96vw);
            max-height: 85vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.35);
        }

        #markdown-dialog h2 {
            font-size: 14px;
            padding: 14px 16px;
            border-bottom: 1px solid var(--border);
        }

        #markdown-dialog p {
            font-size: 12px;
            color: var(--text-muted);
            padding: 0 16px 8px;
        }

        #markdown-snippet {
            margin: 0 16px;
            flex: 1;
            min-height: 160px;
            font-family: var(--mono);
            font-size: 11px;
            line-height: 1.55;
            background: var(--bg);
            color: var(--text);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 10px;
            resize: vertical;
        }

        #markdown-dialog footer {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
            padding: 12px 16px;
            border-top: 1px solid var(--border);
        }

        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }
    </style>
</head>
<body>
<div id="app">
    <header id="topbar">
        <div id="logo">draw<span>·</span>io <span>Editor</span></div>
        <div class="sep"></div>
        <button type="button" class="btn" id="btn-fit" title="Fit diagram in editor">Fit</button>
        <button type="button" class="btn" id="btn-xml" title="Toggle XML panel">XML</button>
        <button type="button" class="btn" id="btn-new" title="Börja om med standarddiagram">Börja om</button>
        <div class="sep"></div>
        <button type="button" class="btn" id="btn-export" title="Download .drawio file">Export</button>
        <button type="button" class="btn" id="btn-export-svg" title="Download SVG file">SVG</button>
        <div class="spacer"></div>
        <button type="button" class="btn success" id="btn-apply" title="Send XML back to the page">Apply to page</button>
        <button type="button" class="btn primary" id="btn-save" title="Apply and show markdown snippet">Save &amp; copy MD</button>
    </header>

    <div id="main">
        <div id="editor-wrap">
            <iframe
                id="drawio-iframe"
                src="<?= htmlspecialchars($iframeSrc, ENT_QUOTES, 'UTF-8') ?>"
                title="draw.io editor"
                allowfullscreen
            ></iframe>
            <div id="loading">
                <div class="spinner"></div>
                <p>Loading draw.io…</p>
            </div>
            <div id="statusbar">
                <span id="status-msg">Waiting for editor…</span>
            </div>
        </div>
        <aside id="xml-panel">
            <div id="xml-header">
                <span>XML</span>
                <button type="button" class="btn" id="btn-apply-xml" style="font-size:10px;padding:2px 8px;">Apply</button>
            </div>
            <textarea id="xml-textarea" spellcheck="false" placeholder="Paste mxGraphModel XML…"></textarea>
            <div id="xml-actions">
                <button type="button" class="btn" id="btn-copy-xml" style="flex:1;">Copy</button>
                <button type="button" class="btn" id="btn-apply-xml2" style="flex:1;">Apply</button>
            </div>
        </aside>
    </div>
</div>

<div id="markdown-modal" role="dialog" aria-labelledby="md-title">
    <div id="markdown-dialog">
        <h2 id="md-title">Markdown snippet</h2>
        <p>Paste this into your <code>.md</code> file to persist the diagram in source control.</p>
        <textarea id="markdown-snippet" readonly spellcheck="false"></textarea>
        <footer>
            <button type="button" class="btn" id="btn-md-close">Close</button>
            <button type="button" class="btn primary" id="btn-md-copy">Copy markdown</button>
        </footer>
    </div>
</div>

<script>
window.kbDrawioEditorConfig = {
    blockId: <?= json_encode($blockId) ?>,
    themeClass: <?= json_encode($themeClass) ?>,
    defaultXml: <?= json_encode($defaultDrawio, JSON_UNESCAPED_UNICODE) ?>,
    iframeSrc: <?= json_encode($iframeSrc) ?>
};
</script>
<script src="js/drawio-editor.js"></script>
</body>
</html>
