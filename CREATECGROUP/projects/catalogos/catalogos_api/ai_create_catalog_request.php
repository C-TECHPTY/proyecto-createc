<?php
declare(strict_types=1);

require __DIR__ . '/ai_helpers.php';

$payload = ai_request_payload();
$sender = ai_normalize_phone((string) ($payload['sender'] ?? ''));
$seller = ai_find_seller_by_sender($sender);
if (!$seller) {
    ai_log('catalog.denied', ['sender' => $sender, 'reason' => 'seller_not_authorized']);
    json_response([
        'ok' => false,
        'error' => 'Vendedor no autorizado.',
    ], 403);
}

$catalog = ai_active_catalog_by_slug_or_latest((string) ($payload['catalog_slug'] ?? ''));
if (!$catalog) {
    json_response([
        'ok' => false,
        'error' => 'No hay catalogo activo disponible.',
    ], 404);
}

$publicUrl = (string) $catalog['public_url'];
$sellerToken = trim((string) ($seller['public_token'] ?? ''));
if ($sellerToken !== '') {
    $separator = str_contains($publicUrl, '?') ? '&' : '?';
    $publicUrl .= $separator . 't=' . rawurlencode($sellerToken);
}

$requestId = 'AI-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
ai_log('catalog.requestByCategory', [
    'request_id' => $requestId,
    'seller_id' => $seller['id'],
    'catalog_id' => $catalog['id'],
    'category' => trim((string) ($payload['category'] ?? '')),
    'channel' => $payload['channel'] ?? '',
    'draft_only' => !empty($payload['draft_only']),
]);

json_response([
    'ok' => true,
    'request_id' => $requestId,
    'status' => 'ready',
    'draft_only' => !empty($payload['draft_only']),
    'seller' => [
        'id' => (int) $seller['id'],
        'name' => $seller['name'],
    ],
    'catalog' => [
        'id' => (int) $catalog['id'],
        'slug' => $catalog['slug'],
        'title' => $catalog['title'],
        'public_url' => $publicUrl,
    ],
    'message' => 'Solicitud preparada. No se creo pedido final.',
]);
