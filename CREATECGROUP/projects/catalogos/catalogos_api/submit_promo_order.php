<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/campaign_helpers.php';

$payload = read_json_input();
$campaignId = max(0, (int) ($payload['campaign_id'] ?? 0));
$campaign = $campaignId > 0 ? campaign_fetch_public($campaignId) : null;
$items = is_array($payload['items'] ?? null) ? $payload['items'] : [];
$contactName = trim((string) ($payload['contact_name'] ?? ''));
$contactPhone = trim((string) ($payload['contact_phone'] ?? ''));

if (!campaign_promo_schema_ready()) {
    json_response(['success' => false, 'message' => 'El módulo de pedidos promocionales no está activo. Ejecuta la migración SQL.'], 503);
}

if (!$campaign || !$items || $contactName === '' || $contactPhone === '') {
    json_response(['success' => false, 'message' => 'Datos incompletos o promoción no disponible.'], 422);
}

$productsByItem = [];
foreach (campaign_active_products($campaignId) as $product) {
    $productsByItem[strtoupper((string) $product['item'])] = $product;
}

$normalizedItems = [];
$totalRegular = 0.0;
$totalPromo = 0.0;
foreach ($items as $item) {
    if (!is_array($item)) continue;
    $itemCode = strtoupper(trim((string) ($item['item'] ?? '')));
    $quantity = max(0, parse_decimal($item['quantity'] ?? 0));
    if ($itemCode === '' || $quantity <= 0 || empty($productsByItem[$itemCode])) {
        continue;
    }
    $product = $productsByItem[$itemCode];
    $regularPrice = (float) ($product['regular_price'] ?? 0);
    $promoPrice = (float) ($product['promo_price'] ?? $regularPrice);
    if ($promoPrice <= 0 || !campaign_product_available($product)) {
        continue;
    }
    $lineRegular = $quantity * $regularPrice;
    $linePromo = $quantity * $promoPrice;
    $totalRegular += $lineRegular;
    $totalPromo += $linePromo;
    $normalizedItems[] = [
        'campaign_product_id' => (int) $product['id'],
        'item' => (string) $product['item'],
        'description' => (string) $product['description'],
        'quantity' => $quantity,
        'regular_price' => $regularPrice,
        'promo_price' => $promoPrice,
        'line_regular_total' => $lineRegular,
        'line_promo_total' => $linePromo,
        'line_savings' => max(0, $lineRegular - $linePromo),
    ];
}

if (!$normalizedItems) {
    json_response(['success' => false, 'message' => 'Promoción no disponible.'], 422);
}

$companyName = trim((string) ($payload['company_name'] ?? ''));
$contactEmail = trim((string) ($payload['contact_email'] ?? ''));
$pdo = db();
$pdo->beginTransaction();
try {
    $pdo->prepare(
        'INSERT INTO campaign_promo_orders (campaign_id, campaign_title, company_name, contact_name, contact_email, contact_phone, total_regular, total_promo, total_savings)
         VALUES (:campaign_id, :campaign_title, :company_name, :contact_name, :contact_email, :contact_phone, :total_regular, :total_promo, :total_savings)'
    )->execute([
        'campaign_id' => $campaignId,
        'campaign_title' => (string) $campaign['title'],
        'company_name' => $companyName,
        'contact_name' => $contactName,
        'contact_email' => filter_var($contactEmail, FILTER_VALIDATE_EMAIL) ? $contactEmail : '',
        'contact_phone' => $contactPhone,
        'total_regular' => $totalRegular,
        'total_promo' => $totalPromo,
        'total_savings' => max(0, $totalRegular - $totalPromo),
    ]);
    $orderId = (int) $pdo->lastInsertId();
    $insertItem = $pdo->prepare(
        'INSERT INTO campaign_promo_order_items (promo_order_id, campaign_product_id, item, description, quantity, regular_price, promo_price, line_regular_total, line_promo_total, line_savings)
         VALUES (:promo_order_id, :campaign_product_id, :item, :description, :quantity, :regular_price, :promo_price, :line_regular_total, :line_promo_total, :line_savings)'
    );
    foreach ($normalizedItems as $row) {
        $row['promo_order_id'] = $orderId;
        $insertItem->execute($row);
    }
    $pdo->commit();
} catch (Throwable $exception) {
    $pdo->rollBack();
    json_response(['success' => false, 'message' => 'No se pudo guardar el pedido promocional.'], 500);
}

