<?php
declare(strict_types=1);

function catalog_config(?string $key = null, mixed $default = null): mixed
{
    global $catalogConfig;

    if ($key === null) {
        return $catalogConfig;
    }

    $segments = explode('.', $key);
    $value = $catalogConfig;
    foreach ($segments as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }

    return $value;
}

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $db = catalog_config('db');
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $db['host'],
        (int) $db['port'],
        $db['database'],
        $db['charset'] ?? 'utf8mb4'
    );

    $pdo = new PDO($dsn, $db['username'], $db['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function html_escape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function read_json_input(): array
{
    $raw = file_get_contents('php://input') ?: '';
    if ($raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        json_response([
            'ok' => false,
            'error' => 'El cuerpo JSON no es valido.',
        ], 422);
    }

    return $decoded;
}

function require_api_key(array $payload = []): void
{
    $expected = (string) catalog_config('api_key', '');
    $headerKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
    $payloadKey = $payload['api_key'] ?? '';
    $provided = (string) ($headerKey ?: $payloadKey);

    if ($expected === '' || !hash_equals($expected, $provided)) {
        json_response([
            'ok' => false,
            'error' => 'API key invalida.',
        ], 401);
    }
}

function start_app_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_name((string) catalog_config('admin.session_name', 'catalog_admin_session'));
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'httponly' => true,
        'secure' => $secure,
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

function current_user(): ?array
{
    start_app_session();
    $user = $_SESSION['catalog_admin_user'] ?? null;
    return is_array($user) ? $user : null;
}

function refresh_current_user_from_db(): ?array
{
    $user = current_user();
    $userId = (int) ($user['id'] ?? 0);
    if ($userId <= 0 || !catalog_table_exists('catalog_users')) {
        return $user;
    }

    $hasSellerId = catalog_column_exists('catalog_users', 'seller_id');
    $hasSellers = catalog_table_exists('sellers');
    $sellerSelect = $hasSellerId && $hasSellers ? ', s.name AS seller_display_name' : ", '' AS seller_display_name";
    $sellerJoin = $hasSellerId && $hasSellers ? ' LEFT JOIN sellers s ON s.id = u.seller_id' : '';
    $statement = db()->prepare(
        "SELECT u.*{$sellerSelect}
         FROM catalog_users u
         {$sellerJoin}
         WHERE u.id = :id AND u.is_active = 1
         LIMIT 1"
    );
    $statement->execute(['id' => $userId]);
    $row = $statement->fetch();
    if (!$row) {
        return $user;
    }

    $_SESSION['catalog_admin_user'] = [
        'id' => (int) $row['id'],
        'username' => $row['username'],
        'full_name' => $row['full_name'],
        'email' => $row['email'],
        'role' => $row['role'],
        'seller_id' => $hasSellerId && !empty($row['seller_id']) ? (int) $row['seller_id'] : null,
        'seller_display_name' => $row['seller_display_name'] ?? '',
    ];

    return $_SESSION['catalog_admin_user'];
}

function user_has_role(array $allowedRoles): bool
{
    $user = current_user();
    if (!$user) {
        return false;
    }

    return in_array((string) ($user['role'] ?? ''), $allowedRoles, true);
}

function require_login(array $allowedRoles = []): void
{
    $user = current_user();
    if (!$user) {
        header('Location: login.php');
        exit;
    }

    if ($allowedRoles && !in_array((string) ($user['role'] ?? ''), $allowedRoles, true)) {
        http_response_code(403);
        echo 'No tienes permisos para acceder a esta seccion.';
        exit;
    }
}

function admin_require_login(array $allowedRoles = []): void
{
    $roles = $allowedRoles ?: ['admin', 'sales', 'billing', 'operator'];
    require_login($roles);
}

function vendor_require_login(): void
{
    $user = refresh_current_user_from_db();
    if (!$user) {
        header('Location: ../catalogos_admin/login.php');
        exit;
    }

    $role = strtolower(trim((string) ($user['role'] ?? '')));
    $sellerId = (int) ($user['seller_id'] ?? 0);
    if (!in_array($role, ['vendor', 'seller', 'vendedor', 'admin', 'sales'], true) && $sellerId <= 0) {
        http_response_code(403);
        echo 'No tienes permisos para acceder a esta seccion.';
        exit;
    }
}

function catalog_table_exists(string $tableName): bool
{
    $statement = db()->prepare(
        'SELECT COUNT(*)
         FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
    );
    $statement->execute(['table_name' => $tableName]);
    return ((int) $statement->fetchColumn()) > 0;
}

function catalog_column_exists(string $tableName, string $columnName): bool
{
    $statement = db()->prepare(
        'SELECT COUNT(*)
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
    );
    $statement->execute([
        'table_name' => $tableName,
        'column_name' => $columnName,
    ]);
    return ((int) $statement->fetchColumn()) > 0;
}

function admin_login(string $username, string $password): bool
{
    $tableExists = static function (string $tableName): bool {
        $statement = db()->prepare(
            'SELECT COUNT(*)
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
        );
        $statement->execute(['table_name' => $tableName]);
        return ((int) $statement->fetchColumn()) > 0;
    };
    $columnExists = static function (string $tableName, string $columnName): bool {
        $statement = db()->prepare(
            'SELECT COUNT(*)
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
        );
        $statement->execute([
            'table_name' => $tableName,
            'column_name' => $columnName,
        ]);
        return ((int) $statement->fetchColumn()) > 0;
    };

    $hasSellerId = $columnExists('catalog_users', 'seller_id');
    $hasSellers = $tableExists('sellers');
    $hasLastLogin = $columnExists('catalog_users', 'last_login_at');
    $sellerSelect = $hasSellerId && $hasSellers ? ', s.name AS seller_display_name' : ", '' AS seller_display_name";
    $sellerJoin = $hasSellerId && $hasSellers ? ' LEFT JOIN sellers s ON s.id = u.seller_id' : '';
    $sql = "SELECT u.*{$sellerSelect}
            FROM catalog_users u
            {$sellerJoin}
            WHERE u.username = :username AND u.is_active = 1
            LIMIT 1";
    $statement = db()->prepare($sql);
    $statement->execute(['username' => $username]);
    $user = $statement->fetch();

    if (!$user || !password_verify($password, (string) $user['password_hash'])) {
        return false;
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
            'id' => $user['id'],
        ]);
    }

    audit_log('auth.login', 'catalog_users', (int) $user['id'], [
        'username' => $user['username'],
        'role' => $user['role'],
    ]);

    return true;
}

function logout_user(): void
{
    $user = current_user();
    if ($user) {
        audit_log('auth.logout', 'catalog_users', (int) $user['id'], [
            'username' => $user['username'],
        ]);
    }

    start_app_session();
    $_SESSION = [];
    session_destroy();
}

function csrf_token(): string
{
    start_app_session();
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(24));
    }

    return (string) $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . html_escape(csrf_token()) . '">';
}

function verify_csrf_or_abort(): void
{
    start_app_session();
    $sent = (string) ($_POST['_csrf'] ?? '');
    $token = (string) ($_SESSION['_csrf'] ?? '');

    if ($sent === '' || $token === '' || !hash_equals($token, $sent)) {
        http_response_code(419);
        echo 'Token CSRF invalido.';
        exit;
    }
}

