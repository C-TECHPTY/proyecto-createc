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
    if (!push_is_configured()) {
        throw new RuntimeException('Faltan las claves VAPID en config/config.php.');
    }

    $pdo = db();
    push_ensure_subscriptions_table($pdo);
    $stmt = $pdo->prepare("
        SELECT *
        FROM push_subscriptions
        WHERE admin_id = ? AND active = 1 AND status = 'active'
        ORDER BY updated_at DESC, created_at DESC
        LIMIT 1
    ");
    $stmt->execute([(int) $admin['id']]);
    $subscription = $stmt->fetch();
    if (!$subscription) {
        throw new RuntimeException('No hay una suscripción activa para este admin.');
    }

    $sent = push_send_subscription($pdo, $subscription, [
        'title' => 'RifaGrid activo',
        'body' => 'Las notificaciones push reales ya están funcionando en este dispositivo.',
        'url' => '../admin/notificaciones.php',
        'tag' => 'rifagrid-test',
    ]);

    if (!$sent) {
        throw new RuntimeException('No se pudo enviar la notificación de prueba. Revisa last_error en push_subscriptions.');
    }

    echo json_encode(['ok' => true, 'message' => 'Notificación de prueba enviada.'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
