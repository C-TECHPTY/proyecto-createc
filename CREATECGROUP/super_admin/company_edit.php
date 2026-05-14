<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/footer.php';

sa_require_login();

$companyId = (int) ($_GET['id'] ?? 0);
$company = sa_company_by_id($companyId);
if (!$company) {
    http_response_code(404);
    echo 'Empresa no encontrada.';
    exit;
}

$errors = [];
$values = $company;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    sa_verify_csrf();
    if (sa_post_string('action', 20) === 'delete') {
        sa_log('company.deleted', 'Empresa eliminada: ' . $company['company_name'], $companyId);
        sa_db()->prepare('DELETE FROM sa_companies WHERE id = :id')->execute(['id' => $companyId]);
        sa_flash_set('success', 'Empresa eliminada correctamente.');
        sa_redirect('companies.php');
    }

    foreach (['company_name','legal_name','slug','contact_name','contact_email','contact_phone','domain','subdomain','logo_url','primary_color','status','storage_mode','notes'] as $key) {
        $values[$key] = sa_post_string($key, $key === 'notes' ? 5000 : 500);
    }
    $values['plan_id'] = sa_post_int('plan_id') ?: null;
    $values['expires_at'] = sa_post_date_or_null('expires_at');
    $values['max_catalogs'] = sa_post_int('max_catalogs');
    $values['max_sellers'] = sa_post_int('max_sellers');
    $values['max_products'] = sa_post_int('max_products');
    $values['slug'] = sa_slugify($values['slug'] !== '' ? $values['slug'] : $values['company_name']);
    if ($values['company_name'] === '') {
        $errors[] = 'El nombre de empresa es obligatorio.';
    }
    if (!in_array($values['status'], ['active', 'suspended', 'inactive'], true)) {
        $values['status'] = 'active';
    }
    if (!in_array($values['storage_mode'], ['hosting', 'backblaze', 'hybrid'], true)) {
        $values['storage_mode'] = 'hosting';
    }
    $statement = sa_db()->prepare('SELECT COUNT(*) FROM sa_companies WHERE slug = :slug AND id <> :id');
    $statement->execute(['slug' => $values['slug'], 'id' => $companyId]);
    if ((int) $statement->fetchColumn() > 0) {
        $errors[] = 'El slug ya existe. Usa uno diferente.';
    }
    if (!$errors) {
        $fields = ['company_name','slug','contact_name','contact_email','contact_phone','domain','subdomain','logo_url','primary_color','status','notes'];
        foreach (['legal_name','plan_id','expires_at','max_catalogs','max_sellers','max_products','storage_mode'] as $field) {
            if (sa_column_exists('sa_companies', $field)) {
                $fields[] = $field;
            }
        }
        $assignments = array_map(static fn(string $field): string => '`' . $field . '` = :' . $field, $fields);
        $payload = array_intersect_key($values, array_flip($fields));
        $payload['id'] = $companyId;
        sa_db()->prepare(
            'UPDATE sa_companies
             SET ' . implode(', ', $assignments) . '
             WHERE id = :id'
        )->execute($payload);
        sa_log('company.updated', 'Empresa actualizada: ' . $values['company_name'], $companyId);
        sa_flash_set('success', 'Empresa actualizada correctamente.');
        sa_redirect('company_edit.php?id=' . $companyId);
    }
}

$subscription = sa_subscription_by_company($companyId);
$license = sa_license_by_company($companyId);
$primaryDomain = sa_primary_domain_by_company($companyId);

sa_header('Editar empresa', 'companies.php');
?>
<section class="panel">
    <div class="toolbar">
        <div>
            <h3><?= sa_e($company['company_name']) ?></h3>
            <p class="muted">Edicion manual sin integracion al flujo actual.</p>
        </div>
        <a class="button button--ghost" href="companies.php">Empresas</a>
    </div>
    <?php if ($errors): ?><div class="flash flash--error"><?= sa_e(implode(' ', $errors)) ?></div><?php endif; ?>
    <?php require __DIR__ . '/includes/company_form.php'; ?>
    <form method="post" style="margin-top:18px;" onsubmit="return confirm('Eliminar esta empresa del Super Admin? Esta accion no toca catalogos, pedidos ni vendedores actuales.');">
        <?= sa_csrf_field() ?>
        <input type="hidden" name="action" value="delete">
        <button class="button button--danger" type="submit">Eliminar empresa</button>
    </form>
</section>
<section class="grid grid--two">
    <div class="panel">
        <h3>Suscripcion</h3>
        <p class="muted"><?= $subscription ? sa_e($subscription['plan_name'] . ' / ' . $subscription['status']) : 'Sin suscripcion registrada.' ?></p>
        <a class="button button--ghost" href="subscriptions.php?company_id=<?= $companyId ?>">Editar suscripcion</a>
    </div>
    <div class="panel">
        <h3>Licencia</h3>
        <p class="muted"><?= $license ? sa_e($license['license_key'] . ' / vence ' . ($license['expires_at'] ?: 'sin fecha')) : 'Sin licencia registrada.' ?></p>
        <a class="button button--ghost" href="licenses.php?company_id=<?= $companyId ?>">Editar licencia</a>
    </div>
    <div class="panel">
        <h3>Dominios</h3>
        <p class="muted"><?= $primaryDomain ? sa_e($primaryDomain['domain'] . ' / ' . $primaryDomain['status']) : 'Sin dominio SaaS registrado.' ?></p>
        <a class="button button--ghost" href="company_domains.php?company_id=<?= $companyId ?>">Ver dominios</a>
    </div>
</section>
<?php sa_footer(); ?>
