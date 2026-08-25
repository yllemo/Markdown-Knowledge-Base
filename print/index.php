<?php
/**
 * print/index.php — Print / export viewer for Markdown Knowledge Base.
 * Loads a .md file the same way as /edit (query: file=, optional style=).
 * UI and client features come from print.html (print.css + print-app.js).
 */
$filename = isset($_GET['file']) ? $_GET['file'] : '';
$style = isset($_GET['style']) ? strtolower($_GET['style']) : 'light';

if (!$filename || !preg_match('/\.md$/i', $filename)) {
    die('Invalid file parameter. Must be a .md file.');
}

// Security: prevent directory traversal
if (strpos($filename, '..') !== false || strpos($filename, '\\') !== false) {
    die('Invalid file path.');
}

$contentDir = realpath(__DIR__ . '/../content');
$filePath = realpath($contentDir . '/' . $filename);

if (!$filePath || !file_exists($filePath) || strpos($filePath, $contentDir) !== 0) {
    die('File not found.');
}

$content = file_get_contents($filePath);
$basename = basename($filename);

/**
 * First ATX H1 (# Title) in markdown, after optional YAML front matter.
 */
function print_first_h1(string $md): ?string
{
    if (preg_match('/^\xEF\xBB\xBF?---[ \t]*\R[\s\S]*?\R---[ \t]*\R?/', $md, $fm)) {
        $md = substr($md, strlen($fm[0]));
    }
    if (preg_match('/^#\s+(.+?)\s*$/m', $md, $m)) {
        $heading = trim($m[1]);
        $heading = rtrim($heading, " \t#");
        $heading = trim($heading);
        return $heading !== '' ? $heading : null;
    }
    return null;
}

$docTitle = print_first_h1($content) ?? 'MD Print';
$titleEsc = htmlspecialchars($docTitle, ENT_QUOTES, 'UTF-8');

$dir = dirname(str_replace('\\', '/', $filename));
$contentBase = '../content/' . ($dir === '.' ? '' : $dir . '/');

$boot = [
    'content' => $content,
    'filename' => $filename,
    'basename' => $basename,
    'contentBase' => $contentBase,
    'style' => $style === 'dark' ? 'dark' : 'light',
    'docTitle' => $docTitle,
];
?>
<!DOCTYPE html>
<html lang="sv">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $titleEsc ?></title>
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<link rel="stylesheet" href="print.css">
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/dompurify@3/dist/purify.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/html2pdf.js@0.10.1/dist/html2pdf.bundle.min.js"></script>
<script src="https://www.masswerk.at/mespeak/mespeak.js"></script>
<script src="https://cdn.jsdelivr.net/npm/lamejs@1.2.1/lame.min.js"></script>
<script src="vendor/html-docx.js"></script>
</head>
<body>

<header class="toolbar">
  <div class="brand" id="brand" title="<?= $titleEsc ?>"><?= $titleEsc ?></div>
  <span class="filename-badge" id="filenameBadge" title=""></span>
  <div class="mode-toggle" id="modeToggle">
    <button class="mode-btn active" id="modeDoc" type="button">📄 Dokument</button>
    <button class="mode-btn" id="modeCode" type="button">✏️ Markdown</button>
  </div>
  <button class="btn btn-outline" id="btnStats" disabled title="Visa dokumentstatistik">▥ Statistik</button>
  <button class="btn btn-outline" id="btnTts" disabled title="Läs upp dokumentet eller skapa MP3">🔊 Läs upp</button>
  <button class="btn btn-outline" id="btnSearch" disabled title="Sök i dokumentet (Ctrl+F)">🔎 Sök</button>
  <button class="btn btn-outline" id="btnPrint" disabled>🖨️ Skriv ut</button>
  <div class="export-menu" id="exportMenu">
    <button class="btn btn-outline" id="btnExportToggle" disabled aria-haspopup="true" aria-expanded="false">⬇️ Spara som ▾</button>
    <div class="export-menu-panel" id="exportMenuPanel" hidden>
      <button id="btnPdf" disabled>📄 PDF-dokument (.pdf)</button>
      <button id="btnDocx" disabled>📝 Word-dokument (.docx)</button>
      <button id="btnHtml" disabled>🌐 Webbsida (.html)</button>
      <button id="btnSaveMd" disabled>⌨️ Markdown (.md)</button>
      <button id="btnSaveTxt" disabled>📃 Ren text (.txt)</button>
    </div>
  </div>
  <button class="btn btn-outline" id="btnSettings" title="Inställningar">⚙️</button>
