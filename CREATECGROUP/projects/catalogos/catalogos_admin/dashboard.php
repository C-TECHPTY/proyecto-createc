<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
admin_require_login();

$hasSellers = admin_table_exists('sellers');
$hasLinks = admin_table_exists('catalog_share_links');
$hasAccessLogs = admin_table_exists('catalog_access_logs');
$hasOrders = admin_table_exists('orders');
$hasCatalogs = admin_table_exists('catalogs');
$ordersHasSeller = $hasOrders && admin_column_exists('orders', 'seller_id');
$ordersHasCreatedAt = $hasOrders && admin_column_exists('orders', 'created_at');
$ordersHasStatus = $hasOrders && admin_column_exists('orders', 'status');
$ordersHasTotal = $hasOrders && admin_column_exists('orders', 'total');
$ordersHasCompany = $hasOrders && admin_column_exists('orders', 'company_name');
$ordersHasContact = $hasOrders && admin_column_exists('orders', 'contact_name');
$catalogsHasStatus = $hasCatalogs && admin_column_exists('catalogs', 'status');
$catalogsHasTitle = $hasCatalogs && admin_column_exists('catalogs', 'title');

$stats = [
    'orders_recent' => $hasOrders ? (int) db()->query($ordersHasCreatedAt ? "SELECT COUNT(*) FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)" : "SELECT COUNT(*) FROM orders")->fetchColumn() : 0,
    'catalogs_active' => $hasCatalogs ? (int) db()->query($catalogsHasStatus ? "SELECT COUNT(*) FROM catalogs WHERE status = 'active'" : "SELECT COUNT(*) FROM catalogs")->fetchColumn() : 0,
    'sellers_active' => $hasSellers ? (int) db()->query("SELECT COUNT(*) FROM sellers WHERE is_active = 1")->fetchColumn() : 0,
    'access_today' => $hasAccessLogs ? (int) db()->query("SELECT COUNT(*) FROM catalog_access_logs WHERE DATE(visited_at) = CURDATE()")->fetchColumn() : 0,
    'links_active' => $hasLinks ? (int) db()->query("SELECT COUNT(*) FROM catalog_share_links WHERE is_active = 1")->fetchColumn() : 0,
];

$recentOrders = [];
if ($hasOrders) {
    $orderNumberExpr = admin_column_exists('orders', 'order_number') ? 'o.order_number' : "CONCAT('PED-', o.id)";
    $companyExpr = $ordersHasCompany ? 'o.company_name' : "''";
    $contactExpr = $ordersHasContact ? 'o.contact_name' : (admin_column_exists('orders', 'customer_name') ? 'o.customer_name' : "''");
    $totalExpr = $ordersHasTotal ? 'o.total' : '0';
    $statusExpr = $ordersHasStatus ? 'o.status' : "'new'";
    $createdExpr = $ordersHasCreatedAt ? 'o.created_at' : "''";
    $catalogTitleExpr = $hasCatalogs && $catalogsHasTitle ? 'c.title' : "''";
    $catalogJoin = $hasCatalogs && admin_column_exists('orders', 'catalog_id') ? 'LEFT JOIN catalogs c ON c.id = o.catalog_id' : '';
    $sellerExpr = $hasSellers && $ordersHasSeller ? 's.name' : "''";
    $sellerJoin = $hasSellers && $ordersHasSeller ? 'LEFT JOIN sellers s ON s.id = o.seller_id' : '';
    $orderBy = $ordersHasCreatedAt ? 'o.created_at DESC' : 'o.id DESC';
    $recentOrders = db()->query(
        "SELECT o.id, {$orderNumberExpr} AS order_number, {$companyExpr} AS company_name, {$contactExpr} AS contact_name,
                {$totalExpr} AS total, {$statusExpr} AS status, {$createdExpr} AS created_at,
                {$catalogTitleExpr} AS catalog_title, {$sellerExpr} AS seller_name
         FROM orders o
         {$catalogJoin}
         {$sellerJoin}
         ORDER BY {$orderBy}
         LIMIT 8"
    )->fetchAll();
}

$recentAccess = [];
if ($hasAccessLogs && $hasCatalogs) {
    $accessSellerJoin = $hasSellers && admin_column_exists('catalog_access_logs', 'seller_id') ? 'LEFT JOIN sellers s ON s.id = l.seller_id' : '';
    $accessSellerExpr = $hasSellers && admin_column_exists('catalog_access_logs', 'seller_id') ? 's.name' : "''";
    $clientsReady = admin_table_exists('clients') && admin_column_exists('catalog_access_logs', 'client_id');
    $clientJoin = $clientsReady ? 'LEFT JOIN clients cl ON cl.id = l.client_id' : '';
    $clientExpr = $clientsReady ? 'cl.business_name' : "''";
    $recentAccess = db()->query(
        "SELECT l.visited_at, c.title, {$accessSellerExpr} AS seller_name, {$clientExpr} AS client_name
         FROM catalog_access_logs l
         INNER JOIN catalogs c ON c.id = l.catalog_id
         {$accessSellerJoin}
         {$clientJoin}
         ORDER BY l.visited_at DESC
         LIMIT 8"
    )->fetchAll();
}

