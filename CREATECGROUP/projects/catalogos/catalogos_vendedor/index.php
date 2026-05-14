<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
vendor_require_panel_login();

if (!function_exists('vendor_whatsapp_url')) {
    function vendor_whatsapp_url(string $message, string $phone = ''): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: '';
        $base = $digits !== '' ? 'https://wa.me/' . $digits : 'https://wa.me/';
        return $base . '?text=' . rawurlencode($message);
    }
}

if (!function_exists('vendor_build_seller_catalog_url')) {
    function vendor_build_seller_catalog_url(array $catalog, string $sellerPublicToken): string
    {
        $publicUrl = trim((string) ($catalog['public_url'] ?? ''));
        if ($publicUrl === '') {
            return '';
        }
        return $sellerPublicToken !== '' ? rtrim($publicUrl, '/') . '/?t=' . $sellerPublicToken : $publicUrl;
    }
}

if (!function_exists('vendor_catalog_share_message')) {
    function vendor_catalog_share_message(array $catalog, string $catalogUrl): string
    {
        $title = trim((string) ($catalog['title'] ?? $catalog['slug'] ?? 'catalogo'));
        return 'Hola, te comparto este catalogo de Rodeo Import: ' . $title . "\n" . $catalogUrl;
    }
}

if (!function_exists('vendor_product_share_message')) {
    function vendor_product_share_message(array $product, string $catalogUrl): string
    {
        $item = trim((string) ($product['item_code'] ?? ''));
        $name = trim((string) ($product['item_name'] ?? $product['description'] ?? ''));
        $message = 'Hola, te comparto este producto de Rodeo Import: ' . $item;
        if ($name !== '') {
            $message .= ' - ' . $name;
        }
        if ($catalogUrl !== '') {
            $message .= "\nVer catalogo: " . $catalogUrl;
        }
        return $message;
    }
}

if (!function_exists('vendor_client_followup_message')) {
    function vendor_client_followup_message(array $client, string $sellerName): string
    {
        $clientName = trim((string) ($client['client_name'] ?? ''));
        $lastOrder = trim((string) ($client['last_order_at'] ?? ''));
        $greetingName = $clientName !== '' ? ' ' . $clientName : '';
        $message = 'Hola' . $greetingName . ', le saluda ' . $sellerName . ' de Rodeo Import.';
        if ($lastOrder !== '') {
            $message .= ' Queria darle seguimiento a su ultimo pedido del ' . $lastOrder . '.';
        }
        $message .= ' Estoy a la orden para ayudarle con nuevos productos o reposicion.';
        return $message;
    }
}

$user = vendor_current_user();
$sellerId = (int) ($user['seller_id'] ?? 0);
$sellerPublicToken = (string) ($user['seller_public_token'] ?? '');
$sellerDisplayNameForMessages = trim((string) ($user['seller_display_name'] ?: $user['full_name'] ?: 'Rodeo Import'));

