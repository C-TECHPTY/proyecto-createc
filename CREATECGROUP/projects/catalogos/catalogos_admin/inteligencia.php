<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
admin_require_login();

$hasEvents = admin_table_exists('catalog_behavior_events');
$hasOrders = admin_table_exists('orders');
$hasOrderItems = admin_table_exists('order_items');
$hasSellers = admin_table_exists('sellers');
$hasClients = admin_table_exists('clients');
$hasCatalogs = admin_table_exists('catalogs');
$allowedWindows = [7, 30, 90];
$eventsWindow = (int) ($_GET['days'] ?? 30);
if (!in_array($eventsWindow, $allowedWindows, true)) {
    $eventsWindow = 30;
}
$selectedSellerId = max(0, (int) ($_GET['seller_id'] ?? 0));
$selectedCategory = trim((string) ($_GET['category'] ?? ''));

$pdo = db();
$quotedCategory = $selectedCategory !== '' ? $pdo->quote($selectedCategory) : "''";
$eventConditions = ["created_at >= DATE_SUB(NOW(), INTERVAL {$eventsWindow} DAY)"];
$eventAliasConditions = ["e.created_at >= DATE_SUB(NOW(), INTERVAL {$eventsWindow} DAY)"];
$ordersConditions = [];
$orderAliasConditions = [];
$sellerOptions = [];
$categoryOptions = [];

