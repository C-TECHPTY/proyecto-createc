<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/db.php';

date_default_timezone_set((string) createc_config('timezone', 'America/Panama'));

require_once __DIR__ . '/helpers.php';
