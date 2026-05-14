<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/db.php';

function catalog_config(?string $key = null, mixed $default = null): mixed
{
    return createc_config($key, $default);
}

function db(): PDO
{
    return createc_db();
}

function read_json_input(): array
{
    $raw = file_get_contents('php://input');
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function catalog_table_exists(string $tableName): bool
{
    return createc_table_exists(db(), $tableName);
}

function catalog_column_exists(string $tableName, string $columnName): bool
{
    return createc_column_exists(db(), $tableName, $columnName);
}