if ($hasSellers && admin_column_exists('catalog_behavior_events', 'seller_id')) {
    $sellerOptions = $pdo->query("SELECT id, name FROM sellers WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
    if ($selectedSellerId > 0) {
        $eventConditions[] = "seller_id = {$selectedSellerId}";
        $eventAliasConditions[] = "e.seller_id = {$selectedSellerId}";
    }
}

if ($selectedCategory !== '' && admin_column_exists('catalog_behavior_events', 'category')) {
    $eventConditions[] = "category = {$quotedCategory}";
    $eventAliasConditions[] = "e.category = {$quotedCategory}";
}

if ($hasOrders && admin_column_exists('orders', 'created_at')) {
    $ordersConditions[] = "created_at >= DATE_SUB(NOW(), INTERVAL {$eventsWindow} DAY)";
    $orderAliasConditions[] = "o.created_at >= DATE_SUB(NOW(), INTERVAL {$eventsWindow} DAY)";
}
if ($hasOrders && $hasSellers && admin_column_exists('orders', 'seller_id') && $selectedSellerId > 0) {
    $ordersConditions[] = "seller_id = {$selectedSellerId}";
    $orderAliasConditions[] = "o.seller_id = {$selectedSellerId}";
}

$eventsWhereSql = implode(' AND ', $eventConditions);
$eventsAliasWhereSql = implode(' AND ', $eventAliasConditions);
$ordersWhereSql = $ordersConditions ? 'WHERE ' . implode(' AND ', $ordersConditions) : '';
$ordersAliasWhereSql = $orderAliasConditions ? 'WHERE ' . implode(' AND ', $orderAliasConditions) : '';

if ($hasEvents) {
    $categoryFilterConditions = ["created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)"];
    if ($selectedSellerId > 0 && admin_column_exists('catalog_behavior_events', 'seller_id')) {
        $categoryFilterConditions[] = "seller_id = {$selectedSellerId}";
    }
    $categoryOptions = $pdo->query(
        "SELECT DISTINCT category
         FROM catalog_behavior_events
         WHERE " . implode(' AND ', $categoryFilterConditions) . "
           AND category <> ''
         ORDER BY category ASC"
    )->fetchAll(PDO::FETCH_COLUMN);
}

$stats = [
    'events' => 0,
    'visitors' => 0,
    'product_interest' => 0,
    'cart_adds' => 0,
    'searches' => 0,
    'orders' => 0,
    'sales_total' => 0,
];
$topProducts = [];
$topSearches = [];
$promotionCandidates = [];
$sellerPerformance = [];
$clientSignals = [];
$highIntentProducts = [];
$topPurchasedProducts = [];
$categorySignals = [];
$clientOpportunities = [];
$sellerConversion = [];
$bundledProducts = [];
$dormantClients = [];
$clientRecommendations = [];
$topPurchasedPreview = [];
$topSearchesPreview = [];
$clientOpportunitiesPreview = [];
$promotionCandidatesPreview = [];

if ($hasEvents) {
    $stats['events'] = (int) db()->query("SELECT COUNT(*) FROM catalog_behavior_events WHERE {$eventsWhereSql}")->fetchColumn();
    $stats['visitors'] = (int) db()->query("SELECT COUNT(DISTINCT NULLIF(visitor_id, '')) FROM catalog_behavior_events WHERE {$eventsWhereSql}")->fetchColumn();
    $stats['product_interest'] = (int) db()->query("SELECT COUNT(*) FROM catalog_behavior_events WHERE {$eventsWhereSql} AND event_type IN ('product_detail','add_to_cart')")->fetchColumn();
    $stats['cart_adds'] = (int) db()->query("SELECT COUNT(*) FROM catalog_behavior_events WHERE {$eventsWhereSql} AND event_type = 'add_to_cart'")->fetchColumn();
    $stats['searches'] = (int) db()->query("SELECT COUNT(*) FROM catalog_behavior_events WHERE {$eventsWhereSql} AND event_type = 'search'")->fetchColumn();

    $topProducts = db()->query(
        "SELECT item_code, MAX(item_name) AS item_name, MAX(category) AS category,
                SUM(event_type = 'product_detail') AS views_count,
                SUM(event_type = 'add_to_cart') AS cart_count,
                COUNT(*) AS interest_score,
                MAX(created_at) AS last_event_at
         FROM catalog_behavior_events
         WHERE item_code <> ''
           AND event_type IN ('product_detail','add_to_cart','cart_quantity')
           AND {$eventsWhereSql}
         GROUP BY item_code
         ORDER BY interest_score DESC, cart_count DESC
         LIMIT 12"
    )->fetchAll();

    $topSearches = db()->query(
        "SELECT search_term, COUNT(*) AS searches_count, MAX(created_at) AS last_search_at
         FROM catalog_behavior_events
         WHERE event_type = 'search'
           AND search_term <> ''
           AND {$eventsWhereSql}
         GROUP BY search_term
         ORDER BY searches_count DESC, last_search_at DESC
         LIMIT 10"
    )->fetchAll();

    $clientNameExpr = $hasClients ? 'COALESCE(cl.business_name, e.visitor_id)' : 'e.visitor_id';
    $clientJoin = $hasClients ? 'LEFT JOIN clients cl ON cl.id = e.client_id' : '';
    $clientSignals = db()->query(
        "SELECT e.client_id, {$clientNameExpr} AS client_name,
                MAX(e.category) AS category,
                COUNT(*) AS events_count,
                SUM(e.event_type = 'add_to_cart') AS cart_count,
                MAX(e.created_at) AS last_event_at
         FROM catalog_behavior_events e
         {$clientJoin}
         WHERE {$eventsAliasWhereSql}
           AND (e.client_id IS NOT NULL OR e.visitor_id <> '')
         GROUP BY e.client_id, e.visitor_id, client_name
         ORDER BY cart_count DESC, events_count DESC, last_event_at DESC
         LIMIT 10"
    )->fetchAll();

    if ($hasOrderItems) {
        $orderItemsQtyExpr = admin_column_exists('order_items', 'quantity') ? 'SUM(quantity)' : '0';
        $promotionCandidates = db()->query(
            "SELECT e.item_code, MAX(e.item_name) AS item_name, MAX(e.category) AS category,
                    SUM(e.event_type = 'product_detail') AS views_count,
                    SUM(e.event_type = 'add_to_cart') AS cart_count,
                    COUNT(DISTINCT e.visitor_id) AS interested_visitors,
                    COALESCE(MAX(oi.ordered_qty), 0) AS ordered_qty
             FROM catalog_behavior_events e
             LEFT JOIN (
                SELECT item_code, {$orderItemsQtyExpr} AS ordered_qty
                FROM order_items
                GROUP BY item_code
             ) oi ON oi.item_code = e.item_code
             WHERE e.item_code <> ''
               AND {$eventsAliasWhereSql}
               AND e.event_type IN ('product_detail','add_to_cart')
             GROUP BY e.item_code
             HAVING interested_visitors >= 1
             ORDER BY ordered_qty ASC, cart_count DESC, views_count DESC
             LIMIT 10"
        )->fetchAll();

        $highIntentProducts = db()->query(
            "SELECT e.item_code, MAX(e.item_name) AS item_name, MAX(e.category) AS category,
                    SUM(e.event_type = 'product_detail') AS views_count,
                    SUM(e.event_type = 'add_to_cart') AS cart_count,
                    COUNT(DISTINCT NULLIF(e.visitor_id, '')) AS interested_visitors,
                    COALESCE(MAX(oi.ordered_qty), 0) AS ordered_qty
             FROM catalog_behavior_events e
             LEFT JOIN (
                SELECT item_code, {$orderItemsQtyExpr} AS ordered_qty
                FROM order_items
                GROUP BY item_code
             ) oi ON oi.item_code = e.item_code
             WHERE e.item_code <> ''
               AND {$eventsAliasWhereSql}
               AND e.event_type IN ('product_detail','add_to_cart','cart_quantity')
             GROUP BY e.item_code
             HAVING views_count >= 2 OR cart_count >= 1
             ORDER BY cart_count DESC, interested_visitors DESC, views_count DESC
             LIMIT 12"
        )->fetchAll();
    }

    $categorySignals = db()->query(
        "SELECT category,
                COUNT(*) AS events_count,
                SUM(event_type = 'product_detail') AS views_count,
                SUM(event_type = 'add_to_cart') AS cart_count,
                COUNT(DISTINCT NULLIF(visitor_id, '')) AS visitors_count,
                MAX(created_at) AS last_event_at
         FROM catalog_behavior_events
         WHERE category <> ''
           AND {$eventsWhereSql}
           AND event_type IN ('product_detail','add_to_cart','cart_quantity','search')
         GROUP BY category
         ORDER BY cart_count DESC, events_count DESC, visitors_count DESC
         LIMIT 10"
    )->fetchAll();

    $clientNameExpr = $hasClients ? 'COALESCE(cl.business_name, e.visitor_id)' : 'e.visitor_id';
    $clientJoin = $hasClients ? 'LEFT JOIN clients cl ON cl.id = e.client_id' : '';
    $clientOpportunities = db()->query(
        "SELECT e.client_id, e.visitor_id, {$clientNameExpr} AS client_name,
                MAX(e.category) AS category,
                MAX(e.item_code) AS item_code,
                MAX(e.item_name) AS item_name,
                COUNT(*) AS events_count,
                SUM(e.event_type = 'product_detail') AS views_count,
                SUM(e.event_type = 'add_to_cart') AS cart_count,
                MAX(e.created_at) AS last_event_at
         FROM catalog_behavior_events e
         {$clientJoin}
         WHERE {$eventsAliasWhereSql}
           AND e.event_type IN ('product_detail','add_to_cart','cart_quantity','search')
           AND (e.client_id IS NOT NULL OR e.visitor_id <> '')
         GROUP BY e.client_id, e.visitor_id, client_name
         HAVING cart_count > 0 OR events_count >= 3
         ORDER BY cart_count DESC, events_count DESC, last_event_at DESC
         LIMIT 12"
    )->fetchAll();
}

if ($hasOrderItems && $hasOrders) {
    $itemsDate = $ordersAliasWhereSql;
    $itemDescriptionExpr = admin_column_exists('order_items', 'description') ? 'MAX(oi.description)' : "''";
    $itemQtyExpr = admin_column_exists('order_items', 'quantity') ? 'SUM(oi.quantity)' : '0';
    $itemTotalExpr = admin_column_exists('order_items', 'line_total') ? 'SUM(oi.line_total)' : '0';
    $topPurchasedProducts = db()->query(
        "SELECT oi.item_code,
                {$itemDescriptionExpr} AS item_name,
                {$itemQtyExpr} AS ordered_qty,
                {$itemTotalExpr} AS ordered_total,
                COUNT(DISTINCT oi.order_id) AS orders_count
         FROM order_items oi
         INNER JOIN orders o ON o.id = oi.order_id
         {$itemsDate}
         GROUP BY oi.item_code
         ORDER BY ordered_total DESC, ordered_qty DESC
         LIMIT 12"
    )->fetchAll();

    $bundleDateCondition = $orderAliasConditions ? 'AND ' . implode(' AND ', $orderAliasConditions) : '';
    $bundledProducts = db()->query(
        "SELECT a.item_code AS item_a,
                MAX(a.description) AS item_a_name,
                b.item_code AS item_b,
                MAX(b.description) AS item_b_name,
                COUNT(DISTINCT a.order_id) AS together_orders
         FROM order_items a
         INNER JOIN order_items b
            ON b.order_id = a.order_id
           AND b.item_code > a.item_code
         INNER JOIN orders o ON o.id = a.order_id
         WHERE a.item_code <> ''
           AND b.item_code <> ''
           {$bundleDateCondition}
         GROUP BY a.item_code, b.item_code
         HAVING together_orders >= 2
         ORDER BY together_orders DESC, item_a ASC, item_b ASC
         LIMIT 10"
    )->fetchAll();
}

if ($hasClients && $hasOrders) {
    $ordersHasClientId = admin_column_exists('orders', 'client_id');
    $ordersHasCreatedAtForClients = admin_column_exists('orders', 'created_at');
    $ordersHasTotalForClients = admin_column_exists('orders', 'total');
    if ($ordersHasClientId) {
        $clientLastOrderExpr = $ordersHasCreatedAtForClients ? 'MAX(o.created_at)' : "''";
        $clientTotalExpr = $ordersHasTotalForClients ? 'COALESCE(SUM(o.total), 0)' : '0';
        $dormantClients = db()->query(
            "SELECT c.id,
                    c.business_name,
                    c.contact_name,
                    {$clientLastOrderExpr} AS last_order_at,
                    COUNT(o.id) AS orders_count,
                    {$clientTotalExpr} AS sales_total
             FROM clients c
             INNER JOIN orders o ON o.client_id = c.id
             " . ($orderAliasConditions ? 'WHERE ' . implode(' AND ', $orderAliasConditions) : '') . "
             GROUP BY c.id, c.business_name, c.contact_name
             HAVING last_order_at <> ''
                AND last_order_at < DATE_SUB(NOW(), INTERVAL 45 DAY)
             ORDER BY last_order_at ASC, sales_total DESC
             LIMIT 10"
        )->fetchAll();
    }
}

if ($hasEvents && $hasOrders && $hasSellers && admin_column_exists('orders', 'seller_id')) {
    $ordersHasCreatedAtForSeller = admin_column_exists('orders', 'created_at');
    $ordersHasTotalForSeller = admin_column_exists('orders', 'total');
    $sellerOrdersDate = $ordersWhereSql;
    $sellerOrdersTotal = $ordersHasTotalForSeller ? 'SUM(total)' : '0';
    $sellerConversion = db()->query(
        "SELECT COALESCE(s.name, CONCAT('Vendedor ', activity.seller_id)) AS seller_name,
                activity.events_count,
                activity.cart_count,
                COALESCE(sales.orders_count, 0) AS orders_count,
                COALESCE(sales.sales_total, 0) AS sales_total
         FROM (
            SELECT seller_id,
                   COUNT(*) AS events_count,
                   SUM(event_type = 'add_to_cart') AS cart_count
            FROM catalog_behavior_events
            WHERE seller_id IS NOT NULL
              AND {$eventsWhereSql}
            GROUP BY seller_id
         ) activity
         LEFT JOIN (
            SELECT seller_id,
                   COUNT(*) AS orders_count,
                   {$sellerOrdersTotal} AS sales_total
            FROM orders
            {$sellerOrdersDate}
            GROUP BY seller_id
         ) sales ON sales.seller_id = activity.seller_id
         LEFT JOIN sellers s ON s.id = activity.seller_id
         ORDER BY sales_total DESC, orders_count DESC, cart_count DESC
         LIMIT 10"
    )->fetchAll();
}

if ($hasOrders) {
    $ordersHasTotal = admin_column_exists('orders', 'total');
    $ordersTotal = $ordersHasTotal ? 'COALESCE(SUM(total), 0)' : '0';
    $stats['orders'] = (int) db()->query("SELECT COUNT(*) FROM orders {$ordersWhereSql}")->fetchColumn();
    $stats['sales_total'] = (float) db()->query("SELECT {$ordersTotal} FROM orders {$ordersWhereSql}")->fetchColumn();

    if ($hasSellers && admin_column_exists('orders', 'seller_id')) {
        $sellerWhere = $ordersAliasWhereSql;
        $sellerTotalExpr = $ordersHasTotal ? 'COALESCE(SUM(o.total), 0)' : '0';
        $sellerLastOrderExpr = admin_column_exists('orders', 'created_at') ? 'MAX(o.created_at)' : "''";
        $sellerPerformance = db()->query(
            "SELECT COALESCE(s.name, o.seller_name, 'Sin vendedor') AS seller_name,
                    COUNT(*) AS orders_count,
                    {$sellerTotalExpr} AS sales_total,
                    {$sellerLastOrderExpr} AS last_order_at
             FROM orders o
             LEFT JOIN sellers s ON s.id = o.seller_id
             {$sellerWhere}
             GROUP BY seller_name
             ORDER BY sales_total DESC, orders_count DESC
             LIMIT 10"
        )->fetchAll();
    }
}

$topPurchasedPreview = array_slice($topPurchasedProducts, 0, 8);
$topSearchesPreview = array_slice($topSearches, 0, 8);
$clientOpportunitiesPreview = array_slice($clientOpportunities, 0, 8);
$promotionCandidatesPreview = array_slice($promotionCandidates, 0, 8);

foreach ($clientOpportunitiesPreview as $opportunity) {
    $category = trim((string) ($opportunity['category'] ?? ''));
    $recommendedProducts = [];

    foreach ($highIntentProducts as $product) {
        if ($category !== '' && strcasecmp((string) ($product['category'] ?? ''), $category) === 0) {
            $recommendedProducts[] = trim((string) ($product['item_code'] ?? ''));
        }
        if (count($recommendedProducts) >= 2) {
            break;
        }
    }

    if (count($recommendedProducts) < 2) {
        foreach ($topPurchasedPreview as $product) {
            $itemCode = trim((string) ($product['item_code'] ?? ''));
            if ($itemCode === '' || in_array($itemCode, $recommendedProducts, true)) {
                continue;
            }
            $recommendedProducts[] = $itemCode;
            if (count($recommendedProducts) >= 2) {
                break;
            }
        }
    }

    $clientName = trim((string) ($opportunity['client_name'] ?? 'Cliente'));
    $itemCode = trim((string) ($opportunity['item_code'] ?? ''));
    $categoryLabel = $category !== '' ? $category : 'la categoria detectada';
    $message = 'Hola ' . $clientName . ', vimos interes reciente en ' . $categoryLabel . '. ';
    if ($itemCode !== '') {
        $message .= 'Tambien podemos ayudarte con ' . $itemCode;
        if (!empty($recommendedProducts)) {
            $message .= ' y sugerirte ' . implode(' / ', $recommendedProducts);
        }
        $message .= '.';
    } elseif (!empty($recommendedProducts)) {
        $message .= 'Te recomendamos revisar ' . implode(' / ', $recommendedProducts) . '.';
    } else {
        $message .= 'Tenemos opciones relacionadas listas para cotizar.';
    }

    $clientRecommendations[] = [
        'client_name' => $clientName !== '' ? $clientName : 'Visitante sin cliente asignado',
        'category' => $categoryLabel,
        'item_code' => $itemCode,
        'recommended_products' => $recommendedProducts,
        'message' => $message,
        'last_event_at' => (string) ($opportunity['last_event_at'] ?? ''),
    ];
}

admin_header('Inteligencia', 'inteligencia.php');
?>
<?php if (!$hasEvents): ?>
    <section class="card">
        <strong>Inteligencia comercial pendiente de activar.</strong>
        <p class="muted">Ejecuta la migracion <code>sql/20260423_intelligence_events.sql</code> para empezar a registrar clicks, busquedas, productos vistos y senales de compra.</p>
    </section>
    <?php admin_footer(); exit; ?>
<?php endif; ?>

<section class="dashboard-hero intelligence-hero">
    <div class="dashboard-hero__content">
        <span class="pill">Inteligencia comercial</span>
        <h3>Lectura del catalogo y la operacion</h3>
        <p>Detecta interes, productos con mejor movimiento, oportunidades de promocion y seguimiento por cliente o vendedor.</p>
    </div>
    <div class="dashboard-hero__stats">
        <div class="hero-mini-card">
            <span>Periodo</span>
            <strong><?= $eventsWindow ?>d</strong>
        </div>
        <div class="hero-mini-card">
            <span>Eventos</span>
            <strong><?= $stats['events'] ?></strong>
        </div>
        <div class="hero-mini-card">
            <span>Pedidos</span>
            <strong><?= $stats['orders'] ?></strong>
        </div>
    </div>
</section>

<section class="card" style="margin-bottom:18px;">
    <div class="toolbar"><strong>Filtros</strong><span class="pill">solo lectura</span></div>
    <form class="form-grid" method="get">
        <label>
            <span>Periodo</span>
            <select name="days">
                <?php foreach ($allowedWindows as $window): ?>
                    <option value="<?= $window ?>" <?= $eventsWindow === $window ? 'selected' : '' ?>><?= $window ?> dias</option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>Vendedor</span>
            <select name="seller_id">
                <option value="0">Todos</option>
                <?php foreach ($sellerOptions as $seller): ?>
                    <option value="<?= (int) $seller['id'] ?>" <?= $selectedSellerId === (int) $seller['id'] ? 'selected' : '' ?>><?= html_escape($seller['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>Categoria</span>
            <select name="category">
                <option value="">Todas</option>
                <?php foreach ($categoryOptions as $categoryOption): ?>
                    <option value="<?= html_escape((string) $categoryOption) ?>" <?= $selectedCategory === (string) $categoryOption ? 'selected' : '' ?>><?= html_escape((string) $categoryOption) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <div class="wide toolbar__actions">
            <button class="button--primary" type="submit">Aplicar filtros</button>
            <a class="button" href="inteligencia.php">Limpiar</a>
        </div>
    </form>
</section>

<div class="grid grid--cards">
    <div class="card"><div class="stat__label">Eventos 30 dias</div><div class="stat__value"><?= $stats['events'] ?></div></div>
    <div class="card"><div class="stat__label">Visitantes detectados</div><div class="stat__value"><?= $stats['visitors'] ?></div></div>
    <div class="card"><div class="stat__label">Interes producto</div><div class="stat__value"><?= $stats['product_interest'] ?></div></div>
    <div class="card"><div class="stat__label">Agregados carrito</div><div class="stat__value"><?= $stats['cart_adds'] ?></div></div>
</div>

<div class="grid grid--cards dashboard-secondary" style="margin-top:18px;">
    <div class="card"><div class="stat__label">Busquedas</div><div class="stat__value"><?= $stats['searches'] ?></div></div>
    <div class="card"><div class="stat__label">Pedidos</div><div class="stat__value"><?= $stats['orders'] ?></div></div>
    <div class="card"><div class="stat__label">Monto en pedidos</div><div class="stat__value"><?= html_escape(number_format((float) $stats['sales_total'], 2)) ?></div></div>
    <div class="card"><div class="stat__label">Conversion simple</div><div class="stat__value"><?= $stats['cart_adds'] > 0 ? html_escape(number_format(($stats['orders'] / max(1, $stats['cart_adds'])) * 100, 1)) . '%' : '0%' ?></div></div>
</div>

<div class="split" style="margin-top:18px;">
    <section class="card">
        <div class="toolbar"><strong>Productos sugeridos por cliente</strong><span class="pill">solo lectura</span></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Cliente</th><th>Interes</th><th>Sugeridos</th><th>Ultima senal</th></tr></thead>
                <tbody>
                <?php foreach ($clientRecommendations as $recommendation): ?>
                    <tr>
                        <td><strong><?= html_escape($recommendation['client_name']) ?></strong></td>
                        <td><?= html_escape($recommendation['category']) ?><div class="muted"><?= html_escape($recommendation['item_code'] ?: 'sin item dominante') ?></div></td>
                        <td><?= html_escape($recommendation['recommended_products'] ? implode(' / ', $recommendation['recommended_products']) : 'Sin sugerencia aun') ?></td>
                        <td><?= html_escape($recommendation['last_event_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$clientRecommendations): ?>
                    <tr><td colspan="4" class="empty-table">Aun no hay recomendaciones por cliente para este filtro.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <div class="toolbar"><strong>Mensaje comercial sugerido</strong><span class="pill">para vendedor</span></div>
        <div class="list">
            <?php foreach ($clientRecommendations as $recommendation): ?>
                <div class="list-item">
                    <strong><?= html_escape($recommendation['client_name']) ?></strong>
                    <div class="muted"><?= html_escape($recommendation['message']) ?></div>
                </div>
            <?php endforeach; ?>
            <?php if (!$clientRecommendations): ?>
                <div class="list-item empty-list-item">Cuando haya senales de interes recientes, aqui veras mensajes sugeridos para seguimiento.</div>
            <?php endif; ?>
        </div>
    </section>
</div>

<div class="split" style="margin-top:18px;">
    <section class="card">
        <div class="toolbar"><strong>Alta intencion</strong><span class="pill">accion comercial</span></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Producto</th><th>Senales</th><th>Pedidos</th><th>Accion sugerida</th></tr></thead>
                <tbody>
                <?php foreach ($highIntentProducts as $product): ?>
                    <?php
                    $views = (int) $product['views_count'];
                    $carts = (int) $product['cart_count'];
                    $orderedQty = (float) $product['ordered_qty'];
                    $suggestion = $orderedQty <= 0
                        ? 'Promocion directa o contacto del vendedor.'
                        : ($carts > $views / 2 ? 'Reforzar disponibilidad y cierre.' : 'Mantener visible en el catalogo.');
                    ?>
                    <tr>
                        <td><strong><?= html_escape($product['item_code']) ?></strong><div class="muted"><?= html_escape($product['item_name']) ?></div></td>
                        <td>
                            <span class="pill"><?= $views ?> vistas</span>
                            <span class="pill"><?= $carts ?> carritos</span>
                            <span class="pill"><?= (int) $product['interested_visitors'] ?> visitantes</span>
                        </td>
                        <td><?= html_escape(number_format($orderedQty, 2)) ?></td>
                        <td><?= html_escape($suggestion) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$highIntentProducts): ?>
                    <tr><td colspan="4" class="empty-table">Todavia no hay productos con alta intencion en este periodo.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <div class="toolbar"><strong>Mas comprados</strong><span class="pill">top <?= count($topPurchasedPreview) ?></span></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>#</th><th>Producto</th><th>Cant. pedida</th><th>Pedidos</th><th>Total</th></tr></thead>
                <tbody>
                <?php foreach ($topPurchasedPreview as $index => $item): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><strong><?= html_escape($item['item_code']) ?></strong><div class="muted"><?= html_escape($item['item_name']) ?></div></td>
                        <td><?= html_escape(number_format((float) $item['ordered_qty'], 2)) ?></td>
                        <td><?= (int) $item['orders_count'] ?></td>
                        <td><?= html_escape(number_format((float) $item['ordered_total'], 2)) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$topPurchasedPreview): ?>
                    <tr><td colspan="5" class="empty-table">Todavia no hay productos comprados para este filtro.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <p class="muted">Usa estos productos como anclas para combos, promociones o recomendaciones relacionadas.</p>
    </section>
</div>

<div class="split" style="margin-top:18px;">
    <section class="card">
        <div class="toolbar"><strong>Se compran juntos</strong><span class="pill">combos sugeridos</span></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Producto A</th><th>Producto B</th><th>Pedidos juntos</th><th>Uso sugerido</th></tr></thead>
                <tbody>
                <?php foreach ($bundledProducts as $bundle): ?>
                    <tr>
                        <td><strong><?= html_escape($bundle['item_a']) ?></strong><div class="muted"><?= html_escape($bundle['item_a_name']) ?></div></td>
                        <td><strong><?= html_escape($bundle['item_b']) ?></strong><div class="muted"><?= html_escape($bundle['item_b_name']) ?></div></td>
                        <td><?= (int) $bundle['together_orders'] ?></td>
                        <td>Crear combo o sugerencia cruzada.</td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$bundledProducts): ?>
                    <tr><td colspan="4" class="empty-table">Aun no hay pares de productos suficientes para sugerir combos.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <div class="toolbar"><strong>Contactar hoy</strong><span class="pill">45+ dias</span></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Cliente</th><th>Ultimo pedido</th><th>Pedidos</th><th>Total</th><th>Accion</th></tr></thead>
                <tbody>
                <?php foreach ($dormantClients as $client): ?>
                    <tr>
                        <td><strong><?= html_escape($client['business_name']) ?></strong><div class="muted"><?= html_escape($client['contact_name']) ?></div></td>
                        <td><?= html_escape($client['last_order_at']) ?></td>
                        <td><?= (int) $client['orders_count'] ?></td>
                        <td><?= html_escape(number_format((float) $client['sales_total'], 2)) ?></td>
                        <td>Enviar promo, novedad o seguimiento comercial.</td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$dormantClients): ?>
                    <tr><td colspan="5" class="empty-table">No hay clientes para reactivar con este filtro.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<div class="split" style="margin-top:18px;">
    <section class="card">
        <div class="toolbar"><strong>Categorias calientes</strong></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Categoria</th><th>Eventos</th><th>Vistas</th><th>Carritos</th><th>Visitantes</th></tr></thead>
                <tbody>
                <?php foreach ($categorySignals as $category): ?>
                    <tr>
                        <td><strong><?= html_escape($category['category']) ?></strong><div class="muted"><?= html_escape($category['last_event_at']) ?></div></td>
                        <td><?= (int) $category['events_count'] ?></td>
                        <td><?= (int) $category['views_count'] ?></td>
                        <td><?= (int) $category['cart_count'] ?></td>
                        <td><?= (int) $category['visitors_count'] ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$categorySignals): ?>
                    <tr><td colspan="5" class="empty-table">No hay categorias con actividad suficiente en este periodo.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <div class="toolbar"><strong>Contactos prioritarios</strong></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Cliente</th><th>Interes</th><th>Vistas</th><th>Carritos</th><th>Accion</th></tr></thead>
                <tbody>
            <?php foreach ($clientOpportunitiesPreview as $opportunity): ?>
                <?php
                $clientAction = (int) $opportunity['cart_count'] > 0
                    ? 'Contactar con seguimiento del carrito.'
                    : 'Enviar recomendacion de la categoria detectada.';
                ?>
                    <tr>
                        <td><strong><?= html_escape($opportunity['client_name'] ?: 'Visitante sin cliente asignado') ?></strong><div class="muted"><?= html_escape($opportunity['last_event_at']) ?></div></td>
                        <td><?= html_escape($opportunity['category'] ?: 'General') ?><div class="muted"><?= html_escape($opportunity['item_code'] ?: 'sin item') ?></div></td>
                        <td><?= (int) $opportunity['views_count'] ?></td>
                        <td><?= (int) $opportunity['cart_count'] ?></td>
                        <td><?= html_escape($clientAction) ?></td>
                    </tr>
            <?php endforeach; ?>
                <?php if (!$clientOpportunitiesPreview): ?>
                    <tr><td colspan="5" class="empty-table">No hay contactos prioritarios para el filtro actual.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<div class="split" style="margin-top:18px;">
    <section class="card">
        <div class="toolbar"><strong>Productos con mas interes</strong><span class="pill">ultimos <?= $eventsWindow ?> dias</span></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Item</th><th>Categoria</th><th>Vistas</th><th>Carrito</th><th>Ultima senal</th></tr></thead>
                <tbody>
                <?php foreach ($topProducts as $product): ?>
                    <tr>
                        <td><strong><?= html_escape($product['item_code']) ?></strong><div class="muted"><?= html_escape($product['item_name']) ?></div></td>
                        <td><?= html_escape($product['category'] ?: 'General') ?></td>
                        <td><?= (int) $product['views_count'] ?></td>
                        <td><?= (int) $product['cart_count'] ?></td>
                        <td><?= html_escape($product['last_event_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$topProducts): ?>
                    <tr><td colspan="5" class="empty-table">No hay productos con interes suficiente en este periodo.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <div class="toolbar"><strong>Busquedas calientes</strong></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Busqueda</th><th>Cantidad</th><th>Ultima vez</th></tr></thead>
                <tbody>
                <?php foreach ($topSearchesPreview as $search): ?>
                    <tr>
                        <td><strong><?= html_escape($search['search_term']) ?></strong></td>
                        <td><?= (int) $search['searches_count'] ?></td>
                        <td><?= html_escape($search['last_search_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$topSearchesPreview): ?>
                    <tr><td colspan="3" class="empty-table">No hay busquedas registradas para este filtro.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<div class="split" style="margin-top:18px;">
    <section class="card">
        <div class="toolbar"><strong>Oportunidades de promocion</strong></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Producto</th><th>Categoria</th><th>Vistas</th><th>Carritos</th><th>Cant. pedida</th><th>Accion</th></tr></thead>
                <tbody>
                <?php foreach ($promotionCandidatesPreview as $item): ?>
                    <tr>
                        <td><strong><?= html_escape($item['item_code']) ?></strong><div class="muted"><?= html_escape($item['item_name']) ?></div></td>
                        <td><?= html_escape($item['category'] ?: 'General') ?></td>
                        <td><?= (int) $item['views_count'] ?></td>
                        <td><?= (int) $item['cart_count'] ?></td>
                        <td><?= html_escape(number_format((float) $item['ordered_qty'], 2)) ?></td>
                        <td>Revisar precio, foto o crear promocion.</td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$promotionCandidatesPreview): ?>
                    <tr><td colspan="6" class="empty-table">Aun no hay productos suficientes para sugerir promociones.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <div class="toolbar"><strong>Clientes mas activos</strong></div>
        <div class="list">
            <?php foreach ($clientSignals as $signal): ?>
                <div class="list-item">
                    <strong><?= html_escape($signal['client_name'] ?: 'Visitante sin cliente asignado') ?></strong>
                    <div class="muted">Categoria detectada: <?= html_escape($signal['category'] ?: 'General') ?></div>
                    <div class="metrics-inline">
                        <span class="pill"><?= (int) $signal['events_count'] ?> eventos</span>
                        <span class="pill"><?= (int) $signal['cart_count'] ?> carritos</span>
                    </div>
                    <div class="muted"><?= html_escape($signal['last_event_at']) ?></div>
                </div>
            <?php endforeach; ?>
            <?php if (!$clientSignals): ?>
                <div class="list-item empty-list-item">Todavia no hay clientes con actividad suficiente para este filtro.</div>
            <?php endif; ?>
        </div>
    </section>
</div>

<section class="card" style="margin-top:18px;">
    <div class="toolbar"><strong>Vendedores por venta</strong><span class="pill">pedidos <?= $eventsWindow ?> dias</span></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Vendedor</th><th>Pedidos</th><th>Total</th><th>Ultimo pedido</th></tr></thead>
            <tbody>
            <?php foreach ($sellerPerformance as $seller): ?>
                <tr>
                    <td><strong><?= html_escape($seller['seller_name']) ?></strong></td>
                    <td><?= (int) $seller['orders_count'] ?></td>
                    <td><?= html_escape(number_format((float) $seller['sales_total'], 2)) ?></td>
                    <td><?= html_escape($seller['last_order_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$sellerPerformance): ?>
                <tr><td colspan="4" class="empty-table">No hay ventas por vendedor para el periodo seleccionado.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card" style="margin-top:18px;">
    <div class="toolbar"><strong>Conversion por vendedor</strong><span class="pill">eventos + pedidos</span></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Vendedor</th><th>Eventos</th><th>Carritos</th><th>Pedidos</th><th>Total</th><th>Lectura comercial</th></tr></thead>
            <tbody>
            <?php foreach ($sellerConversion as $seller): ?>
                <?php
                $sellerReading = (int) $seller['cart_count'] > 0 && (int) $seller['orders_count'] === 0
                    ? 'Tiene intencion sin cierre; revisar seguimiento.'
                    : ((int) $seller['orders_count'] > 0 ? 'Actividad con pedidos registrados.' : 'Aun sin suficientes senales.');
                ?>
                <tr>
                    <td><strong><?= html_escape($seller['seller_name']) ?></strong></td>
                    <td><?= (int) $seller['events_count'] ?></td>
                    <td><?= (int) $seller['cart_count'] ?></td>
                    <td><?= (int) $seller['orders_count'] ?></td>
                    <td><?= html_escape(number_format((float) $seller['sales_total'], 2)) ?></td>
                    <td><?= html_escape($sellerReading) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$sellerConversion): ?>
                <tr><td colspan="6" class="empty-table">No hay conversion por vendedor para este filtro.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php admin_footer(); ?>
