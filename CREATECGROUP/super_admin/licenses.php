<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/footer.php';

sa_require_login();

$selectedCompanyId = (int) ($_GET['company_id'] ?? ($_POST['company_id'] ?? 0));
$companies = sa_company_options();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    sa_verify_csrf();
    $companyId = (int) ($_POST['company_id'] ?? 0);
    $licenseKey = sa_post_string('license_key', 120);
    if ($licenseKey === '') {
        $licenseKey = 'RI-' . strtoupper(bin2hex(random_bytes(8)));
    }
    $payload = [
        'company_id' => $companyId,
        'license_key' => $licenseKey,
        'status' => sa_post_string('status', 20),
        'max_catalogs' => sa_post_int('max_catalogs'),
        'max_vendors' => sa_post_int('max_vendors'),
        'max_products' => sa_post_int('max_products'),
        'expires_at' => sa_post_date_or_null('expires_at'),
    ];
    if (!sa_company_by_id($companyId)) {
        $errors[] = 'Selecciona una empresa valida.';
    }
    if (!in_array($payload['status'], ['active', 'inactive', 'expired', 'revoked'], true)) {
        $payload['status'] = 'active';
    }
    $existingForKey = sa_db()->prepare('SELECT id FROM sa_licenses WHERE license_key = :license_key AND company_id <> :company_id LIMIT 1');
    $existingForKey->execute(['license_key' => $payload['license_key'], 'company_id' => $companyId]);
    if ($existingForKey->fetch()) {
        $errors[] = 'La clave de licencia ya existe.';
    }
    if (!$errors) {
        $existing = sa_license_by_company($companyId);
        if ($existing) {
            $payload['id'] = (int) $existing['id'];
            sa_db()->prepare(
                'UPDATE sa_licenses
                 SET license_key = :license_key, status = :status, max_catalogs = :max_catalogs,
                     max_vendors = :max_vendors, max_products = :max_products, expires_at = :expires_at
                 WHERE id = :id AND company_id = :company_id'
            )->execute($payload);
            sa_log('license.updated', 'Licencia actualizada: ' . $payload['license_key'], $companyId);
        } else {
            sa_db()->prepare(
                'INSERT INTO sa_licenses
                 (company_id, license_key, status, max_catalogs, max_vendors, max_products, expires_at)
                 VALUES
                 (:company_id, :license_key, :status, :max_catalogs, :max_vendors, :max_products, :expires_at)'
            )->execute($payload);
            sa_log('license.created', 'Licencia creada: ' . $payload['license_key'], $companyId);
        }
        sa_flash_set('success', 'Licencia guardada correctamente.');
        sa_redirect('licenses.php?company_id=' . $companyId);
    }
}

$license = $selectedCompanyId > 0 ? sa_license_by_company($selectedCompanyId) : null;
$values = $license ?: [
    'company_id' => $selectedCompanyId,
    'license_key' => '',
    'status' => 'active',
    'max_catalogs' => 0,
    'max_vendors' => 0,
    'max_products' => 0,
    'expires_at' => '',
];
$rows = sa_db()->query(
    'SELECT l.*, c.company_name
     FROM sa_licenses l
     INNER JOIN sa_companies c ON c.id = l.company_id
     ORDER BY l.updated_at DESC'
)->fetchAll();

sa_header('Licencias', 'licenses.php');
?>
<section class="panel">
    <div class="toolbar">
        <div><h3>Licencia manual</h3><p class="muted">Define limites futuros sin aplicarlos todavia al sistema actual.</p></div>
    </div>
    <?php if ($errors): ?><div class="flash flash--error"><?= sa_e(implode(' ', $errors)) ?></div><?php endif; ?>
    <form method="post">
        <?= sa_csrf_field() ?>
        <div class="form-grid">
            <label class="field">Empresa
                <select name="company_id" required>
                    <option value="">Seleccionar</option>
                    <?php foreach ($companies as $company): ?>
                        <option value="<?= (int) $company['id'] ?>" <?= (int) ($values['company_id'] ?? 0) === (int) $company['id'] ? 'selected' : '' ?>><?= sa_e($company['company_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field">Clave <input name="license_key" value="<?= sa_e($values['license_key']) ?>" placeholder="Se genera automaticamente si queda vacia"></label>
            <label class="field">Estado
                <select name="status">
                    <?php foreach (['active' => 'Activo', 'inactive' => 'Inactivo', 'expired' => 'Expirado', 'revoked' => 'Revocado'] as $value => $label): ?>
                        <option value="<?= sa_e($value) ?>" <?= ($values['status'] ?? '') === $value ? 'selected' : '' ?>><?= sa_e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field">Vence <input type="date" name="expires_at" value="<?= sa_e($values['expires_at']) ?>"></label>
            <label class="field">Max catalogos <input type="number" min="0" name="max_catalogs" value="<?= sa_e($values['max_catalogs']) ?>"></label>
            <label class="field">Max vendedores <input type="number" min="0" name="max_vendors" value="<?= sa_e($values['max_vendors']) ?>"></label>
            <label class="field">Max productos <input type="number" min="0" name="max_products" value="<?= sa_e($values['max_products']) ?>"></label>
        </div>
        <div class="actions" style="margin-top:16px;"><button class="button" type="submit">Guardar licencia</button></div>
    </form>
</section>
<section class="panel">
    <h3>Licencias registradas</h3>
    <table>
        <thead><tr><th>Empresa</th><th>Clave</th><th>Estado</th><th>Limites</th><th>Vence</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
            <tr>
                <td><?= sa_e($row['company_name']) ?></td>
                <td><?= sa_e($row['license_key']) ?></td>
                <td><?= sa_badge((string) $row['status']) ?></td>
                <td><?= sa_e('Cat ' . $row['max_catalogs'] . ' / Vend ' . $row['max_vendors'] . ' / Prod ' . $row['max_products']) ?></td>
                <td><?= sa_e($row['expires_at'] ?: '-') ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="5">Sin licencias.</td></tr><?php endif; ?>
        </tbody>
    </table>
</section>
<?php sa_footer(); ?>
