<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$payload = read_json_input();

if (!catalog_table_exists('catalog_behavior_events')) {
    json_response([
        'ok' => true,
        'tracking' => 'disabled',
    ]);
}

$slug = slugify((string) ($payload['slug'] ?? ''));
$shareToken = trim((string) ($payload['share_token'] ?? ''));
$sellerToken = trim((string) ($payload['seller_token'] ?? ''));
$eventType = normalize_tracking_text((string) ($payload['event_type'] ?? ''), 60);

if ($slug === '' || $eventType === '') {
    json_response([
        'ok' => false,
        'error' => 'Datos incompletos para registrar el evento.',
    ], 422);
}

$allowedEvents = [
    'catalog_view',
    'search',
    'category_filter',
    'product_detail',
    'product_media',
    'add_to_cart',
    'cart_open',
    'cart_quantity',
    'remove_from_cart',
    'checkout_start',
    'order_submit_attempt',
    'order_submit_success',
    'order_submit_failed',
    'offline_order_queued',
];

if (!in_array($eventType, $allowedEvents, true)) {
    json_response([
        'ok' => false,
        'error' => 'Tipo de evento no permitido.',
    ], 422);
}

$context = resolve_public_catalog_context($slug, $shareToken, $sellerToken);
$product = is_array($payload['product'] ?? null) ? $payload['product'] : [];
$extra = is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [];

$metadata = [
    'source' => normalize_tracking_text((string) ($payload['source'] ?? 'catalog-public'), 80),
    'path' => normalize_tracking_text((string) ($payload['path'] ?? ''), 255),
    'referrer' => normalize_tracking_text((string) ($_SERVER['HTTP_REFERER'] ?? ''), 255),
    'extra' => $extra,
];

db()->prepare(
    'INSERT INTO catalog_behavior_events (
        catalog_id, share_link_id, seller_id, client_id, event_type,
        item_code, item_name, category, search_term, quantity, value_amount,
        session_id, visitor_id, metadata_json, ip_address, user_agent
     ) VALUES (
        :catalog_id, :share_link_id, :seller_id, :client_id, :event_type,
        :item_code, :item_name, :category, :search_term, :quantity, :value_amount,
        :session_id, :visitor_id, :metadata_json, :ip_address, :user_agent
     )'
)->execute([
    'catalog_id' => (int) $context['catalog']['id'],
    'share_link_id' => !empty($context['share_link']['id']) ? (int) $context['share_link']['id'] : null,
    'seller_id' => !empty($context['seller_id']) ? (int) $context['seller_id'] : null,
    'client_id' => !empty($context['client_id']) ? (int) $context['client_id'] : null,
    'event_type' => $eventType,
    'item_code' => normalize_tracking_text((string) ($product['item_code'] ?? $product['item'] ?? ''), 120),
    'item_name' => normalize_tracking_text((string) ($product['item_name'] ?? $product['description'] ?? ''), 255),
    'category' => normalize_tracking_text((string) ($product['category'] ?? ''), 160),
    'search_term' => normalize_tracking_text((string) ($payload['search_term'] ?? ''), 190),
    'quantity' => array_key_exists('quantity', $payload) ? parse_decimal($payload['quantity']) : null,
    'value_amount' => array_key_exists('value_amount', $payload) ? parse_decimal($payload['value_amount']) : null,
    'session_id' => normalize_tracking_text((string) ($payload['session_id'] ?? ''), 80),
    'visitor_id' => normalize_tracking_text((string) ($payload['visitor_id'] ?? ''), 80),
    'metadata_json' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    'ip_address' => normalize_tracking_text((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 64),
    'user_agent' => normalize_tracking_text((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 255),
]);

json_response([
    'ok' => true,
]);

function normalize_tracking_text(string $value, int $maxLength): string
{
    $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    if ($value === '') {
        return '';
    }

    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLength, 'UTF-8');
    }

    return substr($value, 0, $maxLength);
}
