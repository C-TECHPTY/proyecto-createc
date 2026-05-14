<?php
declare(strict_types=1);

function createc_config(?string $key = null, mixed $default = null): mixed
{
    static $config = null;

    if ($config === null) {
        $configPath = __DIR__ . '/config.php';
        if (!is_file($configPath)) {
            http_response_code(500);
            echo 'No existe includes/config.php. Copia includes/config.example.php y completa la conexion de base de datos en el servidor.';
            exit;
        }

        $loaded = require $configPath;
        $config = is_array($loaded) ? $loaded : [];
        date_default_timezone_set((string) ($config['timezone'] ?? 'America/Panama'));
    }

    if ($key === null) {
        return $config;
    }

    $value = $config;
    foreach (explode('.', $key) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }

    return $value;
}

function createc_db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $db = createc_config('db', []);
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $db['host'] ?? 'localhost',
        (int) ($db['port'] ?? 3306),
        $db['database'] ?? '',
        $db['charset'] ?? 'utf8mb4'
    );

    $pdo = new PDO($dsn, (string) ($db['username'] ?? ''), (string) ($db['password'] ?? ''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function createc_table_exists(PDO $pdo, string $tableName): bool
{
    static $cache = [];
    $key = spl_object_id($pdo) . ':' . $tableName;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $statement = $pdo->prepare(
        'SELECT COUNT(*)
         FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
    );
    $statement->execute(['table_name' => $tableName]);
    $cache[$key] = ((int) $statement->fetchColumn()) > 0;
    return $cache[$key];
}

function createc_column_exists(PDO $pdo, string $tableName, string $columnName): bool
{
    static $cache = [];
    $key = spl_object_id($pdo) . ':' . $tableName . '.' . $columnName;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $statement = $pdo->prepare(
        'SELECT COUNT(*)
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name
           AND COLUMN_NAME = :column_name'
    );
    $statement->execute([
        'table_name' => $tableName,
        'column_name' => $columnName,
    ]);
    $cache[$key] = ((int) $statement->fetchColumn()) > 0;
    return $cache[$key];
}
