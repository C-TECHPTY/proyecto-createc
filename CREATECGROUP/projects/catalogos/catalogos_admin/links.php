<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
admin_require_login(['admin', 'sales']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_abort();
    $action = (string) ($_POST['action'] ?? 'create');
    if ($action === 'toggle') {
        db()->prepare(
            'UPDATE catalog_share_links
             SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END, updated_at = NOW()
             WHERE id = :id'
        )->execute(['id' => (int) $_POST['link_id']]);
        flash_set('success', 'Estado del link actualizado.');
    } else {
        $created = create_share_link(
            (int) $_POST['catalog_id'],
            (int) ($_POST['seller_id'] ?? 0) ?: null,
            (int) ($_POST['client_id'] ?? 0) ?: null,
            trim((string) ($_POST['label'] ?? 'Link comercial')),
            parse_datetime_or_null((string) ($_POST['expires_at'] ?? '')),
            trim((string) ($_POST['notes'] ?? ''))
        );
        flash_set('success', 'Link creado con token ' . substr($created['token'], 0, 12) . '...');
    }
    header('Location: links.php');
    exit;
}

$catalogs = db()->query("SELECT id, title, slug FROM catalogs WHERE status = 'active' ORDER BY updated_at DESC")->fetchAll();
$sellers = db()->query("SELECT id, name FROM sellers WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
$clients = db()->query("SELECT id, business_name FROM clients WHERE is_active = 1 ORDER BY business_name ASC")->fetchAll();
$selectedCatalogId = (int) ($_GET['catalog_id'] ?? 0);
$sellerFilter = (int) ($_GET['seller_id'] ?? 0);
$where = $sellerFilter > 0 ? 'WHERE l.seller_id = :seller_id' : '';
$linksStmt = db()->prepare(
    "SELECT l.*, c.title AS catalog_title, c.slug AS catalog_slug, c.public_url, s.name AS seller_name, cl.business_name AS client_name,
            (SELECT COUNT(*) FROM orders o WHERE o.share_link_id = l.id) AS orders_count
     FROM catalog_share_links l
     INNER JOIN catalogs c ON c.id = l.catalog_id
     LEFT JOIN sellers s ON s.id = l.seller_id
     LEFT JOIN clients cl ON cl.id = l.client_id
     $where
     ORDER BY l.created_at DESC
     LIMIT 200"
);
$linksStmt->execute($sellerFilter > 0 ? ['seller_id' => $sellerFilter] : []);
$links = $linksStmt->fetchAll();

admin_header('Links compartidos', 'links.php');
?>
<div class="links-layout">
    <section class="card">
        <div class="toolbar"><strong>Generar link seguro</strong></div>
        <form class="form-grid" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <label class="wide"><span>Catalogo</span><select name="catalog_id" required><?php foreach ($catalogs as $catalog): ?><option value="<?= (int) $catalog['id'] ?>" <?= $selectedCatalogId === (int) $catalog['id'] ? 'selected' : '' ?>><?= html_escape($catalog['title']) ?> (<?= html_escape($catalog['slug']) ?>)</option><?php endforeach; ?></select></label>
            <label><span>Vendedor</span><select name="seller_id"><option value="">Sin asignar</option><?php foreach ($sellers as $seller): ?><option value="<?= (int) $seller['id'] ?>"><?= html_escape($seller['name']) ?></option><?php endforeach; ?></select></label>
            <label><span>Cliente</span><select name="client_id"><option value="">Sin asignar</option><?php foreach ($clients as $client): ?><option value="<?= (int) $client['id'] ?>"><?= html_escape($client['business_name']) ?></option><?php endforeach; ?></select></label>
            <label class="wide"><span>Etiqueta</span><input type="text" name="label" value="Link comercial" required></label>
            <label><span>Expira</span><input type="datetime-local" name="expires_at"></label>
            <label><span>Notas</span><textarea name="notes"></textarea></label>
            <div class="wide"><button class="button--primary" type="submit">Crear link</button></div>
        </form>
    </section>
    <section class="card">
        <div class="toolbar">
            <strong>Links existentes</strong>
            <div class="toolbar__actions">
                <?php if ($sellerFilter > 0): ?><a class="button" href="links.php">Ver todos</a><?php endif; ?>
                <span class="pill"><?= count($links) ?> links</span>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Catalogo</th><th>Vendedor / Cliente</th><th>Token</th><th>Aperturas</th><th>Pedidos</th><th>Ultimo acceso</th><th>Estado</th><th>URL</th><th>Acciones</th></tr></thead>
                <tbody>
                <?php foreach ($links as $link): ?>
                    <?php $shareUrl = !empty($link['public_url']) ? rtrim((string) $link['public_url'], '/') . '/?token=' . $link['token'] : ''; ?>
                    <tr>
                        <td><?= html_escape($link['catalog_title']) ?></td>
                        <td><?= html_escape($link['seller_name'] ?: 'Sin vendedor') ?> / <?= html_escape($link['client_name'] ?: 'Sin cliente') ?></td>
                        <td><code><?= html_escape(substr($link['token'], 0, 16)) ?>...</code></td>
                        <td><?= (int) $link['open_count'] ?></td>
                        <td><a href="pedidos.php?link_id=<?= (int) $link['id'] ?>"><?= (int) $link['orders_count'] ?></a></td>
                        <td><?= html_escape($link['last_opened_at']) ?></td>
                        <td><?= admin_status_badge(resolve_share_link_status($link)) ?></td>
                        <td><?php if ($shareUrl !== ''): ?><input class="link-url" type="text" value="<?= html_escape($shareUrl) ?>" readonly><a class="button" href="<?= html_escape($shareUrl) ?>" target="_blank">Abrir</a><?php endif; ?></td>
                        <td>
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="link_id" value="<?= (int) $link['id'] ?>">
                                <button type="submit"><?= (int) $link['is_active'] === 1 ? 'Desactivar' : 'Activar' ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<?php admin_footer(); ?>
