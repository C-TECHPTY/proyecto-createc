<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/footer.php';

sa_require_login();

if (!sa_table_exists('saas_publish_logs')) {
    sa_header('Publicaciones SaaS', 'publish_logs.php');
    ?>
    <section class="panel">
        <h3>Migracion pendiente</h3>
        <p class="muted">Ejecuta <code>database/20260509_saas_publish_logs.sql</code> para activar el monitoreo de publicaciones SaaS.</p>
    </section>
    <?php
    sa_footer();
    exit;
}

$companyId = max(0, (int) ($_GET['company_id'] ?? 0));
$status = trim((string) ($_GET['status'] ?? ''));
$search = trim((string) ($_GET['q'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;
$isCsv = isset($_GET['export']) && $_GET['export'] === 'csv';

$filters = [];
$params = [];

if ($companyId > 0) {
    $filters[] = 'l.company_id = :company_id';
    $params['company_id'] = $companyId;
}
if (in_array($status, ['validated', 'warning', 'legacy', 'blocked'], true)) {
    $filters[] = 'l.status = :status';
    $params['status'] = $status;
}
if ($search !== '') {
    $filters[] = '(l.catalog_slug LIKE :search OR l.catalog_title LIKE :search OR l.device_id LIKE :search OR l.publish_url LIKE :search)';
    $params['search'] = '%' . $search . '%';
}

$whereSql = $filters ? 'WHERE ' . implode(' AND ', $filters) : '';
$companies = sa_company_options();

if ($isCsv) {
    $rows = sa_fetch_publish_logs($whereSql, $params, 5000, 0);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="saas_publish_logs.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['created_at','company_slug','license_id','device_id','app_version','endpoint','catalog_slug','catalog_title','publish_url','status','allowed_publish','warning_message','ip_address']);
    foreach ($rows as $row) {
        fputcsv($output, [
            $row['created_at'] ?? '',
            $row['company_slug'] ?? '',
            $row['license_id'] ?? '',
            $row['device_id'] ?? '',
            $row['app_version'] ?? '',
            $row['endpoint'] ?? '',
            $row['catalog_slug'] ?? '',
            $row['catalog_title'] ?? '',
            $row['publish_url'] ?? '',
            $row['status'] ?? '',
            (string) ($row['allowed_publish'] ?? ''),
            $row['warning_message'] ?? '',
            $row['ip_address'] ?? '',
        ]);
    }
    fclose($output);
    exit;
}

$countStatement = sa_db()->prepare("SELECT COUNT(*) FROM saas_publish_logs l {$whereSql}");
$countStatement->execute($params);
$totalRows = (int) $countStatement->fetchColumn();
$totalPages = max(1, (int) ceil($totalRows / $perPage));
$rows = sa_fetch_publish_logs($whereSql, $params, $perPage, $offset);

sa_header('Publicaciones SaaS', 'publish_logs.php');
?>
<section class="panel">
    <div class="toolbar">
        <div>
            <h3>Monitoreo de publicaciones</h3>
            <p class="muted">Lectura operativa de intentos SaaS. No bloquea publicaciones ni modifica catalogos.</p>
        </div>
        <a class="button button--ghost" href="<?= sa_e('publish_logs.php?' . http_build_query(array_merge($_GET, ['export' => 'csv']))) ?>">Exportar CSV</a>
    </div>
    <form method="get" class="form-grid">
        <label class="field">Empresa
            <select name="company_id">
                <option value="0">Todas</option>
                <?php foreach ($companies as $company): ?>
                    <option value="<?= (int) $company['id'] ?>" <?= $companyId === (int) $company['id'] ? 'selected' : '' ?>><?= sa_e($company['company_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field">Estado
            <select name="status">
                <option value="">Todos</option>
                <?php foreach (['validated' => 'Validated', 'warning' => 'Warning', 'legacy' => 'Legacy', 'blocked' => 'Blocked futuro'] as $value => $label): ?>
                    <option value="<?= sa_e($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= sa_e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field field--full">Buscar catalogo o dispositivo
            <input name="q" value="<?= sa_e($search) ?>" placeholder="catalogo, device_id o URL">
        </label>
        <div class="actions">
            <button class="button" type="submit">Filtrar</button>
            <a class="button button--ghost" href="publish_logs.php">Limpiar</a>
        </div>
    </form>
</section>

<section class="panel">
    <div class="toolbar">
        <h3>Ultimos registros</h3>
        <span class="muted"><?= (int) $totalRows ?> registros</span>
    </div>
    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Empresa</th>
                <th>Licencia</th>
                <th>Equipo</th>
                <th>Endpoint</th>
                <th>Catalogo</th>
                <th>URL</th>
                <th>Estado</th>
                <th>IP / Mensaje</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
            <tr>
                <td><?= sa_e($row['created_at']) ?></td>
                <td>
                    <strong><?= sa_e($row['company_name'] ?: '-') ?></strong><br>
                    <span class="muted"><?= sa_e($row['company_slug'] ?: '-') ?></span>
                </td>
                <td>
                    <?= sa_e($row['license_id'] ?: '-') ?><br>
                    <span class="muted"><?= sa_e(sa_short_hash($row['license_key_hash'] ?? '')) ?></span>
                </td>
                <td><?= sa_e($row['device_id'] ?: '-') ?><br><span class="muted"><?= sa_e($row['app_version'] ?: '-') ?></span></td>
                <td><?= sa_e($row['endpoint']) ?></td>
                <td><?= sa_e($row['catalog_slug'] ?: '-') ?><br><span class="muted"><?= sa_e($row['catalog_title'] ?: '') ?></span></td>
                <td><?= $row['publish_url'] ? '<a href="' . sa_e($row['publish_url']) . '" target="_blank">Abrir</a>' : '-' ?></td>
                <td><?= sa_publish_status_badge((string) $row['status']) ?><br><span class="muted">allowed: <?= (int) $row['allowed_publish'] === 1 ? 'si' : 'no' ?></span></td>
                <td><?= sa_e($row['ip_address'] ?: '-') ?><br><span class="muted"><?= sa_e($row['warning_message'] ?: '') ?></span></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="9">Sin registros para los filtros actuales.</td></tr><?php endif; ?>
        </tbody>
    </table>
    <div class="actions" style="margin-top:16px;">
        <?php if ($page > 1): ?><a class="button button--ghost" href="<?= sa_e('publish_logs.php?' . http_build_query(array_merge($_GET, ['page' => $page - 1]))) ?>">Anterior</a><?php endif; ?>
        <span class="muted">Pagina <?= (int) $page ?> de <?= (int) $totalPages ?></span>
        <?php if ($page < $totalPages): ?><a class="button button--ghost" href="<?= sa_e('publish_logs.php?' . http_build_query(array_merge($_GET, ['page' => $page + 1]))) ?>">Siguiente</a><?php endif; ?>
    </div>
</section>
<?php sa_footer(); ?>

<?php
function sa_fetch_publish_logs(string $whereSql, array $params, int $limit, int $offset): array
{
    $sql = "SELECT l.*, c.company_name
            FROM saas_publish_logs l
            LEFT JOIN sa_companies c ON c.id = l.company_id
            {$whereSql}
            ORDER BY l.created_at DESC, l.id DESC
            LIMIT :limit OFFSET :offset";
    $statement = sa_db()->prepare($sql);
    foreach ($params as $key => $value) {
        $statement->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
    $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
    $statement->execute();
    return $statement->fetchAll();
}

function sa_short_hash(string $hash): string
{
    $hash = trim($hash);
    return $hash === '' ? '-' : substr($hash, 0, 10) . '...';
}

function sa_publish_status_badge(string $status): string
{
    $class = match ($status) {
        'validated' => 'badge badge--ok',
        'warning' => 'badge badge--warn',
        'blocked' => 'badge badge--danger',
        default => 'badge',
    };
    return '<span class="' . $class . '">' . sa_e($status) . '</span>';
}
