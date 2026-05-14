<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
vendor_require_panel_login();

$user = vendor_current_user();
$sellerId = (int) ($user['seller_id'] ?? 0);
$userId = (int) ($user['id'] ?? 0);
$schemaReady = vendor_b2b_schema_ready()
    && vendor_table_exists('orders')
    && vendor_table_exists('vendor_client_profiles')
    && vendor_table_exists('vendor_client_notes');
$hasClients = vendor_table_exists('clients');
$clientStatuses = [
    'frecuente' => 'Frecuente',
    'potencial' => 'Potencial',
    'inactivo' => 'Inactivo',
    'mayorista' => 'Mayorista',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_abort();
    if (!$schemaReady || $sellerId <= 0) {
        flash_set('error', 'Falta ejecutar la migracion del Mini CRM.');
        header('Location: crm.php');
        exit;
    }

    $clientKey = trim((string) ($_POST['client_key'] ?? ''));
    $status = trim((string) ($_POST['client_status'] ?? 'potencial'));
    $note = trim((string) ($_POST['note'] ?? ''));
    if ($clientKey === '' || !isset($clientStatuses[$status])) {
        flash_set('error', 'Cliente o estado invalido.');
        header('Location: crm.php');
        exit;
    }

    $clientSnapshot = vendor_crm_client_snapshot($sellerId, $clientKey, $hasClients);
    if (!$clientSnapshot) {
        flash_set('error', 'Cliente no encontrado para este vendedor.');
        header('Location: crm.php');
        exit;
    }

    $profileId = vendor_crm_upsert_profile($sellerId, $clientKey, $status, $clientSnapshot);
    if ($note !== '') {
        vendor_crm_add_note($sellerId, $profileId, $clientKey, $note, $userId);
        flash_set('success', 'Ficha actualizada y nota guardada.');
    } else {
        flash_set('success', 'Ficha actualizada.');
    }

    header('Location: crm.php?client=' . rawurlencode($clientKey));
    exit;
}

$selectedKey = trim((string) ($_GET['client'] ?? ''));
$clients = $schemaReady && $sellerId > 0 ? vendor_crm_clients($sellerId, $hasClients) : [];
if ($selectedKey === '' && $clients) {
    $selectedKey = (string) $clients[0]['client_key'];
}
$selectedClient = $selectedKey !== '' && $schemaReady ? vendor_crm_client_snapshot($sellerId, $selectedKey, $hasClients) : null;
$selectedProfile = $selectedKey !== '' && $schemaReady ? vendor_crm_profile($sellerId, $selectedKey) : null;
$selectedNotes = $selectedKey !== '' && $schemaReady ? vendor_crm_notes($sellerId, $selectedKey) : [];
$selectedOrders = $selectedKey !== '' && $schemaReady ? vendor_crm_orders($sellerId, $selectedKey) : [];

vendor_header('Mini CRM', 'crm.php');
?>
<?php if (!$schemaReady): ?>
    <section class="card">
        <strong>Mini CRM pendiente de migracion.</strong>
        <p class="muted">Ejecuta <code>hosting/sql/20260506_vendor_mini_crm.sql</code> para activar notas y clasificacion de clientes.</p>
    </section>