$schemaReady = vendor_b2b_schema_ready();
$hasEvents = vendor_table_exists('catalog_behavior_events');
$hasOrders = vendor_table_exists('orders');
$hasOrderItems = vendor_table_exists('order_items');
$hasClients = vendor_table_exists('clients');
$allowedWindows = [7, 30, 90];
$catalogsCount = 0;
$linksCount = 0;
$ordersCount = 0;
$ordersTodayCount = 0;
$ordersMonthCount = 0;
$salesMonthTotal = 0.0;
$recentOrders = [];
$topOrderedProducts = [];
$hotProducts = [];
$shareableCatalogs = [];
$frequentClients = [];
$recentClients = [];
$inactiveClients = [];
$activityWindow = (int) ($_GET['days'] ?? 30);
if (!in_array($activityWindow, $allowedWindows, true)) {
    $activityWindow = 30;
}
$activityStats = [
    'events' => 0,
    'cart_adds' => 0,
    'active_clients' => 0,
    'sales_total' => 0.0,
];
$activeClients = [];
$priorityContacts = [];
$suggestedProducts = [];
$suggestedMessages = [];
$primaryCatalogUrl = '';
if ($schemaReady && $sellerId > 0) {
    $catalogsHasSellerId = vendor_column_exists('catalogs', 'seller_id');
    $catalogsHasSellerName = vendor_column_exists('catalogs', 'seller_name');
    $sellerDisplayName = (string) ($user['seller_display_name'] ?? '');
    $catalogConditions = [];
    $catalogParams = [];
    if ($catalogsHasSellerId) {
        $catalogConditions[] = 'c.seller_id = :catalog_seller_id';
        $catalogParams['catalog_seller_id'] = $sellerId;
    }
    if ($catalogsHasSellerName && $sellerDisplayName !== '') {
        $catalogConditions[] = 'c.seller_name = :catalog_seller_name';
        $catalogParams['catalog_seller_name'] = $sellerDisplayName;
    }
    $catalogConditions[] = 'EXISTS (SELECT 1 FROM catalog_share_links l WHERE l.catalog_id = c.id AND l.seller_id = :link_seller_id)';
    $catalogParams['link_seller_id'] = $sellerId;
    $catalogsCountStmt = db()->prepare(
        'SELECT COUNT(DISTINCT c.id) FROM catalogs c WHERE ' . implode(' OR ', $catalogConditions)
    );
    $catalogsCountStmt->execute($catalogParams);
    $catalogsCount = (int) $catalogsCountStmt->fetchColumn();
    $linksCountStmt = db()->prepare('SELECT COUNT(*) FROM catalog_share_links WHERE seller_id = :seller_id');
    $linksCountStmt->execute(['seller_id' => $sellerId]);
    $linksCount = (int) $linksCountStmt->fetchColumn();
    $ordersCountStmt = db()->prepare('SELECT COUNT(*) FROM orders WHERE seller_id = :seller_id');
    $ordersCountStmt->execute(['seller_id' => $sellerId]);
    $ordersCount = (int) $ordersCountStmt->fetchColumn();
    $ordersTodayStmt = db()->prepare(
        'SELECT COUNT(*)
         FROM orders
         WHERE seller_id = :seller_id
           AND created_at >= CURDATE()
           AND created_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)'
    );
    $ordersTodayStmt->execute(['seller_id' => $sellerId]);
    $ordersTodayCount = (int) $ordersTodayStmt->fetchColumn();
    $ordersMonthStmt = db()->prepare(
        "SELECT COUNT(*), COALESCE(SUM(total), 0)
         FROM orders
         WHERE seller_id = :seller_id
           AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"
    );
    $ordersMonthStmt->execute(['seller_id' => $sellerId]);
    $ordersMonthRow = $ordersMonthStmt->fetch(PDO::FETCH_NUM) ?: [0, 0];
    $ordersMonthCount = (int) ($ordersMonthRow[0] ?? 0);
    $salesMonthTotal = (float) ($ordersMonthRow[1] ?? 0);
    $recentOrdersStmt = db()->prepare(
        "SELECT order_number, company_name, total, status, created_at
         FROM orders
         WHERE seller_id = :seller_id
           AND created_at >= DATE_SUB(NOW(), INTERVAL {$activityWindow} DAY)
         ORDER BY created_at DESC
         LIMIT 8"
    );
    $recentOrdersStmt->execute(['seller_id' => $sellerId]);
    $recentOrders = $recentOrdersStmt->fetchAll();

    $catalogsWhere = '(' . implode(' OR ', $catalogConditions) . ')';
    $catalogsAvailableParams = $catalogParams;
    $catalogsAvailableStatusWhere = vendor_column_exists('catalogs', 'status') ? " AND c.status = 'active'" : '';
    $catalogsAvailableUpdatedOrder = vendor_column_exists('catalogs', 'updated_at') ? 'c.updated_at DESC' : 'c.id DESC';
    $shareableCatalogsStmt = db()->prepare(
        "SELECT DISTINCT c.id, c.title, c.slug, c.public_url
         FROM catalogs c
         WHERE {$catalogsWhere}
           AND c.public_url <> ''
           {$catalogsAvailableStatusWhere}
         ORDER BY {$catalogsAvailableUpdatedOrder}
         LIMIT 5"
    );
    $shareableCatalogsStmt->execute($catalogsAvailableParams);
    $shareableCatalogs = $shareableCatalogsStmt->fetchAll();
    if ($shareableCatalogs) {
        $primaryCatalogUrl = vendor_build_seller_catalog_url($shareableCatalogs[0], $sellerPublicToken);
    }

    if ($hasEvents) {
        $activityStatsStmt = db()->prepare(
            "SELECT COUNT(*) AS events_count,
                    SUM(event_type = 'add_to_cart') AS cart_count,
                    COUNT(DISTINCT COALESCE(NULLIF(visitor_id, ''), CONCAT('client-', client_id))) AS active_clients
             FROM catalog_behavior_events
             WHERE seller_id = :seller_id
               AND created_at >= DATE_SUB(NOW(), INTERVAL {$activityWindow} DAY)"
        );
        $activityStatsStmt->execute(['seller_id' => $sellerId]);
        $activityStatsRow = $activityStatsStmt->fetch() ?: [];
        $activityStats['events'] = (int) ($activityStatsRow['events_count'] ?? 0);
        $activityStats['cart_adds'] = (int) ($activityStatsRow['cart_count'] ?? 0);
        $activityStats['active_clients'] = (int) ($activityStatsRow['active_clients'] ?? 0);

        $clientNameExpr = $hasClients
            ? "COALESCE(NULLIF(cl.business_name, ''), NULLIF(cl.contact_name, ''), NULLIF(e.visitor_id, ''), 'Sin cliente asignado')"
            : "COALESCE(NULLIF(e.visitor_id, ''), 'Sin cliente asignado')";
        $clientJoin = $hasClients ? 'LEFT JOIN clients cl ON cl.id = e.client_id' : '';

        $activeClientsStmt = db()->prepare(
            "SELECT {$clientNameExpr} AS client_name,
                    MAX(e.category) AS category,
                    COUNT(*) AS events_count,
                    SUM(e.event_type = 'add_to_cart') AS cart_count,
                    MAX(e.created_at) AS last_event_at
             FROM catalog_behavior_events e
             {$clientJoin}
             WHERE e.seller_id = :seller_id
               AND e.created_at >= DATE_SUB(NOW(), INTERVAL {$activityWindow} DAY)
               AND (e.client_id IS NOT NULL OR e.visitor_id <> '')
             GROUP BY e.client_id, e.visitor_id, client_name
             ORDER BY cart_count DESC, events_count DESC, last_event_at DESC
             LIMIT 6"
        );
        $activeClientsStmt->execute(['seller_id' => $sellerId]);
        $activeClients = $activeClientsStmt->fetchAll();

        $priorityContactsStmt = db()->prepare(
            "SELECT {$clientNameExpr} AS client_name,
                    MAX(e.category) AS category,
                    MAX(e.item_code) AS item_code,
                    MAX(e.item_name) AS item_name,
                    SUM(e.event_type = 'product_detail') AS views_count,
                    SUM(e.event_type = 'add_to_cart') AS cart_count,
                    MAX(e.created_at) AS last_event_at
             FROM catalog_behavior_events e
             {$clientJoin}
             WHERE e.seller_id = :seller_id
               AND e.created_at >= DATE_SUB(NOW(), INTERVAL {$activityWindow} DAY)
               AND e.event_type IN ('product_detail','add_to_cart','cart_quantity','search')
               AND (e.client_id IS NOT NULL OR e.visitor_id <> '')
             GROUP BY e.client_id, e.visitor_id, client_name
             HAVING cart_count > 0 OR views_count >= 2
             ORDER BY cart_count DESC, views_count DESC, last_event_at DESC
             LIMIT 6"
        );
        $priorityContactsStmt->execute(['seller_id' => $sellerId]);
        $priorityContacts = $priorityContactsStmt->fetchAll();

        $suggestedProductsStmt = db()->prepare(
            "SELECT e.item_code,
                    MAX(e.item_name) AS item_name,
                    MAX(e.category) AS category,
                    SUM(e.event_type = 'product_detail') AS views_count,
                    SUM(e.event_type = 'add_to_cart') AS cart_count,
                    COUNT(DISTINCT COALESCE(NULLIF(e.visitor_id, ''), CONCAT('client-', e.client_id))) AS interested_clients
             FROM catalog_behavior_events e
             WHERE e.seller_id = :seller_id
               AND e.created_at >= DATE_SUB(NOW(), INTERVAL {$activityWindow} DAY)
               AND e.item_code <> ''
               AND e.event_type IN ('product_detail','add_to_cart','cart_quantity')
             GROUP BY e.item_code
             HAVING views_count >= 2 OR cart_count >= 1
             ORDER BY cart_count DESC, interested_clients DESC, views_count DESC
             LIMIT 6"
        );
        $suggestedProductsStmt->execute(['seller_id' => $sellerId]);
        $suggestedProducts = $suggestedProductsStmt->fetchAll();
    }

    if ($hasOrders) {
        $salesTotalStmt = db()->prepare(
            "SELECT COALESCE(SUM(total), 0)
             FROM orders
             WHERE seller_id = :seller_id
               AND created_at >= DATE_SUB(NOW(), INTERVAL {$activityWindow} DAY)"
        );
        $salesTotalStmt->execute(['seller_id' => $sellerId]);
        $activityStats['sales_total'] = (float) $salesTotalStmt->fetchColumn();
    }

    if ($hasOrders && $hasOrderItems) {
        $topOrderedProductsStmt = db()->prepare(
            "SELECT oi.item_code,
                    MAX(oi.description) AS description,
                    SUM(oi.quantity) AS ordered_units,
                    SUM(oi.line_total) AS ordered_total,
                    COUNT(DISTINCT o.id) AS orders_count
             FROM order_items oi
             INNER JOIN orders o ON o.id = oi.order_id
             WHERE o.seller_id = :seller_id
               AND o.created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
               AND oi.item_code <> ''
             GROUP BY oi.item_code
             ORDER BY ordered_units DESC, ordered_total DESC
             LIMIT 6"
        );
        $topOrderedProductsStmt->execute(['seller_id' => $sellerId]);
        $topOrderedProducts = $topOrderedProductsStmt->fetchAll();

        $hotProductsByCode = [];
        $hotOrderedProductsStmt = db()->prepare(
            "SELECT oi.item_code,
                    MAX(oi.description) AS item_name,
                    SUM(oi.quantity) AS ordered_units,
                    SUM(oi.line_total) AS ordered_total,
                    COUNT(DISTINCT o.id) AS orders_count
             FROM order_items oi
             INNER JOIN orders o ON o.id = oi.order_id
             WHERE o.seller_id = :seller_id
               AND o.created_at >= DATE_SUB(NOW(), INTERVAL {$activityWindow} DAY)
               AND oi.item_code <> ''
             GROUP BY oi.item_code
             ORDER BY ordered_units DESC, ordered_total DESC
             LIMIT 10"
        );
        $hotOrderedProductsStmt->execute(['seller_id' => $sellerId]);
        foreach ($hotOrderedProductsStmt->fetchAll() as $product) {
            $code = trim((string) ($product['item_code'] ?? ''));
            if ($code === '') {
                continue;
            }
            $hotProductsByCode[$code] = [
                'item_code' => $code,
                'item_name' => (string) ($product['item_name'] ?? ''),
                'ordered_units' => (float) ($product['ordered_units'] ?? 0),
                'orders_count' => (int) ($product['orders_count'] ?? 0),
                'views_count' => 0,
                'cart_count' => 0,
                'interested_clients' => 0,
                'signal' => 'Producto con pedidos recientes',
                'score' => ((float) ($product['ordered_units'] ?? 0) * 4) + ((int) ($product['orders_count'] ?? 0) * 3),
            ];
        }

        if ($hasEvents) {
            foreach ($suggestedProducts as $product) {
                $code = trim((string) ($product['item_code'] ?? ''));
                if ($code === '') {
                    continue;
                }
                if (!isset($hotProductsByCode[$code])) {
                    $hotProductsByCode[$code] = [
                        'item_code' => $code,
                        'item_name' => (string) ($product['item_name'] ?? ''),
                        'ordered_units' => 0.0,
                        'orders_count' => 0,
                        'views_count' => 0,
                        'cart_count' => 0,
                        'interested_clients' => 0,
                        'signal' => 'Producto con interes reciente',
                        'score' => 0,
                    ];
                }
                $hotProductsByCode[$code]['item_name'] = $hotProductsByCode[$code]['item_name'] ?: (string) ($product['item_name'] ?? '');
                $hotProductsByCode[$code]['views_count'] = (int) ($product['views_count'] ?? 0);
                $hotProductsByCode[$code]['cart_count'] = (int) ($product['cart_count'] ?? 0);
                $hotProductsByCode[$code]['interested_clients'] = (int) ($product['interested_clients'] ?? 0);
                $hotProductsByCode[$code]['score'] += ((int) ($product['cart_count'] ?? 0) * 5)
                    + ((int) ($product['interested_clients'] ?? 0) * 3)
                    + ((int) ($product['views_count'] ?? 0));
                if ((int) ($product['cart_count'] ?? 0) > 0) {
                    $hotProductsByCode[$code]['signal'] = 'Agregado a carrito por clientes';
                } elseif ((int) ($product['views_count'] ?? 0) >= 2) {
                    $hotProductsByCode[$code]['signal'] = 'Visto varias veces en catalogo';
                }
            }
        }

        $hotProducts = array_values($hotProductsByCode);
        usort($hotProducts, static function (array $a, array $b): int {
            return ($b['score'] <=> $a['score']);
        });
        $hotProducts = array_slice($hotProducts, 0, 8);
    }

    if (!$hotProducts && $hasEvents) {
        foreach ($suggestedProducts as $product) {
            $viewsCount = (int) ($product['views_count'] ?? 0);
            $cartCount = (int) ($product['cart_count'] ?? 0);
            $hotProducts[] = [
                'item_code' => (string) ($product['item_code'] ?? ''),
                'item_name' => (string) ($product['item_name'] ?? ''),
                'ordered_units' => 0.0,
                'orders_count' => 0,
                'views_count' => $viewsCount,
                'cart_count' => $cartCount,
                'interested_clients' => (int) ($product['interested_clients'] ?? 0),
                'signal' => $cartCount > 0 ? 'Agregado a carrito por clientes' : 'Visto varias veces en catalogo',
                'score' => ($cartCount * 5) + $viewsCount,
            ];
        }
    }

    if ($hasOrders) {
        $clientJoinForOrders = $hasClients ? 'LEFT JOIN clients cl ON cl.id = o.client_id' : '';
        $orderCompanyField = vendor_column_exists('orders', 'company_name') ? 'o.company_name' : "''";
        $orderContactField = vendor_column_exists('orders', 'contact_name') ? 'o.contact_name' : "''";
        $orderEmailField = vendor_column_exists('orders', 'contact_email') ? 'o.contact_email' : "''";
        $orderPhoneField = vendor_column_exists('orders', 'contact_phone') ? 'o.contact_phone' : "''";
        $clientNameOrderExpr = $hasClients
            ? "COALESCE(NULLIF(MAX(cl.business_name), ''), NULLIF(MAX({$orderCompanyField}), ''), NULLIF(MAX({$orderContactField}), ''), 'Cliente sin nombre')"
            : "COALESCE(NULLIF(MAX({$orderCompanyField}), ''), NULLIF(MAX({$orderContactField}), ''), 'Cliente sin nombre')";
        $clientContactOrderExpr = $hasClients
            ? "COALESCE(NULLIF(MAX(cl.contact_name), ''), NULLIF(MAX({$orderContactField}), ''), '')"
            : "COALESCE(NULLIF(MAX({$orderContactField}), ''), '')";
        $clientEmailOrderExpr = $hasClients
            ? "COALESCE(NULLIF(MAX(cl.email), ''), NULLIF(MAX({$orderEmailField}), ''), '')"
            : "COALESCE(NULLIF(MAX({$orderEmailField}), ''), '')";
        $clientPhoneOrderExpr = $hasClients
            ? "COALESCE(NULLIF(MAX(cl.phone), ''), NULLIF(MAX({$orderPhoneField}), ''), '')"
            : "COALESCE(NULLIF(MAX({$orderPhoneField}), ''), '')";
        $clientGroupExpr = "CASE
            WHEN o.client_id IS NOT NULL THEN CONCAT('client-', o.client_id)
            WHEN {$orderEmailField} <> '' THEN CONCAT('email-', LOWER({$orderEmailField}))
            WHEN {$orderPhoneField} <> '' THEN CONCAT('phone-', {$orderPhoneField})
            WHEN {$orderCompanyField} <> '' THEN CONCAT('company-', LOWER({$orderCompanyField}))
            ELSE CONCAT('order-', o.id)
        END";
        $clientBaseSelect = "{$clientGroupExpr} AS client_key,
                {$clientNameOrderExpr} AS client_name,
                {$clientContactOrderExpr} AS contact_name,
                {$clientEmailOrderExpr} AS email,
                {$clientPhoneOrderExpr} AS phone,
                COUNT(*) AS orders_count,
                COALESCE(SUM(o.total), 0) AS total_purchased,
                MAX(o.created_at) AS last_order_at";

        $frequentClientsStmt = db()->prepare(
            "SELECT {$clientBaseSelect}
             FROM orders o
             {$clientJoinForOrders}
             WHERE o.seller_id = :seller_id
               AND o.created_at >= DATE_SUB(NOW(), INTERVAL 180 DAY)
             GROUP BY client_key
             ORDER BY orders_count DESC, total_purchased DESC, last_order_at DESC
             LIMIT 6"
        );
        $frequentClientsStmt->execute(['seller_id' => $sellerId]);
        $frequentClients = $frequentClientsStmt->fetchAll();

        $recentClientsStmt = db()->prepare(
            "SELECT {$clientBaseSelect}
             FROM orders o
             {$clientJoinForOrders}
             WHERE o.seller_id = :seller_id
               AND o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY client_key
             ORDER BY last_order_at DESC
             LIMIT 6"
        );
        $recentClientsStmt->execute(['seller_id' => $sellerId]);
        $recentClients = $recentClientsStmt->fetchAll();

        $inactiveClientsStmt = db()->prepare(
            "SELECT {$clientBaseSelect}
             FROM orders o
             {$clientJoinForOrders}
             WHERE o.seller_id = :seller_id
               AND o.created_at >= DATE_SUB(NOW(), INTERVAL 365 DAY)
             GROUP BY client_key
             HAVING last_order_at < DATE_SUB(NOW(), INTERVAL 60 DAY)
             ORDER BY last_order_at ASC
             LIMIT 6"
        );
        $inactiveClientsStmt->execute(['seller_id' => $sellerId]);
        $inactiveClients = $inactiveClientsStmt->fetchAll();
    }

    foreach (array_slice($priorityContacts, 0, 4) as $contact) {
        $clientName = trim((string) ($contact['client_name'] ?? 'Cliente'));
        $categoryLabel = trim((string) ($contact['category'] ?? 'la categoria detectada'));
        $itemCode = trim((string) ($contact['item_code'] ?? ''));
        $candidateProducts = [];

        foreach ($suggestedProducts as $product) {
            $productCode = trim((string) ($product['item_code'] ?? ''));
            $productCategory = trim((string) ($product['category'] ?? ''));
            if ($productCode === '' || in_array($productCode, $candidateProducts, true)) {
                continue;
            }
            if ($categoryLabel !== '' && $productCategory !== '' && strcasecmp($productCategory, $categoryLabel) !== 0) {
                continue;
            }
            $candidateProducts[] = $productCode;
            if (count($candidateProducts) >= 2) {
                break;
            }
        }

        if (count($candidateProducts) < 2 && $hasOrderItems && $hasOrders) {
            $fallbackStmt = db()->prepare(
                "SELECT oi.item_code
                 FROM order_items oi
                 INNER JOIN orders o ON o.id = oi.order_id
                 WHERE o.seller_id = :seller_id
                   AND oi.item_code <> ''
                   AND o.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                 GROUP BY oi.item_code
                 ORDER BY SUM(oi.line_total) DESC, SUM(oi.quantity) DESC
                 LIMIT 4"
            );
            $fallbackStmt->execute(['seller_id' => $sellerId]);
            foreach ($fallbackStmt->fetchAll(PDO::FETCH_COLUMN) as $fallbackCode) {
                $fallbackCode = trim((string) $fallbackCode);
                if ($fallbackCode === '' || in_array($fallbackCode, $candidateProducts, true)) {
                    continue;
                }
                $candidateProducts[] = $fallbackCode;
                if (count($candidateProducts) >= 2) {
                    break;
                }
            }
        }

        $message = 'Hola ' . $clientName . ', vimos interes reciente en ' . $categoryLabel . '.';
        if ($itemCode !== '') {
            $message .= ' Te podemos ayudar con ' . $itemCode . '.';
        }
        if ($candidateProducts) {
            $message .= ' Te sugerimos revisar ' . implode(' / ', $candidateProducts) . '.';
        } else {
            $message .= ' Tenemos opciones relacionadas listas para cotizar.';
        }

        $suggestedMessages[] = [
            'client_name' => $clientName,
            'message' => $message,
            'last_event_at' => (string) ($contact['last_event_at'] ?? ''),
        ];
    }
}

