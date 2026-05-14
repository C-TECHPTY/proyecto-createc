<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (sa_current_user()) {
    sa_log('auth.logout', 'Cierre de sesion Super Admin');
}

sa_session_start();
$_SESSION = [];
session_destroy();
sa_redirect('login.php');
