<?php
declare(strict_types=1);

function campaign_schema_ready(): bool
{
    foreach (['campaigns', 'campaign_products', 'campaign_recipients', 'campaign_logs'] as $table) {
        if (!catalog_table_exists($table)) {
            return false;
        }
    }
    return true;
}

function campaign_promo_schema_ready(): bool
{
    return catalog_table_exists('campaign_promo_orders')
        && catalog_table_exists('campaign_promo_order_items');
}

function campaign_module_enabled(): bool
{
    return app_setting('campaigns_enabled', '1') === '1';
}

function campaign_type_options(): array
{
    return [
        'nueva_mercancia' => 'Nueva mercancía',
        'promocion' => 'Promoción',
        'liquidacion' => 'Liquidación',
        'producto_destacado' => 'Producto destacado',
        'nuevo_catalogo' => 'Nuevo catálogo',
    ];
}

function campaign_status_label(string $status): string
{
    return [
        'draft' => 'Borrador',
        'approved' => 'Aprobada',
        'sent' => 'Enviada',
        'pending' => 'Pendiente',
        'failed' => 'Fallido',
    ][$status] ?? $status;
}

function campaign_batch_limit(): int
{
    return max(1, min(50, (int) catalog_config('campaigns.batch_limit', 20)));
}

function campaign_logo_url(): string
{
    return trim((string) catalog_config('branding.order_email_logo_url', 'https://rodeoimportzl.com/catalogos_admin/assets/logo-rodeo-blanco.png'));
}

function campaign_no_image_url(): string
{
    return trim((string) catalog_config('branding.order_email_no_image_url', 'https://rodeoimportzl.com/catalogos_admin/assets/no-image.png'));
}

