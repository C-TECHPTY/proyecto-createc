<?php
declare(strict_types=1);

require dirname(__DIR__) . '/_bootstrap.php';
require dirname(__DIR__, 2) . '/catalogos_api/campaign_helpers.php';

start_app_session();
$isAjax = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'fetch'
    || strpos((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false;

if (!current_user()) {
    if ($isAjax && $_SERVER['REQUEST_METHOD'] === 'POST') {
        http_response_code(401);
        campaign_admin_json(false, 'Sesion vencida. Inicia sesion nuevamente.', true, '../login.php');
    }
    header('Location: ../login.php');
    exit;
}
admin_require_login(['admin', 'sales']);

if (!campaign_module_enabled() || !campaign_schema_ready()) {
    if ($isAjax && $_SERVER['REQUEST_METHOD'] === 'POST') {
        http_response_code(503);
        campaign_admin_json(false, 'Modulo de campanas no activo o migracion SQL pendiente.', true);
    }
    admin_header('Campañas', 'campaigns/index.php');
    echo '<section class="card"><strong>Módulo no activo.</strong><p class="muted">Activa <code>campaigns_enabled = 1</code> y ejecuta <code>hosting/sql/20260425_campaigns_module.sql</code> para crear las tablas de campañas.</p></section>';
    admin_footer();
    exit;
}

$currentUser = current_user();

if ($isAjax && $_SERVER['REQUEST_METHOD'] === 'POST') {
    ob_start();
    register_shutdown_function(static function () use ($isAjax): void {
        $error = error_get_last();
        if (!$isAjax || !$error) {
            return;
        }
        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array((int) $error['type'], $fatalTypes, true)) {
            return;
        }
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode([
            'success' => false,
            'message' => 'Error interno procesando la campaña. Revisa que la migración SQL esté ejecutada y que los archivos del módulo estén actualizados.',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    });
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    start_app_session();
    $sentToken = (string) ($_POST['_csrf'] ?? '');
    $sessionToken = (string) ($_SESSION['_csrf'] ?? '');
    if ($sentToken === '' || $sessionToken === '' || !hash_equals($sessionToken, $sentToken)) {
        http_response_code(419);
        campaign_admin_json(false, 'Token de seguridad invalido. Recarga la pagina e intenta de nuevo.', $isAjax);
    }

    try {
        $action = (string) ($_POST['action'] ?? '');
        $campaignId = (int) ($_POST['campaign_id'] ?? 0);

        if ($action === 'create') {
        $title = trim((string) ($_POST['title'] ?? ''));
        $subject = trim((string) ($_POST['subject'] ?? ''));
        $message = trim((string) ($_POST['message'] ?? ''));
        $type = (string) ($_POST['type'] ?? 'promocion');
        $status = (string) ($_POST['status'] ?? 'draft');
        $catalogUrl = trim((string) ($_POST['catalog_url'] ?? ''));
        if ($title === '' || $subject === '' || !array_key_exists($type, campaign_type_options())) {
            flash_set('error', 'Título, asunto y tipo válido son obligatorios.');
            header('Location: index.php');
            exit;
        }
        $status = in_array($status, ['draft', 'approved'], true) ? $status : 'draft';
        db()->prepare(
            'INSERT INTO campaigns (title, subject, message, type, status, catalog_url, active, created_by)
             VALUES (:title, :subject, :message, :type, :status, :catalog_url, :active, :created_by)'
        )->execute([
            'title' => $title,
            'subject' => $subject,
            'message' => $message,
            'type' => $type,
            'status' => $status,
            'catalog_url' => $catalogUrl,
            'active' => isset($_POST['active']) ? 1 : 0,
            'created_by' => $currentUser['id'] ?? null,
        ]);
        $campaignId = (int) db()->lastInsertId();

        campaign_save_products_from_post($campaignId, $catalogUrl);
        campaign_save_recipients_from_post($campaignId);

        flash_set('success', 'Campaña creada como ' . campaign_status_label($status) . '.');
        header('Location: index.php?id=' . $campaignId);
        exit;
        }

        if ($action === 'update' && $campaignId > 0) {
        $campaign = campaign_fetch($campaignId);
        if (!$campaign || (string) $campaign['status'] === 'sent') {
            campaign_admin_json(false, 'La campaña enviada no se puede editar. Usa duplicar.', $isAjax, 'index.php?id=' . $campaignId);
        }
        $title = trim((string) ($_POST['title'] ?? ''));
        $subject = trim((string) ($_POST['subject'] ?? ''));
        $type = (string) ($_POST['type'] ?? 'promocion');
        if ($title === '' || $subject === '' || !array_key_exists($type, campaign_type_options())) {
            campaign_admin_json(false, 'Título, asunto y tipo válido son obligatorios.', $isAjax, 'index.php?id=' . $campaignId);
        }
        $catalogUrl = trim((string) ($_POST['catalog_url'] ?? ''));
        db()->prepare(
            'UPDATE campaigns
             SET title = :title, subject = :subject, message = :message, type = :type, catalog_url = :catalog_url, active = :active
             WHERE id = :id AND status IN ("draft","approved")'
        )->execute([
            'title' => $title,
            'subject' => $subject,
            'message' => trim((string) ($_POST['message'] ?? '')),
            'type' => $type,
            'catalog_url' => $catalogUrl,
            'active' => isset($_POST['active']) ? 1 : 0,
            'id' => $campaignId,
        ]);
        db()->prepare('DELETE FROM campaign_products WHERE campaign_id = :campaign_id')->execute(['campaign_id' => $campaignId]);
        db()->prepare('DELETE FROM campaign_recipients WHERE campaign_id = :campaign_id AND status = "pending"')->execute(['campaign_id' => $campaignId]);
        campaign_save_products_from_post($campaignId, $catalogUrl);
        campaign_save_recipients_from_post($campaignId);
        campaign_admin_json(true, 'Campaña guardada correctamente.', $isAjax, 'index.php?id=' . $campaignId);
        }

        if ($action === 'duplicate' && $campaignId > 0) {
        $source = campaign_fetch($campaignId);
        if (!$source) {
            campaign_admin_json(false, 'Campaña no encontrada.', $isAjax);
        }
        db()->prepare(
            'INSERT INTO campaigns (title, subject, message, type, status, catalog_url, active, created_by)
             VALUES (:title, :subject, :message, :type, "draft", :catalog_url, :active, :created_by)'
        )->execute([
            'title' => 'Copia de ' . (string) $source['title'],
            'subject' => (string) $source['subject'],
            'message' => (string) $source['message'],
            'type' => (string) $source['type'],
            'catalog_url' => (string) $source['catalog_url'],
            'active' => (int) ($source['active'] ?? 1),
            'created_by' => $currentUser['id'] ?? null,
        ]);
        $newId = (int) db()->lastInsertId();
        foreach (campaign_products($campaignId) as $product) {
            campaign_insert_product($newId, $product);
        }
        campaign_admin_json(true, 'Campaña duplicada correctamente.', $isAjax, 'index.php?id=' . $newId);
        }

        if ($action === 'delete' && $campaignId > 0) {
        if ((string) ($currentUser['role'] ?? '') !== 'admin') {
            campaign_admin_json(false, 'Solo un administrador puede eliminar campanas.', $isAjax, 'index.php?id=' . $campaignId);
        }
        if (empty($_POST['confirm_delete'])) {
            campaign_admin_json(false, 'Confirma la eliminacion antes de continuar.', $isAjax, 'index.php?id=' . $campaignId);
        }
        $campaign = campaign_fetch($campaignId);
        if (!$campaign) {
            campaign_admin_json(false, 'Campana no encontrada.', $isAjax, 'index.php');
        }
        if (campaign_has_promo_orders($campaignId)) {
            campaign_admin_json(false, 'No se puede eliminar porque ya tiene pedidos promocionales. Desactivala para conservar el historial.', $isAjax, 'index.php?id=' . $campaignId);
        }
        db()->beginTransaction();
        try {
            db()->prepare('DELETE FROM campaign_logs WHERE campaign_id = :campaign_id')->execute(['campaign_id' => $campaignId]);
            db()->prepare('DELETE FROM campaign_recipients WHERE campaign_id = :campaign_id')->execute(['campaign_id' => $campaignId]);
            db()->prepare('DELETE FROM campaign_products WHERE campaign_id = :campaign_id')->execute(['campaign_id' => $campaignId]);
            db()->prepare('DELETE FROM campaigns WHERE id = :id')->execute(['id' => $campaignId]);
            db()->commit();
        } catch (Throwable $exception) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            throw $exception;
        }
        campaign_admin_json(true, 'Campana eliminada correctamente.', $isAjax, 'index.php');
        }

        if ($action === 'approve' && $campaignId > 0) {
        try {
            $campaign = campaign_fetch($campaignId);
            if (!$campaign) {
                campaign_admin_json(false, 'Campaña no encontrada.', $isAjax);
            }
            if ((string) $campaign['status'] === 'sent') {
                campaign_admin_json(false, 'La campaña enviada no puede aprobarse otra vez.', $isAjax, 'index.php?id=' . $campaignId);
            }
            db()->prepare('UPDATE campaigns SET status = "approved" WHERE id = :id')->execute(['id' => $campaignId]);
            campaign_admin_json(true, 'Campaña aprobada correctamente', $isAjax, 'index.php?id=' . $campaignId);
        } catch (Throwable $exception) {
            campaign_admin_json(false, $exception->getMessage(), $isAjax, 'index.php?id=' . $campaignId);
        }
        }

        if ($action === 'test' && $campaignId > 0) {
        $email = trim((string) ($_POST['test_email'] ?? ''));
        $campaign = campaign_fetch($campaignId);
        if (!$campaign || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            campaign_admin_json(false, 'Indica un correo de prueba válido.', $isAjax, 'index.php?id=' . $campaignId);
        } else {
            $result = campaign_send_to_recipient_detail($campaign, campaign_products($campaignId), ['id' => 0, 'email' => $email]);
            campaign_admin_json($result['status'] === 'sent', $result['status'] === 'sent' ? 'Correo de prueba enviado.' : 'No se pudo enviar la prueba: ' . $result['message'], $isAjax, 'index.php?id=' . $campaignId);
        }
        }

        if ($action === 'send' && $campaignId > 0 && isset($_POST['confirm_send'])) {
        $result = campaign_send_pending_batch($campaignId, null, campaign_batch_limit());
        campaign_admin_json(true, 'Lote procesado. Enviados: ' . $result['sent'] . ' / Fallidos: ' . $result['failed'] . '.', $isAjax, 'index.php?id=' . $campaignId);
        }

        campaign_admin_json(false, 'Acción inválida.', $isAjax, $campaignId > 0 ? 'index.php?id=' . $campaignId : 'index.php');
    } catch (Throwable $exception) {
        campaign_admin_json(false, 'Error: ' . $exception->getMessage(), $isAjax, $campaignId > 0 ? 'index.php?id=' . $campaignId : 'index.php');
    }
}

$selectedId = (int) ($_GET['id'] ?? 0);
$selectedCampaign = $selectedId > 0 ? campaign_fetch($selectedId) : null;
$campaigns = db()->query(
    'SELECT c.*,
            (SELECT COUNT(*) FROM campaign_recipients r WHERE r.campaign_id = c.id) AS recipients_count,
            (SELECT COUNT(*) FROM campaign_recipients r WHERE r.campaign_id = c.id AND r.status = "sent") AS sent_count
     FROM campaigns c
     ORDER BY c.created_at DESC, c.id DESC
     LIMIT 80'
)->fetchAll();
$clients = catalog_table_exists('clients') ? db()->query('SELECT id, business_name, email, phone, seller_id FROM clients WHERE is_active = 1 ORDER BY business_name ASC LIMIT 300')->fetchAll() : [];
$sellers = catalog_table_exists('sellers') ? db()->query('SELECT id, name, email, phone FROM sellers WHERE is_active = 1 ORDER BY name ASC')->fetchAll() : [];
$suggestions = campaign_catalog_product_suggestions(40);
$selectedProducts = $selectedCampaign ? campaign_products((int) $selectedCampaign['id']) : [];
$selectedRecipients = $selectedCampaign ? campaign_recipient_email_map((int) $selectedCampaign['id']) : [];
$editingLocked = $selectedCampaign && (string) $selectedCampaign['status'] === 'sent';
$formCampaign = $selectedCampaign && !$editingLocked ? $selectedCampaign : null;

admin_header('Campañas', 'campaigns/index.php');
?>
<style>
    .campaigns-layout {
        align-items: start;
        grid-template-columns: minmax(0, 1.35fr) minmax(340px, 0.85fr);
    }
    .campaigns-side {
        align-self: start;
        display: grid;
        gap: 18px;
    }
    .campaigns-recent-card {
        max-height: 430px;
        display: flex;
        flex-direction: column;
    }
    .campaigns-recent-card .list {
        overflow: auto;
        padding-right: 4px;
    }
    .campaigns-recent-card .list-item {
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
    }
    .campaign-preview-card iframe {
        height: min(420px, 58vh) !important;
    }
    @media (min-width: 1100px) {
        .campaigns-side {
            position: sticky;
            top: 22px;
        }
    }
    @media (max-width: 900px) {
        .campaigns-layout {
            grid-template-columns: 1fr;
        }
        .campaigns-side {
            position: static;
        }
        .campaigns-recent-card {
            max-height: none;
        }
    }
</style>
<div class="split campaigns-layout">
    <section class="card">
        <div class="toolbar">
            <strong><?= $formCampaign ? 'Editar campaña' : 'Nueva campaña' ?></strong>
            <?php if ($editingLocked): ?><span class="pill">Enviada: solo duplicar</span><?php else: ?><span class="pill">Opcional</span><?php endif; ?>
        </div>
        <form class="grid" method="post" action="index.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $formCampaign ? 'update' : 'create' ?>">
            <input type="hidden" name="campaign_id" value="<?= (int) ($formCampaign['id'] ?? 0) ?>">
            <label><span>Título</span><input type="text" name="title" value="<?= html_escape($formCampaign['title'] ?? '') ?>" required <?= $editingLocked ? 'disabled' : '' ?>></label>
            <label><span>Asunto del correo</span><input type="text" name="subject" value="<?= html_escape($formCampaign['subject'] ?? '') ?>" required <?= $editingLocked ? 'disabled' : '' ?>></label>
            <label><span>Tipo</span><select name="type" <?= $editingLocked ? 'disabled' : '' ?>><?php foreach (campaign_type_options() as $value => $label): ?><option value="<?= html_escape($value) ?>" <?= $value === ($formCampaign['type'] ?? 'promocion') ? 'selected' : '' ?>><?= html_escape($label) ?></option><?php endforeach; ?></select></label>
            <?php if (!$formCampaign): ?><label><span>Estado inicial</span><select name="status"><option value="draft">Borrador</option><option value="approved">Aprobada</option></select></label><?php endif; ?>
            <label class="checkbox-line"><input type="checkbox" name="active" <?= (int) ($formCampaign['active'] ?? 1) === 1 ? 'checked' : '' ?> <?= $editingLocked ? 'disabled' : '' ?>><span>Activa</span></label>
            <label class="wide"><span>URL catálogo completo</span><input type="url" name="catalog_url" value="<?= html_escape($formCampaign['catalog_url'] ?? '') ?>" placeholder="https://rodeoimportzl.com/..." <?= $editingLocked ? 'disabled' : '' ?>></label>
            <label class="wide"><span>Mensaje corto</span><textarea name="message" rows="4" <?= $editingLocked ? 'disabled' : '' ?>><?= html_escape($formCampaign['message'] ?? '') ?></textarea></label>

            <?php if (!$formCampaign): ?>
            <div class="wide"><strong>Productos sugeridos desde catálogos</strong></div>
            <div class="wide list" style="max-height:260px;overflow:auto;">
                <?php foreach ($suggestions as $product): ?>
                    <?php $encoded = base64_encode(json_encode($product, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>
                    <label class="checkbox-line"><input type="checkbox" name="suggested_products[]" value="<?= html_escape($encoded) ?>"><span><?= html_escape($product['item']) ?> · <?= html_escape($product['description']) ?> · <?= html_escape($product['catalog_title']) ?></span></label>
                <?php endforeach; ?>
                <?php if (!$suggestions): ?><p class="muted">No hay productos publicados para sugerir. Puedes escribir uno manualmente.</p><?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="wide"><strong>Productos de campaña</strong></div>
            <?php $productRows = $formCampaign ? $selectedProducts : []; $productRows[] = []; ?>
            <?php foreach ($productRows as $index => $product): ?>
                <fieldset class="wide" style="border:1px solid #d9deea;border-radius:8px;padding:12px;">
                    <legend>Producto <?= $index + 1 ?></legend>
                    <div class="form-grid">
                        <label><span>Item</span><input type="text" name="products[<?= $index ?>][item]" value="<?= html_escape($product['item'] ?? '') ?>" <?= $editingLocked ? 'disabled' : '' ?>></label>
                        <label><span>Precio normal</span><input type="number" step="0.01" min="0" class="js-regular-price" name="products[<?= $index ?>][regular_price]" value="<?= html_escape((string) ($product['regular_price'] ?? '')) ?>" <?= $editingLocked ? 'disabled' : '' ?>></label>
                        <label class="wide"><span>Descripción</span><input type="text" name="products[<?= $index ?>][description]" value="<?= html_escape($product['description'] ?? '') ?>" <?= $editingLocked ? 'disabled' : '' ?>></label>
                        <label><span>Tipo descuento</span><select class="js-discount-type" name="products[<?= $index ?>][discount_type]" <?= $editingLocked ? 'disabled' : '' ?>><?php foreach (campaign_discount_options() as $value => $label): ?><option value="<?= html_escape($value) ?>" <?= $value === ($product['discount_type'] ?? 'none') ? 'selected' : '' ?>><?= html_escape($label) ?></option><?php endforeach; ?></select></label>
                        <label><span>Valor descuento</span><input type="number" step="0.01" min="0" class="js-discount-value" name="products[<?= $index ?>][discount_value]" value="<?= html_escape((string) ($product['discount_value'] ?? '0')) ?>" <?= $editingLocked ? 'disabled' : '' ?>></label>
                        <label><span>Precio promo calculado</span><input type="text" class="js-promo-preview" value="<?= html_escape(campaign_money((float) ($product['promo_price'] ?? $product['regular_price'] ?? 0))) ?>" readonly></label>
                        <label><span>Inicio</span><input type="datetime-local" name="products[<?= $index ?>][promo_start]" value="<?= html_escape(campaign_datetime_input($product['promo_start'] ?? '')) ?>" <?= $editingLocked ? 'disabled' : '' ?>></label>
                        <label><span>Fin</span><input type="datetime-local" name="products[<?= $index ?>][promo_end]" value="<?= html_escape(campaign_datetime_input($product['promo_end'] ?? '')) ?>" <?= $editingLocked ? 'disabled' : '' ?>></label>
                        <label class="wide"><span>Imagen HTTPS</span><input type="url" name="products[<?= $index ?>][image_url]" value="<?= html_escape($product['image_url'] ?? '') ?>" <?= $editingLocked ? 'disabled' : '' ?>></label>
                        <label class="wide"><span>URL producto/catálogo</span><input type="url" name="products[<?= $index ?>][catalog_url]" value="<?= html_escape($product['catalog_url'] ?? '') ?>" <?= $editingLocked ? 'disabled' : '' ?>></label>
                        <label class="checkbox-line"><input type="checkbox" name="products[<?= $index ?>][active]" <?= (int) ($product['active'] ?? 1) === 1 ? 'checked' : '' ?> <?= $editingLocked ? 'disabled' : '' ?>><span>Activo</span></label>
                    </div>
                </fieldset>
            <?php endforeach; ?>

            <div class="wide"><strong>Destinatarios</strong></div>
            <label class="wide"><span>Clientes</span><select name="client_ids[]" multiple size="7" <?= $editingLocked ? 'disabled' : '' ?>><?php foreach ($clients as $client): ?><option value="<?= (int) $client['id'] ?>" <?= isset($selectedRecipients[(string) $client['email']]) ? 'selected' : '' ?>><?= html_escape($client['business_name']) ?> · <?= html_escape($client['email']) ?></option><?php endforeach; ?></select></label>
            <label class="wide"><span>Vendedores</span><select name="seller_ids[]" multiple size="5" <?= $editingLocked ? 'disabled' : '' ?>><?php foreach ($sellers as $seller): ?><option value="<?= (int) $seller['id'] ?>" <?= isset($selectedRecipients[(string) $seller['email']]) ? 'selected' : '' ?>><?= html_escape($seller['name']) ?> · <?= html_escape($seller['email']) ?></option><?php endforeach; ?></select></label>
            <?php if (!$editingLocked): ?><button class="button--primary" type="submit"><?= $formCampaign ? 'Guardar cambios' : 'Crear campaña' ?></button><?php endif; ?>
        </form>
        <?php if ($selectedCampaign): ?>
            <form class="js-campaign-action" method="post" action="index.php" style="margin-top:12px;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="duplicate">
                <input type="hidden" name="campaign_id" value="<?= (int) $selectedCampaign['id'] ?>">
                <button type="submit">Duplicar campaña</button>
            </form>
            <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
                <form class="js-campaign-action" method="post" action="index.php" style="margin-top:12px;border-top:1px solid #d9deea;padding-top:12px;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="campaign_id" value="<?= (int) $selectedCampaign['id'] ?>">
                    <label class="checkbox-line"><input type="checkbox" name="confirm_delete" required><span>Confirmo eliminar esta campana</span></label>
                    <button type="submit" style="background:#dc2626;color:#fff;">Eliminar campana</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <section class="campaigns-side">
        <div class="card campaigns-recent-card">
            <div class="toolbar"><strong>Campañas recientes</strong><span class="pill"><?= count($campaigns) ?></span></div>
            <div class="list">
                <?php foreach ($campaigns as $campaign): ?>
                    <div class="list-item">
                        <div class="toolbar"><strong><?= html_escape($campaign['title']) ?></strong><?= admin_status_badge((string) $campaign['status']) ?></div>
                        <div class="muted"><?= html_escape($campaign['subject']) ?></div>
                        <div class="metrics-inline"><span class="pill"><?= (int) $campaign['recipients_count'] ?> destinatarios</span><span class="pill"><?= (int) $campaign['sent_count'] ?> enviados</span></div>
                        <a class="button" href="index.php?id=<?= (int) $campaign['id'] ?>"><?= $campaign['status'] === 'sent' ? 'Ver' : 'Editar' ?></a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if ($selectedCampaign): ?>
            <?php $products = campaign_products((int) $selectedCampaign['id']); ?>
            <?php $results = campaign_results((int) $selectedCampaign['id']); ?>
            <div class="card campaign-preview-card">
                <div class="toolbar"><strong>Vista previa</strong><?= admin_status_badge((string) $selectedCampaign['status']) ?></div>
                <div class="muted"><?= html_escape($selectedCampaign['subject']) ?></div>
                <iframe title="Vista previa campaña" style="width:100%;height:420px;border:1px solid #d9deea;border-radius:8px;background:#fff;" srcdoc="<?= html_escape(campaign_html_body($selectedCampaign, $products)) ?>"></iframe>
                <div class="toolbar" style="margin-top:16px;"><strong>Resultados reales</strong></div>
                <div class="metrics-inline">
                    <span class="pill">Pedidos promo: <?= (int) $results['orders_count'] ?></span>
                    <span class="pill">Total vendido: <?= html_escape(campaign_money((float) $results['sales_total'])) ?></span>
                    <span class="pill">Correos enviados: <?= (int) $results['email_sent'] ?></span>
                    <span class="pill">Correos fallidos: <?= (int) $results['email_failed'] ?></span>
                    <span class="pill">Pendientes: <?= campaign_pending_count((int) $selectedCampaign['id']) ?></span>
                </div>
                <form class="form-grid js-campaign-action" method="post" action="index.php" style="margin-top:12px;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="campaign_id" value="<?= (int) $selectedCampaign['id'] ?>">
                    <label><span>Correo de prueba</span><input type="email" name="test_email" placeholder="correo@empresa.com"></label>
                    <button name="action" value="test" type="submit">Enviar prueba</button>
                    <?php if ($selectedCampaign['status'] === 'draft'): ?><button class="js-approve-button" name="action" value="approve" type="submit">Aprobar</button><?php endif; ?>
                    <?php if ($selectedCampaign['status'] !== 'draft'): ?>
                        <label class="checkbox-line"><input type="checkbox" name="confirm_send" required><span>Confirmo enviar lote de <?= campaign_batch_limit() ?> correos máximo</span></label>
                        <button class="button--primary" name="action" value="send" type="submit">Enviar lote</button>
                    <?php endif; ?>
                </form>
                <div class="toolbar" style="margin-top:16px;"><strong>Historial</strong><span class="pill">Últimos envíos</span></div>
                <div class="list">
                    <?php foreach (campaign_recent_logs((int) $selectedCampaign['id']) as $log): ?>
                        <div class="list-item"><strong><?= html_escape($log['email']) ?></strong><div class="muted"><?= html_escape($log['status']) ?> · <?= html_escape($log['error_message']) ?> · <?= html_escape($log['created_at']) ?></div></div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </section>
</div>
<script>
(() => {
  const flash = document.createElement("div");
  flash.className = "flash";
  flash.hidden = true;
  document.querySelector(".main")?.prepend(flash);

  function showMessage(ok, message) {
    flash.hidden = false;
    flash.className = `flash ${ok ? "flash--success" : "flash--error"}`;
    flash.textContent = message || (ok ? "Acción completada." : "No se pudo completar.");
  }

  document.querySelectorAll(".js-campaign-action").forEach((form) => {
    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      const button = form.querySelector("button[type='submit'],button:not([type])");
      const original = button ? button.textContent : "";
      if (button) {
        button.disabled = true;
        button.textContent = "Procesando...";
      }
      try {
        const formData = new FormData(form);
        if (event.submitter && event.submitter.name) {
          formData.set(event.submitter.name, event.submitter.value);
        }
        if (formData.get("action") === "delete" && !window.confirm("Esta accion eliminara la campana seleccionada. Deseas continuar?")) {
          return;
        }
        const response = await fetch(form.getAttribute("action") || "index.php", {
          method: "POST",
          headers: { "X-Requested-With": "fetch", "Accept": "application/json" },
          body: formData
        });
        const responseText = await response.text();
        response.json = async () => {
          try {
            return JSON.parse(responseText);
          } catch (parseError) {
            const preview = responseText.replace(/<[^>]*>/g, " ").replace(/\s+/g, " ").trim().slice(0, 260);
            return {
              success: false,
              message: preview
                ? `Respuesta invalida del servidor (${response.status}). ${preview}`
                : `Respuesta invalida del servidor (${response.status}). Revisa error_log del hosting.`
            };
          }
        };
        const result = await response.json().catch(() => ({ success:false, message:"Respuesta inválida del servidor." }));
        showMessage(Boolean(result.success), result.message);
        if (result.success && result.redirect) window.location.href = result.redirect;
      } catch (error) {
        showMessage(false, error.message || "Error de red.");
      } finally {
        if (button) {
          button.disabled = false;
          button.textContent = original;
        }
      }
    });
  });

  document.querySelectorAll(".js-regular-price,.js-discount-type,.js-discount-value").forEach((input) => {
    input.addEventListener("input", () => {
      const box = input.closest("fieldset");
      if (!box) return;
      const regular = Number(box.querySelector(".js-regular-price")?.value || 0);
      const type = box.querySelector(".js-discount-type")?.value || "none";
      const value = Number(box.querySelector(".js-discount-value")?.value || 0);
      let promo = regular;
      if (type === "percent") promo = Math.max(0, regular - (regular * value / 100));
      if (type === "fixed_price") promo = value;
      const preview = box.querySelector(".js-promo-preview");
      if (preview) preview.value = `USD ${promo.toFixed(2)}`;
    });
  });
})();
</script>
<?php admin_footer();

function campaign_insert_product(int $campaignId, array $product): void
{
    $product = campaign_normalize_product($product);
    $item = $product['item'];
    $description = $product['description'];
    if ($item === '' && $description === '') {
        return;
    }
    db()->prepare(
        'INSERT INTO campaign_products (campaign_id, item, description, price, image_url, catalog_url, regular_price, discount_type, discount_value, promo_price, promo_start, promo_end, active)
         VALUES (:campaign_id, :item, :description, :price, :image_url, :catalog_url, :regular_price, :discount_type, :discount_value, :promo_price, :promo_start, :promo_end, :active)'
    )->execute([
        'campaign_id' => $campaignId,
        'item' => $product['item'],
        'description' => $product['description'],
        'price' => $product['price'],
        'image_url' => $product['image_url'],
        'catalog_url' => $product['catalog_url'],
        'regular_price' => $product['regular_price'],
        'discount_type' => $product['discount_type'],
        'discount_value' => $product['discount_value'],
        'promo_price' => $product['promo_price'],
        'promo_start' => $product['promo_start'],
        'promo_end' => $product['promo_end'],
        'active' => $product['active'],
    ]);
}

function campaign_save_products_from_post(int $campaignId, string $catalogUrl): void
{
    $selectedProducts = $_POST['suggested_products'] ?? [];
    if (is_array($selectedProducts)) {
        foreach ($selectedProducts as $encoded) {
            $product = json_decode(base64_decode((string) $encoded), true);
            if (!is_array($product)) {
                continue;
            }
            $product['regular_price'] = $product['regular_price'] ?? $product['price'] ?? 0;
            $product['active'] = 1;
            campaign_insert_product($campaignId, $product);
        }
    }

    foreach ((array) ($_POST['products'] ?? []) as $product) {
        if (!is_array($product)) {
            continue;
        }
        if (empty($product['catalog_url'])) {
            $product['catalog_url'] = $catalogUrl;
        }
        campaign_insert_product($campaignId, $product);
    }
}

function campaign_save_recipients_from_post(int $campaignId): void
{
    foreach ((array) ($_POST['client_ids'] ?? []) as $clientId) {
        $client = campaign_fetch_client((int) $clientId);
        if ($client) {
            campaign_add_recipient($campaignId, 'client', (string) $client['business_name'], (string) $client['email'], (string) $client['phone'], !empty($client['seller_id']) ? (int) $client['seller_id'] : null);
        }
    }
    foreach ((array) ($_POST['seller_ids'] ?? []) as $sellerId) {
        $seller = campaign_fetch_seller((int) $sellerId);
        if ($seller) {
            campaign_add_recipient($campaignId, 'seller', (string) $seller['name'], (string) $seller['email'], (string) $seller['phone'], (int) $seller['id']);
        }
    }
}

function campaign_recipient_email_map(int $campaignId): array
{
    $statement = db()->prepare('SELECT email FROM campaign_recipients WHERE campaign_id = :campaign_id');
    $statement->execute(['campaign_id' => $campaignId]);
    $map = [];
    foreach ($statement->fetchAll() as $row) {
        $map[(string) $row['email']] = true;
    }
    return $map;
}

function campaign_datetime_input(mixed $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }
    $timestamp = strtotime($value);
    return $timestamp ? date('Y-m-d\TH:i', $timestamp) : '';
}

function campaign_results(int $campaignId): array
{
    if (!campaign_promo_schema_ready()) {
        $logs = db()->prepare(
            'SELECT
                SUM(CASE WHEN status = "sent" THEN 1 ELSE 0 END) AS email_sent,
                SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) AS email_failed
             FROM campaign_logs
             WHERE campaign_id = :campaign_id'
        );
        $logs->execute(['campaign_id' => $campaignId]);
        $logStats = $logs->fetch() ?: ['email_sent' => 0, 'email_failed' => 0];
        return [
            'orders_count' => 0,
            'sales_total' => 0,
            'email_sent' => (int) ($logStats['email_sent'] ?? 0),
            'email_failed' => (int) ($logStats['email_failed'] ?? 0),
        ];
    }

    $orders = db()->prepare('SELECT COUNT(*) AS orders_count, COALESCE(SUM(total_promo), 0) AS sales_total FROM campaign_promo_orders WHERE campaign_id = :campaign_id');
    $orders->execute(['campaign_id' => $campaignId]);
    $orderStats = $orders->fetch() ?: ['orders_count' => 0, 'sales_total' => 0];
    $logs = db()->prepare(
        'SELECT
            SUM(CASE WHEN status = "sent" THEN 1 ELSE 0 END) AS email_sent,
            SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) AS email_failed
         FROM campaign_logs
         WHERE campaign_id = :campaign_id'
    );
    $logs->execute(['campaign_id' => $campaignId]);
    $logStats = $logs->fetch() ?: ['email_sent' => 0, 'email_failed' => 0];
    return [
        'orders_count' => (int) ($orderStats['orders_count'] ?? 0),
        'sales_total' => (float) ($orderStats['sales_total'] ?? 0),
        'email_sent' => (int) ($logStats['email_sent'] ?? 0),
        'email_failed' => (int) ($logStats['email_failed'] ?? 0),
    ];
}

function campaign_has_promo_orders(int $campaignId): bool
{
    if (!campaign_promo_schema_ready()) {
        return false;
    }
    $statement = db()->prepare('SELECT COUNT(*) FROM campaign_promo_orders WHERE campaign_id = :campaign_id');
    $statement->execute(['campaign_id' => $campaignId]);
    return ((int) $statement->fetchColumn()) > 0;
}

function campaign_admin_json(bool $success, string $message, bool $asJson, string $redirect = ''): void
{
    if ($asJson) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => $success,
            'message' => $message,
            'redirect' => $redirect,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    flash_set($success ? 'success' : 'error', $message);
    header('Location: ' . ($redirect !== '' ? $redirect : 'index.php'));
    exit;
}

function campaign_fetch_client(int $clientId): ?array
{
    $statement = db()->prepare('SELECT * FROM clients WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $clientId]);
    $row = $statement->fetch();
    return $row ?: null;
}

function campaign_fetch_seller(int $sellerId): ?array
{
    $statement = db()->prepare('SELECT * FROM sellers WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $sellerId]);
    $row = $statement->fetch();
    return $row ?: null;
}
