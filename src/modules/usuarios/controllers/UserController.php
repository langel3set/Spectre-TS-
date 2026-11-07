<?php
declare(strict_types=1);

/**
 * src/modules/usuarios/controllers/UserController.php
 *
 * Controlador de usuarios: login, registro, recuperación de contraseña y logout.
 * Todas las acciones (excepto "login", que solo pinta la vista) responden en JSON
 * y están pensadas para ser consumidas vía fetch()/AJAX.
 */

ini_set('display_errors', '1');
ini_set('html_errors', '0'); // evita que los errores se impriman como HTML (<b>...</b>) y corrompan el JSON
error_reporting(E_ALL);

// Captura cualquier salida (warnings, notices, HTML accidental) que ocurra
// antes de que llamemos a json(), para que NUNCA se mezcle con la respuesta.
ob_start();

require_once __DIR__ . '/../../../../autoload.php';

/**
 * Red de seguridad: cualquier excepción no controlada o error fatal
 * responde igualmente en JSON en lugar de imprimir HTML de PHP.
 */
set_exception_handler(function (\Throwable $e): void {
    error_log('[UserController] Excepción no controlada: ' . $e->getMessage());
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success'        => false,
        'message'        => 'Error interno del servidor.',
        // TEMPORAL para depurar: quita esta clave cuando ya no la necesites.
        '_debug_output'  => $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
});

register_shutdown_function(function (): void {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success'       => false,
            'message'       => 'Error interno del servidor.',
            '_debug_output' => $error['message'] . ' en ' . $error['file'] . ':' . $error['line'],
        ], JSON_UNESCAPED_UNICODE);
    }
});

class UserController
{
    private User $model;

    private const MIN_PASSWORD_LENGTH  = 8;
    private const TOKEN_EXPIRA_MINUTOS = 30;
    private const MAX_INTENTOS_LOGIN   = 5;
    private const BLOQUEO_MINUTOS      = 15;

    public function __construct()
    {
        iniciarSesionSegura();
        $this->model = new User();
    }

    /* =========================================================
     *  VISTA DE LOGIN (GET, no AJAX)
     * ========================================================= */
    public function mostrarLogin(): void
    {
        if (usuarioLogueado()) {
            $this->redirigirPorRol();
        }
        require __DIR__ . '/../views/login.php';
    }

    /* =========================================================
     *  LOGIN (POST AJAX -> JSON)
     * ========================================================= */
    public function procesarLogin(): void
    {
        $this->soloPost();
        $this->verificarCSRFOFallar();

        $correo     = $this->limpiarCorreo((string)($_POST['correo'] ?? ''));
        $contrasena = trim((string)($_POST['contrasena'] ?? ''));

        if ($correo === false || $correo === '') {
            $this->json(false, 'Introduce un correo electrónico válido.');
        }
        if ($contrasena === '') {
            $this->json(false, 'Introduce tu contraseña.');
        }

        // Protección básica contra fuerza bruta (por correo + IP, ver nota en estaBloqueado()).
        if ($this->estaBloqueado($correo)) {
            $this->json(false, 'Demasiados intentos fallidos. Inténtalo de nuevo en unos minutos.');
        }

        $usuario = $this->model->login($correo, $contrasena);

        if (!$usuario) {
            $this->registrarIntentoFallido($correo);
            $this->json(false, 'Correo o contraseña incorrectos.');
        }

        $this->resetIntentosFallidos($correo);

        // Evita fijación de sesión (session fixation): se regenera el ID tras autenticar.
        session_regenerate_id(true);

        $_SESSION['usuario_id']       = (int)$usuario['id'];
        $_SESSION['usuario_nombre']   = $usuario['nombre'];
        $_SESSION['usuario_apellido'] = $usuario['apellido'];
        $_SESSION['usuario_correo']   = $usuario['correo'];
        $_SESSION['usuario_rol']      = $usuario['rol'];

        $this->json(true, 'Bienvenido de nuevo.', [
            'redirect' => match($_SESSION['usuario_rol']) {
                'gerente'   => '/spectre_erp/src/modules/general/views/dashboard.php',
                'trabajador'=> '/spectre_erp/src/modules/general/views/dashboard.php',
                default     => '/spectre_erp/index.php', 
            },
        ]);
    }

