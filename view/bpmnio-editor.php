<?php
/**
 * Standalone BPMN·IO editor — opened from view/index.php diagram blocks.
 * Query: style=light|dark, block=<id>
 * Payload: sessionStorage key bpmnio-edit-payload (JSON)
 */
$style = isset($_GET['style']) ? strtolower($_GET['style']) : 'light';
$themeClass = $style === 'dark' ? 'dark' : 'light';
$blockId = isset($_GET['block']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['block']) : '';

// Default diagram — kept in PHP only; injected via JSON script tag (never raw <?xml in inline JS).
$defaultBpmn = <<<'BPMN_XML'
<?xml version="1.0" encoding="UTF-8"?>
<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL"
  xmlns:bpmndi="http://www.omg.org/spec/BPMN/20100524/DI"
  xmlns:dc="http://www.omg.org/spec/DD/20100524/DC"
  xmlns:di="http://www.omg.org/spec/DD/20100524/DI"
  id="Definitions_1"
  targetNamespace="http://bpmn.io/schema/bpmn">
  <bpmn:process id="Process_1" isExecutable="false">
    <bpmn:startEvent id="StartEvent_1">
      <bpmn:outgoing>Flow_1</bpmn:outgoing>
    </bpmn:startEvent>
    <bpmn:endEvent id="EndEvent_1">
      <bpmn:incoming>Flow_1</bpmn:incoming>
    </bpmn:endEvent>
    <bpmn:sequenceFlow id="Flow_1" sourceRef="StartEvent_1" targetRef="EndEvent_1"/>
  </bpmn:process>
  <bpmndi:BPMNDiagram id="BPMNDiagram_1">
    <bpmndi:BPMNPlane id="BPMNPlane_1" bpmnElement="Process_1">
      <bpmndi:BPMNShape id="StartEvent_1_di" bpmnElement="StartEvent_1">
        <dc:Bounds x="180" y="200" width="36" height="36"/>
      </bpmndi:BPMNShape>
      <bpmndi:BPMNShape id="EndEvent_1_di" bpmnElement="EndEvent_1">
        <dc:Bounds x="320" y="200" width="36" height="36"/>
      </bpmndi:BPMNShape>
      <bpmndi:BPMNEdge id="Flow_1_di" bpmnElement="Flow_1">
        <di:waypoint x="216" y="218"/>
        <di:waypoint x="320" y="218"/>
      </bpmndi:BPMNEdge>
    </bpmndi:BPMNPlane>
  </bpmndi:BPMNDiagram>
</bpmn:definitions>
BPMN_XML;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BPMN·IO Editor</title>
    <script src="https://unpkg.com/bpmn-js@17/dist/bpmn-modeler.production.min.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/bpmn-js@17/dist/assets/diagram-js.css">
    <link rel="stylesheet" href="https://unpkg.com/bpmn-js@17/dist/assets/bpmn-js.css">
    <link rel="stylesheet" href="https://unpkg.com/bpmn-js@17/dist/assets/bpmn-font/css/bpmn.css">
    <style>
        :root {
            --bg: #fafaf8;
            --surface: #fff;
            --surface2: #f3f2ee;
            --border: #e0ddd8;
            --border-active: #2c5282;
            --text: #1a1a1a;
            --text-muted: #666;
            --accent: #2c5282;
            --accent2: #1e7e34;
            --danger: #c0392b;
            --canvas-bg: #fff;
            --radius: 6px;
            --mono: 'Consolas', 'Monaco', 'Courier New', monospace;
            --sans: system-ui, sans-serif;
        }

        html.dark {
            --bg: #0d1117;
            --surface: #161b22;
            --surface2: #1c2128;
            --border: #30363d;
            --border-active: #58a6ff;
            --text: #e6edf3;
            --text-muted: #8b949e;
            --accent: #58a6ff;
            --accent2: #3fb950;
            --danger: #f85149;
            --canvas-bg: #ffffff;
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

        #canvas-panel {
            position: relative;
            background: var(--canvas-bg);
            overflow: hidden;
        }

        #bpmn-canvas { width: 100%; height: 100%; }

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
            gap: 12px;
            font-family: var(--mono);
            font-size: 10px;
            color: var(--text-muted);
            z-index: 10;
        }

        #status-msg { flex: 1; }
        #status-msg.ok { color: var(--accent2); }
        #status-msg.err { color: var(--danger); }

        #zoom-badge {
            position: absolute;
            bottom: 34px;
            right: 10px;
            font-family: var(--mono);
            font-size: 10px;
            background: var(--surface2);
            border: 1px solid var(--border);
            color: var(--text-muted);
            padding: 2px 7px;
            border-radius: 4px;
            z-index: 10;
            pointer-events: none;
        }

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
        <div id="logo">BPMN<span>·</span>IO <span>Editor</span></div>
        <div class="sep"></div>
        <button type="button" class="btn" id="btn-fit" title="Fit diagram">Fit</button>
        <button type="button" class="btn" id="btn-xml" title="Toggle XML panel">XML</button>
        <button type="button" class="btn" id="btn-new" title="Börja om med standarddiagram">Börja om</button>
        <div class="sep"></div>
        <button type="button" class="btn" id="btn-export" title="Download .bpmn file">Export</button>
        <button type="button" class="btn" id="btn-export-svg" title="Download SVG file">SVG</button>
        <div class="spacer"></div>
        <button type="button" class="btn success" id="btn-apply" title="Send XML back to the page">Apply to page</button>
        <button type="button" class="btn primary" id="btn-save" title="Apply and show markdown snippet">Save &amp; copy MD</button>
    </header>

    <div id="main">
        <div id="canvas-panel">
            <div id="bpmn-canvas"></div>
            <div id="zoom-badge">100%</div>
            <div id="statusbar">
                <span id="status-msg">Loading…</span>
                <span id="status-elements"></span>
            </div>
        </div>
        <aside id="xml-panel">
            <div id="xml-header">
                <span>XML</span>
                <button type="button" class="btn" id="btn-apply-xml" style="font-size:10px;padding:2px 8px;">Apply</button>
            </div>
            <textarea id="xml-textarea" spellcheck="false" placeholder="Paste BPMN XML…"></textarea>
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

