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
    $payload = [
        'company_id' => $companyId,
        'plan_name' => sa_post_string('plan_name', 120),
        'billing_cycle' => sa_post_string('billing_cycle', 20),
        'monthly_price' => sa_post_decimal('monthly_price'),
        'annual_price' => sa_post_decimal('annual_price'),
        'start_date' => sa_post_date_or_null('start_date'),
        'end_date' => sa_post_date_or_null('end_date'),
        'status' => sa_post_string('status', 20),
    ];
    if (!sa_company_by_id($companyId)) {
        $errors[] = 'Selecciona una empresa valida.';
    }
    if ($payload['plan_name'] === '') {
        $errors[] = 'El plan es obligatorio.';
    }
    if (!in_array($payload['billing_cycle'], ['monthly', 'annual', 'manual'], true)) {
        $payload['billing_cycle'] = 'monthly';
    }
    if (!in_array($payload['status'], ['active', 'pending', 'expired', 'cancelled'], true)) {
        $payload['status'] = 'active';
    }
    if (!$errors) {
        $existing = sa_subscription_by_company($companyId);
        if ($existing) {
            $payload['id'] = (int) $existing['id'];
            sa_db()->prepare(
                'UPDATE sa_subscriptions
                 SET plan_name = :plan_name, billing_cycle = :billing_cycle, monthly_price = :monthly_price,
                     annual_price = :annual_price, start_date = :start_date, end_date = :end_date, status = :status
                 WHERE id = :id AND company_id = :company_id'
            )->execute($payload);
            sa_log('subscription.updated', 'Suscripcion actualizada: ' . $payload['plan_name'], $companyId);
        } else {
            sa_db()->prepare(
                'INSERT INTO sa_subscriptions
                 (company_id, plan_name, billing_cycle, monthly_price, annual_price, start_date, end_date, status)
                 VALUES
                 (:company_id, :plan_name, :billing_cycle, :monthly_price, :annual_price, :start_date, :end_date, :status)'
            )->execute($payload);
            sa_log('subscription.created', 'Suscripcion creada: ' . $payload['plan_name'], $companyId);
        }
        sa_flash_set('success', 'Suscripcion guardada correctamente.');
        sa_redirect('subscriptions.php?company_id=' . $companyId);
    }
}

$subscription = $selectedCompanyId > 0 ? sa_subscription_by_company($selectedCompanyId) : null;
$values = $subscription ?: [
    'company_id' => $selectedCompanyId,
    'plan_name' => '',
    'billing_cycle' => 'monthly',
    'monthly_price' => '0.00',
    'annual_price' => '0.00',
    'start_date' => date('Y-m-d'),
    'end_date' => '',
    'status' => 'active',
];
$rows = sa_db()->query(
    'SELECT s.*, c.company_name
     FROM sa_subscriptions s
     INNER JOIN sa_companies c ON c.id = s.company_id
     ORDER BY s.updated_at DESC'
)->fetchAll();

sa_header('Suscripciones', 'subscriptions.php');
?>
<section class="panel">
    <div class="toolbar">
        <div><h3>Suscripcion manual</h3><p class="muted">Solo registro administrativo. No ejecuta cobros ni suspensiones.</p></div>
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
            <label class="field">Plan <input name="plan_name" value="<?= sa_e($values['plan_name']) ?>" required></label>
            <label class="field">Ciclo
                <select name="billing_cycle">
                    <?php foreach (['monthly' => 'Mensual', 'annual' => 'Anual', 'manual' => 'Manual'] as $value => $label): ?>
                        <option value="<?= sa_e($value) ?>" <?= ($values['billing_cycle'] ?? '') === $value ? 'selected' : '' ?>><?= sa_e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field">Estado
                <select name="status">
                    <?php foreach (['active' => 'Activo', 'pending' => 'Pendiente', 'expired' => 'Expirado', 'cancelled' => 'Cancelado'] as $value => $label): ?>
                        <option value="<?= sa_e($value) ?>" <?= ($values['status'] ?? '') === $value ? 'selected' : '' ?>><?= sa_e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field">Precio mensual <input name="monthly_price" value="<?= sa_e($values['monthly_price']) ?>"></label>
            <label class="field">Precio anual <input name="annual_price" value="<?= sa_e($values['annual_price']) ?>"></label>
            <label class="field">Inicio <input type="date" name="start_date" value="<?= sa_e($values['start_date']) ?>"></label>
            <label class="field">Fin <input type="date" name="end_date" value="<?= sa_e($values['end_date']) ?>"></label>
        </div>
        <div class="actions" style="margin-top:16px;"><button class="button" type="submit">Guardar suscripcion</button></div>
    </form>
</section>
<section class="panel">
    <h3>Suscripciones registradas</h3>
    <table>
        <thead><tr><th>Empresa</th><th>Plan</th><th>Ciclo</th><th>Estado</th><th>Vigencia</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
            <tr>
                <td><?= sa_e($row['company_name']) ?></td>
                <td><?= sa_e($row['plan_name']) ?></td>
                <td><?= sa_e($row['billing_cycle']) ?></td>
                <td><?= sa_badge((string) $row['status']) ?></td>
                <td><?= sa_e(($row['start_date'] ?: '-') . ' / ' . ($row['end_date'] ?: '-')) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="5">Sin suscripciones.</td></tr><?php endif; ?>
        </tbody>
    </table>
</section>
<?php sa_footer(); ?>
