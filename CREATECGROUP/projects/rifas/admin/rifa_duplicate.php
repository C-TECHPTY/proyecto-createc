<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/raffle_helpers.php';

$admin = require_admin();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['_csrf'] ?? null)) {
    $_SESSION['flash'] = 'Solicitud inválida.';
    redirect('rifas.php');
}

$id = (int) ($_POST['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM raffles WHERE id = ?');
$stmt->execute([$id]);
$raffle = $stmt->fetch();

if (!$raffle) {
    $_SESSION['flash'] = 'Rifa no encontrada.';
    redirect('rifas.php');
}

$baseTitle = preg_replace('/\s+\(renovada.*?\)$/i', '', $raffle['title']) ?: $raffle['title'];
$newTitle = $baseTitle . ' (renovada ' . date('d/m/Y') . ')';
$newSlug = slugify($baseTitle . '-' . date('Ymd-His'));

$insert = $pdo->prepare("
    INSERT INTO raffles
    (title, slug, description, flyer_path, first_prize, second_prize, third_prize, price_per_number, draw_date, draw_method,
     number_min, number_max, reservation_minutes, yappy_number, contact_whatsapp, paypal_link, bank_info, lnb_url, status, theme,
     primary_color, accent_color, background_color, grid_style, item_cost, points_per_amount, points_for_free_number, created_by)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$insert->execute([
    $newTitle,
    $newSlug,
    $raffle['description'],
    $raffle['flyer_path'],
    $raffle['first_prize'],
    $raffle['second_prize'],
    $raffle['third_prize'],
    $raffle['price_per_number'],
    null,
    $raffle['draw_method'],
    $raffle['number_min'],
    $raffle['number_max'],
    $raffle['reservation_minutes'],
    $raffle['yappy_number'],
    $raffle['contact_whatsapp'],
    $raffle['paypal_link'],
    $raffle['bank_info'],
    $raffle['lnb_url'],
    $raffle['theme'],
    $raffle['primary_color'],
    $raffle['accent_color'],
    $raffle['background_color'] ?? '#eaf8ff',
    $raffle['grid_style'] ?? 'soft_cards',
    $raffle['item_cost'],
    $raffle['points_per_amount'],
    $raffle['points_for_free_number'],
    $admin['id'],
]);

$newId = (int) $pdo->lastInsertId();
sync_raffle_numbers($pdo, $newId, (int) $raffle['number_min'], (int) $raffle['number_max']);
audit_log($pdo, 'raffle_duplicated', 'raffle', $newId, ['source_id' => $id]);

$_SESSION['flash'] = 'Rifa renovada como borrador. Revísala, cambia fecha/premios si hace falta y actívala.';
redirect('rifa_edit.php?id=' . $newId);

