<?php
declare(strict_types=1);

return [
    'app_name' => 'Catalogo Rodeo B2B',
    'timezone' => 'America/Bogota',
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'catalog_platform',
        'username' => 'catalog_user',
        'password' => 'change_this_password',
        'charset' => 'utf8mb4',
    ],
    'api_key' => 'CHANGE_THIS_PRIVATE_API_KEY',
    'sales_contact' => [
        'name' => 'Ventas',
        'email' => 'ventas@rodeoimportzl.com',
        'phone' => '4418710',
    ],
    'branding' => [
        'order_email_logo_url' => 'https://rodeoimportzl.com/catalogos_admin/assets/logo-rodeo-blanco.png',
        'order_email_no_image_url' => 'https://rodeoimportzl.com/catalogos_admin/assets/no-image.png',
    ],
    'mail' => [
        'from_name' => 'Catalogo Rodeo B2B',
        'from_email' => 'no-reply@tuempresa.com',
        'smtp' => [
            'enabled' => false,
            'host' => 'mail.tuempresa.com',
            'port' => 465,
            'encryption' => 'ssl', // ssl, tls o none
            'username' => 'no-reply@tuempresa.com',
            'password' => 'CAMBIA_ESTA_CLAVE',
            'timeout' => 20,
        ],
    ],
    'admin' => [
        'session_name' => 'catalog_admin_session',
    ],
    'paths' => [
        'public_catalogs_dir' => dirname(__DIR__) . '/catalogos',
    ],
];
