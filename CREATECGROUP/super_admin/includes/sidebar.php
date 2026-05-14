<?php
declare(strict_types=1);

$items = [
    'dashboard.php' => 'Dashboard',
    'companies.php' => 'Empresas',
    'company_domains.php' => 'Dominios',
    'plans.php' => 'Planes SaaS',
    'subscriptions.php' => 'Suscripciones',
    'licenses.php' => 'Licencias',
    'modules.php' => 'Modulos',
    'project_instances.php' => 'Instancias',
    'publish_logs.php' => 'Publicaciones SaaS',
    'settings.php' => 'Actividad',
];
?>
<aside class="sidebar">
    <div class="brand">
        <h1>CREATEC Super Admin</h1>
        <p>Panel independiente para preparar la plataforma multiempresa.</p>
    </div>
    <nav class="nav">
        <?php foreach ($items as $href => $label): ?>
            <a class="<?= $active === $href ? 'active' : '' ?>" href="<?= sa_e($href) ?>"><?= sa_e($label) ?></a>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-footer">
        <?php $user = sa_current_user(); ?>
        <strong><?= sa_e($user['name'] ?? '') ?></strong>
        <span><?= sa_e($user['email'] ?? '') ?></span>
        <a href="logout.php">Cerrar sesion</a>
    </div>
</aside>
