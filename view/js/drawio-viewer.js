/**
 * draw.io — förhandsvisning: cachad SVG → server → embed.diagrams.net (fallback).
 */
(function () {
    'use strict';

    const RENDER_URL = (window.kbDrawioConfig && window.kbDrawioConfig.renderUrl) || 'drawio-render.php';
    const EMBED_BASE = 'https://embed.diagrams.net/?embed=1&ui=min&proto=json&spin=0'
        + '&noSaveBtn=1&noExitBtn=1&libraries=0&toolbar=0&nav=0&lang=sv';
    let toastTimer = null;

    function storageKey(blockId) {
        const file = window.kbDrawioConfig?.filename || '';
        return 'mdkb_drawio_' + file + '_' + blockId;
    }

    function readStorage(blockId) {
        try {
            const raw = localStorage.getItem(storageKey(blockId));
            if (!raw) return null;
            return JSON.parse(raw);
        } catch (e) {
            return null;
        }
    }

    function writeStorage(blockId, data) {
        localStorage.setItem(storageKey(blockId), JSON.stringify(data));
    }

    function getTemplateXml(block) {
        const tpl = block.querySelector('template.drawio-xml-source');
        if (!tpl) return '';
        if (tpl.content && tpl.content.textContent) {
            return tpl.content.textContent.trim();
        }
        return (tpl.textContent || '').trim();
    }

    function getXml(block) {
        const data = readStorage(block.dataset.drawioId);
        if (data && data.xml) return data.xml.trim();
        return getTemplateXml(block);
    }

    function getStoredSvg(block) {
        const data = readStorage(block.dataset.drawioId);
        if (data && data.svg && typeof data.svg === 'string') {
            const s = data.svg.trim();
            if (s.indexOf('<svg') >= 0) return s;
        }
        return null;
    }

    function hasLocalOverride(block) {
        const data = readStorage(block.dataset.drawioId);
        if (!data || !data.xml) return false;
        return data.xml.trim() !== getTemplateXml(block);
    }

    function setXml(block, xml, svg) {
        let tpl = block.querySelector('template.drawio-xml-source');
        if (!tpl) {
            tpl = document.createElement('template');
            tpl.className = 'drawio-xml-source';
            block.insertBefore(tpl, block.firstChild);
        }
        tpl.textContent = xml;
        const payload = { xml, updated: Date.now() };
        if (svg) payload.svg = svg;
        else {
            const prev = readStorage(block.dataset.drawioId);
            if (prev && prev.svg) payload.svg = prev.svg;
        }
        writeStorage(block.dataset.drawioId, payload);
    }

    function normalizeDrawioXml(xml) {
        const trimmed = xml.trim();
        if (!trimmed) return trimmed;
        if (trimmed.indexOf('<mxfile') >= 0) return trimmed;
        if (trimmed.indexOf('<mxGraphModel') >= 0) {
            return '<mxfile host="app.diagrams.net" agent="mdkb" version="22.1.0">'
                + '<diagram name="Page-1" id="page-1">' + trimmed + '</diagram></mxfile>';
        }
        return trimmed;
    }

    function isDark() {
        return document.body.classList.contains('dark');
    }

    function showToast(message) {
        let el = document.getElementById('drawioToast');
        if (!el) {
            el = document.createElement('div');
            el.id = 'drawioToast';
            el.className = 'drawio-toast';
            el.setAttribute('role', 'status');
            document.body.appendChild(el);
        }
        el.textContent = message;
        el.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => el.classList.remove('show'), 4500);
    }

    function applySvg(wrap, svg) {
        teardownEmbed(wrap);
        wrap.classList.remove('drawio-svg-missing', 'drawio-embed-mode', 'drawio-needs-embed');
        wrap.classList.add('drawio-svg-ready');
        wrap.setAttribute('role', 'img');
        wrap.setAttribute('aria-label', 'draw.io-diagram');
        wrap.innerHTML = svg;
    }

    function showLoading(wrap) {
        teardownEmbed(wrap);
        wrap.classList.remove('drawio-svg-ready', 'drawio-embed-mode');
        wrap.classList.add('drawio-svg-missing');
        wrap.innerHTML = '<div class="drawio-loading">Laddar förhandsvisning\u2026</div>';
    }

    function teardownEmbed(wrap) {
        if (wrap._drawioEmbedCleanup) {
            wrap._drawioEmbedCleanup();
            wrap._drawioEmbedCleanup = null;
        }
    }

    async function fetchSvgFromServer(xml, clientSvg) {
        const body = { xml };
        if (clientSvg) body.svg = clientSvg;

        const resp = await fetch(RENDER_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
            credentials: 'same-origin'
        });
        if (!resp.ok) {
            throw new Error('HTTP ' + resp.status);
        }
        const text = (await resp.text()).trim();
        const idx = text.indexOf('<svg');
        if (idx < 0) throw new Error('Ogiltigt SVG-svar');
        return idx === 0 ? text : text.slice(idx);
    }

    function decodeExportSvg(data) {
        if (!data || typeof data !== 'string') return null;
        if (data.indexOf('<svg') >= 0) return data;
        if (data.startsWith('data:')) {
            const comma = data.indexOf(',');
            if (comma < 0) return null;
            const meta = data.slice(0, comma);
            const payload = data.slice(comma + 1);
            try {
                if (meta.indexOf(';base64') >= 0) {
                    return atob(payload);
                }
                return decodeURIComponent(payload);
            } catch (e) {
                return null;
            }
        }
        return null;
    }

    function showEmbedPreview(block, xml) {
        const wrap = block.querySelector('.drawio-svg-wrap');
        if (!wrap) return;

        teardownEmbed(wrap);
        wrap.classList.remove('drawio-svg-ready', 'drawio-svg-missing', 'drawio-needs-embed');
        wrap.classList.add('drawio-embed-mode');
        wrap.removeAttribute('role');
        wrap.innerHTML = '';

        const iframe = document.createElement('iframe');
        iframe.className = 'drawio-preview-iframe';
        iframe.title = 'draw.io förhandsvisning';
        iframe.setAttribute('loading', 'lazy');
        iframe.setAttribute(
            'sandbox',
            'allow-scripts allow-same-origin allow-popups allow-forms'
        );
        iframe.src = EMBED_BASE + (isDark() ? '&dark=1' : '');

        const normalized = normalizeDrawioXml(xml);
        let loaded = false;

        function onMessage(e) {
            if (e.source !== iframe.contentWindow) return;
            if (typeof e.data !== 'string') return;
            let msg;
            try {
                msg = JSON.parse(e.data);
            } catch (err) {
                return;
            }
            if (msg.event === 'init' && !loaded) {
                loaded = true;
                iframe.contentWindow.postMessage(JSON.stringify({
                    action: 'load',
                    xml: normalized,
                    autosave: false
                }), '*');
            }
        }

        window.addEventListener('message', onMessage);
        wrap._drawioEmbedCleanup = function () {
            window.removeEventListener('message', onMessage);
            wrap.innerHTML = '';
        };

        wrap.appendChild(iframe);
        block.classList.remove('drawio-error');
    }

    async function renderBlock(block, force) {
        const wrap = block.querySelector('.drawio-svg-wrap');
        if (!wrap) return;

        const xml = getXml(block);
        if (!xml) {
            block.classList.add('drawio-error');
            teardownEmbed(wrap);
            wrap.classList.add('drawio-svg-missing');
            wrap.innerHTML = '<p class="drawio-preview-fallback">Tom draw.io-XML.</p>';
            return;
        }

        if (!force && wrap.classList.contains('drawio-svg-ready') && wrap.querySelector('svg') && !hasLocalOverride(block)) {
            return;
        }
        if (!force && wrap.classList.contains('drawio-embed-mode') && wrap.querySelector('iframe')) {
            return;
        }

        const storedSvg = getStoredSvg(block);
        if (storedSvg && (!force || hasLocalOverride(block))) {
            applySvg(wrap, storedSvg);
            block.classList.remove('drawio-error');
            return;
        }

        block.classList.remove('drawio-error');
        showLoading(wrap);

        try {
            const svg = await fetchSvgFromServer(xml, storedSvg || undefined);
            applySvg(wrap, svg);
            writeStorage(block.dataset.drawioId, {
                xml,
                svg,
                updated: Date.now()
            });
            return;
        } catch (e) {
            /* server export often unavailable — embed fallback */
        }

        showEmbedPreview(block, xml);
    }

    async function exportSvg(block) {
        const svgEl = block.querySelector('.drawio-svg-wrap svg');
        if (svgEl) {
            const blob = new Blob(
                [new XMLSerializer().serializeToString(svgEl)],
                { type: 'image/svg+xml' }
            );
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = (block.dataset.drawioId || 'diagram') + '.svg';
            a.click();
            URL.revokeObjectURL(url);
            return;
        }
        const stored = getStoredSvg(block);
        if (stored) {
            const blob = new Blob([stored], { type: 'image/svg+xml' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = (block.dataset.drawioId || 'diagram') + '.svg';
            a.click();
            URL.revokeObjectURL(url);
            return;
        }
        try {
            const svg = await fetchSvgFromServer(getXml(block));
            const blob = new Blob([svg], { type: 'image/svg+xml' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = (block.dataset.drawioId || 'diagram') + '.svg';
            a.click();
            URL.revokeObjectURL(url);
        } catch (e) {
            alert('Kunde inte exportera SVG. Använd Redigera och exportera därifrån.');
        }
    }

    function openEditor(block) {
        sessionStorage.setItem('drawio-edit-payload', JSON.stringify({
            blockId: block.dataset.drawioId,
            xml: getXml(block),
            file: window.kbDrawioConfig?.filename || '',
            style: isDark() ? 'dark' : 'light'
        }));
        const style = isDark() ? 'dark' : 'light';
        window.open(
            'drawio-editor.php?style=' + encodeURIComponent(style) +
            '&block=' + encodeURIComponent(block.dataset.drawioId),
            '_blank'
        );
    }

    function onEditorMessage(event) {
        if (event.origin !== window.location.origin) return;
        const data = event.data;
        if (!data || data.type !== 'drawio-save') return;

        const blockId = String(data.blockId).replace(/\\/g, '\\\\').replace(/"/g, '\\"');
        const block = document.querySelector('.drawio-block[data-drawio-id="' + blockId + '"]');
        if (!block) return;

        const svg = decodeExportSvg(data.svg);
        setXml(block, data.xml, svg || undefined);

        const wrap = block.querySelector('.drawio-svg-wrap');
        if (svg && wrap) {
            applySvg(wrap, svg);
            fetchSvgFromServer(data.xml, svg).catch(function () { /* cache best-effort */ });
            block.classList.remove('drawio-error');
        } else {
            renderBlock(block, true);
        }
        showToast('Diagram uppdaterat. Klistra in markdown från editorn för att spara i .md-filen.');
    }

    function wireBlock(block) {
        block.querySelectorAll('.drawio-action-btn').forEach((btn) => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const action = btn.getAttribute('data-action');
                if (action === 'refresh') renderBlock(block, true);
                if (action === 'svg') exportSvg(block);
                if (action === 'edit') openEditor(block);
            });
        });

        block.addEventListener('click', (e) => {
            if (e.target.closest('.drawio-action-btn')) return;
            openEditor(block);
        });
    }

    function init() {
        document.querySelectorAll('.drawio-block').forEach((block) => {
            wireBlock(block);
            const wrap = block.querySelector('.drawio-svg-wrap');
            const needsRender = hasLocalOverride(block)
                || (wrap && (wrap.classList.contains('drawio-needs-embed')
                    || wrap.classList.contains('drawio-svg-missing')));
            if (needsRender) {
                renderBlock(block, true);
            }
        });
        window.addEventListener('message', onEditorMessage);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    window.kbDrawio = { renderBlock, getXml, setXml };
})();
