<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/footer.php';

sa_require_login();

if (!sa_table_exists('sa_project_instances') || !sa_table_exists('sa_modules')) {
    sa_header('Instancias SaaS', 'project_instances.php');
    ?>
    <section class="panel">
        <h3>Migracion pendiente</h3>
        <p class="muted">Ejecuta <code>database/20260510_createc_saas_core_structure.sql</code> para habilitar instancias de proyecto.</p>
    </section>
    <?php
    sa_footer();
    exit;
}

$errors = [];
$values = [
    'company_id' => 0,
    'module_id' => 0,
    'instance_key' => '',
    'project_path' => '',
    'database_name' => '',
    'domain' => '',
    'subdomain' => '',
    'status' => 'active',
    'notes' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    sa_verify_csrf();
    $values = [
        'company_id' => (int) ($_POST['company_id'] ?? 0),
        'module_id' => (int) ($_POST['module_id'] ?? 0),
        'instance_key' => sa_slugify(sa_post_string('instance_key', 120)),
        'project_path' => sa_post_string('project_path', 255),
        'database_name' => sa_post_string('database_name', 190),
        'domain' => sa_post_string('domain', 190),
        'subdomain' => sa_post_string('subdomain', 190),
        'status' => sa_post_string('status', 20),
        'notes' => sa_post_string('notes', 1000),
    ];

    if ($values['company_id'] <= 0) {
        $errors[] = 'Selecciona una empresa.';
    }
    if ($values['module_id'] <= 0) {
        $errors[] = 'Selecciona un modulo.';
    }
    if ($values['instance_key'] === '') {
        $errors[] = 'La clave de instancia es obligatoria.';
    }
    if ($values['project_path'] === '') {
        $errors[] = 'La ruta del proyecto es obligatoria.';
    }
    if (!in_array($values['status'], ['active', 'maintenance', 'disabled'], true)) {
        $values['status'] = 'active';
    }

    if (!$errors) {
        sa_db()->prepare(
            'INSERT INTO sa_project_instances
             (company_id, module_id, instance_key, project_path, database_name, domain, subdomain, status, notes)
             VALUES
             (:company_id, :module_id, :instance_key, :project_path, :database_name, :domain, :subdomain, :status, :notes)'
        )->execute($values);

        sa_db()->prepare(
            'INSERT IGNORE INTO sa_company_modules (company_id, module_id, status)
             VALUES (:company_id, :module_id, "active")'
        )->execute([
            'company_id' => $values['company_id'],
            'module_id' => $values['module_id'],
        ]);

        sa_log('project_instance.created', 'Instancia creada: ' . $values['instance_key'], $values['company_id']);
        sa_flash_set('success', 'Instancia de proyecto creada correctamente.');
        sa_redirect('project_instances.php');
    }
}

$companies = sa_company_options();
$modules = sa_db()->query('SELECT id, code, name, base_path FROM sa_modules ORDER BY name ASC')->fetchAll();
$instances = sa_db()->query(
    'SELECT pi.*, c.company_name, c.slug AS company_slug, m.name AS module_name, m.code AS module_code
     FROM sa_project_instances pi
     INNER JOIN sa_companies c ON c.id = pi.company_id
     INNER JOIN sa_modules m ON m.id = pi.module_id
     ORDER BY pi.created_at DESC'
)->fetchAll();

sa_header('Instancias SaaS', 'project_instances.php');
?>
<section class="panel">
    <div class="toolbar">
        <div>
            <h3>Asignar proyecto a empresa</h3>
            <p class="muted">Registra que empresa usa que modulo, ruta, dominio/subdominio y base/instancia asignada.</p>
        </div>
    </div>
    <?php if ($errors): ?><div class="flash flash--error"><?= sa_e(implode(' ', $errors)) ?></div><?php endif; ?>
    <form method="post">
        <?= sa_csrf_field() ?>
        <div class="form-grid">
            <label class="field">Empresa
                <select name="company_id" required>
                    <option value="">Selecciona...</option>
                    <?php foreach ($companies as $company): ?>
                        <option value="<?= (int) $company['id'] ?>" <?= (int) $values['company_id'] === (int) $company['id'] ? 'selected' : '' ?>>
                            <?= sa_e($company['company_name'] . ' (' . $company['slug'] . ')') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field">Modulo
                <select name="module_id" required>
                    <option value="">Selecciona...</option>
                    <?php foreach ($modules as $module): ?>
                        <option value="<?= (int) $module['id'] ?>" <?= (int) $values['module_id'] === (int) $module['id'] ? 'selected' : '' ?>>
                            <?= sa_e($module['name'] . ' (' . $module['code'] . ')') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field">Clave instancia <input name="instance_key" value="<?= sa_e($values['instance_key']) ?>" placeholder="cliente1-catalogos" required></label>
            <label class="field">Ruta proyecto <input name="project_path" value="<?= sa_e($values['project_path']) ?>" placeholder="projects/catalogos/" required></label>
            <label class="field">Base/instancia DB <input name="database_name" value="<?= sa_e($values['database_name']) ?>" placeholder="createc_saas"></label>
            <label class="field">Estado
                <select name="status">
                    <?php foreach (['active' => 'Activo', 'maintenance' => 'Mantenimiento', 'disabled' => 'Deshabilitado'] as $value => $label): ?>
                        <option value="<?= sa_e($value) ?>" <?= $values['status'] === $value ? 'selected' : '' ?>><?= sa_e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field">Dominio <input name="domain" value="<?= sa_e($values['domain']) ?>" placeholder="cliente.com"></label>
            <label class="field">Subdominio <input name="subdomain" value="<?= sa_e($values['subdomain']) ?>" placeholder="cliente1.createcpty.com"></label>
            <label class="field field--full">Notas <textarea name="notes"><?= sa_e($values['notes']) ?></textarea></label>
        </div>
        <div class="actions" style="margin-top:16px;"><button class="button" type="submit">Guardar instancia</button></div>
    </form>
</section>

<section class="panel">
    <h3>Instancias registradas</h3>
    <table>
        <thead><tr><th>Empresa</th><th>Modulo</th><th>Instancia</th><th>Ruta</th><th>Dominio</th><th>DB</th><th>Estado</th></tr></thead>
        <tbody>
        <?php foreach ($instances as $instance): ?>
            <tr>
                <td><strong><?= sa_e($instance['company_name']) ?></strong><br><span class="muted"><?= sa_e($instance['company_slug']) ?></span></td>
                <td><?= sa_e($instance['module_name']) ?><br><span class="muted"><?= sa_e($instance['module_code']) ?></span></td>
                <td><?= sa_e($instance['instance_key']) ?></td>
                <td><?= sa_e($instance['project_path']) ?></td>
                <td><?= sa_e($instance['domain'] ?: $instance['subdomain']) ?></td>
                <td><?= sa_e($instance['database_name']) ?></td>
                <td><?= sa_badge((string) $instance['status']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$instances): ?><tr><td colspan="7">Sin instancias registradas.</td></tr><?php endif; ?>
        </tbody>
    </table>
</section>
<?php sa_footer(); ?>
