<?php
require_once __DIR__ . '/includes/auth.php';
$admin = require_admin();
$pdo = db();
$id = (int) ($_GET['id'] ?? 0);
$raffleStmt = $pdo->prepare('SELECT * FROM raffles WHERE id = ?');
$raffleStmt->execute([$id]);
$raffle = $raffleStmt->fetch();
if (!$raffle) {
    redirect('rifas.php');
}
$numbersStmt = $pdo->prepare('SELECT * FROM raffle_numbers WHERE raffle_id = ? ORDER BY number_value');
$numbersStmt->execute([$id]);
$numbers = $numbersStmt->fetchAll();
$pageTitle = 'Grilla: ' . $raffle['title'];
require __DIR__ . '/includes/header.php';
?>
<section class="panel">
    <div class="panel-heading">
        <h2><?= e($raffle['title']) ?></h2>
        <a class="button button-ghost" href="../public/rifa.php?slug=<?= e($raffle['slug']) ?>" target="_blank">Abrir rifa</a>
    </div>
    <div class="number-grid admin-number-grid">
        <?php foreach ($numbers as $number): ?>
            <span class="number-cell is-<?= e($number['status']) ?>"><?= e(raffle_number_label($number['number_value'])) ?></span>
        <?php endforeach; ?>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
