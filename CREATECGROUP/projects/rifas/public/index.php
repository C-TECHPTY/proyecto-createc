<?php
require_once __DIR__ . '/../config/database.php';
$raffles = db()->query("SELECT * FROM raffles WHERE status IN ('active','closed','drawn') ORDER BY created_at DESC")->fetchAll();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#171018">
    <title>RifaGrid / GIBEL Rifas</title>
    <link rel="manifest" href="manifest.json">
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="public-shell">
<main class="home home-polished">
    <header class="home-hero">
        <div class="brand">RifaGrid<span>GIBEL Rifas</span></div>
        <span class="home-kicker">Rifas claras, rápidas y fáciles de administrar</span>
        <h1>Elige tus números y participa con confianza</h1>
        <p>GIBEL Rifas es una web para publicar rifas online con flyer, grilla interactiva, reservas, pagos manuales por Yappy/WhatsApp y transparencia pública de números vendidos y ganadores.</p>
        <div class="home-actions">
            <?php if ($raffles): ?><a class="button" href="#rifas-activas">Ver rifas activas</a><?php endif; ?>
            <button class="button button-ghost pwa-install" type="button" data-pwa-install data-always-visible="1">Instalar app</button>
        </div>
    </header>
    <section class="home-stats" aria-label="Ventajas">
        <article><strong>00-100</strong><span>Grilla interactiva</span></article>
        <article><strong>Yappy</strong><span>Pago manual fácil</span></article>
        <article><strong>24/7</strong><span>Transparencia pública</span></article>
    </section>
    <section class="how-it-works">
        <article><span>1</span><strong>Escoge</strong><p>Toca uno o varios números disponibles en la grilla.</p></article>
        <article><span>2</span><strong>Reserva</strong><p>Llena tus datos y el sistema bloquea tus números temporalmente.</p></article>
        <article><span>3</span><strong>Confirma</strong><p>Envía comprobante por WhatsApp o súbelo desde la web.</p></article>
    </section>

    <section class="raffle-list" id="rifas-activas">
        <?php foreach ($raffles as $raffle): ?>
            <article class="raffle-card">
                <?php if ($raffle['flyer_path']): ?><img src="<?= e($raffle['flyer_path']) ?>" alt="<?= e($raffle['title']) ?>"><?php endif; ?>
                <div>
                    <h2><?= e($raffle['title']) ?></h2>
                    <p><?= e($raffle['first_prize']) ?></p>
                    <strong><?= money_fmt($raffle['price_per_number']) ?> por número</strong>
                    <a class="button" href="rifa.php?slug=<?= e($raffle['slug']) ?>">Participar</a>
                </div>
            </article>
        <?php endforeach; ?>
        <?php if (!$raffles): ?>
            <section class="empty-raffles">
                <strong>No hay rifas activas en este momento</strong>
                <p>Muy pronto publicaremos nuevas oportunidades para participar. Guarda esta página o instala la app para volver más fácil.</p>
                <a class="button button-ghost" href="https://wa.me/<?= e(normalize_phone((string) config_value('ADMIN_WHATSAPP'))) ?>?text=<?= rawurlencode('Hola, quiero saber cuándo habrá una nueva rifa disponible.') ?>" target="_blank">Consultar por WhatsApp</a>
            </section>
        <?php endif; ?>
    </section>
</main>
<script src="assets/js/pwa.js"></script>
</body>
</html>
