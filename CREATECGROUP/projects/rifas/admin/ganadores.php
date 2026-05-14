<?php
require_once __DIR__ . '/includes/auth.php';
$admin = require_admin();
$pdo = db();

$raffles = $pdo->query("SELECT id, title, status, lnb_url FROM raffles ORDER BY created_at DESC")->fetchAll();
$selectedRaffleId = (int) ($_GET['raffle_id'] ?? ($raffles[0]['id'] ?? 0));
$selectedRaffle = null;
foreach ($raffles as $raffleOption) {
    if ((int) $raffleOption['id'] === $selectedRaffleId) {
        $selectedRaffle = $raffleOption;
        break;
    }
}

$winnersStmt = $pdo->prepare("
    SELECT w.*, rn.display_number, c.name, c.whatsapp, ra.title raffle_title, ra.draw_date
    FROM winners w
    JOIN raffle_numbers rn ON rn.id = w.raffle_number_id
    LEFT JOIN reservations r ON r.id = w.reservation_id
    LEFT JOIN customers c ON c.id = r.customer_id
    JOIN raffles ra ON ra.id = w.raffle_id
    WHERE w.raffle_id = ?
    ORDER BY w.created_at DESC
");
$winnersStmt->execute([$selectedRaffleId]);
$winners = $winnersStmt->fetchAll();

$pageTitle = 'Ganadores';
require __DIR__ . '/includes/header.php';
?>
<section class="panel">
    <div class="panel-heading">
        <h2>Resultados manuales LNB</h2>
        <?php if ($selectedRaffle): ?><a class="button button-ghost" href="<?= e($selectedRaffle['lnb_url']) ?>" target="_blank">Verificar en LNB</a><?php endif; ?>
    </div>
    <form method="get" class="inline-form">
        <label>Rifa
            <select name="raffle_id" onchange="this.form.submit()">
                <?php foreach ($raffles as $raffle): ?>
                    <option value="<?= (int) $raffle['id'] ?>" <?= (int) $raffle['id'] === $selectedRaffleId ? 'selected' : '' ?>>
                        <?= e($raffle['title']) ?> (<?= e($raffle['status']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
    </form>

    <?php if ($selectedRaffle): ?>
        <form id="winnerForm" class="admin-form compact-form">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="raffle_id" value="<?= (int) $selectedRaffleId ?>">
            <div class="form-grid">
                <label>Primer premio LNB <input name="first_draw" inputmode="numeric" placeholder="Ej: 4924"></label>
                <label>Segundo premio LNB <input name="second_draw" inputmode="numeric" placeholder="Ej: 1823"></label>
                <label>Tercer premio LNB <input name="third_draw" inputmode="numeric" placeholder="Ej: 3400"></label>
            </div>
            <button class="button" type="submit">Marcar ganadores</button>
            <div class="reserve-result" id="winnerResult"></div>
        </form>
    <?php endif; ?>
</section>

<section class="panel">
    <div class="panel-heading"><h2>Ganadores publicados</h2></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Premio</th><th>Número</th><th>Cliente</th><th>Código</th><th>Mensaje</th></tr></thead>
            <tbody>
            <?php foreach ($winners as $winner): ?>
                <?php
                $message = "🎉 ¡Felicidades! Eres ganador/a de la rifa {$winner['raffle_title']} 🏆\n\nTu número ganador fue: {$winner['display_number']}\nPremio: {$winner['prize_description']}\nCódigo secreto de confirmación: {$winner['secret_code']}\n\nPor favor escríbenos a este WhatsApp para validar tu premio.";
                $waUrl = $winner['whatsapp'] ? 'https://wa.me/' . normalize_phone($winner['whatsapp']) . '?text=' . rawurlencode($message) : '#';
                ?>
                <tr>
                    <td><?= e($winner['prize_label']) ?><br><small>Resultado: <?= e($winner['draw_number']) ?></small></td>
                    <td><strong><?= e($winner['display_number']) ?></strong></td>
                    <td><?= e($winner['name'] ?? 'Sin cliente') ?><br><small><?= e($winner['whatsapp'] ?? '') ?></small></td>
                    <td><?= e($winner['secret_code']) ?></td>
                    <td><?= $winner['whatsapp'] ? '<a class="button button-ghost" target="_blank" href="' . e($waUrl) . '">WhatsApp</a>' : '<span class="muted">No disponible</span>' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (!$winners): ?><p class="muted">Aún no hay ganadores para esta rifa.</p><?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
