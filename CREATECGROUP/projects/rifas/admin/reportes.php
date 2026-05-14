<?php
require_once __DIR__ . '/includes/auth.php';
$admin = require_admin();
$pdo = db();

$raffleId = (int) ($_GET['raffle_id'] ?? 0);
$raffles = $pdo->query('SELECT id, title FROM raffles ORDER BY created_at DESC')->fetchAll();
$where = $raffleId > 0 ? 'WHERE ra.id = ?' : '';
$params = $raffleId > 0 ? [$raffleId] : [];

$summaryStmt = $pdo->prepare("
    SELECT ra.id, ra.title, ra.item_cost,
      COALESCE(reservations.confirmed_total, 0) confirmed_total,
      COALESCE(reservations.pending_total, 0) pending_total,
      COALESCE(reservations.paid_reservations, 0) paid_reservations,
      COALESCE(reservations.pending_reservations, 0) pending_reservations,
      COALESCE(numbers.sold_numbers, 0) sold_numbers,
      COALESCE(numbers.reserved_numbers, 0) reserved_numbers,
      COALESCE(numbers.available_numbers, 0) available_numbers
    FROM raffles ra
    LEFT JOIN (
      SELECT raffle_id,
        SUM(CASE WHEN status = 'paid' THEN total_amount ELSE 0 END) confirmed_total,
        SUM(CASE WHEN status = 'pending' THEN total_amount ELSE 0 END) pending_total,
        SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) paid_reservations,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) pending_reservations
      FROM reservations
      GROUP BY raffle_id
    ) reservations ON reservations.raffle_id = ra.id
    LEFT JOIN (
      SELECT raffle_id,
        SUM(CASE WHEN status IN ('sold','winner') THEN 1 ELSE 0 END) sold_numbers,
        SUM(CASE WHEN status = 'reserved' THEN 1 ELSE 0 END) reserved_numbers,
        SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) available_numbers
      FROM raffle_numbers
      GROUP BY raffle_id
    ) numbers ON numbers.raffle_id = ra.id
    $where
    ORDER BY ra.created_at DESC
");
$summaryStmt->execute($params);
$summaries = $summaryStmt->fetchAll();

$paymentSql = "
    SELECT r.payment_method, COUNT(*) count_rows, COALESCE(SUM(r.total_amount), 0) total
    FROM reservations r
    JOIN raffles ra ON ra.id = r.raffle_id
    $where
    GROUP BY r.payment_method
    ORDER BY total DESC
";
$paymentStmt = $pdo->prepare($paymentSql);
$paymentStmt->execute($params);
$paymentMethods = $paymentStmt->fetchAll();

$numbersSql = "
    SELECT rn.display_number, COUNT(*) times_bought
    FROM raffle_numbers rn
    JOIN raffles ra ON ra.id = rn.raffle_id
    WHERE rn.status IN ('sold','winner')" . ($raffleId > 0 ? ' AND ra.id = ?' : '') . "
    GROUP BY rn.display_number
    ORDER BY times_bought DESC, rn.display_number
    LIMIT 20
";
$numbersStmt = $pdo->prepare($numbersSql);
$numbersStmt->execute($params);
$topNumbers = $numbersStmt->fetchAll();

$pageTitle = 'Reportes';
require __DIR__ . '/includes/header.php';
?>
<section class="panel">
    <div class="panel-heading">
        <h2>Reportes</h2>
        <div class="actions">
            <a class="button button-ghost" href="export_csv.php?type=reservations&raffle_id=<?= $raffleId ?>">Exportar reservas CSV</a>
            <a class="button button-ghost" href="export_csv.php?type=participants">Exportar participantes CSV</a>
        </div>
    </div>
    <form method="get" class="inline-form">
        <label>Filtrar por rifa
            <select name="raffle_id" onchange="this.form.submit()">
                <option value="0">Todas</option>
                <?php foreach ($raffles as $raffle): ?>
                    <option value="<?= (int) $raffle['id'] ?>" <?= (int) $raffle['id'] === $raffleId ? 'selected' : '' ?>><?= e($raffle['title']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </form>
</section>

<section class="metric-grid">
    <?php
    $confirmed = array_sum(array_map(static fn ($row) => (float) $row['confirmed_total'], $summaries));
    $pending = array_sum(array_map(static fn ($row) => (float) $row['pending_total'], $summaries));
    $cost = array_sum(array_map(static fn ($row) => (float) $row['item_cost'], $summaries));
    $profit = $confirmed - $cost;
    ?>
    <article><span>Total confirmado</span><strong><?= money_fmt($confirmed) ?></strong></article>
    <article><span>Total pendiente</span><strong><?= money_fmt($pending) ?></strong></article>
    <article><span>Costo premios</span><strong><?= money_fmt($cost) ?></strong></article>
    <article><span>Utilidad estimada</span><strong><?= money_fmt($profit) ?></strong></article>
    <article><span>Rifas reportadas</span><strong><?= count($summaries) ?></strong></article>
</section>

<section class="panel">
    <div class="panel-heading"><h2>Resumen por rifa</h2></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Rifa</th><th>Confirmado</th><th>Pendiente</th><th>Costo</th><th>Utilidad</th><th>Números</th><th>Reservas</th></tr></thead>
            <tbody>
            <?php foreach ($summaries as $row): ?>
                <tr>
                    <td><?= e($row['title']) ?></td>
                    <td><?= money_fmt($row['confirmed_total']) ?></td>
                    <td><?= money_fmt($row['pending_total']) ?></td>
                    <td><?= money_fmt($row['item_cost']) ?></td>
                    <td><?= money_fmt((float) $row['confirmed_total'] - (float) $row['item_cost']) ?></td>
                    <td><?= (int) $row['sold_numbers'] ?> vendidos / <?= (int) $row['reserved_numbers'] ?> reservados / <?= (int) $row['available_numbers'] ?> libres</td>
                    <td><?= (int) $row['paid_reservations'] ?> pagadas / <?= (int) $row['pending_reservations'] ?> pendientes</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="report-grid">
    <div class="panel">
        <h2>Métodos de pago</h2>
        <table>
            <thead><tr><th>Método</th><th>Cantidad</th><th>Total</th></tr></thead>
            <tbody>
            <?php foreach ($paymentMethods as $method): ?>
                <tr><td><?= e($method['payment_method']) ?></td><td><?= (int) $method['count_rows'] ?></td><td><?= money_fmt($method['total']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="panel">
        <h2>Números más comprados</h2>
        <table>
            <thead><tr><th>Número</th><th>Veces</th></tr></thead>
            <tbody>
            <?php foreach ($topNumbers as $number): ?>
                <tr><td><?= e($number['display_number']) ?></td><td><?= (int) $number['times_bought'] ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