vendor_header('Resumen comercial', 'index.php');
?>
<div class="grid grid--cards">
    <div class="card"><div class="stat__label">Catalogos</div><div class="stat__value"><?= $catalogsCount ?></div></div>
    <div class="card"><div class="stat__label">Links</div><div class="stat__value"><?= $linksCount ?></div></div>
    <div class="card"><div class="stat__label">Pedidos</div><div class="stat__value"><?= $ordersCount ?></div></div>
    <div class="card"><div class="stat__label">Vendedor</div><div class="stat__value" style="font-size:22px;"><?= html_escape($user['seller_display_name'] ?: $user['full_name']) ?></div></div>
</div>
<?php if ($schemaReady && $sellerId > 0): ?>
<div class="grid grid--cards dashboard-secondary">
    <div class="card"><div class="stat__label">Pedidos de hoy</div><div class="stat__value"><?= $ordersTodayCount ?></div></div>
    <div class="card"><div class="stat__label">Pedidos del mes</div><div class="stat__value"><?= $ordersMonthCount ?></div></div>
    <div class="card"><div class="stat__label">Vendido este mes</div><div class="stat__value" style="font-size:22px;"><?= html_escape(number_format($salesMonthTotal, 2)) ?></div></div>
    <div class="card"><div class="stat__label">Catalogos para compartir</div><div class="stat__value"><?= count($shareableCatalogs) ?></div></div>
