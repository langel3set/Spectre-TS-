<?php
declare(strict_types=1);

class User
{
    private PDO $db;

    public function __construct()
    {
        $this->db = getDB();
    }

    public function login(string $correo, string $password): ?array
    {
        $sql = "SELECT *
                FROM usuarios
                WHERE correo = :correo
                AND estado='activo'
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':correo' => trim($correo)
        ]);

        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$usuario) {
            return null;
        }

        if (!password_verify($password, $usuario['password'])) {
            return null;
        }
        unset($usuario['password']);
        return $usuario;
    }

    public function registrar(
        string $nombre,
        string $apellido,
        string $correo,
        string $password
    ): bool {

        $sql = "INSERT INTO usuarios
        (
            nombre,
            apellido,
            correo,
            password,
            rol,
            estado
        )
        VALUES
        (
            :nombre,
            :apellido,
            :correo,
            :password,
            'cliente',
            'activo'
        )";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':nombre' => trim($nombre),
            ':apellido' => trim($apellido),
            ':correo' => trim($correo),
            ':password' => password_hash(
                $password,
                PASSWORD_DEFAULT
            )
        ]);
    }


    public function existeCorreo(string $correo): bool
    {
        $stmt = $this->db->prepare("
            SELECT id
            FROM usuarios
            WHERE correo=:correo
            LIMIT 1
        ");
        $stmt->execute([
            ':correo'=>$correo
        ]);
        return (bool)$stmt->fetchColumn();
    }

    public function buscarPorCorreo(string $correo): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM usuarios
            WHERE correo=:correo
            LIMIT 1
        ");
        $stmt->execute([
            ':correo'=>$correo
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }


    public function buscarPorId(int $id): ?array
    {

        $stmt = $this->db->prepare("
            SELECT *
            FROM usuarios
            WHERE id=:id
        ");
        $stmt->execute([
            ':id'=>$id
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    }


    public function guardarToken(
        int $usuario,
        string $token,
        string $expira
    ): bool {

        $stmt = $this->db->prepare("
            INSERT INTO password_resets
            (
                id_usuario,
                token,
                expires_at
            )
            VALUES
            (
                :usuario,
                :token,
                :expira
            )
        ");

        return $stmt->execute([
            ':usuario'=>$usuario,
            ':token'=>$token,
            ':expira'=>$expira
        ]);

    }

    public function buscarToken(string $token): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                pr.*,
                u.correo,
                u.nombre
            FROM password_resets pr
            INNER JOIN usuarios u
                ON u.id = pr.id_usuario
            WHERE pr.token = :token
            LIMIT 1
        ");

        $stmt->execute([
            ':token' => $token
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    public function actualizarPassword(
        int $usuario,
        string $password
    ): bool {

        $stmt = $this->db->prepare("
        UPDATE usuarios
        SET password=:password
        WHERE id=:id
        ");

        $stmt->execute([
            ':password'=>password_hash(
                $password,
                PASSWORD_DEFAULT
            ),
            ':id'=>$usuario
        ]);

        // execute() devuelve true aunque no se haya actualizado ninguna fila
        // (por ejemplo, si $usuario no coincide con ningún id). Por eso
        // comprobamos rowCount() para saber si el UPDATE realmente aplicó.
        return $stmt->rowCount() > 0;

    }

    /**
 * Obtiene todos los clientes (usuarios con rol 'cliente' y estado 'activo')
 */
public function listarClientes(): array
{
    $sql = "SELECT id, nombre, apellido, correo, fecha_creacion, estado 
            FROM usuarios 
            WHERE rol = 'cliente' AND estado = 'activo'
            ORDER BY id DESC";
    $stmt = $this->db->query($sql);
    return $stmt->fetchAll();
}

    public function contarClientes(): int
{
    $sql = "SELECT COUNT(*) as total FROM usuarios WHERE rol = 'cliente' AND estado = 'activo'";
    $stmt = $this->db->prepare($sql);
    $stmt->execute();
    $result = $stmt->fetch();
    return (int) ($result['total'] ?? 0);
}
    public function eliminarToken(string $token): bool
    {
        $stmt=$this->db->prepare("
        DELETE FROM password_resets
        WHERE token=:token
        ");
        return $stmt->execute([
            ':token'=>$token
        ]);
    }

}