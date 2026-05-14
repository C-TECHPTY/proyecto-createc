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

json_response([
    'ok' => true,
    'catalog' => build_public_catalog_payload($context),
]);
