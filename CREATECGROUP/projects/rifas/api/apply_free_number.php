<?php
declare(strict_types=1);

require_once __DIR__ . '/../admin/includes/auth.php';
header('Content-Type: application/json; charset=utf-8');

try {
    require_admin();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Método no permitido.');
    }
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        throw new RuntimeException('Sesión expirada.');
    }

    $customerId = (int) ($_POST['customer_id'] ?? 0);
    $raffleId = (int) ($_POST['raffle_id'] ?? 0);
    $numberValue = (int) ($_POST['number_value'] ?? -1);

    if (!$customerId || !$raffleId || $numberValue < 0) {
        throw new RuntimeException('Selecciona cliente, rifa y número.');
    }

    $pdo = db();
    $pdo->beginTransaction();

    $raffleStmt = $pdo->prepare("SELECT * FROM raffles WHERE id = ? AND status = 'active' FOR UPDATE");
    $raffleStmt->execute([$raffleId]);
    $raffle = $raffleStmt->fetch();
    if (!$raffle) {
        throw new RuntimeException('La rifa no está activa.');
    }

    $customerStmt = $pdo->prepare('SELECT * FROM customers WHERE id = ?');
    $customerStmt->execute([$customerId]);
    $customer = $customerStmt->fetch();
    if (!$customer) {
        throw new RuntimeException('Cliente no encontrado.');
    }

    $pointsStmt = $pdo->prepare('SELECT COALESCE(SUM(points), 0) FROM loyalty_points WHERE customer_id = ?');
    $pointsStmt->execute([$customerId]);
    $availablePoints = (int) $pointsStmt->fetchColumn();
    $required = (int) $raffle['points_for_free_number'];
    if ($required > 0 && $availablePoints < $required) {
        throw new RuntimeException('El cliente no tiene puntos suficientes.');
    }

    $numberStmt = $pdo->prepare('SELECT * FROM raffle_numbers WHERE raffle_id = ? AND number_value = ? FOR UPDATE');
    $numberStmt->execute([$raffleId, $numberValue]);
    $number = $numberStmt->fetch();
    if (!$number || $number['status'] !== 'available') {
        throw new RuntimeException('El número no está disponible.');
    }

    $pdo->prepare("
        INSERT INTO reservations (raffle_id, customer_id, status, payment_method, total_amount, comment, paid_at, confirmed_by)
        VALUES (?, ?, 'paid', 'cash', 0.00, 'Número gratis aplicado por puntos', NOW(), ?)
    ")->execute([$raffleId, $customerId, $_SESSION['admin_id'] ?? null]);
    $reservationId = (int) $pdo->lastInsertId();

    $pdo->prepare('INSERT INTO reservation_numbers (reservation_id, raffle_number_id) VALUES (?, ?)')
        ->execute([$reservationId, $number['id']]);
    $pdo->prepare("UPDATE raffle_numbers SET status = 'sold', reservation_id = ?, reserved_until = NULL WHERE id = ?")
        ->execute([$reservationId, $number['id']]);
    $pdo->prepare("INSERT INTO payments (reservation_id, amount, method, status, confirmed_by, confirmed_at) VALUES (?, 0.00, 'points', 'confirmed', ?, NOW())")
        ->execute([$reservationId, $_SESSION['admin_id'] ?? null]);

    if ($required > 0) {
        $pdo->prepare('INSERT INTO loyalty_points (customer_id, raffle_id, points, reason, reservation_id) VALUES (?, ?, ?, ?, ?)')
            ->execute([$customerId, $raffleId, -$required, 'Canje por número gratis', $reservationId]);
    }

    audit_log($pdo, 'free_number_applied', 'reservation', $reservationId, [
        'customer_id' => $customerId,
        'raffle_id' => $raffleId,
        'number' => $number['display_number'],
        'points_used' => $required,
    ]);

    $pdo->commit();

    echo json_encode(['ok' => true, 'message' => 'Número gratis aplicado correctamente.']);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
