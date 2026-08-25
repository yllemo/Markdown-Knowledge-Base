/* ---------- Inställningar ---------- */
const DEFAULT_SETTINGS = {
  mermaidVersion: 'latest',
  mermaidWidth: 'auto',
  mermaidAlign: 'center',
  toc: 'off',
  fontSize: '100%',
  pageWidth: '210mm'
};
let settings = { ...DEFAULT_SETTINGS };
try {
  const saved = JSON.parse(localStorage.getItem('md-viewer-settings') || '{}');
  settings = { ...DEFAULT_SETTINGS, ...saved };
} catch { /* ogiltig lagring ignoreras */ }

function saveSettings() {
  try { localStorage.setItem('md-viewer-settings', JSON.stringify(settings)); } catch { /* t.ex. privat läge */ }
}

function applyMermaidStyle() {
  const root = document.documentElement.style;
  root.setProperty('--mmd-width', settings.mermaidWidth === 'auto' ? 'auto' : settings.mermaidWidth);
  root.setProperty('--mmd-align', settings.mermaidAlign);
  root.setProperty('--reader-font-size', settings.fontSize);
  root.setProperty('--reader-width', settings.pageWidth);
}
applyMermaidStyle();

/* ---------- Mermaid: dynamisk laddning enligt vald version ---------- */
let mermaidInstance = null;
let mermaidLoadedVer = null;

async function getMermaid() {
  const ver = settings.mermaidVersion || 'latest';
  if (mermaidInstance && mermaidLoadedVer === ver) return mermaidInstance;
  const url = 'https://cdn.jsdelivr.net/npm/mermaid@' + ver + '/dist/mermaid.esm.min.mjs';
  const mod = await import(url);
  const m = mod.default || mod;
  m.initialize({
    startOnLoad: false,
    theme: 'base',
    securityLevel: 'loose',
    themeVariables: {
      fontFamily: 'Arial, Helvetica, sans-serif',
      primaryColor: '#C0E4F2',
      primaryTextColor: '#1F1F1F',
      primaryBorderColor: '#0077BC',
      lineColor: '#004172',
      secondaryColor: '#F4F9FC',
      tertiaryColor: '#FFFFFF'
    }
  });
  mermaidInstance = m;
  mermaidLoadedVer = ver;
  return m;
}

const $ = (id) => document.getElementById(id);
const fileInput = $('fileInput');
const dropzone = $('dropzone');
const page = $('page');
const content = $('content');
const frontmatterEl = $('frontmatter');
const filenameBadge = $('filenameBadge');
const btnNew = $('btnNew');
const SERVER_ONLY = !!(window.PRINT_BOOT && typeof window.PRINT_BOOT.content === 'string');
const btns = {
  open: $('btnOpen'),
  copy: $('btnCopyHtml'),
  stats: $('btnStats'),
  tts: $('btnTts'),
  search: $('btnSearch'),
  print: $('btnPrint'),
  exportToggle: $('btnExportToggle'),
  pdf: $('btnPdf'),
  docx: $('btnDocx'),
  html: $('btnHtml'),
  md: $('btnSaveMd'),
  txt: $('btnSaveTxt')
};

function enableToolbar() {
  Object.values(btns).forEach((b) => {
    if (b) b.disabled = false;
  });
  if (modeToggle) modeToggle.classList.add('visible');
}

let currentName = 'dokument';
let mermaidSeq = 0;

