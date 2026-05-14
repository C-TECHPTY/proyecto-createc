<?php
require_once __DIR__ . '/includes/auth.php';
$admin = require_admin();
$payments = db()->query("
    SELECT p.*, r.status reservation_status, c.name, c.whatsapp, ra.title
    FROM payments p
    JOIN reservations r ON r.id = p.reservation_id
    JOIN customers c ON c.id = r.customer_id
    JOIN raffles ra ON ra.id = r.raffle_id
    ORDER BY p.created_at DESC
    LIMIT 150
")->fetchAll();
$pageTitle = 'Pagos';
require __DIR__ . '/includes/header.php';
?>
<section class="panel">
    <div class="panel-heading"><h2>Pagos manuales</h2></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Cliente</th><th>Rifa</th><th>Método</th><th>Monto</th><th>Pago</th><th>Reserva</th><th>Confirmado</th></tr></thead>
            <tbody>
            <?php foreach ($payments as $payment): ?>
                <tr>
                    <td><?= e($payment['name']) ?><br><small><?= e($payment['whatsapp']) ?></small></td>
                    <td><?= e($payment['title']) ?></td>
                    <td><?= e($payment['method']) ?></td>
                    <td><?= money_fmt($payment['amount']) ?></td>
                    <td><span class="status status-<?= e($payment['status']) ?>"><?= e($payment['status']) ?></span></td>
                    <td><span class="status status-<?= e($payment['reservation_status']) ?>"><?= e($payment['reservation_status']) ?></span></td>
                    <td><?= e($payment['confirmed_at'] ?? '-') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
