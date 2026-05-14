<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
admin_require_login(['admin', 'sales']);

$hasSellerPhoto = admin_column_exists('sellers', 'photo_path');
$hasSellerToken = admin_column_exists('sellers', 'public_token');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_abort();
    $action = (string) ($_POST['action'] ?? 'create');
    $sellerId = (int) ($_POST['seller_id'] ?? 0);

    if ($action === 'create' || ($action === 'update' && $sellerId > 0)) {
        $data = [
            'code' => trim((string) $_POST['code']),
            'name' => trim((string) $_POST['name']),
            'email' => filter_var(trim((string) $_POST['email']), FILTER_VALIDATE_EMAIL) ? trim((string) $_POST['email']) : '',
            'phone' => trim((string) $_POST['phone']),
            'territory' => trim((string) $_POST['territory']),
            'notes' => trim((string) $_POST['notes']),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];

        if ($data['code'] === '' || $data['name'] === '') {
            flash_set('error', 'Codigo y nombre del vendedor son obligatorios.');
            header('Location: sellers.php' . ($sellerId ? '?edit=' . $sellerId : ''));
            exit;
        }

        try {
            $photoPath = null;
            if ($hasSellerPhoto) {
                $removePhoto = isset($_POST['remove_photo']);
                $prefix = $data['code'] !== '' ? $data['code'] : $data['name'];
                $photoPath = save_uploaded_image('photo_file', 'uploads/sellers', 'seller-' . $prefix);

                if ($action === 'update' && $sellerId > 0) {
                    $existingPhotoStmt = db()->prepare('SELECT photo_path FROM sellers WHERE id = :id LIMIT 1');
                    $existingPhotoStmt->execute(['id' => $sellerId]);
                    $existingPhoto = (string) $existingPhotoStmt->fetchColumn();
                    if ($removePhoto) {
                        delete_panel_file($existingPhoto);
                        $data['photo_path'] = '';
                    } elseif ($photoPath !== null) {
                        delete_panel_file($existingPhoto);
                        $data['photo_path'] = $photoPath;
                    }
                } elseif ($photoPath !== null) {
                    $data['photo_path'] = $photoPath;
                }
            }
        } catch (Throwable $exception) {
            flash_set('error', $exception->getMessage());
            header('Location: sellers.php' . ($sellerId ? '?edit=' . $sellerId : ''));
            exit;
        }

        if ($action === 'update') {
            $data['id'] = $sellerId;
            $sql = 'UPDATE sellers
                    SET code = :code, name = :name, email = :email, phone = :phone,
                        territory = :territory, notes = :notes, is_active = :is_active';
            if ($hasSellerPhoto && array_key_exists('photo_path', $data)) {
                $sql .= ', photo_path = :photo_path';
            }
            $sql .= ', updated_at = NOW() WHERE id = :id';
            db()->prepare($sql)->execute($data);
            audit_log('seller.updated', 'sellers', $sellerId);
            flash_set('success', 'Vendedor actualizado.');
        } else {
            if ($hasSellerPhoto) {
                $insertColumns = 'code, name, email, phone, territory, notes, is_active, photo_path';
                $insertValues = ':code, :name, :email, :phone, :territory, :notes, :is_active, :photo_path';
                $data['photo_path'] = (string) ($data['photo_path'] ?? '');
            } else {
                $insertColumns = 'code, name, email, phone, territory, notes, is_active';
                $insertValues = ':code, :name, :email, :phone, :territory, :notes, :is_active';
            }
            if ($hasSellerToken) {
                $insertColumns .= ', public_token';
                $insertValues .= ', :public_token';
                $data['public_token'] = generate_secure_token();
            }
            db()->prepare(
                "INSERT INTO sellers ({$insertColumns})
                 VALUES ({$insertValues})"
            )->execute($data);
            $newSellerId = (int) db()->lastInsertId();
            audit_log('seller.created', 'sellers', $newSellerId);
            flash_set('success', 'Vendedor creado.');
        }
    }

    if ($action === 'toggle' && $sellerId > 0) {
        db()->prepare(
            'UPDATE sellers
             SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END, updated_at = NOW()
             WHERE id = :id'
        )->execute(['id' => $sellerId]);
        audit_log('seller.toggled', 'sellers', $sellerId);
        flash_set('success', 'Estado del vendedor actualizado.');
    }

    if ($action === 'delete' && $sellerId > 0) {
        $countStmt = db()->prepare(
            'SELECT
                (SELECT COUNT(*) FROM catalogs WHERE seller_id = :id) +
                (SELECT COUNT(*) FROM catalog_share_links WHERE seller_id = :id) +
                (SELECT COUNT(*) FROM orders WHERE seller_id = :id) +
                (SELECT COUNT(*) FROM clients WHERE seller_id = :id) +
                (SELECT COUNT(*) FROM catalog_users WHERE seller_id = :id) AS total_relations'
        );
        $countStmt->execute(['id' => $sellerId]);
        $relations = (int) $countStmt->fetchColumn();
        if ($relations > 0) {
            db()->prepare('UPDATE sellers SET is_active = 0, updated_at = NOW() WHERE id = :id')->execute(['id' => $sellerId]);
            flash_set('success', 'El vendedor tiene historial comercial; se desactivo para conservar trazabilidad.');
        } else {
            db()->prepare('DELETE FROM sellers WHERE id = :id')->execute(['id' => $sellerId]);
            flash_set('success', 'Vendedor eliminado.');
        }
        audit_log('seller.deleted_or_deactivated', 'sellers', $sellerId);
    }

    header('Location: sellers.php');
    exit;
}

