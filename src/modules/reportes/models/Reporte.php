<?php
declare(strict_types=1);

/**
 * src/modules/reportes/models/Reporte.php
 *
 * Modelo de agregación para el panel de Reportes. No tiene tabla propia
 * de datos "vivos": cruza información de ventas, gastos, servicios e
 * inventario para armar el resumen ejecutivo y los reportes exportables.
 */
class Reporte
{
    private PDO $db;

    public function __construct()
    {
        $this->db = getDB();
    }

    /**
     * Resumen ejecutivo para el rango de fechas indicado (o histórico si es null).
     */
    public function resumenGeneral(?string $desde, ?string $hasta): array
    {
        return [
            'ingresos'        => $this->totalIngresos($desde, $hasta),
            'egresos'         => $this->totalEgresos($desde, $hasta),
            'balance'         => $this->totalIngresos($desde, $hasta) - $this->totalEgresos($desde, $hasta),
            'ventas_por_dia'  => $this->ventasPorDia($desde, $hasta),
            'gastos_por_categoria' => $this->gastosPorCategoria($desde, $hasta),
            'top_productos'   => $this->topProductosVendidos($desde, $hasta),
            'top_servicios'   => $this->topServiciosVendidos($desde, $hasta),
            'inventario'      => $this->resumenInventario(),
            'tickets'         => $this->resumenTickets($desde, $hasta),
        ];
    }

    private function totalIngresos(?string $desde, ?string $hasta): float
    {
        $sql = "SELECT COALESCE(SUM(total),0) FROM ventas WHERE estado = 'completada'";
        [$sql, $params] = $this->aplicarRangoFecha($sql, 'fecha_venta', $desde, $hasta);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (float)$stmt->fetchColumn();
    }

    private function totalEgresos(?string $desde, ?string $hasta): float
    {
        $sql = "SELECT COALESCE(SUM(monto),0) FROM gastos WHERE 1=1";
        [$sql, $params] = $this->aplicarRangoFecha($sql, 'fecha_gasto', $desde, $hasta);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (float)$stmt->fetchColumn();
    }

    private function ventasPorDia(?string $desde, ?string $hasta): array
    {
        $sql = "SELECT DATE(fecha_venta) AS dia, COALESCE(SUM(total),0) AS total
                FROM ventas WHERE estado = 'completada'";
        [$sql, $params] = $this->aplicarRangoFecha($sql, 'fecha_venta', $desde, $hasta);
        $sql .= " GROUP BY DATE(fecha_venta) ORDER BY dia ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function gastosPorCategoria(?string $desde, ?string $hasta): array
    {
        $sql = "SELECT categoria, COALESCE(SUM(monto),0) AS total FROM gastos WHERE 1=1";
        [$sql, $params] = $this->aplicarRangoFecha($sql, 'fecha_gasto', $desde, $hasta);
        $sql .= " GROUP BY categoria ORDER BY total DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($filas as &$f) {
            $f['categoria_label'] = Gasto::CATEGORIAS[$f['categoria']] ?? ucfirst((string)$f['categoria']);
            $f['total'] = (float)$f['total'];
        }
        return $filas;
    }

    private function topProductosVendidos(?string $desde, ?string $hasta): array
    {
        $sql = "SELECT p.nombre, SUM(d.cantidad) AS unidades, SUM(d.subtotal) AS total
                FROM detalle_ventas d
                JOIN ventas v ON v.id_venta = d.id_venta
                JOIN productos p ON p.id_producto = d.id_producto
                WHERE d.tipo_item = 'producto' AND v.estado = 'completada'";
        [$sql, $params] = $this->aplicarRangoFecha($sql, 'v.fecha_venta', $desde, $hasta);
        $sql .= " GROUP BY p.id_producto ORDER BY total DESC LIMIT 10";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function topServiciosVendidos(?string $desde, ?string $hasta): array
    {
        $sql = "SELECT s.nombre, SUM(d.cantidad) AS unidades, SUM(d.subtotal) AS total
                FROM detalle_ventas d
                JOIN ventas v ON v.id_venta = d.id_venta
                JOIN servicios s ON s.id_servicio = d.id_servicio
                WHERE d.tipo_item = 'servicio' AND v.estado = 'completada'";
        [$sql, $params] = $this->aplicarRangoFecha($sql, 'v.fecha_venta', $desde, $hasta);
        $sql .= " GROUP BY s.id_servicio ORDER BY total DESC LIMIT 10";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function resumenInventario(): array
    {
        $stmt = $this->db->query("
            SELECT
                COUNT(*) AS total_productos,
                COALESCE(SUM(stock),0) AS total_unidades,
                COALESCE(SUM(stock * precio),0) AS valor_inventario,
                COALESCE(SUM(CASE WHEN stock <= stock_minimo AND stock > 0 THEN 1 ELSE 0 END),0) AS bajo_stock,
                COALESCE(SUM(CASE WHEN stock = 0 THEN 1 ELSE 0 END),0) AS sin_stock
            FROM productos WHERE estado = 'activo'
        ");
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    private function resumenTickets(?string $desde, ?string $hasta): array
    {
        $sql = "SELECT estado, COUNT(*) AS cantidad FROM tickets WHERE 1=1";
        [$sql, $params] = $this->aplicarRangoFecha($sql, 'fecha_creacion', $desde, $hasta);
        $sql .= " GROUP BY estado";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Detalle de ventas + gastos combinados en una sola línea de tiempo,
     * usado para el reporte exportable de "Ingresos vs Egresos".
     */
    public function movimientos(?string $desde, ?string $hasta): array
    {
        $sqlVentas = "SELECT DATE(fecha_venta) AS fecha, 'Ingreso' AS tipo, codigo_venta AS referencia,
                             total AS monto, metodo_pago, descripcion
                      FROM ventas WHERE estado = 'completada'";
        [$sqlVentas, $paramsVentas] = $this->aplicarRangoFecha($sqlVentas, 'fecha_venta', $desde, $hasta);

        $sqlGastos = "SELECT fecha_gasto AS fecha, 'Egreso' AS tipo, concepto AS referencia,
                             monto, metodo_pago, descripcion
                      FROM gastos WHERE 1=1";
        [$sqlGastos, $paramsGastos] = $this->aplicarRangoFecha($sqlGastos, 'fecha_gasto', $desde, $hasta);

        $stmtV = $this->db->prepare($sqlVentas);
        $stmtV->execute($paramsVentas);
        $ventas = $stmtV->fetchAll(PDO::FETCH_ASSOC);

        $stmtG = $this->db->prepare($sqlGastos);
        $stmtG->execute($paramsGastos);
        $gastos = $stmtG->fetchAll(PDO::FETCH_ASSOC);

        $todos = array_merge($ventas, $gastos);
        usort($todos, fn($a, $b) => strcmp((string)$b['fecha'], (string)$a['fecha']));
        return $todos;
    }

    /**
     * @return array{0:string,1:array}
     */
    private function aplicarRangoFecha(string $sql, string $columna, ?string $desde, ?string $hasta): array
    {
        $params = [];
        if ($desde) {
            $sql .= " AND {$columna} >= :desde";
            $params[':desde'] = $desde;
        }
        if ($hasta) {
            $sql .= " AND {$columna} <= :hasta";
            $params[':hasta'] = $hasta . ' 23:59:59';
        }
        return [$sql, $params];
    }
}
