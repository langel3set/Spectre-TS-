<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Composer
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Variables de entorno (.env)
|--------------------------------------------------------------------------
*/

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

/*
|--------------------------------------------------------------------------
| Configuración
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/src/config/database.php';
require_once __DIR__ . '/src/config/auth.php';
require_once __DIR__ . '/src/config/csrf.php';

/*
|--------------------------------------------------------------------------
| Autoload de clases del proyecto
|--------------------------------------------------------------------------
*/

spl_autoload_register(function (string $class): void {

    $class = str_replace('\\', DIRECTORY_SEPARATOR, $class);

    $rutas = [

        __DIR__ . '/src/modules/usuarios/models/' . basename($class) . '.php',

        __DIR__ . '/src/modules/usuarios/controllers/' . basename($class) . '.php',

        __DIR__ . '/src/modules/productos/models/' . basename($class) . '.php',

        __DIR__ . '/src/modules/productos/controllers/' . basename($class) . '.php',

        __DIR__ . '/src/modules/inventario/models/' . basename($class) . '.php',

        __DIR__ . '/src/modules/inventario/controllers/' . basename($class) . '.php',

        __DIR__ . '/src/modules/ventas/models/' . basename($class) . '.php',

        __DIR__ . '/src/modules/ventas/controllers/' . basename($class) . '.php',

        __DIR__ . '/src/modules/servicios/models/' . basename($class) . '.php',

        __DIR__ . '/src/modules/servicios/controllers/' . basename($class) . '.php',

        __DIR__ . '/src/modules/gastos/models/' . basename($class) . '.php',

        __DIR__ . '/src/modules/gastos/controllers/' . basename($class) . '.php',

        __DIR__ . '/src/modules/reportes/models/' . basename($class) . '.php',

        __DIR__ . '/src/modules/reportes/controllers/' . basename($class) . '.php',

        __DIR__ . '/src/modules/soporte/models/' . basename($class) . '.php',

        __DIR__ . '/src/modules/soporte/controllers/' . basename($class) . '.php',

        __DIR__ . '/src/modules/notas/models/' . basename($class) . '.php',

        __DIR__ . '/src/modules/notas/controllers/' . basename($class) . '.php',

        __DIR__ . '/src/services/' . basename($class) . '.php',

        __DIR__ . '/src/helpers/' . basename($class) . '.php',

    ];

    foreach ($rutas as $ruta) {

        if (is_file($ruta)) {

            require_once $ruta;
            return;

        }

    }

});