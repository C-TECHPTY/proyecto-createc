<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/footer.php';
require_once dirname(__DIR__) . '/includes/company_context.php';

sa_require_login();

if (!sa_table_exists('sa_company_domains')) {
    sa_header('Dominios', 'company_domains.php');
    ?>
    <section class="panel">
        <h3>Migracion pendiente</h3>
        <p class="muted">Ejecuta primero <code>database/20260508_super_admin_connect_companies.sql</code> para habilitar dominios SaaS.</p>
    </section>
    <?php
    sa_footer();
    exit;
}

$selectedCompanyId = (int) ($_GET['company_id'] ?? ($_POST['company_id'] ?? 0));
$companies = sa_company_options();
$selectedCompany = $selectedCompanyId > 0 ? sa_company_by_id($selectedCompanyId) : null;
$errors = [];
$values = [
    'domain' => '',
    'type' => 'subdomain',
    'status' => 'pending',
    'is_primary' => 1,
    'dns_target' => '',
    'ssl_status' => 'pending',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    sa_verify_csrf();
    $action = sa_post_string('action', 20);
    $companyId = (int) ($_POST['company_id'] ?? 0);
    $domainId = (int) ($_POST['domain_id'] ?? 0);

    if ($action === 'delete' && $domainId > 0) {
        $row = sa_db()->prepare('SELECT company_id, domain FROM sa_company_domains WHERE id = :id LIMIT 1');
        $row->execute(['id' => $domainId]);
        $domain = $row->fetch();
        if ($domain) {
            sa_db()->prepare('DELETE FROM sa_company_domains WHERE id = :id')->execute(['id' => $domainId]);
            sa_log('company_domain.deleted', 'Dominio eliminado: ' . $domain['domain'], (int) $domain['company_id']);
            sa_flash_set('success', 'Dominio eliminado.');
            sa_redirect('company_domains.php?company_id=' . (int) $domain['company_id']);
        }
    }

    $values = [
        'domain' => sa_normalize_host(sa_post_string('domain', 190)),
        'type' => sa_post_string('type', 30),
        'status' => sa_post_string('status', 30),
        'is_primary' => isset($_POST['is_primary']) ? 1 : 0,
        'dns_target' => sa_post_string('dns_target', 190),
        'ssl_status' => sa_post_string('ssl_status', 40),
    ];

    if (!sa_company_by_id($companyId)) {
        $errors[] = 'Selecciona una empresa valida.';
    }
    if ($values['domain'] === '') {
        $errors[] = 'El dominio es obligatorio.';
    }
    if (!in_array($values['type'], ['subdomain', 'custom_domain'], true)) {
        $values['type'] = 'subdomain';
    }
    if (!in_array($values['status'], ['pending', 'active', 'failed', 'disabled'], true)) {
        $values['status'] = 'pending';
    }
    if ($values['ssl_status'] === '') {
        $values['ssl_status'] = 'pending';
    }

    $duplicate = sa_db()->prepare('SELECT id FROM sa_company_domains WHERE domain = :domain AND id <> :id LIMIT 1');
    $duplicate->execute(['domain' => $values['domain'], 'id' => $domainId]);
    if ($duplicate->fetch()) {
        $errors[] = 'Ese dominio ya esta registrado en otra empresa.';
    }

    if (!$errors) {
        $payload = $values + ['company_id' => $companyId, 'id' => $domainId];
        if ((int) $values['is_primary'] === 1) {
            sa_db()->prepare('UPDATE sa_company_domains SET is_primary = 0 WHERE company_id = :company_id')->execute([
                'company_id' => $companyId,
            ]);
        }

        if ($domainId > 0) {
            sa_db()->prepare(
                'UPDATE sa_company_domains
                 SET domain = :domain, type = :type, status = :status, is_primary = :is_primary,
                     dns_target = :dns_target, ssl_status = :ssl_status
                 WHERE id = :id AND company_id = :company_id'
            )->execute($payload);
            sa_log('company_domain.updated', 'Dominio actualizado: ' . $values['domain'], $companyId);
        } else {
            unset($payload['id']);
            sa_db()->prepare(
                'INSERT INTO sa_company_domains
                 (company_id, domain, type, status, is_primary, dns_target, ssl_status)
                 VALUES
                 (:company_id, :domain, :type, :status, :is_primary, :dns_target, :ssl_status)'
            )->execute($payload);
            sa_log('company_domain.created', 'Dominio creado: ' . $values['domain'], $companyId);
        }

        sa_flash_set('success', 'Dominio guardado correctamente.');
        sa_redirect('company_domains.php?company_id=' . $companyId);
    }
}

