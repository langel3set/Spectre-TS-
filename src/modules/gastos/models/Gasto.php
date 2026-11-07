<?php
declare(strict_types=1);

/**
 * src/modules/gastos/models/Gasto.php
 *
 * Modelo del módulo de Gastos. Lee/escribe la tabla `gastos`.
 */
class Gasto
{
    private PDO $db;

    /** Categorías sugeridas de gasto (slug => etiqueta). */
    public const CATEGORIAS = [
        'proveedores'         => 'Compra a proveedores',
        'sueldos'             => 'Sueldos y planillas',
        'servicios_publicos'  => 'Servicios públicos (luz, agua, internet)',
        'alquiler'            => 'Alquiler / local',
        'marketing'           => 'Marketing y publicidad',
        'mantenimiento'       => 'Mantenimiento y reparaciones',
        'transporte'          => 'Transporte / envíos',
        'impuestos'           => 'Impuestos y trámites',
        'otros'               => 'Otros',
    ];

    public function __construct()
    {
        $this->db = getDB();
    }

    /**
     * Lista gastos con filtros opcionales.
     */
    public function listar(?string $desde = null, ?string $hasta = null, ?string $categoria = null, ?string $busqueda = null): array
    {
        $sql = "SELECT g.*, u.nombre AS usuario_nombre, u.apellido AS usuario_apellido
                FROM gastos g
                LEFT JOIN usuarios u ON u.id = g.id_usuario
                WHERE 1=1";
        $params = [];

        if ($desde) {
            $sql .= " AND g.fecha_gasto >= :desde";
            $params[':desde'] = $desde;
        }
        if ($hasta) {
            $sql .= " AND g.fecha_gasto <= :hasta";
            $params[':hasta'] = $hasta;
        }
        if ($categoria) {
            $sql .= " AND g.categoria = :categoria";
            $params[':categoria'] = $categoria;
        }
        if ($busqueda) {
            $sql .= " AND (g.concepto LIKE :q OR g.descripcion LIKE :q)";
            $params[':q'] = '%' . $busqueda . '%';
        }

        $sql .= " ORDER BY g.fecha_gasto DESC, g.id_gasto DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtener(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM gastos WHERE id_gasto = :id");
        $stmt->execute([':id' => $id]);
        $gasto = $stmt->fetch(PDO::FETCH_ASSOC);
        return $gasto ?: null;
    }

    public function crear(array $datos): array
    {
        if (trim((string)($datos['concepto'] ?? '')) === '') {
            return ['ok' => false, 'message' => 'El concepto es obligatorio.'];
        }
        if (!isset($datos['monto']) || (float)$datos['monto'] <= 0) {
            return ['ok' => false, 'message' => 'El monto debe ser mayor a 0.'];
        }
        if (trim((string)($datos['fecha_gasto'] ?? '')) === '') {
            return ['ok' => false, 'message' => 'La fecha del gasto es obligatoria.'];
        }

        $stmt = $this->db->prepare("
            INSERT INTO gastos (concepto, descripcion, categoria, monto, fecha_gasto, metodo_pago, comprobante_ruta, id_usuario)
            VALUES (:concepto, :descripcion, :categoria, :monto, :fecha_gasto, :metodo_pago, :comprobante_ruta, :id_usuario)
        ");
        $stmt->execute([
            ':concepto'         => trim($datos['concepto']),
            ':descripcion'      => trim((string)($datos['descripcion'] ?? '')) ?: null,
            ':categoria'        => $datos['categoria'] ?? 'otros',
            ':monto'            => (float)$datos['monto'],
            ':fecha_gasto'      => $datos['fecha_gasto'],
            ':metodo_pago'      => trim((string)($datos['metodo_pago'] ?? '')) ?: null,
            ':comprobante_ruta' => $datos['comprobante_ruta'] ?? null,
            ':id_usuario'       => $datos['id_usuario'] ?? null,
        ]);

        return ['ok' => true, 'message' => 'Gasto registrado correctamente.', 'id_gasto' => (int)$this->db->lastInsertId()];
    }

    public function actualizar(int $id, array $datos): array
    {
        if (!$this->obtener($id)) {
            return ['ok' => false, 'message' => 'Gasto no encontrado.'];
        }
        if (trim((string)($datos['concepto'] ?? '')) === '') {
            return ['ok' => false, 'message' => 'El concepto es obligatorio.'];
        }
        if (!isset($datos['monto']) || (float)$datos['monto'] <= 0) {
            return ['ok' => false, 'message' => 'El monto debe ser mayor a 0.'];
        }

        $stmt = $this->db->prepare("
            UPDATE gastos SET
                concepto = :concepto,
                descripcion = :descripcion,
                categoria = :categoria,
                monto = :monto,
                fecha_gasto = :fecha_gasto,
                metodo_pago = :metodo_pago
            WHERE id_gasto = :id
        ");
        $stmt->execute([
            ':concepto'    => trim($datos['concepto']),
            ':descripcion' => trim((string)($datos['descripcion'] ?? '')) ?: null,
            ':categoria'   => $datos['categoria'] ?? 'otros',
            ':monto'       => (float)$datos['monto'],
            ':fecha_gasto' => $datos['fecha_gasto'],
            ':metodo_pago' => trim((string)($datos['metodo_pago'] ?? '')) ?: null,
            ':id'          => $id,
        ]);

        return ['ok' => true, 'message' => 'Gasto actualizado correctamente.'];
    }

    public function eliminar(int $id): array
    {
        if (!$this->obtener($id)) {
            return ['ok' => false, 'message' => 'Gasto no encontrado.'];
        }
        $stmt = $this->db->prepare("DELETE FROM gastos WHERE id_gasto = :id");
        $stmt->execute([':id' => $id]);
        return ['ok' => true, 'message' => 'Gasto eliminado correctamente.'];
    }

    /**
     * Resumen general + datos listos para gráficos circulares.
     */
    public function resumen(?string $desde = null, ?string $hasta = null): array
    {
        $sql = "SELECT
                    COALESCE(SUM(monto), 0) AS total_periodo,
                    COALESCE(SUM(CASE WHEN MONTH(fecha_gasto) = MONTH(CURDATE()) AND YEAR(fecha_gasto) = YEAR(CURDATE()) THEN monto ELSE 0 END), 0) AS total_mes,
                    COALESCE(SUM(CASE WHEN fecha_gasto = CURDATE() THEN monto ELSE 0 END), 0) AS total_hoy,
                    COUNT(*) AS cantidad
                FROM gastos
                WHERE 1=1";
        $params = [];
        if ($desde) { $sql .= " AND fecha_gasto >= :desde"; $params[':desde'] = $desde; }
        if ($hasta) { $sql .= " AND fecha_gasto <= :hasta"; $params[':hasta'] = $hasta; }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $res = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'total_periodo' => 0, 'total_mes' => 0, 'total_hoy' => 0, 'cantidad' => 0,
        ];

        // Distribución por categoría (para el gráfico circular)
        $sqlCat = "SELECT categoria, COALESCE(SUM(monto),0) AS total
                   FROM gastos WHERE 1=1";
        if ($desde) { $sqlCat .= " AND fecha_gasto >= :desde"; }
        if ($hasta) { $sqlCat .= " AND fecha_gasto <= :hasta"; }
        $sqlCat .= " GROUP BY categoria ORDER BY total DESC";

        $stmtCat = $this->db->prepare($sqlCat);
        $stmtCat->execute($params);
        $porCategoria = $stmtCat->fetchAll(PDO::FETCH_ASSOC);

        foreach ($porCategoria as &$fila) {
            $fila['categoria_label'] = self::CATEGORIAS[$fila['categoria']] ?? ucfirst((string)$fila['categoria']);
            $fila['total'] = (float)$fila['total'];
        }
        unset($fila);

        $res['por_categoria'] = $porCategoria;
        return $res;
    }
}
