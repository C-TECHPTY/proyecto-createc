<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
admin_require_login(['admin', 'sales']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_abort();
    db()->prepare(
        'INSERT INTO clients (code, business_name, contact_name, email, phone, address_line, zone, city, country, seller_id, notes, is_active)
         VALUES (:code, :business_name, :contact_name, :email, :phone, :address_line, :zone, :city, :country, :seller_id, :notes, :is_active)'
    )->execute([
        'code' => trim((string) $_POST['code']),
        'business_name' => trim((string) $_POST['business_name']),
        'contact_name' => trim((string) $_POST['contact_name']),
        'email' => trim((string) $_POST['email']),
        'phone' => trim((string) $_POST['phone']),
        'address_line' => trim((string) $_POST['address_line']),
        'zone' => trim((string) $_POST['zone']),
        'city' => trim((string) $_POST['city']),
        'country' => trim((string) $_POST['country']),
        'seller_id' => (int) ($_POST['seller_id'] ?? 0) ?: null,
        'notes' => trim((string) $_POST['notes']),
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
    ]);
    audit_log('client.created', 'clients', (int) db()->lastInsertId());
    flash_set('success', 'Cliente creado.');
    header('Location: clients.php');
    exit;
}

$sellers = db()->query('SELECT id, name FROM sellers WHERE is_active = 1 ORDER BY name ASC')->fetchAll();
$clients = db()->query(
    'SELECT c.*, s.name AS seller_name,
            (SELECT COUNT(*) FROM orders o WHERE o.client_id = c.id) AS orders_count
     FROM clients c
     LEFT JOIN sellers s ON s.id = c.seller_id
     ORDER BY c.business_name ASC'
)->fetchAll();

admin_header('Clientes', 'clients.php');
?>
<div class="split">
    <section class="card">
        <div class="toolbar"><strong>Nuevo cliente</strong></div>
        <form class="form-grid" method="post">
            <?= csrf_field() ?>
            <label><span>Codigo</span><input type="text" name="code" required></label>
            <label><span>Nombre comercial</span><input type="text" name="business_name" required></label>
            <label><span>Contacto</span><input type="text" name="contact_name"></label>
            <label><span>Correo</span><input type="email" name="email"></label>
            <label><span>Telefono</span><input type="text" name="phone"></label>
            <label><span>Vendedor asignado</span><select name="seller_id"><option value="">Sin asignar</option><?php foreach ($sellers as $seller): ?><option value="<?= (int) $seller['id'] ?>"><?= html_escape($seller['name']) ?></option><?php endforeach; ?></select></label>
            <label class="wide"><span>Direccion</span><input type="text" name="address_line"></label>
            <label><span>Zona</span><input type="text" name="zone"></label>
            <label><span>Ciudad</span><input type="text" name="city"></label>
            <label><span>Pais</span><input type="text" name="country"></label>
            <label><span>Activo</span><input type="checkbox" name="is_active" checked style="min-height:auto;width:auto;"></label>
            <label class="wide"><span>Notas</span><textarea name="notes"></textarea></label>
            <div class="wide"><button class="button--primary" type="submit">Crear cliente</button></div>
        </form>
    </section>
    <section class="card">
        <div class="toolbar"><strong>Clientes registrados</strong><span class="pill"><?= count($clients) ?> clientes</span></div>
        <div class="list">
            <?php foreach ($clients as $client): ?>
                <div class="list-item">
                    <div class="toolbar">
                        <div>
                            <strong><?= html_escape($client['business_name']) ?></strong>
                            <div class="muted"><?= html_escape($client['contact_name']) ?> · <?= html_escape($client['phone']) ?></div>
                        </div>
                        <?= admin_status_badge((int) $client['is_active'] === 1 ? 'active' : 'inactive') ?>
                    </div>
                    <div class="metrics-inline">
                        <span class="pill"><?= html_escape($client['seller_name'] ?: 'Sin vendedor') ?></span>
                        <span class="pill"><?= (int) $client['orders_count'] ?> pedidos</span>
                        <span class="pill"><?= html_escape($client['zone']) ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
</div>
<?php admin_footer(); ?>