<script type="application/json" id="bpmn-default-xml"><?= json_encode($defaultBpmn, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
<script>
(function () {
    'use strict';

    const blockId = <?= json_encode($blockId) ?>;
    const themeClass = <?= json_encode($themeClass) ?>;
    document.documentElement.classList.add(themeClass);

    let bpmnModeler = null;
    let xmlPanelOpen = false;
    let editPayload = { blockId: blockId, xml: '', file: '', style: themeClass };

    function getDefaultBpmnXml() {
        const el = document.getElementById('bpmn-default-xml');
        if (!el) return '';
        try {
            const parsed = JSON.parse(el.textContent);
            return typeof parsed === 'string' ? parsed.trim() : '';
        } catch (e) {
            return '';
        }
    }

    const DEFAULT_BPMN = getDefaultBpmnXml();

    function looksLikeBpmnXml(xml) {
        if (!xml || typeof xml !== 'string') return false;
        const t = xml.trim();
        if (t.length < 12 || t.indexOf('<') !== 0) return false;
        return /<(bpmn:)?definitions[\s>]/i.test(t) || /<(bpmn:)?process[\s>]/i.test(t);
    }

    function setStatus(msg, cls) {
        const el = document.getElementById('status-msg');
        el.textContent = msg;
        el.className = cls || '';
    }

    function updateElements() {
        try {
            const count = bpmnModeler.get('elementRegistry').getAll().length;
            document.getElementById('status-elements').textContent = count + ' elements';
        } catch (e) { /* ignore */ }
    }

    function updateZoomBadge() {
        try {
            const z = bpmnModeler.get('canvas').zoom();
            document.getElementById('zoom-badge').textContent = Math.round(z * 100) + '%';
        } catch (e) { /* ignore */ }
    }

    function fitView() {
        try {
            const canvas = bpmnModeler.get('canvas');
            canvas.zoom('fit-viewport', 'auto');
            updateZoomBadge();
        } catch (e) { /* ignore */ }
    }

    async function getXml() {
        const result = await bpmnModeler.saveXML({ format: true });
        return result.xml || '';
    }

    async function resetToDefault() {
        if (typeof bpmnModeler.createDiagram === 'function') {
            try {
                await bpmnModeler.createDiagram();
                fitView();
                updateElements();
                if (xmlPanelOpen) await syncXmlPanel();
                return;
            } catch (e) { /* fall through */ }
        }
        if (DEFAULT_BPMN) {
            await bpmnModeler.importXML(DEFAULT_BPMN);
            fitView();
            updateElements();
            if (xmlPanelOpen) await syncXmlPanel();
            return;
        }
        throw new Error('Inget standarddiagram tillgängligt');
    }

    async function loadXml(xml, options) {
        const opts = Object.assign({ fallback: true }, options);
        const trimmed = (xml || '').trim();

        if (!looksLikeBpmnXml(trimmed)) {
            if (!opts.fallback) {
                setStatus('Ogiltig eller tom BPMN-XML', 'err');
                return false;
            }
            await resetToDefault();
            setStatus(
                trimmed ? 'Ogiltig XML — standarddiagram laddat' : 'Standarddiagram',
                'ok'
            );
            return false;
        }

        try {
            await bpmnModeler.importXML(trimmed);
            fitView();
            updateElements();
            setStatus('Diagram loaded', 'ok');
            if (xmlPanelOpen) await syncXmlPanel();
            return true;
        } catch (err) {
            if (!opts.fallback) {
                setStatus('Error: ' + err.message, 'err');
                throw err;
            }
            await resetToDefault();
            setStatus('Kunde inte läsa XML — standarddiagram laddat', 'ok');
            return false;
        }
    }

    async function syncXmlPanel() {
        try {
            document.getElementById('xml-textarea').value = await getXml();
        } catch (e) { /* ignore */ }
    }

    function buildMarkdown(xml) {
        return '```bpmnio\n' + xml.trim() + '\n```';
    }

    function sendToOpener(xml) {
        if (!window.opener || window.opener.closed) return false;
        window.opener.postMessage({
            type: 'bpmnio-save',
            blockId: editPayload.blockId || blockId,
            xml: xml
        }, window.location.origin);
        return true;
    }

    async function applyToPage(showMd) {
        const xml = await getXml();
        const sent = sendToOpener(xml);
        if (sent) {
            setStatus('Sent to page — reload the markdown file to persist', 'ok');
        } else {
            setStatus('No parent window — use markdown snippet', 'err');
        }
        if (showMd) {
            document.getElementById('markdown-snippet').value = buildMarkdown(xml);
            document.getElementById('markdown-modal').classList.add('visible');
        }
        return xml;
    }

    function toggleXml() {
        xmlPanelOpen = !xmlPanelOpen;
        document.getElementById('xml-panel').classList.toggle('visible', xmlPanelOpen);
        document.getElementById('main').classList.toggle('split', xmlPanelOpen);
        if (xmlPanelOpen) syncXmlPanel();
    }

    function readPayload() {
        try {
            const raw = sessionStorage.getItem('bpmnio-edit-payload');
            if (raw) {
                const p = JSON.parse(raw);
                if (p && typeof p === 'object') {
                    editPayload = Object.assign(editPayload, p);
                }
            }
        } catch (e) { /* ignore */ }
    }

    window.addEventListener('load', async function () {
        readPayload();
        bpmnModeler = new BpmnJS({ container: '#bpmn-canvas' });
        bpmnModeler.on('commandStack.changed', function () {
            if (xmlPanelOpen) syncXmlPanel();
            updateElements();
        });

        const payloadXml = (editPayload.xml || '').trim();
        try {
            if (looksLikeBpmnXml(payloadXml)) {
                await loadXml(payloadXml, { fallback: true });
            } else {
                await resetToDefault();
                setStatus('Ready', 'ok');
            }
        } catch (err) {
            setStatus('Error: ' + err.message, 'err');
        }

        document.getElementById('btn-fit').addEventListener('click', fitView);
        document.getElementById('btn-xml').addEventListener('click', toggleXml);
        document.getElementById('btn-new').addEventListener('click', async function () {
            if (!confirm('Vill du börja om? Osparade ändringar går förlorade.')) {
                return;
            }
            try {
                await resetToDefault();
                setStatus('Nytt diagram', 'ok');
            } catch (err) {
                setStatus('Error: ' + err.message, 'err');
            }
        });
        document.getElementById('btn-apply').addEventListener('click', function () {
            applyToPage(false);
        });
        document.getElementById('btn-save').addEventListener('click', function () {
            applyToPage(true);
        });
        document.getElementById('btn-export').addEventListener('click', async function () {
            const xml = await getXml();
            const blob = new Blob([xml], { type: 'application/xml' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'diagram.bpmn';
            a.click();
            URL.revokeObjectURL(url);
            setStatus('Exported .bpmn', 'ok');
        });

        document.getElementById('btn-export-svg').addEventListener('click', async function () {
            try {
                const { svg } = await bpmnModeler.saveSVG();
                const blob = new Blob([svg], { type: 'image/svg+xml' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'diagram.svg';
                a.click();
                URL.revokeObjectURL(url);
                setStatus('Exported SVG', 'ok');
            } catch (e) {
                setStatus('SVG export failed', 'err');
            }
        });

        async function applyFromTextarea() {
            const xml = document.getElementById('xml-textarea').value.trim();
            if (!xml) {
                setStatus('Tom XML', 'err');
                return;
            }
            try {
                await loadXml(xml, { fallback: true });
            } catch (err) {
                setStatus('Error: ' + err.message, 'err');
            }
        }

        document.getElementById('btn-apply-xml').addEventListener('click', applyFromTextarea);
        document.getElementById('btn-apply-xml2').addEventListener('click', applyFromTextarea);
        document.getElementById('btn-copy-xml').addEventListener('click', async function () {
            await syncXmlPanel();
            try {
                await navigator.clipboard.writeText(document.getElementById('xml-textarea').value);
                setStatus('XML copied', 'ok');
            } catch (e) {
                setStatus('Copy failed', 'err');
            }
        });

        document.getElementById('btn-md-close').addEventListener('click', function () {
            document.getElementById('markdown-modal').classList.remove('visible');
        });
        document.getElementById('btn-md-copy').addEventListener('click', async function () {
            const ta = document.getElementById('markdown-snippet');
            try {
                await navigator.clipboard.writeText(ta.value);
                setStatus('Markdown copied', 'ok');
            } catch (e) {
                ta.select();
                document.execCommand('copy');
            }
        });

        document.getElementById('markdown-modal').addEventListener('click', function (e) {
            if (e.target === this) this.classList.remove('visible');
        });

        setInterval(updateZoomBadge, 500);

        document.addEventListener('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                applyToPage(true);
            }
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                e.preventDefault();
                fitView();
            }
        });
    });
})();
</script>
</body>
</html>
