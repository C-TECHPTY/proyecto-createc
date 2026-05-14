<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$user = current_user();
if (!$user) {
    header('Location: ../catalogos_admin/login.php');
    exit;
}

$userRole = (string) ($user['role'] ?? '');
$userSellerId = (int) ($user['seller_id'] ?? 0);
if (!in_array($userRole, ['admin', 'sales', 'billing', 'operator', 'vendor', 'seller', 'vendedor'], true) && $userSellerId <= 0) {
    http_response_code(403);
    echo 'No tienes permisos para exportar pedidos.';
    exit;
}

$orderId = (int) ($_GET['id'] ?? 0);
$format = strtolower(trim((string) ($_GET['format'] ?? 'csv')));

if ($orderId <= 0) {
    json_response([
        'ok' => false,
        'error' => 'Debes indicar un pedido valido.',
    ], 422);
}

$orderStmt = db()->prepare(
    'SELECT o.*, c.slug AS catalog_slug_ref, c.title AS catalog_title,
            c.public_url, c.catalog_json_path, c.api_payload
     FROM orders o
     LEFT JOIN catalogs c ON c.id = o.catalog_id
     WHERE o.id = :id
     LIMIT 1'
);
$orderStmt->execute(['id' => $orderId]);
$order = $orderStmt->fetch();

if (!$order) {
    json_response([
        'ok' => false,
        'error' => 'Pedido no encontrado.',
    ], 404);
}

if (in_array($userRole, ['vendor', 'seller', 'vendedor'], true) || $userSellerId > 0) {
    if ($userSellerId <= 0 || (int) ($order['seller_id'] ?? 0) !== $userSellerId) {
        http_response_code(403);
        echo 'No tienes permisos para exportar este pedido.';
        exit;
    }
}

$itemsStmt = db()->prepare('SELECT * FROM order_items WHERE order_id = :order_id ORDER BY id ASC');
$itemsStmt->execute(['order_id' => $orderId]);
$rows = hydrate_order_item_image_urls($order, $itemsStmt->fetchAll());

if ($format === 'xlsx') {
    output_order_xlsx($order, $rows);
    exit;
}

if ($format === 'pdf') {
    output_order_printable_html($order, $rows);
    exit;
}

output_order_csv($order, $rows);
exit;

function output_order_csv(array $order, array $rows): void
{
    $filename = sprintf('pedido-%s.csv', safe_filename_part((string) ($order['order_number'] ?? ('PED-' . ($order['id'] ?? 'pedido')))));
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'wb');
    fputcsv($output, ['Pedido', order_number_label($order)]);
    fputcsv($output, ['Catalogo', order_catalog_title($order)]);
    fputcsv($output, ['Empresa', order_company_name($order)]);
    fputcsv($output, ['Contacto', order_contact_name($order)]);
    fputcsv($output, ['Correo', $order['contact_email'] ?? $order['customer_email'] ?? '']);
    fputcsv($output, ['Telefono', $order['contact_phone'] ?? $order['customer_phone'] ?? '']);
    $salesContact = sales_contact_info();
    fputcsv($output, ['Contacto comercial', $salesContact['name']]);
    fputcsv($output, ['Correo comercial', $salesContact['email']]);
    fputcsv($output, ['Telefono comercial', $salesContact['phone']]);
    fputcsv($output, ['Zona / Direccion', $order['address_zone'] ?? '']);
    fputcsv($output, ['Estado', $order['status'] ?? 'new']);
    fputcsv($output, ['Fecha', $order['created_at'] ?? '']);
    fputcsv($output, ['Total', number_format((float) ($order['total'] ?? 0), 2, '.', '')]);
    fputcsv($output, []);
    fputcsv($output, ['URL_IMAGEN', 'ITEM', 'Descripcion', 'Cantidad', 'Unidad de venta', 'Empaque', 'Piezas', 'Precio unitario', 'Total linea']);

    foreach ($rows as $row) {
        fputcsv($output, [
            safeImageUrl((string) ($row['image_url'] ?? ''), 'https://rodeoimportzl.com/catalogos_admin/assets/no-image.png'),
            $row['item_code'] ?? '',
            $row['description'] ?? '',
            format_plain_number((float) ($row['quantity'] ?? 0)),
            $row['sale_unit'] ?? 'unidad',
            trim((string) (($row['package_label'] ?? '') . ' ' . format_plain_number((float) ($row['package_qty'] ?? 0)))),
            format_plain_number((float) ($row['pieces_total'] ?? $row['quantity'] ?? 0)),
            number_format((float) ($row['unit_price'] ?? $row['price'] ?? 0), 2, '.', ''),
            number_format((float) ($row['line_total'] ?? $row['total'] ?? $row['price'] ?? 0), 2, '.', ''),
        ]);
    }

    fclose($output);
}

