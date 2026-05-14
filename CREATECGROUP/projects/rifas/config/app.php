<?php

declare(strict_types=1);

$configFile = __DIR__ . '/config.php';
$exampleFile = __DIR__ . '/config.example.php';

$config = file_exists($configFile) ? require $configFile : require $exampleFile;

if (session_status() === PHP_SESSION_NONE) {
    session_name('rifagrid_session');
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
    session_start();
}

date_default_timezone_set('America/Panama');

function config_value(string $key, mixed $default = null): mixed
{
    global $config;
    return $config[$key] ?? $default;
}

function app_url(string $path = ''): string
{
    $base = rtrim((string) config_value('APP_URL', ''), '/');
    return $base . '/' . ltrim($path, '/');
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function verify_csrf(?string $token): bool
{
    return is_string($token) && hash_equals($_SESSION['_csrf'] ?? '', $token);
}

function money_fmt(float|int|string $amount): string
{
    return 'B/.' . number_format((float) $amount, 2);
}

function raffle_number_label(int|string $number): string
{
    $value = (int) $number;
    return $value < 100 ? str_pad((string) $value, 2, '0', STR_PAD_LEFT) : (string) $value;
}

function partial_name(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    $first = $parts[0] ?? 'Cliente';
    $last = $parts[1] ?? '';
    return trim($first . ' ' . ($last !== '' ? mb_substr($last, 0, 1) . '.' : ''));
}

function initials_name(string $name): string
{
    $parts = array_values(array_filter(preg_split('/\s+/', trim($name)) ?: []));
    if (!$parts) {
        return 'P.';
    }

    $initials = array_map(static fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)) . '.', array_slice($parts, 0, 2));
    return implode(' ', $initials);
}

function normalize_phone(string $phone): string
{
    return preg_replace('/[^\d+]/', '', $phone) ?? '';
}

function audit_log(PDO $pdo, string $action, ?string $entityType = null, ?int $entityId = null, array $meta = []): void
{
    $stmt = $pdo->prepare('INSERT INTO audit_logs (admin_id, action, entity_type, entity_id, metadata, ip_address, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
    $stmt->execute([
        $_SESSION['admin_id'] ?? null,
        $action,
        $entityType,
        $entityId,
        json_encode($meta, JSON_UNESCAPED_UNICODE),
        $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
}