function dateSuffix() {
  const d = new Date();
  const p = (n) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}`;
}
function exportName() {
  return `${currentName}_${dateSuffix()}`;
}

/** First markdown H1 (# …), after optional YAML front matter. */
function extractFirstH1(mdText) {
  if (!mdText || typeof mdText !== 'string') return null;
  let body = mdText.replace(/^\uFEFF/, '');
  const fm = body.match(/^---[ \t]*\r?\n[\s\S]*?\r?\n---[ \t]*\r?\n?/);
  if (fm) body = body.slice(fm[0].length);
  const m = body.match(/^#\s+(.+?)\s*$/m);
  if (!m) return null;
  const heading = m[1].replace(/\s+#+\s*$/, '').trim();
  return heading || null;
}

function applyDocumentTitle(mdText) {
  const heading = extractFirstH1(mdText) || 'MD Print';
  const brand = $('brand');
  if (brand) {
    brand.textContent = heading;
    brand.title = heading;
  }
  document.title = heading;
}

/* Samma filnamn vid utskrift/PDF: webbläsaren föreslår document.title som filnamn */
let titleBackup = null;
window.addEventListener('beforeprint', () => {
  titleBackup = document.title;
  document.title = exportName();
});
window.addEventListener('afterprint', () => {
  if (titleBackup !== null) { document.title = titleBackup; titleBackup = null; }
});

marked.setOptions({ gfm: true, breaks: false });

function toast(msg, ms = 2600) {
  const t = $('toast');
  t.textContent = msg;
  t.classList.add('show');
  clearTimeout(t._h);
  t._h = setTimeout(() => t.classList.remove('show'), ms);
}

/* ---------- Öppna .md-fil (endast standalone / print.html) ---------- */
let currentMdText = null;
const FS_ACCESS = 'showOpenFilePicker' in window;

function isMarkdown(file) {
  return /\.(md|markdown|txt)$/i.test(file.name);
}

async function pickFile() {
  if (FS_ACCESS) {
    try {
      const [handle] = await window.showOpenFilePicker({
        id: 'md-viewer-open',
        startIn: 'downloads',
        types: [{ description: 'Markdown', accept: { 'text/markdown': ['.md', '.markdown', '.txt'] } }],
        excludeAcceptAllOption: false,
        multiple: false
      });
      await loadFile(await handle.getFile());
      return;
    } catch (err) {
      if (err && err.name === 'AbortError') return;
      console.warn('Filväljare föll tillbaka till <input>:', err);
    }
  }
  if (fileInput) fileInput.click();
}

if (!SERVER_ONLY && btns.open) {
  btns.open.addEventListener('click', pickFile);
}
if (!SERVER_ONLY && dropzone) {
  dropzone.addEventListener('click', pickFile);
}
if (!SERVER_ONLY && fileInput) {
  fileInput.addEventListener('change', (e) => {
    if (e.target.files[0]) loadFile(e.target.files[0]);
    fileInput.value = '';
  });
}

if (!SERVER_ONLY) {
  ['dragover', 'dragenter'].forEach(ev =>
    document.addEventListener(ev, (e) => {
      e.preventDefault();
      if (dropzone) dropzone.classList.add('dragover');
    }));
  ['dragleave', 'drop'].forEach(ev =>
    document.addEventListener(ev, (e) => {
      e.preventDefault();
      if (ev === 'drop' || e.target === document.documentElement) {
        if (dropzone) dropzone.classList.remove('dragover');
      }
    }));
  document.addEventListener('drop', async (e) => {
    e.preventDefault();
    const f = e.dataTransfer?.files?.[0];
    if (!f) return;
    loadFile(f);
  });
}

async function loadFile(file) {
  if (!isMarkdown(file)) {
    toast('Endast .md-, .markdown- eller .txt-filer stöds.');
    return;
  }
  const text = await file.text();
  currentMdText = text;
  currentName = file.name.replace(/\.(md|markdown|txt)$/i, '') || 'dokument';
  filenameBadge.textContent = file.name;
  filenameBadge.title = file.name;
  filenameBadge.style.display = 'inline-block';
  document.title = file.name + ' – MD Print';
  if (editor) editor.setValue(text);
  else if (fallbackTextarea) fallbackTextarea.value = text;
  await setMode('doc', { force: true });
  await render(text);
}

/* ---------- Bildhantering ---------- */
function resolveImages() {
  const boot = window.PRINT_BOOT || {};
  const base = boot.contentBase || '';
  content.querySelectorAll('img').forEach(img => {
    const src = img.getAttribute('src') || '';
    if (/^(https?:|data:|blob:)/i.test(src)) {
      attachImgFallback(img, src);
    } else if (base) {
      const resolved = base.replace(/\/?$/, '/') + src.replace(/^\.\//, '');
      img.setAttribute('src', resolved);
      attachImgFallback(img, resolved);
    } else {
      // Lokala/relativa sökvägar utan content-bas (t.ex. drag-and-drop)
      replaceWithPlaceholder(img, src);
    }
  });
}

function attachImgFallback(img, label) {
  img.addEventListener('error', () => replaceWithPlaceholder(img, label), { once: true });
}

function replaceWithPlaceholder(img, src) {
  const ph = document.createElement('div');
  ph.className = 'img-missing';
  const alt = img.getAttribute('alt');
  ph.innerHTML = '🖼️ Bild saknas: <code></code>' + (alt ? '<br><em></em>' : '');
  ph.querySelector('code').textContent = src;
  if (alt) ph.querySelector('em').textContent = alt;
  img.replaceWith(ph);
}

/* ---------- Dokument / Markdown-editor ---------- */
const editorPane = $('editorPane');
const monacoHost = $('monacoHost');
const modeToggle = $('modeToggle');
const modeDocBtn = $('modeDoc');
const modeCodeBtn = $('modeCode');
let mode = 'doc';
let editor = null;
let fallbackTextarea = null;
let monacoPromise = null;

const MONACO_BASE = 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.52.2/min';

function loadMonaco() {
  if (monacoPromise) return monacoPromise;
  monacoPromise = new Promise((resolve, reject) => {
    window.MonacoEnvironment = {
      getWorkerUrl: () => URL.createObjectURL(new Blob(
        ["self.MonacoEnvironment={baseUrl:'" + MONACO_BASE + "/'};importScripts('" + MONACO_BASE + "/vs/base/worker/workerMain.js');"],
        { type: 'text/javascript' }
      ))
    };
    const script = document.createElement('script');
    script.src = MONACO_BASE + '/vs/loader.min.js';
    script.onload = () => {
      window.require.config({ paths: { vs: MONACO_BASE + '/vs' } });
      window.require(
        ['vs/editor/editor.main'],
        () => resolve(window.monaco),
        (err) => reject(err || new Error('Monaco kunde inte laddas'))
      );
    };
    script.onerror = () => reject(new Error('Monaco kunde inte laddas (nätverk).'));
    document.head.appendChild(script);
  });
  return monacoPromise;
}

async function ensureEditor() {
  if (editor || fallbackTextarea) return;
  try {
    toast('Laddar editor…', 1500);
    const monaco = await loadMonaco();
    editor = monaco.editor.create(monacoHost, {
      value: currentMdText || '',
      language: 'markdown',
      theme: 'vs',
      automaticLayout: true,
      wordWrap: 'on',
      minimap: { enabled: false },
      fontSize: 14,
      lineNumbers: 'on',
      scrollBeyondLastLine: false,
      folding: true
    });
  } catch (err) {
    console.warn('Monaco föll tillbaka till textarea:', err);
    fallbackTextarea = document.createElement('textarea');
    fallbackTextarea.className = 'editor-fallback';
    fallbackTextarea.spellcheck = false;
    fallbackTextarea.value = currentMdText || '';
    fallbackTextarea.setAttribute('aria-label', 'Markdown-editor');
    monacoHost.appendChild(fallbackTextarea);
    toast('Monaco kunde inte laddas – enkel textredigerare används istället.');
  }
}

function editorValue() {
  if (editor) return editor.getValue();
  if (fallbackTextarea) return fallbackTextarea.value;
  return currentMdText || '';
}

async function setMode(next, opts = {}) {
  if (next === mode && !opts.force) return;
  if (next === 'code') {
    closeDocumentSearch();
    await ensureEditor();
    if (editor) editor.setValue(currentMdText || '');
    else if (fallbackTextarea) fallbackTextarea.value = currentMdText || '';
    page.style.display = 'none';
    editorPane.style.display = 'block';
    if (editor) { editor.layout(); editor.focus(); }
    else fallbackTextarea?.focus();
  } else {
    const value = editorValue();
    editorPane.style.display = 'none';
    page.style.display = 'block';
    if (currentMdText !== null && value !== currentMdText) {
      currentMdText = value;
      await render(value);
      toast('Dokumentet uppdaterades från redigerad Markdown.');
    }
  }
  mode = next;
  modeDocBtn.classList.toggle('active', next === 'doc');
  modeCodeBtn.classList.toggle('active', next === 'code');
}

async function ensureDocMode() {
  if (mode === 'code') await setMode('doc');
}

modeDocBtn.addEventListener('click', () => setMode('doc'));
modeCodeBtn.addEventListener('click', () => setMode('code'));

document.addEventListener('keydown', (e) => {
  if (!(e.ctrlKey || e.metaKey)) return;
  if (e.key.toLowerCase() === 'p' && mode === 'code') {
    e.preventDefault();
    btns.print.click();
  }
}, true);

/* ---------- Nya dokument, kopiering och statistik ---------- */
if (btnNew) {
  btnNew.addEventListener('click', async () => {
  if (currentMdText !== null) {
    const edited = mode === 'code' && editorValue() !== currentMdText;
    const question = edited
      ? 'Du har ändringar i editorn. Vill du ersätta dem med ett nytt dokument?'
      : 'Vill du stänga det aktuella dokumentet och skapa ett nytt?';
    if (!window.confirm(question)) return;
  }

  const starter = '# Nytt dokument\n\nBörja skriva här…\n';
  currentName = 'nytt-dokument';
  currentMdText = starter;
  filenameBadge.textContent = 'nytt-dokument.md';
  filenameBadge.title = 'Nytt, osparat dokument';
  filenameBadge.style.display = 'inline-block';
  document.title = 'nytt-dokument.md – MD Print';
  await render(starter);
  await setMode('code', { force: true });
  });
}

function copyHtmlFallback() {
  const selection = window.getSelection();
  const range = document.createRange();
  range.selectNodeContents(page);
  selection.removeAllRanges();
  selection.addRange(range);
  const ok = document.execCommand('copy');
  selection.removeAllRanges();
  return ok;
}

if (btns.copy) {
  btns.copy.addEventListener('click', async () => {
  try {
    await ensureDocMode();
    const plain = page.innerText;
    const html = '<article>' + page.innerHTML + '</article>';
    if (navigator.clipboard?.write && typeof ClipboardItem !== 'undefined') {
      await navigator.clipboard.write([new ClipboardItem({
        'text/plain': new Blob([plain], { type: 'text/plain' }),
        'text/html': new Blob([html], { type: 'text/html' })
      })]);
    } else if (!copyHtmlFallback()) {
      throw new Error('Webbläsaren nekade åtkomst till urklipp.');
    }
    toast('Det formaterade dokumentet kopierades.');
  } catch (err) {
    console.error(err);
    toast('Kopiering misslyckades: ' + (err.message || err));
  }
  });
}

const statsDialog = $('statsDialog');
const statsGrid = $('statsGrid');

btns.stats.addEventListener('click', async () => {
  await ensureDocMode();
  const text = content.innerText.trim();
  const words = text ? (text.match(/[\p{L}\p{N}]+(?:[’'-][\p{L}\p{N}]+)*/gu) || []).length : 0;
  const minutes = words ? Math.max(1, Math.ceil(words / 225)) : 0;
  const stats = [
    ['Ord', words.toLocaleString('sv-SE')],
    ['Tecken', text.length.toLocaleString('sv-SE')],
    ['Beräknad lästid', minutes ? minutes + ' min' : '0 min'],
    ['Rubriker', content.querySelectorAll('h1, h2, h3, h4, h5, h6').length],
    ['Länkar', content.querySelectorAll('a[href]').length],
    ['Bilder och diagram', content.querySelectorAll('img, .mermaid-wrap').length]
  ];
  statsGrid.innerHTML = stats.map(([label, value]) =>
    '<div><dt>' + label + '</dt><dd>' + value + '</dd></div>'
  ).join('');
  statsDialog.showModal();
});

$('btnStatsClose').addEventListener('click', () => statsDialog.close());
statsDialog.addEventListener('click', (e) => {
  if (e.target === statsDialog) statsDialog.close();
});

/* ---------- Uppläsning och MP3-export ---------- */
const ttsDialog = $('ttsDialog');
const ttsStatus = $('ttsStatus');
const ttsRate = $('ttsRate');
const btnTtsPlay = $('btnTtsPlay');
const btnTtsPause = $('btnTtsPause');
const btnTtsStop = $('btnTtsStop');
const btnTtsMp3 = $('btnTtsMp3');
let ttsChunks = [];
let ttsChunkIndex = 0;
let ttsActive = false;
let meSpeakReady = null;

function ttsText() {
  return content.innerText
    .replace(/\s+/g, ' ')
    .replace(/\s+([,.;:!?])/g, '$1')
    .trim();
}

function splitForSpeech(text, maxLength = 220) {
  const sentences = text.match(/[^.!?]+[.!?]+|[^.!?]+$/g) || [];
  const chunks = [];
  let current = '';
  for (const sentence of sentences) {
    const part = sentence.trim();
    if (!part) continue;
    if ((current + ' ' + part).trim().length <= maxLength) {
      current = (current + ' ' + part).trim();
      continue;
    }
    if (current) chunks.push(current);
    if (part.length <= maxLength) {
      current = part;
    } else {
      const words = part.split(/\s+/);
      current = '';
      for (const word of words) {
        if ((current + ' ' + word).trim().length > maxLength && current) {
          chunks.push(current);
          current = word;
        } else {
          current = (current + ' ' + word).trim();
        }
      }
    }
  }
  if (current) chunks.push(current);
  return chunks;
}

function buildTtsUnits() {
  const elements = Array.from(content.querySelectorAll(
    'h1, h2, h3, h4, h5, h6, p, li, pre, th, td, figcaption'
  )).filter(el => !(el.matches('li') && el.querySelector('p')));
  const units = [];
  for (const element of elements) {
    const clone = element.cloneNode(true);
    clone.querySelectorAll('ul, ol').forEach(list => list.remove());
    const text = clone.textContent.replace(/\s+/g, ' ').trim();
    if (!text) continue;
    for (const part of splitForSpeech(text)) units.push({ text: part, element });
  }
  return units;
}

function clearTtsHighlight() {
  content.querySelectorAll('.tts-reading').forEach(el => el.classList.remove('tts-reading'));
}

function showTtsPosition(element) {
  clearTtsHighlight();
  if (!element?.isConnected) return;
  element.classList.add('tts-reading');
  element.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
}

function preferredSwedishVoice() {
  const voices = window.speechSynthesis?.getVoices() || [];
  return voices.find(v => v.lang.toLowerCase() === 'sv-se') ||
    voices.find(v => v.lang.toLowerCase().startsWith('sv')) || null;
}

function updateTtsButtons(active) {
  btnTtsPause.disabled = !active;
  btnTtsStop.disabled = !active;
  btnTtsPlay.textContent = active ? '↻ Börja om' : '▶ Läs upp';
}

function speakNextChunk() {
  if (!ttsActive || ttsChunkIndex >= ttsChunks.length) {
    ttsActive = false;
    clearTtsHighlight();
    updateTtsButtons(false);
    ttsStatus.textContent = 'Uppläsningen är klar.';
    return;
  }
  const unit = ttsChunks[ttsChunkIndex];
  showTtsPosition(unit.element);
  const utterance = new SpeechSynthesisUtterance(unit.text);
  const voice = preferredSwedishVoice();
  if (voice) utterance.voice = voice;
  utterance.lang = voice?.lang || 'sv-SE';
  utterance.rate = Number(ttsRate.value);
  utterance.onend = () => {
    if (!ttsActive) return;
    ttsChunkIndex++;
    ttsStatus.textContent = `Läser del ${ttsChunkIndex + 1} av ${ttsChunks.length}…`;
    speakNextChunk();
  };
  utterance.onerror = (event) => {
    if (event.error === 'canceled' || event.error === 'interrupted') return;
    ttsActive = false;
    updateTtsButtons(false);
    ttsStatus.textContent = 'Uppläsningen avbröts: ' + event.error;
  };
  window.speechSynthesis.speak(utterance);
}

function stopSpeaking(message = 'Uppläsningen stoppades.') {
  ttsActive = false;
  window.speechSynthesis?.cancel();
  clearTtsHighlight();
  updateTtsButtons(false);
  btnTtsPause.textContent = '⏸ Pausa';
  ttsStatus.textContent = message;
}

btns.tts.addEventListener('click', async () => {
  await ensureDocMode();
  ttsStatus.textContent = ttsText() ? 'Redo.' : 'Dokumentet saknar text att läsa upp.';
  if (!ttsDialog.open) ttsDialog.show();
});

btnTtsPlay.addEventListener('click', () => {
  if (!('speechSynthesis' in window)) {
    ttsStatus.textContent = 'Webbläsaren saknar stöd för uppläsning.';
    return;
  }
  const text = ttsText();
  if (!text) return;
  window.speechSynthesis.cancel();
  ttsChunks = buildTtsUnits();
  if (!ttsChunks.length) return;
  ttsChunkIndex = 0;
  ttsActive = true;
  updateTtsButtons(true);
  btnTtsPause.textContent = '⏸ Pausa';
  ttsStatus.textContent = `Läser del 1 av ${ttsChunks.length}…`;
  speakNextChunk();
});

btnTtsPause.addEventListener('click', () => {
  if (window.speechSynthesis.paused) {
    window.speechSynthesis.resume();
    btnTtsPause.textContent = '⏸ Pausa';
    ttsStatus.textContent = `Fortsätter del ${ttsChunkIndex + 1} av ${ttsChunks.length}…`;
  } else {
    window.speechSynthesis.pause();
    btnTtsPause.textContent = '▶ Fortsätt';
    ttsStatus.textContent = 'Uppläsningen är pausad.';
  }
});
btnTtsStop.addEventListener('click', () => stopSpeaking());
$('btnTtsClose').addEventListener('click', () => {
  stopSpeaking('Redo.');
  ttsDialog.close();
});
ttsDialog.addEventListener('click', (e) => {
  if (e.target === ttsDialog) {
    stopSpeaking('Redo.');
    ttsDialog.close();
  }
});
ttsDialog.addEventListener('close', () => {
  if (ttsActive || window.speechSynthesis?.paused) stopSpeaking('Redo.');
});

function loadExternalScript(src) {
  return new Promise((resolve, reject) => {
    const script = document.createElement('script');
    script.src = src;
    script.onload = resolve;
    script.onerror = () => reject(new Error('Kunde inte hämta ' + src));
    document.head.appendChild(script);
  });
}

function ensureMeSpeak() {
  if (meSpeakReady) return meSpeakReady;
  meSpeakReady = (async () => {
    if (typeof globalThis.meSpeak === 'undefined') {
      await loadExternalScript('https://rolea.org/assets/mespeak/mespeak.js');
    }
    if (typeof globalThis.meSpeak === 'undefined') {
      throw new Error('Talsyntesmotorn kunde inte laddas från någon av reservkällorna.');
    }
    if (globalThis.meSpeak.isVoiceLoaded?.('sv')) return;
    await new Promise((resolve, reject) => {
      globalThis.meSpeak.loadVoice('sv', (success, message) => {
        if (success) resolve();
        else reject(new Error('Den svenska MP3-rösten kunde inte laddas: ' + message));
      });
    });
  })().catch(err => {
    meSpeakReady = null;
    throw err;
  });
  return meSpeakReady;
}

function synthesizeWav(text, options) {
  return new Promise((resolve, reject) => {
    const id = globalThis.meSpeak.speak(text, { ...options, rawdata: 'array' },
      (success, _soundId, stream) => {
        if (success && stream) resolve(stream);
        else reject(new Error('Talsyntesen kunde inte skapa ljud.'));
      });
    if (!id) reject(new Error('Talsyntesen kunde inte starta ljudgenereringen.'));
  });
}

function readWavPcm(bytes) {
  const data = bytes instanceof Uint8Array ? bytes : new Uint8Array(bytes);
  const view = new DataView(data.buffer, data.byteOffset, data.byteLength);
  const tag = (offset) => String.fromCharCode(
    view.getUint8(offset), view.getUint8(offset + 1),
    view.getUint8(offset + 2), view.getUint8(offset + 3)
  );
  if (tag(0) !== 'RIFF' || tag(8) !== 'WAVE') throw new Error('Ogiltigt WAV-ljud från talsyntesen.');

  let offset = 12;
  let format = null;
  let pcmOffset = 0;
  let pcmLength = 0;
  while (offset + 8 <= view.byteLength) {
    const id = tag(offset);
    const size = view.getUint32(offset + 4, true);
    if (id === 'fmt ') {
      format = {
        type: view.getUint16(offset + 8, true),
        channels: view.getUint16(offset + 10, true),
        sampleRate: view.getUint32(offset + 12, true),
        bits: view.getUint16(offset + 22, true)
      };
    } else if (id === 'data') {
      pcmOffset = offset + 8;
      pcmLength = Math.min(size, view.byteLength - pcmOffset);
      break;
    }
    offset += 8 + size + (size % 2);
  }
  if (!format || !pcmOffset || format.type !== 1 || ![8, 16].includes(format.bits)) {
    throw new Error('Ljudformatet från talsyntesen stöds inte.');
  }

  const bytesPerSample = format.bits / 8;
  const frameCount = Math.floor(pcmLength / bytesPerSample / format.channels);
  const channels = Array.from({ length: format.channels }, () => new Int16Array(frameCount));
  for (let frame = 0; frame < frameCount; frame++) {
    for (let channel = 0; channel < format.channels; channel++) {
      const pos = pcmOffset + (frame * format.channels + channel) * bytesPerSample;
      channels[channel][frame] = format.bits === 16
        ? view.getInt16(pos, true)
        : (view.getUint8(pos) - 128) << 8;
    }
  }
  return { sampleRate: format.sampleRate, channels };
}

btnTtsMp3.addEventListener('click', async () => {
  const text = ttsText();
  if (!text) {
    ttsStatus.textContent = 'Dokumentet saknar text att exportera.';
    return;
  }
  btnTtsMp3.disabled = true;
  try {
    if (typeof lamejs === 'undefined') throw new Error('MP3-kodaren kunde inte laddas.');
    ttsStatus.textContent = 'Laddar svensk MP3-röst…';
    await ensureMeSpeak();

    const parts = splitForSpeech(text, 1100);
    const mp3Parts = [];
    let encoder = null;
    let sampleRate = null;
    let channelCount = null;
    for (let partIndex = 0; partIndex < parts.length; partIndex++) {
      ttsStatus.textContent = `Skapar ljud ${partIndex + 1} av ${parts.length}…`;
      const wav = await synthesizeWav(parts[partIndex], {
        voice: 'sv',
        speed: Math.round(175 * Number(ttsRate.value)),
        pitch: 50
      });
      const pcm = readWavPcm(wav);
      if (!encoder) {
        sampleRate = pcm.sampleRate;
        channelCount = Math.min(2, pcm.channels.length);
        encoder = new lamejs.Mp3Encoder(channelCount, sampleRate, 96);
      } else if (pcm.sampleRate !== sampleRate || Math.min(2, pcm.channels.length) !== channelCount) {
        throw new Error('Talsyntesen ändrade ljudformat mitt i dokumentet.');
      }
      const blockSize = 1152;
      for (let i = 0; i < pcm.channels[0].length; i += blockSize) {
        const left = pcm.channels[0].subarray(i, i + blockSize);
        const right = channelCount === 2 ? pcm.channels[1].subarray(i, i + blockSize) : undefined;
        const encoded = channelCount === 2
          ? encoder.encodeBuffer(left, right)
          : encoder.encodeBuffer(left);
        if (encoded.length) mp3Parts.push(new Uint8Array(encoded));
      }
      await new Promise(resolve => setTimeout(resolve, 0));
    }
    const tail = encoder.flush();
    if (tail.length) mp3Parts.push(new Uint8Array(tail));

    const blob = new Blob(mp3Parts, { type: 'audio/mpeg' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = exportName() + '.mp3';
    document.body.appendChild(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(link.href), 5000);
    ttsStatus.textContent = 'MP3 sparad: ' + exportName() + '.mp3';
  } catch (err) {
    console.error(err);
    ttsStatus.textContent = 'MP3-export misslyckades: ' + (err.message || err);
  } finally {
    btnTtsMp3.disabled = false;
  }
});

/* ---------- Sökning i det renderade dokumentet ---------- */
const searchPanel = $('searchPanel');
const searchInput = $('searchInput');
const searchCount = $('searchCount');
let searchHits = [];
let searchIndex = -1;

function clearSearchHighlights() {
  content.querySelectorAll('mark.search-hit').forEach(mark => mark.replaceWith(...mark.childNodes));
  content.normalize();
  searchHits = [];
  searchIndex = -1;
  searchCount.textContent = '0 träffar';
}

function showSearchHit(index) {
  if (!searchHits.length) return;
  searchHits.forEach(mark => mark.classList.remove('active'));
  searchIndex = (index + searchHits.length) % searchHits.length;
  const active = searchHits[searchIndex];
  active.classList.add('active');
  searchCount.textContent = `${searchIndex + 1} av ${searchHits.length}`;
  active.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function runDocumentSearch() {
  clearSearchHighlights();
  const query = searchInput.value.trim();
  if (!query) return;
  const needle = query.toLocaleLowerCase('sv-SE');
  const walker = document.createTreeWalker(content, NodeFilter.SHOW_TEXT);
  const nodes = [];
  while (walker.nextNode()) {
    if (!walker.currentNode.parentElement?.closest('svg')) nodes.push(walker.currentNode);
  }

  for (const node of nodes) {
    const text = node.nodeValue;
    const lower = text.toLocaleLowerCase('sv-SE');
    let from = 0;
    let at = lower.indexOf(needle, from);
    if (at < 0) continue;
    const fragment = document.createDocumentFragment();
    while (at >= 0) {
      fragment.appendChild(document.createTextNode(text.slice(from, at)));
      const mark = document.createElement('mark');
      mark.className = 'search-hit';
      mark.textContent = text.slice(at, at + query.length);
      fragment.appendChild(mark);
      searchHits.push(mark);
      from = at + query.length;
      at = lower.indexOf(needle, from);
    }
    fragment.appendChild(document.createTextNode(text.slice(from)));
    node.replaceWith(fragment);
  }
  searchCount.textContent = searchHits.length === 1 ? '1 träff' : `${searchHits.length} träffar`;
  if (searchHits.length) showSearchHit(0);
}

function openDocumentSearch() {
  stopSpeaking('Redo.');
  searchPanel.hidden = false;
  searchInput.focus();
  searchInput.select();
}

function closeDocumentSearch() {
  searchPanel.hidden = true;
  clearSearchHighlights();
}

btns.search.addEventListener('click', openDocumentSearch);
searchInput.addEventListener('input', runDocumentSearch);
$('searchNext').addEventListener('click', () => showSearchHit(searchIndex + 1));
$('searchPrev').addEventListener('click', () => showSearchHit(searchIndex - 1));
$('searchClose').addEventListener('click', closeDocumentSearch);
searchInput.addEventListener('keydown', (e) => {
  if (e.key === 'Enter') {
    e.preventDefault();
    showSearchHit(searchIndex + (e.shiftKey ? -1 : 1));
  } else if (e.key === 'Escape') {
    closeDocumentSearch();
  }
});
document.addEventListener('keydown', (e) => {
  if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'f' && mode === 'doc' && currentMdText !== null) {
    e.preventDefault();
    openDocumentSearch();
  }
});

/* ---------- Inställningspanel (UI) ---------- */
const settingsPanel = $('settingsPanel');
const btnSettings = $('btnSettings');
const exportMenu = $('exportMenu');
const exportMenuPanel = $('exportMenuPanel');
const btnExportToggle = $('btnExportToggle');
const selVer = $('setMermaidVer');
const selWidth = $('setMermaidWidth');
const selAlign = $('setMermaidAlign');
const selToc = $('setToc');
const selFontSize = $('setFontSize');
const selPageWidth = $('setPageWidth');

selVer.value = settings.mermaidVersion;
selWidth.value = settings.mermaidWidth;
selAlign.value = settings.mermaidAlign;
selToc.value = settings.toc;
selFontSize.value = settings.fontSize;
selPageWidth.value = settings.pageWidth;

function closeExportMenu() {
  exportMenuPanel.hidden = true;
  btnExportToggle.setAttribute('aria-expanded', 'false');
}

btnExportToggle.addEventListener('click', (e) => {
  e.stopPropagation();
  const willOpen = exportMenuPanel.hidden;
  exportMenuPanel.hidden = !willOpen;
  btnExportToggle.setAttribute('aria-expanded', String(willOpen));
  settingsPanel.hidden = true;
});
exportMenuPanel.addEventListener('click', (e) => {
  e.stopPropagation();
  if (e.target.closest('button')) closeExportMenu();
});

btnSettings.addEventListener('click', (e) => {
  e.stopPropagation();
  closeExportMenu();
  settingsPanel.hidden = !settingsPanel.hidden;
});
document.addEventListener('click', (e) => {
  if (!settingsPanel.hidden && !settingsPanel.contains(e.target) && e.target !== btnSettings) {
    settingsPanel.hidden = true;
  }
  if (!exportMenuPanel.hidden && !exportMenu.contains(e.target)) closeExportMenu();
});
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    settingsPanel.hidden = true;
    closeExportMenu();
  }
});

selWidth.addEventListener('change', () => {
  settings.mermaidWidth = selWidth.value;
  saveSettings();
  applyMermaidStyle();
});
selAlign.addEventListener('change', () => {
  settings.mermaidAlign = selAlign.value;
  saveSettings();
  applyMermaidStyle();
});
selToc.addEventListener('change', () => {
  settings.toc = selToc.value;
  saveSettings();
  if (currentMdText !== null) buildToc();
});
[selFontSize, selPageWidth].forEach(select => select.addEventListener('change', () => {
  settings.fontSize = selFontSize.value;
  settings.pageWidth = selPageWidth.value;
  saveSettings();
  applyMermaidStyle();
}));
selVer.addEventListener('change', async () => {
  settings.mermaidVersion = selVer.value;
  saveSettings();
  mermaidInstance = null;          // tvinga omladdning av vald version
  mermaidLoadedVer = null;
  if (currentMdText !== null) {
    toast('Laddar Mermaid @' + selVer.value + ' och renderar om…');
    await render(currentMdText);
  }
});

/* ---------- YAML-frontmatter ---------- */
function extractFrontmatter(text) {
  const m = text.match(/^\uFEFF?---[ \t]*\r?\n([\s\S]*?)\r?\n---[ \t]*\r?\n?/);
  if (!m) return { meta: null, body: text };
  return { meta: parseSimpleYaml(m[1]), body: text.slice(m[0].length) };
}

/* Enkel YAML-parser: key: value, inline-listor [a, b], samt listor med "- item" */
function parseSimpleYaml(src) {
  const meta = [];
  const lines = src.split(/\r?\n/);
  let current = null;
  const unquote = (s) => s.trim().replace(/^["'](.*)["']$/, '$1');

  for (const raw of lines) {
    if (!raw.trim() || raw.trim().startsWith('#')) continue;

    const listItem = raw.match(/^\s+-\s+(.*)$/);
    if (listItem && current && Array.isArray(current[1])) {
      current[1].push(unquote(listItem[1]));
      continue;
    }

    const kv = raw.match(/^([A-Za-zÅÄÖåäö0-9_\-. ]+):\s*(.*)$/);
    if (!kv) continue;
    const key = kv[1].trim();
    let val = kv[2].trim();

    if (val === '') {
      current = [key, []];            // troligen lista på följande rader
      meta.push(current);
    } else if (/^\[.*\]$/.test(val)) { // inline-lista
      const items = val.slice(1, -1).split(',').map(unquote).filter(Boolean);
      current = [key, items];
      meta.push(current);
    } else {
      current = [key, unquote(val)];
      meta.push(current);
    }
  }
  // Nycklar utan värde och utan listrader → tom sträng
  return meta.map(([k, v]) => [k, Array.isArray(v) && v.length === 0 ? '' : v]);
}

function renderFrontmatter(meta) {
  frontmatterEl.innerHTML = '';
  frontmatterEl.style.display = 'none';
  if (!meta || !meta.length) return;

  const esc = (s) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  const label = (k) => esc(k.charAt(0).toUpperCase() + k.slice(1));

  let rows = '';
  for (const [key, val] of meta) {
    let cell;
    if (Array.isArray(val)) {
      cell = val.map(v => '<span class="fm-tag">' + esc(v) + '</span>').join('');
    } else {
      cell = esc(val);
    }
    rows += '<tr><td class="fm-key">' + label(key) + '</td><td class="fm-val">' + cell + '</td></tr>';
  }
  frontmatterEl.innerHTML =
    '<div class="fm-heading">Metadata</div><table class="fm-table"><tbody>' + rows + '</tbody></table>';
  frontmatterEl.style.display = 'block';
}

/* ---------- Innehållsförteckning ---------- */
const tocEl = $('toc');

function slugify(text) {
  return text.toLowerCase().trim()
    .replace(/[åä]/g, 'a').replace(/ö/g, 'o')
    .replace(/[^a-z0-9\s-]/g, '')
    .replace(/\s+/g, '-')
    .replace(/-+/g, '-')
    .replace(/^-|-$/g, '') || 'avsnitt';
}

function buildToc() {
  tocEl.innerHTML = '';
  tocEl.hidden = true;
  if (settings.toc !== 'on') return;

  const headings = content.querySelectorAll('h1, h2, h3');
  if (headings.length < 2) return;

  const usedIds = new Set();
  let items = '';
  headings.forEach(h => {
    if (!h.id) {
      let id = slugify(h.textContent);
      let n = 1;
      while (usedIds.has(id) || document.getElementById(id)) id = slugify(h.textContent) + '-' + (++n);
      h.id = id;
    }
    usedIds.add(h.id);
    const li = document.createElement('li');
    li.className = 'toc-' + h.tagName.toLowerCase();
    const a = document.createElement('a');
    a.href = '#' + h.id;
    a.textContent = h.textContent;
    li.appendChild(a);
    items += li.outerHTML;
  });

  tocEl.innerHTML = '<div class="toc-title">Innehåll</div><ul>' + items + '</ul>';
  tocEl.hidden = false;
}

/* ---------- Rendering ---------- */
async function render(mdText) {
  applyDocumentTitle(mdText);
  const { meta, body } = extractFrontmatter(mdText);
  renderFrontmatter(meta);

  const rawHtml = marked.parse(body);
  content.innerHTML = DOMPurify.sanitize(rawHtml, {
    ADD_TAGS: ['input'],
    ADD_ATTR: ['type', 'checked', 'disabled']
  });

  resolveImages();

  // GFM-checklistor: ersätt inputs med snygga, utskriftsvänliga rutor
  content.querySelectorAll('input[type="checkbox"]').forEach(cb => {
    const li = cb.closest('li');
    if (li) li.classList.add('task-item');
    const span = document.createElement('span');
    span.className = 'task-check' + (cb.checked ? ' checked' : '');
    span.setAttribute('aria-hidden', 'true');
    cb.replaceWith(span);
  });

  // Hitta mermaid-kodblock och ersätt med diagram-behållare
  const mermaidBlocks = content.querySelectorAll('pre > code.language-mermaid');
  if (mermaidBlocks.length) {
    let mermaid = null;
    try {
      mermaid = await getMermaid();
    } catch (err) {
      console.warn('Mermaid kunde inte laddas:', err);
    }
    for (const codeEl of mermaidBlocks) {
      const src = codeEl.textContent;
      const wrap = document.createElement('div');
      wrap.className = 'mermaid-wrap';
      codeEl.closest('pre').replaceWith(wrap);
      if (!mermaid) {
        wrap.outerHTML = '<div class="mermaid-error"><strong>Mermaid kunde inte laddas</strong> (version @' +
          String(settings.mermaidVersion).replace(/</g, '&lt;') +
          '). Kontrollera internetanslutningen eller byt version under ⚙️ Inställningar.</div>';
        continue;
      }
      try {
        const id = 'mmd-' + (++mermaidSeq);
        const { svg } = await mermaid.render(id, src);
        wrap.innerHTML = svg;
        const svgEl = wrap.querySelector('svg');
        if (svgEl) {
          svgEl.removeAttribute('height');
          svgEl.style.maxWidth = '100%';
        }
      } catch (err) {
        wrap.outerHTML = '<div class="mermaid-error"><strong>Mermaid-fel:</strong> ' +
          String(err.message || err).replace(/</g, '&lt;') + '</div>';
      }
    }
  }

  buildToc();

  if (dropzone) dropzone.style.display = 'none';
  page.style.display = 'block';
  enableToolbar();
  window.scrollTo({ top: 0 });
}

/* ---------- Fristående HTML-export ---------- */
function downloadTextFile(text, extension, mimeType) {
  const blob = new Blob([text], { type: mimeType + ';charset=utf-8' });
  const link = document.createElement('a');
  link.href = URL.createObjectURL(blob);
  link.download = exportName() + extension;
  document.body.appendChild(link);
  link.click();
  link.remove();
  setTimeout(() => URL.revokeObjectURL(link.href), 4000);
}

btns.md.addEventListener('click', () => {
  const markdown = mode === 'code' ? editorValue() : currentMdText;
  if (markdown === null) return;
  downloadTextFile(markdown, '.md', 'text/markdown');
  toast('Markdown sparad: ' + exportName() + '.md');
});

btns.txt.addEventListener('click', async () => {
  await ensureDocMode();
  clearSearchHighlights();
  const plainText = content.innerText
    .replace(/[ \t]+\n/g, '\n')
    .replace(/\n{3,}/g, '\n\n')
    .trim() + '\n';
  downloadTextFile('\uFEFF' + plainText, '.txt', 'text/plain');
  toast('Textfil sparad: ' + exportName() + '.txt');
});

btns.html.addEventListener('click', async () => {
  await ensureDocMode();
  const clone = page.cloneNode(true);
  clone.style.display = 'block';
  clone.querySelectorAll('mark.search-hit').forEach(mark => mark.replaceWith(...mark.childNodes));
  clone.querySelectorAll('.tts-reading').forEach(el => el.classList.remove('tts-reading'));
  let mainCss = document.querySelector('style')?.textContent || '';
  if (!mainCss) {
    const link = document.querySelector('link[rel="stylesheet"][href*="print.css"]');
    if (link) {
      try {
        const res = await fetch(link.href);
        if (res.ok) mainCss = await res.text();
      } catch { /* ignore */ }
    }
  }
  const safeTitle = (currentName || 'dokument')
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  const readerCss = `:root{--reader-font-size:${settings.fontSize};--reader-width:${settings.pageWidth};` +
    `--mmd-width:${settings.mermaidWidth === 'auto' ? 'auto' : settings.mermaidWidth};` +
    `--mmd-align:${settings.mermaidAlign}}` +
    `body{background:#e8ecee;padding:1px}#page{display:block}`;
  const exported = '<!DOCTYPE html><html lang="sv"><head><meta charset="UTF-8">' +
    '<meta name="viewport" content="width=device-width,initial-scale=1.0"><title>' + safeTitle +
    '</title><style>' + mainCss + readerCss + '</style></head><body>' +
    clone.outerHTML + '</body></html>';
  const blob = new Blob([exported], { type: 'text/html;charset=utf-8' });
  const link = document.createElement('a');
  link.href = URL.createObjectURL(blob);
  link.download = exportName() + '.html';
  document.body.appendChild(link);
  link.click();
  link.remove();
  setTimeout(() => URL.revokeObjectURL(link.href), 4000);
  toast('HTML sparad: ' + exportName() + '.html');
});

