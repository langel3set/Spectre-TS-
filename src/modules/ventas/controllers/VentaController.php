<?php
declare(strict_types=1);

/**
 * src/modules/ventas/controllers/VentaController.php
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);
ob_start();

require_once __DIR__ . '/../../../../autoload.php';

use Dompdf\Dompdf;

set_exception_handler(function (\Throwable $e): void {
    error_log('[VentaController] Excepción no controlada: ' . $e->getMessage());
    while (ob_get_level() > 0) ob_end_clean();
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
});

class VentaController
{
    private Venta $model;

    private const METODOS_PAGO_SIMULADOS = [
        'Efectivo simulado',
        'Tarjeta simulada',
        'Yape simulado',
        'Plin simulado',
        'Transferencia simulado',
    ];

    public function __construct()
    {
        iniciarSesionSegura();
        $this->model = new Venta();
    }

    public function checkout(): void
    {
        $this->soloPost();
        $this->requiereLoginJson();
        $this->verificarCSRFOFallar();

        $metodoPago = trim((string)($_POST['metodo_pago'] ?? ''));
        if (!in_array($metodoPago, self::METODOS_PAGO_SIMULADOS, true)) {
            $metodoPago = self::METODOS_PAGO_SIMULADOS[0];
        }

        $resultado = $this->model->checkout((int)$_SESSION['usuario_id'], $metodoPago);
        $this->json($resultado['ok'], $resultado['message'], [
            'id_venta' => $resultado['id_venta'] ?? null,
            'total'    => $resultado['total'] ?? null,
            'items'    => $resultado['items'] ?? [],
            'debug'    => $resultado['debug'] ?? null,
        ]);
    }

    public function misCompras(): void
    {
        $this->requiereLoginJson();
        $this->json(true, '', ['items' => $this->model->listarPorCliente((int)$_SESSION['usuario_id'])]);
    }

    public function listar(): void
    {
        $this->soloPersonalVentas();

        $desde = trim((string)($_GET['desde'] ?? ''));
        $hasta = trim((string)($_GET['hasta'] ?? ''));

        $this->json(true, '', [
            'items' => $this->model->listar(
                $desde !== '' ? $desde : null,
                $hasta !== '' ? $hasta : null
            ),
        ]);
    }

    public function resumen(): void
    {
        $this->soloPersonalVentas();
        $this->json(true, '', ['resumen' => $this->model->resumen()]);
    }

    public function detalle(): void
    {
        $this->soloPersonalVentas();

        $idVenta = (int)($_GET['id_venta'] ?? 0);
        $venta   = $idVenta > 0 ? $this->model->detalle($idVenta) : null;

        if (!$venta) {
            $this->json(false, 'Venta no encontrada.', [], 404);
        }

        $this->json(true, '', ['venta' => $venta]);
    }

    public function guardar(): void
    {
        $this->soloPost();
        $this->soloPersonalVentas();
        $this->verificarCSRFOFallar();

        // --- Convertir id_cliente a NULL si es 0 o vacío ---
        $idCliente = $_POST['id_cliente'] ?? null;
        if ($idCliente === '' || $idCliente === null || (int)$idCliente === 0) {
            $idCliente = null;
        } else {
            $idCliente = (int)$idCliente;
        }

        $metodoPago = trim($_POST['metodo_pago'] ?? 'Efectivo');
        $descripcion = trim($_POST['descripcion'] ?? '');

        // --- Descuento (opcional) ---
        $descuentoTipo = trim((string)($_POST['descuento_tipo'] ?? 'ninguno'));
        if (!in_array($descuentoTipo, ['ninguno', 'porcentaje', 'monto'], true)) {
            $descuentoTipo = 'ninguno';
        }

        $descuentoValor = (float)($_POST['descuento_valor'] ?? 0);
        if ($descuentoValor < 0) {
            $descuentoValor = 0;
        }
        if ($descuentoTipo === 'porcentaje' && $descuentoValor > 100) {
            $descuentoValor = 100;
        }
        if ($descuentoTipo === 'ninguno') {
            $descuentoValor = 0;
        }

        // Ítems mixtos: cada línea trae su tipo (producto|servicio), id y cantidad
        $tipos = $_POST['item_tipo'] ?? [];
        $ids   = $_POST['item_id'] ?? [];
        $cants = $_POST['item_cant'] ?? [];
        if (!is_array($ids) || !is_array($cants) || count($ids) === 0) {
            $_SESSION['mensaje_error'] = 'Debes agregar al menos un producto o servicio.';
            header('Location: /spectre_erp/src/modules/ventas/views/nueva_venta.php');
            exit;
        }

        $productos = [];
        foreach ($ids as $i => $id) {
            $id = (int)$id;
            $cant = (int)($cants[$i] ?? 0);
            $tipo = (($tipos[$i] ?? 'producto') === 'servicio') ? 'servicio' : 'producto';
            if ($id > 0 && $cant > 0) {
                $productos[] = ['tipo' => $tipo, 'id' => $id, 'cantidad' => $cant];
            }
        }

        if (empty($productos)) {
            $_SESSION['mensaje_error'] = 'No se han seleccionado productos o servicios válidos.';
            header('Location: /spectre_erp/src/modules/ventas/views/nueva_venta.php');
            exit;
        }

        // Subida del comprobante (opcional)
        $rutaComprobante = null;
        if (isset($_FILES['comprobante']) && $_FILES['comprobante']['error'] === UPLOAD_ERR_OK) {
            $carpeta = __DIR__ . '/../../../../uploads/comprobantes/';
            if (!is_dir($carpeta)) {
                mkdir($carpeta, 0777, true);
            }

            $extension = pathinfo($_FILES['comprobante']['name'], PATHINFO_EXTENSION);
            $nombreArchivo = 'venta_' . date('Ymd_His') . '.' . $extension;
            $rutaCompleta = $carpeta . $nombreArchivo;

            if (move_uploaded_file($_FILES['comprobante']['tmp_name'], $rutaCompleta)) {
                $rutaComprobante = 'uploads/comprobantes/' . $nombreArchivo;
            } else {
                $_SESSION['mensaje_error'] = 'Error al subir el comprobante.';
                header('Location: /spectre_erp/src/modules/ventas/views/nueva_venta.php');
                exit;
            }
        }

        // Llamar al modelo para crear la venta
        $resultado = $this->model->crearVentaManual([
            'id_cliente'        => $idCliente,
            'id_usuario'        => (int)$_SESSION['usuario_id'],
            'metodo_pago'       => $metodoPago,
            'descripcion'       => $descripcion,
            'comprobante_ruta'  => $rutaComprobante,
            'productos'         => $productos,
            'descuento_tipo'    => $descuentoTipo,
            'descuento_valor'   => $descuentoValor,
        ]);

        if ($resultado['ok']) {
            $mensaje = $resultado['message'];
            if (($resultado['descuento'] ?? 0) > 0) {
                $mensaje .= sprintf(
                    ' Subtotal: S/ %.2f — Descuento: S/ %.2f — Total: S/ %.2f',
                    $resultado['subtotal'],
                    $resultado['descuento'],
                    $resultado['total']
                );
            }
            $_SESSION['mensaje_exito'] = $mensaje;
            // Puedes redirigir a donde prefieras: dashboard, listado de ventas, o nueva_venta
            header('Location: /spectre_erp/src/modules/ventas/views/nueva_venta.php');
            exit;
        } else {
            $_SESSION['mensaje_error'] = $resultado['message'];
            header('Location: /spectre_erp/src/modules/ventas/views/nueva_venta.php');
            exit;
        }
    }

    public function exportarPdf(): void
    {
        $this->soloPersonalVentas();

        $idVenta = (int)($_GET['id_venta'] ?? 0);
        if ($idVenta <= 0) {
            http_response_code(400);
            echo 'ID de venta inválido.';
            exit;
        }

        $venta = $this->model->detalle($idVenta);
        if (!$venta) {
            http_response_code(404);
            echo 'Venta no encontrada.';
            exit;
        }

        // Fix para Dompdf
        if (isset($http_response_header)) {
            unset($http_response_header);
        }

        $dompdf = new Dompdf();

        $nombreCliente = trim(($venta['cliente_nombre'] ?? '') . ' ' . ($venta['cliente_apellido'] ?? ''));
        if ($nombreCliente === '') {
            $nombreCliente = 'Cliente sin registrar';
        }

        $html = <<<HTML
        <html>
        <head><meta charset="UTF-8"><style>
            body { font-family: Arial, sans-serif; }
            h1 { color: #4f6ef7; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
            th { background: #f0f0f0; }
            .total { font-size: 1.2em; font-weight: bold; margin-top: 20px; }
        </style></head>
        <body>
            <h1>Comprobante de Venta</h1>
            <p><strong>Código:</strong> {$venta['codigo_venta']}</p>
            <p><strong>Fecha:</strong> {$venta['fecha_venta']}</p>
            <p><strong>Cliente:</strong> {$nombreCliente}</p>
            <p><strong>Correo:</strong> {$venta['cliente_correo']}</p>
            <p><strong>Método de pago:</strong> {$venta['metodo_pago']}</p>
            <h2>Detalles</h2>
            <table>
                <thead><tr><th>Ítem</th><th>Tipo</th><th>Cantidad</th><th>Precio Unit.</th><th>Subtotal</th></tr></thead>
                <tbody>
        HTML;

        foreach ($venta['items'] as $item) {
            $tipoLabel = ($item['tipo_item'] ?? 'producto') === 'servicio' ? 'Servicio' : 'Producto';
            $html .= "<tr>
                <td>{$item['nombre_producto']}</td>
                <td>{$tipoLabel}</td>
                <td>{$item['cantidad']}</td>
                <td>S/ {$item['precio_unitario']}</td>
                <td>S/ {$item['subtotal']}</td>
            </tr>";
        }

        $descuento = (float)($venta['descuento'] ?? 0);
        $subtotal  = (float)($venta['subtotal'] ?? $venta['total']);

        $bloqueTotales = "<p>Subtotal: S/ " . number_format($subtotal, 2) . "</p>";
        if ($descuento > 0) {
            $etiquetaDescuento = ($venta['descuento_tipo'] ?? '') === 'porcentaje' ? 'Descuento' : 'Descuento (monto fijo)';
            $bloqueTotales .= "<p>{$etiquetaDescuento}: - S/ " . number_format($descuento, 2) . "</p>";
        }
        $bloqueTotales .= "<p class=\"total\">Total: S/ " . number_format((float)$venta['total'], 2) . "</p>";

        $html .= <<<HTML
                </tbody>
            </table>
            {$bloqueTotales}
            <p><strong>Descripción:</strong> {$venta['descripcion']}</p>
        HTML;

        if ($venta['comprobante_ruta']) {
            $html .= "<p><strong>Comprobante adjunto:</strong> <a href='/{$venta['comprobante_ruta']}'>Ver imagen</a></p>";
        }

        $html .= '</body></html>';

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $dompdf->stream("venta_{$idVenta}.pdf", ['Attachment' => true]);
        exit;
    }

    // ==================== HELPERS ====================

    private function soloPost(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(false, 'Método no permitido.', [], 405);
        }
    }

    private function requiereLoginJson(): void
    {
        if (!usuarioLogueado()) {
            $this->json(false, 'Debes iniciar sesión.', ['login_requerido' => true], 401);
        }
    }

    private function soloPersonalVentas(): void
    {
        if (!usuarioLogueado() || !in_array($_SESSION['usuario_rol'] ?? '', ['gerente', 'trabajador'], true)) {
            $this->json(false, 'No tienes permiso para realizar esta acción.', [], 403);
        }
    }

    private function verificarCSRFOFallar(): void
    {
        $tokenEnviado = (string)($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!csrf_verify($tokenEnviado)) {
            $this->json(false, 'Token de seguridad inválido. Recarga la página e inténtalo de nuevo.', [], 419);
        }
    }

    private function json(bool $success, string $message = '', array $extra = [], int $httpCode = 200): void
    {
        while (ob_get_level() > 0) ob_end_clean();

        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode(array_merge([
            'success' => $success,
            'message' => $message,
        ], $extra), JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// ==================== DISPATCHER ====================
$ctrl   = new VentaController();
$accion = $_GET['accion'] ?? '';

match ($accion) {
    'checkout'      => $ctrl->checkout(),
    'mis_compras'   => $ctrl->misCompras(),
    'listar'        => $ctrl->listar(),
    'resumen'       => $ctrl->resumen(),
    'detalle'       => $ctrl->detalle(),
    'guardar'       => $ctrl->guardar(),
    'exportar_pdf'  => $ctrl->exportarPdf(),
    default         => http_response_code(404),
};