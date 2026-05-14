<?php
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok' => false,
    'message' => 'Modo automático experimental no activo. Usa verificación manual con https://www.lnb.gob.pa/.',
    'official_url' => 'https://www.lnb.gob.pa/',
]);

