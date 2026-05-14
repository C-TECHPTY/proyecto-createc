<?php
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json; charset=utf-8');
$raffleId = (int) ($_GET['raffle_id'] ?? 0);
$stmt = db()->prepare('SELECT number_value, status FROM raffle_numbers WHERE raffle_id = ? ORDER BY number_value');
$stmt->execute([$raffleId]);
$numbers = array_map(static function (array $row): array {
    $row['display_number'] = raffle_number_label($row['number_value']);
    return $row;
}, $stmt->fetchAll());
echo json_encode(['ok' => true, 'numbers' => $numbers]);