$sellers = db()->query(
    'SELECT s.*,
            (SELECT COUNT(*) FROM catalogs c WHERE c.seller_id = s.id) AS catalogs_count,
            (SELECT COUNT(*) FROM catalog_share_links l WHERE l.seller_id = s.id) AS links_count,
            (SELECT COUNT(*) FROM orders o WHERE o.seller_id = s.id) AS orders_count
     FROM sellers s
     ORDER BY s.name ASC'
)->fetchAll();

$editId = (int) ($_GET['edit'] ?? 0);
$editSeller = null;
foreach ($sellers as $seller) {
    if ((int) $seller['id'] === $editId) {
        $editSeller = $seller;
        break;
    }
}
$sampleCatalog = null;
if (admin_table_exists('catalogs') && admin_column_exists('catalogs', 'public_url')) {
    $sampleWhere = admin_column_exists('catalogs', 'status') ? "WHERE status = 'active' AND public_url <> ''" : "WHERE public_url <> ''";
    $sampleOrder = admin_column_exists('catalogs', 'updated_at') ? 'updated_at DESC' : 'id DESC';
    $sampleCatalog = db()->query("SELECT title, public_url FROM catalogs {$sampleWhere} ORDER BY {$sampleOrder} LIMIT 1")->fetch();
}

