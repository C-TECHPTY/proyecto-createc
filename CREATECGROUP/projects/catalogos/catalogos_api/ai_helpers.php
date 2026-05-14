<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function ai_request_payload(): array
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        json_response([
            'ok' => false,
            'error' => 'Metodo no permitido.',
        ], 405);
    }

    $payload = read_json_input();
    ai_require_limited_key($payload);
    return $payload;
}

function ai_require_limited_key(array $payload): void
{
    $expected = trim(app_setting('rodeo_ai_api_key', ''));
    if ($expected === '') {
        json_response([
            'ok' => false,
            'error' => 'RODE IA no esta configurado. Define app_settings.rodeo_ai_api_key.',
        ], 503);
    }

    $provided = trim((string) ($_SERVER['HTTP_X_RODEO_AI_KEY'] ?? $payload['api_key'] ?? ''));
    if ($provided === '' || !hash_equals($expected, $provided)) {
        ai_log('auth.denied', ['reason' => 'invalid_ai_key']);
        json_response([
            'ok' => false,
            'error' => 'API key invalida.',
        ], 401);
    }
}

function ai_log(string $action, array $context = []): void
{
    try {
        db()->prepare(
            'INSERT INTO activity_logs (user_id, action, entity_type, entity_id, context_json, ip_address)
             VALUES (NULL, :action, :entity_type, NULL, :context_json, :ip_address)'
        )->execute([
            'action' => 'rodeo_ai.' . $action,
            'entity_type' => 'rodeo_ai',
            'context_json' => $context ? json_encode(ai_redact_context($context), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
        ]);
    } catch (Throwable) {
    }
}

function ai_redact_context(array $context): array
{
    foreach ($context as $key => $value) {
        if (preg_match('/key|token|password|secret/i', (string) $key)) {
            $context[$key] = '[REDACTED]';
        }
    }

    return $context;
}

function ai_normalize_phone(string $phone): string
{
    return preg_replace('/\D+/', '', $phone) ?? '';
}

function ai_find_seller_by_sender(string $sender): ?array
{
    $sender = ai_normalize_phone($sender);
    if ($sender === '') {
        return null;
    }

    $statement = db()->prepare(
        "SELECT id, code, name, email, phone, public_token
         FROM sellers
         WHERE is_active = 1
           AND REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, '+', ''), '-', ''), ' ', ''), '(', ''), ')', '') = :phone
         LIMIT 1"
    );
    $statement->execute(['phone' => $sender]);
    $seller = $statement->fetch();

    return $seller ?: null;
}

function ai_active_catalog_by_slug_or_latest(string $slug = ''): ?array
{
    if ($slug !== '') {
        $statement = db()->prepare(
            "SELECT *
             FROM catalogs
             WHERE slug = :slug AND status = 'active'
             LIMIT 1"
        );
        $statement->execute(['slug' => slugify($slug)]);
        $catalog = $statement->fetch();
        if ($catalog) {
            return $catalog;
        }
    }

    $statement = db()->query(
        "SELECT *
         FROM catalogs
         WHERE status = 'active'
         ORDER BY created_at DESC, id DESC
         LIMIT 1"
    );
    $catalog = $statement->fetch();

    return $catalog ?: null;
}

function ai_catalog_products(array $catalog): array
{
    $json = catalog_json_data((string) ($catalog['catalog_json_path'] ?? ''));
    if (!$json) {
        $json = json_decode((string) ($catalog['api_payload'] ?? ''), true);
        if (!is_array($json)) {
            $json = [];
        }
    }

    foreach (['catalog', 'products', 'items'] as $key) {
        if (!empty($json[$key]) && is_array($json[$key])) {
            return array_values(array_filter($json[$key], static fn($product): bool => is_array($product)));
        }
    }

    if (!empty($json['metadata']['catalog']) && is_array($json['metadata']['catalog'])) {
        return array_values(array_filter($json['metadata']['catalog'], static fn($product): bool => is_array($product)));
    }

    return [];
}

function ai_find_product(array $catalog, string $itemCode): ?array
{
    $target = strtoupper(trim($itemCode));
    foreach (ai_catalog_products($catalog) as $product) {
        $item = strtoupper(trim((string) ($product['item'] ?? $product['item_code'] ?? $product['sku'] ?? '')));
        if ($item === $target) {
            return $product;
        }
    }

    return null;
}

function ai_first_text(array $values): string
{
    foreach ($values as $value) {
        $text = trim((string) $value);
        if ($text !== '') {
            return $text;
        }
    }

    return '';
}

function ai_product_image_url(array $product, array $catalog): string
{
    $image = ai_first_text([
        $product['remote_image_url'] ?? '',
        $product['remoteImageUrl'] ?? '',
        $product['media']['remote_image_url'] ?? '',
        $product['media']['remoteImageUrl'] ?? '',
        $product['image_url'] ?? '',
        $product['imageUrl'] ?? '',
        $product['main_image'] ?? '',
        $product['mainImage'] ?? '',
        $product['media']['mainImage'] ?? '',
        $product['media']['main_image'] ?? '',
        !empty($product['media']['gallery']) && is_array($product['media']['gallery']) ? ($product['media']['gallery'][0] ?? '') : '',
    ]);

    if ($image === '') {
        return '';
    }

    if (preg_match('#^https?://#i', $image)) {
        return $image;
    }

    $baseUrl = rtrim((string) ($catalog['public_url'] ?? ''), '/') . '/';
    return $baseUrl !== '/' ? $baseUrl . ltrim(preg_replace('#^\./#', '', $image) ?? $image, '/') : '';
}
