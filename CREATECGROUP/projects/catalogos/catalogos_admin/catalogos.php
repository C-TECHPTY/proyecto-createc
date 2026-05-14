<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
admin_require_login();

$hasCatalogs = admin_table_exists('catalogs');
$hasSellers = admin_table_exists('sellers');
$hasClients = admin_table_exists('clients');
$hasLinks = admin_table_exists('catalog_share_links');
$hasOrders = admin_table_exists('orders');
$catalogColumns = [
    'id' => $hasCatalogs && admin_column_exists('catalogs', 'id'),
    'slug' => $hasCatalogs && admin_column_exists('catalogs', 'slug'),
    'title' => $hasCatalogs && admin_column_exists('catalogs', 'title'),
    'status' => $hasCatalogs && admin_column_exists('catalogs', 'status'),
    'expires_at' => $hasCatalogs && admin_column_exists('catalogs', 'expires_at'),
    'updated_at' => $hasCatalogs && admin_column_exists('catalogs', 'updated_at'),
    'created_at' => $hasCatalogs && admin_column_exists('catalogs', 'created_at'),
    'generated_at' => $hasCatalogs && admin_column_exists('catalogs', 'generated_at'),
    'public_url' => $hasCatalogs && admin_column_exists('catalogs', 'public_url'),
    'seller_id' => $hasCatalogs && admin_column_exists('catalogs', 'seller_id'),
    'client_id' => $hasCatalogs && admin_column_exists('catalogs', 'client_id'),
    'seller_name' => $hasCatalogs && admin_column_exists('catalogs', 'seller_name'),
    'client_name' => $hasCatalogs && admin_column_exists('catalogs', 'client_name'),
    'hero_title' => $hasCatalogs && admin_column_exists('catalogs', 'hero_title'),
    'hero_subtitle' => $hasCatalogs && admin_column_exists('catalogs', 'hero_subtitle'),
    'promo_title' => $hasCatalogs && admin_column_exists('catalogs', 'promo_title'),
    'promo_text' => $hasCatalogs && admin_column_exists('catalogs', 'promo_text'),
    'promo_image_url' => $hasCatalogs && admin_column_exists('catalogs', 'promo_image_url'),
    'promo_video_url' => $hasCatalogs && admin_column_exists('catalogs', 'promo_video_url'),
    'promo_link_url' => $hasCatalogs && admin_column_exists('catalogs', 'promo_link_url'),
    'promo_link_label' => $hasCatalogs && admin_column_exists('catalogs', 'promo_link_label'),
    'legacy_pdf_url' => $hasCatalogs && admin_column_exists('catalogs', 'legacy_pdf_url'),
    'modern_pdf_url' => $hasCatalogs && admin_column_exists('catalogs', 'modern_pdf_url'),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_abort();
    $action = (string) ($_POST['action'] ?? '');
    $catalogId = (int) ($_POST['catalog_id'] ?? 0);

    if ($action === 'save' && $catalogId > 0) {
        $fields = [
            'title' => trim((string) ($_POST['title'] ?? '')),
            'status' => trim((string) ($_POST['status'] ?? 'active')),
            'expires_at' => parse_datetime_or_null((string) ($_POST['expires_at'] ?? '')),
            'seller_name' => trim((string) ($_POST['seller_name'] ?? '')),
            'client_name' => trim((string) ($_POST['client_name'] ?? '')),
            'hero_title' => trim((string) ($_POST['hero_title'] ?? '')),
            'hero_subtitle' => trim((string) ($_POST['hero_subtitle'] ?? '')),
            'promo_title' => trim((string) ($_POST['promo_title'] ?? '')),
            'promo_text' => trim((string) ($_POST['promo_text'] ?? '')),
            'promo_image_url' => trim((string) ($_POST['promo_image_url'] ?? '')),
            'promo_video_url' => trim((string) ($_POST['promo_video_url'] ?? '')),
            'promo_link_url' => trim((string) ($_POST['promo_link_url'] ?? '')),
            'promo_link_label' => trim((string) ($_POST['promo_link_label'] ?? '')),
            'legacy_pdf_url' => trim((string) ($_POST['legacy_pdf_url'] ?? '')),
            'modern_pdf_url' => trim((string) ($_POST['modern_pdf_url'] ?? '')),
        ];
        $sets = [];
        $params = ['id' => $catalogId];
        foreach ($fields as $field => $value) {
            if (!$catalogColumns[$field]) continue;
            $sets[] = "`{$field}` = :{$field}";
            $params[$field] = $value;
        }
        if ($catalogColumns['updated_at']) $sets[] = 'updated_at = NOW()';
        if ($sets) {
            db()->prepare('UPDATE catalogs SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);
        }
        audit_log('catalog.updated', 'catalogs', $catalogId);
        flash_set('success', 'Catalogo actualizado correctamente.');
        header('Location: catalogos.php');
        exit;
    }

    if ($action === 'toggle' && $catalogId > 0) {
        if ($catalogColumns['status']) {
            $updated = $catalogColumns['updated_at'] ? ', updated_at = NOW()' : '';
            db()->prepare(
                "UPDATE catalogs
                 SET status = CASE WHEN status = 'active' THEN 'archived' ELSE 'active' END{$updated}
                 WHERE id = :id"
            )->execute(['id' => $catalogId]);
        }
        audit_log('catalog.toggled', 'catalogs', $catalogId);
        flash_set('success', 'Estado del catalogo actualizado.');
        header('Location: catalogos.php');
        exit;
    }

    if ($action === 'delete' && $catalogId > 0) {
        admin_delete_catalog($catalogId);
        header('Location: catalogos.php');
        exit;
    }
}

$catalogs = [];
if ($hasCatalogs) {
    $sellerJoin = $hasSellers && $catalogColumns['seller_id'] ? 'LEFT JOIN sellers s ON s.id = c.seller_id' : '';
    $sellerSelect = $hasSellers && $catalogColumns['seller_id'] ? 's.name AS seller_display_name' : "'' AS seller_display_name";
    $clientJoin = $hasClients && $catalogColumns['client_id'] ? 'LEFT JOIN clients cl ON cl.id = c.client_id' : '';
    $clientSelect = $hasClients && $catalogColumns['client_id'] ? 'cl.business_name AS client_business_name' : "'' AS client_business_name";
    $linksSelect = $hasLinks ? '(SELECT COUNT(*) FROM catalog_share_links l WHERE l.catalog_id = c.id) AS links_count' : '0 AS links_count';
    $ordersSelect = $hasOrders && admin_column_exists('orders', 'catalog_id') ? '(SELECT COUNT(*) FROM orders o WHERE o.catalog_id = c.id) AS orders_count' : '0 AS orders_count';
    $orderBy = $catalogColumns['updated_at'] ? 'c.updated_at DESC' : ($catalogColumns['generated_at'] ? 'c.generated_at DESC' : 'c.id DESC');
    $catalogs = db()->query(
        "SELECT c.*, {$sellerSelect}, {$clientSelect}, {$linksSelect}, {$ordersSelect}
         FROM catalogs c
         {$sellerJoin}
         {$clientJoin}
         ORDER BY {$orderBy}
         LIMIT 200"
    )->fetchAll();
}

$editId = (int) ($_GET['edit'] ?? 0);
$editCatalog = null;
foreach ($catalogs as $catalog) {
    if ((int) $catalog['id'] === $editId) {
        $editCatalog = $catalog;
        break;
    }
}

admin_header('Catalogos', 'catalogos.php');
?>
<?php if (!$hasCatalogs): ?>
    <section class="card">
        <strong>Falta la tabla de catalogos.</strong>
        <p class="muted">Ejecuta la migracion SQL antes de usar este modulo.</p>
    </section>
    <?php admin_footer(); exit; ?>
<?php endif; ?>
<?php if ($editCatalog): ?>
    <section class="card" style="margin-bottom:18px;">
        <div class="toolbar"><strong>Editar catalogo</strong><a class="button" href="catalogos.php">Cancelar</a></div>
        <form class="form-grid" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="catalog_id" value="<?= (int) $editCatalog['id'] ?>">
            <label><span>Titulo</span><input type="text" name="title" value="<?= html_escape($editCatalog['title']) ?>" required></label>
            <?php if ($catalogColumns['status']): ?><label><span>Estado</span><select name="status"><?php foreach (['active','draft','expired','archived'] as $status): ?><option value="<?= $status ?>" <?= $status === ($editCatalog['status'] ?? '') ? 'selected' : '' ?>><?= html_escape($status) ?></option><?php endforeach; ?></select></label><?php endif; ?>
            <?php if ($catalogColumns['seller_name']): ?><label><span>Vendedor visible</span><input type="text" name="seller_name" value="<?= html_escape($editCatalog['seller_name'] ?? '') ?>"></label><?php endif; ?>
            <?php if ($catalogColumns['client_name']): ?><label><span>Cliente visible</span><input type="text" name="client_name" value="<?= html_escape($editCatalog['client_name'] ?? '') ?>"></label><?php endif; ?>
            <?php if ($catalogColumns['hero_title']): ?><label class="wide"><span>Hero title</span><input type="text" name="hero_title" value="<?= html_escape($editCatalog['hero_title'] ?? '') ?>"></label><?php endif; ?>
            <?php if ($catalogColumns['hero_subtitle']): ?><label class="wide"><span>Hero subtitle</span><input type="text" name="hero_subtitle" value="<?= html_escape($editCatalog['hero_subtitle'] ?? '') ?>"></label><?php endif; ?>
            <label><span>Promo title</span><input type="text" name="promo_title" value="<?= html_escape($editCatalog['promo_title'] ?? '') ?>"></label>
            <label><span>Promo texto</span><input type="text" name="promo_text" value="<?= html_escape($editCatalog['promo_text'] ?? '') ?>"></label>
            <label class="wide"><span>Promo imagen URL</span><input type="text" name="promo_image_url" value="<?= html_escape($editCatalog['promo_image_url'] ?? '') ?>"></label>
            <label class="wide"><span>Promo video URL</span><input type="text" name="promo_video_url" value="<?= html_escape($editCatalog['promo_video_url'] ?? '') ?>"></label>
            <label class="wide"><span>Promo link URL</span><input type="text" name="promo_link_url" value="<?= html_escape($editCatalog['promo_link_url'] ?? '') ?>"></label>
            <label><span>Promo CTA</span><input type="text" name="promo_link_label" value="<?= html_escape($editCatalog['promo_link_label'] ?? '') ?>"></label>
            <label class="wide"><span>PDF legado URL</span><input type="text" name="legacy_pdf_url" value="<?= html_escape($editCatalog['legacy_pdf_url'] ?? '') ?>"></label>
            <label class="wide"><span>PDF moderno URL</span><input type="text" name="modern_pdf_url" value="<?= html_escape($editCatalog['modern_pdf_url'] ?? '') ?>"></label>
            <?php if ($catalogColumns['expires_at']): ?><label class="wide"><span>Vence</span><input type="datetime-local" name="expires_at" value="<?= !empty($editCatalog['expires_at']) ? html_escape(date('Y-m-d\TH:i', strtotime((string) $editCatalog['expires_at']))) : '' ?>"></label><?php endif; ?>
            <div class="wide"><button class="button--primary" type="submit">Guardar cambios</button></div>
        </form>
    </section>
<?php endif; ?>

<section class="card">
    <div class="toolbar"><strong>Catalogos publicados</strong><span class="pill"><?= count($catalogs) ?> registros</span></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th class="optional-col">Slug</th><th>Titulo</th><th>Vendedor</th><th>Estado</th><th class="optional-col">Creado</th><th>Expira</th><th>Links</th><th>Pedidos</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($catalogs as $catalog): ?>
                <tr>
                    <td class="optional-col"><?= html_escape($catalog['slug'] ?? '') ?></td>
                    <td><strong><?= html_escape($catalog['title'] ?? '') ?></strong><div class="muted"><?= html_escape($catalog['public_url'] ?? '') ?></div></td>
                    <td><?= html_escape(($catalog['seller_display_name'] ?? '') ?: ($catalog['seller_name'] ?? '') ?: 'Sin vendedor') ?></td>
                    <td><?= admin_status_badge(resolve_catalog_status($catalog)) ?></td>
                    <td class="optional-col"><?= html_escape(($catalog['created_at'] ?? '') ?: ($catalog['generated_at'] ?? '')) ?></td>
                    <td><?= html_escape(($catalog['expires_at'] ?? '') ?: 'Sin vencimiento') ?></td>
                    <td><?= (int) $catalog['links_count'] ?></td>
                    <td><?= (int) $catalog['orders_count'] ?></td>
                    <td>
                        <div class="toolbar__actions catalog-actions">
                            <a class="button" href="catalogos.php?edit=<?= (int) $catalog['id'] ?>">Editar</a>
                            <a class="button" href="links.php?catalog_id=<?= (int) $catalog['id'] ?>">Crear link</a>
                            <a class="button" href="catalog_update_data.php?catalog_id=<?= (int) $catalog['id'] ?>">Actualizar datos</a>
                            <a class="button" href="send_catalog_to_sellers.php?catalog_id=<?= (int) $catalog['id'] ?>">Enviar a vendedores</a>
                            <?php if (!empty($catalog['public_url'])): ?><a class="button" href="<?= html_escape($catalog['public_url']) ?>" target="_blank">Abrir</a><?php endif; ?>
                            <?php if ($catalogColumns['status']): ?><form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="catalog_id" value="<?= (int) $catalog['id'] ?>">
                                <button type="submit"><?= ($catalog['status'] ?? '') === 'active' ? 'Archivar' : 'Activar' ?></button>
                            </form><?php endif; ?>
                            <form method="post" onsubmit="return confirm('Eliminar este catalogo y sus links/pedidos asociados? Esta accion no se puede deshacer.');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="catalog_id" value="<?= (int) $catalog['id'] ?>">
                                <button class="button--danger" type="submit">Eliminar</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php admin_footer(); ?>

<?php
function admin_delete_catalog(int $catalogId): void
{
    $stmt = db()->prepare('SELECT id, slug, title FROM catalogs WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $catalogId]);
    $catalog = $stmt->fetch();
    if (!$catalog) {
        flash_set('error', 'Catalogo no encontrado.');
        return;
    }

    if (admin_table_exists('order_items') && admin_table_exists('orders') && admin_column_exists('orders', 'catalog_id')) {
        db()->prepare('DELETE FROM order_items WHERE order_id IN (SELECT id FROM orders WHERE catalog_id = :id)')->execute(['id' => $catalogId]);
    }
    if (admin_table_exists('order_status_history') && admin_table_exists('orders') && admin_column_exists('orders', 'catalog_id')) {
        db()->prepare('DELETE FROM order_status_history WHERE order_id IN (SELECT id FROM orders WHERE catalog_id = :id)')->execute(['id' => $catalogId]);
    }
    if (admin_table_exists('notifications_log') && admin_table_exists('orders') && admin_column_exists('orders', 'catalog_id')) {
        db()->prepare('DELETE FROM notifications_log WHERE order_id IN (SELECT id FROM orders WHERE catalog_id = :id)')->execute(['id' => $catalogId]);
    }
    if (admin_table_exists('orders') && admin_column_exists('orders', 'catalog_id')) {
        db()->prepare('DELETE FROM orders WHERE catalog_id = :id')->execute(['id' => $catalogId]);
    }
    if (admin_table_exists('catalog_access_logs') && admin_column_exists('catalog_access_logs', 'catalog_id')) {
        db()->prepare('DELETE FROM catalog_access_logs WHERE catalog_id = :id')->execute(['id' => $catalogId]);
    }
    if (admin_table_exists('catalog_share_links') && admin_column_exists('catalog_share_links', 'catalog_id')) {
        db()->prepare('DELETE FROM catalog_share_links WHERE catalog_id = :id')->execute(['id' => $catalogId]);
    }

    db()->prepare('DELETE FROM catalogs WHERE id = :id')->execute(['id' => $catalogId]);
    admin_delete_catalog_directory((string) ($catalog['slug'] ?? ''));
    audit_log('catalog.deleted', 'catalogs', $catalogId, [
        'slug' => $catalog['slug'] ?? '',
        'title' => $catalog['title'] ?? '',
    ]);
    flash_set('success', 'Catalogo eliminado correctamente.');
}

function admin_delete_catalog_directory(string $slug): void
{
    $safeSlug = basename(trim($slug));
    if ($safeSlug === '' || $safeSlug === '.' || $safeSlug === '..') {
        return;
    }
    $baseDir = rtrim((string) catalog_config('paths.public_catalogs_dir', dirname(__DIR__) . '/catalogos'), DIRECTORY_SEPARATOR);
    $targetDir = $baseDir . DIRECTORY_SEPARATOR . $safeSlug;
    if (!is_dir($targetDir)) {
        return;
    }
    admin_delete_directory_recursive($targetDir);
}

function admin_delete_directory_recursive(string $dirPath): void
{
    $items = scandir($dirPath);
    if ($items === false) {
        return;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dirPath . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            admin_delete_directory_recursive($path);
            continue;
        }
        @unlink($path);
    }
    @rmdir($dirPath);
}
?>
