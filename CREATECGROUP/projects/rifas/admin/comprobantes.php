<?php
require_once __DIR__ . '/includes/auth.php';
$admin = require_admin();
$receipts = db()->query("
    SELECT pr.*, r.status reservation_status, r.total_amount, c.name, c.whatsapp, ra.title,
      nums.numbers
    FROM payment_receipts pr
    JOIN reservations r ON r.id = pr.reservation_id
    JOIN customers c ON c.id = r.customer_id
    JOIN raffles ra ON ra.id = r.raffle_id
    LEFT JOIN (
      SELECT rrn.reservation_id, GROUP_CONCAT(rn.display_number ORDER BY rn.number_value SEPARATOR ', ') numbers
      FROM reservation_numbers rrn
      JOIN raffle_numbers rn ON rn.id = rrn.raffle_number_id
      GROUP BY rrn.reservation_id
    ) nums ON nums.reservation_id = r.id
    ORDER BY pr.created_at DESC
    LIMIT 150
")->fetchAll();
$pageTitle = 'Comprobantes';
require __DIR__ . '/includes/header.php';
?>
<section class="panel">
    <div class="panel-heading"><h2>Comprobantes recibidos</h2></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Cliente</th><th>Rifa</th><th>Números</th><th>Total</th><th>Archivo</th><th>Estado</th><th>Acción</th></tr></thead>
            <tbody>
            <?php foreach ($receipts as $receipt): ?>
                <tr>
                    <td><?= e($receipt['name']) ?><br><small><?= e($receipt['whatsapp']) ?></small></td>
                    <td><?= e($receipt['title']) ?><br><small><?= e($receipt['created_at']) ?></small></td>
                    <td><?= e($receipt['numbers'] ?? '') ?></td>
                    <td><?= money_fmt($receipt['total_amount']) ?></td>
                    <td><a href="../public/<?= e($receipt['file_path']) ?>" target="_blank"><?= e($receipt['original_name']) ?></a></td>
                    <td><span class="status status-<?= e($receipt['status']) ?>"><?= e($receipt['status']) ?></span><br><small>Reserva: <?= e($receipt['reservation_status']) ?></small></td>
                    <td class="actions">
                        <?php if ($receipt['reservation_status'] === 'pending'): ?>
                            <button class="button js-confirm-payment" data-id="<?= (int) $receipt['reservation_id'] ?>" data-csrf="<?= e(csrf_token()) ?>">Confirmar pago</button>
                        <?php else: ?>
                            <span class="muted">Revisado</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
