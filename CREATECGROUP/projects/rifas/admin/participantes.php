<?php
require_once __DIR__ . '/includes/auth.php';
$admin = require_admin();
$pdo = db();

$participants = $pdo->query("
    SELECT c.id, c.name, c.whatsapp, c.email, c.created_at,
      COALESCE(reservations.reservations_count, 0) reservations_count,
      COALESCE(reservations.confirmed_total, 0) confirmed_total,
      COALESCE(reservations.pending_total, 0) pending_total,
      COALESCE(points.points, 0) points,
      reservations.last_reservation_at
    FROM customers c
    LEFT JOIN (
      SELECT customer_id,
        COUNT(*) reservations_count,
        SUM(CASE WHEN status = 'paid' THEN total_amount ELSE 0 END) confirmed_total,
        SUM(CASE WHEN status = 'pending' THEN total_amount ELSE 0 END) pending_total,
        MAX(created_at) last_reservation_at
      FROM reservations
      GROUP BY customer_id
    ) reservations ON reservations.customer_id = c.id
    LEFT JOIN (
      SELECT customer_id, SUM(points) points
      FROM loyalty_points
      GROUP BY customer_id
    ) points ON points.customer_id = c.id
    ORDER BY last_reservation_at DESC, c.created_at DESC
    LIMIT 200
")->fetchAll();

$pageTitle = 'Participantes';
require __DIR__ . '/includes/header.php';
?>
<section class="panel">
    <div class="panel-heading"><h2>Participantes</h2></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Cliente</th><th>Confirmado</th><th>Pendiente</th><th>Puntos</th><th>Reservas</th><th>Última reserva</th><th>Contacto</th></tr></thead>
            <tbody>
            <?php foreach ($participants as $participant): ?>
                <?php $waUrl = 'https://wa.me/' . normalize_phone($participant['whatsapp']); ?>
                <tr>
                    <td><?= e($participant['name']) ?><br><small><?= e($participant['email'] ?? '') ?></small></td>
                    <td><?= money_fmt($participant['confirmed_total']) ?></td>
                    <td><?= money_fmt($participant['pending_total']) ?></td>
                    <td><?= (int) $participant['points'] ?></td>
                    <td><?= (int) $participant['reservations_count'] ?></td>
                    <td><?= e($participant['last_reservation_at'] ?? '-') ?></td>
                    <td><a class="button button-ghost" target="_blank" href="<?= e($waUrl) ?>">WhatsApp</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (!$participants): ?><p class="muted">Aún no hay participantes.</p><?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
