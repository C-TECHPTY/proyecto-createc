<?php
require_once __DIR__ . '/../config/database.php';
$pdo = db();
$slug = $_GET['slug'] ?? '';

$release = $pdo->prepare("
    UPDATE raffle_numbers rn
    JOIN reservations r ON r.id = rn.reservation_id
    SET rn.status = 'available', rn.reservation_id = NULL, rn.reserved_until = NULL
    WHERE r.status = 'pending' AND r.expires_at < NOW()
");
$release->execute();
$pdo->query("UPDATE reservations SET status = 'expired' WHERE status = 'pending' AND expires_at < NOW()");

$stmt = $pdo->prepare('SELECT * FROM raffles WHERE slug = ? LIMIT 1');
$stmt->execute([$slug]);
$raffle = $stmt->fetch();
if (!$raffle) {
    http_response_code(404);
    echo 'Rifa no encontrada';
    exit;
}
$drawMethod = trim((string) ($raffle['draw_method'] ?? ''));
if ($drawMethod === '' || $drawMethod === 'Lotería Nacional de Beneficencia de Panamá') {
    $drawMethod = 'Sorteo manual';
}
$theme = preg_replace('/[^a-z0-9_-]/i', '', (string) ($raffle['theme'] ?? 'clean_sky')) ?: 'clean_sky';
$gridStyle = preg_replace('/[^a-z0-9_-]/i', '', (string) ($raffle['grid_style'] ?? 'soft_cards')) ?: 'soft_cards';
$backgroundColor = $raffle['background_color'] ?? '#eaf8ff';

$numbersStmt = $pdo->prepare('SELECT * FROM raffle_numbers WHERE raffle_id = ? ORDER BY number_value');
$numbersStmt->execute([$raffle['id']]);
$numbers = $numbersStmt->fetchAll();
$counts = ['available' => 0, 'reserved' => 0, 'sold' => 0, 'winner' => 0];
foreach ($numbers as $number) {
    $counts[$number['status']] = ($counts[$number['status']] ?? 0) + 1;
}

$buyersStmt = $pdo->prepare("
    SELECT c.name, rn.number_value, rn.display_number, rn.status, w.prize_label
    FROM raffle_numbers rn
    JOIN reservations r ON r.id = rn.reservation_id
    JOIN customers c ON c.id = r.customer_id
    LEFT JOIN winners w ON w.raffle_number_id = rn.id AND w.published = 1
    WHERE rn.raffle_id = ? AND rn.status IN ('sold','winner')
    ORDER BY rn.number_value
    LIMIT 20
");
$buyersStmt->execute([$raffle['id']]);
$buyers = $buyersStmt->fetchAll();

$winnersStmt = $pdo->prepare("
    SELECT w.prize_label, w.prize_description, w.draw_number, rn.number_value, rn.display_number, c.name
    FROM winners w
    JOIN raffle_numbers rn ON rn.id = w.raffle_number_id
    LEFT JOIN reservations r ON r.id = w.reservation_id
    LEFT JOIN customers c ON c.id = r.customer_id
    WHERE w.raffle_id = ? AND w.published = 1
    ORDER BY w.created_at DESC
");
$winnersStmt->execute([$raffle['id']]);
$winners = $winnersStmt->fetchAll();

$shareText = rawurlencode('Estoy participando en ' . $raffle['title'] . '. Elige tus números aquí: ' . app_url('rifa.php?slug=' . $raffle['slug']));
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#171018">
    <title><?= e($raffle['title']) ?> | RifaGrid</title>
    <link rel="manifest" href="manifest.json">
    <link rel="stylesheet" href="assets/css/app.css">
    <style>
        :root{--primary:<?= e($raffle['primary_color']) ?>;--accent:<?= e($raffle['accent_color']) ?>;--raffle-bg:<?= e($backgroundColor) ?>}
        body.raffle-page .reservation-panel input,
        body.raffle-page .reservation-panel select,
        body.raffle-page .reservation-panel textarea {
            background-color: #ffffff !important;
            color: #132431 !important;
            -webkit-text-fill-color: #132431 !important;
            caret-color: #132431 !important;
            opacity: 1 !important;
        }
        body.raffle-page .reservation-panel input::placeholder,
        body.raffle-page .reservation-panel textarea::placeholder {
            color: #7f94a3 !important;
            -webkit-text-fill-color: #7f94a3 !important;
        }
        body.raffle-page .reservation-panel select option {
            background-color: #ffffff !important;
            color: #132431 !important;
        }
    </style>
</head>
<body class="public-shell raffle-page theme-<?= e($theme) ?> grid-<?= e($gridStyle) ?> raffle-status-<?= e($raffle['status']) ?>">
<main class="raffle-layout" data-raffle-id="<?= (int) $raffle['id'] ?>" data-price="<?= e($raffle['price_per_number']) ?>">
    <section class="raffle-hero">
        <div class="flyer-frame">
            <?php if ($raffle['flyer_path']): ?>
                <img src="<?= e($raffle['flyer_path']) ?>" alt="<?= e($raffle['title']) ?>">
            <?php else: ?>
                <div class="flyer-placeholder">GIBEL Rifas</div>
            <?php endif; ?>
        </div>
        <div class="raffle-info">
            <span class="eyebrow"><?= e($drawMethod) ?></span>
            <h1><?= e($raffle['title']) ?></h1>
            <p><?= e($raffle['description']) ?></p>
            <div class="prize-list">
                <strong>1er premio: <?= e($raffle['first_prize']) ?></strong>
                <?php if ($raffle['second_prize']): ?><span>2do premio: <?= e($raffle['second_prize']) ?></span><?php endif; ?>
                <?php if ($raffle['third_prize']): ?><span>3er premio: <?= e($raffle['third_prize']) ?></span><?php endif; ?>
            </div>
            <div class="info-strip">
                <span><?= money_fmt($raffle['price_per_number']) ?> por número</span>
                <span>Sorteo: <?= e($raffle['draw_date'] ? date('d/m/Y h:i A', strtotime($raffle['draw_date'])) : 'Por anunciar') ?></span>
                <span>Estado: <?= e($raffle['status']) ?></span>
            </div>
            <a class="button whatsapp" target="_blank" href="https://wa.me/?text=<?= $shareText ?>">Compartir por WhatsApp</a>
            <button class="button button-ghost pwa-install" type="button" data-pwa-install data-always-visible="1">Instalar app</button>
        </div>
    </section>

    <section class="transparency-band lnb-only">
        <article><strong><a href="<?= e($raffle['lnb_url']) ?>" target="_blank">LNB</a></strong><span>Verificar sorteo</span></article>
    </section>

    <?php if ($winners): ?>
        <section class="winner-band">
            <div class="section-heading">
                <h2>Ganadores publicados</h2>
                <a href="<?= e($raffle['lnb_url']) ?>" target="_blank">Verificar en LNB</a>
            </div>
            <div class="winner-grid">
                <?php foreach ($winners as $winner): ?>
                    <article>
                        <span><?= e($winner['prize_label']) ?></span>
                        <strong><?= e(raffle_number_label($winner['number_value'])) ?></strong>
                        <p><?= e($winner['prize_description']) ?></p>
                        <small>Resultado: <?= e($winner['draw_number']) ?> · <?= e(initials_name($winner['name'] ?? 'Ganador')) ?></small>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="grid-section">
        <div class="section-heading">
            <h2>Elige tus números</h2>
            <p>Disponibles para tocar. Los reservados se liberan si no se confirma el pago.</p>
        </div>
        <div class="legend">
            <span><i class="dot available"></i> Disponible</span>
            <span><i class="dot selected"></i> Seleccionado</span>
            <span><i class="dot reserved"></i> Reservado</span>
            <span><i class="dot sold"></i> Vendido</span>
            <span><i class="dot winner"></i> Ganador</span>
        </div>
        <div class="number-grid" id="numberGrid">
            <?php foreach ($numbers as $number): ?>
                <button type="button"
                    class="number-cell is-<?= e($number['status']) ?>"
                    data-number="<?= (int) $number['number_value'] ?>"
                    <?= $number['status'] !== 'available' || $raffle['status'] !== 'active' ? 'disabled' : '' ?>>
                    <?php if ($number['status'] === 'winner'): ?>
                        <span class="winner-mark">🏆</span>
                        <span><?= e(raffle_number_label($number['number_value'])) ?></span>
                        <small>GANADOR</small>
                    <?php elseif ($number['status'] === 'reserved'): ?>
                        <span><?= e(raffle_number_label($number['number_value'])) ?></span>
                        <small>PENDIENTE</small>
                    <?php else: ?>
                        <span><?= e(raffle_number_label($number['number_value'])) ?></span>
                    <?php endif; ?>
                </button>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="reservation-panel" id="reserve">
        <h2>Reservar selección</h2>
        <form id="reserveForm">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="raffle_id" value="<?= (int) $raffle['id'] ?>">
            <input type="hidden" name="numbers" id="selectedNumbers">
            <div class="selection-summary">
                <span id="selectedLabel">Sin números seleccionados</span>
                <strong id="totalLabel">B/.0.00</strong>
            </div>
            <div class="form-grid">
                <label>Nombre <input name="name" required></label>
                <label>WhatsApp <input name="whatsapp" required inputmode="tel"></label>
                <label>Correo opcional <input name="email" type="email"></label>
                <label>Método de pago
                    <select name="payment_method">
                        <option value="yappy">Yappy</option>
                        <option value="cash">Efectivo</option>
                        <option value="transfer">Transferencia</option>
                        <option value="paypal">PayPal / tarjeta</option>
                    </select>
                </label>
            </div>
            <label>Comentario opcional <textarea name="comment"></textarea></label>
            <button class="button" type="submit">Reservar números</button>
            <div class="reserve-result" id="reserveResult"></div>
        </form>
        <div class="payment-note">
            <strong>Pago Yappy</strong>
            <p>Paga por Yappy al número <?= e($raffle['yappy_number']) ?> y envía comprobante por WhatsApp.</p>
        </div>
    </section>

    <section class="public-buyers">
        <h2>Transparencia pública</h2>
        <div class="public-status">
            <span>Fecha del sorteo: <?= e($raffle['draw_date'] ? date('d/m/Y h:i A', strtotime($raffle['draw_date'])) : 'Por anunciar') ?></span>
            <span>Estado: <?= e($raffle['status']) ?></span>
            <a href="<?= e($raffle['lnb_url']) ?>" target="_blank">Link oficial LNB</a>
        </div>
        <?php if ($buyers): ?>
            <ul class="transparency-list">
                <?php foreach ($buyers as $buyer): ?>
                    <li class="<?= $buyer['status'] === 'winner' ? 'is-winner' : '' ?>">
                        <?php if ($buyer['status'] === 'winner'): ?>
                            <strong>🏆 <?= e(raffle_number_label($buyer['number_value'])) ?></strong>
                            <span><?= e(initials_name($buyer['name'])) ?></span>
                            <em>GANADOR<?= $buyer['prize_label'] ? ' · ' . e($buyer['prize_label']) : '' ?></em>
                        <?php else: ?>
                            <strong><?= e(raffle_number_label($buyer['number_value'])) ?></strong>
                            <span><?= e(initials_name($buyer['name'])) ?></span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="muted">Aún no hay números vendidos publicados.</p>
        <?php endif; ?>
    </section>
</main>
<script>
window.RIFA_CONFIG = {
    reserveEndpoint: '../api/reserve_numbers.php',
    adminWhatsapp: <?= json_encode(normalize_phone($raffle['contact_whatsapp'] ?: (string) config_value('ADMIN_WHATSAPP'))) ?>,
    raffleTitle: <?= json_encode($raffle['title']) ?>
};
</script>
<script src="assets/js/rifa.js"></script>
<script src="assets/js/pwa.js"></script>
</body>
</html>
