<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (sa_current_user()) {
    sa_redirect('dashboard.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    sa_verify_csrf();
    $email = sa_post_string('email', 190);
    $password = (string) ($_POST['password'] ?? '');
    if ($email === '' || $password === '') {
        $error = 'Escribe email y contrasena.';
    } elseif (sa_login($email, $password)) {
        sa_redirect('dashboard.php');
    } else {
        $error = 'Credenciales invalidas o usuario inactivo.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin CREATEC</title>
    <style>
        :root { --brand:#0f4c81; --dark:#0b3152; --line:#d9e0ea; --ink:#152033; --muted:#667085; --danger:#d92d20; --radius:8px; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 20px; font-family: Arial, Helvetica, sans-serif; color: var(--ink); background: linear-gradient(135deg, var(--dark), var(--brand) 58%, #f5f7fb 58%); }
        .card { width: min(430px, 100%); background: #fff; border: 1px solid var(--line); border-radius: var(--radius); padding: 26px; box-shadow: 0 18px 50px rgba(15, 35, 60, .18); }
        h1 { margin: 0 0 8px; font-size: 25px; letter-spacing: 0; }
        p { margin: 0 0 22px; color: var(--muted); line-height: 1.45; }
        form { display: grid; gap: 15px; }
        label { display: grid; gap: 7px; font-weight: 700; font-size: 13px; color: #344054; }
        input { width: 100%; border: 1px solid var(--line); border-radius: var(--radius); padding: 11px; font: inherit; }
        button { min-height: 42px; border: 0; border-radius: var(--radius); background: var(--brand); color: #fff; font-weight: 700; cursor: pointer; }
        .error { margin-bottom: 15px; padding: 11px 12px; border-radius: var(--radius); background: #fee4e2; color: var(--danger); border: 1px solid #fecdca; }
    </style>
</head>
<body>
    <section class="card">
        <h1>CREATEC Super Admin</h1>
        <p>Acceso independiente para administrar empresas, planes y licencias.</p>
        <?php if ($error !== ''): ?>
            <div class="error"><?= sa_e($error) ?></div>
        <?php endif; ?>
        <form method="post" action="login.php">
            <?= sa_csrf_field() ?>
            <label>
                Email
                <input type="email" name="email" autocomplete="username" required>
            </label>
            <label>
                Contrasena
                <input type="password" name="password" autocomplete="current-password" required>
            </label>
            <button type="submit">Entrar</button>
        </form>
    </section>
</body>
</html>