function flash_set(string $type, string $message): void
{
    start_app_session();
    $_SESSION['_flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function flash_get(): ?array
{
    start_app_session();
    $flash = $_SESSION['_flash'] ?? null;
    unset($_SESSION['_flash']);
    return is_array($flash) ? $flash : null;
}

function slugify(string $value): string
{
    $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    return trim($value, '-') ?: 'catalogo-publicable';
}

function parse_decimal(mixed $value): float
{
    if ($value === null || $value === '') {
        return 0.0;
    }

    if (is_numeric($value)) {
        return (float) $value;
    }

    $normalized = preg_replace('/[^0-9.,-]/', '', (string) $value) ?? '0';
    $normalized = str_replace(',', '.', $normalized);
    return (float) $normalized;
}

function parse_datetime_or_null(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return null;
    }

    return date('Y-m-d H:i:s', $timestamp);
}

function next_order_number(): string
{
    return 'PED-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

function generate_secure_token(): string
{
    return bin2hex(random_bytes(32));
}

function fetch_catalog_by_slug(string $slug): ?array
{
    $sql = 'SELECT c.*,
                   s.name AS seller_display_name,
                   cl.business_name AS client_business_name,
                   cl.contact_name AS client_contact_name
            FROM catalogs c
            LEFT JOIN sellers s ON s.id = c.seller_id
            LEFT JOIN clients cl ON cl.id = c.client_id
            WHERE c.slug = :slug
            LIMIT 1';
    $statement = db()->prepare($sql);
    $statement->execute(['slug' => $slug]);
    $catalog = $statement->fetch();

    return $catalog ?: null;
}

function resolve_catalog_status(array $catalog): string
{
    $status = (string) ($catalog['status'] ?? 'active');
    if ($status !== 'active') {
        return $status;
    }

    if (!empty($catalog['expires_at']) && strtotime((string) $catalog['expires_at']) < time()) {
        return 'expired';
    }

    return 'active';
}

function ensure_catalog_active(string $slug): array
{
    $catalog = fetch_catalog_by_slug($slug);
    if (!$catalog) {
        json_response([
            'ok' => false,
            'error' => 'Catalogo no encontrado.',
        ], 404);
    }

    $status = resolve_catalog_status($catalog);
    if ($status !== 'active') {
        json_response([
            'ok' => false,
            'error' => 'Catalogo no disponible.',
            'catalog' => [
                'slug' => $catalog['slug'],
                'status' => $status,
                'expires_at' => $catalog['expires_at'],
            ],
        ], 410);
    }

    return $catalog;
}

function fetch_share_link_by_token(string $token): ?array
{
    if ($token === '') {
        return null;
    }

    $sql = 'SELECT l.*,
                   c.slug AS catalog_slug,
                   c.title AS catalog_title,
                   s.name AS seller_name,
                   cl.business_name AS client_name
            FROM catalog_share_links l
            INNER JOIN catalogs c ON c.id = l.catalog_id
            LEFT JOIN sellers s ON s.id = l.seller_id
            LEFT JOIN clients cl ON cl.id = l.client_id
            WHERE l.token = :token
            LIMIT 1';
    $statement = db()->prepare($sql);
    $statement->execute(['token' => $token]);
    $link = $statement->fetch();

    return $link ?: null;
}

function fetch_seller_by_public_token(string $token): ?array
{
    if ($token === '' || !catalog_table_exists('sellers') || !catalog_column_exists('sellers', 'public_token')) {
        return null;
    }

    $statement = db()->prepare(
        'SELECT *
         FROM sellers
         WHERE public_token = :token AND is_active = 1
         LIMIT 1'
    );
    $statement->execute(['token' => $token]);
    $seller = $statement->fetch();

    return $seller ?: null;
}

function fetch_seller_token_by_id(?int $sellerId): string
{
    if (!$sellerId || !catalog_table_exists('sellers') || !catalog_column_exists('sellers', 'public_token')) {
        return '';
    }

    $statement = db()->prepare('SELECT public_token FROM sellers WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $sellerId]);
    return (string) ($statement->fetchColumn() ?: '');
}

function resolve_share_link_status(?array $link): string
{
    if (!$link) {
        return 'none';
    }

    if ((int) ($link['is_active'] ?? 0) !== 1) {
        return 'inactive';
    }

    if (!empty($link['expires_at']) && strtotime((string) $link['expires_at']) < time()) {
        return 'expired';
    }

    return 'active';
}

function resolve_public_catalog_context(string $slug, string $token = '', string $sellerToken = ''): array
{
    $catalog = ensure_catalog_active($slug);
    $link = null;
    $sellerByToken = null;

    if ($token !== '') {
        $link = fetch_share_link_by_token($token);
        if (!$link || (int) $link['catalog_id'] !== (int) $catalog['id']) {
            json_response([
                'ok' => false,
                'error' => 'El enlace compartido no es valido para este catalogo.',
                'share_link' => [
                    'status' => 'invalid',
                ],
            ], 404);
        }

        $linkStatus = resolve_share_link_status($link);
        if ($linkStatus !== 'active') {
            json_response([
                'ok' => false,
                'error' => 'El enlace compartido no esta disponible.',
                'share_link' => [
                    'status' => $linkStatus,
                    'expires_at' => $link['expires_at'],
                ],
            ], 410);
        }
    }

    if (!$link && $sellerToken !== '') {
        $sellerByToken = fetch_seller_by_public_token($sellerToken);
    }

    $sellerId = $link['seller_id'] ?? $sellerByToken['id'] ?? $catalog['seller_id'] ?? null;
    $sellerName = $link['seller_name'] ?? $sellerByToken['name'] ?? $catalog['seller_display_name'] ?? $catalog['seller_name'] ?? '';
    $resolvedSellerToken = $sellerByToken['public_token'] ?? '';
    if ($resolvedSellerToken === '' && $sellerId) {
        $resolvedSellerToken = fetch_seller_token_by_id((int) $sellerId);
    }

    return [
        'catalog' => $catalog,
        'share_link' => $link,
        'seller_id' => $sellerId,
        'seller_token' => $resolvedSellerToken,
        'client_id' => $link['client_id'] ?? $catalog['client_id'] ?? null,
        'seller_name' => $sellerName,
        'client_name' => $link['client_name'] ?? $catalog['client_business_name'] ?? $catalog['client_name'] ?? '',
    ];
}

function record_catalog_access(array $context): void
{
    $catalog = $context['catalog'];
    $link = $context['share_link'] ?? null;

    $statement = db()->prepare(
        'INSERT INTO catalog_access_logs (catalog_id, share_link_id, seller_id, client_id, ip_address, user_agent, referrer, utm_source, utm_medium)
         VALUES (:catalog_id, :share_link_id, :seller_id, :client_id, :ip_address, :user_agent, :referrer, :utm_source, :utm_medium)'
    );
    $statement->execute([
        'catalog_id' => $catalog['id'],
        'share_link_id' => $link['id'] ?? null,
        'seller_id' => $context['seller_id'] ?: null,
        'client_id' => $context['client_id'] ?: null,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'referrer' => $_SERVER['HTTP_REFERER'] ?? '',
        'utm_source' => $_GET['utm_source'] ?? '',
        'utm_medium' => $_GET['utm_medium'] ?? '',
    ]);

    if ($link) {
        db()->prepare(
            'UPDATE catalog_share_links
             SET open_count = open_count + 1, last_opened_at = NOW(), updated_at = NOW()
             WHERE id = :id'
        )->execute(['id' => $link['id']]);
    }

    audit_log('catalog.link_opened', 'catalogs', (int) $catalog['id'], [
        'slug' => $catalog['slug'],
        'share_link_id' => $link['id'] ?? null,
    ]);
}

function catalog_json_data(string $relativeJsonPath): array
{
    $relativeJsonPath = trim($relativeJsonPath);
    if ($relativeJsonPath === '') {
        return [];
    }

    $baseDir = dirname(__DIR__);
    $fullPath = $baseDir . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativeJsonPath);
    if (!is_file($fullPath)) {
        return [];
    }

    $raw = file_get_contents($fullPath);
    if ($raw === false || $raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function build_public_catalog_payload(array $context): array
{
    $catalog = $context['catalog'];
    $json = catalog_json_data((string) ($catalog['catalog_json_path'] ?? ''));
    $apiPayload = json_decode((string) ($catalog['api_payload'] ?? ''), true);
    if (!is_array($apiPayload)) {
        $apiPayload = [];
    }
    if (empty($json['theme']) && !empty($apiPayload['theme']) && is_array($apiPayload['theme'])) {
        $json['theme'] = $apiPayload['theme'];
    }

    $promotion = [
        'title' => (string) ($catalog['promo_title'] ?? ''),
        'text' => (string) ($catalog['promo_text'] ?? ''),
        'image_url' => (string) ($catalog['promo_image_url'] ?? ''),
        'video_url' => (string) ($catalog['promo_video_url'] ?? ''),
        'link_url' => (string) ($catalog['promo_link_url'] ?? ''),
        'link_label' => (string) ($catalog['promo_link_label'] ?? ''),
    ];

    return [
        'id' => (int) $catalog['id'],
        'slug' => $catalog['slug'],
        'title' => $catalog['title'],
        'status' => resolve_catalog_status($catalog),
        'public_url' => $catalog['public_url'],
        'pdf_url' => $catalog['pdf_url'],
        'expires_at' => $catalog['expires_at'],
        'seller' => [
            'id' => $context['seller_id'] ? (int) $context['seller_id'] : null,
            'name' => $context['seller_name'],
            'token' => (string) ($context['seller_token'] ?? ''),
        ],
        'client' => [
            'id' => $context['client_id'] ? (int) $context['client_id'] : null,
            'name' => $context['client_name'],
        ],
        'share_link' => !empty($context['share_link']) ? [
            'id' => (int) $context['share_link']['id'],
            'label' => $context['share_link']['label'],
            'expires_at' => $context['share_link']['expires_at'],
        ] : null,
        'promotion' => $promotion,
        'legacy_pdf_url' => (string) ($catalog['legacy_pdf_url'] ?? ''),
        'modern_pdf_url' => (string) ($catalog['modern_pdf_url'] ?? ''),
        'metadata' => $json,
    ];
}

function audit_log(string $action, string $entityType = '', ?int $entityId = null, array $context = []): void
{
    try {
        $user = current_user();
        db()->prepare(
            'INSERT INTO activity_logs (user_id, action, entity_type, entity_id, context_json, ip_address)
             VALUES (:user_id, :action, :entity_type, :entity_id, :context_json, :ip_address)'
        )->execute([
            'user_id' => $user['id'] ?? null,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'context_json' => $context ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
        ]);
    } catch (Throwable) {
    }
}

function app_setting(string $key, string $default = ''): string
{
    static $cache = [];
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $statement = db()->prepare('SELECT setting_value FROM app_settings WHERE setting_key = :key LIMIT 1');
    $statement->execute(['key' => $key]);
    $row = $statement->fetch();
    $cache[$key] = $row['setting_value'] ?? $default;
    return (string) $cache[$key];
}

function update_app_settings(array $settings): void
{
    $statement = db()->prepare(
        'INSERT INTO app_settings (setting_key, setting_value)
         VALUES (:setting_key, :setting_value)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()'
    );

    foreach ($settings as $key => $value) {
        $statement->execute([
            'setting_key' => $key,
            'setting_value' => (string) $value,
        ]);
    }
}

function panel_uploads_root(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads';
}

function normalize_panel_upload_dir(string $relativeDir): string
{
    $relativeDir = trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativeDir), DIRECTORY_SEPARATOR);
    $uploadsPrefix = 'uploads' . DIRECTORY_SEPARATOR;
    if (str_starts_with($relativeDir, $uploadsPrefix)) {
        $relativeDir = substr($relativeDir, strlen($uploadsPrefix));
    }
    if ($relativeDir === 'uploads') {
        return '';
    }
    return $relativeDir;
}

function ensure_panel_upload_dir(string $relativeDir): string
{
    $relativeDir = normalize_panel_upload_dir($relativeDir);
    $fullPath = panel_uploads_root();
    if ($relativeDir !== '') {
        $fullPath .= DIRECTORY_SEPARATOR . $relativeDir;
    }

    if (!is_dir($fullPath)) {
        mkdir($fullPath, 0775, true);
    }

    return $fullPath;
}

function panel_media_url(?string $relativePath): string
{
    $relativePath = trim((string) $relativePath);
    if ($relativePath === '') {
        return '';
    }

    $normalizedPath = ltrim(str_replace('\\', '/', $relativePath), '/');
    $defaultFullPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalizedPath);
    if (is_file($defaultFullPath)) {
        return '../' . $normalizedPath;
    }

    if (str_starts_with($normalizedPath, 'uploads/')) {
        $legacyPath = 'uploads/' . $normalizedPath;
        $legacyFullPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $legacyPath);
        if (is_file($legacyFullPath)) {
            return '../' . $legacyPath;
        }
    }

    return '../' . $normalizedPath;
}

