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
$expiresAt = !empty($payload['expires_at']) ? date('Y-m-d H:i:s', strtotime((string) $payload['expires_at'])) : null;
$status = trim((string) ($payload['status'] ?? 'active'));
$zipName = basename(trim((string) ($payload['zip_name'] ?? '')));

if ($slug === '' || $zipName === '') {
    json_response([
        'ok' => false,
        'error' => 'Debes indicar slug y zip_name para publicar el catalogo.',
    ], 422);
}

$catalogsDir = rtrim((string) catalog_config('paths.public_catalogs_dir', dirname(__DIR__) . '/catalogos'), DIRECTORY_SEPARATOR);
$zipPath = $catalogsDir . DIRECTORY_SEPARATOR . $zipName;
$targetDir = $catalogsDir . DIRECTORY_SEPARATOR . $slug;
$extractTempDir = $catalogsDir . DIRECTORY_SEPARATOR . '__extract_' . $slug;
$catalogJsonPath = 'catalogos/' . $slug . '/catalog.json';

if (!is_file($zipPath)) {
    json_response([
        'ok' => false,
        'error' => 'No se encontro el ZIP en el servidor: ' . $zipName,
    ], 404);
}

if (!class_exists('ZipArchive')) {
    json_response([
        'ok' => false,
        'error' => 'ZipArchive no esta disponible en este hosting.',
    ], 500);
}

delete_directory_recursive($targetDir);
delete_directory_recursive($extractTempDir);
if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
    json_response([
        'ok' => false,
        'error' => 'No se pudo crear la carpeta destino del catalogo.',
    ], 500);
}
if (!is_dir($extractTempDir) && !mkdir($extractTempDir, 0775, true) && !is_dir($extractTempDir)) {
    json_response([
        'ok' => false,
        'error' => 'No se pudo crear la carpeta temporal de extraccion.',
    ], 500);
}

$zip = new ZipArchive();
if ($zip->open($zipPath) !== true) {
    json_response([
        'ok' => false,
        'error' => 'No se pudo abrir el ZIP del catalogo.',
    ], 500);
}

if (!$zip->extractTo($extractTempDir)) {
    $zip->close();
    json_response([
        'ok' => false,
        'error' => 'No se pudo extraer el ZIP en la carpeta del catalogo.',
    ], 500);
}
$zip->close();

$resolvedSourceDir = resolve_extracted_catalog_root($extractTempDir);
move_directory_contents($resolvedSourceDir, $targetDir);
delete_directory_recursive($extractTempDir);

$indexPath = $targetDir . DIRECTORY_SEPARATOR . 'index.html';
$jsonPath = $targetDir . DIRECTORY_SEPARATOR . 'catalog.json';

if (!is_file($indexPath) || !is_file($jsonPath)) {
    json_response([
        'ok' => false,
        'error' => 'El ZIP extraido no contiene index.html y catalog.json validos.',
    ], 422);
}

$catalogJsonPayload = json_decode((string) file_get_contents($jsonPath), true);
if (!is_array($catalogJsonPayload)) {
    $catalogJsonPayload = [];
}
$storedPayload = array_replace_recursive($catalogJsonPayload, $payload);

file_put_contents(
    $targetDir . DIRECTORY_SEPARATOR . '.htaccess',
    "<Files \"catalog.json\">\n    Require all denied\n</Files>\n"
);

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
    'api_payload' => json_encode($storedPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
]);

$catalog = fetch_catalog_by_slug($slug);
$saasBlock = saas_build_response_block($saasContext);
if ($saasRequested) {
    saas_log_publish_attempt(db(), $saasContext, [
        'endpoint' => 'publish_uploaded_zip.php',
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
        'zip_name' => $zipName,
    ],
];
if ($saasRequested && ($saasContext['mode'] ?? 'legacy') !== 'legacy') {
    $response['saas'] = $saasBlock;
}
json_response($response);

function delete_directory_recursive(string $dirPath): void
{
    if (!is_dir($dirPath)) {
        return;
    }

    $items = scandir($dirPath);
    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $fullPath = $dirPath . DIRECTORY_SEPARATOR . $item;
        if (is_dir($fullPath)) {
            delete_directory_recursive($fullPath);
            continue;
        }
        @unlink($fullPath);
    }

    @rmdir($dirPath);
}

function resolve_extracted_catalog_root(string $extractTempDir): string
{
    if (is_file($extractTempDir . DIRECTORY_SEPARATOR . 'index.html') && is_file($extractTempDir . DIRECTORY_SEPARATOR . 'catalog.json')) {
        return $extractTempDir;
    }

    $items = scandir($extractTempDir);
    if ($items === false) {
        return $extractTempDir;
    }

    $children = [];
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $children[] = $extractTempDir . DIRECTORY_SEPARATOR . $item;
    }

    if (count($children) === 1 && is_dir($children[0])) {
        $candidate = $children[0];
        if (is_file($candidate . DIRECTORY_SEPARATOR . 'index.html') && is_file($candidate . DIRECTORY_SEPARATOR . 'catalog.json')) {
            return $candidate;
        }
    }

    return $extractTempDir;
}

function move_directory_contents(string $sourceDir, string $targetDir): void
{
    $items = scandir($sourceDir);
    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $sourcePath = $sourceDir . DIRECTORY_SEPARATOR . $item;
        $targetPath = $targetDir . DIRECTORY_SEPARATOR . $item;

        if (file_exists($targetPath)) {
            if (is_dir($targetPath)) {
                delete_directory_recursive($targetPath);
            } else {
                @unlink($targetPath);
            }
        }

        @rename($sourcePath, $targetPath);
    }
}
