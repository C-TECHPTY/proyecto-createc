<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$slug = slugify((string) ($_GET['slug'] ?? ''));
$token = trim((string) ($_GET['token'] ?? ''));
$sellerToken = trim((string) ($_GET['t'] ?? $_GET['seller_token'] ?? ''));
if ($slug === '') {
    json_response([
        'ok' => false,
        'error' => 'Debes indicar el slug del catalogo.',
    ], 422);
}

$context = resolve_public_catalog_context($slug, $token, $sellerToken);
record_catalog_access($context);
$catalog = $context['catalog'];
$status = resolve_catalog_status($catalog);

json_response([
    'ok' => $status === 'active',
    'catalog' => [
        'id' => $catalog['id'],
        'slug' => $catalog['slug'],
        'title' => $catalog['title'],
        'status' => $status,
        'public_url' => $catalog['public_url'],
        'pdf_url' => $catalog['pdf_url'],
        'expires_at' => $catalog['expires_at'],
        'seller_name' => $context['seller_name'],
        'seller_token' => $context['seller_token'] ?? '',
        'client_name' => $context['client_name'],
        'share_link_id' => $context['share_link']['id'] ?? null,
    ],
]);