function delete_panel_file(?string $relativePath): void
{
    $relativePath = trim((string) $relativePath);
    if ($relativePath === '') {
        return;
    }

    $candidatePaths = [
        dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath),
    ];
    if (str_starts_with(str_replace('\\', '/', $relativePath), 'uploads/')) {
        $candidatePaths[] = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
    }

    foreach ($candidatePaths as $fullPath) {
        if (is_file($fullPath)) {
            @unlink($fullPath);
            break;
        }
    }
}

function save_uploaded_image(string $fieldName, string $relativeDir, string $filePrefix = 'image'): ?string
{
    if (empty($_FILES[$fieldName]) || !is_array($_FILES[$fieldName])) {
        return null;
    }

    $file = $_FILES[$fieldName];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('No se pudo subir la imagen seleccionada.');
    }

    if (!is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
        throw new RuntimeException('La carga de imagen no es valida.');
    }

    $mime = mime_content_type((string) $file['tmp_name']) ?: '';
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Solo se permiten imagenes JPG, PNG, WEBP o GIF.');
    }

    $normalizedDir = normalize_panel_upload_dir($relativeDir);
    $targetDir = ensure_panel_upload_dir($normalizedDir);
    $filename = slugify($filePrefix) . '-' . date('YmdHis') . '-' . substr(bin2hex(random_bytes(6)), 0, 8) . '.' . $allowed[$mime];
    $targetPath = $targetDir . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file((string) $file['tmp_name'], $targetPath)) {
        throw new RuntimeException('No se pudo guardar la imagen en el servidor.');
    }

    $relativeDir = trim(str_replace('\\', '/', $normalizedDir), '/');
    if ($relativeDir !== '') {
        $relativeDir = 'uploads/' . $relativeDir;
    } else {
        $relativeDir = 'uploads';
    }
    return ($relativeDir !== '' ? $relativeDir . '/' : '') . $filename;
}