    public function registrar(): void
    {
        $this->soloPost();
        $this->verificarCSRFOFallar();

        $nombre     = htmlspecialchars(trim((string)($_POST['nombre']   ?? '')), ENT_QUOTES, 'UTF-8');
        $apellido   = htmlspecialchars(trim((string)($_POST['apellido'] ?? '')), ENT_QUOTES, 'UTF-8');
        $correo     = $this->limpiarCorreo((string)($_POST['correo'] ?? ''));
        $contrasena = (string)($_POST['contrasena'] ?? '');
        $confirmar  = (string)($_POST['confirmar_contrasena'] ?? '');

        if ($nombre === '' || $apellido === '') {
            $this->json(false, 'Nombre y apellido son obligatorios.');
        }
        if ($correo === false || $correo === '') {
            $this->json(false, 'Introduce un correo electrónico válido.');
        }
        if (strlen($contrasena) < self::MIN_PASSWORD_LENGTH) {
            $this->json(false, 'La contraseña debe tener al menos ' . self::MIN_PASSWORD_LENGTH . ' caracteres.');
        }
        if ($contrasena !== $confirmar) {
            $this->json(false, 'Las contraseñas no coinciden.');
        }

        if ($this->model->existeCorreo($correo)) {
            // Mensaje genérico: no es un dato sensible en registro (el usuario ya lo sabe),
            // pero puedes cambiarlo por uno neutro si prefieres no confirmar existencia.
            $this->json(false, 'Ese correo ya está registrado.');
        }

        // User::registrar() hashea la contraseña internamente con password_hash(),
        // por eso aquí se envía en claro (nunca pre-hasheada).
        $creado = $this->model->registrar($nombre, $apellido, $correo, $contrasena);

        if (!$creado) {
            $this->json(false, 'No se pudo completar el registro. Inténtalo más tarde.');
        }

        $this->json(true, 'Cuenta creada correctamente. Ya puedes iniciar sesión.');
    }

    /* =========================================================
     *  RECUPERAR CONTRASEÑA - solicitar enlace (POST AJAX -> JSON)
     * ========================================================= */
    public function recuperar(): void
    {
        $this->soloPost();
        $this->verificarCSRFOFallar();

        $correo = $this->limpiarCorreo((string)($_POST['correo'] ?? ''));

        // Respuesta neutra: nunca revelar si el correo existe o no en el sistema.
        $mensajeNeutro = 'Si el correo existe recibirás un enlace de recuperación.';

        if ($correo === false || $correo === '') {
            $this->json(true, $mensajeNeutro);
        }

        $usuario = $this->model->buscarPorCorreo($correo);

        if ($usuario) {
            $token  = bin2hex(random_bytes(32));
            $expira = (new DateTimeImmutable('+' . self::TOKEN_EXPIRA_MINUTOS . ' minutes'))
                        ->format('Y-m-d H:i:s');

            $this->model->guardarToken((int)$usuario['id'], $token, $expira);

            try {
                $mail = new MailService();
                $mail->enviarRecuperacion($correo, $usuario['nombre'], $token);
            } catch (\Throwable $e) {
                error_log('[UserController::recuperar] Error enviando correo: ' . $e->getMessage());
                // No se filtra el error al cliente para no revelar información interna.
            }
        }

        $this->json(true, $mensajeNeutro);
    }

    public function mostrarRestablecer(): void
    {
        $token = trim($_GET['token'] ?? '');

        if ($token === '') {
            die('Token inválido.');
        }

        require __DIR__ . '/../views/restablecer.php';
    }

    /* =========================================================
     *  RESET DE CONTRASEÑA (POST AJAX -> JSON)
     * ========================================================= */
    public function reset(): void
    {
        try {
            $this->soloPost();
            $this->verificarCSRFOFallar();

            $token      = trim((string)($_POST['token'] ?? ''));
            $contrasena = (string)($_POST['contrasena'] ?? '');
            $confirmar  = (string)($_POST['confirmar_contrasena'] ?? '');

            if ($token === '') {
                $this->json(false, 'Token inválido.');
            }

            if ($contrasena !== $confirmar) {
                $this->json(false, 'Las contraseñas no coinciden.');
            }

            if (strlen($contrasena) < self::MIN_PASSWORD_LENGTH) {
                $this->json(
                    false,
                    'La contraseña debe tener mínimo ' .
                    self::MIN_PASSWORD_LENGTH .
                    ' caracteres.'
                );
            }

            $registro = $this->model->buscarToken($token);

            if (!$registro) {
                $this->json(false, 'Token no encontrado.');
            }

            $actualizado = $this->model->actualizarPassword(
                (int)$registro['id_usuario'],
                $contrasena
            );

            if (!$actualizado) {
                $this->json(false, 'No se pudo actualizar la contraseña.');
            }

            $this->model->eliminarToken($token);

            $this->json(
                true,
                'Contraseña actualizada correctamente.'
            );

        } catch (Throwable $e) {
            $this->json(
                false,
                $e->getMessage()
            );
        }
    }

    public function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();

