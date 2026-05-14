<?php
declare(strict_types=1);

require_once __DIR__ . '/../admin/includes/auth.php';
require_once __DIR__ . '/../config/push.php';
header('Content-Type: application/json; charset=utf-8');

function last_two_digits(string $value): string
{
    $digits = preg_replace('/\D+/', '', $value) ?? '';
    return str_pad(substr($digits, -2), 2, '0', STR_PAD_LEFT);
}

try {
    require_admin();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Método no permitido.');
    }
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        throw new RuntimeException('Sesión expirada.');
    }

    $raffleId = (int) ($_POST['raffle_id'] ?? 0);
    $draws = [
        'Primer premio' => ['draw' => trim($_POST['first_draw'] ?? ''), 'field' => 'first_prize'],
        'Segundo premio' => ['draw' => trim($_POST['second_draw'] ?? ''), 'field' => 'second_prize'],
        'Tercer premio' => ['draw' => trim($_POST['third_draw'] ?? ''), 'field' => 'third_prize'],
    ];

    $pdo = db();
    $pdo->beginTransaction();

    $raffleStmt = $pdo->prepare('SELECT * FROM raffles WHERE id = ? FOR UPDATE');
    $raffleStmt->execute([$raffleId]);
    $raffle = $raffleStmt->fetch();
    if (!$raffle) {
        throw new RuntimeException('Rifa no encontrada.');
    }

    $created = [];
    $winnerReservationIds = [];
    foreach ($draws as $label => $data) {
        if ($data['draw'] === '') {
            continue;
        }

        $winningNumber = last_two_digits($data['draw']);
        $numberValue = (int) $winningNumber;
        $numberStmt = $pdo->prepare("
            SELECT rn.*, r.id reservation_id
            FROM raffle_numbers rn
            LEFT JOIN reservations r ON r.id = rn.reservation_id AND r.status = 'paid'
            WHERE rn.raffle_id = ? AND rn.number_value = ?
            LIMIT 1
        ");
        $numberStmt->execute([$raffleId, $numberValue]);
        $number = $numberStmt->fetch();
        if (!$number || !in_array($number['status'], ['sold', 'winner'], true) || empty($number['reservation_id'])) {
            $created[] = "$label: $winningNumber sin comprador confirmado";
            continue;
        }

        $exists = $pdo->prepare('SELECT id FROM winners WHERE raffle_id = ? AND raffle_number_id = ? AND prize_label = ? LIMIT 1');
        $exists->execute([$raffleId, $number['id'], $label]);
        if ($exists->fetchColumn()) {
            $created[] = "$label: $winningNumber ya estaba registrado";
            continue;
        }

        $secretCode = strtoupper(bin2hex(random_bytes(4)));
        $prizeDescription = $raffle[$data['field']] ?: $label;
        $insert = $pdo->prepare("
            INSERT INTO winners (raffle_id, raffle_number_id, reservation_id, prize_label, prize_description, draw_number, secret_code, published)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1)
        ");
        $insert->execute([$raffleId, $number['id'], $number['reservation_id'], $label, $prizeDescription, $data['draw'], $secretCode]);
        $pdo->prepare("UPDATE raffle_numbers SET status = 'winner' WHERE id = ?")->execute([$number['id']]);
        $created[] = "$label: ganador $winningNumber registrado";
        $winnerReservationIds[] = (int) $number['reservation_id'];
    }

    $pdo->prepare("UPDATE raffles SET status = 'drawn' WHERE id = ?")->execute([$raffleId]);
    audit_log($pdo, 'winners_marked', 'raffle', $raffleId, ['results' => $created]);
    $pdo->commit();

    try {
        $url = $winnerReservationIds ? '../admin/reservas.php?reservation_id=' . $winnerReservationIds[0] : '../admin/ganadores.php';
        $body = "Rifa: {$raffle['title']}\nResultados:\n" . implode("\n", $created);
        $pdo->prepare('INSERT INTO notifications (type, title, body, url) VALUES (?, ?, ?, ?)')
            ->execute(['winners_marked', 'Ganadores marcados', $body, $url]);
        push_notify_admins(
            $pdo,
            'Ganadores marcados',
            $raffle['title'] . ': ' . ($created ? implode(' | ', $created) : 'sin resultados ingresados') . '.',
            $url,
            ['tag' => 'raffle-winners-' . $raffleId]
        );
    } catch (Throwable) {
        // El sorteo no debe fallar si el canal push no esta disponible.
    }

    echo json_encode(['ok' => true, 'message' => $created ? implode("\n", $created) : 'No se ingresaron resultados.']);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
