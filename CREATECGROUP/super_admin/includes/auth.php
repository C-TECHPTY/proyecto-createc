<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

function sa_require_login(): void
{
    if (!sa_current_user()) {
        sa_redirect('login.php');
    }
}

function sa_login(string $email, string $password): bool
{
    $statement = sa_db()->prepare(
        'SELECT * FROM sa_admin_users WHERE email = :email AND status = "active" LIMIT 1'
    );
    $statement->execute(['email' => trim(strtolower($email))]);
    $user = $statement->fetch();

    if (!$user || !password_verify($password, (string) $user['password_hash'])) {
        return false;
    }

    if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
        sa_db()->prepare('UPDATE sa_admin_users SET password_hash = :hash WHERE id = :id')->execute([
            'hash' => password_hash($password, PASSWORD_DEFAULT),
            'id' => (int) $user['id'],
        ]);
    }

    sa_session_start();
    session_regenerate_id(true);
    $_SESSION['sa_admin_user'] = [
        'id' => (int) $user['id'],
        'name' => (string) $user['name'],
        'email' => (string) $user['email'],
        'role' => (string) $user['role'],
    ];
    sa_log('auth.login', 'Inicio de sesion Super Admin');
    return true;
}
