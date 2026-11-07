<?php
declare(strict_types=1);

class Venta
{
    private PDO $db;

    public function __construct()
    {
        $this->db = getDB();
    }

    // ==================== GENERAR CÓDIGO ====================

    private function generarCodigoVenta(): string
    {
        $stmt = $this->db->query("SELECT MAX(id_venta) AS ultimo FROM ventas");
        $ultimo = (int)$stmt->fetchColumn();
        $correlativo = str_pad((string)($ultimo + 1), 4, '0', STR_PAD_LEFT);
        $fecha = date('Ymd');
        return "VENT-{$fecha}-{$correlativo}";
    }

    // ==================== CHECKOUT (desde carrito) ====================

    public function checkout(int $idUsuario, string $metodoPago = 'Simulado'): array
    {
        $carrito = new CarritoItem();
        $items   = $carrito->listar($idUsuario);

        if (empty($items)) {
            return ['ok' => false, 'message' => 'Tu carrito está vacío.'];
        }

        $this->db->beginTransaction();

        try {
            $idCliente = $this->resolverIdCliente($idUsuario);

            $total = 0.0;
            $lineas = [];

            foreach ($items as $item) {
                $idProducto = (int)$item['id_producto'];
                $cantidad   = (int)$item['cantidad'];

                $stmt = $this->db->prepare("
                    SELECT nombre, precio, stock, estado
                    FROM productos
                    WHERE id_producto = :id
                    FOR UPDATE
                ");
                $stmt->execute([':id' => $idProducto]);
                $producto = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$producto || $producto['estado'] !== 'activo') {
                    $this->db->rollBack();
                    return ['ok' => false, 'message' => "\"{$item['nombre']}\" ya no está disponible."];
                }

                if ((int)$producto['stock'] < $cantidad) {
                    $this->db->rollBack();
                    return ['ok' => false, 'message' => "No hay stock suficiente de \"{$producto['nombre']}\" (quedan {$producto['stock']})."];
                }

                $precio   = (float)$producto['precio'];
                $subtotal = $precio * $cantidad;
                $total   += $subtotal;

                $lineas[] = [
                    'id_producto' => $idProducto,
                    'nombre'      => $producto['nombre'],
                    'cantidad'    => $cantidad,
                    'precio'      => $precio,
                    'subtotal'    => $subtotal,
                ];
            }

            // --- Cabecera de la venta con código generado ---
            $codigoVenta = $this->generarCodigoVenta();
            $insVenta = $this->db->prepare("
                INSERT INTO ventas (codigo_venta, id_cliente, id_usuario, total, metodo_pago, estado)
                VALUES (:codigo, :id_cliente, :id_usuario, :total, :metodo_pago, 'completada')
            ");
            $insVenta->execute([
                ':codigo'      => $codigoVenta,
                ':id_cliente'  => $idCliente,
                ':id_usuario'  => $idUsuario,
                ':total'       => $total,
                ':metodo_pago' => $metodoPago,
            ]);
            $idVenta = (int)$this->db->lastInsertId();

            // --- Detalles y actualización de stock ---
            $insDetalle = $this->db->prepare("
                INSERT INTO detalle_ventas (id_venta, id_producto, cantidad, precio_unitario, subtotal)
                VALUES (:id_venta, :id_producto, :cantidad, :precio, :subtotal)
            ");
            $updStock = $this->db->prepare("
                UPDATE productos SET stock = stock - :cantidad_resta
                WHERE id_producto = :id AND stock >= :cantidad_check
            ");
            $insMovimiento = $this->db->prepare("
                INSERT INTO movimientos_inventario
                    (id_producto, tipo, cantidad, stock_anterior, stock_nuevo, motivo, id_usuario)
                VALUES
                    (:id_producto, 'salida', :cantidad, :stock_anterior, :stock_nuevo, :motivo, :id_usuario)
            ");

            foreach ($lineas as $linea) {
                $insDetalle->execute([
                    ':id_venta'    => $idVenta,
                    ':id_producto' => $linea['id_producto'],
                    ':cantidad'    => $linea['cantidad'],
                    ':precio'      => $linea['precio'],
                    ':subtotal'    => $linea['subtotal'],
                ]);

                $updStock->execute([
                    ':cantidad_resta' => $linea['cantidad'],
                    ':cantidad_check' => $linea['cantidad'],
                    ':id'             => $linea['id_producto'],
                ]);

                if ($updStock->rowCount() === 0) {
                    $this->db->rollBack();
                    return ['ok' => false, 'message' => "No hay stock suficiente de \"{$linea['nombre']}\"."];
                }

                $stmtStock = $this->db->prepare("SELECT stock FROM productos WHERE id_producto = :id");
                $stmtStock->execute([':id' => $linea['id_producto']]);
                $stockNuevo = (int)$stmtStock->fetchColumn();

                $insMovimiento->execute([
                    ':id_producto'    => $linea['id_producto'],
                    ':cantidad'       => $linea['cantidad'],
                    ':stock_anterior' => $stockNuevo + $linea['cantidad'],
                    ':stock_nuevo'    => $stockNuevo,
                    ':motivo'         => "Venta #{$idVenta}",
                    ':id_usuario'     => $idUsuario,
                ]);
            }

            $carrito->vaciar($idUsuario);
            $this->db->commit();

            return [
                'ok'       => true,
                'message'  => 'Compra realizada correctamente.',
                'id_venta' => $idVenta,
                'total'    => $total,
                'items'    => $lineas,
            ];

        } catch (\Throwable $e) {
            $this->db->rollBack();
            error_log('[Venta::checkout] ' . $e->getMessage());
            return [
                'ok'      => false,
                'message' => 'No se pudo procesar la compra.',
                'debug'   => $e->getMessage(),
            ];
        }
    }

    // ==================== CREAR VENTA MANUAL (CORREGIDA) ====================

    public function crearVentaManual(array $datos): array
{
    if (empty($datos['productos'])) {
        return ['ok' => false, 'message' => 'Debes agregar al menos un producto o servicio.'];
    }

    $this->db->beginTransaction();

    try {
        // --- Convertir id_cliente a NULL si es 0 o vacío ---
        $idCliente = $datos['id_cliente'] ?? null;
        if ($idCliente === '' || $idCliente === null || (int)$idCliente === 0) {
            $idCliente = null;
        } else {
            $idCliente = (int)$idCliente;
        }

        $idUsuario = (int)$datos['id_usuario'];
        $metodoPago = $datos['metodo_pago'] ?? 'Efectivo';
        $descripcion = $datos['descripcion'] ?? null;
        $comprobanteRuta = $datos['comprobante_ruta'] ?? null;

        // --- Calcular total y validar cada línea (producto o servicio) ---
        $total = 0.0;
        $lineas = [];

        foreach ($datos['productos'] as $item) {
            $tipoItem = ($item['tipo'] ?? 'producto') === 'servicio' ? 'servicio' : 'producto';
            $idItem = (int)($item['id'] ?? $item['id_producto'] ?? 0);
            $cantidad = (int)($item['cantidad'] ?? 0);

            if ($idItem <= 0 || $cantidad <= 0) {
                continue;
            }

            if ($tipoItem === 'servicio') {
                $stmt = $this->db->prepare("
                    SELECT nombre, precio, estado
                    FROM servicios
                    WHERE id_servicio = :id
                    FOR UPDATE
                ");
                $stmt->execute([':id' => $idItem]);
                $servicio = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$servicio || $servicio['estado'] !== 'activo') {
                    $this->db->rollBack();
                    return ['ok' => false, 'message' => "El servicio ID {$idItem} ya no está disponible."];
                }

                $precio   = (float)$servicio['precio'];
                $subtotal = $precio * $cantidad;
                $total   += $subtotal;

                $lineas[] = [
                    'tipo'        => 'servicio',
                    'id_producto' => null,
                    'id_servicio' => $idItem,
                    'nombre'      => $servicio['nombre'],
                    'cantidad'    => $cantidad,
                    'precio'      => $precio,
                    'subtotal'    => $subtotal,
                ];

                continue;
            }

            $idProducto = $idItem;

            $stmt = $this->db->prepare("
                SELECT nombre, precio, stock, estado
                FROM productos
                WHERE id_producto = :id
                FOR UPDATE
            ");
            $stmt->execute([':id' => $idProducto]);
            $producto = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$producto || $producto['estado'] !== 'activo') {
                $this->db->rollBack();
                return ['ok' => false, 'message' => "El producto ID {$idProducto} ya no está disponible."];
            }
            if ((int)$producto['stock'] < $cantidad) {
                $this->db->rollBack();
                return ['ok' => false, 'message' => "Stock insuficiente de \"{$producto['nombre']}\" (quedan {$producto['stock']})."];
            }

            $subtotal = (float)$producto['precio'] * $cantidad;
            $total += $subtotal;

            $lineas[] = [
                'tipo'        => 'producto',
                'id_producto' => $idProducto,
                'id_servicio' => null,
                'nombre'      => $producto['nombre'],
                'cantidad'    => $cantidad,
                'precio'      => (float)$producto['precio'],
                'subtotal'    => $subtotal,
            ];
        }

        if (empty($lineas)) {
            $this->db->rollBack();
            return ['ok' => false, 'message' => 'Debes agregar al menos un producto o servicio válido.'];
        }

        // --- Calcular descuento (opcional) ---
        // $total en este punto es el subtotal (suma de líneas, sin descuento).
        $subtotal = round($total, 2);

        $descuentoTipo = $datos['descuento_tipo'] ?? 'ninguno';
        if (!in_array($descuentoTipo, ['ninguno', 'porcentaje', 'monto'], true)) {
            $descuentoTipo = 'ninguno';
        }

        $descuentoValor = (float)($datos['descuento_valor'] ?? 0);
        if ($descuentoValor < 0) {
            $descuentoValor = 0;
        }

        $montoDescuento = 0.0;
        if ($descuentoTipo === 'porcentaje') {
            // Máximo 100%
            if ($descuentoValor > 100) {
                $descuentoValor = 100;
            }
            $montoDescuento = $subtotal * ($descuentoValor / 100);
        } elseif ($descuentoTipo === 'monto') {
            // El descuento en soles no puede superar el subtotal
            $montoDescuento = min($descuentoValor, $subtotal);
        } else {
            $descuentoTipo = 'ninguno';
            $descuentoValor = 0;
        }

        $montoDescuento = round($montoDescuento, 2);
        $totalFinal = round($subtotal - $montoDescuento, 2);
        if ($totalFinal < 0) {
            $totalFinal = 0;
        }

        // --- Insertar cabecera usando SOLO execute con array ---
        $codigo = $this->generarCodigoVenta();

        $sql = "
            INSERT INTO ventas
                (codigo_venta, id_cliente, id_usuario, total, subtotal, descuento, descuento_tipo, metodo_pago, descripcion, comprobante_ruta, estado)
            VALUES
                (:codigo, :id_cliente, :id_usuario, :total, :subtotal, :descuento, :descuento_tipo, :metodo_pago, :descripcion, :comprobante_ruta, 'completada')
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':codigo' => $codigo,
            ':id_cliente' => $idCliente,
            ':id_usuario' => $idUsuario,
            ':total' => (string)$totalFinal,
            ':subtotal' => (string)$subtotal,
            ':descuento' => (string)$montoDescuento,
            ':descuento_tipo' => $descuentoTipo,
            ':metodo_pago' => $metodoPago,
            ':descripcion' => $descripcion,
            ':comprobante_ruta' => $comprobanteRuta,
        ]);

        $idVenta = (int)$this->db->lastInsertId();

        // --- Detalles: cada línea puede ser producto o servicio ---
        $insDetalle = $this->db->prepare("
            INSERT INTO detalle_ventas (id_venta, id_producto, id_servicio, tipo_item, cantidad, precio_unitario, subtotal)
            VALUES (:id_venta, :id_producto, :id_servicio, :tipo_item, :cantidad, :precio, :subtotal)
        ");
        $updStock = $this->db->prepare("
            UPDATE productos SET stock = stock - :cantidad_resta
            WHERE id_producto = :id AND stock >= :cantidad_check
        ");
        $insMovimiento = $this->db->prepare("
            INSERT INTO movimientos_inventario
                (id_producto, tipo, cantidad, stock_anterior, stock_nuevo, motivo, id_usuario)
            VALUES
                (:id_producto, 'salida', :cantidad, :stock_anterior, :stock_nuevo, :motivo, :id_usuario)
        ");

        foreach ($lineas as $linea) {
            $insDetalle->execute([
                ':id_venta'    => $idVenta,
                ':id_producto' => $linea['id_producto'],
                ':id_servicio' => $linea['id_servicio'],
                ':tipo_item'   => $linea['tipo'],
                ':cantidad'    => $linea['cantidad'],
                ':precio'      => $linea['precio'],
                ':subtotal'    => $linea['subtotal'],
            ]);

            // Los servicios no manejan stock ni movimientos de inventario.
            if ($linea['tipo'] === 'servicio') {
                continue;
            }

            $updStock->execute([
                ':cantidad_resta' => $linea['cantidad'],
                ':cantidad_check' => $linea['cantidad'],
                ':id'             => $linea['id_producto'],
            ]);

            if ($updStock->rowCount() === 0) {
                $this->db->rollBack();
                return ['ok' => false, 'message' => "No hay stock suficiente de \"{$linea['nombre']}\"."];
            }

            $stmtStock = $this->db->prepare("SELECT stock FROM productos WHERE id_producto = :id");
            $stmtStock->execute([':id' => $linea['id_producto']]);
            $stockNuevo = (int)$stmtStock->fetchColumn();

            $insMovimiento->execute([
                ':id_producto'    => $linea['id_producto'],
                ':cantidad'       => $linea['cantidad'],
                ':stock_anterior' => $stockNuevo + $linea['cantidad'],
                ':stock_nuevo'    => $stockNuevo,
                ':motivo'         => "Venta manual #{$idVenta}",
                ':id_usuario'     => $idUsuario,
            ]);
        }

        $this->db->commit();

        return [
            'ok'        => true,
            'message'   => 'Venta creada correctamente.',
            'id_venta'  => $idVenta,
            'subtotal'  => $subtotal,
            'descuento' => $montoDescuento,
            'total'     => $totalFinal,
        ];

    } catch (\PDOException $e) {
        $this->db->rollBack();
        error_log('[Venta::crearVentaManual] PDOException: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'Error SQL: ' . $e->getMessage()];
    } catch (\Throwable $e) {
        $this->db->rollBack();
        error_log('[Venta::crearVentaManual] Exception: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'Error general: ' . $e->getMessage()];
    }
}

    // ==================== LISTAR ====================

    public function listar(?string $desde = null, ?string $hasta = null): array
    {
        $condiciones = [];
        $params = [];

        if ($desde !== null && $desde !== '') {
            $condiciones[] = 'v.fecha_venta >= :desde';
            $params[':desde'] = $desde . ' 00:00:00';
        }
        if ($hasta !== null && $hasta !== '') {
            $condiciones[] = 'v.fecha_venta <= :hasta';
            $params[':hasta'] = $hasta . ' 23:59:59';
        }

        $where = $condiciones ? ('WHERE ' . implode(' AND ', $condiciones)) : '';

        $sql = "
            SELECT
                v.id_venta,
                v.codigo_venta,
                v.descripcion,
                v.total,
                v.subtotal,
                v.descuento,
                v.descuento_tipo,
                v.metodo_pago,
                v.estado,
                v.fecha_venta,
                u.nombre AS cliente_nombre,
                u.apellido AS cliente_apellido,
                u.correo AS cliente_correo,
                (SELECT COUNT(*) FROM detalle_ventas d WHERE d.id_venta = v.id_venta) AS items
            FROM ventas v
            LEFT JOIN clientes u ON u.id_cliente = v.id_cliente
            $where
            ORDER BY v.fecha_venta DESC
            LIMIT 200
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ==================== DETALLE ====================

    public function detalle(int $idVenta): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                v.id_venta,
                v.codigo_venta,
                v.descripcion,
                v.comprobante_ruta,
                v.total,
                v.subtotal,
                v.descuento,
                v.descuento_tipo,
                v.metodo_pago,
                v.estado,
                v.fecha_venta,
                u.nombre AS cliente_nombre,
                u.apellido AS cliente_apellido,
                u.correo AS cliente_correo
            FROM ventas v
            LEFT JOIN clientes u ON u.id_cliente = v.id_cliente
            WHERE v.id_venta = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $idVenta]);
        $venta = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$venta) {
            return null;
        }

        $stmtItems = $this->db->prepare("
            SELECT
                COALESCE(p.nombre, s.nombre) AS nombre_producto,
                COALESCE(d.tipo_item, IF(d.id_servicio IS NOT NULL, 'servicio', 'producto')) AS tipo_item,
                d.cantidad, d.precio_unitario, d.subtotal
            FROM detalle_ventas d
            LEFT JOIN productos p ON p.id_producto = d.id_producto
            LEFT JOIN servicios s ON s.id_servicio = d.id_servicio
            WHERE d.id_venta = :id
            ORDER BY d.id_detalle ASC
        ");
        $stmtItems->execute([':id' => $idVenta]);
        $venta['items'] = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

        return $venta;
    }

    // ==================== MIS COMPRAS ====================

    public function listarPorCliente(int $idUsuario): array
    {
        $stmt = $this->db->prepare("
            SELECT v.id_venta, v.codigo_venta, v.total, v.metodo_pago, v.estado, v.fecha_venta
            FROM ventas v
            INNER JOIN clientes c ON c.id_cliente = v.id_cliente
            INNER JOIN usuarios u ON u.correo = c.correo
            WHERE u.id = :id
            ORDER BY v.fecha_venta DESC
        ");
        $stmt->execute([':id' => $idUsuario]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ==================== KPIs ====================

    public function resumen(): array
    {
        $stmt = $this->db->query("
            SELECT
                COALESCE(SUM(CASE WHEN MONTH(fecha_venta) = MONTH(CURDATE()) AND YEAR(fecha_venta) = YEAR(CURDATE()) AND estado = 'completada' THEN total ELSE 0 END), 0) AS ventas_mes,
                COALESCE(SUM(CASE WHEN estado = 'completada' THEN 1 ELSE 0 END), 0) AS ordenes_totales,
                COALESCE(SUM(CASE WHEN DATE(fecha_venta) = CURDATE() AND estado = 'completada' THEN total ELSE 0 END), 0) AS ventas_hoy,
                COALESCE(AVG(CASE WHEN estado = 'completada' THEN total END), 0) AS ticket_promedio
            FROM ventas
        ");
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'ventas_mes'      => 0,
            'ordenes_totales' => 0,
            'ventas_hoy'      => 0,
            'ticket_promedio' => 0,
        ];
    }

    // ==================== PRIVADOS ====================

    private function resolverIdCliente(int $idUsuario): int
    {
        $stmt = $this->db->prepare("SELECT nombre, apellido, correo FROM usuarios WHERE id = :id");
        $stmt->execute([':id' => $idUsuario]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$usuario) {
            throw new \RuntimeException('Usuario no encontrado.');
        }

        $buscar = $this->db->prepare("SELECT id_cliente FROM clientes WHERE correo = :correo LIMIT 1");
        $buscar->execute([':correo' => $usuario['correo']]);
        $idCliente = $buscar->fetchColumn();

        if ($idCliente) {
            return (int)$idCliente;
        }

        $crear = $this->db->prepare("
            INSERT INTO clientes (nombre, apellido, correo)
            VALUES (:nombre, :apellido, :correo)
        ");
        $crear->execute([
            ':nombre'   => $usuario['nombre'],
            ':apellido' => $usuario['apellido'],
            ':correo'   => $usuario['correo'],
        ]);
        return (int)$this->db->lastInsertId();
    }
}