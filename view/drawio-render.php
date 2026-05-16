<?php
/**
 * draw.io SVG API (same-origin): POST render + cache, GET cached SVG by hash.
 */
declare(strict_types=1);

require_once __DIR__ . '/drawio-lib.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['hash'])) {
    $hash = preg_replace('/[^a-f0-9]/', '', (string) $_GET['hash']);
    if (strlen($hash) !== 64) {
        http_response_code(400);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Ogiltig hash';
        exit;
    }

    $path = drawio_cache_path_for_hash($hash);
    if (!is_readable($path)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Ej cachad';
        exit;
    }

    $svg = file_get_contents($path);
    if ($svg === false) {
        http_response_code(500);
        exit;
    }

    header('Content-Type: image/svg+xml; charset=UTF-8');
    header('Cache-Control: public, max-age=86400');
    header('X-Content-Type-Options: nosniff');
    echo $svg;
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Method not allowed';
    exit;
}

$xml = '';
$contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';

if (stripos($contentType, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);
    if (is_array($data) && isset($data['xml'])) {
        $xml = (string) $data['xml'];
        if (isset($data['svg']) && is_string($data['svg'])) {
            $clientSvg = drawio_parse_svg($data['svg']);
            if ($clientSvg !== null && trim($xml) !== '') {
                drawio_cache_svg($xml, $clientSvg);
                header('Content-Type: image/svg+xml; charset=UTF-8');
                header('Cache-Control: private, max-age=1800');
                header('X-Content-Type-Options: nosniff');
                echo $clientSvg;
                exit;
            }
        }
    }
} elseif (isset($_POST['xml'])) {
    $xml = (string) $_POST['xml'];
} else {
    $xml = (string) file_get_contents('php://input');
}

$render = drawio_render_result(trim($xml));
if (!$render['ok']) {
    http_response_code(502);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Kunde inte rendera diagram';
    exit;
}

header('Content-Type: image/svg+xml; charset=UTF-8');
header('Cache-Control: private, max-age=1800');
header('X-Content-Type-Options: nosniff');
echo $render['svg'];
