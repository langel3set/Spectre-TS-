<?php
declare(strict_types=1);

/**
 * src/modules/inventario/models/Inventario.php
 *
 * Modelo del módulo de inventario. Trabaja sobre la misma tabla
 * `productos` que usa la tienda (no la duplica) y agrega el historial
 * de movimientos de stock en `movimientos_inventario` (kardex), para
 * saber quién movió qué y cuándo.
 *
 * Tipos de movimiento:
 *  - entrada: suma cantidad al stock (ej. llega mercadería del proveedor)
 *  - salida:  resta cantidad al stock (ej. merma, préstamo, uso interno)
 *  - ajuste:  establece el stock EXACTO indicado (corrección de conteo)
 */
class Inventario
{
    private PDO $db;

    public function __construct()
    {
        $this->db = getDB();
    }

    /**
     * Lista productos con su información de stock. Filtros opcionales:
     * categoría, texto de búsqueda (nombre/código) y "solo bajo stock".
     */
    public function listar(?string $categoria = null, ?string $busqueda = null, bool $soloBajoStock = false): array
    {
        $condiciones = ["p.estado = 'activo'"];
        $params = [];

        if ($categoria !== null && $categoria !== '') {
            $condiciones[] = 'p.categoria = :categoria';
            $params[':categoria'] = $categoria;
        }

        if ($busqueda !== null && $busqueda !== '') {
            $condiciones[] = '(p.nombre LIKE :busqueda OR p.codigo LIKE :busqueda)';
            $params[':busqueda'] = '%' . $busqueda . '%';
        }

        if ($soloBajoStock) {
            $condiciones[] = 'p.stock <= p.stock_minimo';
        }

        $where = implode(' AND ', $condiciones);

        $sql = "
            SELECT
                p.id_producto,
                p.codigo,
                p.nombre,
                p.descripcion,
                p.categoria,
                p.precio,
                p.stock,
                p.stock_minimo,
                p.estado,
                (
                    SELECT ip.ruta_imagen
                    FROM imagenes_productos ip
                    WHERE ip.id_producto = p.id_producto
                    ORDER BY ip.id_imagen ASC
                    LIMIT 1
                ) AS imagen
            FROM productos p
            WHERE $where
            ORDER BY p.nombre ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT p.*
            FROM productos p
            WHERE p.id_producto = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Tarjetas de resumen para el panel: total de productos, unidades,
     * valor del inventario (stock x precio), y alertas de stock.
     */
    public function resumen(): array
    {
        $stmt = $this->db->query("
            SELECT
                COUNT(*)                                                     AS total_productos,
                COALESCE(SUM(stock), 0)                                      AS total_unidades,
                COALESCE(SUM(stock * precio), 0)                             AS valor_inventario,
                COALESCE(SUM(CASE WHEN stock = 0 THEN 1 ELSE 0 END), 0)      AS sin_stock,
                COALESCE(SUM(CASE WHEN stock > 0 AND stock <= stock_minimo THEN 1 ELSE 0 END), 0) AS bajo_stock
            FROM productos
            WHERE estado = 'activo'
        ");

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'total_productos'  => 0,
            'total_unidades'   => 0,
            'valor_inventario' => 0,
            'sin_stock'        => 0,
            'bajo_stock'       => 0,
        ];
    }

    /**
     * Registra un movimiento de stock y actualiza `productos.stock` de
     * forma atómica (transacción + bloqueo de fila) para evitar carreras
     * si dos personas mueven el mismo producto al mismo tiempo.
     */
    public function registrarMovimiento(int $idProducto, string $tipo, int $cantidad, ?string $motivo, int $idUsuario): array
    {
        if (!in_array($tipo, ['entrada', 'salida', 'ajuste'], true)) {
            return ['ok' => false, 'message' => 'Tipo de movimiento inválido.'];
        }

        if ($tipo === 'ajuste' ? $cantidad < 0 : $cantidad <= 0) {
            return ['ok' => false, 'message' => 'La cantidad no es válida.'];
        }

        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare("
                SELECT stock FROM productos WHERE id_producto = :id AND estado = 'activo' FOR UPDATE
            ");
            $stmt->execute([':id' => $idProducto]);
            $producto = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$producto) {
                $this->db->rollBack();
                return ['ok' => false, 'message' => 'Producto no encontrado.'];
            }

            $stockAnterior = (int)$producto['stock'];

            $stockNuevo = match ($tipo) {
                'entrada' => $stockAnterior + $cantidad,
                'salida'  => $stockAnterior - $cantidad,
                'ajuste'  => $cantidad,
            };

            if ($stockNuevo < 0) {
                $this->db->rollBack();
                return ['ok' => false, 'message' => 'No hay stock suficiente para esa salida.'];
            }

            $upd = $this->db->prepare("UPDATE productos SET stock = :stock WHERE id_producto = :id");
            $upd->execute([':stock' => $stockNuevo, ':id' => $idProducto]);

            $ins = $this->db->prepare("
                INSERT INTO movimientos_inventario
                    (id_producto, tipo, cantidad, stock_anterior, stock_nuevo, motivo, id_usuario)
                VALUES
                    (:id_producto, :tipo, :cantidad, :stock_anterior, :stock_nuevo, :motivo, :id_usuario)
            ");
            $ins->execute([
                ':id_producto'    => $idProducto,
                ':tipo'           => $tipo,
                ':cantidad'       => $cantidad,
                ':stock_anterior' => $stockAnterior,
                ':stock_nuevo'    => $stockNuevo,
                ':motivo'         => ($motivo !== null && $motivo !== '') ? $motivo : null,
                ':id_usuario'     => $idUsuario,
            ]);

            $this->db->commit();

            return ['ok' => true, 'message' => 'Movimiento registrado correctamente.', 'stock' => $stockNuevo];

        } catch (\Throwable $e) {
            $this->db->rollBack();
            error_log('[Inventario::registrarMovimiento] ' . $e->getMessage());
            return ['ok' => false, 'message' => 'No se pudo registrar el movimiento.'];
        }
    }

    /**
     * Últimos movimientos de un producto (o de todo el inventario si
     * $idProducto es null), con el nombre de quién lo hizo.
     */
    public function historial(?int $idProducto = null, int $limite = 20): array
    {
        $limite = max(1, min($limite, 100));

        $where = $idProducto !== null ? 'WHERE m.id_producto = :id' : '';

        $stmt = $this->db->prepare("
            SELECT
                m.id_movimiento, m.id_producto, m.tipo, m.cantidad,
                m.stock_anterior, m.stock_nuevo, m.motivo, m.fecha,
                p.nombre AS producto_nombre, p.codigo AS producto_codigo,
                u.nombre AS usuario_nombre, u.apellido AS usuario_apellido
            FROM movimientos_inventario m
            INNER JOIN productos p ON p.id_producto = m.id_producto
            LEFT JOIN usuarios u ON u.id = m.id_usuario
            $where
            ORDER BY m.fecha DESC, m.id_movimiento DESC
            LIMIT $limite
        ");

        if ($idProducto !== null) {
            $stmt->bindValue(':id', $idProducto, PDO::PARAM_INT);
        }

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Edita los datos "de catálogo" del producto (no el stock; eso se
     * hace exclusivamente vía registrarMovimiento para dejar rastro).
     */
    public function actualizarDatos(int $idProducto, array $datos): array
    {
        if (!array_key_exists($datos['categoria'] ?? '', Producto::CATEGORIAS)) {
            return ['ok' => false, 'message' => 'Selecciona una categoría válida.'];
        }
        if (!is_numeric($datos['precio'] ?? null) || (float)$datos['precio'] < 0) {
            return ['ok' => false, 'message' => 'El precio no es válido.'];
        }
        if (!isset($datos['stock_minimo']) || (int)$datos['stock_minimo'] < 0) {
            return ['ok' => false, 'message' => 'El stock mínimo no es válido.'];
        }
        if (trim((string)($datos['nombre'] ?? '')) === '') {
            return ['ok' => false, 'message' => 'El nombre es obligatorio.'];
        }

        $stmt = $this->db->prepare("
            UPDATE productos
            SET nombre = :nombre,
                descripcion = :descripcion,
                categoria = :categoria,
                precio = :precio,
                stock_minimo = :stock_minimo
            WHERE id_producto = :id AND estado = 'activo'
        ");

        $ok = $stmt->execute([
            ':nombre'       => trim((string)$datos['nombre']),
            ':descripcion'  => trim((string)($datos['descripcion'] ?? '')),
            ':categoria'    => $datos['categoria'],
            ':precio'       => (float)$datos['precio'],
            ':stock_minimo' => (int)$datos['stock_minimo'],
            ':id'           => $idProducto,
        ]);

        if (!$ok || $stmt->rowCount() === 0) {
            return ['ok' => false, 'message' => 'No se pudo actualizar el producto (verifica que exista).'];
        }

        return ['ok' => true, 'message' => 'Producto actualizado correctamente.'];
    }

    /**
     * Desactiva el producto (baja lógica). No se elimina físicamente
     * para conservar el historial de ventas y movimientos.
     */
    public function eliminar(int $idProducto): bool
    {
        $stmt = $this->db->prepare("UPDATE productos SET estado = 'inactivo' WHERE id_producto = :id");
        $stmt->execute([':id' => $idProducto]);

        return $stmt->rowCount() > 0;
    }
}
