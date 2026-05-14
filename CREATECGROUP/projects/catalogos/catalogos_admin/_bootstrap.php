<?php
declare(strict_types=1);

/**
 * Catálogo Rodeo B2B
 * Nombre actual/provisional del sistema.
 *
 * Autor principal: Nelson Sánchez
 * Año: 2026
 *
 * Sistema desarrollado para generación de catálogos digitales,
 * gestión visual de productos, publicación web y pedidos comerciales.
 *
 * Todos los derechos reservados.
 *
 * Nota:
 * Este encabezado documenta autoría y evolución del sistema.
 * No modifica el funcionamiento del código.
 */

require dirname(__DIR__) . '/catalogos_api/bootstrap.php';

function admin_table_exists(string $tableName): bool
{
    static $cache = [];
    if (array_key_exists($tableName, $cache)) {
        return $cache[$tableName];
    }

    $statement = db()->prepare(
        'SELECT COUNT(*)
         FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
    );
    $statement->execute(['table_name' => $tableName]);
    $cache[$tableName] = ((int) $statement->fetchColumn()) > 0;
    return $cache[$tableName];
}

function admin_column_exists(string $tableName, string $columnName): bool
{
    static $cache = [];
    $key = $tableName . '.' . $columnName;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $statement = db()->prepare(
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

function admin_b2b_schema_ready(): bool
{
    return admin_table_exists('sellers')
        && admin_table_exists('catalog_share_links')
        && admin_table_exists('orders')
        && admin_column_exists('catalog_users', 'seller_id');
}

function admin_menu_items(): array
{
    $items = [
        'dashboard.php' => 'Dashboard',
        'catalogos.php' => 'Catalogos',
        'pedidos.php' => 'Pedidos',
        'exportaciones.php' => 'Exportaciones',
    ];

    if (admin_b2b_schema_ready()) {
        $items = [
            'dashboard.php' => 'Dashboard',
            'catalogos.php' => 'Catalogos',
            'pedidos.php' => 'Pedidos',
            'inteligencia.php' => 'Inteligencia',
            'sellers.php' => 'Vendedores',
            'usuarios.php' => 'Usuarios',
            'links.php' => 'Links / Enlaces',
            'clients.php' => 'Clientes',
            'configuracion.php' => 'Configuracion',
            'exportaciones.php' => 'Exportaciones',
        ];
        if (app_setting('campaigns_enabled', '1') === '1') {
            $items = array_slice($items, 0, 4, true)
                + ['campaigns/index.php' => 'Campañas']
                + array_slice($items, 4, null, true);
        }
    }

    return array_filter($items, static fn(string $file): bool => is_file(__DIR__ . '/' . $file), ARRAY_FILTER_USE_KEY);
}

function admin_post_login_target(array $user): string
{
    if (in_array((string) ($user['role'] ?? ''), ['vendor', 'seller'], true) && admin_b2b_schema_ready() && is_file(dirname(__DIR__) . '/catalogos_vendedor/index.php')) {
        return '../catalogos_vendedor/index.php';
    }

    return is_file(__DIR__ . '/dashboard.php') ? 'dashboard.php' : 'index.php';
}

function admin_authenticate(string $username, string $password): array
{
    $username = trim($username);
    if ($username === '') {
        return ['ok' => false, 'reason' => 'user', 'message' => 'Escribe tu usuario para continuar.'];
    }

    if (!admin_table_exists('catalog_users')) {
        return ['ok' => false, 'reason' => 'schema', 'message' => 'La tabla de usuarios no existe. Revisa la instalacion SQL.'];
    }

    $hasSellerId = admin_column_exists('catalog_users', 'seller_id');
    $hasSellers = admin_table_exists('sellers');
    $hasLastLogin = admin_column_exists('catalog_users', 'last_login_at');
    $sellerSelect = $hasSellerId && $hasSellers ? ', s.name AS seller_display_name' : ", '' AS seller_display_name";
    $sellerJoin = $hasSellerId && $hasSellers ? ' LEFT JOIN sellers s ON s.id = u.seller_id' : '';
    $statement = db()->prepare(
        "SELECT u.*{$sellerSelect}
         FROM catalog_users u
         {$sellerJoin}
         WHERE u.username = :username
         LIMIT 1"
    );
    $statement->execute(['username' => $username]);
    $user = $statement->fetch();

    if (!$user) {
        return ['ok' => false, 'reason' => 'user', 'message' => 'Usuario incorrecto.'];
    }

    if ((int) ($user['is_active'] ?? 0) !== 1) {
        return ['ok' => false, 'reason' => 'denied', 'message' => 'Acceso denegado. El usuario esta inactivo.'];
    }

    $hash = (string) ($user['password_hash'] ?? '');
    if ($hash === '' || !password_verify($password, $hash)) {
        return ['ok' => false, 'reason' => 'password', 'message' => 'Contrasena incorrecta.'];
    }

    if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
        db()->prepare('UPDATE catalog_users SET password_hash = :hash WHERE id = :id')->execute([
            'hash' => password_hash($password, PASSWORD_DEFAULT),
            'id' => (int) $user['id'],
        ]);
    }

    start_app_session();
    session_regenerate_id(true);
    $_SESSION['catalog_admin_user'] = [
        'id' => (int) $user['id'],
        'username' => $user['username'],
        'full_name' => $user['full_name'],
        'email' => $user['email'],
        'role' => $user['role'],
        'seller_id' => $hasSellerId && !empty($user['seller_id']) ? (int) $user['seller_id'] : null,
        'seller_display_name' => $user['seller_display_name'] ?? '',
    ];

    if ($hasLastLogin) {
        db()->prepare('UPDATE catalog_users SET last_login_at = NOW() WHERE id = :id')->execute([
            'id' => (int) $user['id'],
        ]);
    }
    audit_log('auth.login', 'catalog_users', (int) $user['id'], [
        'username' => $user['username'],
        'role' => $user['role'],
    ]);

    return ['ok' => true, 'reason' => 'ok', 'user' => $_SESSION['catalog_admin_user']];
}

function admin_state_label(string $status): string
{
    $labels = [
        'active' => 'Activo',
        'draft' => 'Borrador',
        'expired' => 'Expirado',
        'archived' => 'Archivado',
        'inactive' => 'Inactivo',
        'new' => 'Nuevo',
        'pendiente' => 'Pendiente',
        'confirmado' => 'Confirmado',
        'anulado' => 'Anulado',
        'reviewed' => 'Revisado',
        'processing' => 'En proceso',
        'invoiced' => 'Facturado',
        'completed' => 'Completado',
        'cancelled' => 'Cancelado',
        'queued' => 'En cola',
        'sent' => 'Enviado',
        'failed' => 'Fallido',
        'approved' => 'Aprobado',
        'none' => 'Sin link',
    ];

    return $labels[$status] ?? $status;
}

function admin_header(string $title, string $active = 'dashboard.php'): void
{
    start_app_session();
    $user = current_user();
    $flash = flash_get();
    $currentDir = realpath(dirname((string) ($_SERVER['SCRIPT_FILENAME'] ?? __DIR__))) ?: __DIR__;
    $adminRoot = realpath(__DIR__) ?: __DIR__;
    $relativeDir = trim(str_replace('\\', '/', substr($currentDir, strlen($adminRoot))), '/');
    $depth = $relativeDir === '' ? 0 : count(explode('/', $relativeDir));
    $adminPrefix = str_repeat('../', $depth);
    $siblingPrefix = str_repeat('../', $depth + 1);
    $companyName = app_setting('branding_company_name', (string) catalog_config('app_name', 'Catalogo Rodeo'));
    $companyLogoUrl = panel_media_url(app_setting('branding_company_logo'));
    if ($depth > 0 && $companyLogoUrl !== '' && !preg_match('#^https?://#i', $companyLogoUrl)) {
        $companyLogoUrl = $adminPrefix . $companyLogoUrl;
    }
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= html_escape($title) ?></title>
        <link rel="stylesheet" href="<?= html_escape($siblingPrefix) ?>assets/admin.css">
    </head>
    <body>
    <?php if ($user): ?>
        <div class="shell">
            <aside class="sidebar">
                <div class="brand">
                    <?php if ($companyLogoUrl !== ''): ?>
                        <img class="brand__logo" src="<?= html_escape($companyLogoUrl) ?>" alt="<?= html_escape($companyName) ?>">
                    <?php endif; ?>
                    <h1><?= html_escape($companyName) ?></h1>
                    <p>Plataforma comercial B2B con trazabilidad, links seguros y pedidos operativos.</p>
                </div>
                <nav class="nav">
                    <?php foreach (admin_menu_items() as $href => $label): ?>
                        <a class="<?= $active === $href ? 'active' : '' ?>" href="<?= html_escape($adminPrefix . $href) ?>">
                            <span><?= html_escape($label) ?></span>
                        </a>
                    <?php endforeach; ?>
                </nav>
                <div class="sidebar-footer">
                    <div><strong><?= html_escape($user['full_name'] ?: $user['username']) ?></strong></div>
                    <div><?= html_escape($user['role']) ?></div>
                    <div style="margin-top:12px;"><a href="<?= html_escape($adminPrefix) ?>logout.php" style="color:#fff;">Cerrar sesion</a></div>
                </div>
            </aside>
            <main class="main">
                <div class="topbar">
                    <div>
                        <h2><?= html_escape($title) ?></h2>
                        <p>Operacion comercial, seguridad y seguimiento centralizados.</p>
                    </div>
                    <div class="topbar__actions">
                        <span class="pill"><?= html_escape(date('Y-m-d H:i')) ?></span>
                        <a class="button" href="<?= html_escape($siblingPrefix) ?>catalogos_vendedor/index.php">Vista vendedor</a>
                    </div>
                </div>
                <?php if ($flash): ?>
                    <div class="flash <?= $flash['type'] === 'error' ? 'flash--error' : 'flash--success' ?>">
                        <?= html_escape($flash['message']) ?>
                    </div>
                <?php endif; ?>
    <?php endif; ?>
    <?php
}

function admin_footer(): void
{
    if (current_user()) {
        echo '</main></div>';
    }
    echo '</body></html>';
}

function admin_status_badge(string $status): string
{
    $map = [
        'active' => 'badge badge--ok',
        'new' => 'badge badge--warn',
        'pendiente' => 'badge badge--warn',
        'confirmado' => 'badge badge--ok',
        'anulado' => 'badge badge--danger',
        'archivado' => 'badge',
        'processing' => 'badge',
        'completed' => 'badge badge--ok',
        'cancelled' => 'badge badge--danger',
        'expired' => 'badge badge--danger',
        'inactive' => 'badge badge--danger',
    ];

    $class = $map[$status] ?? 'badge';
    return '<span class="' . $class . '">' . html_escape(admin_state_label($status)) . '</span>';
}
