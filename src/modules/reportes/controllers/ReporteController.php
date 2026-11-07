<?php
declare(strict_types=1);

/**
 * src/modules/reportes/controllers/ReporteController.php
 *
 * Endpoints del panel de Reportes (solo gerente). Genera el resumen para
 * los gráficos del dashboard y permite exportar un reporte en PDF o Excel
 * con las secciones que el usuario elija (ingresos/egresos, gastos por
 * categoría, top productos/servicios, inventario, tickets).
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);
ob_start();

require_once __DIR__ . '/../../../../autoload.php';

use Dompdf\Dompdf;

set_exception_handler(function (\Throwable $e): void {
    error_log('[ReporteController] Excepción no controlada: ' . $e->getMessage());
    while (ob_get_level() > 0) ob_end_clean();
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
});

class ReporteController
{
    private Reporte $model;

    private const SECCIONES_DISPONIBLES = [
        'balance'      => 'Balance de ingresos vs egresos',
        'movimientos'  => 'Detalle de movimientos (ventas y gastos)',
        'gastos_cat'   => 'Gastos por categoría',
        'top_productos'=> 'Productos más vendidos',
        'top_servicios'=> 'Servicios más vendidos',
        'inventario'   => 'Resumen de inventario',
        'tickets'      => 'Tickets de soporte por estado',
    ];

    public function __construct()
    {
        iniciarSesionSegura();
        $this->model = new Reporte();
    }

    public function resumen(): void
    {
        $this->soloGerente();
        [$desde, $hasta] = $this->leerRango();
        $this->json(true, '', [
            'resumen'   => $this->model->resumenGeneral($desde, $hasta),
            'secciones' => self::SECCIONES_DISPONIBLES,
        ]);
    }

    public function exportarPdf(): void
    {
        $this->soloGerente();
        [$desde, $hasta] = $this->leerRango();
        $secciones = $this->leerSecciones();
        $resumen   = $this->model->resumenGeneral($desde, $hasta);

        while (ob_get_level() > 0) ob_end_clean();

        $rangoTexto = ($desde || $hasta) ? 'Del ' . ($desde ?: '—') . ' al ' . ($hasta ?: '—') : 'Histórico completo';

        $html = '<html><head><meta charset="UTF-8"><style>
            body { font-family: Arial, sans-serif; font-size: 12px; color:#222; }
            h1 { color: #4f6ef7; margin-bottom:0; }
            h2 { color:#333; border-bottom: 2px solid #4f6ef7; padding-bottom:4px; margin-top:26px; }
            p.sub { color:#666; margin-top:4px; }
            table { width:100%; border-collapse: collapse; margin-top:10px; }
            th, td { border:1px solid #ccc; padding:6px 8px; text-align:left; }
            th { background:#f0f0f0; }
            .badge-pos { color:#059669; font-weight:bold; }
            .badge-neg { color:#dc2626; font-weight:bold; }
        </style></head><body>';

        $html .= "<h1>Reporte General — SPECTRE</h1><p class=\"sub\">{$rangoTexto} — Generado el " . date('d/m/Y H:i') . "</p>";

        if (in_array('balance', $secciones, true)) {
            $balanceClase = $resumen['balance'] >= 0 ? 'badge-pos' : 'badge-neg';
            $html .= "<h2>Balance</h2>
                <table><tr><th>Ingresos (ventas completadas)</th><th>Egresos (gastos)</th><th>Balance</th></tr>
                <tr><td>S/ " . number_format($resumen['ingresos'], 2) . "</td>
                    <td>S/ " . number_format($resumen['egresos'], 2) . "</td>
                    <td class=\"{$balanceClase}\">S/ " . number_format($resumen['balance'], 2) . "</td></tr></table>";
        }

        if (in_array('movimientos', $secciones, true)) {
            $movs = $this->model->movimientos($desde, $hasta);
            $filas = '';
            foreach ($movs as $m) {
                $filas .= '<tr><td>' . htmlspecialchars((string)$m['fecha']) . '</td>'
                        . '<td>' . htmlspecialchars((string)$m['tipo']) . '</td>'
                        . '<td>' . htmlspecialchars((string)$m['referencia']) . '</td>'
                        . '<td>S/ ' . number_format((float)$m['monto'], 2) . '</td>'
                        . '<td>' . htmlspecialchars((string)($m['metodo_pago'] ?? '—')) . '</td></tr>';
            }
            $html .= "<h2>Detalle de movimientos</h2>
                <table><tr><th>Fecha</th><th>Tipo</th><th>Referencia</th><th>Monto</th><th>Método de pago</th></tr>{$filas}</table>";
        }

        if (in_array('gastos_cat', $secciones, true)) {
            $filas = '';
            foreach ($resumen['gastos_por_categoria'] as $c) {
                $filas .= '<tr><td>' . htmlspecialchars($c['categoria_label']) . '</td><td>S/ ' . number_format($c['total'], 2) . '</td></tr>';
            }
            $html .= "<h2>Gastos por categoría</h2><table><tr><th>Categoría</th><th>Total</th></tr>{$filas}</table>";
        }

        if (in_array('top_productos', $secciones, true)) {
            $filas = '';
            foreach ($resumen['top_productos'] as $p) {
                $filas .= '<tr><td>' . htmlspecialchars($p['nombre']) . '</td><td>' . (int)$p['unidades'] . '</td><td>S/ ' . number_format((float)$p['total'], 2) . '</td></tr>';
            }
            $html .= "<h2>Productos más vendidos</h2><table><tr><th>Producto</th><th>Unidades</th><th>Total</th></tr>{$filas}</table>";
        }

        if (in_array('top_servicios', $secciones, true)) {
            $filas = '';
            foreach ($resumen['top_servicios'] as $s) {
                $filas .= '<tr><td>' . htmlspecialchars($s['nombre']) . '</td><td>' . (int)$s['unidades'] . '</td><td>S/ ' . number_format((float)$s['total'], 2) . '</td></tr>';
            }
            $html .= "<h2>Servicios más vendidos</h2><table><tr><th>Servicio</th><th>Unidades</th><th>Total</th></tr>{$filas}</table>";
        }

        if (in_array('inventario', $secciones, true)) {
            $inv = $resumen['inventario'];
            $html .= "<h2>Resumen de inventario</h2>
                <table><tr><th>Productos activos</th><th>Unidades totales</th><th>Valor de inventario</th><th>Bajo stock</th><th>Sin stock</th></tr>
                <tr><td>{$inv['total_productos']}</td><td>{$inv['total_unidades']}</td><td>S/ " . number_format((float)$inv['valor_inventario'], 2) . "</td><td>{$inv['bajo_stock']}</td><td>{$inv['sin_stock']}</td></tr></table>";
        }

        if (in_array('tickets', $secciones, true)) {
            $filas = '';
            foreach ($resumen['tickets'] as $t) {
                $filas .= '<tr><td>' . htmlspecialchars((string)$t['estado']) . '</td><td>' . (int)$t['cantidad'] . '</td></tr>';
            }
            $html .= "<h2>Tickets de soporte por estado</h2><table><tr><th>Estado</th><th>Cantidad</th></tr>{$filas}</table>";
        }

        $html .= '</body></html>';

        $this->registrarGeneracion('pdf');

        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $dompdf->stream('reporte_general_' . date('Ymd_His') . '.pdf', ['Attachment' => true]);
        exit;
    }

    public function exportarExcel(): void
    {
        $this->soloGerente();
        [$desde, $hasta] = $this->leerRango();
        $secciones = $this->leerSecciones();
        $resumen   = $this->model->resumenGeneral($desde, $hasta);

        while (ob_get_level() > 0) ob_end_clean();

        // Para simplificar, exportamos la sección más "tabular": movimientos si está
        // seleccionada, o la primera sección seleccionada disponible.
        $this->registrarGeneracion('excel');

        if (in_array('movimientos', $secciones, true)) {
            $movs = $this->model->movimientos($desde, $hasta);
            $encabezados = ['Fecha', 'Tipo', 'Referencia', 'Monto (S/)', 'Método de pago'];
            $filas = array_map(fn($m) => [
                $m['fecha'], $m['tipo'], $m['referencia'], number_format((float)$m['monto'], 2), $m['metodo_pago'] ?? '—',
            ], $movs);
            ExcelWriter::descargar('reporte_movimientos_' . date('Ymd_His'), $encabezados, $filas);
            return;
        }

        if (in_array('gastos_cat', $secciones, true)) {
            $encabezados = ['Categoría', 'Total (S/)'];
            $filas = array_map(fn($c) => [$c['categoria_label'], number_format($c['total'], 2)], $resumen['gastos_por_categoria']);
            ExcelWriter::descargar('reporte_gastos_por_categoria_' . date('Ymd_His'), $encabezados, $filas);
            return;
        }

        if (in_array('top_productos', $secciones, true)) {
            $encabezados = ['Producto', 'Unidades', 'Total (S/)'];
            $filas = array_map(fn($p) => [$p['nombre'], (int)$p['unidades'], number_format((float)$p['total'], 2)], $resumen['top_productos']);
            ExcelWriter::descargar('reporte_top_productos_' . date('Ymd_His'), $encabezados, $filas);
            return;
        }

        if (in_array('top_servicios', $secciones, true)) {
            $encabezados = ['Servicio', 'Unidades', 'Total (S/)'];
            $filas = array_map(fn($s) => [$s['nombre'], (int)$s['unidades'], number_format((float)$s['total'], 2)], $resumen['top_servicios']);
            ExcelWriter::descargar('reporte_top_servicios_' . date('Ymd_His'), $encabezados, $filas);
            return;
        }

        if (in_array('tickets', $secciones, true)) {
            $encabezados = ['Estado', 'Cantidad'];
            $filas = array_map(fn($t) => [$t['estado'], (int)$t['cantidad']], $resumen['tickets']);
            ExcelWriter::descargar('reporte_tickets_' . date('Ymd_His'), $encabezados, $filas);
            return;
        }

        // Fallback: balance general
        $encabezados = ['Ingresos', 'Egresos', 'Balance'];
        $filas = [[number_format($resumen['ingresos'], 2), number_format($resumen['egresos'], 2), number_format($resumen['balance'], 2)]];
        ExcelWriter::descargar('reporte_balance_' . date('Ymd_His'), $encabezados, $filas);
    }

    // ==================== HELPERS ====================

    /** Deja constancia en la tabla `reportes` de que se generó un reporte. */
    private function registrarGeneracion(string $formato): void
    {
        try {
            $db = getDB();
            $stmt = $db->prepare("INSERT INTO reportes (tipo_reporte) VALUES (:tipo)");
            $stmt->execute([':tipo' => 'general_' . $formato]);
        } catch (\Throwable $e) {
            // No es crítico: si falla el registro de historial no debe romper la descarga.
            error_log('[ReporteController] No se pudo registrar historial: ' . $e->getMessage());
        }
    }

    /** @return array{0:?string,1:?string} */
    private function leerRango(): array
    {
        return [
            trim((string)($_GET['desde'] ?? '')) ?: null,
            trim((string)($_GET['hasta'] ?? '')) ?: null,
        ];
    }

    private function leerSecciones(): array
    {
        $solicitadas = $_GET['secciones'] ?? array_keys(self::SECCIONES_DISPONIBLES);
        if (is_string($solicitadas)) {
            $solicitadas = explode(',', $solicitadas);
        }
        $validas = array_intersect($solicitadas, array_keys(self::SECCIONES_DISPONIBLES));
        return $validas ?: array_keys(self::SECCIONES_DISPONIBLES);
    }

    private function soloGerente(): void
    {
        if (!usuarioLogueado() || ($_SESSION['usuario_rol'] ?? '') !== 'gerente') {
            $this->json(false, 'Solo el gerente puede acceder a los reportes.', [], 403);
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
$ctrl   = new ReporteController();
$accion = $_GET['accion'] ?? '';

match ($accion) {
    'resumen'        => $ctrl->resumen(),
    'exportar_pdf'   => $ctrl->exportarPdf(),
    'exportar_excel' => $ctrl->exportarExcel(),
    default          => http_response_code(404),
};
