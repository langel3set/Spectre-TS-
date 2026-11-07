<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';

class Ticket
{
    private PDO $db;

    public function __construct()
        {
            $this->db = getDB();
        }

        public function crear(
        int $idCliente,
        string $titulo,
        string $descripcion,
        string $tipo,
        string $prioridad
    ): int|false {
        $codigo = 'TICK-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
        $sql = "INSERT INTO tickets 
                (codigo_ticket, id_cliente, titulo, descripcion_problema, tipo, prioridad, estado, fecha_creacion)
                VALUES (?, ?, ?, ?, ?, ?, 'pendiente', NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$codigo, $idCliente, $titulo, $descripcion, $tipo, $prioridad]);
        
        $id = $this->db->lastInsertId();
        // Si el ID es false o 0, devolvemos false; si es numérico, lo convertimos a int
        return $id ? (int)$id : false;
    }

    public function contarPorEstado(string $estado): int
    {
        $sql = "SELECT COUNT(*) as total FROM tickets WHERE estado = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$estado]);
        return (int) $stmt->fetch()['total'];
    }
    public function guardarAdjunto(int $idTicket, string $nombreOriginal, string $ruta, string $tipo, int $tamano): bool
    {
        $sql = "INSERT INTO ticket_adjuntos (id_ticket, nombre_original, ruta_archivo, tipo_archivo, tamano) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$idTicket, $nombreOriginal, $ruta, $tipo, $tamano]);
    }

    public function listarPorCliente(int $idCliente): array
    {
        $sql = "SELECT * FROM tickets WHERE id_cliente = ? ORDER BY fecha_creacion DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$idCliente]);
        return $stmt->fetchAll();
    }

    public function listarTodos(): array
    {
        $sql = "SELECT t.*, c.nombre, c.apellido, c.correo 
                FROM tickets t
                LEFT JOIN clientes c ON t.id_cliente = c.id_cliente
                ORDER BY t.fecha_creacion DESC";
        return $this->db->query($sql)->fetchAll();
    }

    public function obtenerPorId(int $idTicket): array|false
    {
        $sql = "SELECT t.*, c.nombre, c.apellido, c.correo 
                FROM tickets t
                LEFT JOIN clientes c ON t.id_cliente = c.id_cliente
                WHERE t.id_ticket = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$idTicket]);
        return $stmt->fetch();
    }

    public function obtenerAdjuntos(int $idTicket): array
    {
        $sql = "SELECT * FROM ticket_adjuntos WHERE id_ticket = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$idTicket]);
        return $stmt->fetchAll();
    }

    public function agregarComentario(int $idTicket, int $idUsuario, string $comentario, string $accion = 'comentario'): bool
    {
        $sql = "INSERT INTO historial_tickets (id_ticket, id_usuario, accion_realizada, comentario, fecha) 
                VALUES (?, ?, ?, ?, NOW())";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$idTicket, $idUsuario, $accion, $comentario]);
    }

    public function cambiarEstado(int $idTicket, string $nuevoEstado): bool
    {
        $sql = "UPDATE tickets SET estado = ? WHERE id_ticket = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$nuevoEstado, $idTicket]);
    }

    public function obtenerHistorial(int $idTicket): array
    {
        $sql = "SELECT h.*, u.nombre, u.apellido 
                FROM historial_tickets h
                LEFT JOIN usuarios u ON h.id_usuario = u.id
                WHERE h.id_ticket = ?
                ORDER BY h.fecha ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$idTicket]);
        return $stmt->fetchAll();
    }

    public function asignarUsuario(int $idTicket, int $idUsuario): bool
    {
        $sql = "UPDATE tickets SET id_usuario = ? WHERE id_ticket = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$idUsuario, $idTicket]);
    }

    public function cerrarTicket(int $idTicket, string $solucion): bool
    {
        $sql = "UPDATE tickets SET estado = 'resuelto', fecha_cierre = NOW(), solucion = ? WHERE id_ticket = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$solucion, $idTicket]);
    }
}