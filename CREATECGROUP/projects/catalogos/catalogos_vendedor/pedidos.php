<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
vendor_require_panel_login();

$sellerId = (int) (vendor_current_user()['seller_id'] ?? 0);
$orderId = (int) ($_GET['id'] ?? 0);
$orders = [];
$order = null;
$items = [];
$schemaReady = vendor_table_exists('orders') && vendor_column_exists('orders', 'seller_id');
if ($schemaReady) {
    $orderNumberExpr = vendor_column_exists('orders', 'order_number') ? 'order_number' : "CONCAT('PED-', id)";
    $companyExpr = vendor_column_exists('orders', 'company_name') ? 'company_name' : (vendor_column_exists('orders', 'customer_name') ? 'customer_name' : "''");
    $contactExpr = vendor_column_exists('orders', 'contact_name') ? 'contact_name' : (vendor_column_exists('orders', 'customer_name') ? 'customer_name' : "''");
    $totalExpr = vendor_column_exists('orders', 'total') ? 'total' : '0';
    $statusExpr = vendor_column_exists('orders', 'status') ? 'status' : "'new'";
    $createdExpr = vendor_column_exists('orders', 'created_at') ? 'created_at' : "''";
    $orderBy = vendor_column_exists('orders', 'created_at') ? 'created_at DESC' : 'id DESC';
    if ($orderId > 0 && $sellerId > 0) {
        $detailStmt = db()->prepare(
            "SELECT id, {$orderNumberExpr} AS order_number, {$companyExpr} AS company_name, {$contactExpr} AS contact_name,
                    {$totalExpr} AS total, {$statusExpr} AS status, {$createdExpr} AS created_at
             FROM orders
             WHERE id = :id AND seller_id = :seller_id
             LIMIT 1"
        );
        $detailStmt->execute([
            'id' => $orderId,
            'seller_id' => $sellerId,
        ]);
        $order = $detailStmt->fetch() ?: null;

        if ($order && vendor_table_exists('order_items')) {
            $itemsStmt = db()->prepare('SELECT * FROM order_items WHERE order_id = :order_id ORDER BY id ASC');
            $itemsStmt->execute(['order_id' => $orderId]);
            $items = $itemsStmt->fetchAll();
        }
    }

    $stmt = db()->prepare(
        "SELECT id, {$orderNumberExpr} AS order_number, {$companyExpr} AS company_name, {$contactExpr} AS contact_name,
                {$totalExpr} AS total, {$statusExpr} AS status, {$createdExpr} AS created_at
         FROM orders
         WHERE seller_id = :seller_id
         ORDER BY {$orderBy}
         LIMIT 100"
    );
    $stmt->execute(['seller_id' => $sellerId]);
    $orders = $stmt->fetchAll();
}

vendor_header('Mis pedidos', 'pedidos.php');
?>
<?php if ($orderId > 0): ?>
    <section class="card">
        <?php if (!$schemaReady): ?>
            <p class="muted">El modulo de pedidos por vendedor requiere ejecutar la migracion B2B.</p>
        <?php elseif (!$order): ?>
            <div class="toolbar">
                <strong>Pedido no disponible</strong>
                <a class="button" href="pedidos.php">Volver</a>
            </div>
            <p class="muted">El pedido no existe o no esta asignado a este vendedor.</p>
        <?php else: ?>
            <div class="toolbar">
                <strong><?= html_escape($order['order_number']) ?></strong>
                <div class="toolbar__actions">
                    <?= admin_status_badge((string) $order['status']) ?>
                    <a class="button" href="../catalogos_api/export_order.php?id=<?= (int) $order['id'] ?>">CSV</a>
                    <a class="button" href="../catalogos_api/export_order.php?id=<?= (int) $order['id'] ?>&format=xlsx">XLSX</a>
                    <a class="button" href="../catalogos_api/export_order.php?id=<?= (int) $order['id'] ?>&format=pdf" target="_blank">PDF/Print</a>
                    <a class="button" href="pedidos.php">Volver</a>
                </div>
            </div>
            <div class="form-grid" style="margin-bottom:18px;">
                <?php $salesContact = sales_contact_info(); ?>
                <div><strong>Empresa</strong><div class="muted"><?= html_escape($order['company_name']) ?></div></div>
                <div><strong>Contacto</strong><div class="muted"><?= html_escape($order['contact_name']) ?></div></div>
                <div><strong>Contacto comercial</strong><div class="muted"><?= html_escape($salesContact['name']) ?></div></div>
                <div><strong>Correo comercial</strong><div class="muted"><?= html_escape($salesContact['email']) ?></div></div>
                <div><strong>Telefono comercial</strong><div class="muted"><?= html_escape($salesContact['phone']) ?></div></div>
                <div><strong>Total</strong><div class="muted"><?= html_escape(number_format((float) $order['total'], 2)) ?></div></div>
                <div><strong>Fecha</strong><div class="muted"><?= html_escape($order['created_at']) ?></div></div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>ITEM</th><th>Descripcion</th><th>Cantidad</th><th>Unidad</th><th>Empaque</th><th>Piezas</th><th>Total</th></tr></thead>
                    <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= html_escape($item['item_code'] ?? '') ?></td>
                            <td><?= html_escape($item['description'] ?? '') ?></td>
                            <td><?= html_escape(format_plain_number((float) ($item['quantity'] ?? 0))) ?></td>
                            <td><?= html_escape($item['sale_unit'] ?? 'unidad') ?></td>
                            <td><?= html_escape(trim((string) (($item['package_label'] ?? '') . ' ' . format_plain_number((float) ($item['package_qty'] ?? 0))))) ?></td>
                            <td><?= html_escape(format_plain_number((float) ($item['pieces_total'] ?? $item['quantity'] ?? 0))) ?></td>
                            <td><?= html_escape(number_format((float) ($item['line_total'] ?? $item['total'] ?? $item['price'] ?? 0), 2)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
    <?php vendor_footer(); exit; ?>
<?php endif; ?>
<section class="card">
    <div class="toolbar"><strong>Pedidos asociados</strong></div>
    <?php if (!$schemaReady): ?>
        <p class="muted">El modulo de pedidos por vendedor requiere ejecutar la migracion B2B.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Pedido</th><th>Empresa</th><th>Contacto</th><th>Total</th><th>Estado</th><th>Fecha</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($orders as $order): ?>
                <tr>
                    <td><?= html_escape($order['order_number']) ?></td>
                    <td><?= html_escape($order['company_name']) ?></td>
                    <td><?= html_escape($order['contact_name']) ?></td>
                    <td><?= html_escape(number_format((float) $order['total'], 2)) ?></td>
                    <td><?= admin_status_badge((string) $order['status']) ?></td>
                    <td><?= html_escape($order['created_at']) ?></td>
                    <td>
                        <div class="toolbar__actions">
                            <a class="button" href="pedidos.php?id=<?= (int) $order['id'] ?>">Ver</a>
                            <a class="button" href="../catalogos_api/export_order.php?id=<?= (int) $order['id'] ?>&format=xlsx">XLSX</a>
                            <a class="button" href="../catalogos_api/export_order.php?id=<?= (int) $order['id'] ?>&format=pdf" target="_blank">PDF</a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>
<?php vendor_footer(); ?>