</header>

<div id="settingsPanel" hidden>
  <div class="sp-title">Inställningar</div>
  <label class="sp-field">Mermaid-version
    <select id="setMermaidVer">
      <option value="latest">@latest – senaste (standard)</option>
      <option value="11">11 – senaste 11.x</option>
      <option value="10">10 – senaste 10.x</option>
    </select>
  </label>
  <label class="sp-field">Diagrambredd
    <select id="setMermaidWidth">
      <option value="auto">Auto – naturlig storlek (standard)</option>
      <option value="100%">100 % av sidbredden</option>
      <option value="75%">75 % av sidbredden</option>
      <option value="50%">50 % av sidbredden</option>
    </select>
  </label>
  <label class="sp-field">Diagramjustering
    <select id="setMermaidAlign">
      <option value="center">Centrerad (standard)</option>
      <option value="left">Vänsterställd</option>
    </select>
  </label>
  <label class="sp-field">Innehållsförteckning
    <select id="setToc">
      <option value="off">Av (standard)</option>
      <option value="on">Visa – rubriknivå 1–3</option>
    </select>
  </label>
  <label class="sp-field">Textstorlek
    <select id="setFontSize">
      <option value="90%">Kompakt</option>
      <option value="100%">Normal (standard)</option>
      <option value="115%">Stor</option>
      <option value="130%">Mycket stor</option>
    </select>
  </label>
  <label class="sp-field">Sidbredd
    <select id="setPageWidth">
      <option value="210mm">A4 (standard)</option>
      <option value="960px">Bred</option>
      <option value="1200px">Mycket bred</option>
    </select>
  </label>
  <p class="sp-hint">Inställningarna sparas automatiskt i webbläsaren.</p>
</div>

<div id="searchPanel" hidden>
  <input id="searchInput" type="search" placeholder="Sök i dokumentet…" aria-label="Sök i dokumentet">
  <span id="searchCount">0 träffar</span>
  <button id="searchPrev" type="button" title="Föregående träff">↑</button>
  <button id="searchNext" type="button" title="Nästa träff">↓</button>
  <button id="searchClose" type="button" title="Stäng sökning">×</button>
</div>

<article id="page">
  <div id="frontmatter" class="fm-box"></div>
  <nav id="toc" class="toc-box" hidden></nav>
  <div id="content"></div>
</article>

<div id="editorPane"><div id="monacoHost"></div></div>

<dialog id="statsDialog" aria-labelledby="statsTitle">
  <div class="stats-head">
    <h2 id="statsTitle">Dokumentstatistik</h2>
    <button class="stats-close" id="btnStatsClose" type="button" aria-label="Stäng">×</button>
  </div>
  <dl class="stats-grid" id="statsGrid"></dl>
</dialog>

<dialog id="ttsDialog" aria-labelledby="ttsTitle">
  <div class="stats-head">
    <h2 id="ttsTitle">Uppläsning och MP3</h2>
    <button class="stats-close" id="btnTtsClose" type="button" aria-label="Stäng">×</button>
  </div>
  <div class="tts-body">
    <label class="tts-field">Uppläsningshastighet
      <select id="ttsRate">
        <option value="0.8">Långsam</option>
        <option value="1">Normal</option>
        <option value="1.2">Snabb</option>
        <option value="1.5" selected>Mycket snabb (standard)</option>
        <option value="2">2× hastighet</option>
        <option value="3">3× hastighet</option>
        <option value="4">4× hastighet</option>
      </select>
    </label>
    <div class="tts-actions">
      <button class="btn" id="btnTtsPlay" type="button">▶ Läs upp</button>
      <button class="btn btn-secondary" id="btnTtsPause" type="button" disabled>⏸ Pausa</button>
      <button class="btn btn-secondary" id="btnTtsStop" type="button" disabled>■ Stoppa</button>
      <button class="btn" id="btnTtsMp3" type="button">⬇ Skapa MP3</button>
    </div>
    <p class="tts-status" id="ttsStatus" aria-live="polite">Redo.</p>
  </div>
</dialog>

<div id="toast"></div>

<script>
window.PRINT_BOOT = <?= json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>;
</script>
<script type="module" src="print-app.js"></script>
</body>
</html>