function send_notification_mail(string $subject, string $message, array $recipients = [], ?int $orderId = null, array $attachments = [], string $htmlMessage = ''): string
{
    $finalRecipients = array_values(array_unique(array_filter(array_map('trim', $recipients))));
    if (!$finalRecipients) {
        return 'failed';
    }

    $fromName = (string) catalog_config('mail.from_name', 'Catalog Platform');
    $fromEmail = (string) catalog_config('mail.from_email', 'no-reply@example.com');
    $boundary = 'catalog-mail-' . bin2hex(random_bytes(12));
    $alternativeBoundary = 'catalog-alt-' . bin2hex(random_bytes(12));
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: multipart/mixed; boundary="' . $boundary . '"',
        'From: ' . sprintf('%s <%s>', $fromName, $fromEmail),
        'Reply-To: ' . sprintf('%s <%s>', $fromName, $fromEmail),
        'Return-Path: ' . $fromEmail,
    ];
    $mailParams = filter_var($fromEmail, FILTER_VALIDATE_EMAIL) ? '-f' . escapeshellarg($fromEmail) : '';
    $smtpEnabled = (bool) catalog_config('mail.smtp.enabled', false);

    $body = [];
    $body[] = '--' . $boundary;
    if ($htmlMessage !== '') {
        $body[] = 'Content-Type: multipart/alternative; boundary="' . $alternativeBoundary . '"';
        $body[] = '';
        $body[] = '--' . $alternativeBoundary;
        $body[] = 'Content-Type: text/plain; charset=UTF-8';
        $body[] = 'Content-Transfer-Encoding: 8bit';
        $body[] = '';
        $body[] = $message;
        $body[] = '';
        $body[] = '--' . $alternativeBoundary;
        $body[] = 'Content-Type: text/html; charset=UTF-8';
        $body[] = 'Content-Transfer-Encoding: 8bit';
        $body[] = '';
        $body[] = $htmlMessage;
        $body[] = '';
        $body[] = '--' . $alternativeBoundary . '--';
        $body[] = '';
    } else {
        $body[] = 'Content-Type: text/plain; charset=UTF-8';
        $body[] = 'Content-Transfer-Encoding: 8bit';
        $body[] = '';
        $body[] = $message;
        $body[] = '';
    }

    $attachmentMeta = [];
    foreach ($attachments as $attachment) {
        $filePath = trim((string) ($attachment['path'] ?? ''));
        $name = trim((string) ($attachment['name'] ?? basename($filePath)));
        if ($filePath === '' || !is_file($filePath) || $name === '') {
            continue;
        }

        $mime = (string) ($attachment['mime'] ?? 'application/octet-stream');
        $encoded = chunk_split(base64_encode((string) file_get_contents($filePath)));
        $body[] = '--' . $boundary;
        $body[] = 'Content-Type: ' . $mime . '; name="' . addslashes($name) . '"';
        $body[] = 'Content-Transfer-Encoding: base64';
        $body[] = 'Content-Disposition: attachment; filename="' . addslashes($name) . '"';
        $body[] = '';
        $body[] = $encoded;
        $body[] = '';
        $attachmentMeta[] = [
            'name' => $name,
            'path' => $filePath,
            'mime' => $mime,
        ];
    }
    $body[] = '--' . $boundary . '--';
    $mailBody = implode("\r\n", $body);

    $sentCount = 0;
    $failedCount = 0;
    foreach ($finalRecipients as $recipient) {
        if ($smtpEnabled) {
            $sendResult = smtp_send_mail($recipient, $subject, $headers, $mailBody, $fromEmail);
            $sent = $sendResult['ok'];
            $responseMessage = $sendResult['message'];
        } else {
            $sent = $mailParams !== ''
                ? @mail($recipient, $subject, $mailBody, implode("\r\n", $headers), $mailParams)
                : @mail($recipient, $subject, $mailBody, implode("\r\n", $headers));
            $responseMessage = $sent
                ? 'mail() OK; envelope sender: ' . ($fromEmail ?: 'no definido')
                : 'mail() devolvio false; envelope sender: ' . ($fromEmail ?: 'no definido');
        }
        $status = $sent ? 'sent' : 'failed';
        if ($status === 'sent') {
            $sentCount++;
        } else {
            $failedCount++;
        }
        db()->prepare(
            'INSERT INTO notifications_log (order_id, channel, recipient, subject, payload, attachments_json, status, response_message)
             VALUES (:order_id, :channel, :recipient, :subject, :payload, :attachments_json, :status, :response_message)'
        )->execute([
            'order_id' => $orderId,
            'channel' => 'email',
            'recipient' => $recipient,
            'subject' => $subject,
            'payload' => $message,
            'attachments_json' => $attachmentMeta ? json_encode($attachmentMeta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'status' => $status,
            'response_message' => $responseMessage,
        ]);
    }

    return $sentCount > 0 && $failedCount === 0 ? 'sent' : ($sentCount > 0 ? 'sent' : 'failed');
}

