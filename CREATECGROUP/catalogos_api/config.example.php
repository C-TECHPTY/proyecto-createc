<?php
declare(strict_types=1);

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
    'sales_contact' => [
        'name' => 'CREATEC Ventas',
        'email' => 'info@createcpty.com',
        'phone' => '50765553370',
    ],
    'branding' => [
        'order_email_logo_url' => 'https://createcpty.com/assets/img/logo.png',
        'order_email_no_image_url' => 'https://createcpty.com/assets/img/favicon.png',
    ],
    'mail' => [
        'from_name' => 'CREATEC',
        'from_email' => 'no-reply@createcpty.com',
        'smtp' => [
            'enabled' => false,
            'host' => '',
            'port' => 465,
            'encryption' => 'ssl', // ssl, tls o none
            'username' => '',
            'password' => '',
            'timeout' => 20,
        ],
    ],
    'admin' => [
        'session_name' => 'catalog_admin_session',
    ],
    'paths' => [
        'public_catalogs_dir' => dirname(__DIR__) . '/projects/catalogos',
    ],
];
