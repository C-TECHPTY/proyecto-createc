<?php
declare(strict_types=1);

require dirname(__DIR__) . '/catalogos_api/bootstrap.php';
require dirname(__DIR__) . '/catalogos_api/campaign_helpers.php';

$campaignId = max(0, (int) ($_GET['campaign_id'] ?? 0));
$selectedItem = trim((string) ($_GET['item'] ?? ''));
$campaign = $campaignId > 0 ? campaign_fetch_public($campaignId) : null;
$products = $campaign ? campaign_active_products($campaignId) : [];
if ($selectedItem !== '') {
    usort($products, static fn(array $a, array $b): int => strcasecmp((string) $a['item'], $selectedItem) === 0 ? -1 : 1);
}
$availableItems = array_map(static fn(array $product): string => (string) $product['item'], $products);
$hasSelectedItem = $selectedItem === '' || in_array($selectedItem, $availableItems, true);
$unavailable = !$campaign || !$products || !$hasSelectedItem;
$logoUrl = campaign_logo_url();
$fallbackImage = campaign_no_image_url();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= html_escape($campaign ? (string) $campaign['title'] : 'Promoción no disponible') ?></title>
    <style>
        :root { --brand:#2c4695; --muted:#667085; --bg:#f4f6f8; }
        * { box-sizing:border-box; }
        body { margin:0; font-family:Arial, Helvetica, sans-serif; background:var(--bg); color:#172033; }
        header { background:var(--brand); color:#fff; padding:26px clamp(16px, 4vw, 42px); }
        header img { max-width:220px; width:60%; height:auto; display:block; }
        main { max-width:1120px; margin:0 auto; padding:24px clamp(14px, 3vw, 28px) 48px; }
        .hero { background:#fff; border-radius:10px; padding:22px; margin-bottom:18px; box-shadow:0 10px 30px rgba(20,30,50,.08); }
        .hero h1 { margin:12px 0 8px; font-size:clamp(26px, 4vw, 42px); }
        .muted { color:var(--muted); line-height:1.5; }
        .grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(250px, 1fr)); gap:16px; }
        .product { background:#fff; border:1px solid #e3e8f2; border-radius:10px; overflow:hidden; }
        .product--featured { outline:3px solid rgba(44,70,149,.2); }
        .product__media { background:#fff; display:flex; align-items:center; justify-content:center; aspect-ratio:1.2; padding:18px; }
        .product__media img { max-width:100%; max-height:190px; object-fit:contain; }
        .product__body { padding:16px; }
        .item { color:var(--brand); font-weight:800; font-size:12px; letter-spacing:.04em; }
        .price-old { color:#98a2b3; text-decoration:line-through; margin-top:10px; }
        .price-new { color:var(--brand); font-size:24px; font-weight:800; margin-top:4px; }
        .badge { display:inline-block; background:#dc2626; color:#fff; font-size:11px; font-weight:800; padding:4px 8px; border-radius:999px; margin-top:8px; }
        .cart { position:sticky; bottom:0; background:#fff; border:1px solid #d9deea; border-radius:10px; padding:16px; margin-top:20px; box-shadow:0 -8px 28px rgba(20,30,50,.08); }
        button, .button { border:0; border-radius:8px; background:var(--brand); color:#fff; padding:11px 14px; font-weight:700; cursor:pointer; text-decoration:none; display:inline-block; }
        input { width:100%; padding:10px; border:1px solid #ccd3df; border-radius:7px; }
        .form-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(190px,1fr)); gap:10px; margin-top:12px; }
        .status { margin-top:10px; font-weight:700; }
    </style>
</head>
<body>
<header>
    <?php if ($logoUrl !== ''): ?><img src="<?= html_escape($logoUrl) ?>" alt="RODEO IMPORT"><?php else: ?><strong>RODEO IMPORT</strong><?php endif; ?>
</header>
<main>
<?php if ($unavailable): ?>
    <section class="hero"><h1>Promoción no disponible</h1><p class="muted">La campaña no existe, no está aprobada, el producto no pertenece a la campaña o la promoción venció.</p></section>
<?php else: ?>
    <section class="hero">
        <span class="badge">OFERTA</span>
        <h1><?= html_escape($campaign['title']) ?></h1>
        <p class="muted"><?= nl2br(html_escape((string) $campaign['message'])) ?></p>
    </section>
    <section class="grid">
        <?php foreach ($products as $product): ?>
            <?php
            $isFeatured = $selectedItem !== '' && strcasecmp((string) $product['item'], $selectedItem) === 0;
            $regular = (float) ($product['regular_price'] ?? 0);
            $promo = (float) ($product['promo_price'] ?? $regular);
            ?>
            <article class="product <?= $isFeatured ? 'product--featured' : '' ?>" data-item="<?= html_escape($product['item']) ?>" data-description="<?= html_escape($product['description']) ?>" data-regular="<?= html_escape((string) $regular) ?>" data-promo="<?= html_escape((string) $promo) ?>">
                <div class="product__media"><img src="<?= html_escape(safeImageUrl((string) $product['image_url'], $fallbackImage)) ?>" alt="<?= html_escape($product['item']) ?>"></div>
                <div class="product__body">
                    <div class="item">ITEM <?= html_escape($product['item']) ?></div>
                    <h3><?= html_escape($product['description']) ?></h3>
                    <div class="price-old"><?= html_escape(campaign_money($regular)) ?></div>
                    <div class="price-new"><?= html_escape(campaign_money($promo)) ?></div>
                    <span class="badge">OFERTA</span>
                    <div style="margin-top:12px;display:grid;grid-template-columns:90px 1fr;gap:8px;align-items:center;">
                        <input type="number" min="1" step="1" value="1" aria-label="Cantidad">
                        <button type="button" class="add">Agregar</button>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
    <section class="cart">
        <strong>Resumen de carrito promocional</strong>
        <div id="cartRows" class="muted" style="margin-top:8px;">Sin productos agregados.</div>
        <div class="form-grid">
            <input id="companyName" placeholder="Empresa">
            <input id="contactName" placeholder="Contacto" required>
            <input id="contactEmail" placeholder="Correo" type="email">
            <input id="contactPhone" placeholder="Teléfono" required>
        </div>
        <button id="sendOrder" type="button" style="margin-top:12px;">Enviar pedido</button>
        <div id="status" class="status"></div>
    </section>
<?php endif; ?>
</main>
<script>
(() => {
  const campaignId = <?= (int) $campaignId ?>;
  const storageKey = `promo-cart:${campaignId}`;
  const cart = new Map(JSON.parse(sessionStorage.getItem(storageKey) || "[]"));
  const cartRows = document.getElementById("cartRows");
  const status = document.getElementById("status");

  function save() {
    sessionStorage.setItem(storageKey, JSON.stringify(Array.from(cart.entries())));
    render();
  }
  function render() {
    if (!cartRows) return;
    if (!cart.size) {
      cartRows.textContent = "Sin productos agregados.";
      return;
    }
    let total = 0;
    cartRows.innerHTML = Array.from(cart.values()).map((item) => {
      const line = item.quantity * item.promo_price;
      total += line;
      return `<div>${item.item} · ${item.quantity} · USD ${line.toFixed(2)}</div>`;
    }).join("") + `<strong>Total promo: USD ${total.toFixed(2)}</strong>`;
  }
  document.querySelectorAll(".product").forEach((card) => {
    card.querySelector(".add")?.addEventListener("click", () => {
      const quantity = Math.max(1, Number(card.querySelector("input")?.value || 1));
      const item = card.dataset.item || "";
      cart.set(item, {
        campaign_id: campaignId,
        item,
        description: card.dataset.description || item,
        quantity,
        regular_price: Number(card.dataset.regular || 0),
        promo_price: Number(card.dataset.promo || 0)
      });
      save();
    });
  });
  document.getElementById("sendOrder")?.addEventListener("click", async () => {
    if (!cart.size) {
      status.textContent = "Agrega al menos un producto.";
      return;
    }
    status.textContent = "Enviando pedido...";
    try {
      const response = await fetch("../catalogos_api/submit_promo_order.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          campaign_id: campaignId,
          company_name: document.getElementById("companyName")?.value || "",
          contact_name: document.getElementById("contactName")?.value || "",
          contact_email: document.getElementById("contactEmail")?.value || "",
          contact_phone: document.getElementById("contactPhone")?.value || "",
          items: Array.from(cart.values())
        })
      });
      const result = await response.json().catch(() => ({}));
      if (!response.ok || !result.success) throw new Error(result.message || "No se pudo registrar el pedido.");
      cart.clear();
      save();
      status.textContent = result.message || "Pedido promocional enviado.";
    } catch (error) {
      status.textContent = error.message || "Error enviando pedido.";
    }
  });
  render();
})();
</script>
</body>
</html>