function smtp_send_mail(string $recipient, string $subject, array $headers, string $body, string $fromEmail): array
{
    $host = trim((string) catalog_config('mail.smtp.host', ''));
    $port = (int) catalog_config('mail.smtp.port', 465);
    $encryption = strtolower(trim((string) catalog_config('mail.smtp.encryption', 'ssl')));
    $username = trim((string) catalog_config('mail.smtp.username', ''));
    $password = (string) catalog_config('mail.smtp.password', '');
    $timeout = max(5, (int) catalog_config('mail.smtp.timeout', 20));

    if ($host === '' || $username === '' || $password === '' || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => 'SMTP incompleto en config.php'];
    }

    $remote = $encryption === 'ssl' ? 'ssl://' . $host : $host;
    $errno = 0;
    $errstr = '';
    $socket = @fsockopen($remote, $port, $errno, $errstr, $timeout);
    if (!$socket) {
        return ['ok' => false, 'message' => 'SMTP conexion fallida: ' . trim($errstr ?: (string) $errno)];
    }

    stream_set_timeout($socket, $timeout);
    $lastResponse = '';

    $read = static function () use ($socket, &$lastResponse): string {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }
        $lastResponse = trim($response);
        return $lastResponse;
    };

    $write = static function (string $command) use ($socket): void {
        fwrite($socket, $command . "\r\n");
    };

    $expect = static function (array $codes, string $step) use ($read): ?string {
        $response = $read();
        $code = substr($response, 0, 3);
        return in_array($code, $codes, true) ? null : 'SMTP ' . $step . ' fallo: ' . $response;
    };

    $error = $expect(['220'], 'banner');
    if ($error) {
        fclose($socket);
        return ['ok' => false, 'message' => $error];
    }

    $serverName = $_SERVER['SERVER_NAME'] ?? 'localhost';
    $write('EHLO ' . $serverName);
    $error = $expect(['250'], 'EHLO');
    if ($error) {
        fclose($socket);
        return ['ok' => false, 'message' => $error];
    }

    if ($encryption === 'tls') {
        $write('STARTTLS');
        $error = $expect(['220'], 'STARTTLS');
        if ($error) {
            fclose($socket);
            return ['ok' => false, 'message' => $error];
        }
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($socket);
            return ['ok' => false, 'message' => 'SMTP STARTTLS no pudo activar cifrado'];
        }
        $write('EHLO ' . $serverName);
        $error = $expect(['250'], 'EHLO TLS');
        if ($error) {
            fclose($socket);
            return ['ok' => false, 'message' => $error];
        }
    }

    $write('AUTH LOGIN');
    $error = $expect(['334'], 'AUTH');
    if ($error) {
        fclose($socket);
        return ['ok' => false, 'message' => $error];
    }
    $write(base64_encode($username));
    $error = $expect(['334'], 'usuario SMTP');
    if ($error) {
        fclose($socket);
        return ['ok' => false, 'message' => $error];
    }
    $write(base64_encode($password));
    $error = $expect(['235'], 'clave SMTP');
    if ($error) {
        fclose($socket);
        return ['ok' => false, 'message' => $error];
    }

    $write('MAIL FROM:<' . $fromEmail . '>');
    $error = $expect(['250'], 'MAIL FROM');
    if ($error) {
        fclose($socket);
        return ['ok' => false, 'message' => $error];
    }
    $write('RCPT TO:<' . $recipient . '>');
    $error = $expect(['250', '251'], 'RCPT TO');
    if ($error) {
        fclose($socket);
        return ['ok' => false, 'message' => $error];
    }
    $write('DATA');
    $error = $expect(['354'], 'DATA');
    if ($error) {
        fclose($socket);
        return ['ok' => false, 'message' => $error];
    }

    $messageHeaders = array_merge($headers, [
        'To: <' . $recipient . '>',
        'Subject: ' . $subject,
        'Date: ' . date(DATE_RFC2822),
    ]);
    $message = implode("\r\n", $messageHeaders) . "\r\n\r\n" . $body;
    $message = preg_replace('/^\./m', '..', $message);
    fwrite($socket, $message . "\r\n.\r\n");
    $error = $expect(['250'], 'envio');
    if ($error) {
        fclose($socket);
        return ['ok' => false, 'message' => $error];
    }

    $write('QUIT');
    fclose($socket);

    return ['ok' => true, 'message' => 'SMTP OK: ' . $lastResponse];
}

function build_notification_recipients(array $order, ?array $seller = null): array
{
    $recipients = [];
    $adminEmails = trim(app_setting('order_admin_emails', ''));
    if ($adminEmails !== '') {
        $recipients = array_merge($recipients, preg_split('/[,;]+/', $adminEmails) ?: []);
    } else {
        foreach (['mail_sales', 'mail_billing', 'mail_logistics', 'mail_supervision'] as $key) {
            $recipients = array_merge($recipients, preg_split('/[,;]+/', app_setting($key, '')) ?: []);
        }
    }

    if ($seller && !empty($seller['email'])) {
        $recipients[] = $seller['email'];
    }

    if (!empty($order['contact_email'])) {
        $recipients[] = $order['contact_email'];
    }

    return array_values(array_unique(array_filter(array_map('trim', $recipients))));
}

function fetch_seller(?int $sellerId): ?array
{
    if (!$sellerId) {
        return null;
    }
    $statement = db()->prepare('SELECT * FROM sellers WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $sellerId]);
    $row = $statement->fetch();
    return $row ?: null;
}

function format_money(float $amount, string $currency = 'USD'): string
{
    return sprintf('%s %s', $currency, number_format($amount, 2, '.', ','));
}

function sales_contact_info(): array
{
    return [
        'name' => (string) catalog_config('sales_contact.name', 'Ventas'),
        'email' => (string) catalog_config('sales_contact.email', 'ventas@rodeoimportzl.com'),
        'phone' => (string) catalog_config('sales_contact.phone', '4418710'),
    ];
}

function build_company_signature(): string
{
    return implode("\n", [
        'Saludos,',
        'Nelson Sánchez Dillon',
        'Dept. de Diseño y Fotografía – Rodeo Import, S.A.',
        '',
        '"Este correo electrónico está destinado exclusivamente a su destinatario original. Si usted ha recibido este mensaje por error, por favor notifíquelo al remitente y elimine este mensaje de inmediato. Gracias."',
    ]);
}

function create_share_link(int $catalogId, ?int $sellerId, ?int $clientId, string $label, ?string $expiresAt, string $notes = ''): array
{
    $token = generate_secure_token();
    db()->prepare(
        'INSERT INTO catalog_share_links (catalog_id, seller_id, client_id, token, label, notes, expires_at, created_by_user_id)
         VALUES (:catalog_id, :seller_id, :client_id, :token, :label, :notes, :expires_at, :created_by_user_id)'
    )->execute([
        'catalog_id' => $catalogId,
        'seller_id' => $sellerId,
        'client_id' => $clientId,
        'token' => $token,
        'label' => $label,
        'notes' => $notes,
        'expires_at' => $expiresAt,
        'created_by_user_id' => current_user()['id'] ?? null,
    ]);

    $id = (int) db()->lastInsertId();
    audit_log('catalog.share_link_created', 'catalog_share_links', $id, [
        'catalog_id' => $catalogId,
        'seller_id' => $sellerId,
        'client_id' => $clientId,
    ]);

    return [
        'id' => $id,
        'token' => $token,
    ];
}

function update_order_status(int $orderId, string $nextStatus, string $notes = ''): void
{
    if (!catalog_column_exists('orders', 'status')) {
        return;
    }

    $statement = db()->prepare('SELECT status FROM orders WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $orderId]);
    $row = $statement->fetch();
    if (!$row) {
        return;
    }

    $previous = (string) $row['status'];
    if ($previous === $nextStatus) {
        return;
    }

    db()->prepare('UPDATE orders SET status = :status, updated_at = NOW() WHERE id = :id')->execute([
        'status' => $nextStatus,
        'id' => $orderId,
    ]);

    db()->prepare(
        'INSERT INTO order_status_history (order_id, from_status, to_status, changed_by_user_id, notes)
         VALUES (:order_id, :from_status, :to_status, :changed_by_user_id, :notes)'
    )->execute([
        'order_id' => $orderId,
        'from_status' => $previous,
        'to_status' => $nextStatus,
        'changed_by_user_id' => current_user()['id'] ?? null,
        'notes' => $notes,
    ]);

    audit_log('order.status_changed', 'orders', $orderId, [
        'from' => $previous,
        'to' => $nextStatus,
    ]);
}

function order_exports_base_dir(): string
{
    $configured = trim((string) catalog_config('paths.order_exports_dir', ''));
    if ($configured !== '') {
        return rtrim($configured, DIRECTORY_SEPARATOR);
    }

    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'order_exports';
}

function ensure_directory(string $dirPath): void
{
    if (is_dir($dirPath)) {
        return;
    }

    if (!mkdir($dirPath, 0775, true) && !is_dir($dirPath)) {
        throw new RuntimeException('No se pudo crear el directorio: ' . $dirPath);
    }
}

function generate_order_export_files(array $order, array $items): array
{
    $baseDir = order_exports_base_dir();
    $dayDir = $baseDir . DIRECTORY_SEPARATOR . date('Ymd');
    ensure_directory($dayDir);

    $safeOrderNumber = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) ($order['order_number'] ?? 'pedido')) ?: 'pedido';
    $csvPath = $dayDir . DIRECTORY_SEPARATOR . 'pedido-' . $safeOrderNumber . '.csv';
    $xlsxPath = $dayDir . DIRECTORY_SEPARATOR . 'pedido-' . $safeOrderNumber . '.xlsx';

    file_put_contents($csvPath, build_order_csv_string($order, $items));

    $xlsxOk = false;
    if (class_exists('ZipArchive')) {
        $xlsxOk = build_order_xlsx_file($order, $items, $xlsxPath);
    }

    return [
        'csv_path' => $csvPath,
        'xlsx_path' => $xlsxOk ? $xlsxPath : '',
        'generated_at' => date('Y-m-d H:i:s'),
    ];
}

