<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
admin_require_login(['admin', 'billing', 'sales', 'operator']);

$hasOrders = admin_table_exists('orders');
$orders = [];
if ($hasOrders) {
    $orderNumberExpr = admin_column_exists('orders', 'order_number') ? 'order_number' : "CONCAT('PED-', id)";
    $companyExpr = admin_column_exists('orders', 'company_name') ? 'company_name' : (admin_column_exists('orders', 'customer_name') ? 'customer_name' : "''");
    $totalExpr = admin_column_exists('orders', 'total') ? 'total' : '0';
    $statusExpr = admin_column_exists('orders', 'status') ? 'status' : "'new'";
    $createdExpr = admin_column_exists('orders', 'created_at') ? 'created_at' : "''";
    $orderBy = admin_column_exists('orders', 'created_at') ? 'created_at DESC' : 'id DESC';
    $orders = db()->query(
        "SELECT id, {$orderNumberExpr} AS order_number, {$companyExpr} AS company_name, {$totalExpr} AS total,
                {$statusExpr} AS status, {$createdExpr} AS created_at
         FROM orders
         ORDER BY {$orderBy}
         LIMIT 100"
    )->fetchAll();
}

admin_header('Exportaciones', 'exportaciones.php');
?>
<section class="card">
    <div class="toolbar"><strong>Exportacion operativa</strong><span class="pill">DB como fuente de verdad</span></div>
    <?php if (!$hasOrders): ?>
        <p class="muted">Falta la tabla de pedidos. Ejecuta la migracion SQL antes de exportar.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Pedido</th><th>Empresa</th><th>Total</th><th>Estado</th><th>Fecha</th><th>Descargas</th></tr></thead>
            <tbody>
            <?php foreach ($orders as $order): ?>
                <tr>
                    <td><?= html_escape($order['order_number']) ?></td>
                    <td><?= html_escape($order['company_name']) ?></td>
                    <td><?= html_escape(number_format((float) $order['total'], 2)) ?></td>
                    <td><?= admin_status_badge((string) $order['status']) ?></td>
                    <td><?= html_escape($order['created_at']) ?></td>
                    <td>
                        <div class="toolbar__actions">
                            <a class="button" href="../catalogos_api/export_order.php?id=<?= (int) $order['id'] ?>">CSV</a>
                            <a class="button" href="../catalogos_api/export_order.php?id=<?= (int) $order['id'] ?>&format=xlsx">XLSX</a>
                            <a class="button" href="../catalogos_api/export_order.php?id=<?= (int) $order['id'] ?>&format=pdf" target="_blank">PDF/Print</a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>
<?php admin_footer(); ?>
