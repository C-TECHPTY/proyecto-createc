<?php
declare(strict_types=1);
?>
<form method="post">
    <?= sa_csrf_field() ?>
    <div class="form-grid">
        <label class="field">Empresa <input name="company_name" value="<?= sa_e($values['company_name'] ?? '') ?>" required></label>
        <?php if (sa_column_exists('sa_companies', 'legal_name')): ?>
            <label class="field">Razon legal <input name="legal_name" value="<?= sa_e($values['legal_name'] ?? '') ?>"></label>
        <?php endif; ?>
        <label class="field">Slug <input name="slug" value="<?= sa_e($values['slug'] ?? '') ?>" placeholder="mi-empresa"></label>
        <label class="field">Contacto <input name="contact_name" value="<?= sa_e($values['contact_name'] ?? '') ?>"></label>
        <label class="field">Email contacto <input type="email" name="contact_email" value="<?= sa_e($values['contact_email'] ?? '') ?>"></label>
        <label class="field">Telefono <input name="contact_phone" value="<?= sa_e($values['contact_phone'] ?? '') ?>"></label>
        <label class="field">Dominio <input name="domain" value="<?= sa_e($values['domain'] ?? '') ?>" placeholder="empresa.com"></label>
        <label class="field">Subdominio <input name="subdomain" value="<?= sa_e($values['subdomain'] ?? '') ?>" placeholder="empresa"></label>
        <label class="field">Logo URL <input name="logo_url" value="<?= sa_e($values['logo_url'] ?? '') ?>"></label>
        <label class="field">Color principal <input name="primary_color" value="<?= sa_e($values['primary_color'] ?? '#0f4c81') ?>"></label>
        <?php if (sa_column_exists('sa_companies', 'plan_id')): ?>
            <?php $plans = sa_plan_options(); ?>
            <label class="field">Plan
                <select name="plan_id">
                    <option value="0">Sin plan asignado</option>
                    <?php foreach ($plans as $plan): ?>
                        <option value="<?= (int) $plan['id'] ?>" <?= (int) ($values['plan_id'] ?? 0) === (int) $plan['id'] ? 'selected' : '' ?>><?= sa_e($plan['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php endif; ?>
        <?php if (sa_column_exists('sa_companies', 'expires_at')): ?>
            <label class="field">Vence <input type="date" name="expires_at" value="<?= sa_e($values['expires_at'] ?? '') ?>"></label>
        <?php endif; ?>
        <label class="field">Estado
            <select name="status">
                <?php foreach (['active' => 'Activo', 'suspended' => 'Suspendido', 'inactive' => 'Inactivo'] as $value => $label): ?>
                    <option value="<?= sa_e($value) ?>" <?= ($values['status'] ?? '') === $value ? 'selected' : '' ?>><?= sa_e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php if (sa_column_exists('sa_companies', 'storage_mode')): ?>
            <label class="field">Almacenamiento
                <select name="storage_mode">
                    <?php foreach (['hosting' => 'Hosting actual', 'backblaze' => 'Backblaze B2/CDN', 'hybrid' => 'Hibrido'] as $value => $label): ?>
                        <option value="<?= sa_e($value) ?>" <?= ($values['storage_mode'] ?? 'hosting') === $value ? 'selected' : '' ?>><?= sa_e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php endif; ?>
        <?php if (sa_column_exists('sa_companies', 'max_catalogs')): ?>
            <label class="field">Max catalogos <input type="number" min="0" name="max_catalogs" value="<?= sa_e($values['max_catalogs'] ?? 0) ?>"></label>
        <?php endif; ?>
        <?php if (sa_column_exists('sa_companies', 'max_sellers')): ?>
            <label class="field">Max vendedores <input type="number" min="0" name="max_sellers" value="<?= sa_e($values['max_sellers'] ?? 0) ?>"></label>
        <?php endif; ?>
        <?php if (sa_column_exists('sa_companies', 'max_products')): ?>
            <label class="field">Max productos <input type="number" min="0" name="max_products" value="<?= sa_e($values['max_products'] ?? 0) ?>"></label>
        <?php endif; ?>
        <label class="field field--full">Notas <textarea name="notes"><?= sa_e($values['notes'] ?? '') ?></textarea></label>
    </div>
    <div class="actions" style="margin-top:16px;">
        <button class="button" type="submit">Guardar empresa</button>
        <a class="button button--ghost" href="companies.php">Volver</a>
    </div>
</form>