</div>
<div class="split" style="margin-top:18px;">
    <section class="card">
        <div class="toolbar"><strong>Accesos rapidos</strong><span class="pill">ventas</span></div>
        <div class="toolbar__actions" style="margin-top:12px;">
            <a class="button--primary" href="catalogos.php">Compartir catalogo</a>
            <a class="button" href="pedidos.php">Ver pedidos</a>
            <a class="button" href="links.php">Mis links</a>
        </div>
        <p class="muted" style="margin-top:14px;">Usa estos accesos para abrir catalogos asignados, copiar enlaces trazables y revisar pedidos nuevos.</p>
    </section>
    <section class="card">
        <div class="toolbar"><strong>Catalogos disponibles</strong><span class="pill">para compartir</span></div>
        <div class="list">
            <?php foreach ($shareableCatalogs as $catalog): ?>
                <?php
                $sellerCatalogUrl = vendor_build_seller_catalog_url($catalog, $sellerPublicToken);
                $catalogShareMessage = vendor_catalog_share_message($catalog, $sellerCatalogUrl);
                ?>
                <div class="list-item">
                    <strong><?= html_escape($catalog['title'] ?: $catalog['slug']) ?></strong>
                    <div class="muted"><?= html_escape($catalog['slug']) ?></div>
                    <div class="toolbar__actions" style="margin-top:8px;">
                        <a class="button" href="<?= html_escape($sellerCatalogUrl) ?>" target="_blank">Abrir</a>
                        <a class="button" href="<?= html_escape(vendor_whatsapp_url($catalogShareMessage)) ?>" target="_blank">WhatsApp</a>
                        <button type="button" class="button vendor-copy-action" data-copy="<?= html_escape($sellerCatalogUrl) ?>">Copiar link</button>
                        <input class="link-url" type="text" value="<?= html_escape($sellerCatalogUrl) ?>" readonly>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (!$shareableCatalogs): ?>
                <div class="list-item"><div class="muted">Aun no hay catalogos activos con enlace publico para este vendedor.</div></div>
            <?php endif; ?>
        </div>
    </section>
