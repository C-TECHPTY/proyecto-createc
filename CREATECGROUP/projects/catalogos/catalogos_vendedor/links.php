<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
vendor_require_panel_login();

$sellerId = (int) (vendor_current_user()['seller_id'] ?? 0);
$links = [];
$schemaReady = vendor_table_exists('catalog_share_links') && vendor_column_exists('catalog_share_links', 'seller_id');
if ($schemaReady) {
    $hasCatalogs = vendor_table_exists('catalogs');
    $hasClients = vendor_table_exists('clients') && vendor_column_exists('catalog_share_links', 'client_id');
    $catalogJoin = $hasCatalogs ? 'LEFT JOIN catalogs c ON c.id = l.catalog_id' : '';
    $catalogTitle = $hasCatalogs && vendor_column_exists('catalogs', 'title') ? 'c.title' : "''";
    $catalogUrl = $hasCatalogs && vendor_column_exists('catalogs', 'public_url') ? 'c.public_url' : "''";
    $clientJoin = $hasClients ? 'LEFT JOIN clients cl ON cl.id = l.client_id' : '';
    $clientName = $hasClients ? 'cl.business_name' : "''";
    $orderBy = vendor_column_exists('catalog_share_links', 'created_at') ? 'l.created_at DESC' : 'l.id DESC';
    $stmt = db()->prepare(
        "SELECT l.*, {$catalogTitle} AS catalog_title, {$catalogUrl} AS public_url, {$clientName} AS client_name
         FROM catalog_share_links l
         {$catalogJoin}
         {$clientJoin}
         WHERE l.seller_id = :seller_id
         ORDER BY {$orderBy}"
    );
    $stmt->execute(['seller_id' => $sellerId]);
    $links = $stmt->fetchAll();
}

vendor_header('Mis links', 'links.php');
?>
<section class="card">
    <div class="toolbar"><strong>Links compartidos</strong></div>
    <?php if (!$schemaReady): ?>
        <p class="muted">El modulo de links requiere ejecutar la migracion B2B.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Catalogo</th><th>Cliente</th><th>Aperturas</th><th>Ultimo acceso</th><th>URL</th></tr></thead>
            <tbody>
            <?php foreach ($links as $link): ?>
                <?php $shareUrl = !empty($link['public_url']) ? rtrim((string) $link['public_url'], '/') . '/?token=' . $link['token'] : ''; ?>
                <tr>
                    <td><?= html_escape($link['catalog_title']) ?></td>
                    <td><?= html_escape($link['client_name']) ?></td>
                    <td><?= (int) $link['open_count'] ?></td>
                    <td><?= html_escape($link['last_opened_at']) ?></td>
                    <td>
                        <?php if ($shareUrl !== ''): ?>
                            <div class="toolbar__actions">
                                <button type="button" class="button vendor-copy-link" data-link="<?= html_escape($shareUrl) ?>">Copiar</button>
                                <a class="button" href="<?= html_escape($shareUrl) ?>" target="_blank" rel="noreferrer">Ver</a>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>
<script>
document.addEventListener("click", async (event) => {
  const button = event.target.closest(".vendor-copy-link");
  if (!button) return;
  const link = button.getAttribute("data-link") || "";
  if (!link) return;
  try {
    if (navigator.clipboard && window.isSecureContext) {
      await navigator.clipboard.writeText(link);
    } else {
      const input = document.createElement("input");
      input.value = link;
      document.body.appendChild(input);
      input.select();
      document.execCommand("copy");
      input.remove();
    }
    const previous = button.textContent;
    button.textContent = "Se ha copiado el link";
    button.disabled = true;
    window.setTimeout(() => {
      button.textContent = previous;
      button.disabled = false;
    }, 1800);
  } catch (error) {
    const previous = button.textContent;
    button.textContent = "No se pudo copiar";
    window.setTimeout(() => {
      button.textContent = previous;
    }, 1800);
  }
});
</script>
<?php vendor_footer(); ?>