/* ---------- Skriv ut / PDF ---------- */
btns.print.addEventListener('click', async () => {
  await ensureDocMode();
  clearSearchHighlights();
  window.print();
});
btns.pdf.addEventListener('click', async () => {
  try {
    await ensureDocMode();
    clearSearchHighlights();
    if (typeof html2pdf === 'undefined') {
      throw new Error('PDF-biblioteket kunde inte laddas. Kontrollera internetanslutningen.');
    }
    toast('Skapar PDF…');
    await html2pdf().set({
      margin: [12, 12, 14, 12],
      filename: exportName() + '.pdf',
      image: { type: 'jpeg', quality: 0.98 },
      html2canvas: {
        scale: 2,
        useCORS: true,
        backgroundColor: '#ffffff',
        logging: false
      },
      jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
      pagebreak: { mode: ['css', 'legacy'], avoid: ['pre', 'table', '.mermaid-wrap', 'img'] }
    }).from(page).save();
    toast('PDF sparad: ' + exportName() + '.pdf');
  } catch (err) {
    console.error(err);
    toast('PDF-export misslyckades: ' + (err.message || err));
  }
});

/* ---------- SVG → PNG (för DOCX) ---------- */
function svgToPngDataUri(svgEl, scale = 2) {
  return new Promise((resolve, reject) => {
    const clone = svgEl.cloneNode(true);
    const bbox = svgEl.getBoundingClientRect();
    const w = Math.max(1, Math.ceil(bbox.width)) || 800;
    const h = Math.max(1, Math.ceil(bbox.height)) || 500;
    clone.setAttribute('width', w);
    clone.setAttribute('height', h);
    clone.setAttribute('xmlns', 'http://www.w3.org/2000/svg');

    const svgStr = new XMLSerializer().serializeToString(clone);
    const svgUri = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svgStr);
    const img = new Image();
    img.onload = () => {
      const canvas = document.createElement('canvas');
      canvas.width = w * scale;
      canvas.height = h * scale;
      const ctx = canvas.getContext('2d');
      ctx.fillStyle = '#FFFFFF';
      ctx.fillRect(0, 0, canvas.width, canvas.height);
      ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
      resolve({ dataUri: canvas.toDataURL('image/png'), w, h });
    };
    img.onerror = () => reject(new Error('Kunde inte rastrera SVG.'));
    img.src = svgUri;
  });
}