function build_order_csv_string(array $order, array $rows): string
{
    $rows = hydrate_order_item_image_urls($order, $rows);
    $stream = fopen('php://temp', 'r+');
    if ($stream === false) {
        throw new RuntimeException('No se pudo preparar el archivo CSV temporal.');
    }

    fputcsv($stream, ['Pedido', $order['order_number'] ?? '']);
    fputcsv($stream, ['Catalogo', $order['catalog_title'] ?? $order['catalog_slug'] ?? '']);
    fputcsv($stream, ['Empresa', $order['company_name'] ?? '']);
    fputcsv($stream, ['Contacto', $order['contact_name'] ?? '']);
    fputcsv($stream, ['Correo', $order['contact_email'] ?? '']);
    fputcsv($stream, ['Telefono', $order['contact_phone'] ?? '']);
    $salesContact = sales_contact_info();
    fputcsv($stream, ['Contacto comercial', $salesContact['name']]);
    fputcsv($stream, ['Correo comercial', $salesContact['email']]);
    fputcsv($stream, ['Telefono comercial', $salesContact['phone']]);
    fputcsv($stream, ['Zona / Direccion', $order['address_zone'] ?? '']);
    fputcsv($stream, ['Estado', $order['status'] ?? '']);
    fputcsv($stream, ['Fecha', $order['created_at'] ?? date('Y-m-d H:i:s')]);
    fputcsv($stream, ['Total', number_format((float) ($order['total'] ?? 0), 2, '.', '')]);
    fputcsv($stream, []);
    fputcsv($stream, ['URL_IMAGEN', 'ITEM', 'Descripcion', 'Cantidad', 'Unidad de venta', 'Empaque', 'Piezas', 'Precio unitario', 'Total linea']);

    foreach ($rows as $row) {
        fputcsv($stream, [
            safeImageUrl((string) ($row['image_url'] ?? ''), 'https://rodeoimportzl.com/catalogos_admin/assets/no-image.png'),
            $row['item_code'] ?? '',
            $row['description'] ?? '',
            format_plain_number((float) ($row['quantity'] ?? 0)),
            $row['sale_unit'] ?? '',
            trim((string) (($row['package_label'] ?? '') . ' ' . format_plain_number((float) ($row['package_qty'] ?? 0)))),
            format_plain_number((float) ($row['pieces_total'] ?? 0)),
            number_format((float) ($row['unit_price'] ?? 0), 2, '.', ''),
            number_format((float) ($row['line_total'] ?? 0), 2, '.', ''),
        ]);
    }

    rewind($stream);
    $content = stream_get_contents($stream) ?: '';
    fclose($stream);
    return $content;
}

