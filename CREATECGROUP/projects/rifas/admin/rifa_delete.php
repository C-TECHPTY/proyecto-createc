<?php
require_once __DIR__ . '/includes/auth.php';

$admin = require_admin();
$pdo = db();
$id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT ra.*,
      COALESCE(stats.reservations_count, 0) reservations_count,
      COALESCE(stats.paid_count, 0) paid_count,
      COALESCE(stats.receipt_count, 0) receipt_count
    FROM raffles ra
    LEFT JOIN (
      SELECT r.raffle_id,
        COUNT(*) reservations_count,
        SUM(CASE WHEN r.status = 'paid' THEN 1 ELSE 0 END) paid_count,
        COUNT(pr.id) receipt_count
      FROM reservations r
      LEFT JOIN payment_receipts pr ON pr.reservation_id = r.id
      GROUP BY r.raffle_id
    ) stats ON stats.raffle_id = ra.id
    WHERE ra.id = ?
");
$stmt->execute([$id]);
$raffle = $stmt->fetch();

if (!$raffle) {
    $_SESSION['flash'] = 'Rifa no encontrada.';
    redirect('rifas.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $_SESSION['flash'] = 'Solicitud inválida.';
        redirect('rifas.php');
    }

    $confirm = trim($_POST['confirm'] ?? '');
    if ($confirm !== 'ELIMINAR') {
        $_SESSION['flash'] = 'Para eliminar debes escribir ELIMINAR en el campo de confirmación.';
        redirect('rifa_delete.php?id=' . $id);
    }

    audit_log($pdo, 'raffle_deleted', 'raffle', $id, ['title' => $raffle['title']]);
    $delete = $pdo->prepare('DELETE FROM raffles WHERE id = ?');
    $delete->execute([$id]);

    $_SESSION['flash'] = 'Rifa eliminada. Las reservas/números relacionados también se eliminaron.';
    redirect('rifas.php');
}

$pageTitle = 'Eliminar rifa';
require __DIR__ . '/includes/header.php';
?>
<section class="panel danger-panel">
    <div class="panel-heading">
        <h2>Eliminar rifa</h2>
        <a class="button button-ghost" href="rifas.php">Volver</a>
    </div>

    <div class="delete-warning">
        <strong><?= e($raffle['title']) ?></strong>
        <p>Esta acción eliminará la rifa y sus datos relacionados. No se puede deshacer.</p>
        <ul>
            <li>Estado: <?= e($raffle['status']) ?></li>
            <li>Reservas: <?= (int) $raffle['reservations_count'] ?></li>
            <li>Pagadas: <?= (int) $raffle['paid_count'] ?></li>
            <li>Comprobantes: <?= (int) $raffle['receipt_count'] ?></li>
        </ul>
    </div>

    <form class="admin-form" method="post">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= (int) $raffle['id'] ?>">
        <label>Escribe ELIMINAR para confirmar
            <input type="text" name="confirm" autocomplete="off" required>
        </label>
        <div class="actions">
            <button class="button danger-button" type="submit">Eliminar definitivamente</button>
            <a class="button button-ghost" href="rifas.php">Cancelar</a>
        </div>
    </form>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
