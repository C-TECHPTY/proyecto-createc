<?php
declare(strict_types=1);

$indexHtml = __DIR__ . '/index.html';

if (!is_file($indexHtml)) {
    http_response_code(500);
    echo 'CREATEC main site is not available.';
    exit;
}

header('Content-Type: text/html; charset=UTF-8');
readfile($indexHtml);
