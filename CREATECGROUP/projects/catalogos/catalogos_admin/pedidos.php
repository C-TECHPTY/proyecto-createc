<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
admin_require_login();

$hasOrders = admin_table_exists('orders');
$hasCatalogs = admin_table_exists('catalogs');
$hasSellers = admin_table_exists('sellers');
$hasClients = admin_table_exists('clients');
$hasItems = admin_table_exists('order_items');
$hasHistory = admin_table_exists('order_status_history');
$orderColumns = [];
foreach (['id','order_number','catalog_id','share_link_id','seller_id','seller_token','client_id','company_name','customer_name','contact_name','customer_email','contact_email','customer_phone','contact_phone','address_zone','total','status','created_at','seller_name','is_test','deleted_at','deleted_by','updated_at','customer_confirmed','confirmed_at','customer_ip'] as $column) {
    $orderColumns[$column] = $hasOrders && admin_column_exists('orders', $column);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_abort();
    $action = (string) ($_POST['action'] ?? 'status');
    $orderId = (int) ($_POST['order_id'] ?? 0);
    $redirectId = $orderId > 0 ? '?id=' . $orderId : '';

    if (!$hasOrders) {
        flash_set('error', 'Falta la tabla de pedidos.');
        header('Location: pedidos.php');
        exit;
    }

    if ($action === 'status') {
        $allowedStatuses = ['new', 'pendiente', 'confirmado', 'reviewed', 'processing', 'invoiced', 'completed', 'cancelled', 'anulado', 'archivado'];
        $status = (string) ($_POST['status'] ?? 'new');
        if (!in_array($status, $allowedStatuses, true)) {
            flash_set('error', 'Estado de pedido invalido.');
            header('Location: pedidos.php');
            exit;
        }
        update_order_status($orderId, $status, trim((string) ($_POST['notes'] ?? '')));
        flash_set('success', 'Estado del pedido actualizado.');
        header('Location: pedidos.php?id=' . $orderId);
        exit;
    }

    if ($action === 'cancel' && $orderId > 0) {
        update_order_status($orderId, 'anulado', trim((string) ($_POST['notes'] ?? 'Pedido anulado desde admin.')));
        flash_set('success', 'Pedido anulado. No se elimino ningun dato.');
        header('Location: pedidos.php?id=' . $orderId);
        exit;
    }

    if ($action === 'archive' && $orderId > 0) {
        update_order_status($orderId, 'archivado', trim((string) ($_POST['notes'] ?? 'Pedido archivado desde admin.')));
        flash_set('success', 'Pedido archivado. No se elimino ningun dato.');
        header('Location: pedidos.php?id=' . $orderId);
        exit;
    }

    if ($action === 'mark_test' && $orderId > 0 && $orderColumns['is_test']) {
        $markAsTest = (int) ($_POST['is_test'] ?? 1) === 1 ? 1 : 0;
        $sets = ['is_test = :is_test'];
        $params = ['id' => $orderId, 'is_test' => $markAsTest];
        if ($orderColumns['updated_at']) {
            $sets[] = 'updated_at = NOW()';
        }
        db()->prepare('UPDATE orders SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);
        audit_log($markAsTest === 1 ? 'order.marked_test' : 'order.unmarked_test', 'orders', $orderId);
        flash_set('success', $markAsTest === 1 ? 'Pedido marcado como prueba.' : 'Pedido desmarcado como prueba.');
        header('Location: pedidos.php?id=' . $orderId);
        exit;
    }

    if ($action === 'delete_test' && $orderId > 0 && $orderColumns['is_test']) {
        $stmt = db()->prepare('SELECT is_test FROM orders WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $orderId]);
        $order = $stmt->fetch();
        if (!$order || (int) ($order['is_test'] ?? 0) !== 1) {
            flash_set('error', 'Solo se pueden eliminar pedidos marcados como prueba.');
            header('Location: pedidos.php' . $redirectId);
            exit;
        }
        try {
            $deleted = delete_test_order_safely($orderId);
            if ($deleted <= 0) {
                flash_set('error', 'No se pudo eliminar el pedido de prueba. Verifica que siga marcado como prueba.');
                header('Location: pedidos.php' . $redirectId);
                exit;
            }
        } catch (Throwable $exception) {
            audit_log('order.test_delete_failed', 'orders', $orderId, [
                'error' => $exception->getMessage(),
            ]);
            flash_set('error', 'No se pudo eliminar el pedido de prueba: ' . $exception->getMessage());
            header('Location: pedidos.php' . $redirectId);
            exit;
        }
        flash_set('success', 'Pedido de prueba eliminado. Los pedidos reales no se tocaron.');
        header('Location: pedidos.php');
        exit;
    }

    if ($action === 'cleanup_tests' && $orderColumns['is_test']) {
        $confirmOne = trim((string) ($_POST['confirm_cleanup'] ?? ''));
        $confirmTwo = trim((string) ($_POST['confirm_cleanup_text'] ?? ''));
        if ($confirmOne !== '1' || strtoupper($confirmTwo) !== 'LIMPIAR PRUEBAS') {
            flash_set('error', 'Debes confirmar dos veces para limpiar pedidos de prueba.');
            header('Location: pedidos.php');
            exit;
        }

        $sets = [];
        if ($orderColumns['status']) {
            $sets[] = "status = 'archivado'";
        }
        if ($orderColumns['deleted_at']) {
            $sets[] = 'deleted_at = NOW()';
        }
        if ($orderColumns['deleted_by']) {
            $sets[] = 'deleted_by = :deleted_by';
        }
        if ($orderColumns['updated_at']) {
            $sets[] = 'updated_at = NOW()';
        }
        if (!$sets) {
            flash_set('error', 'No hay columnas disponibles para limpiar pedidos de prueba.');
            header('Location: pedidos.php');
            exit;
        }

        $params = [];
        if ($orderColumns['deleted_by']) {
            $params['deleted_by'] = current_user()['id'] ?? null;
        }
        $whereDeleted = $orderColumns['deleted_at'] ? ' AND deleted_at IS NULL' : '';
        $stmt = db()->prepare('UPDATE orders SET ' . implode(', ', $sets) . ' WHERE is_test = 1' . $whereDeleted);
        $stmt->execute($params);
        $affected = $stmt->rowCount();
        audit_log('order.test_cleanup_archived', 'orders', null, [
            'affected' => $affected,
            'mode' => 'archive_only',
        ]);
        flash_set('success', 'Pedidos de prueba archivados: ' . $affected . '. Los pedidos reales no se eliminaron.');
        header('Location: pedidos.php');
        exit;
    }

    flash_set('error', 'Accion no valida para pedidos.');
    header('Location: pedidos.php' . $redirectId);
    exit;
}

$orderId = (int) ($_GET['id'] ?? 0);
if ($orderId > 0) {
    if (!$hasOrders) {
        admin_header('Detalle de pedido', 'pedidos.php');
        echo '<section class="card">Falta la tabla de pedidos. Ejecuta la migracion SQL.</section>';
        admin_footer();
        exit;
    }
    $catalogJoin = $hasCatalogs && $orderColumns['catalog_id'] ? 'LEFT JOIN catalogs c ON c.id = o.catalog_id' : '';
    $catalogTitle = $hasCatalogs && admin_column_exists('catalogs', 'title') && $orderColumns['catalog_id'] ? 'c.title' : "''";
    $catalogUrl = $hasCatalogs && admin_column_exists('catalogs', 'public_url') && $orderColumns['catalog_id'] ? 'c.public_url' : "''";
    $catalogJsonPath = $hasCatalogs && admin_column_exists('catalogs', 'catalog_json_path') && $orderColumns['catalog_id'] ? 'c.catalog_json_path' : "''";
    $catalogApiPayload = $hasCatalogs && admin_column_exists('catalogs', 'api_payload') && $orderColumns['catalog_id'] ? 'c.api_payload' : "''";
    $sellerJoin = $hasSellers && $orderColumns['seller_id'] ? 'LEFT JOIN sellers s ON s.id = o.seller_id' : '';
    $sellerName = $hasSellers && $orderColumns['seller_id'] ? 's.name' : "''";
    $clientJoin = $hasClients && $orderColumns['client_id'] ? 'LEFT JOIN clients cl ON cl.id = o.client_id' : '';
    $clientName = $hasClients && $orderColumns['client_id'] ? 'cl.business_name' : "''";
    $stmt = db()->prepare(
        "SELECT o.*, {$catalogTitle} AS catalog_title, {$catalogUrl} AS public_url,
                {$catalogJsonPath} AS catalog_json_path, {$catalogApiPayload} AS api_payload,
                {$sellerName} AS seller_display_name, {$clientName} AS client_business_name
         FROM orders o
         {$catalogJoin}
         {$sellerJoin}
         {$clientJoin}
         WHERE o.id = :id
         LIMIT 1"
    );
    $stmt->execute(['id' => $orderId]);
    $order = $stmt->fetch();
    $items = [];
    $history = [];
    if ($order && $hasItems) {
        $itemsStmt = db()->prepare('SELECT * FROM order_items WHERE order_id = :order_id ORDER BY id ASC');
        $itemsStmt->execute(['order_id' => $orderId]);
        $items = hydrate_order_item_image_urls($order, $itemsStmt->fetchAll());
    }
    if ($order && $hasHistory) {
        $historyStmt = db()->prepare('SELECT * FROM order_status_history WHERE order_id = :order_id ORDER BY created_at DESC');
        $historyStmt->execute(['order_id' => $orderId]);
        $history = $historyStmt->fetchAll();
    }

    admin_header('Detalle de pedido', 'pedidos.php');
    if (!$order) {
        echo '<section class="card">Pedido no encontrado.</section>';
        admin_footer();
        exit;
    }
    ?>
    <div class="split">
        <section class="card">
            <div class="toolbar">
                <strong><?= html_escape($order['order_number'] ?? ('PED-' . (int) $order['id'])) ?></strong>
                <div class="toolbar__actions">
                    <?php if ($orderColumns['is_test'] && (int) ($order['is_test'] ?? 0) === 1): ?><span class="badge badge--warn">Prueba</span><?php endif; ?>
                    <?= admin_status_badge((string) ($order['status'] ?? 'new')) ?>
                </div>
            </div>
            <?php if ($orderColumns['deleted_at'] && !empty($order['deleted_at'])): ?>
                <div class="flash flash--error">Este pedido fue limpiado o archivado el <?= html_escape($order['deleted_at']) ?>. Se conserva para trazabilidad.</div>
            <?php endif; ?>
            <div class="form-grid" style="margin-bottom:18px;">
                <?php $salesContact = sales_contact_info(); ?>
                <div><strong>Catalogo</strong><div class="muted"><?= html_escape($order['catalog_title']) ?></div></div>
                <div><strong>Vendedor</strong><div class="muted"><?= html_escape($order['seller_display_name'] ?: $order['seller_name'] ?: 'Sin vendedor') ?></div></div>
                <?php if ($orderColumns['seller_token'] && !empty($order['seller_token'])): ?>
                    <div><strong>Token vendedor</strong><div class="muted"><code><?= html_escape(substr((string) $order['seller_token'], 0, 16)) ?>...</code></div></div>
                <?php endif; ?>
                <div><strong>Cliente asociado</strong><div class="muted"><?= html_escape($order['client_business_name'] ?: 'Sin cliente') ?></div></div>
                <div><strong>Empresa</strong><div class="muted"><?= html_escape(($order['company_name'] ?? '') ?: ($order['customer_name'] ?? '')) ?></div></div>
                <div><strong>Contacto</strong><div class="muted"><?= html_escape(($order['contact_name'] ?? '') ?: ($order['customer_name'] ?? '')) ?></div></div>
                <div><strong>Telefono</strong><div class="muted"><?= html_escape(($order['contact_phone'] ?? '') ?: ($order['customer_phone'] ?? '')) ?></div></div>
                <div><strong>Correo</strong><div class="muted"><?= html_escape(($order['contact_email'] ?? '') ?: ($order['customer_email'] ?? '')) ?></div></div>
                <div><strong>Contacto comercial</strong><div class="muted"><?= html_escape($salesContact['name']) ?></div></div>
                <div><strong>Correo comercial</strong><div class="muted"><?= html_escape($salesContact['email']) ?></div></div>
                <div><strong>Telefono comercial</strong><div class="muted"><?= html_escape($salesContact['phone']) ?></div></div>
                <div><strong>Zona</strong><div class="muted"><?= html_escape($order['address_zone'] ?? '') ?></div></div>
                <?php if ($orderColumns['customer_confirmed']): ?>
                    <div><strong>Confirmacion cliente</strong><div class="muted"><?= (int) ($order['customer_confirmed'] ?? 0) === 1 ? 'Pedido confirmado por cliente' : 'Sin confirmacion registrada' ?></div></div>
                <?php endif; ?>
                <?php if ($orderColumns['confirmed_at']): ?>
                    <div><strong>Fecha confirmacion</strong><div class="muted"><?= html_escape($order['confirmed_at'] ?? '') ?></div></div>
                <?php endif; ?>
                <?php if ($orderColumns['customer_ip']): ?>
                    <div><strong>IP cliente</strong><div class="muted"><?= html_escape($order['customer_ip'] ?? '') ?></div></div>
                <?php endif; ?>
            </div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Imagen</th><th>ITEM</th><th>Descripcion</th><th>Cantidad</th><th>Unidad</th><th>Empaque</th><th>Piezas</th><th>Total</th></tr></thead>
                    <tbody>
                    <?php foreach ($items as $item): ?>
                        <?php $imageUrl = safeImageUrl((string) ($item['image_url'] ?? ''), 'https://rodeoimportzl.com/catalogos_admin/assets/no-image.png'); ?>
                        <tr>
                            <td>
                                <?php if (!empty($item['image_url'])): ?>
                                    <button type="button" class="order-image-thumb" data-full-image="<?= html_escape($imageUrl) ?>" aria-label="Ver imagen de <?= html_escape($item['item_code']) ?>">
                                        <img src="<?= html_escape($imageUrl) ?>" alt="<?= html_escape($item['item_code']) ?>" loading="lazy">
                                    </button>
                                <?php else: ?>
                                    <span class="order-image-placeholder">Sin imagen</span>
                                <?php endif; ?>
                            </td>
                            <td><?= html_escape($item['item_code']) ?></td>
                            <td><?= html_escape($item['description']) ?></td>
                            <td><?= html_escape(rtrim(rtrim(number_format((float) $item['quantity'], 2, '.', ''), '0'), '.')) ?></td>
                            <td><?= html_escape($item['sale_unit'] ?? 'unidad') ?></td>
                            <td><?= html_escape($item['package_label'] ?? '') ?> <?= html_escape(isset($item['package_qty']) ? rtrim(rtrim(number_format((float) $item['package_qty'], 2, '.', ''), '0'), '.') : '') ?></td>
                            <td><?= html_escape(isset($item['pieces_total']) ? rtrim(rtrim(number_format((float) $item['pieces_total'], 2, '.', ''), '0'), '.') : '') ?></td>
                            <td><?= html_escape(number_format((float) ($item['line_total'] ?? $item['price'] ?? 0), 2)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="toolbar" style="margin-top:16px;">
                <strong>Total general: <?= html_escape(number_format((float) ($order['total'] ?? 0), 2)) ?></strong>
                <div class="toolbar__actions">
                    <a class="button" href="../catalogos_api/export_order.php?id=<?= (int) $order['id'] ?>">CSV</a>
                    <a class="button" href="../catalogos_api/export_order.php?id=<?= (int) $order['id'] ?>&format=xlsx">XLSX</a>
                    <a class="button" href="../catalogos_api/export_order.php?id=<?= (int) $order['id'] ?>&format=pdf" target="_blank">PDF/Print</a>
                </div>
            </div>
        </section>
        <section class="grid">
            <div class="card">
                <div class="toolbar"><strong>Cambiar estado</strong></div>
                <form class="grid" method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="status">
                    <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                    <label><span>Nuevo estado</span><select name="status"><?php foreach (['new','pendiente','confirmado','processing','invoiced','completed','reviewed','cancelled','anulado','archivado'] as $status): ?><option value="<?= $status ?>" <?= $status === ($order['status'] ?? '') ? 'selected' : '' ?>><?= html_escape(admin_state_label($status)) ?></option><?php endforeach; ?></select></label>
                    <label><span>Notas</span><textarea name="notes"></textarea></label>
                    <button class="button--primary" type="submit">Actualizar</button>
                </form>
            </div>
            <div class="card">
                <div class="toolbar"><strong>Acciones seguras</strong></div>
                <p class="muted">Esta accion solo aplica a pedidos de prueba. Los pedidos reales no se eliminan.</p>
                <div class="grid">
                    <form method="post" onsubmit="return confirm('Anular este pedido? No se eliminara informacion.');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="cancel">
                        <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                        <button type="submit" class="button--danger">Anular pedido</button>
                    </form>
                    <form method="post" onsubmit="return confirm('Archivar este pedido? Seguira disponible para trazabilidad.');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="archive">
                        <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                        <button type="submit">Archivar pedido</button>
                    </form>
                    <?php if ($orderColumns['is_test']): ?>
                        <form method="post" onsubmit="return confirm('Actualizar marca de prueba para este pedido?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="mark_test">
                            <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                            <input type="hidden" name="is_test" value="<?= (int) ($order['is_test'] ?? 0) === 1 ? 0 : 1 ?>">
                            <button type="submit"><?= (int) ($order['is_test'] ?? 0) === 1 ? 'Quitar marca de prueba' : 'Marcar como prueba' ?></button>
                        </form>
                        <?php if ((int) ($order['is_test'] ?? 0) === 1): ?>
                            <form method="post" onsubmit="return confirm('Primer aviso: solo se eliminara porque esta marcado como prueba.') && confirm('Segundo aviso: esta eliminacion fisica no aplica a pedidos reales. Continuar?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_test">
                                <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                                <button type="submit" class="button--danger">Eliminar pedido de prueba</button>
                            </form>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="flash flash--error">Ejecuta la migracion de FASE 1 para habilitar marcado de pruebas.</div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card">
                <div class="toolbar"><strong>Historial</strong></div>
                <div class="list">
                    <?php foreach ($history as $row): ?>
                        <div class="list-item">
                            <strong><?= html_escape($row['to_status']) ?></strong>
                            <div class="muted"><?= html_escape($row['created_at']) ?></div>
                            <div class="muted"><?= html_escape($row['notes']) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    </div>
    <style>
        .order-image-thumb { width:60px; height:60px; padding:0; border:1px solid #d9deea; border-radius:8px; background:#fff; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; }
        .order-image-thumb img { width:60px; height:60px; object-fit:contain; border-radius:8px; display:block; }
        .order-image-placeholder { width:60px; height:60px; border:1px solid #d9deea; border-radius:8px; background:#f4f6f8; color:#667085; display:inline-flex; align-items:center; justify-content:center; font-size:11px; text-align:center; }
        .order-image-lightbox { position:fixed; inset:0; z-index:9999; background:rgba(10,18,32,.78); display:none; align-items:center; justify-content:center; padding:24px; }
        .order-image-lightbox.open { display:flex; }
        .order-image-lightbox__panel { position:relative; max-width:min(920px, 94vw); max-height:90vh; background:#fff; border-radius:10px; padding:18px; box-shadow:0 18px 60px rgba(0,0,0,.3); }
        .order-image-lightbox__panel img { max-width:100%; max-height:78vh; object-fit:contain; display:block; }
        .order-image-lightbox__close { position:absolute; top:8px; right:8px; border:0; border-radius:999px; width:32px; height:32px; background:#2c4695; color:#fff; font-size:20px; line-height:32px; cursor:pointer; }
    </style>
    <div class="order-image-lightbox" id="orderImageLightbox" aria-hidden="true">
        <div class="order-image-lightbox__panel">
            <button type="button" class="order-image-lightbox__close" aria-label="Cerrar">&times;</button>
            <img src="" alt="Imagen de producto">
        </div>
    </div>
    <script>
        (() => {
            const lightbox = document.getElementById("orderImageLightbox");
            if (!lightbox) return;
            const image = lightbox.querySelector("img");
            const close = () => {
                lightbox.classList.remove("open");
                lightbox.setAttribute("aria-hidden", "true");
                if (image) image.src = "";
            };
            document.querySelectorAll(".order-image-thumb").forEach((button) => {
                button.addEventListener("click", () => {
                    if (!image || !button.dataset.fullImage) return;
                    image.src = button.dataset.fullImage;
                    lightbox.classList.add("open");
                    lightbox.setAttribute("aria-hidden", "false");
                });
            });
            lightbox.querySelector(".order-image-lightbox__close")?.addEventListener("click", close);
            lightbox.addEventListener("click", (event) => {
                if (event.target === lightbox) close();
            });
            document.addEventListener("keydown", (event) => {
                if (event.key === "Escape") close();
            });
        })();
    </script>
    <?php
    admin_footer();
    exit;
}

$sellerFilter = (int) ($_GET['seller_id'] ?? 0);
$linkFilter = (int) ($_GET['link_id'] ?? 0);
$showArchived = (int) ($_GET['archivados'] ?? 0) === 1;
$conditions = [];
$params = [];
if ($sellerFilter > 0 && $orderColumns['seller_id']) {
    $conditions[] = 'o.seller_id = :seller_id';
    $params['seller_id'] = $sellerFilter;
}
if ($linkFilter > 0 && $orderColumns['share_link_id']) {
    $conditions[] = 'o.share_link_id = :link_id';
    $params['link_id'] = $linkFilter;
}
if (!$showArchived && $orderColumns['deleted_at']) {
    $conditions[] = 'o.deleted_at IS NULL';
}
$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
$orders = [];
$testOrdersCount = 0;
if ($hasOrders) {
    $orderNumberExpr = $orderColumns['order_number'] ? 'o.order_number' : "CONCAT('PED-', o.id)";
    $companyExpr = $orderColumns['company_name'] ? 'o.company_name' : ($orderColumns['customer_name'] ? 'o.customer_name' : "''");
    $contactExpr = $orderColumns['contact_name'] ? 'o.contact_name' : ($orderColumns['customer_name'] ? 'o.customer_name' : "''");
    $totalExpr = $orderColumns['total'] ? 'o.total' : '0';
    $statusExpr = $orderColumns['status'] ? 'o.status' : "'new'";
    $createdExpr = $orderColumns['created_at'] ? 'o.created_at' : "''";
    $catalogJoin = $hasCatalogs && $orderColumns['catalog_id'] ? 'LEFT JOIN catalogs c ON c.id = o.catalog_id' : '';
    $catalogExpr = $hasCatalogs && $orderColumns['catalog_id'] && admin_column_exists('catalogs', 'title') ? 'c.title' : "''";
    $sellerJoin = $hasSellers && $orderColumns['seller_id'] ? 'LEFT JOIN sellers s ON s.id = o.seller_id' : '';
    $sellerExpr = $hasSellers && $orderColumns['seller_id'] ? 's.name' : "''";
    $orderBy = $orderColumns['created_at'] ? 'o.created_at DESC' : 'o.id DESC';
    $isTestExpr = $orderColumns['is_test'] ? 'o.is_test' : '0';
    $deletedAtExpr = $orderColumns['deleted_at'] ? 'o.deleted_at' : 'NULL';
    $customerConfirmedExpr = $orderColumns['customer_confirmed'] ? 'o.customer_confirmed' : '0';
    $ordersStmt = db()->prepare(
        "SELECT o.id, {$orderNumberExpr} AS order_number, {$companyExpr} AS company_name, {$contactExpr} AS contact_name,
                {$totalExpr} AS total, {$statusExpr} AS status, {$createdExpr} AS created_at,
                {$catalogExpr} AS catalog_title, {$sellerExpr} AS seller_name,
                {$isTestExpr} AS is_test, {$deletedAtExpr} AS deleted_at,
                {$customerConfirmedExpr} AS customer_confirmed
         FROM orders o
         {$catalogJoin}
         {$sellerJoin}
         {$where}
         ORDER BY {$orderBy}
         LIMIT 200"
    );
    $ordersStmt->execute($params);
    $orders = $ordersStmt->fetchAll();

    if ($orderColumns['is_test']) {
        $testWhere = $orderColumns['deleted_at'] ? 'WHERE is_test = 1 AND deleted_at IS NULL' : 'WHERE is_test = 1';
        $testOrdersCount = (int) db()->query('SELECT COUNT(*) FROM orders ' . $testWhere)->fetchColumn();
    }
}

admin_header('Pedidos', 'pedidos.php');
?>
<?php if (!$hasOrders): ?>
    <section class="card">
        <strong>Falta la tabla de pedidos.</strong>
        <p class="muted">Ejecuta la migracion SQL antes de usar este modulo.</p>
    </section>
    <?php admin_footer(); exit; ?>
<?php endif; ?>
<section class="card">
    <div class="toolbar">
        <strong>Pedidos registrados</strong>
        <div class="toolbar__actions">
            <?php if ($showArchived): ?><a class="button" href="pedidos.php">Ocultar limpiados</a><?php else: ?><a class="button" href="pedidos.php?archivados=1">Ver limpiados</a><?php endif; ?>
            <?php if ($sellerFilter > 0 || $linkFilter > 0): ?><a class="button" href="pedidos.php">Ver todos</a><?php endif; ?>
            <span class="pill"><?= count($orders) ?> resultados</span>
        </div>
    </div>
    <div class="flash flash--error">Esta accion solo aplica a pedidos de prueba. Los pedidos reales no se eliminan.</div>
    <form class="toolbar" method="post" onsubmit="return confirm('Primer aviso: se archivaran solo pedidos marcados como prueba. Los pedidos reales no se eliminan.') && confirm('Segundo aviso: confirma que deseas limpiar pedidos de prueba.');" style="margin-bottom:16px;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="cleanup_tests">
        <input type="hidden" name="confirm_cleanup" value="1">
        <label style="max-width:280px;"><span>Confirmacion escrita</span><input name="confirm_cleanup_text" placeholder="LIMPIAR PRUEBAS" autocomplete="off"></label>
        <button class="button--danger" type="submit" <?= $testOrdersCount <= 0 ? 'disabled' : '' ?>>Limpiar pedidos de prueba</button>
        <span class="pill"><?= $testOrdersCount ?> pedidos de prueba activos</span>
    </form>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Pedido</th><th>Catalogo</th><th>Vendedor</th><th>Empresa</th><th>Contacto</th><th>Total</th><th>Estado</th><th>Fecha</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($orders as $order): ?>
                <tr>
                    <td>
                        <strong><?= html_escape($order['order_number']) ?></strong>
                        <?php if ((int) ($order['is_test'] ?? 0) === 1): ?><div><span class="badge badge--warn">Prueba</span></div><?php endif; ?>
                        <?php if (!empty($order['deleted_at'])): ?><div class="muted">Limpiado: <?= html_escape($order['deleted_at']) ?></div><?php endif; ?>
                    </td>
                    <td><?= html_escape($order['catalog_title']) ?></td>
                    <td><?= html_escape($order['seller_name'] ?: 'Sin vendedor') ?></td>
                    <td><?= html_escape($order['company_name']) ?></td>
                    <td><?= html_escape($order['contact_name']) ?></td>
                    <td><?= html_escape(number_format((float) $order['total'], 2)) ?></td>
                    <td>
                        <?= admin_status_badge((string) $order['status']) ?>
                        <?php if ((int) ($order['customer_confirmed'] ?? 0) === 1): ?><div class="muted">Pedido confirmado por cliente</div><?php endif; ?>
                    </td>
                    <td><?= html_escape($order['created_at']) ?></td>
                    <td>
                        <div class="toolbar__actions">
                            <a class="button" href="pedidos.php?id=<?= (int) $order['id'] ?>">Ver pedido</a>
                            <?php if ($orderColumns['is_test']): ?>
                                <form method="post" onsubmit="return confirm('Marcar este pedido como prueba?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="mark_test">
                                    <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                                    <input type="hidden" name="is_test" value="1">
                                    <button type="submit">Marcar prueba</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php admin_footer();

function delete_test_order_safely(int $orderId): int
{
    if ($orderId <= 0 || !admin_table_exists('orders') || !admin_column_exists('orders', 'is_test')) {
        return 0;
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare('SELECT id, is_test FROM orders WHERE id = :id FOR UPDATE');
        $statement->execute(['id' => $orderId]);
        $order = $statement->fetch();
        if (!$order || (int) ($order['is_test'] ?? 0) !== 1) {
            $pdo->rollBack();
            return 0;
        }

        $relatedDeletes = [];
        foreach (['notifications_log', 'order_status_history', 'order_items'] as $table) {
            if (!admin_table_exists($table) || !admin_column_exists($table, 'order_id')) {
                continue;
            }
            $delete = $pdo->prepare('DELETE FROM `' . $table . '` WHERE order_id = :order_id');
            $delete->execute(['order_id' => $orderId]);
            $relatedDeletes[$table] = $delete->rowCount();
        }

        audit_log('order.test_deleted', 'orders', $orderId, [
            'reason' => 'Eliminacion fisica permitida solo para pedidos de prueba.',
            'related_deleted' => $relatedDeletes,
        ]);

        $deleteOrder = $pdo->prepare('DELETE FROM orders WHERE id = :id AND is_test = 1');
        $deleteOrder->execute(['id' => $orderId]);
        $deleted = $deleteOrder->rowCount();
        $pdo->commit();
        return $deleted;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}
