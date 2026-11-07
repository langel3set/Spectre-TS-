<?php

/**
 * Inicia una sesión segura.
 */
function iniciarSesionSegura(): void
{
    if (session_status() === PHP_SESSION_NONE) {

        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $https,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        session_start();
    }
}

/**
 * Regenera el ID de sesión.
 */
function regenerarSesion(): void
{
    iniciarSesionSegura();

    if (!isset($_SESSION['__regenerada'])) {

        session_regenerate_id(true);
        $_SESSION['__regenerada'] = time();

    } elseif (time() - $_SESSION['__regenerada'] > 1800) {

        session_regenerate_id(true);
        $_SESSION['__regenerada'] = time();

    }
}

/**
 * ¿Hay un usuario autenticado?
 */
function usuarioLogueado(): bool
{
    iniciarSesionSegura();

    return !empty($_SESSION['usuario_id']);
}

/**
 * Obliga a iniciar sesión.
 */
function requiereLogin(): void
{
    if (!usuarioLogueado()) {

        header('Location: /spectre_erp/src/modules/usuarios/views/login.php');
        exit;

    }
}

/**
 * Verifica que el usuario tenga uno de los roles permitidos.
 */
function requiereRol(string ...$roles): void
{
    requiereLogin();

    if (!in_array($_SESSION['usuario_rol'] ?? '', $roles, true)) {

        http_response_code(403);
        require __DIR__ . '/../modules/general/views/403.php';
        exit;

    }
}

/**
 * Devuelve los datos del usuario autenticado.
 */
function usuarioActual(): array
{
    iniciarSesionSegura();

    return [

        'id'        => $_SESSION['usuario_id'] ?? null,
        'nombre'    => $_SESSION['usuario_nombre'] ?? '',
        'apellido'  => $_SESSION['usuario_apellido'] ?? '',
        'correo'    => $_SESSION['usuario_correo'] ?? '',
        'rol'       => $_SESSION['usuario_rol'] ?? ''

    ];
}

/**
 * Obtiene el rol actual.
 */
function rolActual(): string
{
    iniciarSesionSegura();

    return $_SESSION['usuario_rol'] ?? '';
}

/**
 * Verifica si pertenece al personal.
 */
function esPersonal(): bool
{
    return in_array(
        rolActual(),
        ['gerente', 'trabajador'],
        true
    );
}

/**
 * Inicia la sesión del usuario.
 */
function loginUsuario(array $usuario): void
{
    iniciarSesionSegura();

    session_regenerate_id(true);

    $_SESSION['usuario_id'] = $usuario['id'];
    $_SESSION['usuario_nombre'] = $usuario['nombre'];
    $_SESSION['usuario_apellido'] = $usuario['apellido'];
    $_SESSION['usuario_correo'] = $usuario['correo'];
    $_SESSION['usuario_rol'] = $usuario['rol'];

    $_SESSION['ultimo_acceso'] = time();
}

/**
 * Cierra completamente la sesión.
 */
function cerrarSesion(): void
{
    iniciarSesionSegura();

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
}

/**
 * Verifica tiempo de inactividad.
 */
function verificarTimeout(int $minutos = 30): void
{
    iniciarSesionSegura();

    if (!usuarioLogueado()) {
        return;
    }

    if (
        isset($_SESSION['ultimo_acceso']) &&
        (time() - $_SESSION['ultimo_acceso']) > ($minutos * 60)
    ) {

        cerrarSesion();

        header('Location: /spectre_erp/src/modules/usuarios/views/login.php');
        exit;

    }

    $_SESSION['ultimo_acceso'] = time();
}