admin_header('Vendedores', 'sellers.php');
?>
<div class="split">
    <section class="card">
        <div class="toolbar">
            <strong><?= $editSeller ? 'Editar vendedor' : 'Nuevo vendedor' ?></strong>
            <?php if ($editSeller): ?><a class="button" href="sellers.php">Nuevo</a><?php endif; ?>
        </div>
        <form class="form-grid" method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $editSeller ? 'update' : 'create' ?>">
            <input type="hidden" name="seller_id" value="<?= (int) ($editSeller['id'] ?? 0) ?>">
            <label><span>Codigo</span><input type="text" name="code" value="<?= html_escape($editSeller['code'] ?? '') ?>" required></label>
            <label><span>Nombre</span><input type="text" name="name" value="<?= html_escape($editSeller['name'] ?? '') ?>" required></label>
            <label><span>Correo</span><input type="email" name="email" value="<?= html_escape($editSeller['email'] ?? '') ?>"></label>
            <label><span>Telefono</span><input type="text" name="phone" value="<?= html_escape($editSeller['phone'] ?? '') ?>"></label>
            <label><span>Territorio</span><input type="text" name="territory" value="<?= html_escape($editSeller['territory'] ?? '') ?>"></label>
            <label class="checkbox-line"><input type="checkbox" name="is_active" <?= !$editSeller || (int) $editSeller['is_active'] === 1 ? 'checked' : '' ?>><span>Activo</span></label>
            <?php if ($hasSellerPhoto): ?>
                <label class="wide">
                    <span>Foto del vendedor</span>
                    <input type="file" name="photo_file" accept="image/*">
                </label>
                <?php if (!empty($editSeller['photo_path'])): ?>
                    <div class="wide seller-photo-editor">
                        <img class="seller-avatar seller-avatar--large" src="<?= html_escape(panel_media_url((string) $editSeller['photo_path'])) ?>" alt="<?= html_escape($editSeller['name'] ?? 'Vendedor') ?>">
                        <label class="checkbox-line"><input type="checkbox" name="remove_photo"><span>Quitar foto actual</span></label>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            <label class="wide"><span>Notas</span><textarea name="notes"><?= html_escape($editSeller['notes'] ?? '') ?></textarea></label>
            <div class="wide"><button class="button--primary" type="submit"><?= $editSeller ? 'Guardar vendedor' : 'Crear vendedor' ?></button></div>
        </form>
    </section>
    <section class="card">
        <div class="toolbar"><strong>Base comercial</strong><span class="pill"><?= count($sellers) ?> vendedores</span></div>
        <div class="list">
            <?php foreach ($sellers as $seller): ?>
                <div class="list-item">
                    <div class="toolbar">
                        <div class="seller-card">
                            <?php if ($hasSellerPhoto && !empty($seller['photo_path'])): ?>
                                <img class="seller-avatar" src="<?= html_escape(panel_media_url((string) $seller['photo_path'])) ?>" alt="<?= html_escape($seller['name']) ?>">
                            <?php else: ?>
                                <div class="seller-avatar seller-avatar--fallback"><?= html_escape(strtoupper(substr((string) $seller['name'], 0, 1))) ?></div>
                            <?php endif; ?>
                            <div>
                            <strong><?= html_escape($seller['name']) ?></strong>
                            <div class="muted"><?= html_escape($seller['code']) ?> - <?= html_escape($seller['territory']) ?></div>
                            </div>
                        </div>
                        <?= admin_status_badge((int) $seller['is_active'] === 1 ? 'active' : 'inactive') ?>
                    </div>
                    <div class="metrics-inline">
                        <span class="pill"><?= (int) $seller['catalogs_count'] ?> catalogos</span>
                        <span class="pill"><?= (int) $seller['links_count'] ?> links</span>
                        <span class="pill"><?= (int) $seller['orders_count'] ?> pedidos</span>
                    </div>
                    <?php if ($hasSellerToken): ?>
                        <?php $sellerPublicUrl = $sampleCatalog && !empty($sampleCatalog['public_url']) && !empty($seller['public_token']) ? rtrim((string) $sampleCatalog['public_url'], '/') . '/?t=' . $seller['public_token'] : ''; ?>
                        <div class="grid" style="gap:8px;margin:12px 0;">
                            <div class="muted">Token vendedor: <code><?= html_escape(substr((string) ($seller['public_token'] ?? ''), 0, 16)) ?>...</code></div>
                            <?php if ($sellerPublicUrl !== ''): ?>
                                <input class="link-url" type="text" value="<?= html_escape($sellerPublicUrl) ?>" readonly>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <div class="toolbar__actions seller-actions">
                        <a class="button" href="sellers.php?edit=<?= (int) $seller['id'] ?>">Editar</a>
                        <a class="button" href="links.php?seller_id=<?= (int) $seller['id'] ?>">Ver links</a>
                        <a class="button" href="pedidos.php?seller_id=<?= (int) $seller['id'] ?>">Ver pedidos</a>
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="seller_id" value="<?= (int) $seller['id'] ?>">
                            <button type="submit"><?= (int) $seller['is_active'] === 1 ? 'Desactivar' : 'Activar' ?></button>
                        </form>
                        <form method="post" onsubmit="return confirm('Eliminar o desactivar este vendedor?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="seller_id" value="<?= (int) $seller['id'] ?>">
                            <button class="button--danger" type="submit">Eliminar</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
</div>
<?php admin_footer(); ?>
