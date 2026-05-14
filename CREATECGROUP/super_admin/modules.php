<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/footer.php';

sa_require_login();

if (!sa_table_exists('sa_modules')) {
    sa_header('Modulos SaaS', 'modules.php');
    ?>
    <section class="panel">
        <h3>Migracion pendiente</h3>
        <p class="muted">Ejecuta <code>database/20260510_createc_saas_core_structure.sql</code> para habilitar modulos SaaS.</p>
    </section>
    <?php
    sa_footer();
    exit;
}

$errors = [];
$moduleId = (int) ($_GET['id'] ?? 0);
$editingModule = null;
$values = [
    'code' => '',
    'name' => '',
    'description' => '',
    'base_path' => '',
    'status' => 'active',
];

if ($moduleId > 0) {
    $statement = sa_db()->prepare('SELECT * FROM sa_modules WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $moduleId]);
    $editingModule = $statement->fetch() ?: null;
    if ($editingModule) {
        $values = array_replace($values, $editingModule);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    sa_verify_csrf();
    $postedModuleId = (int) ($_POST['module_id'] ?? 0);
    $values = [
        'code' => sa_slugify(sa_post_string('code', 80)),
        'name' => sa_post_string('name', 120),
        'description' => sa_post_string('description', 1000),
        'base_path' => sa_post_string('base_path', 190),
        'status' => sa_post_string('status', 20),
    ];

    if ($values['code'] === '') {
        $errors[] = 'El codigo del modulo es obligatorio.';
    }
    if ($values['name'] === '') {
        $errors[] = 'El nombre del modulo es obligatorio.';
    }
    if (!in_array($values['status'], ['active', 'inactive', 'planned'], true)) {
        $values['status'] = 'active';
    }

    if (!$errors) {
        if ($postedModuleId > 0) {
            sa_db()->prepare(
                'UPDATE sa_modules
                 SET code = :code, name = :name, description = :description, base_path = :base_path, status = :status
                 WHERE id = :id'
            )->execute($values + ['id' => $postedModuleId]);
            sa_log('module.updated', 'Modulo actualizado: ' . $values['code']);
            sa_flash_set('success', 'Modulo actualizado correctamente.');
        } else {
            sa_db()->prepare(
                'INSERT INTO sa_modules (code, name, description, base_path, status)
                 VALUES (:code, :name, :description, :base_path, :status)'
            )->execute($values);
            sa_log('module.created', 'Modulo creado: ' . $values['code']);
            sa_flash_set('success', 'Modulo creado correctamente.');
        }
        sa_redirect('modules.php');
    }
}

$modules = sa_db()->query(
    'SELECT m.*,
            COUNT(DISTINCT cm.company_id) AS assigned_companies,
            COUNT(DISTINCT pi.id) AS project_instances
     FROM sa_modules m
     LEFT JOIN sa_company_modules cm ON cm.module_id = m.id
     LEFT JOIN sa_project_instances pi ON pi.module_id = m.id
     GROUP BY m.id
     ORDER BY FIELD(m.status, "active", "planned", "inactive"), m.name ASC'
)->fetchAll();

sa_header('Modulos SaaS', 'modules.php');
?>
<section class="panel">
    <div class="toolbar">
        <div>
            <h3><?= $editingModule ? 'Editar modulo' : 'Nuevo modulo' ?></h3>
            <p class="muted">Define los productos SaaS que CREATEC puede activar por empresa.</p>
        </div>
        <?php if ($editingModule): ?><a class="button button--ghost" href="modules.php">Nuevo modulo</a><?php endif; ?>
    </div>
    <?php if ($errors): ?><div class="flash flash--error"><?= sa_e(implode(' ', $errors)) ?></div><?php endif; ?>
    <form method="post">
        <?= sa_csrf_field() ?>
        <input type="hidden" name="module_id" value="<?= (int) ($editingModule['id'] ?? 0) ?>">
        <div class="form-grid">
            <label class="field">Codigo <input name="code" value="<?= sa_e($values['code']) ?>" placeholder="catalogos" required></label>
            <label class="field">Nombre <input name="name" value="<?= sa_e($values['name']) ?>" required></label>
            <label class="field">Ruta base <input name="base_path" value="<?= sa_e($values['base_path']) ?>" placeholder="projects/catalogos/"></label>
            <label class="field">Estado
                <select name="status">
                    <?php foreach (['active' => 'Activo', 'planned' => 'Planificado', 'inactive' => 'Inactivo'] as $value => $label): ?>
                        <option value="<?= sa_e($value) ?>" <?= $values['status'] === $value ? 'selected' : '' ?>><?= sa_e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field field--full">Descripcion <textarea name="description"><?= sa_e($values['description']) ?></textarea></label>
        </div>
        <div class="actions" style="margin-top:16px;"><button class="button" type="submit">Guardar modulo</button></div>
    </form>
</section>

<section class="panel">
    <h3>Modulos registrados</h3>
    <table>
        <thead><tr><th>Modulo</th><th>Ruta base</th><th>Empresas</th><th>Instancias</th><th>Estado</th><th>Acciones</th></tr></thead>
        <tbody>
        <?php foreach ($modules as $module): ?>
            <tr>
                <td><strong><?= sa_e($module['name']) ?></strong><br><span class="muted"><?= sa_e($module['code']) ?></span></td>
                <td><?= sa_e($module['base_path'] ?: 'Sin ruta') ?></td>
                <td><?= (int) $module['assigned_companies'] ?></td>
                <td><?= (int) $module['project_instances'] ?></td>
                <td><?= sa_badge((string) $module['status']) ?></td>
                <td><a class="button button--ghost" href="modules.php?id=<?= (int) $module['id'] ?>">Editar</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$modules): ?><tr><td colspan="6">Sin modulos registrados.</td></tr><?php endif; ?>
        </tbody>
    </table>
</section>
<?php sa_footer(); ?>
