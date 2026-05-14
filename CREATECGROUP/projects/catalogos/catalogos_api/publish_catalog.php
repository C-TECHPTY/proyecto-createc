<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/saas_license_helpers.php';

$payload = read_json_input();
require_api_key($payload);
$saasContext = saas_validate_license_context(db(), $payload);
$saasRequested = !empty($payload['saas_validation_enabled'])
    || !empty($payload['saas_license_key'])
    || !empty($payload['saas_company_slug']);

$slug = slugify((string) ($payload['slug'] ?? 'catalogo-publicable'));
$title = trim((string) ($payload['title'] ?? 'Catalogo publicable'));
$template = trim((string) ($payload['template'] ?? 'classic'));
$publicUrl = trim((string) ($payload['public_url'] ?? ''));
$pdfUrl = trim((string) ($payload['pdf_url'] ?? ''));
$sellerName = trim((string) ($payload['seller_name'] ?? ''));
$clientName = trim((string) ($payload['client_name'] ?? ''));
$heroTitle = trim((string) ($payload['hero_title'] ?? $title));
$heroSubtitle = trim((string) ($payload['hero_subtitle'] ?? 'Catalogo comercial B2B'));
$promoTitle = trim((string) ($payload['promo_title'] ?? ''));
$promoText = trim((string) ($payload['promo_text'] ?? ''));
$promoImageUrl = trim((string) ($payload['promo_image_url'] ?? ''));
$promoVideoUrl = trim((string) ($payload['promo_video_url'] ?? ''));
$promoLinkUrl = trim((string) ($payload['promo_link_url'] ?? ''));
$promoLinkLabel = trim((string) ($payload['promo_link_label'] ?? ''));
$currency = trim((string) ($payload['currency'] ?? 'USD'));
$legacyPdfUrl = trim((string) ($payload['legacy_pdf_url'] ?? $pdfUrl));
$modernPdfUrl = trim((string) ($payload['modern_pdf_url'] ?? ''));
$notes = trim((string) ($payload['notes'] ?? ''));
$catalogJsonPath = trim((string) ($payload['catalog_json_path'] ?? ''));
$expiresAt = !empty($payload['expires_at']) ? date('Y-m-d H:i:s', strtotime((string) $payload['expires_at'])) : null;
$status = trim((string) ($payload['status'] ?? 'active'));

$sql = <<<SQL
INSERT INTO catalogs (
    slug, title, template, public_url, pdf_url, generated_at, expires_at, status,
    seller_name, client_name, hero_title, hero_subtitle, promo_title, promo_text,
    promo_image_url, promo_video_url, promo_link_url, promo_link_label,
    currency, legacy_pdf_url, modern_pdf_url, notes, catalog_json_path, api_payload
) VALUES (
    :slug, :title, :template, :public_url, :pdf_url, NOW(), :expires_at, :status,
    :seller_name, :client_name, :hero_title, :hero_subtitle, :promo_title, :promo_text,
    :promo_image_url, :promo_video_url, :promo_link_url, :promo_link_label,
    :currency, :legacy_pdf_url, :modern_pdf_url, :notes, :catalog_json_path, :api_payload
)
ON DUPLICATE KEY UPDATE
    title = VALUES(title),
    template = VALUES(template),
    public_url = VALUES(public_url),
    pdf_url = VALUES(pdf_url),
    expires_at = VALUES(expires_at),
    status = VALUES(status),
    seller_name = VALUES(seller_name),
    client_name = VALUES(client_name),
    hero_title = VALUES(hero_title),
    hero_subtitle = VALUES(hero_subtitle),
    promo_title = VALUES(promo_title),
    promo_text = VALUES(promo_text),
    promo_image_url = VALUES(promo_image_url),
    promo_video_url = VALUES(promo_video_url),
    promo_link_url = VALUES(promo_link_url),
    promo_link_label = VALUES(promo_link_label),
    currency = VALUES(currency),
    legacy_pdf_url = VALUES(legacy_pdf_url),
    modern_pdf_url = VALUES(modern_pdf_url),
    notes = VALUES(notes),
    catalog_json_path = VALUES(catalog_json_path),
    api_payload = VALUES(api_payload),
    updated_at = NOW()
SQL;

$statement = db()->prepare($sql);
$statement->execute([
    'slug' => $slug,
    'title' => $title,
    'template' => $template,
    'public_url' => $publicUrl,
    'pdf_url' => $pdfUrl,
    'expires_at' => $expiresAt,
    'status' => $status,
    'seller_name' => $sellerName,
    'client_name' => $clientName,
    'hero_title' => $heroTitle,
    'hero_subtitle' => $heroSubtitle,
    'promo_title' => $promoTitle,
    'promo_text' => $promoText,
    'promo_image_url' => $promoImageUrl,
    'promo_video_url' => $promoVideoUrl,
    'promo_link_url' => $promoLinkUrl,
    'promo_link_label' => $promoLinkLabel,
    'currency' => $currency !== '' ? $currency : 'USD',
    'legacy_pdf_url' => $legacyPdfUrl,
    'modern_pdf_url' => $modernPdfUrl,
    'notes' => $notes,
    'catalog_json_path' => $catalogJsonPath,
    'api_payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
]);

$catalog = fetch_catalog_by_slug($slug);
$saasBlock = saas_build_response_block($saasContext);
if ($saasRequested) {
    saas_log_publish_attempt(db(), $saasContext, [
        'endpoint' => 'publish_catalog.php',
        'catalog_slug' => $slug,
        'catalog_title' => $title,
        'publish_url' => $publicUrl,
    ]);
}

$response = [
    'ok' => true,
    'catalog' => [
        'id' => $catalog['id'],
        'slug' => $catalog['slug'],
        'title' => $catalog['title'],
        'public_url' => $catalog['public_url'],
        'expires_at' => $catalog['expires_at'],
        'status' => resolve_catalog_status($catalog),
    ],
];
if ($saasRequested && ($saasContext['mode'] ?? 'legacy') !== 'legacy') {
    $response['saas'] = $saasBlock;
}
json_response($response);
