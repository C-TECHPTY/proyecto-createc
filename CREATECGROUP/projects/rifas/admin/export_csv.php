<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_admin();

$pdo = db();
$type = $_GET['type'] ?? 'reservations';
$raffleId = (int) ($_GET['raffle_id'] ?? 0);

$filename = 'rifagrid-' . $type . '-' . date('Ymd-His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
fputcsv($out, ['RifaGrid export', date('Y-m-d H:i:s')]);

if ($type === 'participants') {
    fputcsv($out, ['Nombre', 'WhatsApp', 'Email', 'Reservas', 'Total confirmado', 'Total pendiente', 'Puntos']);
    $rows = $pdo->query("
        SELECT c.name, c.whatsapp, c.email,
          COALESCE(reservations.reservations_count, 0) reservations_count,
          COALESCE(reservations.confirmed_total, 0) confirmed_total,
          COALESCE(reservations.pending_total, 0) pending_total,
          COALESCE(points.points, 0) points
        FROM customers c
        LEFT JOIN (
          SELECT customer_id,
            COUNT(*) reservations_count,
            SUM(CASE WHEN status = 'paid' THEN total_amount ELSE 0 END) confirmed_total,
            SUM(CASE WHEN status = 'pending' THEN total_amount ELSE 0 END) pending_total
          FROM reservations
          GROUP BY customer_id
        ) reservations ON reservations.customer_id = c.id
        LEFT JOIN (
          SELECT customer_id, SUM(points) points
          FROM loyalty_points
          GROUP BY customer_id
        ) points ON points.customer_id = c.id
        ORDER BY confirmed_total DESC
    ")->fetchAll();
    foreach ($rows as $row) {
        fputcsv($out, [$row['name'], $row['whatsapp'], $row['email'], $row['reservations_count'], $row['confirmed_total'], $row['pending_total'], $row['points']]);
    }
    exit;
}

fputcsv($out, ['Reserva', 'Rifa', 'Cliente', 'WhatsApp', 'Numeros', 'Metodo', 'Estado', 'Total', 'Fecha']);
$sql = "
    SELECT r.id, r.payment_method, r.status, r.total_amount, r.created_at, c.name, c.whatsapp, ra.title,
      nums.numbers
    FROM reservations r
    JOIN customers c ON c.id = r.customer_id
    JOIN raffles ra ON ra.id = r.raffle_id
    LEFT JOIN (
      SELECT rrn.reservation_id, GROUP_CONCAT(rn.display_number ORDER BY rn.number_value SEPARATOR ', ') numbers
      FROM reservation_numbers rrn
      JOIN raffle_numbers rn ON rn.id = rrn.raffle_number_id
      GROUP BY rrn.reservation_id
    ) nums ON nums.reservation_id = r.id
";
$params = [];
if ($raffleId > 0) {
    $sql .= ' WHERE r.raffle_id = ?';
    $params[] = $raffleId;
}
$sql .= ' ORDER BY r.created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
foreach ($stmt->fetchAll() as $row) {
    fputcsv($out, [$row['id'], $row['title'], $row['name'], $row['whatsapp'], $row['numbers'], $row['payment_method'], $row['status'], $row['total_amount'], $row['created_at']]);
}