</div>
<section class="card" style="margin-top:18px;">
    <div class="toolbar"><strong>Productos mas pedidos</strong><span class="pill">mes actual</span></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Item</th><th>Descripcion</th><th>Cantidad</th><th>Pedidos</th><th>Total</th></tr></thead>
            <tbody>
            <?php foreach ($topOrderedProducts as $product): ?>
                <tr>
                    <td><strong><?= html_escape($product['item_code']) ?></strong></td>
                    <td><?= html_escape($product['description']) ?></td>
                    <td><?= html_escape(format_plain_number((float) $product['ordered_units'])) ?></td>
                    <td><?= (int) $product['orders_count'] ?></td>
                    <td><?= html_escape(number_format((float) $product['ordered_total'], 2)) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$topOrderedProducts): ?>
                <tr><td colspan="5" class="muted">Aun no hay productos pedidos durante este mes.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<section class="card" style="margin-top:18px;">
    <div class="toolbar"><strong>Productos calientes</strong><span class="pill">prioridad comercial</span></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Item</th><th>Producto</th><th>Senal</th><th>Pedidos</th><th>Carritos</th><th>Vistas</th><th>Accion</th></tr></thead>
            <tbody>
            <?php foreach ($hotProducts as $product): ?>
                <?php $productShareMessage = vendor_product_share_message($product, $primaryCatalogUrl); ?>
                <tr>
                    <td><strong><?= html_escape($product['item_code']) ?></strong></td>
                    <td><?= html_escape($product['item_name']) ?></td>
                    <td><?= html_escape($product['signal']) ?></td>
                    <td><?= html_escape(format_plain_number((float) $product['ordered_units'])) ?></td>
                    <td><?= (int) $product['cart_count'] ?></td>
                    <td><?= (int) $product['views_count'] ?></td>
                    <td>
                        <a class="button" href="<?= html_escape(vendor_whatsapp_url($productShareMessage)) ?>" target="_blank">WhatsApp</a>
                        <button type="button" class="button vendor-copy-action" data-copy="<?= html_escape($productShareMessage) ?>">Copiar</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$hotProducts): ?>
                <tr><td colspan="7" class="muted">Aun no hay datos suficientes para detectar productos calientes.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<section class="card" style="margin-top:18px;">
    <div class="toolbar"><strong>Clientes inteligentes</strong><span class="pill">seguimiento comercial</span></div>
    <div class="split" style="margin-top:12px;">
        <div>
            <div class="toolbar"><strong>Frecuentes</strong><span class="pill">180 dias</span></div>
            <div class="list">
                <?php foreach ($frequentClients as $client): ?>
                    <?php $clientFollowupMessage = vendor_client_followup_message($client, $sellerDisplayNameForMessages); ?>
                    <div class="list-item">
                        <strong><?= html_escape($client['client_name']) ?></strong>
                        <div class="muted"><?= html_escape($client['contact_name'] ?: 'Sin contacto') ?></div>
                        <div class="metrics-inline">
                            <span class="pill"><?= (int) $client['orders_count'] ?> pedidos</span>
                            <span class="pill"><?= html_escape(number_format((float) $client['total_purchased'], 2)) ?></span>
                        </div>
                        <div class="muted">Ultimo pedido: <?= html_escape($client['last_order_at']) ?></div>
                        <div class="toolbar__actions" style="margin-top:8px;">
                            <?php if (trim((string) ($client['phone'] ?? '')) !== ''): ?>
                                <a class="button" href="<?= html_escape(vendor_whatsapp_url($clientFollowupMessage, (string) $client['phone'])) ?>" target="_blank">WhatsApp</a>
                            <?php endif; ?>
                            <button type="button" class="button vendor-copy-action" data-copy="<?= html_escape($clientFollowupMessage) ?>">Copiar mensaje</button>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (!$frequentClients): ?>
                    <div class="list-item"><div class="muted">Aun no hay clientes frecuentes detectados.</div></div>
                <?php endif; ?>
            </div>
        </div>
        <div>
            <div class="toolbar"><strong>Recientes</strong><span class="pill">30 dias</span></div>
            <div class="list">
                <?php foreach ($recentClients as $client): ?>
                    <?php $clientFollowupMessage = vendor_client_followup_message($client, $sellerDisplayNameForMessages); ?>
                    <div class="list-item">
                        <strong><?= html_escape($client['client_name']) ?></strong>
                        <div class="muted"><?= html_escape($client['phone'] ?: $client['email'] ?: 'Sin contacto registrado') ?></div>
                        <div class="metrics-inline">
                            <span class="pill"><?= (int) $client['orders_count'] ?> pedidos</span>
                            <span class="pill"><?= html_escape(number_format((float) $client['total_purchased'], 2)) ?></span>
                        </div>
                        <div class="muted">Ultimo pedido: <?= html_escape($client['last_order_at']) ?></div>
                        <div class="toolbar__actions" style="margin-top:8px;">
                            <?php if (trim((string) ($client['phone'] ?? '')) !== ''): ?>
                                <a class="button" href="<?= html_escape(vendor_whatsapp_url($clientFollowupMessage, (string) $client['phone'])) ?>" target="_blank">WhatsApp</a>
                            <?php endif; ?>
                            <button type="button" class="button vendor-copy-action" data-copy="<?= html_escape($clientFollowupMessage) ?>">Copiar mensaje</button>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (!$recentClients): ?>
                    <div class="list-item"><div class="muted">No hay clientes recientes en los ultimos 30 dias.</div></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div style="margin-top:18px;">
        <div class="toolbar"><strong>Clientes inactivos</strong><span class="pill">sin compra 60+ dias</span></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Cliente</th><th>Contacto</th><th>Pedidos</th><th>Total comprado</th><th>Ultimo pedido</th></tr></thead>
                <tbody>
                <?php foreach ($inactiveClients as $client): ?>
                    <?php $clientFollowupMessage = vendor_client_followup_message($client, $sellerDisplayNameForMessages); ?>
                    <tr>
                        <td><strong><?= html_escape($client['client_name']) ?></strong></td>
                        <td><?= html_escape($client['phone'] ?: $client['email'] ?: $client['contact_name'] ?: 'Sin contacto') ?></td>
                        <td><?= (int) $client['orders_count'] ?></td>
                        <td><?= html_escape(number_format((float) $client['total_purchased'], 2)) ?></td>
                        <td>
                            <?= html_escape($client['last_order_at']) ?>
                            <div class="toolbar__actions" style="margin-top:8px;">
                                <?php if (trim((string) ($client['phone'] ?? '')) !== ''): ?>
                                    <a class="button" href="<?= html_escape(vendor_whatsapp_url($clientFollowupMessage, (string) $client['phone'])) ?>" target="_blank">WhatsApp</a>
                                <?php endif; ?>
                                <button type="button" class="button vendor-copy-action" data-copy="<?= html_escape($clientFollowupMessage) ?>">Copiar</button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$inactiveClients): ?>
                    <tr><td colspan="5" class="muted">No hay clientes inactivos detectados para este vendedor.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<section class="card" style="margin-top:18px;">
    <div class="toolbar"><strong>Filtros del resumen</strong><span class="pill">solo lectura</span></div>
    <form class="form-grid" method="get">
        <label>
            <span>Periodo</span>
            <select name="days">
                <?php foreach ($allowedWindows as $window): ?>
                    <option value="<?= $window ?>" <?= $activityWindow === $window ? 'selected' : '' ?>><?= $window ?> dias</option>
                <?php endforeach; ?>
            </select>
        </label>
        <div class="wide toolbar__actions">
            <button class="button--primary" type="submit">Aplicar filtro</button>
            <a class="button" href="index.php">Limpiar</a>
        </div>
    </form>
