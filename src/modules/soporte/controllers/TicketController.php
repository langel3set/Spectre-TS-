<?php
declare(strict_types=1);

// Mostrar errores para depuración
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../../../config/auth.php';
require_once __DIR__ . '/../../../config/csrf.php';
require_once __DIR__ . '/../models/Ticket.php';
require_once __DIR__ . '/../../../services/MailService.php';

class TicketController
{
    private Ticket $model;
    private const MAX_FILE_SIZE = 50 * 1024 * 1024;
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'mov', 'avi', 'pdf', 'doc', 'docx'];

    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            iniciarSesionSegura();
        }
        $this->model = new Ticket();
    }

    public function crear(): void
    {
        try {
            $this->soloPost();
            $this->verificarCSRFOFallar();

            $titulo = trim($_POST['asunto'] ?? '');
            $descripcion = trim($_POST['problema'] ?? '');
            $tipo = trim($_POST['tipo'] ?? 'soporte');
            $prioridad = trim($_POST['prioridad'] ?? 'media');

            if (!$titulo || !$descripcion) {
                $this->json(false, 'Completa todos los campos obligatorios.');
            }

            $usuario = usuarioActual();
            if (!$usuario['id'] || $usuario['rol'] !== 'cliente') {
                $this->json(false, 'Debes iniciar sesión como cliente.');
            }

            $idCliente = (int) $usuario['id'];
            $idTicket = $this->model->crear($idCliente, $titulo, $descripcion, $tipo, $prioridad);

            if (!$idTicket) {
                $this->json(false, 'Error al crear el ticket en la base de datos.');
            }

            // Procesar archivos
            if (!empty($_FILES['adjuntos']['name'][0])) {
                $this->procesarArchivos($idTicket);
            }

            $this->model->agregarComentario($idTicket, $idCliente, 'Ticket creado por el cliente.', 'creacion');

            // Enviar correo (opcional)
            try {
                $mail = new MailService();
                $mail->enviarConfirmacionTicket($usuario['correo'], $usuario['nombre'], $idTicket, $titulo);
            } catch (\Throwable $e) {
                error_log('Error enviando correo: ' . $e->getMessage());
            }

            $this->json(true, 'Ticket creado correctamente.', [
                'redirect' => '/spectre_erp/src/modules/soporte/views/mis_tickets.php'
            ]);

        } catch (\Throwable $e) {
            error_log('TicketController::crear error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            $this->json(false, 'Error interno: ' . $e->getMessage());
        }
    }

    public function verMisTickets(): void
    {
        requiereLogin();
        if (rolActual() !== 'cliente') {
            header('Location: /spectre_erp/src/modules/general/views/dashboard.php');
            exit;
        }
        $idCliente = (int) $_SESSION['usuario_id'];
        $tickets = $this->model->listarPorCliente($idCliente);
        require __DIR__ . '/../views/mis_tickets.php';
    }

    public function verDetalle(): void
    {
        requiereLogin();
        $idTicket = (int) ($_GET['id'] ?? 0);
        if (!$idTicket) die('Ticket no especificado.');

        $ticket = $this->model->obtenerPorId($idTicket);
        if (!$ticket) die('Ticket no encontrado.');

        $esPersonal = in_array(rolActual(), ['gerente', 'trabajador']);
        if (!$esPersonal && (int)$ticket['id_cliente'] !== (int)$_SESSION['usuario_id']) {
            die('No tienes permiso.');
        }

        $adjuntos = $this->model->obtenerAdjuntos($idTicket);
        $historial = $this->model->obtenerHistorial($idTicket);

        if ($esPersonal) {
            require __DIR__ . '/../views/panel_detalle_ticket.php';
        } else {
            require __DIR__ . '/../views/detalle_ticket.php';
        }
    }

    public function panelListado(): void
    {
        requiereRol('gerente', 'trabajador');
        $tickets = $this->model->listarTodos();
        require __DIR__ . '/../views/panel_tickets.php';
    }

    /**
     * Responder a un ticket (vía AJAX/JSON)
     */
    public function responder(): void
    {
        try {
            $this->soloPost();
            requiereRol('gerente', 'trabajador');
            $this->verificarCSRFOFallar();

            $idTicket = (int) ($_POST['id_ticket'] ?? 0);
            $comentario = trim($_POST['comentario'] ?? '');
            $nuevoEstado = trim($_POST['estado'] ?? '');
            $solucion = trim($_POST['solucion'] ?? '');

            if (!$idTicket || !$comentario) {
                $this->json(false, 'Comentario obligatorio.');
            }

            $ticket = $this->model->obtenerPorId($idTicket);
            if (!$ticket) {
                $this->json(false, 'Ticket no encontrado.');
            }

            $idUsuario = (int) $_SESSION['usuario_id'];
            $this->model->agregarComentario($idTicket, $idUsuario, $comentario, 'respuesta');

            if ($nuevoEstado && in_array($nuevoEstado, ['pendiente', 'en_proceso', 'resuelto', 'cerrado'])) {
                $this->model->cambiarEstado($idTicket, $nuevoEstado);
                if ($nuevoEstado === 'resuelto' && $solucion) {
                    $this->model->cerrarTicket($idTicket, $solucion);
                }
            }

            // Enviar correo (opcional)
            try {
                $mail = new MailService();
                $mail->enviarRespuestaTicket($ticket['correo'], $ticket['nombre'], $idTicket, $comentario);
            } catch (\Throwable $e) {
                error_log('Error enviando correo: ' . $e->getMessage());
            }

            $this->json(true, 'Respuesta enviada correctamente.');

        } catch (\Throwable $e) {
            error_log('TicketController::responder error: ' . $e->getMessage());
            $this->json(false, 'Error interno: ' . $e->getMessage());
        }
    }

    private function soloPost(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(false, 'Método no permitido.', [], 405);
        }
    }

    private function verificarCSRFOFallar(): void
    {
        $tokenEnviado = (string)($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!csrf_verify($tokenEnviado)) {
            $this->json(false, 'Token de seguridad inválido.', [], 419);
        }
    }

    public function listarJson(): void
    {
        requiereRol('gerente', 'trabajador');
        $tickets = $this->model->listarTodos();
        $this->json(true, '', ['tickets' => $tickets]);
    }

    public function detalleJson(): void
    {
        requiereRol('gerente', 'trabajador');
        $idTicket = (int) ($_GET['id'] ?? 0);
        if (!$idTicket) {
            $this->json(false, 'ID de ticket no especificado.');
        }
        $ticket = $this->model->obtenerPorId($idTicket);
        if (!$ticket) {
            $this->json(false, 'Ticket no encontrado.');
        }
        $historial = $this->model->obtenerHistorial($idTicket);
        $this->json(true, '', ['ticket' => $ticket, 'historial' => $historial]);
    }

    public function resumen(): void
{
    requiereRol('gerente', 'trabajador');
    $abiertos = $this->model->contarPorEstado('pendiente');
    $enProceso = $this->model->contarPorEstado('en_proceso');
    $resueltos = $this->model->contarPorEstado('resuelto');
    $this->json(true, '', [
        'abiertos' => $abiertos,
        'en_proceso' => $enProceso,
        'resueltos' => $resueltos
    ]);
}

    private function procesarArchivos(int $idTicket): void
    {
        $uploadDir = __DIR__ . '/../../../../public/uploads/tickets/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        foreach ($_FILES['adjuntos']['error'] as $key => $error) {
            if ($error !== UPLOAD_ERR_OK) continue;

            $nombreOriginal = $_FILES['adjuntos']['name'][$key];
            $tipo = $_FILES['adjuntos']['type'][$key];
            $tamano = $_FILES['adjuntos']['size'][$key];
            $tmpName = $_FILES['adjuntos']['tmp_name'][$key];
            $extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));

            if (!in_array($extension, self::ALLOWED_EXTENSIONS) || $tamano > self::MAX_FILE_SIZE) {
                continue;
            }

            $nombreUnico = uniqid('ticket_') . '.' . $extension;
            $rutaDestino = $uploadDir . $nombreUnico;

            if (move_uploaded_file($tmpName, $rutaDestino)) {
                $rutaRelativa = '/spectre_erp/public/uploads/tickets/' . $nombreUnico;
                $this->model->guardarAdjunto($idTicket, $nombreOriginal, $rutaRelativa, $tipo, $tamano);
            }
        }
    }

    private function json(bool $success, string $message = '', array $extra = [], int $httpCode = 200): void
    {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// ============================================================
//  DISPATCHER
// ============================================================
$ctrl = new TicketController();

// Prioridad: POST > GET (para que funcionen tanto formularios como enlaces)
$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

try {
    match ($accion) {
    'resumen'      => $ctrl->resumen(),
    'listar-json'  => $ctrl->listarJson(),
    'detalle-json' => $ctrl->detalleJson(),
    'responder'    => $ctrl->responder(),
    'crear'        => $ctrl->crear(),
    'mis-tickets'  => $ctrl->verMisTickets(),
    'detalle'      => $ctrl->verDetalle(),
    'panel'        => $ctrl->panelListado(),
    default        => die('Acción no válida.'),
};
} catch (\Throwable $e) {
    error_log('TicketController error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo 'Error interno del servidor.';
}