$editDomainId = (int) ($_GET['domain_id'] ?? 0);
if ($editDomainId > 0) {
    $statement = sa_db()->prepare('SELECT * FROM sa_company_domains WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $editDomainId]);
    $editDomain = $statement->fetch();
    if ($editDomain) {
        $values = $editDomain;
        $selectedCompanyId = (int) $editDomain['company_id'];
        $selectedCompany = sa_company_by_id($selectedCompanyId);
    }
}

$domains = [];
if ($selectedCompanyId > 0) {
    $statement = sa_db()->prepare('SELECT * FROM sa_company_domains WHERE company_id = :company_id ORDER BY is_primary DESC, id DESC');
    $statement->execute(['company_id' => $selectedCompanyId]);
    $domains = $statement->fetchAll();
}

sa_header('Dominios', 'company_domains.php');
?>
<section class="panel">
    <div class="toolbar">
        <div>
            <h3>Dominios por empresa</h3>
            <p class="muted">Preparacion SaaS. Todavia no cambia rutas de catalogos, pedidos ni publicacion legacy.</p>
        </div>
        <a class="button button--ghost" href="companies.php">Empresas</a>
    </div>
    <?php if ($errors): ?><div class="flash flash--error"><?= sa_e(implode(' ', $errors)) ?></div><?php endif; ?>
    <form method="get" class="form-grid" style="margin-bottom:16px;">
        <label class="field">Empresa
            <select name="company_id" onchange="this.form.submit()">
                <option value="0">Seleccionar empresa</option>
                <?php foreach ($companies as $company): ?>
                    <option value="<?= (int) $company['id'] ?>" <?= $selectedCompanyId === (int) $company['id'] ? 'selected' : '' ?>><?= sa_e($company['company_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </form>
    <?php if ($selectedCompany): ?>
        <form method="post">
            <?= sa_csrf_field() ?>
            <input type="hidden" name="company_id" value="<?= (int) $selectedCompanyId ?>">
            <input type="hidden" name="domain_id" value="<?= (int) ($values['id'] ?? 0) ?>">
            <div class="form-grid">
                <label class="field">Dominio <input name="domain" value="<?= sa_e($values['domain'] ?? '') ?>" placeholder="cliente1.createcpty.com" required></label>
                <label class="field">Tipo
                    <select name="type">
                        <?php foreach (['subdomain' => 'Subdominio SaaS', 'custom_domain' => 'Dominio propio'] as $value => $label): ?>
                            <option value="<?= sa_e($value) ?>" <?= ($values['type'] ?? '') === $value ? 'selected' : '' ?>><?= sa_e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field">Estado
                    <select name="status">
                        <?php foreach (['pending' => 'Pendiente', 'active' => 'Activo', 'failed' => 'Fallido', 'disabled' => 'Deshabilitado'] as $value => $label): ?>
                            <option value="<?= sa_e($value) ?>" <?= ($values['status'] ?? '') === $value ? 'selected' : '' ?>><?= sa_e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field">DNS target <input name="dns_target" value="<?= sa_e($values['dns_target'] ?? '') ?>" placeholder="createcpty.com"></label>
                <label class="field">SSL <input name="ssl_status" value="<?= sa_e($values['ssl_status'] ?? 'pending') ?>" placeholder="pending"></label>
                <label class="field"><span>Principal</span><label><input type="checkbox" name="is_primary" value="1" <?= (int) ($values['is_primary'] ?? 0) === 1 ? 'checked' : '' ?>> Usar como dominio principal</label></label>
            </div>
            <div class="actions" style="margin-top:16px;">
                <button class="button" type="submit">Guardar dominio</button>
                <a class="button button--ghost" href="company_domains.php?company_id=<?= (int) $selectedCompanyId ?>">Nuevo</a>
            </div>
        </form>
    <?php endif; ?>
</section>

<?php if ($selectedCompany): ?>
<section class="panel">
    <h3>Dominios registrados para <?= sa_e($selectedCompany['company_name']) ?></h3>
    <table>
        <thead><tr><th>Dominio</th><th>Tipo</th><th>Estado</th><th>DNS / SSL</th><th>Acciones</th></tr></thead>
        <tbody>
        <?php foreach ($domains as $domain): ?>
            <tr>
                <td><strong><?= sa_e($domain['domain']) ?></strong><br><span class="muted"><?= (int) $domain['is_primary'] === 1 ? 'Principal' : '' ?></span></td>
                <td><?= sa_e($domain['type']) ?></td>
                <td><?= sa_badge((string) $domain['status']) ?></td>
                <td><?= sa_e($domain['dns_target'] ?: '-') ?><br><span class="muted"><?= sa_e($domain['ssl_status']) ?></span></td>
                <td>
                    <div class="actions">
                        <a class="button button--ghost" href="company_domains.php?company_id=<?= (int) $selectedCompanyId ?>&domain_id=<?= (int) $domain['id'] ?>">Editar</a>
                        <form method="post" onsubmit="return confirm('Eliminar este dominio?');">
                            <?= sa_csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="domain_id" value="<?= (int) $domain['id'] ?>">
                            <button class="button button--danger" type="submit">Eliminar</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$domains): ?><tr><td colspan="5">Sin dominios registrados.</td></tr><?php endif; ?>
        </tbody>
    </table>
</section>
<?php endif; ?>
<?php sa_footer(); ?>
