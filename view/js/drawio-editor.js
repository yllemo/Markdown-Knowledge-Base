/**
 * draw.io embed editor (view/drawio-editor.php)
 * Expects window.kbDrawioEditorConfig
 */
(function () {
    'use strict';

    const cfg = window.kbDrawioEditorConfig || {};
    const iframe = document.getElementById('drawio-iframe');

    let editorReady = false;
    let xmlPanelOpen = false;
    let currentXML = '';
    let pendingLoad = null;
    let pendingAction = null;
    let editPayload = {
        blockId: cfg.blockId || '',
        xml: '',
        file: '',
        style: cfg.themeClass || 'light'
    };

    function sendToEditor(msg) {
        if (!iframe || !iframe.contentWindow) return;
        iframe.contentWindow.postMessage(JSON.stringify(msg), '*');
    }

    function setStatus(msg, cls) {
        const el = document.getElementById('status-msg');
        if (!el) return;
        el.textContent = msg;
        el.className = cls || '';
    }

    function formatXML(xml) {
        try {
            let level = 0;
            return xml
                .replace(/>\s*</g, '><')
                .replace(/(<\/?[^>]+>)/g, (m) => {
                    if (m.startsWith('</')) {
                        level--;
                        return '\n' + '  '.repeat(Math.max(0, level)) + m;
                    }
                    const r = '\n' + '  '.repeat(level) + m;
                    if (!m.startsWith('<?') && !m.endsWith('/>') && !m.startsWith('</')) level++;
                    return r;
                }).trim();
        } catch (e) {
            return xml;
        }
    }

    function buildMarkdown(xml) {
        return '```drawio\n' + xml.trim() + '\n```';
    }

    function decodeExportSvg(data) {
        if (!data || typeof data !== 'string') return '';
        if (data.indexOf('<svg') >= 0) return data;
        if (data.startsWith('data:')) {
            const comma = data.indexOf(',');
            if (comma < 0) return '';
            const meta = data.slice(0, comma);
            const payload = data.slice(comma + 1);
            try {
                if (meta.indexOf(';base64') >= 0) {
                    return atob(payload);
                }
                return decodeURIComponent(payload);
            } catch (e) {
                return '';
            }
        }
        return '';
    }

    function sendToOpener(xml, svg) {
        if (!window.opener || window.opener.closed) return false;
        const msg = {
            type: 'drawio-save',
            blockId: editPayload.blockId || cfg.blockId,
            xml: xml
        };
        if (svg) msg.svg = svg;
        window.opener.postMessage(msg, window.location.origin);
        return true;
    }

    function readPayload() {
        try {
            const raw = sessionStorage.getItem('drawio-edit-payload');
            if (raw) {
                const p = JSON.parse(raw);
                if (p && typeof p === 'object') {
                    editPayload = Object.assign(editPayload, p);
                }
            }
        } catch (e) { /* ignore */ }
    }

    function loadXML(xml) {
        const trimmed = xml.trim();
        if (!trimmed) return;
        currentXML = trimmed;
        if (!editorReady) {
            pendingLoad = trimmed;
        } else {
            sendToEditor({ action: 'load', xml: trimmed, autoresize: false });
        }
        setStatus('Diagram loaded', 'ok');
        if (xmlPanelOpen) {
            document.getElementById('xml-textarea').value = formatXML(trimmed);
        }
    }

    function requestXML() {
        sendToEditor({ action: 'export', format: 'xml', xml: true });
        pendingAction = 'get-xml';
    }

    function handleExport(msg) {
        if (pendingAction === 'get-xml') {
            if (msg.xml || msg.data) {
                currentXML = msg.xml || msg.data;
                if (xmlPanelOpen) {
                    document.getElementById('xml-textarea').value = formatXML(currentXML);
                }
            }
            pendingAction = null;
            return;
        }

        if (pendingAction === 'export-xml') {
            const xml = msg.xml || currentXML;
            download(xml, 'diagram.drawio', 'application/xml');
            setStatus('Exported .drawio', 'ok');
            pendingAction = null;
            return;
        }

        if (pendingAction === 'export-svg') {
            const raw = decodeExportSvg(msg.data || msg.xml || '');
            if (raw) {
                download(raw, 'diagram.svg', 'image/svg+xml');
                setStatus('Exported SVG', 'ok');
            } else {
                setStatus('SVG export failed', 'err');
            }
            pendingAction = null;
            return;
        }

    }

    function download(content, filename, type) {
        const blob = new Blob([content], { type });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        a.click();
        URL.revokeObjectURL(url);
    }

    function finishApplyPage(xml, svg, showMd) {
        const sent = sendToOpener(xml, svg);
        if (sent) {
            setStatus('Sent to page — copy markdown to persist in .md', 'ok');
        } else {
            setStatus('No parent window — use markdown snippet', 'err');
        }
        if (showMd) {
            document.getElementById('markdown-snippet').value = buildMarkdown(xml);
            document.getElementById('markdown-modal').classList.add('visible');
        }
    }

    function applyToPage(showMd) {
        pendingAction = 'apply-page-xml';
        window._drawioApplyShowMd = showMd;
        sendToEditor({ action: 'export', format: 'xml', xml: true });
    }

    function toggleXml() {
        xmlPanelOpen = !xmlPanelOpen;
        document.getElementById('xml-panel').classList.toggle('visible', xmlPanelOpen);
        document.getElementById('main').classList.toggle('split', xmlPanelOpen);
        if (xmlPanelOpen && editorReady) requestXML();
    }

    window.addEventListener('message', (e) => {
        if (!iframe || e.source !== iframe.contentWindow) return;
        if (!e.data || typeof e.data !== 'string') return;

        let msg;
        try { msg = JSON.parse(e.data); } catch (err) { return; }

        switch (msg.event) {
            case 'init':
                editorReady = true;
                document.getElementById('loading').classList.add('hidden');
                setStatus('Editor ready', 'ok');
                sendToEditor({
                    action: 'load',
                    xml: pendingLoad || editPayload.xml || cfg.defaultXml,
                    autoresize: false
                });
                pendingLoad = null;
                break;

            case 'load':
                setStatus('Diagram loaded', 'ok');
                if (xmlPanelOpen) requestXML();
                break;

            case 'autosave':
            case 'save':
                if (msg.xml) {
                    currentXML = msg.xml;
                    if (xmlPanelOpen) {
                        document.getElementById('xml-textarea').value = formatXML(currentXML);
                    }
                }
                break;

            case 'export':
                if (pendingAction === 'apply-page-xml') {
                    if (msg.xml) currentXML = msg.xml;
                    else if (msg.data) currentXML = msg.data;
                    pendingAction = 'apply-page-svg';
                    sendToEditor({ action: 'export', format: 'svg' });
                } else if (pendingAction === 'apply-page-svg') {
                    const svg = decodeExportSvg(msg.data || msg.xml || '');
                    finishApplyPage((currentXML || '').trim(), svg, window._drawioApplyShowMd);
                    pendingAction = null;
                    window._drawioApplyShowMd = false;
                } else {
                    handleExport(msg);
                }
                break;

            case 'configure':
                sendToEditor({ action: 'configure', config: { defaultFonts: [] } });
                break;

            default:
                break;
        }
    });

    function wireUi() {
        document.getElementById('btn-fit').addEventListener('click', () => {
            sendToEditor({ action: 'fit' });
        });
        document.getElementById('btn-xml').addEventListener('click', toggleXml);
        document.getElementById('btn-new').addEventListener('click', () => {
            if (!confirm('Vill du börja om? Osparade ändringar går förlorade.')) {
                return;
            }
            const defaultXml = (cfg.defaultXml || '').trim();
            if (!defaultXml) {
                setStatus('Inget standarddiagram', 'err');
                return;
            }
            pendingAction = null;
            loadXML(defaultXml);
            setStatus('Nytt diagram', 'ok');
        });
        document.getElementById('btn-export').addEventListener('click', () => {
            pendingAction = 'export-xml';
            sendToEditor({ action: 'export', format: 'xml', xml: true });
        });
        document.getElementById('btn-export-svg').addEventListener('click', () => {
            pendingAction = 'export-svg';
            sendToEditor({ action: 'export', format: 'svg' });
        });
        document.getElementById('btn-apply').addEventListener('click', () => applyToPage(false));
        document.getElementById('btn-save').addEventListener('click', () => applyToPage(true));

        document.getElementById('btn-apply-xml').addEventListener('click', () => {
            const xml = document.getElementById('xml-textarea').value.trim();
            if (xml) loadXML(xml);
        });
        document.getElementById('btn-apply-xml2').addEventListener('click', () => {
            const xml = document.getElementById('xml-textarea').value.trim();
            if (xml) loadXML(xml);
        });
        document.getElementById('btn-copy-xml').addEventListener('click', async () => {
            const xml = document.getElementById('xml-textarea').value || currentXML;
            try {
                await navigator.clipboard.writeText(xml);
                setStatus('XML copied', 'ok');
            } catch (e) {
                setStatus('Copy failed', 'err');
            }
        });

        document.getElementById('btn-md-close').addEventListener('click', () => {
            document.getElementById('markdown-modal').classList.remove('visible');
        });
        document.getElementById('btn-md-copy').addEventListener('click', async () => {
            const ta = document.getElementById('markdown-snippet');
            try {
                await navigator.clipboard.writeText(ta.value);
                setStatus('Markdown copied', 'ok');
            } catch (e) {
                ta.select();
                document.execCommand('copy');
            }
        });
        document.getElementById('markdown-modal').addEventListener('click', (e) => {
            if (e.target.id === 'markdown-modal') {
                e.target.classList.remove('visible');
            }
        });

        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                applyToPage(true);
            }
        });
    }

    document.documentElement.classList.add(cfg.themeClass === 'dark' ? 'dark' : 'light');

    readPayload();
    wireUi();
})();
