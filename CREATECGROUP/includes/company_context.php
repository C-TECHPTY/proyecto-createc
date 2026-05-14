<?php
declare(strict_types=1);

if (!function_exists('sa_normalize_host')) {
    function sa_normalize_host(?string $host): string
    {
        $host = strtolower(trim((string) $host));
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;
        $host = trim($host, ". \t\n\r\0\x0B");

        if (function_exists('idn_to_ascii') && $host !== '') {
            $converted = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
            if (is_string($converted) && $converted !== '') {
                $host = strtolower($converted);
            }
        }

        return $host;
    }
}

if (!function_exists('sa_company_context_table_exists')) {
    function sa_company_context_table_exists(PDO $pdo, string $tableName): bool
    {
        $statement = $pdo->prepare(
            'SELECT COUNT(*)
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
        );
        $statement->execute(['table_name' => $tableName]);
        return ((int) $statement->fetchColumn()) > 0;
    }
}

if (!function_exists('sa_company_context_legacy_result')) {
    function sa_company_context_legacy_result(string $host): array
    {
        return [
            'mode' => 'legacy',
            'legacy' => true,
            'host' => $host,
            'company' => null,
            'status' => 'legacy',
            'allowed' => true,
            'message' => 'No se detecto empresa SaaS para este dominio; se mantiene modo legacy.',
        ];
    }
}

if (!function_exists('sa_company_context_result')) {
    function sa_company_context_result(array $company, string $host, string $source): array
    {
        $status = strtolower((string) ($company['status'] ?? 'active'));
        $blockedStatuses = ['suspended', 'expired', 'disabled', 'inactive'];
        $allowed = !in_array($status, $blockedStatuses, true);

        return [
            'mode' => 'company',
            'legacy' => false,
            'host' => $host,
            'company' => $company,
            'company_id' => (int) ($company['id'] ?? 0),
            'company_slug' => (string) ($company['slug'] ?? ''),
            'status' => $status,
            'source' => $source,
            'allowed' => $allowed,
            'message' => $allowed
                ? 'Empresa SaaS activa detectada.'
                : 'Empresa SaaS detectada con estado bloqueado: ' . $status,
        ];
    }
}

if (!function_exists('resolve_company_by_host')) {
    function resolve_company_by_host(PDO $pdo, ?string $host = null): array
    {
        $normalizedHost = sa_normalize_host($host ?? ($_SERVER['HTTP_HOST'] ?? ''));
        if ($normalizedHost === '' || !sa_company_context_table_exists($pdo, 'sa_companies')) {
            return sa_company_context_legacy_result($normalizedHost);
        }

        if (sa_company_context_table_exists($pdo, 'sa_company_domains')) {
            $statement = $pdo->prepare(
                'SELECT c.*, d.domain AS matched_domain, d.type AS matched_domain_type,
                        d.status AS domain_status, d.is_primary AS domain_is_primary,
                        d.dns_target, d.ssl_status
                 FROM sa_company_domains d
                 INNER JOIN sa_companies c ON c.id = d.company_id
                 WHERE d.domain = :domain AND d.status <> "disabled"
                 ORDER BY d.is_primary DESC, d.id DESC
                 LIMIT 1'
            );
            $statement->execute(['domain' => $normalizedHost]);
            $company = $statement->fetch(PDO::FETCH_ASSOC);
            if (is_array($company) && $company) {
                return sa_company_context_result($company, $normalizedHost, 'sa_company_domains');
            }
        }

        $statement = $pdo->prepare(
            'SELECT *
             FROM sa_companies
             WHERE LOWER(TRIM(domain)) = :host
                OR LOWER(TRIM(subdomain)) = :host
             LIMIT 1'
        );
        $statement->execute(['host' => $normalizedHost]);
        $company = $statement->fetch(PDO::FETCH_ASSOC);
        if (is_array($company) && $company) {
            return sa_company_context_result($company, $normalizedHost, 'sa_companies.domain');
        }

        $hostParts = explode('.', $normalizedHost);
        $firstLabel = (string) ($hostParts[0] ?? '');
        if ($firstLabel !== '' && count($hostParts) > 2) {
            $statement = $pdo->prepare('SELECT * FROM sa_companies WHERE LOWER(TRIM(subdomain)) = :subdomain LIMIT 1');
            $statement->execute(['subdomain' => $firstLabel]);
            $company = $statement->fetch(PDO::FETCH_ASSOC);
            if (is_array($company) && $company) {
                return sa_company_context_result($company, $normalizedHost, 'sa_companies.subdomain');
            }
        }

        return sa_company_context_legacy_result($normalizedHost);
    }
}

if (!function_exists('get_current_company')) {
    function get_current_company(PDO $pdo): array
    {
        static $context = null;
        if ($context === null) {
            $context = resolve_company_by_host($pdo);
        }
        return $context;
    }
}

if (!function_exists('require_active_company')) {
    function require_active_company(PDO $pdo): array
    {
        $context = get_current_company($pdo);
        if (($context['legacy'] ?? false) || ($context['allowed'] ?? false)) {
            return $context;
        }

        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'status' => $context['status'] ?? 'blocked',
            'message' => $context['message'] ?? 'Empresa no disponible.',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('is_legacy_company_context')) {
    function is_legacy_company_context(PDO $pdo): bool
    {
        $context = get_current_company($pdo);
        return (bool) ($context['legacy'] ?? true);
    }
}