</section>
<div class="grid grid--cards dashboard-secondary">
    <div class="card"><div class="stat__label">Eventos <?= $activityWindow ?> dias</div><div class="stat__value"><?= $activityStats['events'] ?></div></div>
    <div class="card"><div class="stat__label">Carritos <?= $activityWindow ?> dias</div><div class="stat__value"><?= $activityStats['cart_adds'] ?></div></div>
    <div class="card"><div class="stat__label">Clientes mas activos</div><div class="stat__value"><?= $activityStats['active_clients'] ?></div></div>
    <div class="card"><div class="stat__label">Monto en pedidos</div><div class="stat__value" style="font-size:22px;"><?= html_escape(number_format((float) $activityStats['sales_total'], 2)) ?></div></div>
</div>
<?php endif; ?>
<?php if (!$schemaReady || $sellerId <= 0): ?>
    <section class="card" style="margin-top:18px;">
        <strong>Panel vendedor pendiente de migracion.</strong>
        <p class="muted">Ejecuta <code>hosting/sql/20260419_b2b_schema_compat.sql</code> y asigna este usuario a un vendedor para activar catalogos, links y pedidos trazables.</p>
    </section>
<?php endif; ?>
<?php if ($schemaReady && $sellerId > 0 && !$hasEvents): ?>
    <section class="card" style="margin-top:18px;">
        <strong>Inteligencia comercial pendiente de activar.</strong>
        <p class="muted">Cuando ejecutes <code>hosting/sql/20260423_intelligence_events.sql</code> y el catalogo publique eventos, aqui apareceran contactos prioritarios, productos sugeridos y mensajes comerciales.</p>
    </section>
