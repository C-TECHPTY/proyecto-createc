<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

$currentUser = function_exists('current_user') ? current_user() : null;
if ($currentUser) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$username = '';
$companyName = app_setting('branding_company_name', (string) catalog_config('app_name', 'Catalogo Rodeo B2B'));
$loginTitle = app_setting('branding_login_title', 'Catalogo Rodeo B2B');
$loginSubtitle = app_setting('branding_login_subtitle', 'Administracion comercial, vendedores, links y pedidos trazables.');
$loginLogoPath = app_setting('branding_login_logo');
$companyLogoPath = app_setting('branding_company_logo');
$loginBackgroundPath = app_setting('branding_login_background');
$loginLogoUrl = panel_media_url($loginLogoPath !== '' ? $loginLogoPath : $companyLogoPath);
$loginBackgroundUrl = panel_media_url($loginBackgroundPath);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (function_exists('verify_csrf_or_abort')) {
        verify_csrf_or_abort();
    }
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $auth = function_exists('admin_authenticate')
        ? admin_authenticate($username, $password)
        : ['ok' => admin_login($username, $password), 'message' => 'Usuario o contrasena invalidos.'];
    if ($auth['ok'] ?? false) {
        $user = $auth['user'] ?? current_user();
        $target = function_exists('admin_post_login_target') ? admin_post_login_target($user) : 'dashboard.php';
        header('Location: ' . $target);
        exit;
    }
    $error = (string) ($auth['message'] ?? 'Acceso denegado.');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Catalogo Rodeo B2B</title>
    <link rel="stylesheet" href="../assets/admin.css">
</head>
<body class="login-page"<?= $loginBackgroundUrl !== '' ? ' style="--login-bg-image:url(\'' . html_escape($loginBackgroundUrl) . '\')"' : '' ?>>
    <div class="login-shell">
        <div class="login-card">
            <div class="login-brand">
                <span class="login-brand__mark">
                    <?php if ($loginLogoUrl !== ''): ?>
                        <img class="login-brand__logo" src="<?= html_escape($loginLogoUrl) ?>" alt="<?= html_escape($companyName) ?>">
                    <?php else: ?>
                        B2B
                    <?php endif; ?>
                </span>
                <div>
                    <h1><?= html_escape($loginTitle) ?></h1>
                    <p><?= html_escape($loginSubtitle) ?></p>
                </div>
            </div>
            <?php if ($error !== ''): ?>
                <div class="flash flash--error"><?= html_escape($error) ?></div>
            <?php endif; ?>
            <form class="grid" method="post" autocomplete="on">
                <?= function_exists('csrf_field') ? csrf_field() : '' ?>
                <label><span>Usuario</span><input type="text" name="username" value="<?= html_escape($username) ?>" autocomplete="username" required autofocus></label>
                <label><span>Contrasena</span><input type="password" name="password" autocomplete="current-password" required></label>
                <button class="button--primary" type="submit">Ingresar al panel</button>
            </form>
            <div class="login-meta">
                <span><?= html_escape($companyName) ?></span>
                <span>Acceso seguro con sesiones protegidas</span>
                <span>API y pedidos integrados</span>
            </div>
        </div>
    </div>
</body>
</html>