$plain = campaign_promo_order_plain_body($campaign, $normalizedItems, $companyName, $contactName, $contactPhone, $totalPromo);
$html = campaign_promo_order_html_body($campaign, $normalizedItems, $companyName, $contactName, $contactPhone, $totalRegular, $totalPromo);
$recipients = array_filter(array_merge(
    preg_split('/[,;]+/', app_setting('mail_sales', '')) ?: [],
    [sales_contact_info()['email'] ?? '']
));
$mailStatus = 'pending';
if ($recipients) {
    $mailStatus = send_notification_mail('Pedido promocional - ' . (string) $campaign['title'], $plain, $recipients, null, [], $html);
}
db()->prepare('UPDATE campaign_promo_orders SET email_status = :email_status, status = :status WHERE id = :id')->execute([
    'email_status' => $mailStatus === 'sent' ? 'sent' : 'failed',
    'status' => $mailStatus === 'sent' ? 'sent' : 'failed',
    'id' => $orderId,
]);

json_response([
    'success' => true,
    'message' => 'Pedido promocional enviado correctamente.',
    'order_id' => $orderId,
]);

function campaign_promo_order_plain_body(array $campaign, array $items, string $company, string $contact, string $phone, float $total): string
{
    $lines = [
        'Pedido generado desde campaña promocional',
        'Campaña: ' . (string) $campaign['title'],
        'Empresa: ' . ($company !== '' ? $company : 'No definido'),
        'Contacto: ' . $contact,
        'Teléfono: ' . $phone,
        '',
        'Detalle:',
    ];
    foreach ($items as $item) {
        $lines[] = sprintf('%s | %s | Cantidad %s | Promo %s | Total %s', $item['item'], $item['description'], format_plain_number((float) $item['quantity']), campaign_money((float) $item['promo_price']), campaign_money((float) $item['line_promo_total']));
    }
    $lines[] = '';
    $lines[] = 'Total promocional: ' . campaign_money($total);
    return implode("\n", $lines);
}

function campaign_promo_order_html_body(array $campaign, array $items, string $company, string $contact, string $phone, float $regular, float $promo): string
{
    $rows = '';
    foreach ($items as $item) {
        $rows .= '<tr><td style="padding:8px;border-bottom:1px solid #e6e9f2;">' . campaign_safe_text($item['item']) . '</td>'
            . '<td style="padding:8px;border-bottom:1px solid #e6e9f2;">' . campaign_safe_text($item['description']) . '</td>'
            . '<td style="padding:8px;border-bottom:1px solid #e6e9f2;text-align:center;">' . html_escape(format_plain_number((float) $item['quantity'])) . '</td>'
            . '<td style="padding:8px;border-bottom:1px solid #e6e9f2;text-align:right;">' . html_escape(campaign_money((float) $item['regular_price'])) . '</td>'
            . '<td style="padding:8px;border-bottom:1px solid #e6e9f2;text-align:right;font-weight:700;color:#2c4695;">' . html_escape(campaign_money((float) $item['promo_price'])) . '</td></tr>';
    }
    return '<!doctype html><html><body style="margin:0;background:#f4f6f8;font-family:Arial,Helvetica,sans-serif;">'
        . '<table width="100%" cellspacing="0" cellpadding="0" style="background:#f4f6f8;"><tr><td align="center" style="padding:24px;">'
        . '<table width="720" cellspacing="0" cellpadding="0" style="max-width:720px;width:100%;background:#fff;border-radius:10px;overflow:hidden;">'
        . '<tr><td style="background:#2c4695;color:#fff;padding:24px;"><h1 style="margin:0;">Pedido generado desde campaña promocional</h1><p>' . campaign_safe_text($campaign['title']) . '</p></td></tr>'
        . '<tr><td style="padding:20px;"><p><strong>Empresa:</strong> ' . campaign_safe_text($company) . '<br><strong>Contacto:</strong> ' . campaign_safe_text($contact) . '<br><strong>Teléfono:</strong> ' . campaign_safe_text($phone) . '</p>'
        . '<table width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;"><tr><th align="left">Item</th><th align="left">Descripción</th><th>Cantidad</th><th align="right">Normal</th><th align="right">Promo</th></tr>' . $rows . '</table>'
        . '<p style="text-align:right;color:#667085;text-decoration:line-through;">Normal: ' . html_escape(campaign_money($regular)) . '</p>'
        . '<h2 style="text-align:right;color:#2c4695;">Total promo: ' . html_escape(campaign_money($promo)) . '</h2>'
        . '<p style="text-align:right;">Ahorro estimado: ' . html_escape(campaign_money(max(0, $regular - $promo))) . '</p>'
        . '</td></tr></table></td></tr></table></body></html>';
}
