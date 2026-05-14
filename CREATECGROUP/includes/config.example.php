<?php
declare(strict_types=1);

/*
 * CREATEC SaaS configuration template.
 *
 * Copy this file to includes/config.php only on the hosting/server and fill
 * the real credentials there. Do not commit or upload real passwords, SMTP
 * secrets, API keys or private tokens into the repository.
 */

return [
    'app_name' => 'CREATEC SaaS',
    'timezone' => 'America/Panama',
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'createc_saas',
        'username' => 'CPANEL_MYSQL_USER',
        'password' => 'CPANEL_MYSQL_PASSWORD',
        'charset' => 'utf8mb4',
    ],
    'api_key' => 'CHANGE_THIS_PRIVATE_API_KEY',
    'admin' => [
        'session_name' => 'createc_super_admin_session',
    ],
    'mail' => [
        'from_name' => 'CREATEC',
        'from_email' => 'no-reply@createcpty.com',
        'smtp' => [
            'enabled' => false,
            'host' => '',
            'port' => 465,
            'encryption' => 'ssl',
            'username' => '',
            'password' => '',
            'timeout' => 20,
        ],
    ],
];
