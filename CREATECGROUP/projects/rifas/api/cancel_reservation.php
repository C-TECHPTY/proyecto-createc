<?php
declare(strict_types=1);

require_once __DIR__ . '/../admin/includes/auth.php';
require_once __DIR__ . '/../config/push.php';
header('Content-Type: application/json; charset=utf-8');

try {
    require_admin();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Método no permitido.');
    }
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        throw new RuntimeException('Sesión expirada.');
    }

    $reservationId = (int) ($_POST['reservation_id'] ?? 0);
    $pdo = db();
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT r.*, c.name, ra.title
        FROM reservations r
        JOIN customers c ON c.id = r.customer_id
        JOIN raffles ra ON ra.id = r.raffle_id
        WHERE r.id = ? FOR UPDATE
    ");
    $stmt->execute([$reservationId]);
    $reservation = $stmt->fetch();
    if (!$reservation || $reservation['status'] !== 'pending') {
        throw new RuntimeException('Solo se pueden cancelar reservas pendientes.');
    }

    $pdo->prepare("UPDATE reservations SET status = 'cancelled' WHERE id = ?")->execute([$reservationId]);
    $pdo->prepare("UPDATE raffle_numbers SET status = 'available', reservation_id = NULL, reserved_until = NULL WHERE reservation_id = ? AND status = 'reserved'")
        ->execute([$reservationId]);
    $pdo->prepare("UPDATE payments SET status = 'rejected' WHERE reservation_id = ? AND status = 'pending'")
        ->execute([$reservationId]);
    $pdo->prepare("UPDATE payment_receipts SET status = 'rejected' WHERE reservation_id = ? AND status = 'pending'")
        ->execute([$reservationId]);

    $numbersStmt = $pdo->prepare("
        SELECT rn.display_number
        FROM raffle_numbers rn
        JOIN reservation_numbers rrn ON rrn.raffle_number_id = rn.id
        WHERE rrn.reservation_id = ?
        ORDER BY rn.number_value
    ");
    $numbersStmt->execute([$reservationId]);
    $displayNumbers = array_column($numbersStmt->fetchAll(), 'display_number');

    audit_log($pdo, 'reservation_cancelled', 'reservation', $reservationId);
    $pdo->commit();

    try {
        $reservationUrl = '../admin/reservas.php?reservation_id=' . $reservationId;
        $body = "Cliente: {$reservation['name']}\nRifa: {$reservation['title']}\nNúmeros liberados: " . implode(', ', $displayNumbers);
        $pdo->prepare('INSERT INTO notifications (type, title, body, url) VALUES (?, ?, ?, ?)')
            ->execute(['reservation_cancelled', 'Reserva cancelada', $body, $reservationUrl]);
        push_notify_admins(
            $pdo,
            'Reserva cancelada',
            $reservation['name'] . ' fue cancelado en ' . $reservation['title'] . '. Numeros: ' . implode(', ', $displayNumbers) . '.',
            $reservationUrl,
            ['tag' => 'reservation-' . $reservationId]
        );
    } catch (Throwable) {
        // La cancelacion no debe fallar si el canal push no esta disponible.
    }

    echo json_encode(['ok' => true, 'message' => 'Reserva cancelada y números liberados.']);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
