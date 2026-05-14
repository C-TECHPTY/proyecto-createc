<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/footer.php';

sa_require_login();

$logs = sa_db()->query(
    'SELECT l.*, u.name AS admin_name, u.email AS admin_email, c.company_name
     FROM sa_activity_logs l
     INNER JOIN sa_admin_users u ON u.id = l.admin_user_id
     LEFT JOIN sa_companies c ON c.id = l.company_id
     ORDER BY l.created_at DESC
     LIMIT 200'
)->fetchAll();

sa_header('Actividad', 'settings.php');
?>
<section class="panel">
    <div class="toolbar">
        <div>
            <h3>Registro de acciones</h3>
            <p class="muted">Bitacora del modulo Super Admin. No modifica configuracion SMTP ni config.php.</p>
        </div>
    </div>
    <table>
        <thead><tr><th>Fecha</th><th>Admin</th><th>Empresa</th><th>Accion</th><th>Descripcion</th><th>IP</th></tr></thead>
        <tbody>
        <?php foreach ($logs as $log): ?>
            <tr>
                <td><?= sa_e($log['created_at']) ?></td>
                <td><?= sa_e($log['admin_name']) ?><br><span class="muted"><?= sa_e($log['admin_email']) ?></span></td>
                <td><?= sa_e($log['company_name'] ?: '-') ?></td>
                <td><?= sa_e($log['action']) ?></td>
                <td><?= sa_e($log['description']) ?></td>
                <td><?= sa_e($log['ip_address']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$logs): ?><tr><td colspan="6">Sin actividad registrada.</td></tr><?php endif; ?>
        </tbody>
    </table>
</section>
<?php sa_footer(); ?>
