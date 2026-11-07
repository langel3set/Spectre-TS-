<?php
declare(strict_types=1);

/**
 * src/modules/servicios/controllers/ServicioController.php
 *
 * Endpoints AJAX del módulo de servicios, usados desde el panel
 * (dashboard.php). Responden siempre en JSON. Mismos permisos que
 * inventario: gerente/trabajador pueden ver, crear y editar; solo
 * gerente puede dar de baja.
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);
ob_start();

require_once __DIR__ . '/../../../../autoload.php';

set_exception_handler(function (\Throwable $e): void {
    error_log('[ServicioController] Excepción no controlada: ' . $e->getMessage());
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

class ServicioController
{
    private Servicio $model;

    public function __construct()
    {
        iniciarSesionSegura();
        $this->model = new Servicio();
    }

    public function listar(): void
    {
        $this->soloPersonal();

        $busqueda = trim((string)($_GET['q'] ?? ''));
        $this->json(true, '', ['items' => $this->model->listar($busqueda !== '' ? $busqueda : null)]);
    }

    public function resumen(): void
    {
        $this->soloPersonal();
        $this->json(true, '', ['resumen' => $this->model->resumen()]);
    }

    public function crear(): void
    {
        $this->soloPost();
        $this->soloPersonal();
        $this->verificarCSRFOFallar();

        $resultado = $this->model->crear([
            'nombre'             => $_POST['nombre'] ?? '',
            'descripcion'        => $_POST['descripcion'] ?? '',
            'precio'             => $_POST['precio'] ?? '',
            'duracion_estimada'  => $_POST['duracion_estimada'] ?? '',
        ]);

        $this->json($resultado['ok'], $resultado['message'], [
            'id_servicio' => $resultado['id_servicio'] ?? null,
        ]);
    }

    public function actualizar(): void
    {
        $this->soloPost();
        $this->soloPersonal();
        $this->verificarCSRFOFallar();

        $idServicio = (int)($_POST['id_servicio'] ?? 0);
        if ($idServicio <= 0) {
            $this->json(false, 'Servicio inválido.');
        }

        $resultado = $this->model->actualizar($idServicio, [
            'nombre'             => $_POST['nombre'] ?? '',
            'descripcion'        => $_POST['descripcion'] ?? '',
            'precio'             => $_POST['precio'] ?? '',
            'duracion_estimada'  => $_POST['duracion_estimada'] ?? '',
        ]);

        $this->json($resultado['ok'], $resultado['message']);
    }

    public function eliminar(): void
    {
        $this->soloPost();
        $this->soloGerente();
        $this->verificarCSRFOFallar();

        $idServicio = (int)($_POST['id_servicio'] ?? 0);
        if ($idServicio <= 0) {
            $this->json(false, 'Servicio inválido.');
        }

        $ok = $this->model->eliminar($idServicio);
        $this->json($ok, $ok ? 'Servicio dado de baja correctamente.' : 'No se pudo dar de baja el servicio.');
    }

    /* ===================== helpers ===================== */

    private function soloPost(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(false, 'Método no permitido.', [], 405);
        }
    }

    private function soloPersonal(): void
    {
        if (!usuarioLogueado() || !in_array($_SESSION['usuario_rol'] ?? '', ['gerente', 'trabajador'], true)) {
            $this->json(false, 'No tienes permiso para acceder a servicios.', [], 403);
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
$ctrl   = new ServicioController();
$accion = $_GET['accion'] ?? '';

match ($accion) {
    'listar'     => $ctrl->listar(),
    'resumen'    => $ctrl->resumen(),
    'crear'      => $ctrl->crear(),
    'actualizar' => $ctrl->actualizar(),
    'eliminar'   => $ctrl->eliminar(),
    default      => http_response_code(404),
};
