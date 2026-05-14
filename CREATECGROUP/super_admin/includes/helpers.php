<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function sa_e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function sa_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_name((string) sa_config('admin.session_name', 'createc_super_admin_session'));
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'httponly' => true,
        'secure' => $secure,
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

function sa_current_user(): ?array
{
    sa_session_start();
    $user = $_SESSION['sa_admin_user'] ?? null;
    return is_array($user) ? $user : null;
}

function sa_flash_set(string $type, string $message): void
{
    sa_session_start();
    $_SESSION['sa_flash'] = ['type' => $type, 'message' => $message];
}

function sa_flash_get(): ?array
{
    sa_session_start();
    $flash = $_SESSION['sa_flash'] ?? null;
    unset($_SESSION['sa_flash']);
    return is_array($flash) ? $flash : null;
}

function sa_csrf_token(): string
{
    sa_session_start();
    if (empty($_SESSION['sa_csrf'])) {
        $_SESSION['sa_csrf'] = bin2hex(random_bytes(24));
    }
    return (string) $_SESSION['sa_csrf'];
}

function sa_csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . sa_e(sa_csrf_token()) . '">';
}

function sa_verify_csrf(): void
{
    sa_session_start();
    $sent = (string) ($_POST['_csrf'] ?? '');
    $token = (string) ($_SESSION['sa_csrf'] ?? '');
    if ($sent === '' || $token === '' || !hash_equals($token, $sent)) {
        http_response_code(419);
        echo 'Token CSRF invalido.';
        exit;
    }
}

function sa_redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function sa_slugify(string $value): string
{
    $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    return trim($value, '-');
}

function sa_post_string(string $key, int $max = 500): string
{
    $value = trim((string) ($_POST[$key] ?? ''));
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $max);
    }
    return substr($value, 0, $max);
}

function sa_post_date_or_null(string $key): ?string
{
    $value = sa_post_string($key, 20);
    if ($value === '') {
        return null;
    }
    $timestamp = strtotime($value);
    return $timestamp === false ? null : date('Y-m-d', $timestamp);
}

function sa_post_decimal(string $key): float
{
    $raw = str_replace(',', '.', sa_post_string($key, 40));
    $raw = preg_replace('/[^0-9.-]/', '', $raw) ?? '0';
    return round((float) $raw, 2);
}

function sa_post_int(string $key): int
{
    return max(0, (int) sa_post_string($key, 20));
}

function sa_table_exists(string $tableName): bool
{
    static $cache = [];
    if (array_key_exists($tableName, $cache)) {
        return $cache[$tableName];
    }

    $statement = sa_db()->prepare(
        'SELECT COUNT(*)
         FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
    );
    $statement->execute(['table_name' => $tableName]);
    $cache[$tableName] = ((int) $statement->fetchColumn()) > 0;
    return $cache[$tableName];
}

function sa_column_exists(string $tableName, string $columnName): bool
{
    static $cache = [];
    $key = $tableName . '.' . $columnName;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $statement = sa_db()->prepare(
        'SELECT COUNT(*)
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
    );
    $statement->execute([
        'table_name' => $tableName,
        'column_name' => $columnName,
    ]);
    $cache[$key] = ((int) $statement->fetchColumn()) > 0;
    return $cache[$key];
}

function sa_company_options(): array
{
    return sa_db()
        ->query('SELECT id, company_name, slug FROM sa_companies ORDER BY company_name ASC')
        ->fetchAll();
}

function sa_plan_options(): array
{
    if (!sa_table_exists('sa_plans')) {
        return [];
    }

    return sa_db()
        ->query('SELECT id, name FROM sa_plans WHERE status = "active" ORDER BY name ASC')
        ->fetchAll();
}

function sa_primary_domain_by_company(int $companyId): ?array
{
    if (!sa_table_exists('sa_company_domains')) {
        return null;
    }

    $statement = sa_db()->prepare(
        'SELECT *
         FROM sa_company_domains
         WHERE company_id = :company_id
         ORDER BY is_primary DESC, status = "active" DESC, id DESC
         LIMIT 1'
    );
    $statement->execute(['company_id' => $companyId]);
    $row = $statement->fetch();
    return $row ?: null;
}

function sa_company_by_id(int $id): ?array
{
    $statement = sa_db()->prepare('SELECT * FROM sa_companies WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $id]);
    $row = $statement->fetch();
    return $row ?: null;
}

function sa_subscription_by_company(int $companyId): ?array
{
    $statement = sa_db()->prepare('SELECT * FROM sa_subscriptions WHERE company_id = :company_id ORDER BY id DESC LIMIT 1');
    $statement->execute(['company_id' => $companyId]);
    $row = $statement->fetch();
    return $row ?: null;
}

function sa_license_by_company(int $companyId): ?array
{
    $statement = sa_db()->prepare('SELECT * FROM sa_licenses WHERE company_id = :company_id ORDER BY id DESC LIMIT 1');
    $statement->execute(['company_id' => $companyId]);
    $row = $statement->fetch();
    return $row ?: null;
}

function sa_log(string $action, string $description = '', ?int $companyId = null): void
{
    $user = sa_current_user();
    if (!$user) {
        return;
    }

    $statement = sa_db()->prepare(
        'INSERT INTO sa_activity_logs (admin_user_id, company_id, action, description, ip_address)
         VALUES (:admin_user_id, :company_id, :action, :description, :ip_address)'
    );
    $statement->execute([
        'admin_user_id' => (int) $user['id'],
        'company_id' => $companyId,
        'action' => $action,
        'description' => $description,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
    ]);
}

function sa_status_label(string $status): string
{
    $labels = [
        'active' => 'Activo',
        'inactive' => 'Inactivo',
        'suspended' => 'Suspendido',
        'pending' => 'Pendiente',
        'expired' => 'Expirado',
        'cancelled' => 'Cancelado',
        'revoked' => 'Revocado',
    ];
    return $labels[$status] ?? $status;
}

function sa_badge(string $status): string
{
    $class = match ($status) {
        'active' => 'badge badge--ok',
        'suspended', 'expired', 'revoked', 'cancelled' => 'badge badge--danger',
        'pending' => 'badge badge--warn',
        default => 'badge',
    };
    return '<span class="' . $class . '">' . sa_e(sa_status_label($status)) . '</span>';
}
