<?php
require_once __DIR__ . '/../../../../autoload.php';
iniciarSesionSegura();

// Solo personal de venta puede cargar catálogo.
requiereRol('gerente', 'trabajador');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Nuevo producto — SPECTRE</title>
<style>
  :root {
    --bg: #08090d; --bg2: #0e1018; --bg3: #141620;
    --border: rgba(99,102,241,.2); --blue: #4f6ef7; --violet: #7c3aed;
    --grad: linear-gradient(135deg, #4f6ef7, #7c3aed);
    --text: #e8eaf0; --muted: #6b7280; --radius: 10px;
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { background: var(--bg); color: var(--text); font-family: 'Segoe UI', system-ui, sans-serif; min-height: 100vh; display:flex; justify-content:center; padding: 2.5rem 1rem; }
  a { text-decoration:none; color: inherit; }
  .card { width: 100%; max-width: 560px; background: var(--bg2); border: 1px solid var(--border); border-radius: var(--radius); padding: 2rem; }
  h1 { font-size: 1.2rem; margin-bottom: .3rem; }
  .sub { font-size: .8rem; color: var(--muted); margin-bottom: 1.5rem; }
  .row { display: grid; grid-template-columns: 1fr 1fr; gap: .8rem; }
  label { display: block; font-size: .75rem; color: var(--muted); margin: .8rem 0 .3rem; }
  input, select, textarea {
    width: 100%; background: var(--bg3); border: 1px solid var(--border); color: var(--text);
    padding: .55rem .7rem; border-radius: 7px; font-size: .85rem; font-family: inherit;
  }
  input:focus, select:focus, textarea:focus { outline: none; border-color: var(--blue); }
  textarea { resize: vertical; min-height: 70px; }
  .btn { margin-top: 1.5rem; width: 100%; padding: .7rem; background: var(--grad); color: #fff; font-weight: 600; border: none; border-radius: 8px; cursor: pointer; font-size: .9rem; }
  .msg { margin-top: 1rem; font-size: .82rem; padding: .6rem .8rem; border-radius: 7px; display: none; }
  .msg.ok { background: rgba(16,185,129,.12); color: #34d399; border: 1px solid rgba(16,185,129,.3); display: block; }
  .msg.err { background: rgba(239,68,68,.12); color: #fca5a5; border: 1px solid rgba(239,68,68,.3); display: block; }
  .back { display:inline-block; margin-bottom: 1rem; font-size: .8rem; color: var(--muted); }
</style>
</head>
<body>
<div class="card">
  <a href="/spectre_erp/index.php" class="back">← Volver a la tienda</a>
  <h1>Nuevo producto</h1>
  <p class="sub">Se agrega directamente al catálogo con el stock que definas aquí.</p>

  <form id="formProducto">
    <label>Código único</label>
    <input type="text" name="codigo" placeholder="Ej. GPU-5090" required>

    <label>Nombre</label>
    <input type="text" name="nombre" placeholder="Ej. RTX 5090 32GB" required>

    <label>Descripción</label>
    <textarea name="descripcion" placeholder="Detalles cortos para la tarjeta del producto"></textarea>

    <div class="row">
      <div>
        <label>Categoría</label>
        <select name="categoria" required>
          <option value="">Selecciona…</option>
          <?php foreach (Producto::CATEGORIAS as $slug => $label): ?>
            <option value="<?= htmlspecialchars($slug) ?>"><?= htmlspecialchars($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Precio (S/)</label>
        <input type="number" name="precio" step="0.01" min="0" required>
      </div>
    </div>

    <div class="row">
      <div>
        <label>Stock inicial</label>
        <input type="number" name="stock" min="0" step="1" required>
      </div>
      <div>
        <label>Stock mínimo (alerta)</label>
        <input type="number" name="stock_minimo" min="0" step="1" value="0">
      </div>
    </div>

    <label>URL de imagen (opcional)</label>
    <input type="url" name="imagen_url" placeholder="https://...">

    <button type="submit" class="btn">Guardar producto</button>
    <div class="msg" id="msg"></div>
  </form>
</div>

<script>
const CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;

document.getElementById('formProducto').addEventListener('submit', async (e) => {
  e.preventDefault();
  const form = e.target;
  const datos = new FormData(form);
  datos.append('csrf_token', CSRF_TOKEN);

  const msg = document.getElementById('msg');
  msg.className = 'msg';

  try {
    const r = await fetch('/spectre_erp/src/modules/productos/controllers/ProductoController.php?accion=crear', {
      method: 'POST', body: datos
    });
    const json = await r.json();
    msg.textContent = json.message;
    msg.classList.add(json.success ? 'ok' : 'err');
    if (json.success) form.reset();
  } catch (err) {
    msg.textContent = 'No se pudo conectar con el servidor.';
    msg.classList.add('err');
  }
});
</script>
</body>
</html>
