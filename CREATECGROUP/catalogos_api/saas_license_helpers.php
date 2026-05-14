<?php
declare(strict_types=1);

function saas_read_json_or_post_input(): array
{
    $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
    $jsonPayload = str_contains($contentType, 'application/json') ? read_json_input() : [];
    return array_merge($jsonPayload, $_POST);
}

function saas_validate_license_context(PDO $pdo, array $input): array
{
    $enabled = filter_var($input['saas_validation_enabled'] ?? $input['saasValidationEnabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $licenseKey = trim((string) ($input['saas_license_key'] ?? $input['license_key'] ?? ''));
    $companySlug = trim((string) ($input['saas_company_slug'] ?? $input['company_slug'] ?? ''));
    $deviceId = trim((string) ($input['saas_device_id'] ?? $input['device_id'] ?? ''));
    $appVersion = trim((string) ($input['saas_app_version'] ?? $input['app_version'] ?? ''));

    $context = [
        'mode' => 'legacy',
        'company_id' => null,
        'company_name' => '',
        'company_slug' => $companySlug !== '' ? $companySlug : null,
        'license_id' => null,
        'license_key_hash' => $licenseKey !== '' ? hash('sha256', $licenseKey) : null,
        'device_id' => $deviceId !== '' ? $deviceId : null,
        'app_version' => $appVersion !== '' ? $appVersion : null,
        'allowed_publish' => true,
        'message' => 'Publicacion legacy sin validacion SaaS.',
        'plan' => '',
        'expires_at' => null,
    ];

    if (!$enabled && $licenseKey === '' && $companySlug === '') {
        return $context;
    }

    $context['mode'] = 'warning';
    $context['message'] = 'Validacion SaaS solicitada, pero faltan datos.';

    if ($licenseKey === '') {
        $context['message'] = 'No se envio licencia SaaS. Publicacion continua en modo legacy.';
        return $context;
    }

    if (!catalog_table_exists('sa_licenses') || !catalog_table_exists('sa_companies')) {
        $context['message'] = 'Tablas SaaS no disponibles. Publicacion continua en modo legacy.';
        return $context;
    }

    $hasPlans = catalog_table_exists('sa_plans') && catalog_column_exists('sa_companies', 'plan_id');
    $hasSubscriptions = catalog_table_exists('sa_subscriptions');
    $planSelect = $hasPlans ? ', p.name AS plan_name_from_plan' : ", '' AS plan_name_from_plan";
    $planJoin = $hasPlans ? ' LEFT JOIN sa_plans p ON p.id = c.plan_id' : '';
    $subscriptionSelect = $hasSubscriptions ? ', s.plan_name AS plan_name_from_subscription, s.end_date AS subscription_end_date' : ", '' AS plan_name_from_subscription, NULL AS subscription_end_date";
    $subscriptionJoin = $hasSubscriptions
        ? ' LEFT JOIN sa_subscriptions s ON s.id = (
            SELECT s2.id FROM sa_subscriptions s2 WHERE s2.company_id = c.id ORDER BY s2.id DESC LIMIT 1
          )'
        : '';

    $sql = 'SELECT l.*, c.company_name, c.slug, c.status AS company_status'
        . (catalog_column_exists('sa_companies', 'expires_at') ? ', c.expires_at AS company_expires_at' : ', NULL AS company_expires_at')
        . $planSelect . $subscriptionSelect .
        ' FROM sa_licenses l
          INNER JOIN sa_companies c ON c.id = l.company_id'
        . $planJoin . $subscriptionJoin .
        ' WHERE l.license_key = :license_key';

    $params = ['license_key' => $licenseKey];
    if ($companySlug !== '') {
        $sql .= ' AND c.slug = :company_slug';
        $params['company_slug'] = $companySlug;
    }
    $sql .= ' LIMIT 1';

    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    $license = $statement->fetch();

    if (!$license) {
        $context['message'] = 'Licencia SaaS no encontrada. Publicacion continua en modo legacy.';
        return $context;
    }

    $companyStatus = strtolower((string) ($license['company_status'] ?? 'active'));
    $licenseStatus = strtolower((string) ($license['status'] ?? 'inactive'));
    $expiresAt = (string) (($license['expires_at'] ?? '') ?: ($license['company_expires_at'] ?? '') ?: ($license['subscription_end_date'] ?? ''));
    $isExpired = $expiresAt !== '' && strtotime($expiresAt . ' 23:59:59') !== false && strtotime($expiresAt . ' 23:59:59') < time();
    $blockedCompany = in_array($companyStatus, ['suspended', 'expired', 'disabled', 'inactive'], true);
    $allowed = $licenseStatus === 'active' && !$blockedCompany && !$isExpired;

    $context['company_id'] = (int) $license['company_id'];
    $context['company_name'] = (string) ($license['company_name'] ?? '');
    $context['company_slug'] = (string) ($license['slug'] ?? $companySlug);
    $context['license_id'] = (int) $license['id'];
    $context['allowed_publish'] = $allowed;
    $context['plan'] = (string) (($license['plan_name_from_plan'] ?? '') ?: ($license['plan_name_from_subscription'] ?? ''));
    $context['expires_at'] = $expiresAt !== '' ? $expiresAt : null;

    if ($allowed) {
        $context['mode'] = 'validated';
        $context['message'] = 'Licencia SaaS validada correctamente.';
        return $context;
    }

    $context['mode'] = 'warning';
    $context['message'] = 'Licencia SaaS o empresa no habilitada. Publicacion continua en modo legacy.';
    if ($blockedCompany) {
        $context['message'] = 'Empresa SaaS con estado ' . $companyStatus . '. Publicacion continua en modo legacy.';
    } elseif ($isExpired) {
        $context['message'] = 'Licencia SaaS vencida. Publicacion continua en modo legacy.';
    } elseif ($licenseStatus !== 'active') {
        $context['message'] = 'Licencia SaaS con estado ' . $licenseStatus . '. Publicacion continua en modo legacy.';
    }
    return $context;
}

function saas_log_publish_attempt(PDO $pdo, array $context, array $publishResult): void
{
    if (!catalog_table_exists('saas_publish_logs')) {
        return;
    }

    $statement = $pdo->prepare(
        'INSERT INTO saas_publish_logs
         (company_id, company_slug, license_id, license_key_hash, device_id, app_version,
          endpoint, catalog_slug, catalog_title, publish_url, status, allowed_publish,
          warning_message, ip_address, user_agent)
         VALUES
         (:company_id, :company_slug, :license_id, :license_key_hash, :device_id, :app_version,
          :endpoint, :catalog_slug, :catalog_title, :publish_url, :status, :allowed_publish,
          :warning_message, :ip_address, :user_agent)'
    );
    $statement->execute([
        'company_id' => $context['company_id'] ?? null,
        'company_slug' => $context['company_slug'] ?? null,
        'license_id' => $context['license_id'] ?? null,
        'license_key_hash' => $context['license_key_hash'] ?? null,
        'device_id' => $context['device_id'] ?? null,
        'app_version' => $context['app_version'] ?? null,
        'endpoint' => $publishResult['endpoint'] ?? '',
        'catalog_slug' => $publishResult['catalog_slug'] ?? null,
        'catalog_title' => $publishResult['catalog_title'] ?? null,
        'publish_url' => $publishResult['publish_url'] ?? null,
        'status' => $context['mode'] ?? 'legacy',
        'allowed_publish' => !empty($context['allowed_publish']) ? 1 : 0,
        'warning_message' => $context['message'] ?? null,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
    ]);
}

function saas_build_response_block(array $context): array
{
    return [
        'mode' => $context['mode'] ?? 'legacy',
        'company_id' => $context['company_id'] ?? null,
        'company_slug' => $context['company_slug'] ?? null,
        'allowed_publish' => (bool) ($context['allowed_publish'] ?? true),
        'message' => $context['message'] ?? 'Publicacion legacy.',
    ];
}
