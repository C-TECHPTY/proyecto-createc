<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
admin_require_login(['admin', 'billing', 'sales']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_abort();
    $settings = [
        'mail_sales' => trim((string) ($_POST['mail_sales'] ?? '')),
        'mail_billing' => trim((string) ($_POST['mail_billing'] ?? '')),
        'mail_logistics' => trim((string) ($_POST['mail_logistics'] ?? '')),
        'mail_supervision' => trim((string) ($_POST['mail_supervision'] ?? '')),
        'order_admin_emails' => trim((string) ($_POST['order_admin_emails'] ?? '')),
        'mail_copy_seller' => isset($_POST['mail_copy_seller']) ? '1' : '0',
        'mail_copy_client' => isset($_POST['mail_copy_client']) ? '1' : '0',
        'branding_company_name' => trim((string) ($_POST['branding_company_name'] ?? '')),
        'branding_login_title' => trim((string) ($_POST['branding_login_title'] ?? '')),
        'branding_login_subtitle' => trim((string) ($_POST['branding_login_subtitle'] ?? '')),
    ];

    try {
        $logoPath = save_uploaded_image('branding_company_logo_file', 'uploads/branding', 'company-logo');
        $loginLogoPath = save_uploaded_image('branding_login_logo_file', 'uploads/branding', 'login-logo');
        $backgroundPath = save_uploaded_image('branding_login_background_file', 'uploads/branding', 'login-background');
    } catch (Throwable $exception) {
        flash_set('error', $exception->getMessage());
        header('Location: configuracion.php');
        exit;
    }

    $currentCompanyLogo = app_setting('branding_company_logo');
    $currentLoginLogo = app_setting('branding_login_logo');
    $currentBackground = app_setting('branding_login_background');

    if (isset($_POST['remove_company_logo'])) {
        delete_panel_file($currentCompanyLogo);
        $settings['branding_company_logo'] = '';
    } elseif ($logoPath !== null) {
        delete_panel_file($currentCompanyLogo);
        $settings['branding_company_logo'] = $logoPath;
    }

    if (isset($_POST['remove_login_logo'])) {
        delete_panel_file($currentLoginLogo);
        $settings['branding_login_logo'] = '';
    } elseif ($loginLogoPath !== null) {
        delete_panel_file($currentLoginLogo);
        $settings['branding_login_logo'] = $loginLogoPath;
    }

    if (isset($_POST['remove_login_background'])) {
        delete_panel_file($currentBackground);
        $settings['branding_login_background'] = '';
    } elseif ($backgroundPath !== null) {
        delete_panel_file($currentBackground);
        $settings['branding_login_background'] = $backgroundPath;
    }

    update_app_settings($settings);
    audit_log('settings.notifications_updated', 'app_settings');
    flash_set('success', 'Configuracion actualizada.');
    header('Location: configuracion.php');
    exit;
}

