<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function sa_header(string $title, string $active): void
{
    $user = sa_current_user();
    $flash = sa_flash_get();
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= sa_e($title) ?> | Super Admin CREATEC</title>
        <style>
            :root {
                --bg: #f5f7fb;
                --panel: #ffffff;
                --ink: #152033;
                --muted: #667085;
                --line: #d9e0ea;
                --brand: #0f4c81;
                --brand-dark: #0b3152;
                --accent: #d92d20;
                --ok: #067647;
                --warn: #b54708;
                --radius: 8px;
            }
            * { box-sizing: border-box; }
            body { margin: 0; font-family: Arial, Helvetica, sans-serif; background: var(--bg); color: var(--ink); }
            a { color: inherit; text-decoration: none; }
            .shell { min-height: 100vh; display: grid; grid-template-columns: 260px 1fr; }
            .sidebar { background: var(--brand-dark); color: #fff; padding: 22px; display: flex; flex-direction: column; gap: 24px; }
            .brand h1 { margin: 0; font-size: 20px; letter-spacing: 0; }
            .brand p { margin: 8px 0 0; color: #cbd5e1; line-height: 1.45; font-size: 13px; }
            .nav { display: grid; gap: 6px; }
            .nav a { padding: 11px 12px; border-radius: var(--radius); color: #e5edf7; font-size: 14px; }
            .nav a.active, .nav a:hover { background: rgba(255,255,255,.13); color: #fff; }
            .sidebar-footer { margin-top: auto; color: #d6e0ec; font-size: 13px; display: grid; gap: 5px; }
            .main { padding: 28px; min-width: 0; }
            .topbar { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; margin-bottom: 22px; }
            .topbar h2 { margin: 0; font-size: 26px; letter-spacing: 0; }
            .topbar p { margin: 7px 0 0; color: var(--muted); }
            .panel { background: var(--panel); border: 1px solid var(--line); border-radius: var(--radius); padding: 18px; margin-bottom: 18px; }
            .grid { display: grid; gap: 16px; }
            .grid--stats { grid-template-columns: repeat(5, minmax(140px, 1fr)); }
            .grid--two { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .stat strong { display: block; font-size: 30px; margin-bottom: 5px; }
            .stat span, .muted { color: var(--muted); font-size: 14px; }
            .toolbar { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 14px; }
            .button { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 38px; padding: 9px 13px; border: 1px solid var(--brand); border-radius: var(--radius); background: var(--brand); color: #fff; font-weight: 700; cursor: pointer; }
            .button--ghost { background: #fff; color: var(--brand); }
            .button--danger { background: var(--accent); border-color: var(--accent); }
            table { width: 100%; border-collapse: collapse; }
            th, td { padding: 12px 10px; border-bottom: 1px solid var(--line); text-align: left; vertical-align: top; font-size: 14px; }
            th { color: var(--muted); font-size: 12px; text-transform: uppercase; letter-spacing: .04em; }
            .actions { display: flex; gap: 8px; flex-wrap: wrap; }
            .badge { display: inline-flex; align-items: center; min-height: 24px; padding: 3px 9px; border-radius: 999px; background: #eef2f7; color: #344054; font-size: 12px; font-weight: 700; }
            .badge--ok { background: #dcfae6; color: var(--ok); }
            .badge--warn { background: #fef0c7; color: var(--warn); }
            .badge--danger { background: #fee4e2; color: var(--accent); }
            .form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 15px; }
            .field { display: grid; gap: 7px; }
            .field--full { grid-column: 1 / -1; }
            label { color: #344054; font-size: 13px; font-weight: 700; }
            input, select, textarea { width: 100%; border: 1px solid var(--line); border-radius: var(--radius); padding: 10px 11px; font: inherit; background: #fff; color: var(--ink); }
            textarea { min-height: 110px; resize: vertical; }
            .flash { border-radius: var(--radius); padding: 12px 14px; margin-bottom: 18px; background: #dcfae6; color: var(--ok); border: 1px solid #abefc6; }
            .flash--error { background: #fee4e2; color: var(--accent); border-color: #fecdca; }
            .login-page { min-height: 100vh; display: grid; place-items: center; padding: 20px; background: linear-gradient(135deg, #0b3152, #0f4c81 55%, #f5f7fb 55%); }
            .login-card { width: min(430px, 100%); background: #fff; border-radius: var(--radius); border: 1px solid var(--line); padding: 26px; box-shadow: 0 18px 50px rgba(15, 35, 60, .18); }
            .login-card h1 { margin: 0 0 8px; font-size: 25px; letter-spacing: 0; }
            @media (max-width: 960px) {
                .shell { grid-template-columns: 1fr; }
                .sidebar { position: static; }
                .grid--stats, .grid--two, .form-grid { grid-template-columns: 1fr; }
                .main { padding: 20px; }
                .topbar, .toolbar { flex-direction: column; align-items: stretch; }
            }
        </style>
    </head>
    <body>
    <?php if ($user): ?>
        <div class="shell">
            <?php require __DIR__ . '/sidebar.php'; ?>
            <main class="main">
                <div class="topbar">
                    <div>
                        <h2><?= sa_e($title) ?></h2>
                        <p>Control inicial de empresas, planes y licencias sin afectar el sistema operativo actual.</p>
                    </div>
                    <span class="badge"><?= sa_e(date('Y-m-d H:i')) ?></span>
                </div>
                <?php if ($flash): ?>
                    <div class="flash <?= ($flash['type'] ?? '') === 'error' ? 'flash--error' : '' ?>">
                        <?= sa_e($flash['message'] ?? '') ?>
                    </div>
                <?php endif; ?>
    <?php endif; ?>
    <?php
}