<?php endif; ?>
<?php if ($schemaReady && $sellerId > 0 && $hasEvents): ?>
<div class="split" style="margin-top:18px;">
    <section class="card">
        <div class="toolbar"><strong>Clientes mas activos</strong><span class="pill">ultimos <?= $activityWindow ?> dias</span></div>
        <div class="list">
            <?php foreach ($activeClients as $client): ?>
                <div class="list-item">
                    <strong><?= html_escape($client['client_name']) ?></strong>
                    <div class="muted">Categoria detectada: <?= html_escape($client['category'] ?: 'General') ?></div>
                    <div class="metrics-inline">
                        <span class="pill"><?= (int) $client['events_count'] ?> eventos</span>
                        <span class="pill"><?= (int) $client['cart_count'] ?> carritos</span>
                    </div>
                    <div class="muted"><?= html_escape($client['last_event_at']) ?></div>
                </div>
            <?php endforeach; ?>
            <?php if (!$activeClients): ?>
                <div class="list-item"><div class="muted">Aun no hay actividad suficiente para este vendedor.</div></div>
            <?php endif; ?>
        </div>
    </section>

    <section class="card">
        <div class="toolbar"><strong>Contactos prioritarios</strong><span class="pill">seguimiento</span></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Cliente</th><th>Interes</th><th>Vistas</th><th>Carritos</th><th>Accion</th></tr></thead>
                <tbody>
                <?php foreach ($priorityContacts as $contact): ?>
                    <tr>
                        <td><strong><?= html_escape($contact['client_name']) ?></strong><div class="muted"><?= html_escape($contact['last_event_at']) ?></div></td>
                        <td><?= html_escape($contact['category'] ?: 'General') ?><div class="muted"><?= html_escape($contact['item_code'] ?: 'sin item') ?></div></td>
                        <td><?= (int) $contact['views_count'] ?></td>
                        <td><?= (int) $contact['cart_count'] ?></td>
                        <td><?= (int) $contact['cart_count'] > 0 ? 'Dar seguimiento al carrito.' : 'Enviar recomendacion de la categoria.' ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$priorityContacts): ?>
                    <tr><td colspan="5" class="muted">Todavia no hay contactos prioritarios para este vendedor.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<div class="split" style="margin-top:18px;">
    <section class="card">
        <div class="toolbar"><strong>Productos sugeridos</strong><span class="pill">venta cruzada</span></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Producto</th><th>Categoria</th><th>Vistas</th><th>Carritos</th><th>Clientes</th></tr></thead>
                <tbody>
                <?php foreach ($suggestedProducts as $product): ?>
                    <tr>
                        <td><strong><?= html_escape($product['item_code']) ?></strong><div class="muted"><?= html_escape($product['item_name']) ?></div></td>
                        <td><?= html_escape($product['category'] ?: 'General') ?></td>
                        <td><?= (int) $product['views_count'] ?></td>
                        <td><?= (int) $product['cart_count'] ?></td>
                        <td><?= (int) $product['interested_clients'] ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$suggestedProducts): ?>
                    <tr><td colspan="5" class="muted">Todavia no hay productos con senales suficientes.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <div class="toolbar"><strong>Mensaje comercial sugerido</strong><span class="pill">uso manual</span></div>
        <div class="list">
            <?php foreach ($suggestedMessages as $message): ?>
                <div class="list-item">
                    <strong><?= html_escape($message['client_name']) ?></strong>
                    <div class="muted"><?= html_escape($message['message']) ?></div>
                    <div class="muted" style="margin-top:8px;"><?= html_escape($message['last_event_at']) ?></div>
                </div>
            <?php endforeach; ?>
            <?php if (!$suggestedMessages): ?>
                <div class="list-item"><div class="muted">Cuando existan contactos con interes reciente, aqui veras mensajes listos para seguimiento.</div></div>
            <?php endif; ?>
        </div>
    </section>
