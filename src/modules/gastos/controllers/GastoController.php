<?php
declare(strict_types=1);

/**
 * src/modules/gastos/controllers/GastoController.php
 *
 * Endpoints del módulo de Gastos, usados desde el panel (dashboard.php).
 * Mismos permisos que ventas/inventario: gerente y trabajador pueden ver
 * y registrar; solo gerente puede eliminar.
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);
ob_start();

require_once __DIR__ . '/../../../../autoload.php';

use Dompdf\Dompdf;

set_exception_handler(function (\Throwable $e): void {
    error_log('[GastoController] Excepción no controlada: ' . $e->getMessage());
    while (ob_get_level() > 0) ob_end_clean();
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
});

class GastoController
{
    private Gasto $model;

    /** Columnas exportables (clave => [ etiqueta, callback de formato opcional ]) */
    private const COLUMNAS_EXPORT = [
        'id_gasto'     => 'ID',
        'concepto'     => 'Concepto',
        'descripcion'  => 'Descripción',
        'categoria'    => 'Categoría',
        'monto'        => 'Monto (S/)',
        'fecha_gasto'  => 'Fecha del gasto',
        'metodo_pago'  => 'Método de pago',
        'usuario'      => 'Registrado por',
        'fecha_registro' => 'Fecha de registro',
    ];

    public function __construct()
    {
        iniciarSesionSegura();
        $this->model = new Gasto();
    }

    public function listar(): void
    {
        $this->soloPersonal();
        $filtros = $this->leerFiltros();
        $this->json(true, '', ['items' => $this->model->listar(...$filtros)]);
    }

    public function resumen(): void
    {
        $this->soloPersonal();
        $desde = trim((string)($_GET['desde'] ?? '')) ?: null;
        $hasta = trim((string)($_GET['hasta'] ?? '')) ?: null;
        $this->json(true, '', [
            'resumen'    => $this->model->resumen($desde, $hasta),
            'categorias' => Gasto::CATEGORIAS,
        ]);
    }

    public function crear(): void
    {
        $this->soloPost();
        $this->soloPersonal();
        $this->verificarCSRFOFallar();

        $rutaComprobante = $this->manejarSubidaComprobante();

        $resultado = $this->model->crear([
            'concepto'         => $_POST['concepto'] ?? '',
            'descripcion'      => $_POST['descripcion'] ?? '',
            'categoria'        => $_POST['categoria'] ?? 'otros',
            'monto'            => $_POST['monto'] ?? 0,
            'fecha_gasto'      => $_POST['fecha_gasto'] ?? '',
            'metodo_pago'      => $_POST['metodo_pago'] ?? '',
            'comprobante_ruta' => $rutaComprobante,
            'id_usuario'       => $_SESSION['usuario_id'] ?? null,
        ]);

        $this->json($resultado['ok'], $resultado['message'], ['id_gasto' => $resultado['id_gasto'] ?? null]);
    }

    public function actualizar(): void
    {
        $this->soloPost();
        $this->soloPersonal();
        $this->verificarCSRFOFallar();

        $id = (int)($_POST['id_gasto'] ?? 0);
        if ($id <= 0) {
            $this->json(false, 'ID de gasto inválido.', [], 400);
        }

        $resultado = $this->model->actualizar($id, [
            'concepto'    => $_POST['concepto'] ?? '',
            'descripcion' => $_POST['descripcion'] ?? '',
            'categoria'   => $_POST['categoria'] ?? 'otros',
            'monto'       => $_POST['monto'] ?? 0,
            'fecha_gasto' => $_POST['fecha_gasto'] ?? '',
            'metodo_pago' => $_POST['metodo_pago'] ?? '',
        ]);

        $this->json($resultado['ok'], $resultado['message']);
    }

    public function eliminar(): void
    {
        $this->soloPost();
        $this->soloGerente();
        $this->verificarCSRFOFallar();

        $id = (int)($_POST['id_gasto'] ?? 0);
        $resultado = $this->model->eliminar($id);
        $this->json($resultado['ok'], $resultado['message']);
    }

    public function exportarPdf(): void
    {
        $this->soloPersonal();

        $filtros    = $this->leerFiltros();
        $columnas   = $this->leerColumnasSeleccionadas();
        $items      = $this->model->listar(...$filtros);
        $resumen    = $this->model->resumen($filtros[0], $filtros[1]);

        while (ob_get_level() > 0) ob_end_clean();

        $dompdf = new Dompdf();

        $filasHtml = '';
        foreach ($items as $g) {
            $filasHtml .= '<tr>';
            foreach ($columnas as $col) {
                $valor = $this->valorColumna($g, $col);
                $filasHtml .= '<td>' . htmlspecialchars((string)$valor) . '</td>';
            }
            $filasHtml .= '</tr>';
        }

        $encabezadosHtml = '';
        foreach ($columnas as $col) {
            $encabezadosHtml .= '<th>' . htmlspecialchars(self::COLUMNAS_EXPORT[$col] ?? $col) . '</th>';
        }

        $rangoTexto = ($filtros[0] || $filtros[1])
            ? 'Del ' . ($filtros[0] ?: '—') . ' al ' . ($filtros[1] ?: '—')
            : 'Historial completo';

        $html = <<<HTML
        <html>
        <head><meta charset="UTF-8"><style>
            body { font-family: Arial, sans-serif; font-size: 12px; }
            h1 { color: #4f6ef7; margin-bottom: 0; }
            p.sub { color: #666; margin-top: 4px; }
            table { width: 100%; border-collapse: collapse; margin-top: 16px; }
            th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
            th { background: #f0f0f0; }
            .total { font-size: 1.1em; font-weight: bold; margin-top: 16px; }
        </style></head>
        <body>
            <h1>Reporte de Gastos — SPECTRE</h1>
            <p class="sub">{$rangoTexto} — Generado el {$this->fechaHoy()}</p>
            <p class="total">Total del periodo: S/ {$this->fmt($resumen['total_periodo'])} &nbsp;|&nbsp; Cantidad de registros: {$resumen['cantidad']}</p>
            <table>
                <thead><tr>{$encabezadosHtml}</tr></thead>
                <tbody>{$filasHtml}</tbody>
            </table>
        </body>
        </html>
        HTML;

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        $dompdf->stream('reporte_gastos_' . date('Ymd_His') . '.pdf', ['Attachment' => true]);
        exit;
    }

    public function exportarExcel(): void
    {
        $this->soloPersonal();

        $filtros  = $this->leerFiltros();
        $columnas = $this->leerColumnasSeleccionadas();
        $items    = $this->model->listar(...$filtros);

        while (ob_get_level() > 0) ob_end_clean();

        $encabezados = array_map(fn($c) => self::COLUMNAS_EXPORT[$c] ?? $c, $columnas);
        $filas = [];
        foreach ($items as $g) {
            $fila = [];
            foreach ($columnas as $col) {
                $fila[] = $this->valorColumna($g, $col);
            }
            $filas[] = $fila;
        }

        ExcelWriter::descargar('reporte_gastos_' . date('Ymd_His'), $encabezados, $filas);
    }

    // ==================== HELPERS ====================

    private function valorColumna(array $g, string $col)
    {
        if ($col === 'usuario') {
            return trim(($g['usuario_nombre'] ?? '') . ' ' . ($g['usuario_apellido'] ?? '')) ?: '—';
        }
        if ($col === 'categoria') {
            return Gasto::CATEGORIAS[$g['categoria']] ?? $g['categoria'];
        }
        if ($col === 'monto') {
            return number_format((float)$g['monto'], 2);
        }
        return $g[$col] ?? '';
    }

    private function fmt(float $n): string
    {
        return number_format($n, 2);
    }

    private function fechaHoy(): string
    {
        return date('d/m/Y H:i');
    }

    /** @return array{0:?string,1:?string,2:?string,3:?string} */
    private function leerFiltros(): array
    {
        return [
            trim((string)($_GET['desde'] ?? '')) ?: null,
            trim((string)($_GET['hasta'] ?? '')) ?: null,
            trim((string)($_GET['categoria'] ?? '')) ?: null,
            trim((string)($_GET['q'] ?? '')) ?: null,
        ];
    }

    private function leerColumnasSeleccionadas(): array
    {
        $solicitadas = $_GET['columnas'] ?? array_keys(self::COLUMNAS_EXPORT);
        if (is_string($solicitadas)) {
            $solicitadas = explode(',', $solicitadas);
        }
        $validas = array_intersect($solicitadas, array_keys(self::COLUMNAS_EXPORT));
        return $validas ?: array_keys(self::COLUMNAS_EXPORT);
    }

    private function manejarSubidaComprobante(): ?string
    {
        if (!isset($_FILES['comprobante']) || $_FILES['comprobante']['error'] !== UPLOAD_ERR_OK) {
            return null;
        }
        $carpeta = __DIR__ . '/../../../../uploads/gastos/';
        if (!is_dir($carpeta)) {
            mkdir($carpeta, 0777, true);
        }
        $extension = pathinfo($_FILES['comprobante']['name'], PATHINFO_EXTENSION);
        $nombreArchivo = 'gasto_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $extension;
        $rutaCompleta = $carpeta . $nombreArchivo;

        if (move_uploaded_file($_FILES['comprobante']['tmp_name'], $rutaCompleta)) {
            return 'uploads/gastos/' . $nombreArchivo;
        }
        return null;
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
$ctrl   = new GastoController();
$accion = $_GET['accion'] ?? '';

match ($accion) {
    'listar'         => $ctrl->listar(),
    'resumen'        => $ctrl->resumen(),
    'crear'          => $ctrl->crear(),
    'actualizar'     => $ctrl->actualizar(),
    'eliminar'       => $ctrl->eliminar(),
    'exportar_pdf'   => $ctrl->exportarPdf(),
    'exportar_excel' => $ctrl->exportarExcel(),
    default          => http_response_code(404),
};