        header('Location: /spectre_erp');
        exit;
    }

    /* =========================================================
     *  HELPERS PRIVADOS
     * ========================================================= */

    private function soloPost(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(false, 'Método no permitido.', [], 405);
        }
    }

    /**
     * Valida el token CSRF enviado (por POST o por cabecera X-CSRF-Token)
     * usando csrf_verify() de src/config/csrf.php.
     */
    private function verificarCSRFOFallar(): void
    {
        $tokenEnviado = (string)($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

        if (!csrf_verify($tokenEnviado)) {
            $this->json(false, 'Token de seguridad inválido. Recarga la página e inténtalo de nuevo.', [], 419);
        }
    }

    private function limpiarCorreo(string $correo): string|false
    {
        $correo = trim($correo);
        $correo = filter_var($correo, FILTER_SANITIZE_EMAIL);
        return filter_var($correo, FILTER_VALIDATE_EMAIL);
    }

   public function contarClientes(): void
{
    // No forzar solo POST, aceptar GET también
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $this->verificarCSRFOFallar();
    }
    
    $model = new User();
    $total = $model->contarClientes();
    
    $this->json(true, '', ['total' => $total]);
}

    private function redirigirPorRol(): void
    {
        if (esPersonal()) {
            header('Location: /spectre_erp/src/modules/general/views/dashboard.php');
        } else {
            header('Location: /spectre_erp/index.php');
        }
        exit;
    }

    /**
     * Protección contra fuerza bruta basada en $_SESSION + IP.
     *
     * Es una base funcional, pero al vivir en sesión de servidor no comparte
     * estado entre navegadores/dispositivos distintos que ataquen la misma
     * cuenta. Para producción se recomienda migrar el contador a una tabla
     * `intentos_login (correo, ip, intentos, bloqueado_hasta)` en la base
     * de datos y consultarla en estaBloqueado()/registrarIntentoFallido().
     */
    private function claveIntentos(string $correo): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'desconocida';
        return 'intentos_' . md5($correo . '|' . $ip);
    }

    private function estaBloqueado(string $correo): bool
    {
        $clave = $this->claveIntentos($correo);
        $info  = $_SESSION[$clave] ?? null;

        if (!$info) {
            return false;
        }
        if ($info['intentos'] >= self::MAX_INTENTOS_LOGIN && time() < $info['bloqueado_hasta']) {
            return true;
        }
        if (time() >= $info['bloqueado_hasta']) {
            unset($_SESSION[$clave]);
        }
        return false;
    }

    private function registrarIntentoFallido(string $correo): void
    {
        $clave = $this->claveIntentos($correo);
        $info  = $_SESSION[$clave] ?? ['intentos' => 0, 'bloqueado_hasta' => 0];
        $info['intentos']++;
        if ($info['intentos'] >= self::MAX_INTENTOS_LOGIN) {
            $info['bloqueado_hasta'] = time() + self::BLOQUEO_MINUTOS * 60;
        }
        $_SESSION[$clave] = $info;
    }
   public function listarClientes(): void
{
    // Solo personal (gerente o trabajador)
    requiereRol('gerente', 'trabajador');

    try {
        $model = new User();
        $clientes = $model->listarClientes();
        $this->json(true, 'Clientes obtenidos correctamente', ['clientes' => $clientes]);
    } catch (\Throwable $e) {
        error_log('Error en listarClientes: ' . $e->getMessage());
        $this->json(false, 'Error al obtener clientes: ' . $e->getMessage());
    }
}

    private function resetIntentosFallidos(string $correo): void
    {
        unset($_SESSION[$this->claveIntentos($correo)]);
    }

    /**
     * Responde en JSON y termina la ejecución.
     */
    private function json(bool $success, string $message = '', array $extra = [], int $httpCode = 200): void
    {
        // Cualquier warning/notice/echo accidental que haya ocurrido antes de
        // este punto queda en el buffer: lo sacamos de ahí para que nunca
        // contamine el JSON, pero lo dejamos visible para depurar.
        $salidaPrevia = trim((string) ob_get_contents());
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');

        $payload = array_merge([
            'success' => $success,
            'message' => $message,
        ], $extra);

        if ($salidaPrevia !== '') {
            error_log('[UserController] Salida inesperada antes del JSON: ' . $salidaPrevia);
            // TEMPORAL para depurar: quita esta línea cuando ya no la necesites.
            $payload['_debug_output'] = $salidaPrevia;
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

/* =================================================================
 *  DISPATCHER
 * ================================================================= */
$ctrl   = new UserController();
$accion = (string)($_GET['accion'] ?? 'login');

match ($accion) {
    'restablecer'    => $ctrl->mostrarRestablecer(),
    'login'          => $ctrl->mostrarLogin(),
    'procesar'       => $ctrl->procesarLogin(),
    'registrar'      => $ctrl->registrar(),
    'recuperar'      => $ctrl->recuperar(),
    'reset'          => $ctrl->reset(),
    'logout'         => $ctrl->logout(),
    'contar-clientes' => $ctrl->contarClientes(),
    'listar-clientes' => $ctrl->listarClientes(),
    default          => $ctrl->mostrarLogin(),
};