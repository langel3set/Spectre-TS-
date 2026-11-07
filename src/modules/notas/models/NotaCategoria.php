<?php
declare(strict_types=1);

/**
 * src/modules/notas/models/NotaCategoria.php
 *
 * Modelo del árbol de categorías del módulo Notas (Base de Conocimiento).
 * Tabla: notas_categorias (self-referencing: id_categoria_padre).
 */
class NotaCategoria
{
    private PDO $db;

    public function __construct()
    {
        $this->db = getDB();
    }

    /**
     * Devuelve TODAS las categorías (planas), con el conteo de procedimientos
     * visibles que contiene cada una. El árbol se arma en PHP/JS a partir de
     * id_categoria_padre para no depender de CTEs recursivas (compatibilidad
     * con MySQL 5.7, todavía muy usado en hosting compartido).
     */
    public function listarTodas(): array
    {
        $sql = "
            SELECT
                c.id_categoria,
                c.nombre,
                c.slug,
                c.icono,
                c.color,
                c.id_categoria_padre,
                c.orden,
                (SELECT COUNT(*) FROM notas_procedimientos p
                    WHERE p.id_categoria = c.id_categoria AND p.visible = 1) AS total_procedimientos
            FROM notas_categorias c
            ORDER BY c.id_categoria_padre IS NULL DESC, c.orden ASC, c.nombre ASC
        ";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtener(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM notas_categorias WHERE id_categoria = :id");
        $stmt->execute([':id' => $id]);
        $cat = $stmt->fetch(PDO::FETCH_ASSOC);
        return $cat ?: null;
    }

    public function crear(array $datos): array
    {
        $nombre = trim((string)($datos['nombre'] ?? ''));
        if ($nombre === '') {
            return ['ok' => false, 'message' => 'El nombre de la categoría es obligatorio.'];
        }

        $idPadre = !empty($datos['id_categoria_padre']) ? (int)$datos['id_categoria_padre'] : null;
        if ($idPadre !== null && !$this->obtener($idPadre)) {
            return ['ok' => false, 'message' => 'La categoría padre indicada no existe.'];
        }

        $slug = $this->generarSlugUnico($nombre);

        $stmt = $this->db->prepare("
            INSERT INTO notas_categorias (nombre, slug, icono, color, id_categoria_padre, orden)
            VALUES (:nombre, :slug, :icono, :color, :padre, :orden)
        ");
        $stmt->execute([
            ':nombre' => $nombre,
            ':slug'   => $slug,
            ':icono'  => trim((string)($datos['icono'] ?? '')) ?: null,
            ':color'  => trim((string)($datos['color'] ?? '')) ?: '#4f6ef7',
            ':padre'  => $idPadre,
            ':orden'  => (int)($datos['orden'] ?? 0),
        ]);

        return [
            'ok' => true,
            'message' => 'Categoría creada correctamente.',
            'id_categoria' => (int)$this->db->lastInsertId(),
        ];
    }

    public function actualizar(int $id, array $datos): array
    {
        if (!$this->obtener($id)) {
            return ['ok' => false, 'message' => 'Categoría no encontrada.'];
        }

        $nombre = trim((string)($datos['nombre'] ?? ''));
        if ($nombre === '') {
            return ['ok' => false, 'message' => 'El nombre de la categoría es obligatorio.'];
        }

        $idPadre = !empty($datos['id_categoria_padre']) ? (int)$datos['id_categoria_padre'] : null;
        if ($idPadre === $id) {
            return ['ok' => false, 'message' => 'Una categoría no puede ser su propia categoría padre.'];
        }
        if ($idPadre !== null && $this->esDescendiente($id, $idPadre)) {
            return ['ok' => false, 'message' => 'No puedes mover una categoría dentro de una de sus propias subcategorías.'];
        }

        $stmt = $this->db->prepare("
            UPDATE notas_categorias SET
                nombre = :nombre,
                icono = :icono,
                color = :color,
                id_categoria_padre = :padre,
                orden = :orden
            WHERE id_categoria = :id
        ");
        $stmt->execute([
            ':nombre' => $nombre,
            ':icono'  => trim((string)($datos['icono'] ?? '')) ?: null,
            ':color'  => trim((string)($datos['color'] ?? '')) ?: '#4f6ef7',
            ':padre'  => $idPadre,
            ':orden'  => (int)($datos['orden'] ?? 0),
            ':id'     => $id,
        ]);

        return ['ok' => true, 'message' => 'Categoría actualizada correctamente.'];
    }

    public function eliminar(int $id): array
    {
        if (!$this->obtener($id)) {
            return ['ok' => false, 'message' => 'Categoría no encontrada.'];
        }

        $stmtHijos = $this->db->prepare("SELECT COUNT(*) FROM notas_categorias WHERE id_categoria_padre = :id");
        $stmtHijos->execute([':id' => $id]);
        if ((int)$stmtHijos->fetchColumn() > 0) {
            return ['ok' => false, 'message' => 'No puedes eliminar una categoría que tiene subcategorías. Elimínalas primero o muévelas.'];
        }

        $stmtProc = $this->db->prepare("SELECT COUNT(*) FROM notas_procedimientos WHERE id_categoria = :id");
        $stmtProc->execute([':id' => $id]);
        if ((int)$stmtProc->fetchColumn() > 0) {
            return ['ok' => false, 'message' => 'No puedes eliminar una categoría que todavía contiene procedimientos. Muévelos a otra categoría primero.'];
        }

        $stmt = $this->db->prepare("DELETE FROM notas_categorias WHERE id_categoria = :id");
        $stmt->execute([':id' => $id]);

        return ['ok' => true, 'message' => 'Categoría eliminada correctamente.'];
    }

    /**
     * ¿$posibleDescendiente está dentro del árbol de $idCategoria?
     * Evita crear ciclos al reasignar el padre de una categoría.
     */
    private function esDescendiente(int $idCategoria, int $posibleDescendiente): bool
    {
        $actual = $posibleDescendiente;
        $visitados = [];

        while ($actual !== null) {
            if ($actual === $idCategoria) {
                return true;
            }
            if (in_array($actual, $visitados, true)) {
                break; // protección ante datos corruptos con ciclos existentes
            }
            $visitados[] = $actual;

            $stmt = $this->db->prepare("SELECT id_categoria_padre FROM notas_categorias WHERE id_categoria = :id");
            $stmt->execute([':id' => $actual]);
            $padre = $stmt->fetchColumn();
            $actual = $padre !== false && $padre !== null ? (int)$padre : null;
        }

        return false;
    }

    private function generarSlugUnico(string $nombre): string
    {
        $base = $this->slugify($nombre);
        $slug = $base;
        $i = 1;

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM notas_categorias WHERE slug = :slug");
        while (true) {
            $stmt->execute([':slug' => $slug]);
            if ((int)$stmt->fetchColumn() === 0) {
                break;
            }
            $i++;
            $slug = $base . '-' . $i;
        }

        return $slug;
    }

    private function slugify(string $texto): string
    {
        $texto = mb_strtolower(trim($texto), 'UTF-8');
        $texto = strtr($texto, [
            'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','ü'=>'u',
        ]);
        $texto = preg_replace('/[^a-z0-9]+/', '-', $texto) ?? '';
        return trim($texto, '-') ?: 'categoria';
    }
}
