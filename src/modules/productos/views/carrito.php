<?php
require_once __DIR__ . '/../../../../autoload.php';
iniciarSesionSegura();

// Regla dura: sin cuenta no hay carrito. Si no hay sesión, al login.
requiereLogin();

$idUsuario = (int)$_SESSION['usuario_id'];
$items     = (new CarritoItem())->listar($idUsuario);
$total     = (new CarritoItem())->total($idUsuario);

function imagenPlaceholderCarrito(string $nombre): string
{
    return 'https://placehold.co/200x200/141620/4f6ef7?text=' . rawurlencode($nombre);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tu carrito — SPECTRE</title>
<style>
  :root {
    --bg: #08090d; --bg2: #0e1018; --bg3: #141620;
    --border: rgba(99,102,241,.2); --blue: #4f6ef7; --violet: #7c3aed;
    --grad: linear-gradient(135deg, #4f6ef7, #7c3aed);
    --text: #e8eaf0; --muted: #6b7280; --radius: 10px;
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { background: var(--bg); color: var(--text); font-family: 'Segoe UI', system-ui, sans-serif; }
  a { text-decoration: none; color: inherit; }
  .wrap { max-width: 900px; margin: 0 auto; padding: 2rem 1.5rem 4rem; }
  .top-bar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; }
  .top-bar a.back { font-size: .85rem; color: var(--muted); }
  h1 { font-size: 1.3rem; }
  .item {
    display: grid; grid-template-columns: 70px 1fr auto auto; gap: 1rem; align-items: center;
    background: #0f1117; border: 1px solid var(--border); border-radius: var(--radius);
    padding: .9rem; margin-bottom: .75rem;
  }
  .item img { width: 70px; height: 70px; object-fit: cover; border-radius: 8px; }
  .item-name { font-size: .9rem; font-weight: 600; }
  .item-price { font-size: .78rem; color: var(--muted); margin-top: .2rem; }
  .qty { display: flex; align-items: center; gap: .4rem; }
  .qty button {
    width: 26px; height: 26px; border-radius: 6px; border: 1px solid var(--border);
    background: var(--bg3); color: var(--text); cursor: pointer; font-size: .9rem;
  }
  .qty span { min-width: 22px; text-align: center; font-size: .85rem; }
  .item-total { font-size: .9rem; font-weight: 700; min-width: 90px; text-align: right; }
  .btn-remove { color: #ef4444; font-size: .75rem; background: none; border: none; cursor: pointer; margin-top: .3rem; }
  .empty { text-align: center; padding: 4rem 1rem; color: var(--muted); }
  .summary {
    margin-top: 1.5rem; padding: 1.25rem; background: #0f1117; border: 1px solid var(--border);
    border-radius: var(--radius); display: flex; justify-content: space-between; align-items: center;
  }
  .summary .total-label { font-size: .85rem; color: var(--muted); }
  .summary .total-amount { font-size: 1.4rem; font-weight: 800; background: var(--grad); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
  .btn-checkout { padding: .7rem 1.5rem; background: var(--grad); color: #fff; font-weight: 600; border: none; border-radius: 8px; cursor: pointer; font-size: .9rem; }
  .btn-checkout:disabled { opacity: .5; cursor: not-allowed; }
  .metodo-pago-wrap { display: flex; flex-direction: column; gap: .3rem; }
  .metodo-pago-wrap label { font-size: .8rem; color: var(--muted); }
  .metodo-pago-wrap select { padding: .5rem .7rem; border-radius: 8px; border: 1px solid var(--border, #ddd); font-size: .85rem; background: #fff; }
  .metodo-pago-wrap select:disabled { opacity: .5; cursor: not-allowed; }
  .toast {
    position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%) translateY(20px);
    background: var(--bg3); border: 1px solid var(--border); color: var(--text);
    padding: .7rem 1.2rem; border-radius: 8px; font-size: .85rem;
    opacity: 0; pointer-events: none; transition: opacity .2s, transform .2s;
  }
  .toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
  .toast.error { border-color: rgba(239,68,68,.5); color: #fca5a5; }

  .recibo-overlay {
    display: none; position: fixed; inset: 0; background: rgba(4,5,9,.7);
    z-index: 100; align-items: center; justify-content: center; padding: 1rem;
  }
  .recibo-overlay.open { display: flex; }
  .recibo {
    width: 100%; max-width: 380px; background: var(--bg2);
    border: 1px solid var(--border); border-radius: var(--radius);
    padding: 1.75rem;
  }
  .recibo-check {
    width: 46px; height: 46px; border-radius: 50%;
    background: rgba(16,185,129,.15); border: 1px solid rgba(16,185,129,.4);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.4rem; margin: 0 auto .9rem;
  }
  .recibo h2 { text-align: center; font-size: 1.05rem; margin-bottom: .2rem; }
  .recibo .recibo-sub { text-align: center; font-size: .75rem; color: var(--muted); margin-bottom: 1.2rem; }
  .recibo-badge-sim {
    display: block; text-align: center; font-size: .68rem; font-weight: 700;
    letter-spacing: .06em; text-transform: uppercase; color: #fbbf24;
    background: rgba(245,158,11,.12); border: 1px solid rgba(245,158,11,.3);
    border-radius: 20px; padding: .25rem .6rem; margin: 0 auto 1.2rem; width: fit-content;
  }
  .recibo-linea {
    display: flex; justify-content: space-between; font-size: .8rem;
    padding: .4rem 0; border-bottom: 1px dashed var(--border); color: var(--muted);
  }
  .recibo-linea strong { color: var(--text); font-weight: 500; }
  .recibo-total {
    display: flex; justify-content: space-between; align-items: center;
    margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border);
    font-size: 1rem; font-weight: 800;
  }
  .btn-cerrar-recibo {
    width: 100%; margin-top: 1.4rem; padding: .7rem; background: var(--grad);
    color: #fff; font-weight: 600; border: none; border-radius: 8px; cursor: pointer; font-size: .9rem;
  }
</style>
</head>
<body>
<div class="wrap">
  <div class="top-bar">
    <h1>Tu carrito</h1>
    <a href="/spectre_erp/index.php" class="back">← Seguir comprando</a>
  </div>

  <div id="listaCarrito">
    <?php if (empty($items)): ?>
      <div class="empty" id="estadoVacio">
        <p>Tu carrito está vacío.</p>
      </div>
    <?php else: ?>
      <?php foreach ($items as $it): ?>
        <div class="item" data-id="<?= (int)$it['id_producto'] ?>" data-precio="<?= (float)$it['precio'] ?>">
          <img src="<?= htmlspecialchars($it['imagen'] ?: imagenPlaceholderCarrito($it['nombre'])) ?>" alt="">
          <div>
            <div class="item-name"><?= htmlspecialchars($it['nombre']) ?></div>
            <div class="item-price">S/ <?= number_format((float)$it['precio'], 2) ?> c/u</div>
            <button class="btn-remove" onclick="quitar(<?= (int)$it['id_producto'] ?>)">Quitar</button>
          </div>
          <div class="qty">
            <button onclick="cambiarCantidad(<?= (int)$it['id_producto'] ?>, -1)">−</button>
            <span class="qty-value"><?= (int)$it['cantidad'] ?></span>
            <button onclick="cambiarCantidad(<?= (int)$it['id_producto'] ?>, 1)">+</button>
          </div>
          <div class="item-total">S/ <?= number_format((float)$it['precio'] * (int)$it['cantidad'], 2) ?></div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="summary">
    <div>
      <div class="total-label">Total</div>
      <div class="total-amount" id="totalCarrito">S/ <?= number_format($total, 2) ?></div>
    </div>
    <div class="metodo-pago-wrap">
      <label for="metodoPago">Método de pago (simulado)</label>
      <select id="metodoPago" <?= empty($items) ? 'disabled' : '' ?>>
        <option value="Efectivo simulado">Efectivo simulado</option>
        <option value="Tarjeta simulada">Tarjeta simulada</option>
        <option value="Yape simulado">Yape simulado</option>
        <option value="Plin simulado">Plin simulado</option>
        <option value="Transferencia simulada">Transferencia simulada</option>
      </select>
    </div>
    <button class="btn-checkout" id="btnCheckout" <?= empty($items) ? 'disabled' : '' ?>>
      Finalizar compra
    </button>
  </div>
</div>

<div class="toast" id="toast"></div>

<div class="recibo-overlay" id="reciboOverlay">
  <div class="recibo">
    <div class="recibo-check">✅</div>
    <h2>¡Compra realizada!</h2>
    <p class="recibo-sub">Orden <strong id="reciboNumero">#0</strong> — <span id="reciboFecha"></span></p>
    <span class="recibo-badge-sim">Compra simulada — sin cobro real</span>
    <div id="reciboItems"></div>
    <div class="recibo-total">
      <span>Total</span>
      <span id="reciboTotal">S/ 0.00</span>
    </div>
    <button class="btn-cerrar-recibo" onclick="cerrarRecibo()">Volver a la tienda</button>
  </div>
</div>

<script>
const CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;

function mostrarToast(msg, esError = false) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.toggle('error', esError);
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 2400);
}

async function llamar(accion, body) {
  const datos = new FormData();
  for (const k in body) datos.append(k, body[k]);
  datos.append('csrf_token', CSRF_TOKEN);
  const r = await fetch('/spectre_erp/src/modules/productos/controllers/CarritoController.php?accion=' + accion, {
    method: 'POST', body: datos
  });
  return r.json();
}

function recalcularFila(fila, cantidad) {
  const precio = parseFloat(fila.dataset.precio);
  fila.querySelector('.qty-value').textContent = cantidad;
  fila.querySelector('.item-total').textContent = 'S/ ' + (precio * cantidad).toFixed(2);
}

async function cambiarCantidad(idProducto, delta) {
  const fila = document.querySelector(`.item[data-id="${idProducto}"]`);
  const actual = parseInt(fila.querySelector('.qty-value').textContent, 10);
  const nueva = actual + delta;
  if (nueva <= 0) { return quitar(idProducto); }

  const json = await llamar('actualizar', { id_producto: idProducto, cantidad: nueva });
  if (json.success) {
    recalcularFila(fila, json.cantidad);
    document.getElementById('totalCarrito').textContent = 'S/ ' + Number(json.total).toFixed(2);
  } else {
    mostrarToast(json.message, true);
  }
}

async function quitar(idProducto) {
  const json = await llamar('eliminar', { id_producto: idProducto });
  if (json.success) {
    document.querySelector(`.item[data-id="${idProducto}"]`)?.remove();
    document.getElementById('totalCarrito').textContent = 'S/ ' + Number(json.total).toFixed(2);
    if (!document.querySelector('.item')) {
      document.getElementById('listaCarrito').innerHTML = '<div class="empty">Tu carrito está vacío.</div>';
      document.getElementById('btnCheckout').disabled = true;
    }
  } else {
    mostrarToast(json.message, true);
  }
}

let procesandoCheckout = false;

document.getElementById('btnCheckout')?.addEventListener('click', async () => {
  if (procesandoCheckout) return;
  procesandoCheckout = true;

  const btn = document.getElementById('btnCheckout');
  const textoOriginal = btn.textContent;
  btn.disabled = true;
  btn.textContent = 'Procesando…';

  try {
    const datos = new FormData();
    datos.append('csrf_token', CSRF_TOKEN);
    datos.append('metodo_pago', document.getElementById('metodoPago')?.value || 'Efectivo simulado');

    const r = await fetch('/spectre_erp/src/modules/ventas/controllers/VentaController.php?accion=checkout', {
      method: 'POST', body: datos
    });
    const json = await r.json();

    if (!json.success) {
      mostrarToast(json.message, true);
      btn.disabled = false;
      btn.textContent = textoOriginal;
      procesandoCheckout = false;
      return;
    }

    mostrarRecibo(json);

    // Vaciar la vista del carrito, ya que el checkout lo vació en BD.
    document.getElementById('listaCarrito').innerHTML = '<div class="empty">Tu carrito está vacío.</div>';
    document.getElementById('totalCarrito').textContent = 'S/ 0.00';
    btn.disabled = true;
    btn.textContent = textoOriginal;

  } catch (err) {
    mostrarToast('No se pudo conectar con el servidor.', true);
    btn.disabled = false;
    btn.textContent = textoOriginal;
  }

  procesandoCheckout = false;
});

function mostrarRecibo(json) {
  document.getElementById('reciboNumero').textContent = '#' + json.id_venta;
  document.getElementById('reciboFecha').textContent = new Date().toLocaleString('es-PE');
  document.getElementById('reciboTotal').textContent = 'S/ ' + Number(json.total).toFixed(2);

  document.getElementById('reciboItems').innerHTML = (json.items || []).map(it => `
    <div class="recibo-linea">
      <span><strong>${it.cantidad}×</strong> ${escapeHtmlCarrito(it.nombre)}</span>
      <span>S/ ${Number(it.subtotal).toFixed(2)}</span>
    </div>
  `).join('');

  document.getElementById('reciboOverlay').classList.add('open');
}

function cerrarRecibo() {
  document.getElementById('reciboOverlay').classList.remove('open');
  window.location.href = '/spectre_erp/index.php';
}

function escapeHtmlCarrito(str) {
  const d = document.createElement('div');
  d.textContent = str ?? '';
  return d.innerHTML;
}
</script>
</body>
</html>