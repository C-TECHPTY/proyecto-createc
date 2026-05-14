<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/raffle_helpers.php';
$admin = require_admin();
$pdo = db();
$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM raffles WHERE id = ?');
$stmt->execute([$id]);
$raffle = $stmt->fetch();
if (!$raffle) {
    redirect('rifas.php');
}
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            throw new RuntimeException('Sesión expirada.');
        }
        $title = trim($_POST['title'] ?? '');
        $slug = slugify($_POST['slug'] ?: $title);
        $min = max(0, (int) ($_POST['number_min'] ?? 0));
        $max = min(9999, (int) ($_POST['number_max'] ?? 100));
        $flyer = handle_flyer_upload('flyer') ?: $raffle['flyer_path'];
        $update = $pdo->prepare("
            UPDATE raffles SET title=?, slug=?, description=?, flyer_path=?, first_prize=?, second_prize=?, third_prize=?,
            price_per_number=?, draw_date=?, draw_method=?, number_min=?, number_max=?, reservation_minutes=?,
            yappy_number=?, contact_whatsapp=?, paypal_link=?, bank_info=?, status=?, theme=?, primary_color=?,
            accent_color=?, background_color=?, grid_style=?, item_cost=?, points_per_amount=?, points_for_free_number=? WHERE id=?
        ");
        $update->execute([
            $title, $slug, $_POST['description'] ?? '', $flyer, $_POST['first_prize'] ?? '', $_POST['second_prize'] ?? '',
            $_POST['third_prize'] ?? '', (float) ($_POST['price_per_number'] ?? 0), $_POST['draw_date'] ?: null,
            $_POST['draw_method'] ?: 'Sorteo manual', $min, $max,
            (int) ($_POST['reservation_minutes'] ?? 20), $_POST['yappy_number'] ?? '', $_POST['contact_whatsapp'] ?? '',
            $_POST['paypal_link'] ?? '', $_POST['bank_info'] ?? '', $_POST['status'] ?? 'draft', $_POST['theme'] ?? 'afro_glam',
            $_POST['primary_color'] ?? '#38aeea', $_POST['accent_color'] ?? '#f06292',
            $_POST['background_color'] ?? '#eaf8ff', $_POST['grid_style'] ?? 'soft_cards', (float) ($_POST['item_cost'] ?? 0),
            (float) ($_POST['points_per_amount'] ?? 2), (int) ($_POST['points_for_free_number'] ?? 10), $id,
        ]);
        sync_raffle_numbers($pdo, $id, $min, $max);
        audit_log($pdo, 'raffle_updated', 'raffle', $id);
        $_SESSION['flash'] = 'Rifa actualizada.';
        redirect('rifas.php');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$pageTitle = 'Editar rifa';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/rifa_form.php';
require __DIR__ . '/includes/footer.php';
