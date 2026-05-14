<?php
require_once __DIR__ . '/includes/auth.php';
$admin = require_admin();
$raffles = db()->query("
    SELECT ra.*,
      COALESCE(stats.sold_numbers, 0) sold_numbers,
      COALESCE(stats.available_numbers, 0) available_numbers,
      COALESCE(stats.reserved_numbers, 0) reserved_numbers
    FROM raffles ra
    LEFT JOIN (
      SELECT raffle_id,
        SUM(CASE WHEN status = 'sold' THEN 1 ELSE 0 END) sold_numbers,
        SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) available_numbers,
        SUM(CASE WHEN status = 'reserved' THEN 1 ELSE 0 END) reserved_numbers
      FROM raffle_numbers
      GROUP BY raffle_id
    ) stats ON stats.raffle_id = ra.id
    ORDER BY ra.created_at DESC
")->fetchAll();
$pageTitle = 'Rifas';
require __DIR__ . '/includes/header.php';
?>
<section class="panel">
    <div class="panel-heading">
        <h2>Gestión de rifas</h2>
        <a class="button" href="rifa_create.php">Nueva rifa</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Rifa</th><th>Precio</th><th>Estado</th><th>Números</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($raffles as $raffle): ?>
                <tr>
                    <td><strong><?= e($raffle['title']) ?></strong><br><small><?= e($raffle['slug']) ?></small></td>
                    <td><?= money_fmt($raffle['price_per_number']) ?></td>
                    <td><span class="status status-<?= e($raffle['status']) ?>"><?= e($raffle['status']) ?></span></td>
                    <td><?= (int) $raffle['sold_numbers'] ?> vendidos / <?= (int) $raffle['reserved_numbers'] ?> reservados / <?= (int) $raffle['available_numbers'] ?> libres</td>
                    <td class="actions">
                        <a href="rifa_edit.php?id=<?= (int) $raffle['id'] ?>">Editar</a>
                        <a href="rifa_numbers.php?id=<?= (int) $raffle['id'] ?>">Grilla</a>
                        <a href="../public/rifa.php?slug=<?= e($raffle['slug']) ?>" target="_blank">Ver</a>
                        <form method="post" action="rifa_duplicate.php" class="inline-action">
                            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="id" value="<?= (int) $raffle['id'] ?>">
                            <button type="submit">Renovar</button>
                        </form>
                        <a class="danger-link" href="rifa_delete.php?id=<?= (int) $raffle['id'] ?>">Eliminar</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
