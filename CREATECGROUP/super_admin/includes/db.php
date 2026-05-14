<?php
declare(strict_types=1);

function sa_config(?string $key = null, mixed $default = null): mixed
{
    require_once dirname(__DIR__, 2) . '/includes/db.php';
    return createc_config($key, $default);
}

function sa_db(): PDO
{
    require_once dirname(__DIR__, 2) . '/includes/db.php';
    return createc_db();
}
