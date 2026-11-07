<?php
require_once __DIR__ . '/../../../../autoload.php';

// Solo personal
requiereLogin();
requiereRol('gerente', 'trabajador');

$csrf = csrf_token();

// Obtener conexión a la BD
$db = getDB();

// --- Productos activos disponibles para la venta ---
$productos = $db->query("
    SELECT id_producto, nombre, codigo, precio
    FROM productos
    WHERE estado = 'activo'
    ORDER BY nombre
")->fetchAll(PDO::FETCH_ASSOC);

// --- Servicios activos disponibles para la venta ---
$servicios = $db->query("
    SELECT id_servicio, nombre, precio, duracion_estimada
    FROM servicios
    WHERE estado = 'activo'
    ORDER BY nombre
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Nueva Venta</title>
    <style>
        body {
            background: #07080c;
            color: #e2e5f0;
            font-family: Arial, sans-serif;
            padding: 2rem;
        }
        .container {
            max-width: 700px;
            margin: 0 auto;
            background: #0f1119;
            padding: 2rem;
            border-radius: 10px;
            border: 1px solid rgba(99,102,241,.15);
        }
        h1 { color: #fff; margin-top: 0; }
        .form-group { margin-bottom: 1.2rem; }
        label { display: block; font-weight: 600; font-size: 0.85rem; color: #5a6280; margin-bottom: 0.3rem; }
        input, select, textarea {
            width: 100%;
            padding: 0.6rem;
            background: #161924;
            border: 1px solid rgba(99,102,241,.15);
            color: #e2e5f0;
            border-radius: 6px;
            font-size: 0.9rem;
        }
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #4f6ef7;
        }
        .producto-row {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        .producto-row input, .producto-row select {
            flex: 1;
            min-width: 120px;
        }
        .btn {
            padding: 0.6rem 1.2rem;
            background: linear-gradient(135deg,#4f6ef7,#7c3aed);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: opacity 0.2s;
        }
        .btn:hover { opacity: 0.85; }
        .btn-secundario {
            background: transparent;
            border: 1px solid rgba(99,102,241,.3);
            color: #5a6280;
        }
        .btn-secundario:hover { background: #161924; color: #e2e5f0; }
        .btn-eliminar {
            background: #ef4444;
            color: white;
            border: none;
            padding: 0.3rem 0.8rem;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-eliminar:hover { background: #dc2626; }
        .tag-tipo {
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .03em;
            padding: 0.25rem 0.55rem;
            border-radius: 999px;
            white-space: nowrap;
        }
        .tag-producto { background: rgba(79,110,247,.12); color: #7c96f9; border: 1px solid rgba(79,110,247,.25); }
        .tag-servicio { background: rgba(124,58,237,.12); color: #a78bfa; border: 1px solid rgba(124,58,237,.25); }
        .btn:disabled, .btn-secundario:disabled { opacity: 0.4; cursor: not-allowed; }
        .acciones { margin-top: 1.5rem; display: flex; gap: 0.8rem; flex-wrap: wrap; }
        .ayuda { font-size: 0.75rem; color: #5a6280; margin-top: 0.2rem; }

        /* Mensajes flash */
        .alert {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }
        .alert-success {
            background: #064e3b;
            border: 1px solid #10b981;
            color: #a7f3d0;
        }
        .alert-danger {
            background: #7f1d1d;
            border: 1px solid #ef4444;
            color: #fca5a5;
        }
        .alert .icon {
            font-size: 1.5rem;
        }
        .alert .mensaje {
            flex: 1;
        }
        .alert .btn-volver {
            background: #10b981;
            color: #fff;
            padding: 0.4rem 1rem;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 600;
            transition: background 0.2s;
        }
        .alert .btn-volver:hover {
            background: #059669;
        }
        /* Ocultar formulario en éxito (opcional) */
        .formulario-oculto {
            display: none;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>➕ Nueva Venta</h1>

    <?php
    // Mostrar mensajes de sesión
    $mensajeExito = $_SESSION['mensaje_exito'] ?? null;
    $mensajeError = $_SESSION['mensaje_error'] ?? null;
    unset($_SESSION['mensaje_exito'], $_SESSION['mensaje_error']);
    ?>

    <?php if ($mensajeExito): ?>
        <div class="alert alert-success">
            <span class="icon">✅</span>
            <span class="mensaje"><?= htmlspecialchars($mensajeExito) ?></span>
            <a href="/spectre_erp/src/modules/general/views/dashboard.php?panel=ventas" class="btn-volver">Volver al dashboard</a>
        </div>
        <?php // Ocultar formulario si se desea (opcional) ?>
        <!-- <style>.formulario { display: none; }</style> -->
    <?php endif; ?>

    <?php if ($mensajeError): ?>
        <div class="alert alert-danger">
            <span class="icon">❌</span>
            <span class="mensaje"><?= htmlspecialchars($mensajeError) ?></span>
        </div>
    <?php endif; ?>

    <form action="/spectre_erp/src/modules/ventas/controllers/VentaController.php?accion=guardar" method="POST" enctype="multipart/form-data" class="formulario">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

        <!-- Cliente (opcional) -->
        <div class="form-group">
            <label>Cliente (opcional)</label>
            <select name="id_cliente">
                <option value="">Sin cliente</option>
                <?php
                $clientes = $db->query("SELECT id_cliente, nombre, apellido, correo FROM clientes ORDER BY nombre")->fetchAll();
                foreach ($clientes as $c) {
                    $nombre = trim($c['nombre'] . ' ' . $c['apellido']);
                    if ($nombre === '') $nombre = $c['correo'];
                    echo "<option value='{$c['id_cliente']}'>" . htmlspecialchars($nombre) . "</option>";
                }
                ?>
            </select>
        </div>

        <!-- Método de pago -->
        <div class="form-group">
            <label>Método de pago</label>
            <select name="metodo_pago">
                <option>Efectivo</option>
                <option>Tarjeta</option>
                <option>Yape</option>
                <option>Plin</option>
                <option>Transferencia</option>
            </select>
        </div>

        <!-- Descripción -->
        <div class="form-group">
            <label>Descripción (opcional)</label>
            <textarea name="descripcion" rows="3" placeholder="Observaciones de la venta..."></textarea>
        </div>

        <!-- Comprobante -->
        <div class="form-group">
            <label>Comprobante (imagen, opcional)</label>
            <input type="file" name="comprobante" accept="image/*,.pdf">
            <div class="ayuda">Puedes subir una foto del comprobante, boleta, factura, etc.</div>
        </div>

        <!-- Productos y Servicios -->
        <div id="items-container">
            <h3 style="margin-top: 1.5rem; color: #e2e5f0;">Productos y servicios</h3>

            <?php if (empty($productos) && empty($servicios)): ?>
                <p class="ayuda">No hay productos ni servicios activos registrados todavía.</p>
            <?php endif; ?>
        </div>

        <div class="acciones" style="margin-top:0;margin-bottom:1.5rem;">
            <button type="button" class="btn btn-secundario" onclick="agregarFila('producto')" <?= empty($productos) ? 'disabled' : '' ?>>+ Agregar producto</button>
            <button type="button" class="btn btn-secundario" onclick="agregarFila('servicio')" <?= empty($servicios) ? 'disabled' : '' ?>>+ Agregar servicio</button>
        </div>

        <!-- Plantillas ocultas: se clonan por JS al agregar una fila -->
        <template id="tpl-producto">
            <div class="producto-row">
                <input type="hidden" name="item_tipo[]" value="producto">
                <select name="item_id[]" required style="flex:2">
                    <option value="">Selecciona un producto…</option>
                    <?php foreach ($productos as $p): ?>
                        <option value="<?= (int)$p['id_producto'] ?>" data-precio="<?= (float)$p['precio'] ?>">
                            <?= htmlspecialchars($p['nombre'] . ' (' . $p['codigo'] . ') — S/ ' . number_format((float)$p['precio'], 2)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="number" name="item_cant[]" placeholder="Cantidad" min="1" step="1" value="1" required style="flex:1">
                <span class="tag-tipo tag-producto">Producto</span>
                <button type="button" class="btn-eliminar" onclick="this.parentElement.remove()">X</button>
            </div>
        </template>

        <template id="tpl-servicio">
            <div class="producto-row">
                <input type="hidden" name="item_tipo[]" value="servicio">
                <select name="item_id[]" required style="flex:2">
                    <option value="">Selecciona un servicio…</option>
                    <?php foreach ($servicios as $s): ?>
                        <option value="<?= (int)$s['id_servicio'] ?>" data-precio="<?= (float)$s['precio'] ?>">
                            <?= htmlspecialchars($s['nombre'] . ' — S/ ' . number_format((float)$s['precio'], 2) . ($s['duracion_estimada'] ? ' (' . $s['duracion_estimada'] . ')' : '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="number" name="item_cant[]" placeholder="Cantidad" min="1" step="1" value="1" required style="flex:1">
                <span class="tag-tipo tag-servicio">Servicio</span>
                <button type="button" class="btn-eliminar" onclick="this.parentElement.remove()">X</button>
            </div>
        </template>

        <!-- Descuento -->
        <div class="form-group" style="border-top:1px solid rgba(99,102,241,.15); padding-top:1.2rem; margin-top:0.5rem;">
            <label>Descuento (opcional)</label>
            <div class="producto-row" style="margin-bottom:0;">
                <select name="descuento_tipo" id="descuento_tipo" style="flex:1;">
                    <option value="ninguno">Sin descuento</option>
                    <option value="porcentaje">Porcentaje (%)</option>
                    <option value="monto">Monto fijo (S/)</option>
                </select>
                <input type="number" name="descuento_valor" id="descuento_valor" min="0" step="0.01" value="0" placeholder="0" style="flex:1;" disabled>
            </div>
            <div class="ayuda">El descuento se aplica sobre el subtotal de productos y servicios seleccionados.</div>
        </div>

        <!-- Resumen de totales (se actualiza en vivo) -->
        <div class="form-group" style="background:#161924; border:1px solid rgba(99,102,241,.15); border-radius:8px; padding:1rem;">
            <div style="display:flex; justify-content:space-between; margin-bottom:0.4rem;">
                <span class="ayuda" style="margin:0;">Subtotal</span>
                <span id="resumen-subtotal" style="color:#e2e5f0; font-weight:600;">S/ 0.00</span>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:0.4rem;">
                <span class="ayuda" style="margin:0;">Descuento</span>
                <span id="resumen-descuento" style="color:#f59e0b; font-weight:600;">- S/ 0.00</span>
            </div>
            <div style="display:flex; justify-content:space-between; border-top:1px solid rgba(99,102,241,.15); padding-top:0.5rem; margin-top:0.3rem;">
                <span style="color:#fff; font-weight:700;">Total a pagar</span>
                <span id="resumen-total" style="color:#4f6ef7; font-weight:700; font-size:1.1rem;">S/ 0.00</span>
            </div>
        </div>

        <div class="acciones">
            <button type="submit" class="btn">Crear Venta</button>
            <a href="/spectre_erp/src/modules/general/views/dashboard.php?panel=ventas" class="btn btn-secundario" style="text-decoration:none; display:inline-block; padding:0.6rem 1.2rem;">Cancelar</a>
        </div>
    </form>
</div>

<script>
function agregarFila(tipo) {
    const tplId = tipo === 'servicio' ? 'tpl-servicio' : 'tpl-producto';
    const tpl = document.getElementById(tplId);
    if (!tpl) return;
    const nodo = tpl.content.cloneNode(true);
    document.getElementById('items-container').appendChild(nodo);
    calcularResumen();
}

function fmtSoles(n) {
    return 'S/ ' + (isFinite(n) ? n : 0).toFixed(2);
}

function calcularSubtotal() {
    let subtotal = 0;
    document.querySelectorAll('#items-container .producto-row').forEach(fila => {
        const select = fila.querySelector('select[name="item_id[]"]');
        const cantidadInput = fila.querySelector('input[name="item_cant[]"]');
        if (!select || !cantidadInput) return;
        const opcion = select.options[select.selectedIndex];
        const precio = opcion ? parseFloat(opcion.dataset.precio || '0') : 0;
        const cantidad = parseInt(cantidadInput.value || '0', 10);
        if (precio > 0 && cantidad > 0) {
            subtotal += precio * cantidad;
        }
    });
    return subtotal;
}

function calcularResumen() {
    const subtotal = calcularSubtotal();
    const tipoDescuento = document.getElementById('descuento_tipo').value;
    const valorDescuentoInput = document.getElementById('descuento_valor');
    let valorDescuento = parseFloat(valorDescuentoInput.value || '0');
    if (valorDescuento < 0) valorDescuento = 0;

    let montoDescuento = 0;
    if (tipoDescuento === 'porcentaje') {
        if (valorDescuento > 100) { valorDescuento = 100; valorDescuentoInput.value = 100; }
        montoDescuento = subtotal * (valorDescuento / 100);
    } else if (tipoDescuento === 'monto') {
        montoDescuento = Math.min(valorDescuento, subtotal);
    }

    const total = Math.max(0, subtotal - montoDescuento);

    document.getElementById('resumen-subtotal').textContent = fmtSoles(subtotal);
    document.getElementById('resumen-descuento').textContent = '- ' + fmtSoles(montoDescuento);
    document.getElementById('resumen-total').textContent = fmtSoles(total);
}

// Habilita/deshabilita el campo de valor de descuento según el tipo elegido
document.getElementById('descuento_tipo').addEventListener('change', function () {
    const input = document.getElementById('descuento_valor');
    if (this.value === 'ninguno') {
        input.disabled = true;
        input.value = 0;
    } else {
        input.disabled = false;
        if (!input.value || parseFloat(input.value) <= 0) input.value = '';
    }
    calcularResumen();
});

document.getElementById('descuento_valor').addEventListener('input', calcularResumen);

// Recalcula el resumen cuando se agregan/quitan filas o cambian cantidades/productos
document.getElementById('items-container').addEventListener('input', calcularResumen);
document.getElementById('items-container').addEventListener('change', calcularResumen);
document.getElementById('items-container').addEventListener('click', (e) => {
    if (e.target.classList.contains('btn-eliminar')) {
        setTimeout(calcularResumen, 0);
    }
});

// Al cargar la página, si hay productos o servicios disponibles,
// agregamos una primera fila automáticamente para no arrancar vacío.
document.addEventListener('DOMContentLoaded', () => {
    const hayProductos = document.getElementById('tpl-producto').content.querySelectorAll('option').length > 1;
    const hayServicios = document.getElementById('tpl-servicio').content.querySelectorAll('option').length > 1;
    if (hayProductos) agregarFila('producto');
    else if (hayServicios) agregarFila('servicio');
    calcularResumen();
});
</script>
</body>
</html>