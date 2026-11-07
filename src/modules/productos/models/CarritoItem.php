<?php
declare(strict_types=1);

/**
 * src/modules/productos/models/CarritoItem.php
 *
 * Carrito persistente en BD (tabla `carrito_items`), siempre asociado
 * a un id_usuario. No existe carrito "anónimo": por eso el controlador
 * exige sesión iniciada antes de tocar cualquiera de estos métodos.
 */
class CarritoItem
{
    private PDO $db;

    public function __construct()
    {
        $this->db = getDB();
    }

    /**
     * Agrega un producto al carrito o, si ya estaba, incrementa la
     * cantidad. Nunca deja que la cantidad supere el stock disponible.
     *
     * @return array{ok:bool, message:string}
     */
    public function agregar(int $idUsuario, int $idProducto, int $cantidad = 1): array
    {
        $producto = (new Producto())->buscarPorId($idProducto);

        if (!$producto || $producto['estado'] !== 'activo') {
            return ['ok' => false, 'message' => 'Ese producto ya no está disponible.'];
        }

        if ((int)$producto['stock'] <= 0) {
            return ['ok' => false, 'message' => 'Producto agotado.'];
        }

        $stmt = $this->db->prepare("
            SELECT cantidad FROM carrito_items
            WHERE id_usuario = :usuario AND id_producto = :producto
            LIMIT 1
        ");
        $stmt->execute([':usuario' => $idUsuario, ':producto' => $idProducto]);
        $actual = $stmt->fetchColumn();

        $nuevaCantidad = ($actual !== false ? (int)$actual : 0) + $cantidad;

        if ($nuevaCantidad > (int)$producto['stock']) {
            $nuevaCantidad = (int)$producto['stock'];
        }

        if ($actual !== false) {
            $upd = $this->db->prepare("
                UPDATE carrito_items
                SET cantidad = :cantidad
                WHERE id_usuario = :usuario AND id_producto = :producto
            ");
            $upd->execute([
                ':cantidad' => $nuevaCantidad,
                ':usuario'  => $idUsuario,
                ':producto' => $idProducto,
            ]);
        } else {
            $ins = $this->db->prepare("
                INSERT INTO carrito_items (id_usuario, id_producto, cantidad)
                VALUES (:usuario, :producto, :cantidad)
            ");
            $ins->execute([
                ':usuario'  => $idUsuario,
                ':producto' => $idProducto,
                ':cantidad' => $nuevaCantidad,
            ]);
        }

        return ['ok' => true, 'message' => 'Agregado al carrito.'];
    }

    /** Lista los ítems del carrito de un usuario, con datos del producto. */
    public function listar(int $idUsuario): array
    {
        $stmt = $this->db->prepare("
            SELECT
                ci.id_item,
                ci.id_producto,
                ci.cantidad,
                p.nombre,
                p.precio,
                p.stock,
                (
                    SELECT ip.ruta_imagen
                    FROM imagenes_productos ip
                    WHERE ip.id_producto = p.id_producto
                    ORDER BY ip.id_imagen ASC
                    LIMIT 1
                ) AS imagen
            FROM carrito_items ci
            INNER JOIN productos p ON p.id_producto = ci.id_producto
            WHERE ci.id_usuario = :usuario
            ORDER BY ci.fecha_agregado DESC
        ");
        $stmt->execute([':usuario' => $idUsuario]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function actualizarCantidad(int $idUsuario, int $idProducto, int $cantidad): array
    {
        if ($cantidad <= 0) {
            return $this->eliminar($idUsuario, $idProducto);
        }

        $producto = (new Producto())->buscarPorId($idProducto);
        if (!$producto) {
            return ['ok' => false, 'message' => 'Producto no encontrado.'];
        }

        if ($cantidad > (int)$producto['stock']) {
            $cantidad = (int)$producto['stock'];
        }

        $stmt = $this->db->prepare("
            UPDATE carrito_items
            SET cantidad = :cantidad
            WHERE id_usuario = :usuario AND id_producto = :producto
        ");
        $stmt->execute([
            ':cantidad' => $cantidad,
            ':usuario'  => $idUsuario,
            ':producto' => $idProducto,
        ]);

        return ['ok' => true, 'message' => 'Cantidad actualizada.', 'cantidad' => $cantidad];
    }

    public function eliminar(int $idUsuario, int $idProducto): array
    {
        $stmt = $this->db->prepare("
            DELETE FROM carrito_items
            WHERE id_usuario = :usuario AND id_producto = :producto
        ");
        $stmt->execute([':usuario' => $idUsuario, ':producto' => $idProducto]);

        return ['ok' => true, 'message' => 'Producto quitado del carrito.'];
    }

    public function vaciar(int $idUsuario): bool
    {
        $stmt = $this->db->prepare("DELETE FROM carrito_items WHERE id_usuario = :usuario");
        return $stmt->execute([':usuario' => $idUsuario]);
    }

    /** Suma de unidades en el carrito (para el contador del navbar). */
    public function contarUnidades(int $idUsuario): int
    {
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(cantidad), 0)
            FROM carrito_items
            WHERE id_usuario = :usuario
        ");
        $stmt->execute([':usuario' => $idUsuario]);

        return (int)$stmt->fetchColumn();
    }

    public function total(int $idUsuario): float
    {
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(ci.cantidad * p.precio), 0)
            FROM carrito_items ci
            INNER JOIN productos p ON p.id_producto = ci.id_producto
            WHERE ci.id_usuario = :usuario
        ");
        $stmt->execute([':usuario' => $idUsuario]);

        return (float)$stmt->fetchColumn();
    }
}
