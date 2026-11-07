<?php
declare(strict_types=1);

/**
 * src/modules/servicios/models/Servicio.php
 *
 * Modelo del módulo de servicios (instalaciones, mantenimientos,
 * soporte técnico presencial, etc). A diferencia de `productos`,
 * los servicios NO manejan stock: solo nombre, descripción, precio,
 * duración estimada y estado.
 */
class Servicio
{
    private PDO $db;

    public function __construct()
    {
        $this->db = getDB();
    }

    /**
     * Lista servicios activos, con búsqueda opcional por nombre/descripción.
     */
    public function listar(?string $busqueda = null): array
    {
        $condiciones = ["estado = 'activo'"];
        $params = [];

        if ($busqueda !== null && $busqueda !== '') {
            $condiciones[] = '(nombre LIKE :busqueda OR descripcion LIKE :busqueda)';
            $params[':busqueda'] = '%' . $busqueda . '%';
        }

        $where = implode(' AND ', $condiciones);

        $stmt = $this->db->prepare("
            SELECT id_servicio, nombre, descripcion, precio, duracion_estimada, estado
            FROM servicios
            WHERE $where
            ORDER BY nombre ASC
        ");
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Todos los servicios activos (para el selector de nueva venta). */
    public function listarActivosParaVenta(): array
    {
        $stmt = $this->db->query("
            SELECT id_servicio, nombre, precio
            FROM servicios
            WHERE estado = 'activo'
            ORDER BY nombre ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id_servicio, nombre, descripcion, precio, duracion_estimada, estado
            FROM servicios
            WHERE id_servicio = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Tarjetas de resumen para el panel: total de servicios activos
     * y valor de catálogo (suma de precios).
     */
    public function resumen(): array
    {
        $stmt = $this->db->query("
            SELECT
                COUNT(*) AS total_servicios,
                COALESCE(SUM(precio), 0) AS valor_catalogo,
                COALESCE(AVG(precio), 0) AS precio_promedio
            FROM servicios
            WHERE estado = 'activo'
        ");

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'total_servicios' => 0,
            'valor_catalogo'  => 0,
            'precio_promedio' => 0,
        ];
    }

    public function crear(array $datos): array
    {
        $nombre = trim((string)($datos['nombre'] ?? ''));
        $descripcion = trim((string)($datos['descripcion'] ?? ''));
        $precio = $datos['precio'] ?? null;
        $duracion = trim((string)($datos['duracion_estimada'] ?? ''));

        if ($nombre === '') {
            return ['ok' => false, 'message' => 'El nombre del servicio es obligatorio.'];
        }
        if (!is_numeric($precio) || (float)$precio < 0) {
            return ['ok' => false, 'message' => 'El precio no es válido.'];
        }

        $stmt = $this->db->prepare("
            INSERT INTO servicios (nombre, descripcion, precio, duracion_estimada, estado)
            VALUES (:nombre, :descripcion, :precio, :duracion_estimada, 'activo')
        ");
        $ok = $stmt->execute([
            ':nombre'             => $nombre,
            ':descripcion'        => $descripcion !== '' ? $descripcion : null,
            ':precio'             => (float)$precio,
            ':duracion_estimada'  => $duracion !== '' ? $duracion : null,
        ]);

        if (!$ok) {
            return ['ok' => false, 'message' => 'No se pudo guardar el servicio.'];
        }

        return ['ok' => true, 'message' => 'Servicio creado correctamente.', 'id_servicio' => (int)$this->db->lastInsertId()];
    }

    public function actualizar(int $idServicio, array $datos): array
    {
        $nombre = trim((string)($datos['nombre'] ?? ''));
        $descripcion = trim((string)($datos['descripcion'] ?? ''));
        $precio = $datos['precio'] ?? null;
        $duracion = trim((string)($datos['duracion_estimada'] ?? ''));

        if ($nombre === '') {
            return ['ok' => false, 'message' => 'El nombre del servicio es obligatorio.'];
        }
        if (!is_numeric($precio) || (float)$precio < 0) {
            return ['ok' => false, 'message' => 'El precio no es válido.'];
        }

        $stmt = $this->db->prepare("
            UPDATE servicios
            SET nombre = :nombre,
                descripcion = :descripcion,
                precio = :precio,
                duracion_estimada = :duracion_estimada
            WHERE id_servicio = :id AND estado = 'activo'
        ");
        $ok = $stmt->execute([
            ':nombre'             => $nombre,
            ':descripcion'        => $descripcion !== '' ? $descripcion : null,
            ':precio'             => (float)$precio,
            ':duracion_estimada'  => $duracion !== '' ? $duracion : null,
            ':id'                 => $idServicio,
        ]);

        if (!$ok || $stmt->rowCount() === 0) {
            return ['ok' => false, 'message' => 'No se pudo actualizar el servicio (verifica que exista).'];
        }

        return ['ok' => true, 'message' => 'Servicio actualizado correctamente.'];
    }

    /** Baja lógica, igual que productos, para conservar el historial de ventas. */
    public function eliminar(int $idServicio): bool
    {
        $stmt = $this->db->prepare("UPDATE servicios SET estado = 'inactivo' WHERE id_servicio = :id");
        $stmt->execute([':id' => $idServicio]);

        return $stmt->rowCount() > 0;
    }
}
