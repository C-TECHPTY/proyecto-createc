<?php
declare(strict_types=1);

require __DIR__ . '/ai_helpers.php';

$payload = ai_request_payload();
$sender = ai_normalize_phone((string) ($payload['sender'] ?? ''));
$seller = ai_find_seller_by_sender($sender);
if (!$seller) {
    ai_log('product.denied', ['sender' => $sender, 'reason' => 'seller_not_authorized']);
    json_response([
        'ok' => false,
        'error' => 'Vendedor no autorizado.',
    ], 403);
}

$item = trim((string) ($payload['item'] ?? ''));
$query = trim((string) ($payload['query'] ?? 'full'));
$catalog = ai_active_catalog_by_slug_or_latest((string) ($payload['catalog_slug'] ?? ''));
if (!$catalog) {
    json_response([
        'ok' => false,
        'error' => 'No hay catalogo activo disponible.',
    ], 404);
}

$product = $item !== '' ? ai_find_product($catalog, $item) : null;
if (!$product) {
    ai_log('product.not_found', ['seller_id' => $seller['id'], 'item' => $item]);
    json_response([
        'ok' => false,
        'error' => 'Producto no encontrado.',
        'item' => $item,
    ], 404);
}

$response = [
    'ok' => true,
    'query' => $query,
    'catalog' => [
        'id' => (int) $catalog['id'],
        'slug' => $catalog['slug'],
        'title' => $catalog['title'],
        'public_url' => $catalog['public_url'],
    ],
    'item' => ai_first_text([$product['item'] ?? '', $product['item_code'] ?? '', $item]),
    'description' => ai_first_text([$product['description'] ?? '', $product['shortDescription'] ?? '', $product['name'] ?? '']),
    'price' => ai_first_text([$product['price'] ?? '', $product['unit_price'] ?? '', $product['regular_price'] ?? '']),
    'stock' => ai_first_text([$product['available'] ?? '', $product['stock'] ?? '', $product['disponible'] ?? '', $product['media']['available'] ?? '']),
    'image_url' => ai_product_image_url($product, $catalog),
];

ai_log('product.query', [
    'seller_id' => $seller['id'],
    'item' => $response['item'],
    'query' => $query,
    'catalog_id' => $catalog['id'],
]);

json_response($response);
