<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/saas_license_helpers.php';

$payload = saas_read_json_or_post_input();
$licenseKey = trim((string) ($payload['license_key'] ?? ''));

if ($licenseKey === '') {
    json_response([
        'success' => false,
        'company_id' => null,
        'company_name' => '',
        'status' => 'missing_license',
        'plan' => '',
        'expires_at' => null,
        'allowed_publish' => false,
        'message' => 'Debes enviar license_key.',
    ], 422);
}

$context = saas_validate_license_context(db(), [
    'saas_validation_enabled' => true,
    'saas_license_key' => $licenseKey,
    'saas_company_slug' => $payload['company_slug'] ?? '',
    'saas_device_id' => $payload['device_id'] ?? '',
    'saas_app_version' => $payload['app_version'] ?? '',
]);
$block = saas_build_response_block($context);

json_response([
    'success' => $context['mode'] === 'validated',
    'company_id' => $context['company_id'],
    'company_name' => $context['company_name'] ?? '',
    'status' => $context['mode'] === 'validated' ? 'active' : 'warning',
    'plan' => $context['plan'] ?? '',
    'expires_at' => $context['expires_at'] ?? null,
    'allowed_publish' => (bool) $context['allowed_publish'],
    'message' => $context['message'],
    'device_id' => $context['device_id'] ?? '',
    'app_version' => $context['app_version'] ?? '',
    'saas' => $block,
]);
