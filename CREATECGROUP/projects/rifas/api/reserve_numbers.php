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

    $pdo = db();
    $raffleId = (int) ($_POST['raffle_id'] ?? 0);
    $numbers = array_values(array_unique(array_map('intval', explode(',', $_POST['numbers'] ?? ''))));
    $numbers = array_filter($numbers, static fn ($n) => $n >= 0);
    if (!$raffleId || !$numbers) {
        throw new RuntimeException('Selecciona al menos un número.');
    }

    $pdo->beginTransaction();

    $raffleStmt = $pdo->prepare("SELECT * FROM raffles WHERE id = ? AND status = 'active' FOR UPDATE");
    $raffleStmt->execute([$raffleId]);
    $raffle = $raffleStmt->fetch();
    if (!$raffle) {
        throw new RuntimeException('La rifa no está activa.');
    }

    $pdo->prepare("UPDATE reservations SET status = 'expired' WHERE raffle_id = ? AND status = 'pending' AND expires_at < NOW()")->execute([$raffleId]);
    $pdo->prepare("
        UPDATE raffle_numbers rn
        LEFT JOIN reservations r ON r.id = rn.reservation_id
        SET rn.status = 'available', rn.reservation_id = NULL, rn.reserved_until = NULL
        WHERE rn.raffle_id = ? AND rn.status = 'reserved' AND (rn.reserved_until < NOW() OR r.status = 'expired')
    ")->execute([$raffleId]);

    $placeholders = implode(',', array_fill(0, count($numbers), '?'));
    $lockStmt = $pdo->prepare("SELECT * FROM raffle_numbers WHERE raffle_id = ? AND number_value IN ($placeholders) FOR UPDATE");
    $lockStmt->execute(array_merge([$raffleId], $numbers));
    $rows = $lockStmt->fetchAll();
    if (count($rows) !== count($numbers)) {
        throw new RuntimeException('Uno o más números no pertenecen a esta rifa.');
    }
    foreach ($rows as $row) {
        if ($row['status'] !== 'available') {
            throw new RuntimeException('El número ' . $row['display_number'] . ' ya no está disponible.');
        }
    }

    $name = trim($_POST['name'] ?? '');
    $whatsapp = normalize_phone($_POST['whatsapp'] ?? '');
    if ($name === '' || $whatsapp === '') {
        throw new RuntimeException('Nombre y WhatsApp son obligatorios.');
    }

    $customerStmt = $pdo->prepare('SELECT id FROM customers WHERE whatsapp = ? LIMIT 1');
    $customerStmt->execute([$whatsapp]);
    $customerId = (int) ($customerStmt->fetchColumn() ?: 0);
    if ($customerId) {
        $pdo->prepare('UPDATE customers SET name = ?, email = ? WHERE id = ?')->execute([$name, $_POST['email'] ?: null, $customerId]);
    } else {
        $pdo->prepare('INSERT INTO customers (name, whatsapp, email) VALUES (?, ?, ?)')->execute([$name, $whatsapp, $_POST['email'] ?: null]);
        $customerId = (int) $pdo->lastInsertId();
    }

    $total = (float) $raffle['price_per_number'] * count($numbers);
    $expiresAt = date('Y-m-d H:i:s', time() + ((int) $raffle['reservation_minutes'] * 60));
    $method = in_array($_POST['payment_method'] ?? 'yappy', ['yappy','cash','transfer','paypal'], true) ? $_POST['payment_method'] : 'yappy';

    $pdo->prepare("
        INSERT INTO reservations (raffle_id, customer_id, payment_method, total_amount, comment, expires_at)
        VALUES (?, ?, ?, ?, ?, ?)
    ")->execute([$raffleId, $customerId, $method, $total, $_POST['comment'] ?? '', $expiresAt]);
    $reservationId = (int) $pdo->lastInsertId();

    $linkStmt = $pdo->prepare('INSERT INTO reservation_numbers (reservation_id, raffle_number_id) VALUES (?, ?)');
    $updateNumber = $pdo->prepare("UPDATE raffle_numbers SET status = 'reserved', reservation_id = ?, reserved_until = ? WHERE id = ?");
    foreach ($rows as $row) {
        $linkStmt->execute([$reservationId, $row['id']]);
        $updateNumber->execute([$reservationId, $expiresAt, $row['id']]);
    }

    $displayNumbers = array_map(static fn ($row) => $row['display_number'], $rows);
    sort($displayNumbers);
    $reservationUrl = '../admin/reservas.php?reservation_id=' . $reservationId;
    $body = "Cliente: $name\nWhatsApp: $whatsapp\nRifa: {$raffle['title']}\nNúmeros: " . implode(', ', $displayNumbers) . "\nTotal: " . money_fmt($total) . "\nMétodo: $method\nEstado: Pendiente de pago/comprobante";
    $pdo->prepare('INSERT INTO notifications (type, title, body, url) VALUES (?, ?, ?, ?)')
        ->execute(['reservation_created', 'Nueva reserva de rifa', $body, $reservationUrl]);
    $pdo->prepare('INSERT INTO payments (reservation_id, amount, method) VALUES (?, ?, ?)')
        ->execute([$reservationId, $total, $method]);

    audit_log($pdo, 'reservation_created', 'reservation', $reservationId, ['numbers' => $displayNumbers]);

    $pdo->commit();

    push_notify_admins(
        $pdo,
        'Nueva reserva pendiente',
        $name . ' reservo ' . implode(', ', $displayNumbers) . ' en ' . $raffle['title'] . ' por ' . money_fmt($total) . '.',
        $reservationUrl,
        ['tag' => 'reservation-' . $reservationId]
    );

    $waMessage = rawurlencode("Hola, quiero participar en la rifa.\n\nNombre: $name\nWhatsApp: $whatsapp\nRifa: {$raffle['title']}\nNúmeros: " . implode(', ', $displayNumbers) . "\nTotal: " . money_fmt($total) . "\nMétodo: " . ucfirst($method) . "\n\nAdjunto comprobante de pago.");

    echo json_encode([
        'ok' => true,
        'message' => 'Reserva creada. Tus números quedan bloqueados hasta ' . date('h:i A', strtotime($expiresAt)) . '.',
        'reservation_id' => $reservationId,
        'numbers' => $displayNumbers,
        'total' => money_fmt($total),
        'whatsapp_url' => 'https://wa.me/' . normalize_phone($raffle['contact_whatsapp'] ?: (string) config_value('ADMIN_WHATSAPP')) . '?text=' . $waMessage,
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