function output_order_printable_html(array $order, array $rows): void
{
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title><?= html_escape('Pedido ' . order_number_label($order)) ?></title>
        <style>
            body { font-family: Arial, Helvetica, sans-serif; margin: 24px; color: #1d1d1d; }
            h1 { margin-bottom: 8px; }
            .meta { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px 18px; margin-bottom: 24px; }
            table { width: 100%; border-collapse: collapse; }
            th, td { border: 1px solid #d8d8d8; padding: 8px; text-align: left; vertical-align: top; }
            th { background: #f1f1f1; }
            .total { margin-top: 16px; text-align: right; font-size: 18px; font-weight: 700; }
        </style>
    </head>
    <body>
        <h1>Pedido <?= html_escape(order_number_label($order)) ?></h1>
        <div class="meta">
            <div><strong>Catalogo:</strong> <?= html_escape(order_catalog_title($order)) ?></div>
            <div><strong>Estado:</strong> <?= html_escape($order['status'] ?? 'new') ?></div>
            <div><strong>Empresa:</strong> <?= html_escape(order_company_name($order)) ?></div>
            <div><strong>Contacto:</strong> <?= html_escape(order_contact_name($order)) ?></div>
            <div><strong>Correo:</strong> <?= html_escape($order['contact_email'] ?? $order['customer_email'] ?? '') ?></div>
            <div><strong>Telefono:</strong> <?= html_escape($order['contact_phone'] ?? $order['customer_phone'] ?? '') ?></div>
            <?php $salesContact = sales_contact_info(); ?>
            <div><strong>Contacto comercial:</strong> <?= html_escape($salesContact['name']) ?></div>
            <div><strong>Correo comercial:</strong> <?= html_escape($salesContact['email']) ?></div>
            <div><strong>Telefono comercial:</strong> <?= html_escape($salesContact['phone']) ?></div>
            <div><strong>Zona:</strong> <?= html_escape($order['address_zone'] ?? '') ?></div>
            <div><strong>Fecha:</strong> <?= html_escape($order['created_at'] ?? '') ?></div>
        </div>
        <table>
            <thead>
                <tr>
                    <th>ITEM</th>
                    <th>Descripcion</th>
                    <th>Cantidad</th>
                    <th>Unidad</th>
                    <th>Empaque</th>
                    <th>Piezas</th>
                    <th>Precio</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= html_escape($row['item_code'] ?? '') ?></td>
                    <td><?= html_escape($row['description'] ?? '') ?></td>
                    <td><?= html_escape(format_plain_number((float) ($row['quantity'] ?? 0))) ?></td>
                    <td><?= html_escape($row['sale_unit'] ?? 'unidad') ?></td>
                    <td><?= html_escape(trim((string) (($row['package_label'] ?? '') . ' ' . format_plain_number((float) ($row['package_qty'] ?? 0))))) ?></td>
                    <td><?= html_escape(format_plain_number((float) ($row['pieces_total'] ?? $row['quantity'] ?? 0))) ?></td>
                    <td><?= html_escape(number_format((float) ($row['unit_price'] ?? $row['price'] ?? 0), 2, '.', '')) ?></td>
                    <td><?= html_escape(number_format((float) ($row['line_total'] ?? $row['total'] ?? $row['price'] ?? 0), 2, '.', '')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div class="total">Total general: <?= html_escape(number_format((float) ($order['total'] ?? 0), 2, '.', '')) ?></div>
    </body>
    </html>
    <?php
}

function output_order_xlsx(array $order, array $rows): void
{
    if (!class_exists('ZipArchive')) {
        output_order_csv($order, $rows);
        return;
    }

    $filename = sprintf('pedido-%s.xlsx', safe_filename_part(order_number_label($order)));
    $tempFile = tempnam(sys_get_temp_dir(), 'order_xlsx_');
    if ($tempFile === false) {
        output_order_csv($order, $rows);
        return;
    }
    @unlink($tempFile);
    $xlsxPath = $tempFile . '.xlsx';

    $headerRow = 14;
    $dataStartRow = 15;
    $dataEndRow = $dataStartRow + max(count($rows) - 1, 0);
    $totalRow = $dataEndRow + 2;

    if (!build_order_xlsx_file($order, $rows, $xlsxPath)) {
        output_order_csv($order, $rows);
        return;
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . (string) filesize($xlsxPath));
    readfile($xlsxPath);
    @unlink($xlsxPath);
}

function build_sheet_xml(array $order, array $rows, int $headerRow, int $dataEndRow, int $totalRow): string
{
    $cells = [];
    $salesContact = sales_contact_info();
    $metaRows = [
        ['A1', 'Pedido', 'B1', order_number_label($order)],
        ['A2', 'Catalogo', 'B2', order_catalog_title($order)],
        ['A3', 'Empresa', 'B3', order_company_name($order)],
        ['A4', 'Contacto', 'B4', order_contact_name($order)],
        ['A5', 'Correo', 'B5', (string) ($order['contact_email'] ?? $order['customer_email'] ?? '')],
        ['A6', 'Telefono', 'B6', (string) ($order['contact_phone'] ?? $order['customer_phone'] ?? '')],
        ['A7', 'Contacto comercial', 'B7', $salesContact['name']],
        ['A8', 'Correo comercial', 'B8', $salesContact['email']],
        ['A9', 'Telefono comercial', 'B9', $salesContact['phone']],
        ['A10', 'Zona', 'B10', (string) ($order['address_zone'] ?? '')],
        ['A11', 'Estado', 'B11', (string) ($order['status'] ?? 'new')],
        ['A12', 'Fecha', 'B12', (string) ($order['created_at'] ?? '')],
    ];

    foreach ($metaRows as $index => $meta) {
        $cells[] = sheet_row($index + 1, [
            text_cell($meta[0], $meta[1]),
            text_cell($meta[2], $meta[3]),
        ]);
    }

    $headers = ['ITEM', 'Descripcion', 'Cantidad', 'Unidad', 'Empaque', 'Piezas', 'Precio Unitario', 'Total Linea'];
    $headerCells = [];
    foreach (range('A', 'H') as $index => $column) {
        $headerCells[] = text_cell($column . $headerRow, $headers[$index], 1);
    }
    $cells[] = sheet_row($headerRow, $headerCells);

    $rowNumber = $headerRow + 1;
    foreach ($rows as $row) {
        $cells[] = sheet_row($rowNumber, [
            text_cell('A' . $rowNumber, (string) ($row['item_code'] ?? ''), 2),
            text_cell('B' . $rowNumber, (string) ($row['description'] ?? ''), 2),
            number_cell('C' . $rowNumber, (float) ($row['quantity'] ?? 0), 3),
            text_cell('D' . $rowNumber, (string) ($row['sale_unit'] ?? 'unidad'), 2),
            text_cell('E' . $rowNumber, trim((string) (($row['package_label'] ?? '') . ' ' . format_plain_number((float) ($row['package_qty'] ?? 0)))), 2),
            number_cell('F' . $rowNumber, (float) ($row['pieces_total'] ?? $row['quantity'] ?? 0), 3),
            number_cell('G' . $rowNumber, (float) ($row['unit_price'] ?? $row['price'] ?? 0), 4),
            number_cell('H' . $rowNumber, (float) ($row['line_total'] ?? $row['total'] ?? $row['price'] ?? 0), 4),
        ]);
        $rowNumber++;
    }

    $cells[] = sheet_row($totalRow, [
        text_cell('G' . $totalRow, 'Total General', 5),
        number_cell('H' . $totalRow, (float) ($order['total'] ?? 0), 6),
    ]);

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<sheetViews><sheetView workbookViewId="0"/></sheetViews>'
        . '<sheetFormatPr defaultRowHeight="18"/>'
        . '<cols>'
        . '<col min="1" max="1" width="18" customWidth="1"/>'
        . '<col min="2" max="2" width="42" customWidth="1"/>'
        . '<col min="3" max="8" width="16" customWidth="1"/>'
        . '</cols>'
        . '<sheetData>' . implode('', $cells) . '</sheetData>'
        . '<autoFilter ref="A' . $headerRow . ':H' . max($headerRow, $dataEndRow) . '"/>'
        . '</worksheet>';
}

function build_styles_xml(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<numFmts count="1"><numFmt numFmtId="164" formatCode="$#,##0.00"/></numFmts>'
        . '<fonts count="3">'
        . '<font><sz val="11"/><name val="Calibri"/></font>'
        . '<font><sz val="11"/><name val="Calibri"/><b/></font>'
        . '<font><sz val="12"/><name val="Calibri"/><b/><color rgb="FFFFFFFF"/></font>'
        . '</fonts>'
        . '<fills count="4">'
        . '<fill><patternFill patternType="none"/></fill>'
        . '<fill><patternFill patternType="gray125"/></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FF1F3B5F"/><bgColor indexed="64"/></patternFill></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FFF4F7FB"/><bgColor indexed="64"/></patternFill></fill>'
        . '</fills>'
        . '<borders count="2">'
        . '<border><left/><right/><top/><bottom/><diagonal/></border>'
        . '<border><left style="thin"><color auto="1"/></left><right style="thin"><color auto="1"/></right><top style="thin"><color auto="1"/></top><bottom style="thin"><color auto="1"/></bottom><diagonal/></border>'
        . '</borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="7">'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        . '<xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center" wrapText="1"/></xf>'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
        . '<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
        . '<xf numFmtId="0" fontId="1" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
        . '<xf numFmtId="164" fontId="1" fillId="3" borderId="1" xfId="0" applyFont="1" applyNumberFormat="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
        . '</cellXfs>'
        . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        . '</styleSheet>';
}

function build_content_types_xml(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . '</Types>';
}

function build_root_rels_xml(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>';
}

function build_workbook_xml(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="Pedido" sheetId="1" r:id="rId1"/></sheets>'
        . '</workbook>';
}

function build_workbook_rels_xml(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '</Relationships>';
}

function sheet_row(int $rowNumber, array $cells): string
{
    return '<row r="' . $rowNumber . '">' . implode('', $cells) . '</row>';
}

function text_cell(string $cellRef, string $value, int $style = 0): string
{
    return '<c r="' . $cellRef . '" s="' . $style . '" t="inlineStr"><is><t>' . xml_escape($value) . '</t></is></c>';
}

function number_cell(string $cellRef, float $value, int $style = 0): string
{
    return '<c r="' . $cellRef . '" s="' . $style . '"><v>' . number_format($value, 2, '.', '') . '</v></c>';
}

function xml_escape(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function order_number_label(array $order): string
{
    return (string) ($order['order_number'] ?? ('PED-' . ($order['id'] ?? 'pedido')));
}

function order_catalog_title(array $order): string
{
    return (string) (($order['catalog_title'] ?? '') ?: ($order['catalog_slug'] ?? $order['catalog_slug_ref'] ?? ''));
}

function order_company_name(array $order): string
{
    return (string) (($order['company_name'] ?? '') ?: ($order['customer_name'] ?? ''));
}

function order_contact_name(array $order): string
{
    return (string) (($order['contact_name'] ?? '') ?: ($order['customer_name'] ?? ''));
}

function safe_filename_part(string $value): string
{
    $safe = preg_replace('/[^A-Za-z0-9_-]+/', '-', $value) ?: 'pedido';
    return trim($safe, '-') ?: 'pedido';
}
