<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
admin_require_login(['admin', 'sales']);

const CATALOG_UPDATE_MAX_BYTES = 5242880;

$catalogId = (int) ($_GET['catalog_id'] ?? $_POST['catalog_id'] ?? 0);
$catalog = $catalogId > 0 ? admin_update_fetch_catalog($catalogId) : null;
$logsReady = admin_table_exists('catalog_product_update_logs');
$preview = null;
$applyResult = null;
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_abort();
    $action = (string) ($_POST['action'] ?? 'preview');
    try {
        if (!$catalog) {
            throw new RuntimeException('Catalogo no encontrado.');
        }
        if (resolve_catalog_status($catalog) !== 'active') {
            throw new RuntimeException('Solo se pueden actualizar catalogos activos.');
        }
        if (!$logsReady) {
            throw new RuntimeException('Falta ejecutar la migracion catalog_product_update_logs.');
        }
        if ($action === 'preview') {
            $preview = admin_update_preview_from_upload($catalog);
        } elseif ($action === 'apply') {
            $applyResult = admin_update_apply_confirmed($catalog);
        }
    } catch (Throwable $exception) {
        $errorMessage = $exception->getMessage();
    }
}

admin_header('Actualizar datos de catalogo', 'catalogos.php');
?>
<section class="card">
    <div class="toolbar">
        <strong>Actualizar datos comerciales</strong>
        <a class="button" href="catalogos.php">Volver</a>
    </div>

    <?php if (!$catalog): ?>
        <p class="muted">Catalogo no encontrado.</p>
    <?php elseif (!$logsReady): ?>
        <p class="muted">Falta la tabla <code>catalog_product_update_logs</code>. Ejecuta <code>hosting/sql/20260505_catalog_product_update_logs.sql</code>.</p>
    <?php else: ?>
        <p class="muted">Catalogo: <strong><?= html_escape($catalog['title'] ?? '') ?></strong></p>
        <p class="muted">Esta fase solo actualiza datos comerciales por ITEM. No sube imagenes, no crea galerias, no elimina productos y no cambia el diseño.</p>

        <?php if ($errorMessage !== ''): ?>
            <div class="notice notice--warning" style="margin:16px 0;"><?= html_escape($errorMessage) ?></div>
        <?php endif; ?>

        <?php if ($applyResult): ?>
            <div class="notice notice--success" style="margin:16px 0;">
                Actualizacion aplicada. Actualizados: <?= (int) $applyResult['updated_count'] ?> · Agotados: <?= (int) $applyResult['out_of_stock_count'] ?> · No encontrados: <?= (int) $applyResult['not_found_count'] ?>
            </div>
            <p class="muted">Backup creado: <code><?= html_escape($applyResult['backup_path'] ?? '') ?></code></p>
        <?php endif; ?>

        <?php if ($preview): ?>
            <div class="metrics-grid" style="margin:18px 0;">
                <div class="metric-card"><span>Filas leidas</span><strong><?= (int) $preview['total_rows'] ?></strong></div>
                <div class="metric-card"><span>Productos encontrados</span><strong><?= (int) $preview['matched_count'] ?></strong></div>
                <div class="metric-card"><span>Serian actualizados</span><strong><?= (int) $preview['updated_count'] ?></strong></div>
                <div class="metric-card"><span>Quedarian agotados</span><strong><?= (int) $preview['out_of_stock_count'] ?></strong></div>
                <div class="metric-card"><span>No encontrados</span><strong><?= (int) $preview['not_found_count'] ?></strong></div>
                <div class="metric-card"><span>Errores</span><strong><?= (int) $preview['error_count'] ?></strong></div>
            </div>

            <?php if (!empty($preview['errors'])): ?>
                <div class="notice notice--warning" style="margin-bottom:16px;">
                    <?= html_escape(implode(' ', array_slice($preview['errors'], 0, 6))) ?>
                </div>
            <?php endif; ?>

            <div class="table-wrap" style="margin-bottom:18px;">
                <table>
                    <thead><tr><th>ITEM</th><th>Estado</th><th>Descripcion</th><th>Precio</th><th>Disp.</th><th>Empaque</th></tr></thead>
                    <tbody>
                    <?php foreach (array_slice($preview['sample'], 0, 30) as $row): ?>
                        <tr>
                            <td><?= html_escape($row['item'] ?? '') ?></td>
                            <td><?= html_escape($row['status'] ?? '') ?></td>
                            <td><?= html_escape($row['description'] ?? '') ?></td>
                            <td><?= html_escape($row['price'] ?? '') ?></td>
                            <td><?= html_escape($row['available'] ?? '') ?></td>
                            <td><?= html_escape($row['package'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ((int) $preview['matched_count'] > 0): ?>
                <form method="post" onsubmit="return confirm('Confirmas aplicar esta actualizacion comercial al catalogo publicado?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="apply">
                    <input type="hidden" name="catalog_id" value="<?= (int) $catalog['id'] ?>">
                    <input type="hidden" name="preview_token" value="<?= html_escape($preview['preview_token']) ?>">
                    <button class="button--primary" type="submit">Confirmar actualizacion</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>

        <form class="form-grid" method="post" enctype="multipart/form-data" style="margin-top:18px;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="preview">
            <input type="hidden" name="catalog_id" value="<?= (int) $catalog['id'] ?>">
            <label class="wide">
                <span>Archivo CSV</span>
                <input type="file" name="catalog_data_file" accept=".csv,text/csv" required>
            </label>
            <label class="wide check-row">
                <input type="checkbox" name="mark_missing_out_of_stock" value="1">
                <span>Marcar como agotados los productos que no vienen en el archivo.</span>
            </label>
            <div class="wide"><button class="button--primary" type="submit">Vista previa</button></div>
        </form>
    <?php endif; ?>
</section>
<?php admin_footer(); ?>

<?php
function admin_update_fetch_catalog(int $catalogId): ?array
{
    if (!admin_table_exists('catalogs')) return null;
    $stmt = db()->prepare('SELECT * FROM catalogs WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $catalogId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function admin_update_preview_from_upload(array $catalog): array
{
    if (empty($_FILES['catalog_data_file']) || !is_array($_FILES['catalog_data_file'])) {
        throw new RuntimeException('Debes subir un archivo CSV.');
    }
    $file = $_FILES['catalog_data_file'];
    if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('No se pudo recibir el archivo.');
    }
    if ((int) ($file['size'] ?? 0) <= 0 || (int) $file['size'] > CATALOG_UPDATE_MAX_BYTES) {
        throw new RuntimeException('El archivo debe pesar menos de 5 MB.');
    }
    $originalName = basename((string) ($file['name'] ?? 'datos.csv'));
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($extension !== 'csv') {
        throw new RuntimeException('En esta fase solo se permite CSV. XLSX queda para una fase posterior.');
    }
    $rows = admin_update_parse_csv((string) $file['tmp_name']);
    $markMissing = isset($_POST['mark_missing_out_of_stock']);
    return admin_update_build_preview($catalog, $rows, $originalName, $markMissing);
}

function admin_update_parse_csv(string $path): array
{
    $handle = fopen($path, 'rb');
    if (!$handle) throw new RuntimeException('No se pudo abrir el CSV.');
    $firstLine = fgets($handle);
    if ($firstLine === false) {
        fclose($handle);
        throw new RuntimeException('El CSV esta vacio.');
    }
    $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
    rewind($handle);
    $headers = fgetcsv($handle, 0, $delimiter);
    if (!is_array($headers)) {
        fclose($handle);
        throw new RuntimeException('No se pudo leer el encabezado del CSV.');
    }
    $headers = array_map('admin_update_normalize_column', $headers);
    $rows = [];
    while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
        if (!array_filter($data, static fn($value): bool => trim((string) $value) !== '')) continue;
        $row = [];
        foreach ($headers as $index => $header) {
            if ($header === '') continue;
            $row[$header] = trim((string) ($data[$index] ?? ''));
        }
        $rows[] = $row;
    }
    fclose($handle);
    return $rows;
}

function admin_update_build_preview(array $catalog, array $rows, string $filename, bool $markMissing): array
{
    $jsonPath = admin_update_catalog_json_full_path($catalog);
    $json = admin_update_read_catalog_json($jsonPath);
    $products =& admin_update_products_ref($json);
    $productIndex = [];
    foreach ($products as $idx => $product) {
        $item = admin_update_item_key((string) ($product['item'] ?? ''));
        if ($item !== '') $productIndex[$item] = $idx;
    }
    $required = ['ITEM', 'DESCRIPCION', 'DISPONIBLE', 'PRECIO', 'EMPAQUE', 'UM', 'CTN', 'CBARRA'];
    $availableColumns = [];
    foreach ($rows[0] ?? [] as $column => $_) $availableColumns[] = $column;
    $errors = [];
    foreach ($required as $column) {
        if (!in_array($column, $availableColumns, true)) $errors[] = 'Falta columna: ' . $column . '.';
    }
    $matchedItems = [];
    $sample = [];
    $matched = $updated = $outOfStock = $notFound = 0;
    foreach ($rows as $row) {
        $item = admin_update_item_key((string) ($row['ITEM'] ?? ''));
        if ($item === '') {
            $errors[] = 'Fila sin ITEM.';
            continue;
        }
        if (!array_key_exists($item, $productIndex)) {
            $notFound++;
            $sample[] = admin_update_preview_sample($row, 'no encontrado');
            continue;
        }
        $matched++;
        $updated++;
        $matchedItems[$item] = true;
        if (admin_update_available_number((string) ($row['DISPONIBLE'] ?? '')) < 1) $outOfStock++;
        $sample[] = admin_update_preview_sample($row, 'actualizaria');
    }
    if ($markMissing) {
        foreach ($productIndex as $item => $_idx) {
            if (!isset($matchedItems[$item])) $outOfStock++;
        }
    }
    $previewToken = bin2hex(random_bytes(16));
    $preview = [
        'preview_token' => $previewToken,
        'catalog_id' => (int) $catalog['id'],
        'filename' => $filename,
        'mark_missing_out_of_stock' => $markMissing,
        'rows' => $rows,
        'total_rows' => count($rows),
        'matched_count' => $matched,
        'updated_count' => $updated,
        'out_of_stock_count' => $outOfStock,
        'not_found_count' => $notFound,
        'error_count' => count($errors),
        'errors' => $errors,
        'sample' => $sample,
    ];
    admin_update_write_preview($previewToken, $preview);
    return $preview;
}

function admin_update_apply_confirmed(array $catalog): array
{
    $token = preg_replace('/[^a-f0-9]/', '', strtolower((string) ($_POST['preview_token'] ?? '')));
    $preview = admin_update_read_preview($token);
    if (!$preview || (int) ($preview['catalog_id'] ?? 0) !== (int) $catalog['id']) {
        throw new RuntimeException('La vista previa vencio o no pertenece a este catalogo.');
    }
    if (!empty($preview['errors'])) {
        throw new RuntimeException('Corrige los errores de columnas antes de aplicar.');
    }
    $jsonPath = admin_update_catalog_json_full_path($catalog);
    $json = admin_update_read_catalog_json($jsonPath);
    $backupPath = admin_update_backup_catalog_json($jsonPath, (string) ($catalog['slug'] ?? 'catalogo'));
    $products =& admin_update_products_ref($json);
    $productIndex = [];
    foreach ($products as $idx => $product) {
        $item = admin_update_item_key((string) ($product['item'] ?? ''));
        if ($item !== '') $productIndex[$item] = $idx;
    }
    $matchedItems = [];
    $updated = $outOfStock = $notFound = 0;
    foreach ((array) ($preview['rows'] ?? []) as $row) {
        $item = admin_update_item_key((string) ($row['ITEM'] ?? ''));
        if ($item === '' || !array_key_exists($item, $productIndex)) {
            if ($item !== '') $notFound++;
            continue;
        }
        $idx = $productIndex[$item];
        $available = admin_update_available_number((string) ($row['DISPONIBLE'] ?? ''));
        $products[$idx]['description'] = admin_update_clean_text((string) ($row['DESCRIPCION'] ?? $products[$idx]['description'] ?? ''));
        $products[$idx]['shortDescription'] = $products[$idx]['description'];
        $products[$idx]['price'] = admin_update_format_price((string) ($row['PRECIO'] ?? $products[$idx]['price'] ?? ''));
        $products[$idx]['available'] = (string) max(0, $available);
        $products[$idx]['outOfStock'] = $available > 0 ? 0 : 1;
        $products[$idx]['agotado'] = $available > 0 ? 0 : 1;
        $products[$idx]['package'] = admin_update_clean_text((string) ($row['EMPAQUE'] ?? $products[$idx]['package'] ?? ''));
        $products[$idx]['empaque'] = $products[$idx]['package'];
        $products[$idx]['packageLabel'] = $products[$idx]['package'];
        $products[$idx]['packageQty'] = max(1, admin_update_available_number($products[$idx]['package']));
        $products[$idx]['um'] = admin_update_clean_text((string) ($row['UM'] ?? $products[$idx]['um'] ?? ''));
        $products[$idx]['saleUnit'] = $products[$idx]['um'] ?: ($products[$idx]['saleUnit'] ?? 'bulto');
        $products[$idx]['ctn'] = admin_update_clean_text((string) ($row['CTN'] ?? $products[$idx]['ctn'] ?? ''));
        $products[$idx]['barcode'] = admin_update_clean_text((string) ($row['CBARRA'] ?? $products[$idx]['barcode'] ?? ''));
        $matchedItems[$item] = true;
        $updated++;
        if ($available < 1) $outOfStock++;
    }
    if (!empty($preview['mark_missing_out_of_stock'])) {
        foreach ($productIndex as $item => $idx) {
            if (isset($matchedItems[$item])) continue;
            $products[$idx]['available'] = '0';
            $products[$idx]['outOfStock'] = 1;
            $products[$idx]['agotado'] = 1;
            $outOfStock++;
        }
    }
    admin_update_write_catalog_json($jsonPath, $json);
    if (admin_column_exists('catalogs', 'api_payload')) {
        $updatedSet = admin_column_exists('catalogs', 'updated_at') ? ', updated_at = NOW()' : '';
        db()->prepare('UPDATE catalogs SET api_payload = :payload' . $updatedSet . ' WHERE id = :id')->execute([
            'payload' => json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'id' => (int) $catalog['id'],
        ]);
    }
    db()->prepare(
        'INSERT INTO catalog_product_update_logs (catalog_id, admin_user_id, filename, total_rows, matched_count, updated_count, out_of_stock_count, not_found_count, error_count)
         VALUES (:catalog_id, :admin_user_id, :filename, :total_rows, :matched_count, :updated_count, :out_of_stock_count, :not_found_count, :error_count)'
    )->execute([
        'catalog_id' => (int) $catalog['id'],
        'admin_user_id' => current_user()['id'] ?? null,
        'filename' => (string) ($preview['filename'] ?? ''),
        'total_rows' => (int) ($preview['total_rows'] ?? 0),
        'matched_count' => (int) ($preview['matched_count'] ?? 0),
        'updated_count' => $updated,
        'out_of_stock_count' => $outOfStock,
        'not_found_count' => $notFound,
        'error_count' => (int) ($preview['error_count'] ?? 0),
    ]);
    admin_update_delete_preview($token);
    audit_log('catalog.products_updated_from_csv', 'catalogs', (int) $catalog['id'], ['updated' => $updated, 'backup' => $backupPath]);
    return ['updated_count' => $updated, 'out_of_stock_count' => $outOfStock, 'not_found_count' => $notFound, 'backup_path' => $backupPath];
}

function admin_update_catalog_json_full_path(array $catalog): string
{
    $relative = trim((string) ($catalog['catalog_json_path'] ?? ''));
    if ($relative === '') throw new RuntimeException('El catalogo no tiene ruta catalog_json_path.');
    $baseDir = dirname(__DIR__);
    $fullPath = $baseDir . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
    $realBase = realpath($baseDir);
    $realDir = realpath(dirname($fullPath));
    if (!$realBase || !$realDir || strpos($realDir, $realBase) !== 0 || !is_file($fullPath)) {
        throw new RuntimeException('No se encontro catalog.json dentro del hosting permitido.');
    }
    return $fullPath;
}

function admin_update_read_catalog_json(string $path): array
{
    $decoded = json_decode((string) file_get_contents($path), true);
    if (!is_array($decoded)) throw new RuntimeException('catalog.json no es valido.');
    return $decoded;
}

function &admin_update_products_ref(array &$json): array
{
    if (isset($json['catalog']) && is_array($json['catalog'])) return $json['catalog'];
    if (isset($json['metadata']['catalog']) && is_array($json['metadata']['catalog'])) return $json['metadata']['catalog'];
    throw new RuntimeException('No se encontro arreglo catalog en catalog.json.');
}

function admin_update_backup_catalog_json(string $jsonPath, string $slug): string
{
    $backupDir = dirname($jsonPath) . DIRECTORY_SEPARATOR . 'backups';
    if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
        throw new RuntimeException('No se pudo crear carpeta de backups.');
    }
    $backupPath = $backupDir . DIRECTORY_SEPARATOR . 'backup_' . date('Ymd_His') . '.json';
    if (!copy($jsonPath, $backupPath)) throw new RuntimeException('No se pudo crear backup del catalogo.');
    return str_replace('\\', '/', str_replace(dirname(__DIR__) . DIRECTORY_SEPARATOR, '', $backupPath));
}

function admin_update_write_catalog_json(string $path, array $json): void
{
    $encoded = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($encoded === false || file_put_contents($path, $encoded) === false) {
        throw new RuntimeException('No se pudo escribir catalog.json actualizado.');
    }
}

function admin_update_preview_dir(): string
{
    $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'catalog_updates';
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) throw new RuntimeException('No se pudo preparar carpeta temporal.');
    return $dir;
}

