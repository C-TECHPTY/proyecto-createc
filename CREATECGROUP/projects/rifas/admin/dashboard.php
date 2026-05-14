<?php
require_once __DIR__ . '/includes/auth.php';
$admin = require_admin();
$pdo = db();

$stats = [
    'active' => (int) $pdo->query("SELECT COUNT(*) FROM raffles WHERE status = 'active'")->fetchColumn(),
    'pending' => (int) $pdo->query("SELECT COUNT(*) FROM reservations WHERE status = 'pending'")->fetchColumn(),
    'paid' => (int) $pdo->query("SELECT COUNT(*) FROM reservations WHERE status = 'paid'")->fetchColumn(),
    'sold' => (float) $pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM reservations WHERE status = 'paid'")->fetchColumn(),
    'pending_total' => (float) $pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM reservations WHERE status = 'pending'")->fetchColumn(),
];

$recent = $pdo->query("
    SELECT r.id, r.status, r.total_amount, r.created_at, c.name, c.whatsapp, ra.title
    FROM reservations r
    JOIN customers c ON c.id = r.customer_id
    JOIN raffles ra ON ra.id = r.raffle_id
    ORDER BY r.created_at DESC
    LIMIT 8
")->fetchAll();

$pageTitle = 'Dashboard';
require __DIR__ . '/includes/header.php';
?>
<section class="metric-grid">
    <article><span>Rifas activas</span><strong><?= $stats['active'] ?></strong></article>
    <article><span>Total confirmado</span><strong><?= money_fmt($stats['sold']) ?></strong></article>
    <article><span>Total pendiente</span><strong><?= money_fmt($stats['pending_total']) ?></strong></article>
    <article><span>Reservas pendientes</span><strong><?= $stats['pending'] ?></strong></article>
    <article><span>Pagos confirmados</span><strong><?= $stats['paid'] ?></strong></article>
</section>

<section class="panel">
    <div class="panel-heading">
        <h2>Reservas recientes</h2>
        <a class="button" href="rifa_create.php">Crear rifa</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Cliente</th><th>Rifa</th><th>Total</th><th>Estado</th><th>Fecha</th></tr></thead>
            <tbody>
            <?php foreach ($recent as $row): ?>
                <tr>
                    <td><?= e($row['name']) ?><br><small><?= e($row['whatsapp']) ?></small></td>
                    <td><?= e($row['title']) ?></td>
                    <td><?= money_fmt($row['total_amount']) ?></td>
                    <td><span class="status status-<?= e($row['status']) ?>"><?= e($row['status']) ?></span></td>
                    <td><?= e($row['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>