<?php else: ?>
<div class="split">
    <section class="card">
        <div class="toolbar"><strong>Clientes con pedidos</strong><span class="pill"><?= count($clients) ?></span></div>
        <div class="list">
            <?php foreach ($clients as $client): ?>
                <div class="list-item">
                    <div class="toolbar">
                        <strong><?= html_escape($client['client_name']) ?></strong>
                        <span class="pill"><?= html_escape($clientStatuses[$client['client_status']] ?? 'Potencial') ?></span>
                    </div>
                    <div class="muted"><?= html_escape($client['contact_name'] ?: $client['phone'] ?: $client['email'] ?: 'Sin contacto') ?></div>
                    <div class="metrics-inline">
                        <span class="pill"><?= (int) $client['orders_count'] ?> pedidos</span>
                        <span class="pill"><?= html_escape(number_format((float) $client['total_purchased'], 2)) ?></span>
                    </div>
                    <a class="button" href="crm.php?client=<?= rawurlencode((string) $client['client_key']) ?>">Abrir ficha</a>
                </div>
            <?php endforeach; ?>
            <?php if (!$clients): ?>
                <div class="list-item"><div class="muted">Aun no hay clientes con pedidos para este vendedor.</div></div>
            <?php endif; ?>
        </div>
    </section>

    <section class="card">
        <?php if ($selectedClient): ?>
            <div class="toolbar">
                <strong><?= html_escape($selectedClient['client_name']) ?></strong>
                <span class="pill"><?= html_escape($clientStatuses[$selectedProfile['client_status'] ?? 'potencial'] ?? 'Potencial') ?></span>
            </div>
            <div class="grid grid--cards" style="margin-top:12px;">
                <div class="card"><div class="stat__label">Pedidos</div><div class="stat__value"><?= (int) $selectedClient['orders_count'] ?></div></div>
                <div class="card"><div class="stat__label">Total comprado</div><div class="stat__value" style="font-size:22px;"><?= html_escape(number_format((float) $selectedClient['total_purchased'], 2)) ?></div></div>
            </div>
            <p class="muted">
                Contacto: <?= html_escape($selectedClient['contact_name'] ?: 'Sin contacto') ?><br>
                Tel: <?= html_escape($selectedClient['phone'] ?: 'Sin telefono') ?> · Correo: <?= html_escape($selectedClient['email'] ?: 'Sin correo') ?><br>
                Ultimo pedido: <?= html_escape($selectedClient['last_order_at']) ?>
            </p>
            <form method="post" class="form-grid" style="margin-top:16px;">
                <?= csrf_field() ?>
                <input type="hidden" name="client_key" value="<?= html_escape($selectedKey) ?>">
                <label>
                    <span>Clasificacion</span>
                    <select name="client_status">
                        <?php foreach ($clientStatuses as $value => $label): ?>
                            <option value="<?= html_escape($value) ?>" <?= (($selectedProfile['client_status'] ?? 'potencial') === $value) ? 'selected' : '' ?>><?= html_escape($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="wide">
                    <span>Nueva nota</span>
                    <textarea name="note" rows="4" placeholder="Ejemplo: llamar el viernes para reposicion."></textarea>
                </label>
                <div class="wide toolbar__actions">
                    <button class="button--primary" type="submit">Guardar ficha</button>
                </div>
            </form>
        <?php else: ?>
            <p class="muted">Selecciona un cliente para abrir su ficha comercial.</p>
        <?php endif; ?>
    </section>
</div>

<?php if ($selectedClient): ?>
<div class="split" style="margin-top:18px;">
    <section class="card">
        <div class="toolbar"><strong>Historial de notas</strong><span class="pill"><?= count($selectedNotes) ?></span></div>
        <div class="list">
            <?php foreach ($selectedNotes as $note): ?>
                <div class="list-item">
                    <div class="muted"><?= html_escape($note['created_at']) ?></div>
                    <strong><?= html_escape($note['note']) ?></strong>
                </div>
            <?php endforeach; ?>
            <?php if (!$selectedNotes): ?>
                <div class="list-item"><div class="muted">Este cliente aun no tiene notas.</div></div>
            <?php endif; ?>
        </div>
    </section>
    <section class="card">
        <div class="toolbar"><strong>Historial de pedidos</strong><span class="pill"><?= count($selectedOrders) ?></span></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Pedido</th><th>Total</th><th>Estado</th><th>Fecha</th></tr></thead>
                <tbody>
                <?php foreach ($selectedOrders as $order): ?>
                    <tr>
                        <td><strong><?= html_escape($order['order_number']) ?></strong></td>
                        <td><?= html_escape(number_format((float) $order['total'], 2)) ?></td>
                        <td><?= admin_status_badge((string) $order['status']) ?></td>
                        <td><?= html_escape($order['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$selectedOrders): ?>
                    <tr><td colspan="4" class="muted">No hay pedidos para este cliente.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<?php endif; ?>
<?php endif; ?>
<?php vendor_footer(); ?>

<?php
function vendor_crm_order_fields(): array
{
    return [
        'company' => vendor_column_exists('orders', 'company_name') ? 'o.company_name' : "''",
        'contact' => vendor_column_exists('orders', 'contact_name') ? 'o.contact_name' : "''",
        'email' => vendor_column_exists('orders', 'contact_email') ? 'o.contact_email' : "''",
        'phone' => vendor_column_exists('orders', 'contact_phone') ? 'o.contact_phone' : "''",
    ];
}

function vendor_crm_client_key_expr(array $fields): string
{
    return "CASE
        WHEN o.client_id IS NOT NULL THEN CONCAT('client-', o.client_id)
        WHEN {$fields['email']} <> '' THEN CONCAT('email-', LOWER({$fields['email']}))
        WHEN {$fields['phone']} <> '' THEN CONCAT('phone-', {$fields['phone']})
        WHEN {$fields['company']} <> '' THEN CONCAT('company-', LOWER({$fields['company']}))
        ELSE CONCAT('order-', o.id)
    END";
}

function vendor_crm_base_select(bool $hasClients, array $fields): string
{
    $nameExpr = $hasClients
        ? "COALESCE(NULLIF(MAX(cl.business_name), ''), NULLIF(MAX({$fields['company']}), ''), NULLIF(MAX({$fields['contact']}), ''), 'Cliente sin nombre')"
        : "COALESCE(NULLIF(MAX({$fields['company']}), ''), NULLIF(MAX({$fields['contact']}), ''), 'Cliente sin nombre')";
    $contactExpr = $hasClients
        ? "COALESCE(NULLIF(MAX(cl.contact_name), ''), NULLIF(MAX({$fields['contact']}), ''), '')"
        : "COALESCE(NULLIF(MAX({$fields['contact']}), ''), '')";
    $emailExpr = $hasClients
        ? "COALESCE(NULLIF(MAX(cl.email), ''), NULLIF(MAX({$fields['email']}), ''), '')"
        : "COALESCE(NULLIF(MAX({$fields['email']}), ''), '')";
    $phoneExpr = $hasClients
        ? "COALESCE(NULLIF(MAX(cl.phone), ''), NULLIF(MAX({$fields['phone']}), ''), '')"
        : "COALESCE(NULLIF(MAX({$fields['phone']}), ''), '')";

    return vendor_crm_client_key_expr($fields) . " AS client_key,
        MAX(o.client_id) AS client_id,
        {$nameExpr} AS client_name,
        {$contactExpr} AS contact_name,
        {$emailExpr} AS email,
        {$phoneExpr} AS phone,
        COUNT(*) AS orders_count,
        COALESCE(SUM(o.total), 0) AS total_purchased,
        MAX(o.created_at) AS last_order_at";
}

function vendor_crm_clients(int $sellerId, bool $hasClients): array
{
    $fields = vendor_crm_order_fields();
    $clientJoin = $hasClients ? 'LEFT JOIN clients cl ON cl.id = o.client_id' : '';
    $clientKeyExpr = vendor_crm_client_key_expr($fields);
    $select = vendor_crm_base_select($hasClients, $fields);
    $statement = db()->prepare(
        "SELECT crm.client_status, base.*
         FROM (
             SELECT {$select}
             FROM orders o
             {$clientJoin}
             WHERE o.seller_id = :seller_id
             GROUP BY client_key
         ) base
         LEFT JOIN vendor_client_profiles crm
           ON crm.seller_id = :seller_id_profile AND crm.client_key = base.client_key
         ORDER BY base.last_order_at DESC
         LIMIT 80"
    );
    $statement->execute(['seller_id' => $sellerId, 'seller_id_profile' => $sellerId]);
    return $statement->fetchAll();
}

function vendor_crm_client_snapshot(int $sellerId, string $clientKey, bool $hasClients): ?array
{
    $fields = vendor_crm_order_fields();
    $clientJoin = $hasClients ? 'LEFT JOIN clients cl ON cl.id = o.client_id' : '';
    $clientKeyExpr = vendor_crm_client_key_expr($fields);
    $select = vendor_crm_base_select($hasClients, $fields);
    $statement = db()->prepare(
        "SELECT *
         FROM (
             SELECT {$select}
             FROM orders o
             {$clientJoin}
             WHERE o.seller_id = :seller_id
             GROUP BY client_key
         ) base
         WHERE base.client_key = :client_key
         LIMIT 1"
    );
    $statement->execute(['seller_id' => $sellerId, 'client_key' => $clientKey]);
    $row = $statement->fetch();
    return $row ?: null;
}

function vendor_crm_profile(int $sellerId, string $clientKey): ?array
{
    $statement = db()->prepare('SELECT * FROM vendor_client_profiles WHERE seller_id = :seller_id AND client_key = :client_key LIMIT 1');
    $statement->execute(['seller_id' => $sellerId, 'client_key' => $clientKey]);
    $row = $statement->fetch();
    return $row ?: null;
}

function vendor_crm_upsert_profile(int $sellerId, string $clientKey, string $status, array $client): int
{
    $statement = db()->prepare(
        'INSERT INTO vendor_client_profiles (seller_id, client_id, client_key, client_name, contact_name, email, phone, client_status)
         VALUES (:seller_id, :client_id, :client_key, :client_name, :contact_name, :email, :phone, :client_status)
         ON DUPLICATE KEY UPDATE
           client_id = VALUES(client_id),
           client_name = VALUES(client_name),
           contact_name = VALUES(contact_name),
           email = VALUES(email),
           phone = VALUES(phone),
           client_status = VALUES(client_status)'
    );
    $statement->execute([
        'seller_id' => $sellerId,
        'client_id' => !empty($client['client_id']) ? (int) $client['client_id'] : null,
        'client_key' => $clientKey,
        'client_name' => (string) ($client['client_name'] ?? ''),
        'contact_name' => (string) ($client['contact_name'] ?? ''),
        'email' => (string) ($client['email'] ?? ''),
        'phone' => (string) ($client['phone'] ?? ''),
        'client_status' => $status,
    ]);
    $profile = vendor_crm_profile($sellerId, $clientKey);
    return (int) ($profile['id'] ?? 0);
}

function vendor_crm_add_note(int $sellerId, int $profileId, string $clientKey, string $note, int $userId): void
{
    $statement = db()->prepare(
        'INSERT INTO vendor_client_notes (seller_id, profile_id, client_key, note, created_by)
         VALUES (:seller_id, :profile_id, :client_key, :note, :created_by)'
    );
    $statement->execute([
        'seller_id' => $sellerId,
        'profile_id' => $profileId > 0 ? $profileId : null,
        'client_key' => $clientKey,
        'note' => $note,
        'created_by' => $userId > 0 ? $userId : null,
    ]);
    db()->prepare('UPDATE vendor_client_profiles SET last_note_at = NOW() WHERE id = :id')->execute(['id' => $profileId]);
}

function vendor_crm_notes(int $sellerId, string $clientKey): array
{
    $statement = db()->prepare(
        'SELECT note, created_at
         FROM vendor_client_notes
         WHERE seller_id = :seller_id AND client_key = :client_key
         ORDER BY created_at DESC, id DESC
         LIMIT 30'
    );
    $statement->execute(['seller_id' => $sellerId, 'client_key' => $clientKey]);
    return $statement->fetchAll();
}

function vendor_crm_orders(int $sellerId, string $clientKey): array
{
    $fields = vendor_crm_order_fields();
    $clientKeyExpr = vendor_crm_client_key_expr($fields);
    $orderNumberExpr = vendor_column_exists('orders', 'order_number') ? 'o.order_number' : "CONCAT('PED-', o.id)";
    $statusExpr = vendor_column_exists('orders', 'status') ? 'o.status' : "'new'";
    $statement = db()->prepare(
        "SELECT o.id, {$orderNumberExpr} AS order_number, o.total, {$statusExpr} AS status, o.created_at
         FROM orders o
         WHERE o.seller_id = :seller_id
           AND {$clientKeyExpr} = :client_key
         ORDER BY o.created_at DESC
         LIMIT 20"
    );
    $statement->execute(['seller_id' => $sellerId, 'client_key' => $clientKey]);
    return $statement->fetchAll();
}
