<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/whatsapp.php';

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $mode = $_GET['hub_mode'] ?? $_GET['hub.mode'] ?? '';
    $token = $_GET['hub_verify_token'] ?? $_GET['hub.verify_token'] ?? '';
    $challenge = $_GET['hub_challenge'] ?? $_GET['hub.challenge'] ?? '';

    $configuredToken = '';
    try {
        $configuredToken = (string) $pdo->query("SELECT webhook_verify_token FROM whatsapp_configs WHERE active = 1 ORDER BY id DESC LIMIT 1")->fetchColumn();
    } catch (Throwable) {
        $configuredToken = (string) config_value('WHATSAPP_WEBHOOK_VERIFY_TOKEN', '');
    }

    if ($mode === 'subscribe' && $configuredToken !== '' && hash_equals($configuredToken, (string) $token)) {
        header('Content-Type: text/plain; charset=utf-8');
        echo $challenge;
        exit;
    }

    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'message' => 'Verificación rechazada.']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

try {
    $raw = file_get_contents('php://input') ?: '';
    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        $payload = $_POST ?: ['raw' => $raw];
    }

    $provider = $_GET['provider'] ?? 'cloud_api';
    $text = whatsapp_extract_text($payload);
    $from = whatsapp_extract_from($payload);
    $to = whatsapp_extract_to($payload);
    $intent = whatsapp_detect_intent($text);

    $stmt = $pdo->prepare("
        INSERT INTO whatsapp_messages (provider, direction, from_phone, to_phone, message_text, intent, payload, status)
        VALUES (?, 'inbound', ?, ?, ?, ?, ?, 'received')
    ");
    $stmt->execute([
        $provider,
        $from,
        $to,
        $text,
        $intent,
        json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]);

    if ($intent !== 'empty') {
        $body = "WhatsApp: " . ($from ?: 'desconocido') . "\nIntent: $intent\nMensaje: $text";
        $pdo->prepare('INSERT INTO notifications (type, title, body, url) VALUES (?, ?, ?, ?)')
            ->execute(['whatsapp_inbound', 'Nuevo mensaje WhatsApp', $body, '../admin/settings.php#whatsapp-messages']);
    }

    echo json_encode([
        'ok' => true,
        'message' => 'Mensaje registrado. La respuesta automática se activará en una integración futura.',
        'intent' => $intent,
    ]);
} catch (Throwable $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
