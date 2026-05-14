<?php
require_once __DIR__ . '/includes/auth.php';
$admin = require_admin();
$pdo = db();

$customers = $pdo->query("
    SELECT c.id, c.name, c.whatsapp, c.email,
      COALESCE(points.points, 0) points,
      COALESCE(reservations.reservations_count, 0) reservations_count,
      COALESCE(reservations.total_paid, 0) total_paid
    FROM customers c
    LEFT JOIN (
      SELECT customer_id, SUM(points) points
      FROM loyalty_points
      GROUP BY customer_id
    ) points ON points.customer_id = c.id
    LEFT JOIN (
      SELECT customer_id, COUNT(*) reservations_count, SUM(CASE WHEN status = 'paid' THEN total_amount ELSE 0 END) total_paid
      FROM reservations
      GROUP BY customer_id
    ) reservations ON reservations.customer_id = c.id
    ORDER BY COALESCE(points.points, 0) DESC, c.name
")->fetchAll();

$raffles = $pdo->query("SELECT id, title, points_for_free_number FROM raffles WHERE status = 'active' ORDER BY title")->fetchAll();
$history = $pdo->query("
    SELECT lp.*, c.name, c.whatsapp, ra.title raffle_title
    FROM loyalty_points lp
    JOIN customers c ON c.id = lp.customer_id
    LEFT JOIN raffles ra ON ra.id = lp.raffle_id
    ORDER BY lp.created_at DESC
    LIMIT 100
")->fetchAll();

$pageTitle = 'Puntos';
require __DIR__ . '/includes/header.php';
?>
<section class="panel">
    <div class="panel-heading"><h2>Fidelidad y números gratis</h2></div>
    <form id="freeNumberForm" class="admin-form compact-form">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <div class="form-grid">
            <label>Cliente
                <select name="customer_id" required>
                    <option value="">Seleccionar</option>
                    <?php foreach ($customers as $customer): ?>
                        <option value="<?= (int) $customer['id'] ?>"><?= e($customer['name']) ?> - <?= e($customer['whatsapp']) ?> (<?= (int) $customer['points'] ?> pts)</option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Rifa activa
                <select name="raffle_id" required>
                    <option value="">Seleccionar</option>
                    <?php foreach ($raffles as $raffle): ?>
                        <option value="<?= (int) $raffle['id'] ?>"><?= e($raffle['title']) ?> - requiere <?= (int) $raffle['points_for_free_number'] ?> pts</option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Número gratis <input name="number_value" type="number" min="0" required placeholder="Ej: 24"></label>
        </div>
        <button class="button" type="submit">Aplicar número gratis</button>
        <div class="reserve-result" id="freeNumberResult"></div>
    </form>
</section>

<section class="panel">
    <div class="panel-heading"><h2>Clientes con puntos</h2></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Cliente</th><th>Puntos</th><th>Total pagado</th><th>Reservas</th></tr></thead>
            <tbody>
            <?php foreach ($customers as $customer): ?>
                <tr>
                    <td><?= e($customer['name']) ?><br><small><?= e($customer['whatsapp']) ?> <?= e($customer['email'] ?? '') ?></small></td>
                    <td><strong><?= (int) $customer['points'] ?></strong></td>
                    <td><?= money_fmt($customer['total_paid']) ?></td>
                    <td><?= (int) $customer['reservations_count'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <div class="panel-heading"><h2>Historial de puntos</h2></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Cliente</th><th>Rifa</th><th>Puntos</th><th>Razón</th><th>Fecha</th></tr></thead>
            <tbody>
            <?php foreach ($history as $row): ?>
                <tr>
                    <td><?= e($row['name']) ?><br><small><?= e($row['whatsapp']) ?></small></td>
                    <td><?= e($row['raffle_title'] ?? '-') ?></td>
                    <td><?= (int) $row['points'] ?></td>
                    <td><?= e($row['reason']) ?></td>
                    <td><?= e($row['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
