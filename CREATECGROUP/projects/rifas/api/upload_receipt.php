<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/push.php';
header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Método no permitido.');
    }
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        throw new RuntimeException('Sesión expirada. Recarga la página.');
    }

    $reservationId = (int) ($_POST['reservation_id'] ?? 0);
    if (!$reservationId) {
        throw new RuntimeException('Reserva inválida.');
    }

    if (empty($_FILES['receipt']['name']) || ($_FILES['receipt']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('Selecciona un comprobante.');
    }
    if ($_FILES['receipt']['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('No se pudo subir el comprobante.');
    }
    if ($_FILES['receipt']['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('El comprobante no debe superar 5 MB.');
    }

    $pdo = db();
    $stmt = $pdo->prepare("
        SELECT r.id, r.status, r.total_amount, c.name, ra.title
        FROM reservations r
        JOIN customers c ON c.id = r.customer_id
        JOIN raffles ra ON ra.id = r.raffle_id
        WHERE r.id = ?
    ");
    $stmt->execute([$reservationId]);
    $reservation = $stmt->fetch();
    if (!$reservation || $reservation['status'] !== 'pending') {
        throw new RuntimeException('Solo se aceptan comprobantes para reservas pendientes.');
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'application/pdf' => 'pdf',
    ];
    $mime = mime_content_type($_FILES['receipt']['tmp_name']);
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('El archivo debe ser JPG, PNG o PDF.');
    }

    $targetDir = __DIR__ . '/../public/assets/uploads/comprobantes';
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0775, true);
    }

    $filename = 'comprobante-' . $reservationId . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    $target = $targetDir . '/' . $filename;
    if (!move_uploaded_file($_FILES['receipt']['tmp_name'], $target)) {
        throw new RuntimeException('No se pudo guardar el comprobante.');
    }

    $publicPath = 'assets/uploads/comprobantes/' . $filename;
    $pdo->prepare('INSERT INTO payment_receipts (reservation_id, file_path, original_name, mime_type) VALUES (?, ?, ?, ?)')
        ->execute([$reservationId, $publicPath, $_FILES['receipt']['name'], $mime]);

    $reservationUrl = '../admin/reservas.php?reservation_id=' . $reservationId;
    $body = "Cliente: {$reservation['name']}\nRifa: {$reservation['title']}\nTotal: " . money_fmt($reservation['total_amount']) . "\nRevisar y confirmar en el panel admin.";
    $pdo->prepare('INSERT INTO notifications (type, title, body, url) VALUES (?, ?, ?, ?)')
        ->execute(['receipt_uploaded', 'Nuevo comprobante recibido', $body, $reservationUrl]);

    audit_log($pdo, 'receipt_uploaded', 'reservation', $reservationId, ['file' => $publicPath]);

    push_notify_admins(
        $pdo,
        'Nuevo comprobante recibido',
        $reservation['name'] . ' subio comprobante de ' . money_fmt($reservation['total_amount']) . ' para ' . $reservation['title'] . '.',
        $reservationUrl,
        ['tag' => 'receipt-' . $reservationId]
    );

    echo json_encode(['ok' => true, 'message' => 'Comprobante recibido. El admin lo revisará para confirmar tu pago.']);
} catch (Throwable $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