function admin_update_write_preview(string $token, array $preview): void
{
    file_put_contents(admin_update_preview_dir() . DIRECTORY_SEPARATOR . $token . '.json', json_encode($preview, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function admin_update_read_preview(string $token): ?array
{
    if ($token === '') return null;
    $path = admin_update_preview_dir() . DIRECTORY_SEPARATOR . $token . '.json';
    if (!is_file($path)) return null;
    $decoded = json_decode((string) file_get_contents($path), true);
    return is_array($decoded) ? $decoded : null;
}

function admin_update_delete_preview(string $token): void
{
    $path = admin_update_preview_dir() . DIRECTORY_SEPARATOR . $token . '.json';
    if (is_file($path)) @unlink($path);
}

function admin_update_normalize_column(string $value): string
{
    $value = strtoupper(trim(str_replace("\xEF\xBB\xBF", '', $value)));
    $value = strtr($value, ['Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ñ'=>'N']);
    $value = preg_replace('/[^A-Z0-9]+/', '', $value) ?? $value;
    return match ($value) {
        'DESCRIPCION', 'DESCRIPCIONPRODUCTO', 'PRODUCTO', 'NOMBRE' => 'DESCRIPCION',
        'DISP', 'DISPONIBLE', 'STOCK', 'EXISTENCIA' => 'DISPONIBLE',
        'PRECIO', 'PRICE', 'PVP' => 'PRECIO',
        'CODIGOBARRAS', 'CODBARRA', 'CB' => 'CBARRA',
        default => $value,
    };
}

function admin_update_item_key(string $value): string
{
    return strtoupper(preg_replace('/[^A-Z0-9-]+/i', '', trim($value)) ?? '');
}

function admin_update_clean_text(string $value): string
{
    return trim(strip_tags($value));
}

function admin_update_available_number(string $value): int
{
    $normalized = preg_replace('/[^0-9.-]+/', '', str_replace(',', '.', $value)) ?? '';
    return (int) floor(max(0, (float) $normalized));
}

function admin_update_format_price(string $value): string
{
    $normalized = preg_replace('/[^0-9.,-]+/', '', $value) ?? '';
    $normalized = str_replace(',', '.', $normalized);
    $number = (float) $normalized;
    return $number > 0 ? '$' . number_format($number, 2, '.', '') : trim($value);
}

function admin_update_preview_sample(array $row, string $status): array
{
    return [
        'item' => (string) ($row['ITEM'] ?? ''),
        'status' => $status,
        'description' => (string) ($row['DESCRIPCION'] ?? ''),
        'price' => (string) ($row['PRECIO'] ?? ''),
        'available' => (string) ($row['DISPONIBLE'] ?? ''),
        'package' => (string) ($row['EMPAQUE'] ?? ''),
    ];
}
