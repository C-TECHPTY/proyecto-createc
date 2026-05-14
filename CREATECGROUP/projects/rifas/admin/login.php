<?php
require_once __DIR__ . '/includes/auth.php';

if (current_admin()) {
    redirect('dashboard.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Sesión expirada. Intenta nuevamente.';
    } elseif (admin_login(trim($_POST['email'] ?? ''), $_POST['password'] ?? '')) {
        redirect('dashboard.php');
    } else {
        $error = 'Credenciales inválidas.';
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#171018">
    <title>Login | RifaGrid</title>
    <link rel="manifest" href="../manifest.webmanifest">
    <link rel="stylesheet" href="../public/assets/css/app.css">
    <script>
        window.RIFAGRID_SW_URL = '../service-worker.js';
        window.RIFAGRID_SW_SCOPE = '../';
    </script>
</head>
<body class="login-page">
    <form class="login-card" method="post">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <div class="brand brand-login">RifaGrid<span>GIBEL Rifas</span></div>
        <h1>Panel admin</h1>
        <?php if ($error): ?><div class="alert"><?= e($error) ?></div><?php endif; ?>
        <label>Correo
            <input type="email" name="email" required autocomplete="username">
        </label>
        <label>Contraseña
            <input type="password" name="password" required autocomplete="current-password">
        </label>
        <button class="button" type="submit">Entrar</button>
        <button class="button button-ghost pwa-install" type="button" data-pwa-install data-always-visible="1">Instalar app admin</button>
    </form>
    <script src="../public/assets/js/pwa.js"></script>
</body>
</html>
