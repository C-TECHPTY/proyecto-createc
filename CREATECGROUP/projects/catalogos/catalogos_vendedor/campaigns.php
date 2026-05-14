<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
require dirname(__DIR__) . '/catalogos_api/campaign_helpers.php';
vendor_require_panel_login();

$user = vendor_current_user();
$sellerId = (int) ($user['seller_id'] ?? 0);

if (!campaign_module_enabled() || !campaign_schema_ready()) {
    vendor_header('Campañas', 'campaigns.php');
    echo '<section class="card"><strong>Módulo no activo.</strong><p class="muted">El administrador debe ejecutar la migración de campañas.</p></section>';
    vendor_footer();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_abort();
    $campaignId = (int) ($_POST['campaign_id'] ?? 0);
    $campaign = campaign_fetch($campaignId);
    if (!$campaign || $campaign['status'] === 'draft') {
        flash_set('error', 'Campaña no disponible.');
        header('Location: campaigns.php');
        exit;
    }
    if ($sellerId <= 0) {
        flash_set('error', 'Tu usuario no tiene vendedor asignado.');
        header('Location: campaigns.php');
        exit;
    }
    $clients = db()->prepare('SELECT * FROM clients WHERE seller_id = :seller_id AND is_active = 1 AND email <> ""');
    $clients->execute(['seller_id' => $sellerId]);
    foreach ($clients->fetchAll() as $client) {
        campaign_add_recipient($campaignId, 'client', (string) $client['business_name'], (string) $client['email'], (string) $client['phone'], $sellerId);
    }
    $result = campaign_send_pending_batch($campaignId, $sellerId, campaign_batch_limit());
    flash_set('success', 'Lote enviado a tus clientes. Enviados: ' . $result['sent'] . ' / Fallidos: ' . $result['failed'] . '.');
    header('Location: campaigns.php?id=' . $campaignId);
    exit;
}

$selectedId = (int) ($_GET['id'] ?? 0);
$selectedCampaign = $selectedId > 0 ? campaign_fetch($selectedId) : null;
$campaigns = db()->query('SELECT * FROM campaigns WHERE status IN ("approved","sent") ORDER BY created_at DESC, id DESC LIMIT 60')->fetchAll();

vendor_header('Campañas', 'campaigns.php');
?>
<div class="split">
    <section class="card">
        <div class="toolbar"><strong>Campañas aprobadas</strong><span class="pill"><?= count($campaigns) ?></span></div>
        <div class="list">
            <?php foreach ($campaigns as $campaign): ?>
                <div class="list-item">
                    <div class="toolbar"><strong><?= html_escape($campaign['title']) ?></strong><?= admin_status_badge((string) $campaign['status']) ?></div>
                    <div class="muted"><?= html_escape($campaign['subject']) ?></div>
                    <a class="button" href="campaigns.php?id=<?= (int) $campaign['id'] ?>">Ver campaña</a>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <section class="card">
        <?php if ($selectedCampaign && $selectedCampaign['status'] !== 'draft'): ?>
            <?php $products = campaign_products((int) $selectedCampaign['id']); ?>
            <div class="toolbar"><strong>Vista previa</strong></div>
            <iframe title="Vista previa campaña" style="width:100%;height:460px;border:1px solid #d9deea;border-radius:8px;background:#fff;" srcdoc="<?= html_escape(campaign_html_body($selectedCampaign, $products)) ?>"></iframe>
            <form method="post" onsubmit="return confirm('Enviar esta campaña solo a tus clientes asignados?');" style="margin-top:14px;">
                <?= csrf_field() ?>
                <input type="hidden" name="campaign_id" value="<?= (int) $selectedCampaign['id'] ?>">
                <button class="button--primary" type="submit">Enviar a mis clientes</button>
            </form>
            <p class="muted">Solo se usarán clientes activos asignados a tu vendedor y con correo válido.</p>
        <?php else: ?>
            <p class="muted">Selecciona una campaña aprobada para ver la vista previa.</p>
        <?php endif; ?>
    </section>
</div>
<?php vendor_footer(); ?>