admin_header('Configuracion', 'configuracion.php');
$apiKey = (string) catalog_config('api_key', '');
$maskedApiKey = $apiKey !== '' ? substr($apiKey, 0, 4) . str_repeat('*', max(8, strlen($apiKey) - 8)) . substr($apiKey, -4) : 'No configurada';
$brandingCompanyLogo = app_setting('branding_company_logo');
$brandingLoginLogo = app_setting('branding_login_logo');
$brandingLoginBackground = app_setting('branding_login_background');
?>
<div class="split">
    <section class="card">
        <div class="toolbar"><strong>Destinos de notificacion</strong></div>
        <form class="form-grid" method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <label><span>Ventas</span><input type="text" name="mail_sales" value="<?= html_escape(app_setting('mail_sales')) ?>"></label>
            <label><span>Facturacion</span><input type="text" name="mail_billing" value="<?= html_escape(app_setting('mail_billing')) ?>"></label>
            <label><span>Logistica</span><input type="text" name="mail_logistics" value="<?= html_escape(app_setting('mail_logistics')) ?>"></label>
            <label><span>Supervision</span><input type="text" name="mail_supervision" value="<?= html_escape(app_setting('mail_supervision')) ?>"></label>
            <label class="wide"><span>Correos administrativos de pedidos</span><input type="text" name="order_admin_emails" value="<?= html_escape(app_setting('order_admin_emails')) ?>" placeholder="pedidos@empresa.com, gerencia@empresa.com"></label>
            <label class="checkbox-line"><input type="checkbox" name="mail_copy_seller" <?= app_setting('mail_copy_seller', '1') === '1' ? 'checked' : '' ?>><span>Copiar a vendedor asignado</span></label>
            <label class="checkbox-line"><input type="checkbox" name="mail_copy_client" <?= app_setting('mail_copy_client', '1') === '1' ? 'checked' : '' ?>><span>Confirmacion al cliente</span></label>
            <label><span>Nombre de empresa</span><input type="text" name="branding_company_name" value="<?= html_escape(app_setting('branding_company_name', (string) catalog_config('app_name', 'Catalogo Rodeo B2B'))) ?>"></label>
            <label><span>Titulo del login</span><input type="text" name="branding_login_title" value="<?= html_escape(app_setting('branding_login_title', 'Catalogo Rodeo B2B')) ?>"></label>
            <label class="wide"><span>Texto del login</span><input type="text" name="branding_login_subtitle" value="<?= html_escape(app_setting('branding_login_subtitle', 'Administracion comercial, vendedores, links y pedidos trazables.')) ?>"></label>
            <label><span>Logo principal</span><input type="file" name="branding_company_logo_file" accept="image/*"></label>
            <label><span>Logo del login</span><input type="file" name="branding_login_logo_file" accept="image/*"></label>
            <label class="wide"><span>Fondo del login</span><input type="file" name="branding_login_background_file" accept="image/*"></label>
            <?php if ($brandingCompanyLogo !== ''): ?>
                <div class="branding-preview">
                    <img class="branding-preview__logo" src="<?= html_escape(panel_media_url($brandingCompanyLogo)) ?>" alt="Logo principal">
                    <label class="checkbox-line"><input type="checkbox" name="remove_company_logo"><span>Quitar logo principal</span></label>
                </div>
            <?php endif; ?>
            <?php if ($brandingLoginLogo !== ''): ?>
                <div class="branding-preview">
                    <img class="branding-preview__logo" src="<?= html_escape(panel_media_url($brandingLoginLogo)) ?>" alt="Logo login">
                    <label class="checkbox-line"><input type="checkbox" name="remove_login_logo"><span>Quitar logo del login</span></label>
                </div>
            <?php endif; ?>
            <?php if ($brandingLoginBackground !== ''): ?>
                <div class="branding-preview branding-preview--wide">
                    <img class="branding-preview__background" src="<?= html_escape(panel_media_url($brandingLoginBackground)) ?>" alt="Fondo login">
                    <label class="checkbox-line"><input type="checkbox" name="remove_login_background"><span>Quitar fondo del login</span></label>
                </div>
            <?php endif; ?>
            <div class="wide"><button class="button--primary" type="submit">Guardar configuracion</button></div>
        </form>
    </section>
    <section class="card">
        <div class="toolbar"><strong>Configuracion general</strong></div>
        <div class="list">
            <div class="list-item">
                <strong>Aplicacion</strong>
                <div class="muted"><?= html_escape((string) catalog_config('app_name', 'Catalogo Rodeo B2B')) ?></div>
            </div>
            <div class="list-item">
                <strong>API key privada</strong>
                <div class="muted"><?= html_escape($maskedApiKey) ?></div>
                <p class="muted">Se lee desde <code>catalogos_api/config.php</code>. No se muestra completa para evitar exposicion accidental.</p>
            </div>
            <div class="list-item">
                <strong>Carpeta publica de catalogos</strong>
                <div class="muted"><?= html_escape((string) catalog_config('paths.public_catalogs_dir', '')) ?></div>
            </div>
        </div>
    </section>
</div>
<section class="card" style="margin-top:18px;">
    <div class="toolbar"><strong>Ultimas notificaciones</strong></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Fecha</th><th>Destinatario</th><th>Asunto</th><th>Estado</th><th>Respuesta</th></tr></thead>
            <tbody>
            <?php foreach (db()->query('SELECT recipient, subject, status, response_message, created_at FROM notifications_log ORDER BY created_at DESC LIMIT 50')->fetchAll() as $row): ?>
                <tr>
                    <td><?= html_escape($row['created_at']) ?></td>
                    <td><?= html_escape($row['recipient']) ?></td>
                    <td><?= html_escape($row['subject']) ?></td>
                    <td><?= admin_status_badge((string) $row['status']) ?></td>
                    <td><?= html_escape($row['response_message'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php admin_footer(); ?>
