<?php
declare(strict_types=1);

$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'error' => 'No existe catalogos_api/config.php. Copia config.example.php y completa tus credenciales.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$catalogConfig = require $configPath;
date_default_timezone_set($catalogConfig['timezone'] ?? 'UTC');

require_once __DIR__ . '/helpers.php';