function campaign_fetch(int $campaignId): ?array
{
    $statement = db()->prepare('SELECT * FROM campaigns WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $campaignId]);
    $campaign = $statement->fetch();
    return $campaign ?: null;
}

function campaign_products(int $campaignId): array
{
    $statement = db()->prepare('SELECT * FROM campaign_products WHERE campaign_id = :campaign_id ORDER BY id ASC');
    $statement->execute(['campaign_id' => $campaignId]);
    return $statement->fetchAll();
}

function campaign_recent_logs(int $campaignId, int $limit = 80): array
{
    $statement = db()->prepare(
        'SELECT * FROM campaign_logs
         WHERE campaign_id = :campaign_id
         ORDER BY created_at DESC, id DESC
         LIMIT ' . max(1, min(200, $limit))
    );
    $statement->execute(['campaign_id' => $campaignId]);
    return $statement->fetchAll();
}

function campaign_safe_text(mixed $value): string
{
    $text = trim((string) $value);
    return html_escape($text !== '' ? $text : 'No definido');
}

function campaign_money_text(mixed $value): string
{
    $raw = trim((string) $value);
    if ($raw === '') {
        return 'No definido';
    }
    $numeric = parse_decimal($raw);
    return $numeric > 0 ? 'USD ' . number_format($numeric, 2, '.', ',') : $raw;
}

function campaign_money(float $value): string
{
    return 'USD ' . number_format($value, 2, '.', ',');
}

function campaign_discount_options(): array
{
    return [
        'none' => 'Sin descuento',
        'percent' => 'Porcentaje',
        'fixed_price' => 'Precio fijo',
    ];
}

function campaign_calculate_promo_price(float $regularPrice, string $discountType, float $discountValue): float
{
    $regularPrice = max(0, $regularPrice);
    $discountValue = max(0, $discountValue);
    if ($discountType === 'percent') {
        return round(max(0, $regularPrice - ($regularPrice * $discountValue / 100)), 2);
    }
    if ($discountType === 'fixed_price') {
        return round($discountValue, 2);
    }
    return round($regularPrice, 2);
}

function campaign_normalize_product(array $product): array
{
    $regularPrice = parse_decimal($product['regular_price'] ?? $product['price'] ?? 0);
    $discountType = (string) ($product['discount_type'] ?? 'none');
    if (!array_key_exists($discountType, campaign_discount_options())) {
        $discountType = 'none';
    }
    $discountValue = max(0, parse_decimal($product['discount_value'] ?? 0));
    $promoPrice = campaign_calculate_promo_price($regularPrice, $discountType, $discountValue);
    if ($promoPrice <= 0 && $regularPrice > 0) {
        $promoPrice = $regularPrice;
        $discountType = 'none';
        $discountValue = 0;
    }

    return [
        'item' => trim((string) ($product['item'] ?? '')),
        'description' => trim((string) ($product['description'] ?? '')),
        'price' => trim((string) ($product['price'] ?? ($regularPrice > 0 ? (string) $regularPrice : ''))),
        'image_url' => safeImageUrl((string) ($product['image_url'] ?? ''), ''),
        'catalog_url' => trim((string) ($product['catalog_url'] ?? '')),
        'regular_price' => round($regularPrice, 2),
        'discount_type' => $discountType,
        'discount_value' => round($discountValue, 2),
        'promo_price' => round($promoPrice, 2),
        'promo_start' => campaign_datetime_or_null($product['promo_start'] ?? null),
        'promo_end' => campaign_datetime_or_null($product['promo_end'] ?? null),
        'active' => array_key_exists('active', $product) ? (!empty($product['active']) ? 1 : 0) : 1,
    ];
}

function campaign_datetime_or_null(mixed $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    $timestamp = strtotime($value);
    return $timestamp ? date('Y-m-d H:i:s', $timestamp) : null;
}

function campaign_product_available(array $product, ?int $now = null): bool
{
    if ((int) ($product['active'] ?? 1) !== 1) {
        return false;
    }
    $now = $now ?? time();
    if (!empty($product['promo_start']) && strtotime((string) $product['promo_start']) > $now) {
        return false;
    }
    if (!empty($product['promo_end']) && strtotime((string) $product['promo_end']) < $now) {
        return false;
    }
    return true;
}

function campaign_plain_body(array $campaign, array $products): string
{
    $lines = [
        (string) ($campaign['title'] ?? ''),
        '',
        trim((string) ($campaign['message'] ?? '')),
        '',
        'Productos:',
    ];
    foreach ($products as $product) {
        $lines[] = sprintf(
            '- %s | %s | %s | %s',
            (string) ($product['item'] ?? ''),
            (string) ($product['description'] ?? ''),
            campaign_money((float) ($product['promo_price'] ?? $product['regular_price'] ?? 0)),
            (string) ($product['catalog_url'] ?? $campaign['catalog_url'] ?? '')
        );
    }
    $lines[] = '';
    $lines[] = 'Rodeo Import · Sistema de Catálogos B2B';
    return implode("\n", $lines);
}

function campaign_html_body(array $campaign, array $products): string
{
    $brandColor = '#2c4695';
    $logoUrl = campaign_logo_url();
    $fallbackImage = campaign_no_image_url();
    $catalogUrl = trim((string) ($campaign['catalog_url'] ?? ''));
    $promoBaseUrl = rtrim((string) catalog_config('campaigns.promo_base_url', 'https://rodeoimportzl.com/catalogos/promo.php'), '?');
    $campaignId = (int) ($campaign['id'] ?? 0);
    $allPromoUrl = $promoBaseUrl . '?campaign_id=' . $campaignId;
    $logoHtml = $logoUrl !== ''
        ? '<img src="' . html_escape($logoUrl) . '" width="220" alt="RODEO IMPORT" style="display:block;border:0;max-width:220px;width:220px;height:auto;color:#ffffff;font-size:24px;font-weight:700;">'
        : '<span style="font-size:24px;font-weight:700;color:#ffffff;">RODEO IMPORT</span>';

    $productCells = '';
    foreach ($products as $index => $product) {
        $imageUrl = safeImageUrl((string) ($product['image_url'] ?? ''), $fallbackImage);
        $productUrl = trim((string) (($product['catalog_url'] ?? '') ?: $catalogUrl));
        $buyUrl = $promoBaseUrl . '?campaign_id=' . $campaignId . '&item=' . rawurlencode((string) ($product['item'] ?? ''));
        $allPromoUrl = $promoBaseUrl . '?campaign_id=' . $campaignId;
        $regularPrice = (float) ($product['regular_price'] ?? 0);
        $promoPrice = (float) ($product['promo_price'] ?? $regularPrice);
        $hasOffer = $regularPrice > 0 && $promoPrice > 0 && $promoPrice < $regularPrice;
        $viewButton = $productUrl !== ''
            ? '<a href="' . html_escape($productUrl) . '" style="display:inline-block;background:' . $brandColor . ';color:#ffffff;text-decoration:none;font-size:12px;font-weight:700;padding:9px 12px;border-radius:6px;">Ver producto</a>'
            : '';
        $buyButton = '<a href="' . html_escape($buyUrl) . '" style="display:inline-block;background:#16a34a;color:#ffffff;text-decoration:none;font-size:12px;font-weight:700;padding:9px 12px;border-radius:6px;margin-right:6px;">Comprar promoción</a>';
        $priceHtml = $regularPrice > 0
            ? '<div style="font-size:13px;color:#667085;margin-top:8px;text-decoration:line-through;">' . html_escape(campaign_money($regularPrice)) . '</div>'
            : '';
        $priceHtml .= '<div style="font-size:20px;color:' . $brandColor . ';font-weight:800;margin-top:2px;">' . html_escape(campaign_money($promoPrice)) . '</div>';
        $badge = $hasOffer ? '<span style="display:inline-block;background:#dc2626;color:#ffffff;font-size:11px;font-weight:800;padding:4px 8px;border-radius:999px;margin-top:8px;">OFERTA</span>' : '';
        $productCells .= '<td width="50%" style="padding:8px;vertical-align:top;">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border:1px solid #e2e7f0;border-radius:8px;background:#ffffff;">'
            . '<tr><td align="center" style="padding:14px;background:#f4f6f8;"><img src="' . html_escape($imageUrl) . '" width="150" height="150" alt="' . campaign_safe_text($product['item'] ?? '') . '" style="display:block;width:150px;height:150px;object-fit:contain;border:0;"></td></tr>'
            . '<tr><td style="padding:14px;"><div style="font-size:12px;font-weight:700;color:' . $brandColor . ';">ITEM ' . campaign_safe_text($product['item'] ?? '') . '</div>'
            . '<div style="font-size:15px;line-height:20px;color:#172033;font-weight:700;margin-top:6px;">' . campaign_safe_text($product['description'] ?? '') . '</div>'
            . $badge . $priceHtml
            . '<div style="margin-top:12px;">' . $buyButton . $viewButton . '</div></td></tr></table></td>';
        if ($index % 2 === 1) {
            $productCells .= '</tr><tr>';
        }
    }
    if ($productCells === '') {
        $productCells = '<td style="padding:14px;color:#667085;">No definido</td>';
    }

    $catalogButton = '<tr><td align="center" style="padding:18px 28px;"><a href="' . html_escape($allPromoUrl) . '" style="display:inline-block;background:' . $brandColor . ';color:#ffffff;text-decoration:none;font-size:15px;font-weight:700;padding:13px 18px;border-radius:8px;">Ver todas las promociones</a></td></tr>';

    return '<!doctype html><html><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>'
        . '<body style="margin:0;padding:0;background:#f4f6f8;font-family:Arial,Helvetica,sans-serif;color:#172033;">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f4f6f8;"><tr><td align="center" style="padding:28px 12px;">'
        . '<table role="presentation" width="760" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:760px;background:#ffffff;border-radius:12px;overflow:hidden;">'
        . '<tr><td style="background:' . $brandColor . ';padding:28px;">' . $logoHtml . '<div style="font-size:28px;line-height:36px;color:#ffffff;font-weight:700;margin-top:22px;">' . campaign_safe_text($campaign['title'] ?? '') . '</div></td></tr>'
        . '<tr><td style="padding:24px 28px;color:#344054;font-size:15px;line-height:23px;">' . nl2br(campaign_safe_text($campaign['message'] ?? '')) . '</td></tr>'
        . '<tr><td style="padding:0 20px 8px 20px;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"><tr>' . $productCells . '</tr></table></td></tr>'
        . $catalogButton
        . '<tr><td align="center" style="padding:20px 28px;border-top:1px solid #e6e9f2;color:#667085;font-size:12px;line-height:18px;">Rodeo Import · Sistema de Catálogos B2B<br>Este correo fue generado automáticamente.</td></tr>'
        . '</table></td></tr></table></body></html>';
}

function campaign_public_url(int $campaignId, string $item = ''): string
{
    $base = rtrim((string) catalog_config('campaigns.promo_base_url', 'https://rodeoimportzl.com/catalogos/promo.php'), '?');
    $url = $base . '?campaign_id=' . $campaignId;
    if ($item !== '') {
        $url .= '&item=' . rawurlencode($item);
    }
    return $url;
}

function campaign_active_products(int $campaignId): array
{
    return array_values(array_filter(campaign_products($campaignId), static fn(array $product): bool => campaign_product_available($product)));
}

function campaign_fetch_public(int $campaignId): ?array
{
    $campaign = campaign_fetch($campaignId);
    if (!$campaign || !in_array((string) ($campaign['status'] ?? ''), ['approved', 'sent'], true) || (int) ($campaign['active'] ?? 1) !== 1) {
        return null;
    }
    return $campaign;
}

function campaign_send_to_recipient(array $campaign, array $products, array $recipient): string
{
    $result = campaign_send_to_recipient_detail($campaign, $products, $recipient);
    return $result['status'];
}

function campaign_send_to_recipient_detail(array $campaign, array $products, array $recipient): array
{
    $email = trim((string) ($recipient['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $status = campaign_record_send_result((int) $campaign['id'], (int) ($recipient['id'] ?? 0), $email, 'failed', 'Correo invalido.');
        return ['status' => $status, 'message' => 'Correo invalido.'];
    }

    try {
        $subject = (string) ($campaign['subject'] ?: $campaign['title']);
        $status = send_notification_mail(
            $subject,
            campaign_plain_body($campaign, $products),
            [$email],
            null,
            [],
            campaign_html_body($campaign, $products)
        );
        $errorMessage = $status === 'sent' ? '' : campaign_last_notification_response($email, $subject);
        if ($errorMessage === '') {
            $errorMessage = 'No se pudo enviar.';
        }
        $recordedStatus = campaign_record_send_result((int) $campaign['id'], (int) ($recipient['id'] ?? 0), $email, $status === 'sent' ? 'sent' : 'failed', $errorMessage);
        return [
            'status' => $recordedStatus,
            'message' => $recordedStatus === 'sent' ? 'Correo enviado correctamente.' : $errorMessage,
        ];
    } catch (Throwable $exception) {
        $message = campaign_public_error_message($exception->getMessage());
        $status = campaign_record_send_result((int) $campaign['id'], (int) ($recipient['id'] ?? 0), $email, 'failed', $message);
        return ['status' => $status, 'message' => $message];
    }
}

function campaign_last_notification_response(string $email, string $subject): string
{
    if (!catalog_table_exists('notifications_log')) {
        return '';
    }
    $statement = db()->prepare(
        'SELECT response_message
         FROM notifications_log
         WHERE recipient = :recipient AND subject = :subject
         ORDER BY id DESC
         LIMIT 1'
    );
    $statement->execute([
        'recipient' => $email,
        'subject' => $subject,
    ]);
    return campaign_public_error_message((string) ($statement->fetchColumn() ?: ''));
}

function campaign_public_error_message(string $message): string
{
    $message = trim($message);
    if ($message === '') {
        return '';
    }
    $message = preg_replace('/(password|passwd|clave|secret|token)\s*[:=]\s*\S+/i', '$1: [oculto]', $message) ?? $message;
    return substr($message, 0, 220);
}

function campaign_record_send_result(int $campaignId, int $recipientId, string $email, string $status, string $errorMessage = ''): string
{
    if ($recipientId > 0) {
        db()->prepare(
            'UPDATE campaign_recipients
             SET status = :status, error_message = :error_message, sent_at = :sent_at
             WHERE id = :id'
        )->execute([
            'status' => $status,
            'error_message' => substr($errorMessage, 0, 255),
            'sent_at' => $status === 'sent' ? date('Y-m-d H:i:s') : null,
            'id' => $recipientId,
        ]);
    }
    db()->prepare(
        'INSERT INTO campaign_logs (campaign_id, email, status, error_message)
         VALUES (:campaign_id, :email, :status, :error_message)'
    )->execute([
        'campaign_id' => $campaignId,
        'email' => $email,
        'status' => $status === 'sent' ? 'sent' : 'failed',
        'error_message' => substr($errorMessage, 0, 255),
    ]);
    return $status === 'sent' ? 'sent' : 'failed';
}

function campaign_send_pending_batch(int $campaignId, ?int $sellerId = null, ?int $limit = null): array
{
    $campaign = campaign_fetch($campaignId);
    if (!$campaign || (string) $campaign['status'] === 'draft') {
        return ['sent' => 0, 'failed' => 0];
    }
    $limit = $limit ?? campaign_batch_limit();
    $whereSeller = $sellerId !== null ? ' AND seller_id = :seller_id' : '';
    $statement = db()->prepare(
        'SELECT * FROM campaign_recipients
         WHERE campaign_id = :campaign_id AND status = "pending"' . $whereSeller . '
         ORDER BY id ASC
         LIMIT ' . max(1, min(50, $limit))
    );
    $params = ['campaign_id' => $campaignId];
    if ($sellerId !== null) {
        $params['seller_id'] = $sellerId;
    }
    $statement->execute($params);
    $recipients = $statement->fetchAll();
    $products = campaign_products($campaignId);
    $sent = 0;
    $failed = 0;
    foreach ($recipients as $recipient) {
        $result = campaign_send_to_recipient($campaign, $products, $recipient);
        $result === 'sent' ? $sent++ : $failed++;
    }
    if ($sent > 0 && campaign_pending_count($campaignId) === 0) {
        db()->prepare('UPDATE campaigns SET status = "sent", sent_at = NOW() WHERE id = :id')->execute(['id' => $campaignId]);
    }
    return ['sent' => $sent, 'failed' => $failed];
}

function campaign_pending_count(int $campaignId): int
{
    $statement = db()->prepare('SELECT COUNT(*) FROM campaign_recipients WHERE campaign_id = :campaign_id AND status = "pending"');
    $statement->execute(['campaign_id' => $campaignId]);
    return (int) $statement->fetchColumn();
}

function campaign_add_recipient(int $campaignId, string $type, string $name, string $email, string $phone = '', ?int $sellerId = null): void
{
    $email = trim($email);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return;
    }
    db()->prepare(
        'INSERT INTO campaign_recipients (campaign_id, recipient_type, name, email, phone, seller_id, status)
         SELECT :campaign_id, :recipient_type, :name, :email, :phone, :seller_id, "pending"
         WHERE NOT EXISTS (
             SELECT 1 FROM campaign_recipients WHERE campaign_id = :campaign_id_check AND email = :email_check
         )'
    )->execute([
        'campaign_id' => $campaignId,
        'recipient_type' => $type,
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'seller_id' => $sellerId,
        'campaign_id_check' => $campaignId,
        'email_check' => $email,
    ]);
}

function campaign_catalog_product_suggestions(int $limit = 80): array
{
    if (!catalog_table_exists('catalogs')) {
        return [];
    }
    $catalogs = db()->query('SELECT * FROM catalogs WHERE status = "active" ORDER BY updated_at DESC, id DESC LIMIT 5')->fetchAll();
    $products = [];
    foreach ($catalogs as $catalog) {
        $json = catalog_json_data((string) ($catalog['catalog_json_path'] ?? ''));
        $rows = is_array($json['catalog'] ?? null) ? $json['catalog'] : [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $item = trim((string) ($row['item'] ?? ''));
            if ($item === '') {
                continue;
            }
            $media = is_array($row['media'] ?? null) ? $row['media'] : [];
            $products[] = [
                'item' => $item,
                'description' => (string) ($row['description'] ?? $item),
                'price' => (string) ($row['price'] ?? ''),
                'regular_price' => parse_decimal($row['price'] ?? 0),
                'discount_type' => 'none',
                'discount_value' => 0,
                'active' => 1,
                'image_url' => productImageUrl(['item_code' => $item], build_catalog_product_image_map($catalog), (string) ($catalog['public_url'] ?? '')),
                'catalog_url' => (string) ($catalog['public_url'] ?? ''),
                'catalog_title' => (string) ($catalog['title'] ?? ''),
            ];
            if (count($products) >= $limit) {
                return $products;
            }
        }
    }
    return $products;
}
