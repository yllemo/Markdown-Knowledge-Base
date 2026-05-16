/**
 * BPMN·IO viewer blocks for Markdown Knowledge Base (view/index.php).
 * Expects: window.BpmnJS, window.kbBpmnioConfig { filename, style }
 */
(function () {
    'use strict';

    const viewers = new Map();
    let toastTimer = null;

    function storageKey(blockId) {
        const file = window.kbBpmnioConfig?.filename || '';
        return 'mdkb_bpmnio_' + file + '_' + blockId;
    }

    function looksLikeBpmnXml(xml) {
        if (!xml || typeof xml !== 'string') return false;
        const t = xml.trim();
        if (t.length < 12 || t.indexOf('<') !== 0) return false;
        return /<(bpmn:)?definitions[\s>]/i.test(t) || /<(bpmn:)?process[\s>]/i.test(t);
    }

    function getXml(block) {
        const saved = localStorage.getItem(storageKey(block.dataset.bpmnioId));
        if (saved) {
            try {
                const data = JSON.parse(saved);
                if (data && data.xml && looksLikeBpmnXml(data.xml)) {
                    return data.xml.trim();
                }
            } catch (e) { /* ignore */ }
        }
        const attr = block.getAttribute('data-bpmn-xml') || '';
        return looksLikeBpmnXml(attr) ? attr.trim() : '';
    }

    function setXml(block, xml) {
        block.setAttribute('data-bpmn-xml', xml);
        localStorage.setItem(
            storageKey(block.dataset.bpmnioId),
            JSON.stringify({ xml, updated: Date.now() })
        );
    }

    function showToast(message) {
        let el = document.getElementById('bpmnioToast');
        if (!el) {
            el = document.createElement('div');
            el.id = 'bpmnioToast';
            el.className = 'bpmnio-toast';
            el.setAttribute('role', 'status');
            document.body.appendChild(el);
        }
        el.textContent = message;
        el.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => el.classList.remove('show'), 4000);
    }

    function renderBlock(block) {
        const canvasEl = block.querySelector('.bpmnio-canvas');
        if (!canvasEl || typeof window.BpmnJS === 'undefined') return;

        const xml = getXml(block).trim();
        if (!xml) {
            block.classList.add('bpmnio-error');
            canvasEl.innerHTML = 'Empty BPMN XML';
            return;
        }

        const id = block.dataset.bpmnioId;
        if (viewers.has(id)) {
            try { viewers.get(id).destroy(); } catch (e) { /* ignore */ }
            viewers.delete(id);
        }

        const viewer = new window.BpmnJS({ container: canvasEl });
        viewers.set(id, viewer);

        viewer.importXML(xml).then(() => {
            const canvas = viewer.get('canvas');
            canvas.zoom('fit-viewport', 'auto');
            block.classList.remove('bpmnio-error');
        }).catch((err) => {
            block.classList.add('bpmnio-error');
            canvasEl.innerHTML = 'Parse error: ' + escapeHtml(err.message || String(err));
        });
    }

    function escapeHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function fitBlock(block) {
        const viewer = viewers.get(block.dataset.bpmnioId);
        if (!viewer) return;
        try {
            viewer.get('canvas').zoom('fit-viewport', 'auto');
        } catch (e) { /* ignore */ }
    }

    async function exportSvg(block) {
        const viewer = viewers.get(block.dataset.bpmnioId);
        if (!viewer) return;
        try {
            const { svg } = await viewer.saveSVG();
            const blob = new Blob([svg], { type: 'image/svg+xml' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = (block.dataset.bpmnioId || 'diagram') + '.svg';
            a.click();
            URL.revokeObjectURL(url);
        } catch (e) {
            console.error(e);
        }
    }

    function openEditor(block) {
        const blockId = block.dataset.bpmnioId;
        const xml = getXml(block);
        const style = document.body.classList.contains('dark') ? 'dark' : 'light';
        const payload = {
            blockId,
            xml,
            file: window.kbBpmnioConfig?.filename || '',
            style
        };
        sessionStorage.setItem('bpmnio-edit-payload', JSON.stringify(payload));
        const url = 'bpmnio-editor.php?style=' + encodeURIComponent(style) +
            '&block=' + encodeURIComponent(blockId);
        window.open(url, '_blank');
    }

    function buildMarkdownSnippet(xml) {
        return '```bpmnio\n' + xml.trim() + '\n```';
    }

    function onEditorMessage(event) {
        if (event.origin !== window.location.origin) return;
        const data = event.data;
        if (!data || data.type !== 'bpmnio-save') return;

        const blockId = String(data.blockId).replace(/\\/g, '\\\\').replace(/"/g, '\\"');
        const block = document.querySelector(
            '.bpmnio-block[data-bpmnio-id="' + blockId + '"]'
        );
        if (!block) return;

        setXml(block, data.xml);
        renderBlock(block);
        showToast('Diagram updated. Copy markdown from the editor to save the .md file.');
    }

    function wireBlock(block) {
        block.querySelectorAll('.bpmnio-action-btn').forEach((btn) => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const action = btn.getAttribute('data-action');
                if (action === 'fit') fitBlock(block);
                if (action === 'svg') exportSvg(block);
                if (action === 'edit') openEditor(block);
            });
        });

        block.addEventListener('click', (e) => {
            if (e.target.closest('.bpmnio-action-btn')) return;
            openEditor(block);
        });
    }

    function init() {
        document.querySelectorAll('.bpmnio-block').forEach((block) => {
            wireBlock(block);
            renderBlock(block);
        });
        window.addEventListener('message', onEditorMessage);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    window.kbBpmnio = {
        renderBlock,
        buildMarkdownSnippet,
        getXml,
        setXml
    };
})();