/* ---------- DOCX-export ---------- */
btns.docx.addEventListener('click', async () => {
  try {
    await ensureDocMode();
    clearSearchHighlights();
    if (typeof htmlDocx === 'undefined' || !htmlDocx.asBlob) {
      toast('DOCX-biblioteket kunde inte initieras. Ladda om sidan och försök igen.');
      return;
    }
    toast('Skapar DOCX…');
    const cloned = document.createElement('div');
    if (frontmatterEl.style.display !== 'none' && frontmatterEl.innerHTML) {
      cloned.appendChild(frontmatterEl.cloneNode(true));
    }
    if (!tocEl.hidden && tocEl.innerHTML) {
      cloned.appendChild(tocEl.cloneNode(true));
    }
    cloned.appendChild(content.cloneNode(true));

    // Ersätt SVG (mermaid m.fl.) med PNG-bilder – Word hanterar inte inbäddad SVG i altChunk
    const liveSvgs = content.querySelectorAll('svg');
    const clonedSvgs = cloned.querySelectorAll('svg');
    for (let i = 0; i < clonedSvgs.length; i++) {
      try {
        const { dataUri, w, h } = await svgToPngDataUri(liveSvgs[i]);
        const img = document.createElement('img');
        img.src = dataUri;
        const maxW = 640;
        const scale = w > maxW ? maxW / w : 1;
        img.width = Math.round(w * scale);
        img.height = Math.round(h * scale);
        clonedSvgs[i].replaceWith(img);
      } catch {
        clonedSvgs[i].replaceWith(document.createTextNode('[Diagram kunde inte exporteras]'));
      }
    }

    // Checklistor → symboler i Word
    cloned.querySelectorAll('.task-check').forEach(tc => {
      tc.replaceWith(document.createTextNode(tc.classList.contains('checked') ? '☑ ' : '☐ '));
    });
    cloned.querySelectorAll('input[type="checkbox"]').forEach(cb => {
      cb.replaceWith(document.createTextNode(cb.checked ? '☑ ' : '☐ '));
    });

    // Bäddar in bilder (blob:-URL:er fungerar inte i Word) som PNG data-URI:er
    const liveImgs = Array.from(content.querySelectorAll('img'));
    const clonedImgs = Array.from(cloned.querySelectorAll('img')).filter(im => !im.src.startsWith('data:'));
    for (const cim of clonedImgs) {
      const live = liveImgs.find(li => li.getAttribute('src') === cim.getAttribute('src'));
      try {
        const el = live || cim;
        if (!el.complete || !el.naturalWidth) throw new Error('ej laddad');
        const canvas = document.createElement('canvas');
        canvas.width = el.naturalWidth;
        canvas.height = el.naturalHeight;
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#FFFFFF';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(el, 0, 0);
        cim.src = canvas.toDataURL('image/png');
        const maxW = 640;
        if (el.naturalWidth > maxW) {
          cim.width = maxW;
          cim.height = Math.round(el.naturalHeight * (maxW / el.naturalWidth));
        } else {
          cim.width = el.naturalWidth;
          cim.height = el.naturalHeight;
        }
      } catch {
        const ph = document.createElement('p');
        ph.textContent = '[Bild kunde inte bäddas in: ' + (cim.getAttribute('alt') || cim.getAttribute('src') || '') + ']';
        cim.replaceWith(ph);
      }
    }

    const docCss = `
      body { font-family: Arial, Helvetica, sans-serif; font-size: 11pt; line-height: 1.5; color: #333333; }
      h1 { font-size: 20pt; color: #0077bc; border-bottom: 2pt solid #0077bc; padding-bottom: 4pt; }
      h2 { font-size: 15pt; color: #1F1F1F; border-bottom: 1pt solid #d1d9dc; padding-bottom: 3pt; }
      h3 { font-size: 13pt; color: #1F1F1F; }
      h4, h5, h6 { font-size: 11pt; color: #1F1F1F; }
      a { color: #005799; }
      blockquote { border-left: 3pt solid #0077bc; background: #F2F9F9; padding: 6pt 10pt; margin: 0 0 10pt; color: #444444; }
      code { font-family: Consolas, monospace; font-size: 10pt; background: #F4F9FC; }
      pre { background: #F4F9FC; border: 1pt solid #d1d9dc; padding: 8pt; font-family: Consolas, monospace; font-size: 9pt; }
      table { border-collapse: collapse; width: 100%; font-size: 10pt; }
      th, td { border: 1pt solid #d1d9dc; padding: 4pt 6pt; text-align: left; vertical-align: top; }
      th { background: #0077bc; color: #ffffff; font-weight: bold; }
      hr { border: none; border-top: 1pt solid #d1d9dc; }
      img { max-width: 100%; }
      .fm-box { background: #F4F9FC; border: 1pt solid #d1d9dc; border-left: 3pt solid #0077bc; padding: 8pt 10pt; margin: 0 0 14pt; }
      .fm-heading { font-size: 8pt; font-weight: bold; text-transform: uppercase; letter-spacing: 1pt; color: #0077bc; margin: 0 0 4pt; }
      table.fm-table { width: 100%; border-collapse: collapse; font-size: 10pt; }
      td.fm-key { color: #0077bc; font-weight: bold; white-space: nowrap; vertical-align: top; padding: 2pt 12pt 2pt 0; border: none; background: transparent; }
      td.fm-val { vertical-align: top; padding: 2pt 0; border: none; background: transparent; }
      .fm-tag { background: #ffffff; border: 1pt solid #d1d9dc; padding: 0 4pt; font-size: 9pt; }
      .img-missing { border: 1pt dashed #d1d9dc; background: #fafbfc; color: #6E6E6E; padding: 8pt; text-align: center; font-size: 9pt; }
      li.task-item { list-style: none; }
      .toc-box { background: #F2F9F9; border: 1pt solid #d1d9dc; padding: 8pt 10pt; margin: 0 0 14pt; }
      .toc-title { font-size: 8pt; font-weight: bold; text-transform: uppercase; letter-spacing: 1pt; color: #0077bc; margin: 0 0 4pt; }
      .toc-box ul { list-style: none; margin: 0; padding: 0; }
      .toc-box li { font-size: 10pt; margin: 1pt 0; }
      .toc-box li.toc-h2 { margin-left: 14pt; }
      .toc-box li.toc-h3 { margin-left: 28pt; font-size: 9pt; }
      .toc-box a { color: #005799; text-decoration: none; }
    `;

    const fullHtml =
      '<!DOCTYPE html><html><head><meta charset="utf-8"><style>' + docCss +
      '</style></head><body>' + cloned.innerHTML + '</body></html>';

    const blob = htmlDocx.asBlob(fullHtml, {
      orientation: 'portrait',
      margins: { top: 1020, right: 907, bottom: 1020, left: 907 } /* ≈18/16 mm i twips */
    });

    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = exportName() + '.docx';
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(a.href), 4000);
    toast('DOCX sparad: ' + exportName() + '.docx');
  } catch (err) {
    console.error(err);
    toast('DOCX-export misslyckades: ' + (err.message || err));
  }
});

/* ---------- Boot från PHP (?file=) ---------- */
async function bootFromServer() {
  const boot = window.PRINT_BOOT;
  if (!boot || typeof boot.content !== 'string') return;
  currentMdText = boot.content;
  currentName = (boot.basename || 'dokument').replace(/\.(md|markdown|txt)$/i, '') || 'dokument';
  const displayName = boot.basename || (currentName + '.md');
  filenameBadge.textContent = displayName;
  filenameBadge.title = boot.filename || displayName;
  filenameBadge.style.display = 'inline-block';
  if (editor) editor.setValue(currentMdText);
  else if (fallbackTextarea) fallbackTextarea.value = currentMdText;
  await setMode('doc', { force: true });
  await render(currentMdText);
}

bootFromServer().catch((err) => {
  console.error(err);
  toast('Kunde inte ladda dokumentet: ' + (err.message || err));
});