</div>
<?php endif; ?>
<section class="card" style="margin-top:18px;">
    <div class="toolbar"><strong>Pedidos recientes</strong><span class="pill">ultimos <?= $activityWindow ?> dias</span></div>
    <div class="list">
        <?php foreach ($recentOrders as $order): ?>
            <div class="list-item">
                <strong><?= html_escape($order['order_number']) ?></strong>
                <div class="muted"><?= html_escape($order['company_name']) ?></div>
                <div class="metrics-inline">
                    <span class="pill"><?= html_escape(number_format((float) $order['total'], 2)) ?></span>
                    <span class="pill"><?= html_escape($order['status']) ?></span>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (!$recentOrders): ?>
            <div class="list-item"><div class="muted">Todavia no hay pedidos recientes para este vendedor.</div></div>
        <?php endif; ?>
    </div>
</section>
<script>
document.addEventListener('click', async function (event) {
    const button = event.target.closest('.vendor-copy-action');
    if (!button) return;
    const text = button.getAttribute('data-copy') || '';
    if (!text) return;
    try {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(text);
        } else {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.focus();
            textarea.select();
            document.execCommand('copy');
            textarea.remove();
        }
        const original = button.textContent;
        button.textContent = 'Copiado';
        setTimeout(function () { button.textContent = original; }, 1600);
    } catch (error) {
        alert('No se pudo copiar automaticamente. Copia el texto manualmente.');
    }
});
</script>
<?php vendor_footer(); ?>
