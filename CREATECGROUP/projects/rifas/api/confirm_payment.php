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

    $reservationId = (int) ($_POST['reservation_id'] ?? 0);
    $pdo = db();
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT r.*, c.name, c.whatsapp, ra.title, ra.draw_date
        FROM reservations r
        JOIN customers c ON c.id = r.customer_id
        JOIN raffles ra ON ra.id = r.raffle_id
        WHERE r.id = ? FOR UPDATE
    ");
    $stmt->execute([$reservationId]);
    $reservation = $stmt->fetch();
    if (!$reservation || $reservation['status'] !== 'pending') {
        throw new RuntimeException('La reserva no está pendiente.');
    }

    $numbersStmt = $pdo->prepare("
        SELECT rn.id, rn.display_number
        FROM raffle_numbers rn
        JOIN reservation_numbers rrn ON rrn.raffle_number_id = rn.id
        WHERE rrn.reservation_id = ?
        ORDER BY rn.number_value
    ");
    $numbersStmt->execute([$reservationId]);
    $numbers = $numbersStmt->fetchAll();

    $pdo->prepare("UPDATE reservations SET status = 'paid', paid_at = NOW(), confirmed_by = ? WHERE id = ?")
        ->execute([$admin['id'], $reservationId]);
    $pdo->prepare("UPDATE raffle_numbers SET status = 'sold', reserved_until = NULL WHERE reservation_id = ?")
        ->execute([$reservationId]);
    $pdo->prepare("UPDATE payments SET status = 'confirmed', confirmed_by = ?, confirmed_at = NOW() WHERE reservation_id = ?")
        ->execute([$admin['id'], $reservationId]);
    $pdo->prepare("UPDATE payment_receipts SET status = 'approved' WHERE reservation_id = ? AND status = 'pending'")
        ->execute([$reservationId]);

    $points = 0;
    $raffleConfig = $pdo->prepare('SELECT points_per_amount FROM raffles WHERE id = ?');
    $raffleConfig->execute([$reservation['raffle_id']]);
    $pointsPerAmount = (float) ($raffleConfig->fetchColumn() ?: 0);
    if ($pointsPerAmount > 0) {
        $points = (int) floor((float) $reservation['total_amount'] / $pointsPerAmount);
        if ($points > 0) {
            $pdo->prepare('INSERT INTO loyalty_points (customer_id, raffle_id, points, reason, reservation_id) VALUES (?, ?, ?, ?, ?)')
                ->execute([$reservation['customer_id'], $reservation['raffle_id'], $points, 'Compra confirmada', $reservationId]);
        }
    }

    $displayNumbers = array_column($numbers, 'display_number');
    $reservationUrl = '../admin/reservas.php?reservation_id=' . $reservationId;
    audit_log($pdo, 'payment_confirmed', 'reservation', $reservationId, ['numbers' => $displayNumbers, 'points' => $points]);

    $pdo->commit();

    try {
        $body = "Cliente: {$reservation['name']}\nRifa: {$reservation['title']}\nNúmeros: " . implode(', ', $displayNumbers) . "\nTotal: " . money_fmt($reservation['total_amount']);
        $pdo->prepare('INSERT INTO notifications (type, title, body, url) VALUES (?, ?, ?, ?)')
            ->execute(['reservation_paid', 'Reserva confirmada', $body, $reservationUrl]);
        push_notify_admins(
            $pdo,
            'Reserva confirmada',
            $reservation['name'] . ' quedo confirmado en ' . $reservation['title'] . ' con ' . implode(', ', $displayNumbers) . '.',
            $reservationUrl,
            ['tag' => 'reservation-' . $reservationId]
        );
    } catch (Throwable) {
        // La confirmacion de pago no debe fallar si el canal push no esta disponible.
    }

    $message = "✅ Pago confirmado\nGracias por participar en {$reservation['title']}.\nTus números confirmados son: " . implode(', ', $displayNumbers) . "\nTotal pagado: " . money_fmt($reservation['total_amount']) . "\nFecha del sorteo: " . ($reservation['draw_date'] ? date('d/m/Y h:i A', strtotime($reservation['draw_date'])) : 'Por anunciar') . "\nGuarda este mensaje como comprobante.";
    $waUrl = 'https://wa.me/' . normalize_phone($reservation['whatsapp']) . '?text=' . rawurlencode($message);

    echo json_encode(['ok' => true, 'message' => 'Pago confirmado.', 'whatsapp_url' => $waUrl]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
