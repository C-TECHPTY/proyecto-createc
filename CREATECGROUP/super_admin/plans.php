<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/footer.php';

sa_require_login();

if (!sa_table_exists('sa_plans')) {
    sa_header('Planes SaaS', 'plans.php');
    ?>
    <section class="panel">
        <h3>Migracion pendiente</h3>
        <p class="muted">Ejecuta primero <code>database/20260508_super_admin_connect_companies.sql</code> para habilitar planes SaaS.</p>
    </section>
    <?php
    sa_footer();
    exit;
}

$errors = [];
$planId = (int) ($_GET['id'] ?? 0);
$editingPlan = null;
$values = [
    'name' => '',
    'monthly_price' => '0.00',
    'yearly_price' => '0.00',
    'max_catalogs' => 0,
    'max_sellers' => 0,
    'max_products' => 0,
    'allow_custom_domain' => 0,
    'allow_backblaze' => 0,
    'allow_campaigns' => 0,
    'allow_ai' => 0,
    'status' => 'active',
];

if ($planId > 0) {
    $statement = sa_db()->prepare('SELECT * FROM sa_plans WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $planId]);
    $editingPlan = $statement->fetch() ?: null;
    if ($editingPlan) {
        $values = array_replace($values, $editingPlan);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    sa_verify_csrf();
    $action = sa_post_string('action', 20);
    $postedPlanId = (int) ($_POST['plan_id'] ?? 0);

    if ($action === 'toggle' && $postedPlanId > 0) {
        $statement = sa_db()->prepare('SELECT status, name FROM sa_plans WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $postedPlanId]);
        $plan = $statement->fetch();
        if ($plan) {
            $nextStatus = ((string) $plan['status']) === 'active' ? 'inactive' : 'active';
            sa_db()->prepare('UPDATE sa_plans SET status = :status WHERE id = :id')->execute([
                'status' => $nextStatus,
                'id' => $postedPlanId,
            ]);
            sa_log('plan.status_updated', 'Plan ' . $plan['name'] . ' actualizado a ' . $nextStatus);
            sa_flash_set('success', 'Estado del plan actualizado.');
        }
        sa_redirect('plans.php');
    }

    $values = [
        'name' => sa_post_string('name', 120),
        'monthly_price' => sa_post_decimal('monthly_price'),
        'yearly_price' => sa_post_decimal('yearly_price'),
        'max_catalogs' => sa_post_int('max_catalogs'),
        'max_sellers' => sa_post_int('max_sellers'),
        'max_products' => sa_post_int('max_products'),
        'allow_custom_domain' => isset($_POST['allow_custom_domain']) ? 1 : 0,
        'allow_backblaze' => isset($_POST['allow_backblaze']) ? 1 : 0,
        'allow_campaigns' => isset($_POST['allow_campaigns']) ? 1 : 0,
        'allow_ai' => isset($_POST['allow_ai']) ? 1 : 0,
        'status' => sa_post_string('status', 20),
    ];

    if ($values['name'] === '') {
        $errors[] = 'El nombre del plan es obligatorio.';
    }
    if (!in_array($values['status'], ['active', 'inactive'], true)) {
        $values['status'] = 'active';
    }

    if (!$errors) {
        if ($postedPlanId > 0) {
            $payload = $values + ['id' => $postedPlanId];
            sa_db()->prepare(
                'UPDATE sa_plans
                 SET name = :name, monthly_price = :monthly_price, yearly_price = :yearly_price,
                     max_catalogs = :max_catalogs, max_sellers = :max_sellers, max_products = :max_products,
                     allow_custom_domain = :allow_custom_domain, allow_backblaze = :allow_backblaze,
                     allow_campaigns = :allow_campaigns, allow_ai = :allow_ai, status = :status
                 WHERE id = :id'
            )->execute($payload);
            sa_log('plan.updated', 'Plan actualizado: ' . $values['name']);
            sa_flash_set('success', 'Plan actualizado correctamente.');
        } else {
            sa_db()->prepare(
                'INSERT INTO sa_plans
                 (name, monthly_price, yearly_price, max_catalogs, max_sellers, max_products,
                  allow_custom_domain, allow_backblaze, allow_campaigns, allow_ai, status)
                 VALUES
                 (:name, :monthly_price, :yearly_price, :max_catalogs, :max_sellers, :max_products,
                  :allow_custom_domain, :allow_backblaze, :allow_campaigns, :allow_ai, :status)'
            )->execute($values);
            sa_log('plan.created', 'Plan creado: ' . $values['name']);
            sa_flash_set('success', 'Plan creado correctamente.');
        }
        sa_redirect('plans.php');
    }
}

$plans = sa_db()->query('SELECT * FROM sa_plans ORDER BY status = "active" DESC, name ASC')->fetchAll();

sa_header('Planes SaaS', 'plans.php');
?>
<section class="panel">
    <div class="toolbar">
        <div>
            <h3><?= $editingPlan ? 'Editar plan' : 'Nuevo plan' ?></h3>
            <p class="muted">Los planes preparan limites SaaS. Todavia no bloquean proyectos CREATEC.</p>
        </div>
        <?php if ($editingPlan): ?><a class="button button--ghost" href="plans.php">Nuevo plan</a><?php endif; ?>
    </div>
    <?php if ($errors): ?><div class="flash flash--error"><?= sa_e(implode(' ', $errors)) ?></div><?php endif; ?>
    <form method="post">
        <?= sa_csrf_field() ?>
        <input type="hidden" name="plan_id" value="<?= (int) ($editingPlan['id'] ?? 0) ?>">
        <div class="form-grid">
            <label class="field">Nombre <input name="name" value="<?= sa_e($values['name'] ?? '') ?>" required></label>
            <label class="field">Estado
                <select name="status">
                    <?php foreach (['active' => 'Activo', 'inactive' => 'Inactivo'] as $value => $label): ?>
                        <option value="<?= sa_e($value) ?>" <?= ($values['status'] ?? '') === $value ? 'selected' : '' ?>><?= sa_e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field">Precio mensual <input name="monthly_price" value="<?= sa_e($values['monthly_price'] ?? '0.00') ?>"></label>
            <label class="field">Precio anual <input name="yearly_price" value="<?= sa_e($values['yearly_price'] ?? '0.00') ?>"></label>
            <label class="field">Max catalogos <input type="number" min="0" name="max_catalogs" value="<?= sa_e($values['max_catalogs'] ?? 0) ?>"></label>
            <label class="field">Max vendedores <input type="number" min="0" name="max_sellers" value="<?= sa_e($values['max_sellers'] ?? 0) ?>"></label>
            <label class="field">Max productos <input type="number" min="0" name="max_products" value="<?= sa_e($values['max_products'] ?? 0) ?>"></label>
            <div class="field">
                <span>Permisos</span>
                <label><input type="checkbox" name="allow_custom_domain" value="1" <?= (int) ($values['allow_custom_domain'] ?? 0) === 1 ? 'checked' : '' ?>> Dominio propio</label>
                <label><input type="checkbox" name="allow_backblaze" value="1" <?= (int) ($values['allow_backblaze'] ?? 0) === 1 ? 'checked' : '' ?>> Backblaze/CDN</label>
                <label><input type="checkbox" name="allow_campaigns" value="1" <?= (int) ($values['allow_campaigns'] ?? 0) === 1 ? 'checked' : '' ?>> Campanas</label>
                <label><input type="checkbox" name="allow_ai" value="1" <?= (int) ($values['allow_ai'] ?? 0) === 1 ? 'checked' : '' ?>> IA/OpenClaw</label>
            </div>
        </div>
        <div class="actions" style="margin-top:16px;"><button class="button" type="submit">Guardar plan</button></div>
    </form>
</section>

<section class="panel">
    <h3>Planes registrados</h3>
    <table>
        <thead><tr><th>Plan</th><th>Precio</th><th>Limites</th><th>Permisos</th><th>Estado</th><th>Acciones</th></tr></thead>
        <tbody>
        <?php foreach ($plans as $plan): ?>
            <tr>
                <td><strong><?= sa_e($plan['name']) ?></strong></td>
                <td><?= sa_e('$' . $plan['monthly_price'] . ' / $' . $plan['yearly_price']) ?></td>
                <td><?= sa_e('Cat ' . $plan['max_catalogs'] . ' / Vend ' . $plan['max_sellers'] . ' / Prod ' . $plan['max_products']) ?></td>
                <td>
                    <?= (int) $plan['allow_custom_domain'] === 1 ? sa_badge('active') . ' dominio ' : '' ?>
                    <?= (int) $plan['allow_backblaze'] === 1 ? sa_badge('active') . ' B2 ' : '' ?>
                    <?= (int) $plan['allow_campaigns'] === 1 ? sa_badge('active') . ' campanas ' : '' ?>
                    <?= (int) $plan['allow_ai'] === 1 ? sa_badge('active') . ' IA ' : '' ?>
                </td>
                <td><?= sa_badge((string) $plan['status']) ?></td>
                <td>
                    <div class="actions">
                        <a class="button button--ghost" href="plans.php?id=<?= (int) $plan['id'] ?>">Editar</a>
                        <form method="post">
                            <?= sa_csrf_field() ?>
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="plan_id" value="<?= (int) $plan['id'] ?>">
                            <button class="button <?= $plan['status'] === 'active' ? 'button--danger' : 'button--ghost' ?>" type="submit">
                                <?= $plan['status'] === 'active' ? 'Desactivar' : 'Activar' ?>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$plans): ?><tr><td colspan="6">Sin planes registrados.</td></tr><?php endif; ?>
        </tbody>
    </table>
</section>
<?php sa_footer(); ?>
