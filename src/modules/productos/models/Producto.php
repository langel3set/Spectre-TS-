<?php
declare(strict_types=1);

/**
 * src/modules/productos/models/Producto.php
 *
 * Modelo de productos. Lee/escribe la tabla `productos` (que ya trae
 * columnas de stock) y toma la primera imagen asociada desde
 * `imagenes_productos` si existe.
 */
class Producto
{
    private PDO $db;

    /** Categorías válidas mostradas en la tienda (slug => etiqueta). */
    public const CATEGORIAS = [
        'gpus'          => 'Tarjetas Gráficas',
        'monitores'     => 'Monitores',
        'pcs'           => 'PCs Gaming',
        'perifericos'   => 'Periféricos',
        'memorias'      => 'RAM & Storage',
        'refrigeracion' => 'Refrigeración',
        'fuentes'       => 'Fuentes de poder',
        'gabinetes'     => 'Gabinetes',
    ];

    public function __construct()
    {
        $this->db = getDB();
    }

    /**
     * Lista productos activos, opcionalmente filtrados por categoría
     * y/o texto de búsqueda (nombre o descripción).
     */
    public function listar(?string $categoria = null, ?string $busqueda = null): array
{
    $sql = "SELECT p.*, i.ruta_imagen AS imagen
            FROM productos p
            LEFT JOIN imagenes_productos i ON p.id_producto = i.id_producto
            WHERE p.estado = 'activo'";

    $params = [];

    if ($categoria) {
        $sql .= " AND p.categoria = ?";
        $params[] = $categoria;
    }

    if ($busqueda) {
        $sql .= " AND (p.nombre LIKE ? OR p.descripcion LIKE ?)";
        $params[] = "%$busqueda%";
        $params[] = "%$busqueda%";
    }

    $sql .= " ORDER BY p.id_producto DESC";

    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                p.*,
                (
                    SELECT ip.ruta_imagen
                    FROM imagenes_productos ip
                    WHERE ip.id_producto = p.id_producto
                    ORDER BY ip.id_imagen ASC
                    LIMIT 1
                ) AS imagen
            FROM productos p
            WHERE p.id_producto = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    

    /**
     * Cantidad de productos activos por categoría (para el sidebar).
     * Devuelve un array [slug => cantidad].
     */
    public function contarPorCategoria(): array
    {
        $stmt = $this->db->query("
            SELECT categoria, COUNT(*) AS total
            FROM productos
            WHERE estado = 'activo'
            GROUP BY categoria
        ");

        $conteo = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $conteo[$fila['categoria']] = (int)$fila['total'];
        }

        return $conteo;
    }

    /**
     * Crea un producto nuevo. Si se indica $imagenUrl, también se
     * guarda una fila en `imagenes_productos`.
     */
    public function crear(array $datos, ?string $imagenUrl = null): int|false
    {
        $sql = "
            INSERT INTO productos
                (codigo, nombre, descripcion, categoria, tipo, precio, stock, stock_minimo, id_proveedor, estado)
            VALUES
                (:codigo, :nombre, :descripcion, :categoria, :tipo, :precio, :stock, :stock_minimo, :id_proveedor, :estado)
        ";

        $stmt = $this->db->prepare($sql);

        $ok = $stmt->execute([
            ':codigo'       => $datos['codigo'],
            ':nombre'       => $datos['nombre'],
            ':descripcion'  => $datos['descripcion'] ?? null,
            ':categoria'    => $datos['categoria'],
            ':tipo'         => $datos['tipo'] ?? 'producto',
            ':precio'       => $datos['precio'],
            ':stock'        => $datos['stock'],
            ':stock_minimo' => $datos['stock_minimo'] ?? 0,
            ':id_proveedor' => $datos['id_proveedor'] ?? null,
            ':estado'       => $datos['estado'] ?? 'activo',
        ]);

        if (!$ok) {
            return false;
        }

        $idProducto = (int)$this->db->lastInsertId();

        if ($imagenUrl !== null && $imagenUrl !== '') {
            $stmtImg = $this->db->prepare("
                INSERT INTO imagenes_productos (id_producto, ruta_imagen, descripcion)
                VALUES (:id_producto, :ruta, :descripcion)
            ");
            $stmtImg->execute([
                ':id_producto' => $idProducto,
                ':ruta'        => $imagenUrl,
                ':descripcion' => $datos['nombre'],
            ]);
        }

        return $idProducto;
    }

    /**
     * Descuenta stock (por ejemplo al confirmar una compra). Devuelve
     * false si no había stock suficiente, para evitar cantidades negativas.
     */
    public function descontarStock(int $idProducto, int $cantidad): bool
    {
        $stmt = $this->db->prepare("
            UPDATE productos
            SET stock = stock - :cantidad1
            WHERE id_producto = :id AND stock >= :cantidad2
        ");
        $stmt->execute([
            ':cantidad1' => $cantidad,
            ':cantidad2' => $cantidad,
            ':id'        => $idProducto,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function existeCodigo(string $codigo): bool
    {
        $stmt = $this->db->prepare("SELECT id_producto FROM productos WHERE codigo = :codigo LIMIT 1");
        $stmt->execute([':codigo' => $codigo]);

        return (bool)$stmt->fetchColumn();
    }
}
