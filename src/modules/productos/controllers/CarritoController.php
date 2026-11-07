<?php
declare(strict_types=1);

/**
 * src/modules/productos/controllers/CarritoController.php
 *
 * Endpoints AJAX del carrito. Responden siempre en JSON.
 * IMPORTANTE: ninguna acción de escritura funciona sin sesión iniciada;
 * es la regla "solo compra quien tiene cuenta".
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);
ob_start();

require_once __DIR__ . '/../../../../autoload.php';

set_exception_handler(function (\Throwable $e): void {
    error_log('[CarritoController] Excepción no controlada: ' . $e->getMessage());
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

class CarritoController
{
    private CarritoItem $carrito;

    public function __construct()
    {
        iniciarSesionSegura();
        $this->carrito = new CarritoItem();
    }

    public function agregar(): void
    {
        $this->soloPost();
        $this->requiereLoginJson();
        $this->verificarCSRFOFallar();

        $idProducto = (int)($_POST['id_producto'] ?? 0);
        $cantidad   = max(1, (int)($_POST['cantidad'] ?? 1));

        if ($idProducto <= 0) {
            $this->json(false, 'Producto inválido.');
        }

        $resultado = $this->carrito->agregar((int)$_SESSION['usuario_id'], $idProducto, $cantidad);

        $this->json(
            $resultado['ok'],
            $resultado['message'],
            ['contador' => $this->carrito->contarUnidades((int)$_SESSION['usuario_id'])]
        );
    }

    public function actualizar(): void
    {
        $this->soloPost();
        $this->requiereLoginJson();
        $this->verificarCSRFOFallar();

        $idProducto = (int)($_POST['id_producto'] ?? 0);
        $cantidad   = (int)($_POST['cantidad'] ?? 0);

        $resultado = $this->carrito->actualizarCantidad((int)$_SESSION['usuario_id'], $idProducto, $cantidad);

        $this->json($resultado['ok'], $resultado['message'], [
            'cantidad' => $resultado['cantidad'] ?? 0,
            'total'    => $this->carrito->total((int)$_SESSION['usuario_id']),
            'contador' => $this->carrito->contarUnidades((int)$_SESSION['usuario_id']),
        ]);
    }

    public function eliminar(): void
    {
        $this->soloPost();
        $this->requiereLoginJson();
        $this->verificarCSRFOFallar();

        $idProducto = (int)($_POST['id_producto'] ?? 0);
        $resultado  = $this->carrito->eliminar((int)$_SESSION['usuario_id'], $idProducto);

        $this->json($resultado['ok'], $resultado['message'], [
            'total'    => $this->carrito->total((int)$_SESSION['usuario_id']),
            'contador' => $this->carrito->contarUnidades((int)$_SESSION['usuario_id']),
        ]);
    }

    public function listar(): void
    {
        $this->requiereLoginJson();

        $idUsuario = (int)$_SESSION['usuario_id'];

        $this->json(true, '', [
            'items' => $this->carrito->listar($idUsuario),
            'total' => $this->carrito->total($idUsuario),
        ]);
    }

    public function contador(): void
    {
        if (!usuarioLogueado()) {
            $this->json(true, '', ['contador' => 0]);
        }

        $this->json(true, '', ['contador' => $this->carrito->contarUnidades((int)$_SESSION['usuario_id'])]);
    }

    /* ===================== helpers ===================== */

    private function soloPost(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(false, 'Método no permitido.', [], 405);
        }
    }

    /**
     * Corta la ejecución con 401 si no hay sesión. Este es el punto
     * que garantiza "no hay compra sin cuenta".
     */
    private function requiereLoginJson(): void
    {
        if (!usuarioLogueado()) {
            $this->json(false, 'Debes iniciar sesión para comprar.', ['login_requerido' => true], 401);
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
$ctrl   = new CarritoController();
$accion = $_GET['accion'] ?? '';

match ($accion) {
    'agregar'    => $ctrl->agregar(),
    'actualizar' => $ctrl->actualizar(),
    'eliminar'   => $ctrl->eliminar(),
    'listar'     => $ctrl->listar(),
    'contador'   => $ctrl->contador(),
    default      => http_response_code(404),
};
