<?php
$admin = $admin ?? current_admin();
$unreadCount = 0;
try {
    $unreadCount = (int) db()->query('SELECT COUNT(*) FROM notifications WHERE read_at IS NULL')->fetchColumn();
} catch (Throwable) {
    $unreadCount = 0;
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#171018">
    <title><?= e($pageTitle ?? 'Admin | RifaGrid') ?></title>
    <link rel="manifest" href="../manifest.webmanifest">
    <link rel="stylesheet" href="../public/assets/css/app.css">
    <script>
        window.RIFAGRID_CSRF = <?= json_encode(csrf_token()) ?>;
        window.RIFAGRID_PUSH_PUBLIC_KEY = <?= json_encode((string) config_value('WEB_PUSH_PUBLIC_KEY', '')) ?>;
        window.RIFAGRID_SW_URL = '../service-worker.js';
        window.RIFAGRID_SW_SCOPE = '../';
    </script>
</head>
<body class="admin-shell">
<aside class="admin-sidebar">
    <div class="admin-sidebar-head">
        <a class="brand" href="dashboard.php">RifaGrid<span>GIBEL Rifas</span></a>
        <button class="admin-menu-toggle" type="button" aria-expanded="false" aria-controls="adminNav">Menú</button>
    </div>
    <nav id="adminNav" class="admin-nav">
        <a href="dashboard.php">Dashboard</a>
        <a href="rifas.php">Rifas</a>
        <a href="reservas.php">Reservas</a>
        <a href="notificaciones.php">Notificaciones</a>
        <a href="pagos.php">Pagos</a>
        <a href="comprobantes.php">Comprobantes</a>
        <a href="ganadores.php">Ganadores</a>
        <a href="participantes.php">Participantes</a>
        <a href="puntos.php">Puntos</a>
        <a href="reportes.php">Reportes</a>
        <a href="settings.php">Settings</a>
    </nav>
</aside>
<main class="admin-main">
    <header class="admin-topbar">
        <div>
            <strong><?= e($pageTitle ?? 'Panel admin') ?></strong>
            <span><?= e($admin['name'] ?? '') ?></span>
        </div>
        <div class="topbar-actions">
            <a class="notification-pill" id="notificationCount" href="notificaciones.php"><?= $unreadCount ?></a>
            <a class="button button-ghost" href="../public/index.php" target="_blank">Ver público</a>
            <button class="button button-ghost pwa-install" type="button" data-pwa-install data-always-visible="1">Instalar app</button>
            <button class="button button-ghost" type="button" data-web-push-subscribe>Activar notificaciones en este dispositivo</button>
            <a class="button button-ghost" href="logout.php">Salir</a>
        </div>
    </header>
    <?php if (!empty($_SESSION['flash'])): ?>
        <div class="flash"><?= e($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
    <?php endif; ?>
    <div class="notification-toast" id="notificationToast" hidden>
        <strong id="notificationToastTitle"></strong>
        <p id="notificationToastBody"></p>
        <div class="actions">
            <a id="notificationToastLink" href="notificaciones.php">Ver</a>
            <button type="button" id="notificationToastClose">Cerrar</button>
        </div>
    </div>
