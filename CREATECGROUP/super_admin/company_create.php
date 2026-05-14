<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/footer.php';

sa_require_login();

$errors = [];
$values = [
    'company_name' => '',
    'legal_name' => '',
    'slug' => '',
    'contact_name' => '',
    'contact_email' => '',
    'contact_phone' => '',
    'domain' => '',
    'subdomain' => '',
    'logo_url' => '',
    'primary_color' => '#0f4c81',
    'plan_id' => 0,
    'expires_at' => '',
    'max_catalogs' => 0,
    'max_sellers' => 0,
    'max_products' => 0,
    'storage_mode' => 'hosting',
    'status' => 'active',
    'notes' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    sa_verify_csrf();
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
    if ($values['slug'] === '') {
        $errors[] = 'El slug es obligatorio.';
    }
    if (!in_array($values['status'], ['active', 'suspended', 'inactive'], true)) {
        $values['status'] = 'active';
    }
    if (!in_array($values['storage_mode'], ['hosting', 'backblaze', 'hybrid'], true)) {
        $values['storage_mode'] = 'hosting';
    }
    $statement = sa_db()->prepare('SELECT COUNT(*) FROM sa_companies WHERE slug = :slug');
    $statement->execute(['slug' => $values['slug']]);
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
        $payload = array_intersect_key($values, array_flip($fields));
        $statement = sa_db()->prepare(
            'INSERT INTO sa_companies (`' . implode('`, `', $fields) . '`) VALUES (:' . implode(', :', $fields) . ')'
        );
        $statement->execute($payload);
        $companyId = (int) sa_db()->lastInsertId();
        sa_log('company.created', 'Empresa creada: ' . $values['company_name'], $companyId);
        sa_flash_set('success', 'Empresa creada correctamente.');
        sa_redirect('company_edit.php?id=' . $companyId);
    }
}

sa_header('Nueva empresa', 'companies.php');
?>
<section class="panel">
    <?php if ($errors): ?><div class="flash flash--error"><?= sa_e(implode(' ', $errors)) ?></div><?php endif; ?>
    <?php require __DIR__ . '/includes/company_form.php'; ?>
</section>
<?php sa_footer(); ?>
