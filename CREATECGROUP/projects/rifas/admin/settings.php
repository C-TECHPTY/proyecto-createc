<?php
require_once __DIR__ . '/includes/auth.php';
$admin = require_admin();
$pdo = db();
$pushCount = 0;
try {
    $pushCount = (int) $pdo->query('SELECT COUNT(*) FROM push_subscriptions WHERE active = 1')->fetchColumn();
} catch (Throwable) {
    $pushCount = 0;
}

$settingsMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['section'] ?? '') === 'whatsapp') {
    try {
        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            throw new RuntimeException('Sesión expirada.');
        }
        $provider = in_array($_POST['provider'] ?? 'manual', ['manual','cloud_api','twilio','wati','360dialog'], true) ? $_POST['provider'] : 'manual';
        $active = !empty($_POST['active']) ? 1 : 0;
        $existingId = (int) $pdo->query('SELECT id FROM whatsapp_configs ORDER BY id DESC LIMIT 1')->fetchColumn();
        if ($existingId) {
            $stmt = $pdo->prepare('UPDATE whatsapp_configs SET provider=?, phone_number=?, phone_id=?, api_token=?, webhook_verify_token=?, active=? WHERE id=?');
            $stmt->execute([
                $provider,
                $_POST['phone_number'] ?? '',
                $_POST['phone_id'] ?? '',
                $_POST['api_token'] ?? '',
                $_POST['webhook_verify_token'] ?? '',
                $active,
                $existingId,
            ]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO whatsapp_configs (provider, phone_number, phone_id, api_token, webhook_verify_token, active) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $provider,
                $_POST['phone_number'] ?? '',
                $_POST['phone_id'] ?? '',
                $_POST['api_token'] ?? '',
                $_POST['webhook_verify_token'] ?? '',
                $active,
            ]);
        }
        audit_log($pdo, 'whatsapp_config_updated', 'settings', null, ['provider' => $provider, 'active' => $active]);
        $settingsMessage = 'Configuración WhatsApp guardada.';
    } catch (Throwable $e) {
        $settingsMessage = $e->getMessage();
    }
}

$whatsappConfig = [];
$messages = [];
try {
    $whatsappConfig = $pdo->query('SELECT * FROM whatsapp_configs ORDER BY id DESC LIMIT 1')->fetch() ?: [];
    $messages = $pdo->query('SELECT * FROM whatsapp_messages ORDER BY created_at DESC LIMIT 50')->fetchAll();
} catch (Throwable) {
    $whatsappConfig = [];
    $messages = [];
}

$pageTitle = 'Settings';
$publicUrl = rtrim((string) config_value('APP_URL'), '/');
$projectUrl = preg_replace('~/public$~', '', $publicUrl) ?: $publicUrl;
require __DIR__ . '/includes/header.php';
?>
<section class="panel">
    <h2>PWA y notificaciones</h2>
    <div class="settings-grid">
        <article>
            <span>Manifest</span>
            <strong>Activo</strong>
            <p class="muted">La app puede instalarse desde navegador compatible.</p>
        </article>
        <article>
            <span>Service worker</span>
            <strong>Activo</strong>
            <p class="muted">Cache básico, fallback offline y estructura de push.</p>
        </article>
        <article>
            <span>Web Push</span>
            <strong><?= config_value('WEB_PUSH_PUBLIC_KEY') ? 'Preparado' : 'Pendiente de claves' ?></strong>
            <p class="muted"><?= $pushCount ?> dispositivo(s) admin suscrito(s).</p>
        </article>
    </div>
    <p class="muted">Para activar envío real de Web Push configura claves VAPID en `config.php` y conecta un proveedor/librería de envío en una fase futura.</p>
</section>

<section class="panel" id="whatsapp-api">
    <div class="panel-heading"><h2>WhatsApp API / bot futuro</h2></div>
    <?php if ($settingsMessage): ?><div class="flash"><?= e($settingsMessage) ?></div><?php endif; ?>
    <form method="post" class="admin-form">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="section" value="whatsapp">
        <div class="form-grid">
            <label>Proveedor
                <select name="provider">
                    <?php foreach (['manual' => 'Manual wa.me', 'cloud_api' => 'WhatsApp Cloud API', 'twilio' => 'Twilio', 'wati' => 'WATI', '360dialog' => '360Dialog'] as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= (($whatsappConfig['provider'] ?? 'manual') === $key) ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Teléfono / WhatsApp <input name="phone_number" value="<?= e($whatsappConfig['phone_number'] ?? (string) config_value('ADMIN_WHATSAPP')) ?>"></label>
            <label>Phone ID <input name="phone_id" value="<?= e($whatsappConfig['phone_id'] ?? (string) config_value('WHATSAPP_PHONE_ID')) ?>"></label>
            <label>Verify token webhook <input name="webhook_verify_token" value="<?= e($whatsappConfig['webhook_verify_token'] ?? '') ?>"></label>
        </div>
        <label>API token opcional <textarea name="api_token" placeholder="Se guarda para fase futura; no se usa para enviar todavía."><?= e($whatsappConfig['api_token'] ?? (string) config_value('WHATSAPP_API_TOKEN')) ?></textarea></label>
        <label class="check-label"><input type="checkbox" name="active" value="1" <?= !empty($whatsappConfig['active']) ? 'checked' : '' ?>> Activar configuración para verificación de webhook</label>
        <button class="button" type="submit">Guardar WhatsApp</button>
    </form>
    <div class="webhook-box">
        <span>Webhook URL</span>
        <code><?= e($projectUrl) ?>/api/whatsapp_webhook.php?provider=cloud_api</code>
        <p class="muted">En hosting real ajusta la URL al dominio público. El modo manual con enlaces wa.me sigue funcionando aunque esto esté apagado.</p>
    </div>
</section>

<section class="panel" id="whatsapp-messages">
    <div class="panel-heading"><h2>Mensajes WhatsApp recibidos</h2></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Fecha</th><th>De</th><th>Intent</th><th>Mensaje</th><th>Proveedor</th></tr></thead>
            <tbody>
            <?php foreach ($messages as $message): ?>
                <tr>
                    <td><?= e($message['created_at']) ?></td>
                    <td><?= e($message['from_phone'] ?? '-') ?></td>
                    <td><span class="status"><?= e($message['intent'] ?? '-') ?></span></td>
                    <td><?= e($message['message_text'] ?? '') ?></td>
                    <td><?= e($message['provider']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (!$messages): ?><p class="muted">Aún no hay mensajes entrantes registrados.</p><?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
