<?php
/**
 * Shared draw.io XML → SVG helpers (index.php prerender + drawio-render.php).
 */
declare(strict_types=1);

const DRAWIO_MAX_XML_BYTES = 2_097_152;
const DRAWIO_CACHE_DIR = __DIR__ . '/cache/drawio';

function drawio_normalize_xml(string $xml): string
{
    $trimmed = trim($xml);
    if ($trimmed === '') {
        return $trimmed;
    }
    if (strpos($trimmed, '<mxfile') !== false) {
        return $trimmed;
    }
    if (strpos($trimmed, '<mxGraphModel') !== false) {
        return '<mxfile host="app.diagrams.net" agent="mdkb" version="22.1.0">'
            . '<diagram name="Page-1" id="page-1">' . $trimmed . '</diagram></mxfile>';
    }
    return $trimmed;
}

function drawio_content_hash(string $xml): string
{
    return hash('sha256', drawio_normalize_xml($xml));
}

function drawio_parse_svg(string $text): ?string
{
    $t = trim($text);
    if ($t === '') {
        return null;
    }
    if (strncmp($t, '<svg', 4) === 0) {
        return $t;
    }
    $pos = stripos($t, '<svg');
    if ($pos !== false) {
        return substr($t, $pos);
    }
    return null;
}

function drawio_encode_xmldata(string $xml): string
{
    if (!function_exists('gzdeflate')) {
        return '';
    }
    $deflated = gzdeflate($xml);
    if ($deflated === false) {
        return '';
    }
    return rawurlencode(base64_encode($deflated));
}

function drawio_cache_path_for_hash(string $hash): string
{
    return DRAWIO_CACHE_DIR . '/' . $hash . '.svg';
}

function drawio_cache_path(string $xml): string
{
    return drawio_cache_path_for_hash(drawio_content_hash($xml));
}

function drawio_ensure_cache_dir(): void
{
    if (!is_dir(DRAWIO_CACHE_DIR)) {
        @mkdir(DRAWIO_CACHE_DIR, 0755, true);
    }
}

function drawio_get_cached_svg(string $xml): ?string
{
    $path = drawio_cache_path($xml);
    if (!is_readable($path)) {
        return null;
    }
    $svg = file_get_contents($path);
    if ($svg === false) {
        return null;
    }
    return drawio_parse_svg($svg);
}

/**
 * @return array{body: string, http_code: int, error: string, len: int}
 */
function drawio_http_post(string $url, string $body): array
{
    $result = ['body' => '', 'http_code' => 0, 'error' => '', 'len' => 0];

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            $result['error'] = 'curl_init failed';
            return $result;
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: image/svg+xml,text/plain,*/*',
                'User-Agent: Mozilla/5.0 (compatible; MarkdownKnowledgeBase/1.0; +drawio-export)',
            ],
        ]);
        $response = curl_exec($ch);
        $result['http_code'] = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($response === false) {
            $result['error'] = curl_error($ch);
        } else {
            $result['body'] = (string) $response;
            $result['len'] = strlen($result['body']);
        }
        curl_close($ch);
        return $result;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n"
                . "Accept: image/svg+xml,text/plain,*/*\r\n"
                . "User-Agent: Mozilla/5.0 (compatible; MarkdownKnowledgeBase/1.0)\r\n",
            'content' => $body,
            'timeout' => 60,
            'ignore_errors' => true,
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);
    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        $result['error'] = 'file_get_contents failed';
        return $result;
    }
    $result['body'] = $response;
    $result['len'] = strlen($response);
    $result['http_code'] = 200;
    return $result;
}

function drawio_try_export_body(string $url, string $body): ?string
{
    $http = drawio_http_post($url, $body);
    if ($http['http_code'] < 200 || $http['http_code'] >= 300 || $http['len'] === 0) {
        return null;
    }
    return drawio_parse_svg($http['body']);
}

function drawio_export_svg(string $xml): ?string
{
    $trimmed = trim($xml);
    if ($trimmed === '') {
        return null;
    }

    $candidates = [
        $trimmed,
        drawio_normalize_xml($trimmed),
    ];
    $candidates = array_values(array_unique($candidates));

    $endpoints = [
        'https://convert.diagrams.net/ImageExport4/export',
        'https://convert.diagrams.net/node/export',
        'https://convert.diagrams.net/export/svg',
    ];

    foreach ($candidates as $candidate) {
        $encoded = rawurlencode($candidate);
        $xmldata = drawio_encode_xmldata($candidate);

        $bodies = [
            'format=svg&xml=' . $encoded,
            'xml=' . $encoded,
        ];
        if ($xmldata !== '') {
            $bodies[] = 'format=svg&xmldata=' . $xmldata;
            $bodies[] = 'xmldata=' . $xmldata;
        }

        foreach ($endpoints as $url) {
            foreach ($bodies as $body) {
                $svg = drawio_try_export_body($url, $body);
                if ($svg !== null) {
                    return $svg;
                }
            }
        }
    }

    return null;
}

function drawio_cache_svg(string $xml, string $svg): void
{
    drawio_ensure_cache_dir();
    @file_put_contents(drawio_cache_path($xml), $svg);
}

function drawio_get_or_export_svg(string $xml): ?string
{
    $xml = trim($xml);
    if ($xml === '' || strlen($xml) > DRAWIO_MAX_XML_BYTES) {
        return null;
    }

    $cached = drawio_get_cached_svg($xml);
    if ($cached !== null) {
        return $cached;
    }

    $svg = drawio_export_svg($xml);
    if ($svg !== null) {
        drawio_cache_svg($xml, $svg);
    }

    return $svg;
}

function drawio_svg_wrap_html(?string $svg): string
{
    if ($svg !== null && $svg !== '') {
        return '<div class="drawio-svg-wrap drawio-svg-ready" role="img" aria-label="draw.io diagram">'
            . $svg
            . '</div>';
    }

    return '<div class="drawio-svg-wrap drawio-svg-missing drawio-needs-embed" role="region" aria-label="draw.io diagram">'
        . '<p class="drawio-preview-fallback drawio-preview-loading">Laddar förhandsvisning\u2026</p>'
        . '</div>';
}

/**
 * @return array{ok: bool, svg: ?string, error: ?string}
 */
function drawio_render_result(string $xml): array
{
    $xml = trim($xml);
    if ($xml === '') {
        return ['ok' => false, 'svg' => null, 'error' => 'empty'];
    }
    if (strlen($xml) > DRAWIO_MAX_XML_BYTES) {
        return ['ok' => false, 'svg' => null, 'error' => 'too_large'];
    }

    $svg = drawio_get_or_export_svg($xml);
    if ($svg === null) {
        return ['ok' => false, 'svg' => null, 'error' => 'export_failed'];
    }

    return ['ok' => true, 'svg' => $svg, 'error' => null];
}
