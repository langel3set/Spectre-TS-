<?php
require_once __DIR__ . '/../../../../autoload.php';
iniciarSesionSegura();

$busqueda  = trim($_GET['q']   ?? '');
$categoria = trim($_GET['cat'] ?? '');

// Productos reales desde la BD (tabla `productos`, con stock e imagen).
$modeloProducto = new Producto();
$productos      = $modeloProducto->listar($categoria ?: null, $busqueda ?: null);
$conteoCategorias = $modeloProducto->contarPorCategoria();

$logueado  = usuarioLogueado();

$labels_cat = Producto::CATEGORIAS;
$titulo_sec = $busqueda   ? "Resultados para \"$busqueda\""
            : ($categoria ? ($labels_cat[$categoria] ?? $categoria)
            : 'Destacados gaming');

/** Genera una imagen placeholder de marca cuando el producto no tiene foto propia. */
function imagenPlaceholder(string $codigo, string $nombre): string
{
    $texto = $codigo !== '' ? str_replace('-', '+', $codigo) : rawurlencode($nombre);
    return 'https://placehold.co/500x500/141620/4f6ef7?text=' . $texto;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SPECTRE — Hardware Gaming</title>
  <style>
    :root {
      --bg:      #08090d;
      --bg2:     #0e1018;
      --bg3:     #141620;
      --border:  rgba(99,102,241,.2);
      --blue:    #4f6ef7;
      --violet:  #7c3aed;
      --grad:    linear-gradient(135deg, #4f6ef7, #7c3aed);
      --text:    #e8eaf0;
      --muted:   #6b7280;
      --card-bg: #0f1117;
      --radius:  10px;
      --hdr-h:   108px; /* navbar + catbar */
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html { scroll-behavior: smooth; }
    body { background: var(--bg); color: var(--text); font-family: 'Segoe UI', system-ui, sans-serif; min-height: 100vh; }
    a { text-decoration: none; color: inherit; }

    /* ── HEADER ─────────────────────────────────────────────── */
    .site-header {
      position: sticky; top: 0; z-index: 100;
      background: rgba(8,9,13,.95);
      backdrop-filter: blur(12px);
      border-bottom: 1px solid var(--border);
    }
    .hdr-inner {
      display: flex; align-items: center; flex-wrap: wrap; gap: .75rem 1rem;
      max-width: 1400px; margin: 0 auto;
      padding: .7rem 1.5rem;
    }

    /* Logo */
    .site-logo { flex-shrink: 0; display: flex; align-items: center; gap: .5rem; }
    .logo-img  { height: 36px; }
    .logo-fallback {
      display: none; align-items: center;
      font-size: 1.3rem; font-weight: 800; letter-spacing: .06em; color: #fff;
    }
    .logo-fallback span { background: var(--grad); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }

    /* Search */
    .search-bar {
      flex: 1 1 240px; display: flex; min-width: 0; max-width: 620px;
      background: var(--bg3); border: 1px solid var(--border);
      border-radius: 8px; overflow: hidden;
      transition: border-color .2s;
    }
    .search-bar:focus-within { border-color: var(--blue); }
    .search-bar input {
      flex: 1; background: none; border: none; outline: none;
      color: var(--text); padding: .6rem 1rem; font-size: .92rem;
    }
    .search-bar button {
      padding: 0 1rem; background: var(--grad); border: none;
      color: #fff; cursor: pointer; display: flex; align-items: center;
      transition: opacity .2s;
    }
    .search-bar button:hover { opacity: .85; }

    /* Header actions */
    .hdr-actions { display: flex; align-items: center; gap: .5rem; flex-shrink: 0; margin-left: auto; }
    .hdr-link {
      display: flex; align-items: center; gap: .35rem;
      font-size: .82rem; color: var(--muted); padding: .4rem .7rem;
      border-radius: 6px; transition: color .2s, background .2s;
      white-space: nowrap;
    }
    .hdr-link:hover { color: var(--text); background: var(--bg3); }
    .btn-login-hdr {
      padding: .45rem 1.1rem; background: var(--grad);
      color: #fff; font-size: .85rem; font-weight: 600;
      border-radius: 7px; white-space: nowrap;
      transition: opacity .2s;
    }
    .btn-login-hdr:hover { opacity: .85; }

    /* User menu */
    .user-menu { position: relative; }
    .user-btn {
      display: flex; align-items: center; gap: .45rem;
      background: var(--bg3); border: 1px solid var(--border);
      color: var(--text); padding: .35rem .8rem .35rem .5rem;
      border-radius: 7px; cursor: pointer; font-size: .85rem;
      transition: border-color .2s;
    }
    .user-btn:hover { border-color: var(--blue); }
    .user-avatar {
      width: 26px; height: 26px; border-radius: 50%;
      background: var(--grad); display: flex; align-items: center;
      justify-content: center; font-size: .75rem; font-weight: 700; color: #fff;
    }
    .user-dropdown {
      display: none; position: absolute; right: 0; top: calc(100% + 8px);
      background: var(--bg3); border: 1px solid var(--border);
      border-radius: 8px; min-width: 180px; overflow: hidden;
      box-shadow: 0 12px 40px rgba(0,0,0,.5);
    }
    .user-dropdown.open { display: block; }
    .user-dropdown a {
      display: block; padding: .65rem 1rem; font-size: .85rem;
      color: var(--muted); transition: background .15s, color .15s;
    }
    .user-dropdown a:hover { background: var(--bg2); color: var(--text); }

    /* Category bar */
    .cat-bar {
      display: flex; gap: 0; overflow-x: auto; scrollbar-width: none;
      border-top: 1px solid var(--border);
      max-width: 100%;
    }
    .cat-bar::-webkit-scrollbar { display: none; }
    .cat-bar a {
      padding: .5rem 1.1rem; font-size: .78rem; color: var(--muted);
      white-space: nowrap; transition: color .2s, background .2s;
      border-right: 1px solid var(--border);
    }
    .cat-bar a:hover { color: var(--text); background: var(--bg3); }

    /* ── LAYOUT ─────────────────────────────────────────────── */
    .page-wrap {
      display: grid;
      grid-template-columns: 240px 1fr;
      gap: 1.5rem;
      max-width: 1400px; margin: 0 auto;
      padding: 1.5rem 1.5rem 3rem;
    }

    /* ── SIDEBAR / BANNER ──────────────────────────────────── */
    .sidebar { display: flex; flex-direction: column; gap: 1rem; }

    .promo-banner {
      border-radius: var(--radius);
      background: linear-gradient(160deg, #0f1320 0%, #1a1040 100%);
      border: 1px solid rgba(124,58,237,.3);
      padding: 1.5rem;
      display: flex; flex-direction: column; gap: .75rem;
      position: relative; overflow: hidden;
      min-height: 260px;
    }
    .promo-banner::before {
      content: '';
      position: absolute; bottom: -40px; right: -40px;
      width: 180px; height: 180px; border-radius: 50%;
      background: radial-gradient(circle, rgba(124,58,237,.25) 0%, transparent 70%);
      pointer-events: none;
    }
    .promo-tag {
      font-size: .65rem; letter-spacing: .12em; text-transform: uppercase;
      background: var(--grad); -webkit-background-clip: text; -webkit-text-fill-color: transparent;
      font-weight: 700;
    }
    .promo-title {
      font-size: 1.4rem; font-weight: 800; line-height: 1.2; color: #fff;
    }
    .promo-title span { background: var(--grad); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
    .promo-desc { font-size: .8rem; color: var(--muted); line-height: 1.5; }
    .promo-btn {
      display: inline-block; margin-top: auto;
      padding: .55rem 1.2rem; background: var(--grad);
      color: #fff; font-size: .82rem; font-weight: 600;
      border-radius: 7px; width: fit-content;
      transition: opacity .2s;
    }
    .promo-btn:hover { opacity: .85; }

    .sidebar-cats h3 {
      font-size: .7rem; letter-spacing: .1em; text-transform: uppercase;
      color: var(--muted); margin-bottom: .5rem; padding-left: .5rem;
    }
    .sidebar-cats a {
      display: flex; align-items: center; justify-content: space-between;
      padding: .5rem .75rem; border-radius: 7px;
      font-size: .84rem; color: var(--muted);
      transition: background .15s, color .15s;
    }
    .sidebar-cats a:hover { background: var(--bg3); color: var(--text); }
    .sidebar-cats a .count {
      font-size: .7rem; background: var(--bg3); border: 1px solid var(--border);
      padding: .1rem .45rem; border-radius: 20px; color: var(--muted);
    }

    /* ── MAIN CONTENT ──────────────────────────────────────── */
    .main-content { min-width: 0; }

    .sec-header {
      display: flex; align-items: center; justify-content: space-between;
      flex-wrap: wrap; gap: .4rem 1rem;
      margin-bottom: 1rem;
    }
    .sec-title {
      font-size: 1rem; font-weight: 700; color: var(--text);
      display: flex; align-items: center; gap: .5rem;
    }
    .sec-title::before {
      content: '';
      display: block; width: 3px; height: 18px;
      background: var(--grad); border-radius: 2px;
    }
    .sec-count { font-size: .78rem; color: var(--muted); font-weight: 400; }

    /* Product grid */
    .product-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
      align-items: stretch;
      gap: 1rem;
    }

    .prod-card {
      background: var(--card-bg);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      overflow: hidden;
      transition: transform .2s, border-color .2s, box-shadow .2s;
      cursor: pointer;
      display: flex; flex-direction: column;
      height: 100%;
    }
    .prod-card:hover {
      transform: translateY(-3px);
      border-color: rgba(79,110,247,.4);
      box-shadow: 0 8px 30px rgba(79,110,247,.12);
    }

    .prod-img {
      aspect-ratio: 1;
      background: var(--bg3);
      display: flex; align-items: center; justify-content: center;
      position: relative;
    }
    .prod-img svg { opacity: .18; }
    .prod-img img { width: 100%; height: 100%; object-fit: cover; }

    .prod-badge {
      position: absolute; top: 8px; left: 8px;
      font-size: .6rem; font-weight: 700; letter-spacing: .08em;
      text-transform: uppercase; padding: .2rem .5rem;
      border-radius: 4px;
    }
    .prod-badge.hot    { background: #ef4444; color: #fff; }
    .prod-badge.nuevo  { background: #10b981; color: #fff; }
    .prod-badge.oferta { background: var(--violet); color: #fff; }

    .prod-info { padding: .85rem; flex: 1; display: flex; flex-direction: column; gap: .35rem; }
    .prod-name {
      font-size: .82rem; font-weight: 600; color: var(--text); line-height: 1.35;
      display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
      overflow: hidden; min-height: calc(1.35em * 2);
    }
    .prod-desc {
      font-size: .72rem; color: var(--muted); line-height: 1.4;
      display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
      overflow: hidden; min-height: calc(1.4em * 2);
    }
    .prod-price {
      font-size: 1.05rem; font-weight: 800; margin-top: auto; padding-top: .5rem;
      background: var(--grad); -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    }
    .prod-actions { padding: 0 .85rem .85rem; }
    .btn-carrito {
      width: 100%; padding: .5rem; background: rgba(79,110,247,.12);
      border: 1px solid rgba(79,110,247,.3); color: var(--blue);
      border-radius: 7px; font-size: .8rem; font-weight: 600; cursor: pointer;
      transition: background .2s, border-color .2s;
    }
    .btn-carrito:hover { background: rgba(79,110,247,.25); border-color: var(--blue); }

    /* Empty state */
    .empty-state {
      grid-column: 1/-1; text-align: center; padding: 4rem 2rem; color: var(--muted);
    }
    .empty-state h3 { font-size: 1.1rem; color: var(--text); margin-bottom: .5rem; }

    /* Stock */
    .prod-stock { font-size: .7rem; color: #34d399; font-weight: 600; }
    .prod-stock.sin-stock { color: #ef4444; }
    .btn-carrito:disabled { opacity: .5; cursor: not-allowed; }
    .btn-carrito:disabled:hover { background: rgba(79,110,247,.12); border-color: rgba(79,110,247,.3); }

    /* Modal login */
    .modal-overlay {
      display: none; position: fixed; inset: 0; z-index: 200;
      background: rgba(4,5,9,.7); backdrop-filter: blur(4px);
      align-items: center; justify-content: center; padding: 1rem;
    }
    .modal-overlay.open { display: flex; }
    .modal-box {
      background: var(--bg2); border: 1px solid var(--border);
      border-radius: var(--radius); padding: 1.75rem; max-width: 380px; width: 100%;
      box-shadow: 0 20px 60px rgba(0,0,0,.5);
    }
    .modal-box h3 { font-size: 1.05rem; margin-bottom: .5rem; color: #fff; }
    .modal-box p { font-size: .85rem; color: var(--muted); line-height: 1.5; margin-bottom: 1.25rem; }
    .modal-actions { display: flex; gap: .6rem; }
    .btn-modal-primary, .btn-modal-secondary {
      flex: 1; text-align: center; padding: .6rem; border-radius: 7px;
      font-size: .85rem; font-weight: 600; cursor: pointer; border: none;
    }
    .btn-modal-primary { background: var(--grad); color: #fff; }
    .btn-modal-secondary { background: var(--bg3); color: var(--muted); border: 1px solid var(--border); }

    /* Toast */
    .toast {
      position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%) translateY(20px);
      background: var(--bg3); border: 1px solid var(--border); color: var(--text);
      padding: .7rem 1.2rem; border-radius: 8px; font-size: .85rem;
      opacity: 0; pointer-events: none; transition: opacity .2s, transform .2s;
      z-index: 300; box-shadow: 0 10px 30px rgba(0,0,0,.4);
    }
    .toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
    .toast.error { border-color: rgba(239,68,68,.5); color: #fca5a5; }

    /* Mobile category chips (hidden on desktop, shown instead of the sidebar list below 900px) */
    .mobile-cats {
      display: none;
      gap: .5rem; overflow-x: auto; scrollbar-width: none;
      padding-bottom: .25rem; margin-bottom: 1rem;
    }
    .mobile-cats::-webkit-scrollbar { display: none; }
    .mobile-cats a {
      flex: 0 0 auto; white-space: nowrap;
      padding: .4rem .9rem; border-radius: 20px;
      font-size: .78rem; color: var(--muted);
      background: var(--bg3); border: 1px solid var(--border);
    }
    .mobile-cats a .count { color: var(--muted); margin-left: .25rem; }

    /* ── RESPONSIVE ─────────────────────────────────────────── */

    /* Tablet: narrower sidebar instead of a fixed 240px */
    @media (max-width: 1100px) {
      .page-wrap { grid-template-columns: 200px 1fr; gap: 1.25rem; }
    }

    /* Small tablet / large phone: drop the sidebar column, keep categories reachable as chips */
    @media (max-width: 900px) {
      .page-wrap { grid-template-columns: 1fr; padding: 1.25rem 1.25rem 2.5rem; }
      .sidebar   { display: none; }
      .mobile-cats { display: flex; }
      .product-grid { grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); }
    }

    /* Phones: header stacks cleanly, search takes its own row */
    @media (max-width: 640px) {
      .hdr-inner { padding: .6rem 1rem; }
      .site-logo { order: 1; }
      .hdr-actions { order: 2; margin-left: 0; }
      .search-bar { order: 3; flex-basis: 100%; max-width: 100%; }
      .cat-bar a { padding: .5rem .85rem; font-size: .74rem; }
      .page-wrap { padding: 1rem 1rem 2rem; gap: 1rem; }
      .product-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; }
      .prod-name, .prod-desc { -webkit-line-clamp: 2; }
    }

    /* Small phones: one comfortable column, card becomes image-left / info-right */
    @media (max-width: 400px) {
      .product-grid { grid-template-columns: 1fr; }
      .prod-card {
        display: grid;
        grid-template-columns: 110px 1fr;
        grid-template-rows: auto auto;
      }
      .prod-img   { grid-column: 1; grid-row: 1 / 3; aspect-ratio: auto; height: 100%; }
      .prod-info  { grid-column: 2; grid-row: 1; padding: .7rem .7rem .35rem; }
      .prod-actions { grid-column: 2; grid-row: 2; padding: 0 .7rem .7rem; }
      .prod-name, .prod-desc { -webkit-line-clamp: 2; min-height: 0; }
      .hdr-link span:not(.badge) { display: none; }
      .modal-box { padding: 1.25rem; }
    }
  </style>
</head>
<body>

<?php require __DIR__ . '/_navbar.php'; ?>

<div class="page-wrap">

  <!-- Sidebar: banner + categorías -->
  <aside class="sidebar">
    <div class="promo-banner">
      <span class="promo-tag">Temporada gaming 2026</span>
      <h2 class="promo-title">Arma tu <span>setup</span> definitivo</h2>
      <p class="promo-desc">
        GPUs de última generación, monitores OLED y periféricos pro.<br>
        Envíos a todo el país.
      </p>
      <a href="/spectre_erp/index.php?cat=gpus" class="promo-btn">Ver GPUs →</a>
    </div>

    <div class="sidebar-cats">
      <h3>Categorías</h3>
      <?php foreach (Producto::CATEGORIAS as $slug => $label): ?>
        <a href="/spectre_erp/index.php?cat=<?= $slug ?>">
          <?= htmlspecialchars($label) ?>
          <span class="count"><?= $conteoCategorias[$slug] ?? 0 ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </aside>

  <!-- Contenido principal -->
  <main class="main-content">
    <nav class="mobile-cats">
      <?php foreach (Producto::CATEGORIAS as $slug => $label): ?>
        <a href="/spectre_erp/index.php?cat=<?= $slug ?>">
          <?= htmlspecialchars($label) ?><span class="count">(<?= $conteoCategorias[$slug] ?? 0 ?>)</span>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="sec-header">
      <h2 class="sec-title">
        <?= htmlspecialchars($titulo_sec) ?>
        <span class="sec-count"><?= count($productos) ?> productos</span>
      </h2>
    </div>

    <div class="product-grid">
      <?php if (empty($productos)): ?>
        <div class="empty-state">
          <h3>Sin resultados</h3>
          <p>Intenta con otro término o revisa las categorías.</p>
        </div>
      <?php else: ?>
        <?php foreach ($productos as $p):
          $stock = (int)$p['stock'];
          $agotado = $stock <= 0;
          $pocasUnidades = !$agotado && $stock <= (int)$p['stock_minimo'];
          $img = $p['imagen'] ?: imagenPlaceholder($p['codigo'], $p['nombre']);
        ?>
          <div class="prod-card">
            <div class="prod-img">
              <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($p['nombre']) ?>" loading="lazy">
              <?php if ($agotado): ?>
                <span class="prod-badge oferta" style="background:#4b5563">AGOTADO</span>
              <?php elseif ($pocasUnidades): ?>
                <span class="prod-badge hot">¡ÚLTIMAS!</span>
              <?php endif; ?>
            </div>
            <div class="prod-info">
              <div class="prod-name"><?= htmlspecialchars($p['nombre']) ?></div>
              <div class="prod-desc"><?= htmlspecialchars($p['descripcion'] ?? '') ?></div>
              <div class="prod-stock <?= $agotado ? 'sin-stock' : '' ?>">
                <?= $agotado ? 'Sin stock' : 'Quedan ' . $stock . ' unidades' ?>
              </div>
              <div class="prod-price">S/ <?= number_format((float)$p['precio'], 2) ?></div>
            </div>
            <div class="prod-actions">
              <button class="btn-carrito"
                <?= $agotado ? 'disabled' : '' ?>
                onclick="agregarCarrito(<?= (int)$p['id_producto'] ?>, this)">
                <?= $agotado ? 'Sin stock' : '+ Agregar al carrito' ?>
              </button>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </main>

</div>

<!-- Modal: pedir inicio de sesión antes de comprar -->
<div class="modal-overlay" id="modalLogin">
  <div class="modal-box">
    <h3>Inicia sesión para comprar</h3>
    <p>Crea una cuenta o inicia sesión para agregar productos al carrito y completar tu compra.</p>
    <div class="modal-actions">
      <a href="/spectre_erp/src/modules/usuarios/controllers/UserController.php?accion=login" class="btn-modal-primary">Iniciar sesión</a>
      <button class="btn-modal-secondary" onclick="cerrarModalLogin()">Ahora no</button>
    </div>
  </div>
</div>

<!-- Toast de confirmación -->
<div class="toast" id="toast"></div>

<script>
const CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;
const LOGUEADO   = <?= json_encode($logueado) ?>;

function mostrarToast(mensaje, esError = false) {
  const toast = document.getElementById('toast');
  toast.textContent = mensaje;
  toast.classList.toggle('error', esError);
  toast.classList.add('show');
  setTimeout(() => toast.classList.remove('show'), 2600);
}

function abrirModalLogin() {
  document.getElementById('modalLogin').classList.add('open');
}
function cerrarModalLogin() {
  document.getElementById('modalLogin').classList.remove('open');
}

function actualizarContadorCarrito(n) {
  const el = document.getElementById('cartCount');
  if (!el) return;
  el.textContent = n;
  el.style.display = n > 0 ? 'flex' : 'none';
}

async function agregarCarrito(idProducto, btn) {
  if (!LOGUEADO) {
    abrirModalLogin();
    return;
  }

  btn.disabled = true;
  const textoOriginal = btn.textContent;
  btn.textContent = 'Agregando...';

  try {
    const datos = new FormData();
    datos.append('id_producto', idProducto);
    datos.append('cantidad', 1);
    datos.append('csrf_token', CSRF_TOKEN);

    const respuesta = await fetch('/spectre_erp/src/modules/productos/controllers/CarritoController.php?accion=agregar', {
      method: 'POST',
      body: datos
    });
    const json = await respuesta.json();

    if (respuesta.status === 401 || json.login_requerido) {
      abrirModalLogin();
      return;
    }

    mostrarToast(json.message, !json.success);
    if (json.success) {
      actualizarContadorCarrito(json.contador ?? 0);
    }
  } catch (e) {
    mostrarToast('No se pudo conectar con el servidor.', true);
  } finally {
    btn.disabled = false;
    btn.textContent = textoOriginal;
  }
}
</script>

</body>
</html>
