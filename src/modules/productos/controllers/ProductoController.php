<?php
declare(strict_types=1);

/**
 * src/modules/productos/controllers/ProductoController.php
 *
 * Alta de productos con stock, restringida a gerente/trabajador.
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);
ob_start();

require_once __DIR__ . '/../../../../autoload.php';

set_exception_handler(function (\Throwable $e): void {
    error_log('[ProductoController] Excepción no controlada: ' . $e->getMessage());
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

class ProductoController
{
    private Producto $model;

    public function __construct()
    {
        iniciarSesionSegura();
        $this->model = new Producto();
    }

    public function crear(): void
    {
        $this->soloPost();
        $this->soloPersonalVenta();
        $this->verificarCSRFOFallar();

        $codigo      = trim((string)($_POST['codigo'] ?? ''));
        $nombre      = trim((string)($_POST['nombre'] ?? ''));
        $descripcion = trim((string)($_POST['descripcion'] ?? ''));
        $categoria   = trim((string)($_POST['categoria'] ?? ''));
        $precio      = (string)($_POST['precio'] ?? '');
        $stock       = (string)($_POST['stock'] ?? '');
        $stockMinimo = (string)($_POST['stock_minimo'] ?? '0');
        $imagenUrl   = trim((string)($_POST['imagen_url'] ?? ''));

        if ($codigo === '' || $nombre === '') {
            $this->json(false, 'Código y nombre son obligatorios.');
        }
        if (!array_key_exists($categoria, Producto::CATEGORIAS)) {
            $this->json(false, 'Selecciona una categoría válida.');
        }
        if (!is_numeric($precio) || (float)$precio < 0) {
            $this->json(false, 'El precio no es válido.');
        }
        if (!ctype_digit($stock)) {
            $this->json(false, 'El stock debe ser un número entero (0 o mayor).');
        }
        if ($imagenUrl !== '' && !filter_var($imagenUrl, FILTER_VALIDATE_URL)) {
            $this->json(false, 'La URL de imagen no es válida.');
        }
        if ($this->model->existeCodigo($codigo)) {
            $this->json(false, 'Ya existe un producto con ese código.');
        }

        $idProducto = $this->model->crear([
            'codigo'       => $codigo,
            'nombre'       => $nombre,
            'descripcion'  => $descripcion,
            'categoria'    => $categoria,
            'tipo'         => 'producto',
            'precio'       => (float)$precio,
            'stock'        => (int)$stock,
            'stock_minimo' => ctype_digit($stockMinimo) ? (int)$stockMinimo : 0,
            'estado'       => 'activo',
        ], $imagenUrl !== '' ? $imagenUrl : null);

        if ($idProducto === false) {
            $this->json(false, 'No se pudo guardar el producto.');
        }

        $this->json(true, 'Producto agregado correctamente.', ['id_producto' => $idProducto]);
    }

    /* ===================== helpers ===================== */

    private function soloPost(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(false, 'Método no permitido.', [], 405);
        }
    }

    private function soloPersonalVenta(): void
    {
        if (!usuarioLogueado() || !in_array($_SESSION['usuario_rol'] ?? '', ['gerente', 'trabajador'], true)) {
            $this->json(false, 'No tienes permiso para agregar productos.', [], 403);
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
$ctrl   = new ProductoController();
$accion = $_GET['accion'] ?? '';

match ($accion) {
    'crear' => $ctrl->crear(),
    default => http_response_code(404),
};
