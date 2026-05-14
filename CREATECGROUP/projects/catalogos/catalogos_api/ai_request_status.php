<?php
declare(strict_types=1);

require __DIR__ . '/ai_helpers.php';

$payload = ai_request_payload();
$requestId = trim((string) ($payload['request_id'] ?? ''));
if ($requestId === '') {
    json_response([
        'ok' => false,
        'error' => 'Debes indicar request_id.',
    ], 422);
}

ai_log('catalog.status', ['request_id' => $requestId]);

json_response([
    'ok' => true,
    'request_id' => $requestId,
    'status' => 'ready',
    'message' => 'Solicitud registrada en modo seguro. Sin procesamiento asincrono en esta fase.',
]);
