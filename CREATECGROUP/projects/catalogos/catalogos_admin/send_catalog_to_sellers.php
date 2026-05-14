<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
admin_require_login(['admin', 'sales']);

$catalogId = (int) ($_GET['catalog_id'] ?? $_POST['catalog_id'] ?? 0);
$catalog = $catalogId > 0 ? admin_fetch_catalog_for_seller_email($catalogId) : null;
$schemaReady = admin_table_exists('catalogs')
    && admin_table_exists('sellers')
    && admin_table_exists('catalog_share_links');
$logsReady = admin_table_exists('catalog_seller_email_logs');
$activeSellers = $schemaReady ? admin_active_sellers_with_email() : [];
$resultSummary = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_abort();
    $createMissing = isset($_POST['create_missing_link']);
    $resultSummary = $logsReady
        ? admin_send_catalog_to_sellers($catalog, $activeSellers, $createMissing)
        : ['sent' => 0, 'skipped' => 0, 'errors' => 1, 'details' => [['seller_name' => '', 'email' => '', 'status' => 'error', 'message' => 'Falta ejecutar la migracion catalog_seller_email_logs.']]];
}

admin_header('Enviar catalogo a vendedores', 'catalogos.php');
?>
<section class="card">
    <div class="toolbar">
        <strong>Enviar catalogo a vendedores</strong>
        <a class="button" href="catalogos.php">Volver</a>
    </div>

    <?php if (!$schemaReady): ?>
        <p class="muted">Faltan tablas requeridas: catalogos, vendedores o links seguros. Ejecuta las migraciones B2B antes de enviar.</p>
    <?php elseif (!$logsReady): ?>
        <p class="muted">Falta la tabla de logs <code>catalog_seller_email_logs</code>. Ejecuta <code>hosting/sql/20260505_catalog_seller_email_logs.sql</code> antes de enviar.</p>
    <?php elseif (!$catalog): ?>
        <p class="muted">El catalogo solicitado no existe.</p>
    <?php elseif (resolve_catalog_status($catalog) !== 'active'): ?>
        <p class="muted">Este catalogo no esta activo. Solo se pueden enviar catalogos publicados y activos.</p>
    <?php elseif (trim((string) ($catalog['public_url'] ?? '')) === ''): ?>
        <p class="muted">Este catalogo no tiene URL publica configurada. Publicalo antes de enviarlo a vendedores.</p>
    <?php else: ?>
        <div class="metrics-grid" style="margin-bottom:18px;">
            <div class="metric-card">
                <span>Catalogo</span>
                <strong><?= html_escape($catalog['title'] ?? '') ?></strong>
                <p class="muted"><?= html_escape($catalog['public_url'] ?? '') ?></p>
            </div>
            <div class="metric-card">
                <span>Vendedores activos con correo</span>
                <strong><?= count($activeSellers) ?></strong>
                <p class="muted">Se enviara un correo individual por vendedor.</p>
            </div>
        </div>

        <?php if ($resultSummary): ?>
            <div class="notice <?= (int) $resultSummary['errors'] > 0 ? 'notice--warning' : 'notice--success' ?>" style="margin-bottom:18px;">
                Enviados: <?= (int) $resultSummary['sent'] ?> · Omitidos: <?= (int) $resultSummary['skipped'] ?> · Errores: <?= (int) $resultSummary['errors'] ?>
            </div>
            <?php if (!empty($resultSummary['details'])): ?>
                <div class="table-wrap" style="margin-bottom:18px;">
                    <table>
                        <thead><tr><th>Vendedor</th><th>Email</th><th>Estado</th><th>Detalle</th></tr></thead>
                        <tbody>
                        <?php foreach ($resultSummary['details'] as $detail): ?>
                            <tr>
                                <td><?= html_escape($detail['seller_name'] ?? '') ?></td>
                                <td><?= html_escape($detail['email'] ?? '') ?></td>
                                <td><?= html_escape($detail['status'] ?? '') ?></td>
                                <td><?= html_escape($detail['message'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <form method="post" onsubmit="return confirm('Confirmas enviar este catalogo a todos los vendedores activos con correo?');">
            <?= csrf_field() ?>
            <input type="hidden" name="catalog_id" value="<?= (int) $catalog['id'] ?>">
            <label class="check-row" style="margin-bottom:16px;">
                <input type="checkbox" name="create_missing_link" value="1" checked>
                <span>Crear link seguro si no existe para el vendedor.</span>
            </label>
            <button class="button--primary" type="submit" <?= count($activeSellers) === 0 ? 'disabled' : '' ?>>Confirmar envio</button>
        </form>
        <?php if (count($activeSellers) === 0): ?>
            <p class="muted">No hay vendedores activos con correo valido para enviar.</p>
        <?php endif; ?>
    <?php endif; ?>
</section>
<?php admin_footer(); ?>

<?php
function admin_fetch_catalog_for_seller_email(int $catalogId): ?array
{
    if (!admin_table_exists('catalogs')) {
        return null;
    }
    $statement = db()->prepare('SELECT * FROM catalogs WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $catalogId]);
    $catalog = $statement->fetch();
    return $catalog ?: null;
}

function admin_active_sellers_with_email(): array
{
    if (!admin_table_exists('sellers')) {
        return [];
    }
    $statement = db()->query(
        "SELECT id, name, email
         FROM sellers
         WHERE is_active = 1 AND email IS NOT NULL AND email <> ''
         ORDER BY name ASC"
    );
    return array_values(array_filter($statement->fetchAll(), static function (array $seller): bool {
        return filter_var((string) ($seller['email'] ?? ''), FILTER_VALIDATE_EMAIL) !== false;
    }));
}

function admin_send_catalog_to_sellers(?array $catalog, array $sellers, bool $createMissing): array
{
    $summary = ['sent' => 0, 'skipped' => 0, 'errors' => 0, 'details' => []];
    if (!$catalog || resolve_catalog_status($catalog) !== 'active' || trim((string) ($catalog['public_url'] ?? '')) === '') {
        $summary['errors']++;
        $summary['details'][] = ['seller_name' => '', 'email' => '', 'status' => 'error', 'message' => 'Catalogo invalido o inactivo.'];
        return $summary;
    }

    foreach ($sellers as $seller) {
        $sellerId = (int) ($seller['id'] ?? 0);
        $email = trim((string) ($seller['email'] ?? ''));
        $sellerName = trim((string) ($seller['name'] ?? 'Vendedor'));
        if ($sellerId <= 0 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $summary['skipped']++;
            continue;
        }

        $link = admin_find_active_catalog_seller_link((int) $catalog['id'], $sellerId);
        if (!$link && $createMissing) {
            $link = create_share_link((int) $catalog['id'], $sellerId, null, 'Catalogo para vendedor', null, 'Creado por envio masivo a vendedores.');
        }

        if (!$link || empty($link['token'])) {
            $summary['skipped']++;
            $message = 'Sin link seguro activo y no se solicito crearlo.';
            admin_log_catalog_seller_email((int) $catalog['id'], $sellerId, null, '', $email, 'error', $message);
            $summary['details'][] = ['seller_name' => $sellerName, 'email' => $email, 'status' => 'omitido', 'message' => $message];
            continue;
        }

        $catalogUrl = rtrim((string) $catalog['public_url'], '/') . '/?token=' . rawurlencode((string) $link['token']);
        $plain = admin_catalog_seller_email_plain($catalog, $sellerName, $catalogUrl);
        $mailStatus = send_notification_mail('Catalogo disponible', $plain, [$email]);
        $sent = $mailStatus === 'sent';
        $status = $sent ? 'sent' : 'error';
        $error = $sent ? '' : 'El servidor de correo no confirmo el envio.';
        admin_log_catalog_seller_email((int) $catalog['id'], $sellerId, (int) ($link['id'] ?? 0), (string) $link['token'], $email, $status, $error);

        if ($sent) {
            $summary['sent']++;
        } else {
            $summary['errors']++;
        }
        $summary['details'][] = ['seller_name' => $sellerName, 'email' => $email, 'status' => $status, 'message' => $sent ? 'Correo enviado.' : $error];
    }

    audit_log('catalog.sent_to_sellers', 'catalogs', (int) ($catalog['id'] ?? 0), [
        'sent' => $summary['sent'],
        'skipped' => $summary['skipped'],
        'errors' => $summary['errors'],
    ]);
    return $summary;
}

function admin_find_active_catalog_seller_link(int $catalogId, int $sellerId): ?array
{
    $statement = db()->prepare(
        'SELECT id, token
         FROM catalog_share_links
         WHERE catalog_id = :catalog_id
           AND seller_id = :seller_id
           AND client_id IS NULL
           AND is_active = 1
           AND (expires_at IS NULL OR expires_at >= NOW())
         ORDER BY id DESC
         LIMIT 1'
    );
    $statement->execute(['catalog_id' => $catalogId, 'seller_id' => $sellerId]);
    $link = $statement->fetch();
    return $link ?: null;
}

function admin_catalog_email_greeting(): string
{
    $hour = (int) date('G');
    if ($hour < 12) {
        return 'Buenos dias';
    }
    if ($hour < 18) {
        return 'Buenas tardes';
    }
    return 'Buenas noches';
}

function admin_catalog_seller_email_plain(array $catalog, string $sellerName, string $catalogUrl): string
{
    $greeting = admin_catalog_email_greeting();
    return implode("\n", [
        $greeting . ' ' . $sellerName . ',',
        '',
        'Ya se encuentra disponible un nuevo catalogo para compartir con sus clientes.',
        'Use el siguiente enlace personalizado para que los pedidos queden asociados a usted:',
        $catalogUrl,
    ]) . "\n\n" . build_company_signature();
}

function admin_catalog_seller_email_html(array $catalog, string $sellerName, string $catalogUrl): string
{
    $brandColor = '#2c4695';
    $greeting = admin_catalog_email_greeting();
    $title = trim((string) ($catalog['title'] ?? 'Nuevo catalogo'));
    $safeUrl = html_escape($catalogUrl);
    return '<!doctype html><html><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8"></head>'
        . '<body style="margin:0;padding:0;background:#f4f6f8;font-family:Arial,Helvetica,sans-serif;color:#172033;">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f4f6f8;"><tr><td align="center" style="padding:28px 12px;">'
        . '<table role="presentation" width="680" cellspacing="0" cellpadding="0" border="0" style="width:680px;max-width:680px;background:#ffffff;">'
        . '<tr><td style="background:' . $brandColor . ';padding:24px 28px;color:#ffffff;font-size:24px;font-weight:700;">Rodeo Import</td></tr>'
        . '<tr><td style="padding:28px;color:#172033;font-size:15px;line-height:23px;">'
        . '<h1 style="margin:0 0 14px;font-size:24px;line-height:30px;color:#172033;">' . html_escape($title) . '</h1>'
        . '<p style="margin:0 0 14px;">' . html_escape($greeting . ' ' . $sellerName) . ',</p>'
        . '<p style="margin:0 0 14px;">Ya se encuentra disponible un nuevo catalogo para compartir con sus clientes.</p>'
        . '<p style="margin:0 0 20px;">Use el siguiente enlace personalizado para que los pedidos queden asociados a usted:</p>'
        . '<table role="presentation" cellspacing="0" cellpadding="0" border="0"><tr><td bgcolor="' . $brandColor . '" style="background:' . $brandColor . ';padding:13px 18px;"><a href="' . $safeUrl . '" style="display:block;color:#ffffff;text-decoration:none;font-size:15px;font-weight:700;">Abrir catalogo</a></td></tr></table>'
        . '<p style="margin:22px 0 8px;color:#667085;">Tambien puede copiar este enlace:</p>'
        . '<p style="margin:0;word-break:break-all;color:' . $brandColor . ';">' . $safeUrl . '</p>'
        . '<p style="margin:24px 0 0;">Saludos,<br>Rodeo Import</p>'
        . '</td></tr></table></td></tr></table></body></html>';
}

function admin_log_catalog_seller_email(int $catalogId, int $sellerId, ?int $secureLinkId, string $token, string $email, string $status, string $errorMessage = ''): void
{
    if (!admin_table_exists('catalog_seller_email_logs')) {
        return;
    }
    db()->prepare(
        'INSERT INTO catalog_seller_email_logs (catalog_id, seller_id, secure_link_id, token, email, status, error_message, sent_at)
         VALUES (:catalog_id, :seller_id, :secure_link_id, :token, :email, :status, :error_message, NOW())'
    )->execute([
        'catalog_id' => $catalogId,
        'seller_id' => $sellerId,
        'secure_link_id' => $secureLinkId ?: null,
        'token' => $token,
        'email' => $email,
        'status' => $status,
        'error_message' => $errorMessage,
    ]);
}
