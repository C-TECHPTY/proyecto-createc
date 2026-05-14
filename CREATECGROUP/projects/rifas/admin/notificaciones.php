<?php
require_once __DIR__ . '/includes/auth.php';
$admin = require_admin();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verify_csrf($_POST['_csrf'] ?? null)) {
        if (($_POST['action'] ?? '') === 'mark_all_read') {
            $pdo->exec('UPDATE notifications SET read_at = NOW() WHERE read_at IS NULL');
            $_SESSION['flash'] = 'Notificaciones marcadas como leídas.';
        } elseif (($_POST['action'] ?? '') === 'mark_read') {
            $stmt = $pdo->prepare('UPDATE notifications SET read_at = NOW() WHERE id = ? AND read_at IS NULL');
            $stmt->execute([(int) ($_POST['id'] ?? 0)]);
        }
    }
    redirect('notificaciones.php');
}

$notifications = $pdo->query('
    SELECT *
    FROM notifications
    ORDER BY created_at DESC
    LIMIT 100
')->fetchAll();

$pageTitle = 'Notificaciones';
require __DIR__ . '/includes/header.php';
?>
<section class="panel">
    <div class="panel-heading">
        <h2>Bandeja de notificaciones</h2>
        <form method="post">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="mark_all_read">
            <button class="button button-ghost" type="submit">Marcar todas como leídas</button>
        </form>
    </div>
    <div class="notification-list">
        <?php foreach ($notifications as $notification): ?>
            <article class="notification-item <?= $notification['read_at'] ? 'is-read' : 'is-unread' ?>">
                <div>
                    <strong><?= e($notification['title']) ?></strong>
                    <small><?= e($notification['created_at']) ?></small>
                    <p><?= nl2br(e($notification['body'])) ?></p>
                    <?php if ($notification['url']): ?><a href="<?= e($notification['url']) ?>">Abrir módulo relacionado</a><?php endif; ?>
                </div>
                <?php if (!$notification['read_at']): ?>
                    <form method="post">
                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="mark_read">
                        <input type="hidden" name="id" value="<?= (int) $notification['id'] ?>">
                        <button class="button button-ghost" type="submit">Leída</button>
                    </form>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
        <?php if (!$notifications): ?><p class="muted">No hay notificaciones todavía.</p><?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
