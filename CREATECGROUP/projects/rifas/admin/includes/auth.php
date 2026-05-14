<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';

function current_admin(): ?array
{
    if (empty($_SESSION['admin_id'])) {
        return null;
    }

    $stmt = db()->prepare('SELECT id, name, email, role FROM admins WHERE id = ? AND active = 1');
    $stmt->execute([$_SESSION['admin_id']]);
    return $stmt->fetch() ?: null;
}

function require_admin(): array
{
    $admin = current_admin();
    if (!$admin) {
        redirect('login.php');
    }
    return $admin;
}

function admin_login(string $email, string $password): bool
{
    $stmt = db()->prepare('SELECT * FROM admins WHERE email = ? AND active = 1 LIMIT 1');
    $stmt->execute([$email]);
    $admin = $stmt->fetch();

    if (!$admin || !password_verify($password, $admin['password_hash'])) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int) $admin['id'];

    $update = db()->prepare('UPDATE admins SET last_login_at = NOW() WHERE id = ?');
    $update->execute([$admin['id']]);

    return true;
}

function admin_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