admin_header('Dashboard', 'dashboard.php');
?>
<section class="dashboard-hero">
    <div class="dashboard-hero__content">
        <span class="pill">Operacion central</span>
        <h3><?= html_escape(app_setting('branding_company_name', (string) catalog_config('app_name', 'Catalogo Rodeo B2B'))) ?></h3>
        <p>Resumen ejecutivo del catalogo, pedidos, accesos y red comercial en un mismo panel.</p>
    </div>
    <div class="dashboard-hero__stats">
        <div class="hero-mini-card">
            <span>Catalogos</span>
            <strong><?= $stats['catalogs_active'] ?></strong>
        </div>
        <div class="hero-mini-card">
            <span>Pedidos</span>
            <strong><?= $stats['orders_recent'] ?></strong>
        </div>
        <div class="hero-mini-card">
            <span>Accesos hoy</span>
            <strong><?= $stats['access_today'] ?></strong>
        </div>
    </div>
</section>
<div class="grid grid--cards">
    <div class="card"><div class="stat__label">Catalogos activos</div><div class="stat__value"><?= $stats['catalogs_active'] ?></div></div>
    <div class="card"><div class="stat__label">Pedidos 30 dias</div><div class="stat__value"><?= $stats['orders_recent'] ?></div></div>
    <div class="card"><div class="stat__label">Vendedores activos</div><div class="stat__value"><?= $stats['sellers_active'] ?></div></div>
    <div class="card"><div class="stat__label">Links activos</div><div class="stat__value"><?= $stats['links_active'] ?></div></div>
</div>
<div class="grid grid--cards dashboard-secondary">
    <div class="card"><div class="stat__label">Accesos hoy</div><div class="stat__value"><?= $stats['access_today'] ?></div></div>
    <div class="card"><div class="stat__label">Operacion</div><div class="stat__value"><?= $stats['catalogs_active'] + $stats['links_active'] ?></div></div>
    <div class="card"><div class="stat__label">Ventas</div><div class="stat__value"><?= $stats['orders_recent'] ?></div></div>
    <div class="card"><div class="stat__label">Red comercial</div><div class="stat__value"><?= $stats['sellers_active'] ?></div></div>
</div>
<div class="split" style="margin-top:18px;">
    <section class="card">
        <div class="toolbar"><strong>Pedidos recientes</strong></div>
        <div class="list">
            <?php foreach ($recentOrders as $order): ?>
                <div class="list-item">
                    <div class="toolbar">
                        <div>
                            <strong><?= html_escape($order['order_number']) ?></strong>
                            <div class="muted"><?= html_escape($order['company_name'] ?: $order['contact_name']) ?></div>
                            <div class="muted"><?= html_escape($order['catalog_title']) ?> / <?= html_escape($order['seller_name'] ?: 'Sin vendedor') ?></div>
                        </div>
                        <?= admin_status_badge((string) $order['status']) ?>
                    </div>
                    <div class="metrics-inline">
                        <span class="pill"><?= html_escape(number_format((float) $order['total'], 2)) ?></span>
                        <span class="pill"><?= html_escape($order['created_at']) ?></span>
                        <a class="button" href="pedidos.php?id=<?= (int) $order['id'] ?>">Ver</a>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (!$recentOrders): ?>
                <div class="list-item"><div class="muted">Todavia no hay pedidos recientes para mostrar.</div></div>
            <?php endif; ?>
        </div>
    </section>
    <section class="card">
        <div class="toolbar"><strong>Accesos recientes</strong></div>
        <div class="list">
            <?php foreach ($recentAccess as $access): ?>
                <div class="list-item">
                    <strong><?= html_escape($access['title']) ?></strong>
                    <div class="muted"><?= html_escape($access['seller_name'] ?: 'Sin vendedor') ?> / <?= html_escape($access['client_name'] ?: 'Sin cliente') ?></div>
                    <div class="muted"><?= html_escape($access['visited_at']) ?></div>
                </div>
            <?php endforeach; ?>
            <?php if (!$recentAccess): ?>
                <div class="list-item"><div class="muted">Todavia no hay accesos recientes para mostrar.</div></div>
            <?php endif; ?>
        </div>
    </section>
</div>
<?php admin_footer(); ?>