function build_order_xlsx_file(array $order, array $rows, string $targetPath): bool
{
    $rows = hydrate_order_item_image_urls($order, $rows);
    $headerRow = 14;
    $dataStartRow = 15;
    $dataEndRow = $dataStartRow + max(count($rows) - 1, 0);
    $totalRow = $dataEndRow + 2;

    $embeddedImages = prepare_order_xlsx_images($rows, $dataStartRow);
    $sheetXml = build_order_sheet_xml($order, $rows, $headerRow, $dataEndRow, $totalRow, $embeddedImages);
    $zip = new ZipArchive();
    if ($zip->open($targetPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        return false;
    }

    $zip->addFromString('[Content_Types].xml', build_order_content_types_xml($embeddedImages));
    $zip->addFromString('_rels/.rels', build_order_root_rels_xml());
    $zip->addFromString('xl/workbook.xml', build_order_workbook_xml());
    $zip->addFromString('xl/_rels/workbook.xml.rels', build_order_workbook_rels_xml());
    $zip->addFromString('xl/styles.xml', build_order_styles_xml());
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
    if ($embeddedImages) {
        $zip->addFromString('xl/worksheets/_rels/sheet1.xml.rels', build_order_sheet_rels_xml());
        $zip->addFromString('xl/drawings/drawing1.xml', build_order_drawing_xml($embeddedImages));
        $zip->addFromString('xl/drawings/_rels/drawing1.xml.rels', build_order_drawing_rels_xml($embeddedImages));
        foreach ($embeddedImages as $image) {
            $zip->addFromString('xl/media/' . $image['name'], $image['content']);
        }
    }
    $zip->close();

    return is_file($targetPath);
}

function build_order_sheet_xml(array $order, array $rows, int $headerRow, int $dataEndRow, int $totalRow, array $embeddedImages = []): string
{
    $cells = [];
    $salesContact = sales_contact_info();
    $metaRows = [
        ['A1', 'Pedido', 'B1', (string) ($order['order_number'] ?? '')],
        ['A2', 'Catalogo', 'B2', (string) ($order['catalog_title'] ?? $order['catalog_slug'] ?? '')],
        ['A3', 'Empresa', 'B3', (string) ($order['company_name'] ?? '')],
        ['A4', 'Contacto', 'B4', (string) ($order['contact_name'] ?? '')],
        ['A5', 'Correo', 'B5', (string) ($order['contact_email'] ?? '')],
        ['A6', 'Telefono', 'B6', (string) ($order['contact_phone'] ?? '')],
        ['A7', 'Contacto comercial', 'B7', $salesContact['name']],
        ['A8', 'Correo comercial', 'B8', $salesContact['email']],
        ['A9', 'Telefono comercial', 'B9', $salesContact['phone']],
        ['A10', 'Zona', 'B10', (string) ($order['address_zone'] ?? '')],
        ['A11', 'Estado', 'B11', (string) ($order['status'] ?? '')],
        ['A12', 'Fecha', 'B12', (string) ($order['created_at'] ?? date('Y-m-d H:i:s'))],
    ];

    foreach ($metaRows as $index => $meta) {
        $cells[] = build_order_sheet_row($index + 1, [
            build_order_text_cell($meta[0], $meta[1]),
            build_order_text_cell($meta[2], $meta[3]),
        ]);
    }

    $headers = ['Imagen', 'ITEM', 'Descripcion', 'Cantidad', 'Unidad', 'Empaque', 'Piezas', 'Precio Unitario', 'Total Linea'];
    $headerCells = [];
    foreach (range('A', 'I') as $index => $column) {
        $headerCells[] = build_order_text_cell($column . $headerRow, $headers[$index], 1);
    }
    $cells[] = build_order_sheet_row($headerRow, $headerCells);

    $rowNumber = $headerRow + 1;
    foreach ($rows as $row) {
        $hasImage = trim((string) ($row['image_url'] ?? '')) !== '';
        $cells[] = build_order_sheet_row($rowNumber, [
            build_order_text_cell('A' . $rowNumber, $hasImage ? '' : 'Sin imagen', 3),
            build_order_text_cell('B' . $rowNumber, (string) ($row['item_code'] ?? ''), 2),
            build_order_text_cell('C' . $rowNumber, (string) ($row['description'] ?? ''), 2),
            build_order_number_cell('D' . $rowNumber, (float) ($row['quantity'] ?? 0), 3),
            build_order_text_cell('E' . $rowNumber, (string) ($row['sale_unit'] ?? ''), 2),
            build_order_text_cell('F' . $rowNumber, trim((string) (($row['package_label'] ?? '') . ' ' . format_plain_number((float) ($row['package_qty'] ?? 0)))), 2),
            build_order_number_cell('G' . $rowNumber, (float) ($row['pieces_total'] ?? 0), 3),
            build_order_number_cell('H' . $rowNumber, (float) ($row['unit_price'] ?? 0), 4),
            build_order_number_cell('I' . $rowNumber, (float) ($row['line_total'] ?? 0), 4),
        ], $hasImage ? 48 : null);
        $rowNumber++;
    }

    $cells[] = build_order_sheet_row($totalRow, [
        build_order_text_cell('H' . $totalRow, 'Total General', 5),
        build_order_number_cell('I' . $totalRow, (float) ($order['total'] ?? 0), 6),
    ]);

    $drawingXml = $embeddedImages ? '<drawing r:id="rId1"/>' : '';
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheetViews><sheetView workbookViewId="0"/></sheetViews>'
        . '<sheetFormatPr defaultRowHeight="18"/>'
        . '<cols>'
        . '<col min="1" max="1" width="12" customWidth="1"/>'
        . '<col min="2" max="2" width="18" customWidth="1"/>'
        . '<col min="3" max="3" width="42" customWidth="1"/>'
        . '<col min="4" max="9" width="16" customWidth="1"/>'
        . '</cols>'
        . '<sheetData>' . implode('', $cells) . '</sheetData>'
        . '<autoFilter ref="A' . $headerRow . ':I' . max($headerRow, $dataEndRow) . '"/>'
        . $drawingXml
        . '</worksheet>';
}

function build_order_styles_xml(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<numFmts count="1"><numFmt numFmtId="164" formatCode="$#,##0.00"/></numFmts>'
        . '<fonts count="3">'
        . '<font><sz val="11"/><name val="Calibri"/></font>'
        . '<font><sz val="11"/><name val="Calibri"/><b/></font>'
        . '<font><sz val="12"/><name val="Calibri"/><b/><color rgb="FFFFFFFF"/></font>'
        . '</fonts>'
        . '<fills count="4">'
        . '<fill><patternFill patternType="none"/></fill>'
        . '<fill><patternFill patternType="gray125"/></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FF1F3B5F"/><bgColor indexed="64"/></patternFill></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FFF4F7FB"/><bgColor indexed="64"/></patternFill></fill>'
        . '</fills>'
        . '<borders count="2">'
        . '<border><left/><right/><top/><bottom/><diagonal/></border>'
        . '<border><left style="thin"><color auto="1"/></left><right style="thin"><color auto="1"/></right><top style="thin"><color auto="1"/></top><bottom style="thin"><color auto="1"/></bottom><diagonal/></border>'
        . '</borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="7">'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        . '<xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center" wrapText="1"/></xf>'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
        . '<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
        . '<xf numFmtId="0" fontId="1" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
        . '<xf numFmtId="164" fontId="1" fillId="3" borderId="1" xfId="0" applyFont="1" applyNumberFormat="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
        . '</cellXfs>'
        . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        . '</styleSheet>';
}

function build_order_content_types_xml(array $embeddedImages = []): string
{
    $imageDefaults = [];
    foreach ($embeddedImages as $image) {
        $extension = strtolower((string) ($image['extension'] ?? ''));
        if ($extension === 'jpg') {
            $extension = 'jpeg';
        }
        if ($extension !== '' && !isset($imageDefaults[$extension])) {
            $contentType = $extension === 'png' ? 'image/png' : 'image/jpeg';
            $imageDefaults[$extension] = '<Default Extension="' . xml_escape_value($extension) . '" ContentType="' . xml_escape_value($contentType) . '"/>';
        }
    }

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . implode('', $imageDefaults)
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . ($embeddedImages ? '<Override PartName="/xl/drawings/drawing1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>' : '')
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . '</Types>';
}

function build_order_root_rels_xml(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>';
}

function build_order_workbook_xml(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="Pedido" sheetId="1" r:id="rId1"/></sheets>'
        . '</workbook>';
}

function build_order_workbook_rels_xml(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '</Relationships>';
}

function build_order_sheet_rels_xml(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing1.xml"/>'
        . '</Relationships>';
}

function build_order_drawing_rels_xml(array $embeddedImages): string
{
    $relationships = [];
    foreach ($embeddedImages as $image) {
        $relationships[] = '<Relationship Id="rId' . (int) $image['id'] . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/' . xml_escape_value((string) $image['name']) . '"/>';
    }

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . implode('', $relationships)
        . '</Relationships>';
}

function build_order_drawing_xml(array $embeddedImages): string
{
    $anchors = [];
    foreach ($embeddedImages as $image) {
        $rowIndex = max(0, (int) $image['row'] - 1);
        $widthEmu = max(1, (int) $image['width_emu']);
        $heightEmu = max(1, (int) $image['height_emu']);
        $anchors[] = '<xdr:oneCellAnchor>'
            . '<xdr:from><xdr:col>0</xdr:col><xdr:colOff>95250</xdr:colOff><xdr:row>' . $rowIndex . '</xdr:row><xdr:rowOff>95250</xdr:rowOff></xdr:from>'
            . '<xdr:ext cx="' . $widthEmu . '" cy="' . $heightEmu . '"/>'
            . '<xdr:pic>'
            . '<xdr:nvPicPr><xdr:cNvPr id="' . (int) $image['id'] . '" name="' . xml_escape_value((string) $image['name']) . '"/><xdr:cNvPicPr><a:picLocks noChangeAspect="1"/></xdr:cNvPicPr></xdr:nvPicPr>'
            . '<xdr:blipFill><a:blip r:embed="rId' . (int) $image['id'] . '"/><a:stretch><a:fillRect/></a:stretch></xdr:blipFill>'
            . '<xdr:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' . $widthEmu . '" cy="' . $heightEmu . '"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr>'
            . '</xdr:pic><xdr:clientData/></xdr:oneCellAnchor>';
    }

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . implode('', $anchors)
        . '</xdr:wsDr>';
}

function build_order_sheet_row(int $rowNumber, array $cells, ?float $height = null): string
{
    $heightAttr = $height !== null ? ' ht="' . number_format($height, 2, '.', '') . '" customHeight="1"' : '';
    return '<row r="' . $rowNumber . '"' . $heightAttr . '>' . implode('', $cells) . '</row>';
}

function build_order_text_cell(string $cellRef, string $value, int $style = 0): string
{
    return '<c r="' . $cellRef . '" s="' . $style . '" t="inlineStr"><is><t>' . xml_escape_value($value) . '</t></is></c>';
}

function build_order_number_cell(string $cellRef, float $value, int $style = 0): string
{
    return '<c r="' . $cellRef . '" s="' . $style . '"><v>' . number_format($value, 2, '.', '') . '</v></c>';
}

function hydrate_order_item_image_urls(array $order, array $rows): array
{
    $imageMap = build_catalog_product_image_map($order);
    $publicUrl = (string) ($order['public_url'] ?? $order['catalog_public_url'] ?? '');

    foreach ($rows as &$row) {
        if (!is_array($row)) {
            continue;
        }
        $row['image_url'] = productImageUrl($row, $imageMap, $publicUrl);
    }
    unset($row);

    return $rows;
}

function safeText(mixed $value): string
{
    $text = trim((string) $value);
    return html_escape($text !== '' ? $text : 'No definido');
}

function money(float $value, string $currency = 'USD'): string
{
    return html_escape(($currency !== '' ? $currency : 'USD') . ' ' . number_format($value, 2, '.', ','));
}

function cleanPhone(mixed $value): string
{
    return preg_replace('/\D+/', '', (string) $value) ?? '';
}

function productImageUrl(array $product, array $catalogProductImages, string $catalogPublicUrl = ''): string
{
    $direct = first_non_empty_string([
        $product['remote_image_url'] ?? '',
        $product['remoteImageUrl'] ?? '',
        $product['image_url'] ?? '',
        $product['imageUrl'] ?? '',
        $product['main_image'] ?? '',
        $product['mainImage'] ?? '',
    ]);
    if ($direct !== '') {
        return absolutize_catalog_asset_url($direct, $catalogPublicUrl);
    }

    $itemCode = normalize_product_item_key((string) ($product['item_code'] ?? $product['item'] ?? ''));
    if ($itemCode !== '' && !empty($catalogProductImages[$itemCode])) {
        return absolutize_catalog_asset_url((string) $catalogProductImages[$itemCode], $catalogPublicUrl);
    }

    return '';
}

function build_catalog_product_image_map(array $catalog): array
{
    $json = catalog_json_data((string) ($catalog['catalog_json_path'] ?? ''));
    if (!$json) {
        $apiPayload = json_decode((string) ($catalog['api_payload'] ?? ''), true);
        $json = is_array($apiPayload) ? $apiPayload : [];
    }

    $products = [];
    foreach (['catalog', 'products', 'items'] as $key) {
        if (!empty($json[$key]) && is_array($json[$key])) {
            $products = $json[$key];
            break;
        }
    }
    if (!$products && !empty($json['metadata']['catalog']) && is_array($json['metadata']['catalog'])) {
        $products = $json['metadata']['catalog'];
    }

    $map = [];
    foreach ($products as $product) {
        if (!is_array($product)) {
            continue;
        }
        $itemCode = normalize_product_item_key((string) ($product['item'] ?? $product['item_code'] ?? ''));
        if ($itemCode === '') {
            continue;
        }
        $media = !empty($product['media']) && is_array($product['media']) ? $product['media'] : [];
        $imageUrl = first_non_empty_string([
            $product['remote_image_url'] ?? '',
            $product['remoteImageUrl'] ?? '',
            $media['remote_image_url'] ?? '',
            $media['remoteImageUrl'] ?? '',
            $product['image_url'] ?? '',
            $product['imageUrl'] ?? '',
            $product['main_image'] ?? '',
            $product['mainImage'] ?? '',
            $media['mainImage'] ?? '',
            $media['main_image'] ?? '',
            !empty($media['gallery']) && is_array($media['gallery']) ? ($media['gallery'][0] ?? '') : '',
            !empty($media['mainImageCandidates']) && is_array($media['mainImageCandidates']) ? ($media['mainImageCandidates'][0] ?? '') : '',
        ]);
        if ($imageUrl !== '') {
            $map[$itemCode] = $imageUrl;
        }
    }

    return $map;
}

function safeImageUrl(string $imageUrl, string $fallbackUrl): string
{
    $imageUrl = trim($imageUrl);
    if ($imageUrl !== '' && preg_match('#^https?://#i', $imageUrl)) {
        return $imageUrl;
    }

    return trim($fallbackUrl) !== '' ? trim($fallbackUrl) : 'https://rodeoimportzl.com/catalogos_admin/assets/no-image.png';
}

function absolutize_catalog_asset_url(string $url, string $catalogPublicUrl): string
{
    $url = trim($url);
    if ($url === '' || str_starts_with($url, 'data:') || str_starts_with($url, 'blob:')) {
        return '';
    }
    if (preg_match('#^https?://#i', $url)) {
        return $url;
    }

    $base = trim($catalogPublicUrl);
    if ($base === '') {
        return '';
    }
    $base = preg_replace('/[#?].*$/', '', $base) ?? $base;
    $base = preg_replace('#/[^/]*$#', '/', $base) ?? $base;
    return rtrim($base, '/') . '/' . ltrim($url, './');
}

function normalize_product_item_key(string $value): string
{
    return strtoupper(trim($value));
}

function first_non_empty_string(array $values): string
{
    foreach ($values as $value) {
        $text = trim((string) $value);
        if ($text !== '') {
            return $text;
        }
    }

    return '';
}

function prepare_order_xlsx_images(array $rows, int $dataStartRow): array
{
    $images = [];
    $id = 1;
    foreach ($rows as $index => $row) {
        if (trim((string) ($row['image_url'] ?? '')) === '') {
            continue;
        }
        $imageUrl = safeImageUrl((string) ($row['image_url'] ?? ''), '');
        if ($imageUrl === '') {
            continue;
        }
        $image = fetch_order_xlsx_image($imageUrl, $id, $dataStartRow + $index);
        if ($image) {
            $images[] = $image;
            $id++;
        }
    }

    return $images;
}

function fetch_order_xlsx_image(string $imageUrl, int $id, int $rowNumber): ?array
{
    $context = stream_context_create([
        'http' => [
            'timeout' => 4,
            'follow_location' => 1,
            'user_agent' => 'CatalogoRodeoB2B/1.0',
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);
    $content = @file_get_contents($imageUrl, false, $context);
    if ($content === false || $content === '') {
        return null;
    }
    if (strlen($content) > 2_500_000) {
        return null;
    }

    $info = @getimagesizefromstring($content);
    if (!is_array($info)) {
        return null;
    }
    $mime = (string) ($info['mime'] ?? '');
    $extension = match ($mime) {
        'image/png' => 'png',
        'image/jpeg' => 'jpeg',
        default => '',
    };
    if ($extension === '') {
        if (!function_exists('imagecreatefromstring') || !function_exists('imagepng')) {
            return null;
        }
        $sourceImage = @imagecreatefromstring($content);
        if (!$sourceImage) {
            return null;
        }
        ob_start();
        imagepng($sourceImage);
        $converted = ob_get_clean();
        imagedestroy($sourceImage);
        if (!is_string($converted) || $converted === '') {
            return null;
        }
        $content = $converted;
        $extension = 'png';
    }

    $width = max(1, (int) ($info[0] ?? 60));
    $height = max(1, (int) ($info[1] ?? 60));
    $scale = min(60 / $width, 60 / $height, 1);
    $displayWidth = max(1, (int) round($width * $scale));
    $displayHeight = max(1, (int) round($height * $scale));

    return [
        'id' => $id,
        'row' => $rowNumber,
        'name' => 'product-' . $id . '.' . $extension,
        'extension' => $extension,
        'content' => $content,
        'width_emu' => $displayWidth * 9525,
        'height_emu' => $displayHeight * 9525,
    ];
}

function xml_escape_value(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function format_plain_number(float $value): string
{
    return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
}
