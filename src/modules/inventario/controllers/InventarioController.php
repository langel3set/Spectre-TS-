<?php
declare(strict_types=1);

/**
 * src/modules/inventario/controllers/InventarioController.php
 *
 * Endpoints AJAX del módulo de inventario, usados desde el panel
 * (dashboard.php). Responden siempre en JSON.
 *
 * Permisos:
 *  - gerente y trabajador: ver listado/resumen/historial y registrar
 *    movimientos de stock (entrada, salida, ajuste) y editar datos
 *    de catálogo del producto.
 *  - solo gerente: desactivar (dar de baja) un producto.
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);
ob_start();

require_once __DIR__ . '/../../../../autoload.php';

set_exception_handler(function (\Throwable $e): void {
    error_log('[InventarioController] Excepción no controlada: ' . $e->getMessage());
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
});

class InventarioController
{
    private Inventario $model;

    public function __construct()
    {
        iniciarSesionSegura();
        $this->model = new Inventario();
    }

    public function listar(): void
    {
        $this->soloPersonalInventario();

        $categoria = trim((string)($_GET['categoria'] ?? ''));
        $busqueda  = trim((string)($_GET['q'] ?? ''));
        $bajoStock = ($_GET['bajo_stock'] ?? '') === '1';

        $items = $this->model->listar(
            $categoria !== '' ? $categoria : null,
            $busqueda !== '' ? $busqueda : null,
            $bajoStock
        );

        $this->json(true, '', [
            'items'      => $items,
            'categorias' => Producto::CATEGORIAS,
        ]);
    }

    public function resumen(): void
    {
        $this->soloPersonalInventario();
        $this->json(true, '', ['resumen' => $this->model->resumen()]);
    }

    public function historial(): void
    {
        $this->soloPersonalInventario();

        $idProducto = isset($_GET['id_producto']) ? (int)$_GET['id_producto'] : null;
        $limite     = isset($_GET['limite']) ? (int)$_GET['limite'] : 20;

        $this->json(true, '', ['items' => $this->model->historial($idProducto, $limite)]);
    }

    public function movimiento(): void
    {
        $this->soloPost();
        $this->soloPersonalInventario();
        $this->verificarCSRFOFallar();

        $idProducto = (int)($_POST['id_producto'] ?? 0);
        $tipo       = trim((string)($_POST['tipo'] ?? ''));
        $cantidad   = (string)($_POST['cantidad'] ?? '');
        $motivo     = trim((string)($_POST['motivo'] ?? ''));

        if ($idProducto <= 0) {
            $this->json(false, 'Producto inválido.');
        }
        if (!ctype_digit($cantidad)) {
            $this->json(false, 'La cantidad debe ser un número entero.');
        }

        $resultado = $this->model->registrarMovimiento(
            $idProducto,
            $tipo,
            (int)$cantidad,
            $motivo !== '' ? $motivo : null,
            (int)$_SESSION['usuario_id']
        );

        $this->json($resultado['ok'], $resultado['message'], [
            'stock' => $resultado['stock'] ?? null,
        ]);
    }

    public function actualizar(): void
    {
        $this->soloPost();
        $this->soloPersonalInventario();
        $this->verificarCSRFOFallar();

        $idProducto = (int)($_POST['id_producto'] ?? 0);

        if ($idProducto <= 0) {
            $this->json(false, 'Producto inválido.');
        }

        $resultado = $this->model->actualizarDatos($idProducto, [
            'nombre'       => $_POST['nombre'] ?? '',
            'descripcion'  => $_POST['descripcion'] ?? '',
            'categoria'    => $_POST['categoria'] ?? '',
            'precio'       => $_POST['precio'] ?? '',
            'stock_minimo' => $_POST['stock_minimo'] ?? '',
        ]);

        $this->json($resultado['ok'], $resultado['message']);
    }

    public function eliminar(): void
    {
        $this->soloPost();
        $this->soloGerente();
        $this->verificarCSRFOFallar();

        $idProducto = (int)($_POST['id_producto'] ?? 0);

        if ($idProducto <= 0) {
            $this->json(false, 'Producto inválido.');
        }

        $ok = $this->model->eliminar($idProducto);

        $this->json($ok, $ok ? 'Producto dado de baja correctamente.' : 'No se pudo dar de baja el producto.');
    }

    /* ===================== helpers ===================== */

    private function soloPost(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(false, 'Método no permitido.', [], 405);
        }
    }

    private function soloPersonalInventario(): void
    {
        if (!usuarioLogueado() || !in_array($_SESSION['usuario_rol'] ?? '', ['gerente', 'trabajador'], true)) {
            $this->json(false, 'No tienes permiso para acceder al inventario.', [], 403);
        }
    }

    private function soloGerente(): void
    {
        if (!usuarioLogueado() || ($_SESSION['usuario_rol'] ?? '') !== 'gerente') {
            $this->json(false, 'Solo el gerente puede realizar esta acción.', [], 403);
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
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode(array_merge([
            'success' => $success,
            'message' => $message,
        ], $extra), JSON_UNESCAPED_UNICODE);
        exit;
    }
}

/* ===================== dispatcher ===================== */
$ctrl   = new InventarioController();
$accion = $_GET['accion'] ?? '';

match ($accion) {
    'listar'     => $ctrl->listar(),
    'resumen'    => $ctrl->resumen(),
    'historial'  => $ctrl->historial(),
    'movimiento' => $ctrl->movimiento(),
    'actualizar' => $ctrl->actualizar(),
    'eliminar'   => $ctrl->eliminar(),
    default      => http_response_code(404),
};
