<?php
declare(strict_types=1);

require_once __DIR__ . '/../admin/includes/auth.php';
require_once __DIR__ . '/../config/push.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $admin = require_admin();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Método no permitido.');
    }
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        throw new RuntimeException('Sesión expirada.');
    }

    $payload = json_decode($_POST['subscription'] ?? '', true);
    if (!is_array($payload)) {
        throw new RuntimeException('Suscripción inválida.');
    }

    $pdo = db();
    push_save_subscription($pdo, (int) $admin['id'], $payload, $_SERVER['HTTP_USER_AGENT'] ?? '');

    echo json_encode([
        'ok' => true,
        'configured' => push_is_configured(),
        'message' => push_is_configured()
            ? 'Notificaciones activadas en este dispositivo.'
            : 'Suscripción guardada. Configura las claves VAPID para enviar notificaciones reales.',
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
