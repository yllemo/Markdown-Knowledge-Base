<?php
// index.php - Markdown Viewer

$filename = isset($_GET['file']) ? basename($_GET['file']) : '';
$style = isset($_GET['style']) ? strtolower($_GET['style']) : 'light';

if (!$filename || !preg_match('/\.md$/i', $filename)) {
    die('Invalid file parameter. Must be a .md file.');
}

$filePath = realpath(__DIR__ . '/../content/' . $filename);
if (!$filePath || !file_exists($filePath)) {
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
// Convert Markdown checkboxes to real HTML checkboxes
$html = preg_replace_callback(
    '/<li>\s*\[([ xX])\]\s*(.*?)<\/li>/',
    function ($matches) {
        $checked = strtolower($matches[1]) === 'x' ? 'checked' : '';
        return '<li><input type="checkbox" disabled ' . $checked . '> ' . $matches[2] . '</li>';
    },
    $html
);

$title = htmlspecialchars(pathinfo($filename, PATHINFO_FILENAME));
$darkClass = $style === 'dark' ? 'dark' : 'light';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?></title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
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
        code {
            background: rgba(0,0,0,0.05);
            padding: 0.2em 0.4em;
            border-radius: 4px;
            font-family: monospace;
        }
        body.dark code { background: rgba(255,255,255,0.1); }
        pre code {
            display: block;
            padding: 1em;
            overflow-x: auto;
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
</body>
</html>
