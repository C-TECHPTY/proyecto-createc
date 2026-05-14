<?php
require_once __DIR__ . '/includes/auth.php';
$admin = require_admin();
$pdo = db();
$status = $_GET['status'] ?? '';
$selectedReservationId = (int) ($_GET['reservation_id'] ?? 0);
$whereParts = [];
$params = [];
if ($status !== '') {
    $whereParts[] = 'r.status = ?';
    $params[] = $status;
}
if ($selectedReservationId > 0) {
    $whereParts[] = 'r.id = ?';
    $params[] = $selectedReservationId;
}
$where = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';
$stmt = $pdo->prepare("
    SELECT r.*, c.name, c.whatsapp, c.email, ra.title,
      nums.numbers,
      COALESCE(receipts.receipt_count, 0) receipt_count
    FROM reservations r
    JOIN customers c ON c.id = r.customer_id
    JOIN raffles ra ON ra.id = r.raffle_id
    LEFT JOIN (
      SELECT rrn.reservation_id, GROUP_CONCAT(rn.display_number ORDER BY rn.number_value SEPARATOR ', ') numbers
      FROM reservation_numbers rrn
      JOIN raffle_numbers rn ON rn.id = rrn.raffle_number_id
      GROUP BY rrn.reservation_id
    ) nums ON nums.reservation_id = r.id
    LEFT JOIN (
      SELECT reservation_id, COUNT(*) receipt_count
      FROM payment_receipts
      GROUP BY reservation_id
    ) receipts ON receipts.reservation_id = r.id
    $where
    ORDER BY r.created_at DESC
    LIMIT 150
");
$stmt->execute($params);
$reservations = $stmt->fetchAll();
$pageTitle = 'Reservas';
require __DIR__ . '/includes/header.php';
?>
<section class="panel">
    <div class="panel-heading">
        <h2>Reservas</h2>
        <div class="actions">
            <a href="reservas.php">Todas</a>
            <a href="reservas.php?status=pending">Pendientes</a>
            <a href="reservas.php?status=paid">Pagadas</a>
            <a href="reservas.php?status=cancelled">Canceladas</a>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Cliente</th><th>Rifa</th><th>Números</th><th>Total</th><th>Estado</th><th>Comprobante</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($reservations as $row): ?>
                <tr class="<?= (int) $row['id'] === $selectedReservationId ? 'is-highlighted' : '' ?>" data-reservation-row="<?= (int) $row['id'] ?>">
                    <td><?= e($row['name']) ?><br><small><?= e($row['whatsapp']) ?></small></td>
                    <td><?= e($row['title']) ?><br><small><?= e($row['created_at']) ?></small></td>
                    <td><?= e($row['numbers'] ?? '') ?></td>
                    <td><?= money_fmt($row['total_amount']) ?><br><small><?= e($row['payment_method']) ?></small></td>
                    <td><span class="status status-<?= e($row['status']) ?>"><?= e($row['status']) ?></span></td>
                    <td><?= (int) $row['receipt_count'] ?> archivo(s)</td>
                    <td class="actions">
                        <?php if ($row['status'] === 'pending'): ?>
                            <button class="button js-confirm-payment" data-id="<?= (int) $row['id'] ?>" data-csrf="<?= e(csrf_token()) ?>">Confirmar</button>
                            <button class="button button-ghost js-cancel-reservation" data-id="<?= (int) $row['id'] ?>" data-csrf="<?= e(csrf_token()) ?>">Cancelar</button>
                        <?php else: ?>
                            <span class="muted">Sin acciones</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
