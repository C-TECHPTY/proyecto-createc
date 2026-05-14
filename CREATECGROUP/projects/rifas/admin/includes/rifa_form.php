<?php
$raffle = $raffle ?? [];
$drawMethodValue = $raffle['draw_method'] ?? 'Sorteo manual';
if ($drawMethodValue === 'Lotería Nacional de Beneficencia de Panamá') {
    $drawMethodValue = 'Sorteo manual';
}
?>
<section class="panel form-panel">
    <?php if (!empty($error)): ?><div class="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="admin-form">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <div class="form-grid">
            <label>Título <input name="title" required value="<?= e($raffle['title'] ?? '') ?>"></label>
            <label>Slug <input name="slug" value="<?= e($raffle['slug'] ?? '') ?>" placeholder="mega-rifa-gourmand"></label>
            <label>Primer premio <input name="first_prize" required value="<?= e($raffle['first_prize'] ?? '') ?>"></label>
            <label>Segundo premio <input name="second_prize" value="<?= e($raffle['second_prize'] ?? '') ?>"></label>
            <label>Tercer premio <input name="third_prize" value="<?= e($raffle['third_prize'] ?? '') ?>"></label>
            <label>Precio por número <input name="price_per_number" type="number" step="0.01" min="0" required value="<?= e((string)($raffle['price_per_number'] ?? '2.00')) ?>"></label>
            <label>Fecha sorteo <input name="draw_date" type="datetime-local" value="<?= e(isset($raffle['draw_date']) ? str_replace(' ', 'T', substr($raffle['draw_date'], 0, 16)) : '') ?>"></label>
            <label>Método sorteo <input name="draw_method" value="<?= e($drawMethodValue) ?>"></label>
            <label>Número mínimo <input name="number_min" type="number" min="0" value="<?= e((string)($raffle['number_min'] ?? '0')) ?>"></label>
            <label>Número máximo <input name="number_max" type="number" min="0" value="<?= e((string)($raffle['number_max'] ?? '100')) ?>"></label>
            <label>Expira reserva en minutos <input name="reservation_minutes" type="number" min="1" value="<?= e((string)($raffle['reservation_minutes'] ?? '20')) ?>"></label>
            <label>Estado
                <select name="status">
                    <?php foreach (['draft','active','closed','drawn'] as $status): ?>
                        <option value="<?= $status ?>" <?= (($raffle['status'] ?? 'active') === $status) ? 'selected' : '' ?>><?= $status ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Yappy <input name="yappy_number" value="<?= e($raffle['yappy_number'] ?? (string) config_value('YAPPY_NUMBER')) ?>"></label>
            <label>WhatsApp contacto <input name="contact_whatsapp" value="<?= e($raffle['contact_whatsapp'] ?? (string) config_value('ADMIN_WHATSAPP')) ?>"></label>
            <label>PayPal / tarjeta <input name="paypal_link" value="<?= e($raffle['paypal_link'] ?? (string) config_value('PAYPAL_LINK')) ?>"></label>
            <label>Costo artículo <input name="item_cost" type="number" step="0.01" value="<?= e((string)($raffle['item_cost'] ?? '0')) ?>"></label>
            <label>Color primario <input name="primary_color" type="color" value="<?= e($raffle['primary_color'] ?? '#38aeea') ?>"></label>
            <label>Color secundario <input name="accent_color" type="color" value="<?= e($raffle['accent_color'] ?? '#f06292') ?>"></label>
            <label>Fondo <input name="background_color" type="color" value="<?= e($raffle['background_color'] ?? '#eaf8ff') ?>"></label>
            <label>Tema
                <select name="theme">
                    <option value="clean_sky" <?= (($raffle['theme'] ?? 'clean_sky') === 'clean_sky') ? 'selected' : '' ?>>Limpio celeste</option>
                    <option value="premium_flyer" <?= (($raffle['theme'] ?? '') === 'premium_flyer') ? 'selected' : '' ?>>Flyer premium</option>
                    <option value="afro_glam" <?= (($raffle['theme'] ?? '') === 'afro_glam') ? 'selected' : '' ?>>Etnia negra / afro glam</option>
                    <option value="gourmand_perfume" <?= (($raffle['theme'] ?? '') === 'gourmand_perfume') ? 'selected' : '' ?>>Gourmand / perfume</option>
                    <option value="minimalist" <?= (($raffle['theme'] ?? '') === 'minimalist') ? 'selected' : '' ?>>Minimalista</option>
                </select>
            </label>
            <label>Estilo de grilla
                <select name="grid_style">
                    <option value="soft_cards" <?= (($raffle['grid_style'] ?? 'soft_cards') === 'soft_cards') ? 'selected' : '' ?>>Tarjetas suaves</option>
                    <option value="outlined" <?= (($raffle['grid_style'] ?? '') === 'outlined') ? 'selected' : '' ?>>Borde elegante</option>
                    <option value="compact" <?= (($raffle['grid_style'] ?? '') === 'compact') ? 'selected' : '' ?>>Compacta</option>
                    <option value="premium" <?= (($raffle['grid_style'] ?? '') === 'premium') ? 'selected' : '' ?>>Premium brillante</option>
                </select>
            </label>
            <label>Puntos por monto <input name="points_per_amount" type="number" step="0.01" value="<?= e((string)($raffle['points_per_amount'] ?? '2.00')) ?>"></label>
            <label>Puntos para número gratis <input name="points_for_free_number" type="number" value="<?= e((string)($raffle['points_for_free_number'] ?? '10')) ?>"></label>
        </div>
        <label>Datos bancarios <textarea name="bank_info"><?= e($raffle['bank_info'] ?? (string) config_value('BANK_INFO')) ?></textarea></label>
        <label>Descripción <textarea name="description"><?= e($raffle['description'] ?? '') ?></textarea></label>
        <label>Flyer / arte <input type="file" name="flyer" accept="image/jpeg,image/png,image/webp"></label>
        <button class="button" type="submit">Guardar rifa</button>
    </form>
</section>
