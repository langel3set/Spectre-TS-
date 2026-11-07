<?php
declare(strict_types=1);

/**
 * src/modules/notas/controllers/NotaController.php
 *
 * Endpoints del módulo Notas (Base de Conocimiento Técnica), consumidos
 * desde el panel "notas" en dashboard.php vía fetch() + JSON.
 *
 * Permisos (mapeados a los 2 roles que existen en el sistema, ya que el
 * ERP no tiene un tercer rol "supervisor"):
 *   - gerente:    todo, incluyendo eliminar procedimientos/categorías e importar.
 *   - trabajador: leer, buscar, copiar, favoritos, crear/editar procedimientos
 *                 y exportar (son quienes documentan soluciones en el día a día),
 *                 pero NO pueden eliminar ni gestionar categorías ni importar.
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);
ob_start();

require_once __DIR__ . '/../../../../autoload.php';

use Dompdf\Dompdf;

set_exception_handler(function (\Throwable $e): void {
    error_log('[NotaController] Excepción no controlada: ' . $e->getMessage());
    while (ob_get_level() > 0) ob_end_clean();
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
});

class NotaController
{
    private NotaProcedimiento $model;
    private NotaCategoria $modelCategorias;

    private const EXTENSIONES_PERMITIDAS = [
        'zip', 'rar', '7z', 'bat', 'cmd', 'reg', 'xml', 'exe', 'pdf',
        'png', 'jpg', 'jpeg', 'gif', 'webp', 'docx', 'xlsx', 'pptx',
        'txt', 'md', 'json', 'ps1', 'sh',
    ];

    private const TAMANO_MAXIMO_BYTES = 25 * 1024 * 1024; // 25 MB

    public function __construct()
    {
        iniciarSesionSegura();
        $this->model = new NotaProcedimiento();
        $this->modelCategorias = new NotaCategoria();
    }

    // ==================== CATEGORÍAS ====================

    public function categorias(): void
    {
        $this->soloPersonal();
        $this->json(true, '', ['categorias' => $this->modelCategorias->listarTodas()]);
    }

    public function categoriaCrear(): void
    {
        $this->soloPost();
        $this->soloGerente(); // gestión del árbol de categorías: solo administrador
        $this->verificarCSRFOFallar();

        $resultado = $this->modelCategorias->crear([
            'nombre'             => $_POST['nombre'] ?? '',
            'icono'              => $_POST['icono'] ?? '',
            'color'              => $_POST['color'] ?? '',
            'id_categoria_padre' => $_POST['id_categoria_padre'] ?? null,
            'orden'              => $_POST['orden'] ?? 0,
        ]);
        $this->json($resultado['ok'], $resultado['message'], ['id_categoria' => $resultado['id_categoria'] ?? null]);
    }

    public function categoriaActualizar(): void
    {
        $this->soloPost();
        $this->soloGerente();
        $this->verificarCSRFOFallar();

        $id = (int)($_POST['id_categoria'] ?? 0);
        $resultado = $this->modelCategorias->actualizar($id, [
            'nombre'             => $_POST['nombre'] ?? '',
            'icono'              => $_POST['icono'] ?? '',
            'color'              => $_POST['color'] ?? '',
            'id_categoria_padre' => $_POST['id_categoria_padre'] ?? null,
            'orden'              => $_POST['orden'] ?? 0,
        ]);
        $this->json($resultado['ok'], $resultado['message']);
    }

    public function categoriaEliminar(): void
    {
        $this->soloPost();
        $this->soloGerente();
        $this->verificarCSRFOFallar();

        $id = (int)($_POST['id_categoria'] ?? 0);
        $resultado = $this->modelCategorias->eliminar($id);
        $this->json($resultado['ok'], $resultado['message']);
    }

    // ==================== PROCEDIMIENTOS ====================

    public function listar(): void
    {
        $this->soloPersonal();

        $filtros = [
            'categoria'  => !empty($_GET['categoria']) ? (int)$_GET['categoria'] : null,
            'busqueda'   => trim((string)($_GET['q'] ?? '')) ?: null,
            'favoritos'  => !empty($_GET['favoritos']),
            'autor'      => !empty($_GET['autor']) ? (int)$_GET['autor'] : null,
            'estado'     => trim((string)($_GET['estado'] ?? '')) ?: null,
            'etiqueta'   => !empty($_GET['etiqueta']) ? (int)$_GET['etiqueta'] : null,
            'orden'      => trim((string)($_GET['orden'] ?? '')) ?: 'recientes',
            'id_usuario' => $_SESSION['usuario_id'] ?? null,
        ];

        $this->json(true, '', ['items' => $this->model->listar($filtros)]);
    }

    public function detalle(): void
    {
        $this->soloPersonal();

        $id = (int)($_GET['id_procedimiento'] ?? 0);
        $idUsuario = $_SESSION['usuario_id'] ?? null;

        $proc = $this->model->obtenerCompleto($id, $idUsuario);
        if (!$proc) {
            $this->json(false, 'Procedimiento no encontrado.', [], 404);
        }

        // Solo registramos "abrir" cuando no se pide explícitamente omitirlo
        // (el modal de edición carga el detalle pero no debe contar como "uso").
        if (empty($_GET['sin_contar'])) {
            $this->model->registrarUso($id, 'abrir', $idUsuario);
        }

        $this->json(true, '', ['procedimiento' => $proc]);
    }

    public function crear(): void
    {
        $this->soloPost();
        $this->soloPersonal();
        $this->verificarCSRFOFallar();

        $datos = $this->leerDatosProcedimientoDesdePost();
        $datos['id_usuario_autor'] = $_SESSION['usuario_id'] ?? null;

        $resultado = $this->model->crear($datos);
        $this->json($resultado['ok'], $resultado['message'], ['id_procedimiento' => $resultado['id_procedimiento'] ?? null]);
    }

    public function actualizar(): void
    {
        $this->soloPost();
        $this->soloPersonal();
        $this->verificarCSRFOFallar();

        $id = (int)($_POST['id_procedimiento'] ?? 0);
        if ($id <= 0) {
            $this->json(false, 'ID de procedimiento inválido.', [], 400);
        }

        $datos = $this->leerDatosProcedimientoDesdePost();
        $datos['id_usuario_modifico'] = $_SESSION['usuario_id'] ?? null;

        $resultado = $this->model->actualizar($id, $datos);
        $this->json($resultado['ok'], $resultado['message']);
    }

    public function eliminar(): void
    {
        $this->soloPost();
        $this->soloGerente(); // eliminar procedimientos: solo administrador
        $this->verificarCSRFOFallar();

        $id = (int)($_POST['id_procedimiento'] ?? 0);

        // Borrar primero los archivos físicos de los adjuntos
        $proc = $this->model->obtenerCompleto($id);
        if ($proc) {
            foreach ($proc['adjuntos'] as $adjunto) {
                $ruta = __DIR__ . '/../../../../' . $adjunto['ruta_archivo'];
                if (is_file($ruta)) {
                    @unlink($ruta);
                }
            }
        }

        $resultado = $this->model->eliminar($id);
        $this->json($resultado['ok'], $resultado['message']);
    }

    private function leerDatosProcedimientoDesdePost(): array
    {
        $pasosJson = $_POST['pasos_json'] ?? '[]';
        $pasos = json_decode((string)$pasosJson, true);
        if (!is_array($pasos)) {
            $pasos = [];
        }

        $etiquetasRaw = trim((string)($_POST['etiquetas'] ?? ''));
        $etiquetas = $etiquetasRaw !== '' ? array_map('trim', explode(',', $etiquetasRaw)) : [];

        return [
            'titulo'       => $_POST['titulo'] ?? '',
            'descripcion'  => $_POST['descripcion'] ?? '',
            'id_categoria' => $_POST['id_categoria'] ?? null,
            'icono'        => $_POST['icono'] ?? '',
            'color'        => $_POST['color'] ?? '',
            'estado'       => $_POST['estado'] ?? 'publicado',
            'pasos'        => $pasos,
            'etiquetas'    => $etiquetas,
        ];
    }

    // ==================== FAVORITOS ====================

    public function favorito(): void
    {
        $this->soloPost();
        $this->soloPersonal();
        $this->verificarCSRFOFallar();

        $id = (int)($_POST['id_procedimiento'] ?? 0);
        $idUsuario = (int)($_SESSION['usuario_id'] ?? 0);

        $resultado = $this->model->alternarFavorito($id, $idUsuario);
        $this->json($resultado['ok'], $resultado['message'], ['favorito' => $resultado['favorito']]);
    }

    // ==================== COPIAR (registro para estadísticas) ====================

    public function registrarCopia(): void
    {
        $this->soloPost();
        $this->soloPersonal();
        $this->verificarCSRFOFallar();

        $id = (int)($_POST['id_procedimiento'] ?? 0);
        $idPaso = !empty($_POST['id_paso']) ? (int)$_POST['id_paso'] : null;
        $idUsuario = $_SESSION['usuario_id'] ?? null;

        $this->model->registrarUso($id, 'copiar', $idUsuario, $idPaso);
        $this->json(true, 'Registrado.');
    }

    // ==================== VERSIONES ====================

    public function versiones(): void
    {
        $this->soloPersonal();
        $id = (int)($_GET['id_procedimiento'] ?? 0);
        $this->json(true, '', ['versiones' => $this->model->listarVersiones($id)]);
    }

    public function versionDetalle(): void
    {
        $this->soloPersonal();
        $idVersion = (int)($_GET['id_version'] ?? 0);
        $version = $this->model->obtenerVersion($idVersion);
        if (!$version) {
            $this->json(false, 'Versión no encontrada.', [], 404);
        }
        $this->json(true, '', ['version' => $version]);
    }

    // ==================== ESTADÍSTICAS / RECIENTES ====================

    public function resumen(): void
    {
        $this->soloPersonal();
        $this->json(true, '', ['resumen' => $this->model->estadisticas()]);
    }

    public function recientes(): void
    {
        $this->soloPersonal();
        $idUsuario = (int)($_SESSION['usuario_id'] ?? 0);
        $this->json(true, '', ['items' => $this->model->recientesPorUsuario($idUsuario)]);
    }

    // ==================== ADJUNTOS ====================

    public function adjuntoSubir(): void
    {
        $this->soloPost();
        $this->soloPersonal();
        $this->verificarCSRFOFallar();

        $idProcedimiento = (int)($_POST['id_procedimiento'] ?? 0);
        if ($idProcedimiento <= 0) {
            $this->json(false, 'Debes guardar el procedimiento antes de adjuntar archivos.', [], 400);
        }

        if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
            $this->json(false, 'No se recibió ningún archivo válido.', [], 400);
        }

        if ($_FILES['archivo']['size'] > self::TAMANO_MAXIMO_BYTES) {
            $this->json(false, 'El archivo supera el tamaño máximo permitido (25 MB).', [], 400);
        }

        $extension = strtolower(pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, self::EXTENSIONES_PERMITIDAS, true)) {
            $this->json(false, 'Tipo de archivo no permitido (.' . $extension . ').', [], 400);
        }

        $carpeta = __DIR__ . '/../../../../uploads/notas/';
        if (!is_dir($carpeta)) {
            mkdir($carpeta, 0777, true);
        }

        $nombreArchivo = 'notas_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        $rutaCompleta = $carpeta . $nombreArchivo;

        if (!move_uploaded_file($_FILES['archivo']['tmp_name'], $rutaCompleta)) {
            $this->json(false, 'No se pudo guardar el archivo en el servidor.', [], 500);
        }

        $idAdjunto = $this->model->registrarAdjunto([
            'id_procedimiento' => $idProcedimiento,
            'id_paso'          => !empty($_POST['id_paso']) ? (int)$_POST['id_paso'] : null,
            'nombre_original'  => $_FILES['archivo']['name'],
            'ruta_archivo'     => 'uploads/notas/' . $nombreArchivo,
            'tipo_mime'        => $_FILES['archivo']['type'] ?? null,
            'tamano_bytes'     => $_FILES['archivo']['size'] ?? null,
            'id_usuario_subio' => $_SESSION['usuario_id'] ?? null,
        ]);

        $this->json(true, 'Archivo adjuntado correctamente.', [
            'id_adjunto' => $idAdjunto,
            'ruta'       => 'uploads/notas/' . $nombreArchivo,
        ]);
    }

    public function adjuntoEliminar(): void
    {
        $this->soloPost();
        $this->soloPersonal();
        $this->verificarCSRFOFallar();

        $idAdjunto = (int)($_POST['id_adjunto'] ?? 0);
        $adjunto = $this->model->eliminarAdjunto($idAdjunto);

        if (!$adjunto) {
            $this->json(false, 'Adjunto no encontrado.', [], 404);
        }

        $ruta = __DIR__ . '/../../../../' . $adjunto['ruta_archivo'];
        if (is_file($ruta)) {
            @unlink($ruta);
        }

        $this->json(true, 'Adjunto eliminado correctamente.');
    }

    // ==================== EXPORTAR ====================

    public function exportarPdf(): void
    {
        $this->soloPersonal();
        $id = (int)($_GET['id_procedimiento'] ?? 0);
        $proc = $this->model->obtenerCompleto($id, $_SESSION['usuario_id'] ?? null);
        if (!$proc) {
            $this->json(false, 'Procedimiento no encontrado.', [], 404);
        }

        $this->model->registrarUso($id, 'exportar', $_SESSION['usuario_id'] ?? null);

        while (ob_get_level() > 0) ob_end_clean();

        $pasosHtml = '';
        foreach ($proc['pasos'] as $i => $paso) {
            $pasosHtml .= '<h3>Paso ' . ($i + 1) . ($paso['titulo'] ? ': ' . htmlspecialchars($paso['titulo']) : '') . '</h3>';
            if ($paso['descripcion']) {
                $pasosHtml .= '<p>' . nl2br(htmlspecialchars($paso['descripcion'])) . '</p>';
            }
            if (in_array($paso['tipo'], NotaProcedimiento::TIPOS_CODIGO, true) && $paso['contenido']) {
                $pasosHtml .= '<pre>' . htmlspecialchars($paso['contenido']) . '</pre>';
            } elseif ($paso['tipo'] === 'checklist') {
                $pasosHtml .= '<ul>';
                foreach ($paso['checklist_items'] as $item) {
                    $pasosHtml .= '<li>☐ ' . htmlspecialchars($item['texto']) . '</li>';
                }
                $pasosHtml .= '</ul>';
            } elseif ($paso['tipo'] === 'enlace' && $paso['contenido']) {
                $pasosHtml .= '<p><a href="' . htmlspecialchars($paso['contenido']) . '">' . htmlspecialchars($paso['contenido']) . '</a></p>';
            } elseif ($paso['contenido']) {
                $pasosHtml .= '<p>' . nl2br(htmlspecialchars($paso['contenido'])) . '</p>';
            }
        }

        $etiquetasTexto = implode(', ', array_column($proc['etiquetas'], 'nombre'));

        $html = <<<HTML
        <html>
        <head><meta charset="UTF-8"><style>
            body { font-family: Arial, sans-serif; font-size: 12px; color:#222; }
            h1 { color: #4f6ef7; margin-bottom: 0; }
            p.sub { color: #666; margin-top: 4px; }
            h3 { color: #333; margin-top: 18px; margin-bottom: 4px; }
            pre { background: #f4f4f4; border: 1px solid #ddd; padding: 8px 10px; border-radius: 4px; white-space: pre-wrap; font-family: "Courier New", monospace; font-size: 11px; }
            .meta { font-size: 11px; color: #888; margin-top: 8px; }
        </style></head>
        <body>
            <h1>{$proc['titulo']}</h1>
            <p class="sub">{$proc['categoria_nombre']} — Generado el {$this->fechaHoy()}</p>
            {$this->parrafo($proc['descripcion'])}
            {$pasosHtml}
            <p class="meta">Etiquetas: {$etiquetasTexto}</p>
        </body>
        </html>
        HTML;

        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $dompdf->stream($this->nombreArchivoSeguro($proc['titulo']) . '.pdf', ['Attachment' => true]);
        exit;
    }

    public function exportarHtml(): void
    {
        $this->soloPersonal();
        $proc = $this->obtenerProcedimientoParaExportarOFallar();
        $html = $this->generarHtmlExportable($proc);

        while (ob_get_level() > 0) ob_end_clean();
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $this->nombreArchivoSeguro($proc['titulo']) . '.html"');
        echo $html;
        exit;
    }

    public function exportarWord(): void
    {
        $this->soloPersonal();
        $proc = $this->obtenerProcedimientoParaExportarOFallar();
        // Truco estándar: Word abre perfectamente un HTML con la extensión .doc
        $html = $this->generarHtmlExportable($proc, true);

        while (ob_get_level() > 0) ob_end_clean();
        header('Content-Type: application/msword; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $this->nombreArchivoSeguro($proc['titulo']) . '.doc"');
        echo $html;
        exit;
    }

    public function exportarMarkdown(): void
    {
        $this->soloPersonal();
        $proc = $this->obtenerProcedimientoParaExportarOFallar();

        $md = "# {$proc['titulo']}\n\n";
        if ($proc['descripcion']) {
            $md .= $proc['descripcion'] . "\n\n";
        }
        $md .= "_Categoría: {$proc['categoria_nombre']}_\n\n";

        foreach ($proc['pasos'] as $i => $paso) {
            $n = $i + 1;
            $tituloPaso = $paso['titulo'] ? ": {$paso['titulo']}" : '';
            $md .= "## Paso {$n}{$tituloPaso}\n\n";
            if ($paso['descripcion']) {
                $md .= $paso['descripcion'] . "\n\n";
            }
            if (in_array($paso['tipo'], NotaProcedimiento::TIPOS_CODIGO, true) && $paso['contenido']) {
                $lang = $paso['lenguaje'] ?: $paso['tipo'];
                $md .= "```{$lang}\n{$paso['contenido']}\n```\n\n";
            } elseif ($paso['tipo'] === 'checklist') {
                foreach ($paso['checklist_items'] as $item) {
                    $md .= "- [ ] {$item['texto']}\n";
                }
                $md .= "\n";
            } elseif ($paso['contenido']) {
                $md .= $paso['contenido'] . "\n\n";
            }
        }

        if (!empty($proc['etiquetas'])) {
            $md .= "\n---\nEtiquetas: " . implode(', ', array_map(fn($e) => '#' . $e['nombre'], $proc['etiquetas'])) . "\n";
        }

        while (ob_get_level() > 0) ob_end_clean();
        header('Content-Type: text/markdown; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $this->nombreArchivoSeguro($proc['titulo']) . '.md"');
        echo $md;
        exit;
    }

    public function exportarTxt(): void
    {
        $this->soloPersonal();
        $proc = $this->obtenerProcedimientoParaExportarOFallar();

        $txt = strtoupper($proc['titulo']) . "\n" . str_repeat('=', mb_strlen($proc['titulo'])) . "\n\n";
        if ($proc['descripcion']) {
            $txt .= $proc['descripcion'] . "\n\n";
        }

        foreach ($proc['pasos'] as $i => $paso) {
            $n = $i + 1;
            $txt .= "PASO {$n}" . ($paso['titulo'] ? ' - ' . $paso['titulo'] : '') . "\n";
            $txt .= str_repeat('-', 40) . "\n";
            if ($paso['descripcion']) {
                $txt .= $paso['descripcion'] . "\n";
            }
            if ($paso['tipo'] === 'checklist') {
                foreach ($paso['checklist_items'] as $item) {
                    $txt .= "[ ] {$item['texto']}\n";
                }
            } elseif ($paso['contenido']) {
                $txt .= $paso['contenido'] . "\n";
            }
            $txt .= "\n";
        }

        while (ob_get_level() > 0) ob_end_clean();
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $this->nombreArchivoSeguro($proc['titulo']) . '.txt"');
        echo $txt;
        exit;
    }

    private function obtenerProcedimientoParaExportarOFallar(): array
    {
        $id = (int)($_GET['id_procedimiento'] ?? 0);
        $proc = $this->model->obtenerCompleto($id, $_SESSION['usuario_id'] ?? null);
        if (!$proc) {
            $this->json(false, 'Procedimiento no encontrado.', [], 404);
        }
        $this->model->registrarUso($id, 'exportar', $_SESSION['usuario_id'] ?? null);
        return $proc;
    }

    private function generarHtmlExportable(array $proc, bool $paraWord = false): string
    {
        $pasosHtml = '';
        foreach ($proc['pasos'] as $i => $paso) {
            $pasosHtml .= '<h3>Paso ' . ($i + 1) . ($paso['titulo'] ? ': ' . htmlspecialchars($paso['titulo']) : '') . '</h3>';
            if ($paso['descripcion']) {
                $pasosHtml .= '<p>' . nl2br(htmlspecialchars($paso['descripcion'])) . '</p>';
            }
            if (in_array($paso['tipo'], NotaProcedimiento::TIPOS_CODIGO, true) && $paso['contenido']) {
                $pasosHtml .= '<pre style="background:#f4f4f4;border:1px solid #ddd;padding:10px;border-radius:4px;">' . htmlspecialchars($paso['contenido']) . '</pre>';
            } elseif ($paso['tipo'] === 'checklist') {
                $pasosHtml .= '<ul>';
                foreach ($paso['checklist_items'] as $item) {
                    $pasosHtml .= '<li>&#9744; ' . htmlspecialchars($item['texto']) . '</li>';
                }
                $pasosHtml .= '</ul>';
            } elseif ($paso['contenido']) {
                $pasosHtml .= '<p>' . nl2br(htmlspecialchars($paso['contenido'])) . '</p>';
            }
        }

        $cabecera = $paraWord
            ? '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40"><head><meta charset="UTF-8"><title>' . htmlspecialchars($proc['titulo']) . '</title></head><body>'
            : '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>' . htmlspecialchars($proc['titulo']) . '</title>'
              . '<style>body{font-family:Arial,sans-serif;max-width:800px;margin:2rem auto;color:#222}h1{color:#4f6ef7}pre{white-space:pre-wrap}</style></head><body>';

        return $cabecera
            . '<h1>' . htmlspecialchars($proc['titulo']) . '</h1>'
            . '<p><em>' . htmlspecialchars($proc['categoria_nombre']) . '</em></p>'
            . ($proc['descripcion'] ? '<p>' . nl2br(htmlspecialchars($proc['descripcion'])) . '</p>' : '')
            . $pasosHtml
            . '</body></html>';
    }

    // ==================== IMPORTAR ====================

    /**
     * Importador simple desde texto plano o Markdown.
     * Reconoce: "# Título" (H1), "## Paso N" (H2 -> nuevo paso) y
     * bloques de código con ``` (con o sin lenguaje). El resto del texto
     * se agrega como pasos de tipo "texto".
     *
     * Word y HTML NO se procesan automáticamente por seguridad y complejidad
     * (requerirían librerías adicionales); se recomienda copiar el contenido
     * y pegarlo aquí, o exportarlo primero a Markdown/TXT.
     */
    public function importar(): void
    {
        $this->soloPost();
        $this->soloGerente(); // importación masiva: solo administrador
        $this->verificarCSRFOFallar();

        $contenido = (string)($_POST['contenido'] ?? '');
        $idCategoria = (int)($_POST['id_categoria'] ?? 0);

        if (trim($contenido) === '') {
            $this->json(false, 'No hay contenido para importar.', [], 400);
        }
        if ($idCategoria <= 0) {
            $this->json(false, 'Selecciona una categoría de destino.', [], 400);
        }

        $parseado = $this->parsearTextoImportado($contenido);

        $resultado = $this->model->crear([
            'titulo'            => $parseado['titulo'] ?: 'Procedimiento importado ' . date('d/m/Y H:i'),
            'descripcion'       => $parseado['descripcion'],
            'id_categoria'      => $idCategoria,
            'estado'            => 'borrador', // se importa como borrador para revisión manual
            'pasos'             => $parseado['pasos'],
            'id_usuario_autor'  => $_SESSION['usuario_id'] ?? null,
        ]);

        $this->json($resultado['ok'], $resultado['message'], ['id_procedimiento' => $resultado['id_procedimiento'] ?? null]);
    }

    private function parsearTextoImportado(string $texto): array
    {
        $texto = str_replace("\r\n", "\n", $texto);
        $lineas = explode("\n", $texto);

        $titulo = '';
        $descripcion = '';
        $pasos = [];

        $pasoActual = null;
        $enBloqueCodigo = false;
        $lenguajeBloque = '';
        $bufferCodigo = [];
        $bufferTexto = [];

        $cerrarPasoActual = function () use (&$pasoActual, &$pasos, &$bufferTexto) {
            if ($pasoActual !== null) {
                if (!empty($bufferTexto)) {
                    $pasoActual['descripcion'] = trim(($pasoActual['descripcion'] ?? '') . "\n" . implode("\n", $bufferTexto));
                }
                $pasos[] = $pasoActual;
            }
            $pasoActual = null;
            $bufferTexto = [];
        };

        foreach ($lineas as $linea) {
            $trim = rtrim($linea);

            // Bloque de código ```lenguaje ... ```
            if (str_starts_with(trim($trim), '```')) {
                if (!$enBloqueCodigo) {
                    $enBloqueCodigo = true;
                    $lenguajeBloque = trim(substr(trim($trim), 3));
                    $bufferCodigo = [];
                } else {
                    $enBloqueCodigo = false;
                    $tipoDetectado = $this->mapearLenguajeATipo($lenguajeBloque);
                    $pasos[] = [
                        'titulo'      => null,
                        'descripcion' => null,
                        'tipo'        => $tipoDetectado,
                        'contenido'   => implode("\n", $bufferCodigo),
                        'lenguaje'    => $lenguajeBloque ?: null,
                    ];
                    $bufferCodigo = [];
                }
                continue;
            }
            if ($enBloqueCodigo) {
                $bufferCodigo[] = $linea;
                continue;
            }

            // H1 -> título del procedimiento
            if (str_starts_with($trim, '# ')) {
                $titulo = trim(substr($trim, 2));
                continue;
            }

            // H2 -> nuevo paso
            if (str_starts_with($trim, '## ')) {
                $cerrarPasoActual();
                $tituloPaso = trim(substr($trim, 3));
                $tituloPaso = preg_replace('/^Paso\s*\d+\s*:?\s*/i', '', $tituloPaso) ?? $tituloPaso;
                $pasoActual = ['titulo' => $tituloPaso ?: null, 'descripcion' => null, 'tipo' => 'texto', 'contenido' => null];
                continue;
            }

            if ($trim === '' && $pasoActual === null && $titulo !== '' && empty($pasos)) {
                // línea en blanco tras el título, antes del primer paso: separador de descripción
                continue;
            }

            if ($pasoActual === null) {
                if ($trim !== '') {
                    $descripcion .= ($descripcion ? "\n" : '') . $trim;
                }
                continue;
            }

            $bufferTexto[] = $linea;
        }

        $cerrarPasoActual();

        // Si no se detectó ninguna estructura de encabezados, todo el texto
        // se importa como un único paso de tipo "texto" para no perder nada.
        if (empty($pasos) && trim($texto) !== '') {
            $pasos[] = [
                'titulo'      => null,
                'descripcion' => null,
                'tipo'        => 'texto',
                'contenido'   => trim($texto),
            ];
        }

        return ['titulo' => $titulo, 'descripcion' => trim($descripcion), 'pasos' => $pasos];
    }

    private function mapearLenguajeATipo(string $lenguaje): string
    {
        $lenguaje = strtolower(trim($lenguaje));
        return match ($lenguaje) {
            'powershell', 'ps1', 'ps' => 'powershell',
            'bash', 'sh', 'shell'     => 'bash',
            'cmd', 'bat', 'batch'     => 'cmd',
            'sql'                     => 'sql',
            'json'                    => 'json',
            'xml'                     => 'xml',
            'html'                    => 'html',
            'css'                     => 'css',
            'js', 'javascript'        => 'javascript',
            'php'                     => 'php',
            'reg'                     => 'reg',
            default                   => 'codigo',
        };
    }

    // ==================== HELPERS ====================

    private function parrafo(?string $texto): string
    {
        return $texto ? '<p>' . nl2br(htmlspecialchars($texto)) . '</p>' : '';
    }

    private function nombreArchivoSeguro(string $titulo): string
    {
        $slug = preg_replace('/[^a-zA-Z0-9]+/', '_', $titulo) ?? 'procedimiento';
        return trim($slug, '_') ?: 'procedimiento';
    }

    private function fechaHoy(): string
    {
        return date('d/m/Y H:i');
    }

    private function soloPost(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(false, 'Método no permitido.', [], 405);
        }
    }

    private function soloPersonal(): void
    {
        if (!usuarioLogueado() || !in_array($_SESSION['usuario_rol'] ?? '', ['gerente', 'trabajador'], true)) {
            $this->json(false, 'No tienes permiso para realizar esta acción.', [], 403);
        }
    }

    private function soloGerente(): void
    {
        if (!usuarioLogueado() || ($_SESSION['usuario_rol'] ?? '') !== 'gerente') {
            $this->json(false, 'Solo el administrador puede realizar esta acción.', [], 403);
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
$ctrl   = new NotaController();
$accion = $_GET['accion'] ?? '';

match ($accion) {
    'categorias'           => $ctrl->categorias(),
    'categoria_crear'      => $ctrl->categoriaCrear(),
    'categoria_actualizar' => $ctrl->categoriaActualizar(),
    'categoria_eliminar'   => $ctrl->categoriaEliminar(),

    'listar'                => $ctrl->listar(),
    'detalle'               => $ctrl->detalle(),
    'crear'                 => $ctrl->crear(),
    'actualizar'            => $ctrl->actualizar(),
    'eliminar'               => $ctrl->eliminar(),

    'favorito'              => $ctrl->favorito(),
    'registrar_copia'       => $ctrl->registrarCopia(),

    'versiones'             => $ctrl->versiones(),
    'version_detalle'       => $ctrl->versionDetalle(),

    'resumen'               => $ctrl->resumen(),
    'recientes'             => $ctrl->recientes(),

    'adjunto_subir'         => $ctrl->adjuntoSubir(),
    'adjunto_eliminar'      => $ctrl->adjuntoEliminar(),

    'exportar_pdf'          => $ctrl->exportarPdf(),
    'exportar_html'         => $ctrl->exportarHtml(),
    'exportar_word'         => $ctrl->exportarWord(),
    'exportar_markdown'     => $ctrl->exportarMarkdown(),
    'exportar_txt'          => $ctrl->exportarTxt(),

    'importar'              => $ctrl->importar(),

    default                 => http_response_code(404),
};
