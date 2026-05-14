<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
vendor_require_panel_login();

$sellerId = (int) (vendor_current_user()['seller_id'] ?? 0);
$sellerPublicToken = (string) (vendor_current_user()['seller_public_token'] ?? '');
$catalogs = [];
$schemaReady = vendor_table_exists('catalogs');
if ($schemaReady) {
    $hasSellerId = vendor_column_exists('catalogs', 'seller_id');
    $hasSellerName = vendor_column_exists('catalogs', 'seller_name');
    $hasLinks = vendor_table_exists('catalog_share_links') && vendor_column_exists('catalog_share_links', 'catalog_id');
    $hasLinkSellerId = $hasLinks && vendor_column_exists('catalog_share_links', 'seller_id');
    $linksWhere = $hasLinkSellerId ? ' AND l.seller_id = :link_seller_id' : '';
    $linksSelect = $hasLinks ? "(SELECT COUNT(*) FROM catalog_share_links l WHERE l.catalog_id = c.id{$linksWhere}) AS links_count" : '0 AS links_count';
    $conditions = [];
    $params = [];
    if ($hasLinkSellerId) $params['link_seller_id'] = $sellerId;
    if ($hasSellerId) {
        $conditions[] = 'c.seller_id = :catalog_seller_id';
        $params['catalog_seller_id'] = $sellerId;
    }
    if ($hasSellerName) {
        $conditions[] = 'c.seller_name = :seller_name';
        $params['seller_name'] = vendor_current_user()['seller_display_name'] ?? '';
    }
    if ($hasLinkSellerId) {
        $conditions[] = 'EXISTS (SELECT 1 FROM catalog_share_links lx WHERE lx.catalog_id = c.id AND lx.seller_id = :exists_link_seller_id)';
        $params['exists_link_seller_id'] = $sellerId;
    }
    $where = $conditions ? 'WHERE ' . implode(' OR ', $conditions) : '';
    $orderBy = vendor_column_exists('catalogs', 'updated_at') ? 'c.updated_at DESC' : 'c.id DESC';
    $stmt = db()->prepare(
        "SELECT DISTINCT c.*, {$linksSelect}
         FROM catalogs c
         {$where}
         ORDER BY {$orderBy}"
    );
    $stmt->execute($params);
    $catalogs = $stmt->fetchAll();
}

vendor_header('Mis catalogos', 'catalogos.php');
?>
<section class="card">
    <div class="toolbar"><strong>Catalogos asignados</strong></div>
    <?php if (!$schemaReady): ?>
        <p class="muted">Falta la tabla de catalogos. Ejecuta la migracion SQL.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Slug</th><th>Titulo</th><th>Estado</th><th>Vence</th><th>Links</th><th>Link vendedor</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($catalogs as $catalog): ?>
                <?php $sellerUrl = !empty($catalog['public_url']) && $sellerPublicToken !== '' ? rtrim((string) $catalog['public_url'], '/') . '/?t=' . $sellerPublicToken : ''; ?>
                <tr>
                    <td><?= html_escape($catalog['slug']) ?></td>
                    <td><?= html_escape($catalog['title']) ?></td>
                    <td><?= admin_status_badge(resolve_catalog_status($catalog)) ?></td>
                    <td><?= html_escape($catalog['expires_at']) ?></td>
                    <td><?= (int) $catalog['links_count'] ?></td>
                    <td><?php if ($sellerUrl !== ''): ?><input class="link-url" type="text" value="<?= html_escape($sellerUrl) ?>" readonly><?php endif; ?></td>
                    <td><?php if (!empty($catalog['public_url'])): ?><a class="button" href="<?= html_escape($catalog['public_url']) ?>" target="_blank">Abrir</a><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>
<?php vendor_footer(); ?>
