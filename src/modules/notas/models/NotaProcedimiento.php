<?php
declare(strict_types=1);

/**
 * src/modules/notas/models/NotaProcedimiento.php
 *
 * Modelo principal del módulo Notas (Base de Conocimiento Técnica).
 * Gestiona procedimientos junto con sus pasos, ítems de checklist,
 * etiquetas, favoritos, historial de uso y versiones — todo en una
 * sola clase porque estas tablas viven y mueren junto al procedimiento
 * (mismo patrón que Venta::crearVentaManual con detalle_ventas).
 *
 * Tipos de paso soportados (columna notas_pasos.tipo):
 *   texto, cmd, powershell, bash, codigo, sql, json, xml, html, css,
 *   javascript, php, reg, archivo, enlace, video, imagen, nota,
 *   checklist, advertencia, recomendacion
 */
class NotaProcedimiento
{
    private PDO $db;

    public const TIPOS_PASO = [
        'texto', 'cmd', 'powershell', 'bash', 'codigo', 'sql',
        'json', 'xml', 'html', 'css', 'javascript', 'php', 'reg',
        'archivo', 'enlace', 'video', 'imagen', 'nota',
        'checklist', 'advertencia', 'recomendacion',
    ];

    /** Tipos de paso que se muestran como bloque de código con Prism.js */
    public const TIPOS_CODIGO = [
        'cmd', 'powershell', 'bash', 'codigo', 'sql', 'json',
        'xml', 'html', 'css', 'javascript', 'php', 'reg',
    ];

    public function __construct()
    {
        $this->db = getDB();
    }

    // ==================== LISTADO / BÚSQUEDA ====================

    /**
     * Lista procedimientos con filtros combinables.
     *
     * @param array{
     *   categoria?: ?int, busqueda?: ?string, favoritos?: bool,
     *   id_usuario?: ?int, autor?: ?int, estado?: ?string, etiqueta?: ?int,
     *   orden?: string
     * } $filtros
     */
    public function listar(array $filtros = []): array
    {
        $idUsuario = $filtros['id_usuario'] ?? null;

        $sql = "
            SELECT
                p.id_procedimiento, p.titulo, p.descripcion, p.icono, p.color,
                p.estado, p.visible, p.veces_utilizado, p.veces_copiado,
                p.version_actual, p.fecha_creacion, p.fecha_modificacion,
                c.id_categoria, c.nombre AS categoria_nombre, c.color AS categoria_color,
                u.nombre AS autor_nombre, u.apellido AS autor_apellido,
                (SELECT COUNT(*) FROM notas_pasos ps WHERE ps.id_procedimiento = p.id_procedimiento) AS total_pasos,
                (SELECT GROUP_CONCAT(e.nombre SEPARATOR '||')
                    FROM notas_procedimiento_etiqueta pe
                    JOIN notas_etiquetas e ON e.id_etiqueta = pe.id_etiqueta
                    WHERE pe.id_procedimiento = p.id_procedimiento) AS etiquetas_raw
                " . ($idUsuario ? ",
                (SELECT COUNT(*) FROM notas_favoritos f
                    WHERE f.id_procedimiento = p.id_procedimiento AND f.id_usuario = :id_usuario_fav) AS es_favorito
                " : ", 0 AS es_favorito") . "
            FROM notas_procedimientos p
            JOIN notas_categorias c ON c.id_categoria = p.id_categoria
            LEFT JOIN usuarios u ON u.id = p.id_usuario_autor
            WHERE p.visible = 1
        ";
        $params = [];
        if ($idUsuario) {
            $params[':id_usuario_fav'] = $idUsuario;
        }

        if (!empty($filtros['categoria'])) {
            // Incluye la categoría exacta y todas sus subcategorías (2 niveles, suficiente para el árbol propuesto)
            $sql .= " AND (c.id_categoria = :categoria1 OR c.id_categoria_padre = :categoria2)";
            $params[':categoria1'] = (int)$filtros['categoria'];
            $params[':categoria2'] = (int)$filtros['categoria'];
        }

        if (!empty($filtros['estado'])) {
            $sql .= " AND p.estado = :estado";
            $params[':estado'] = $filtros['estado'];
        }

        if (!empty($filtros['autor'])) {
            $sql .= " AND p.id_usuario_autor = :autor";
            $params[':autor'] = (int)$filtros['autor'];
        }

        if (!empty($filtros['etiqueta'])) {
            $sql .= " AND EXISTS (
                SELECT 1 FROM notas_procedimiento_etiqueta pe2
                WHERE pe2.id_procedimiento = p.id_procedimiento AND pe2.id_etiqueta = :etiqueta
            )";
            $params[':etiqueta'] = (int)$filtros['etiqueta'];
        }

        if (!empty($filtros['favoritos']) && $idUsuario) {
            $sql .= " AND EXISTS (
                SELECT 1 FROM notas_favoritos f2
                WHERE f2.id_procedimiento = p.id_procedimiento AND f2.id_usuario = :id_usuario_solo_fav
            )";
            $params[':id_usuario_solo_fav'] = $idUsuario;
        }

        if (!empty($filtros['busqueda'])) {
            $q = trim((string)$filtros['busqueda']);
            // Búsqueda por título/descripción (FULLTEXT) + contenido de pasos + etiquetas
            $sql .= " AND (
                MATCH(p.titulo, p.descripcion) AGAINST (:q_ft IN BOOLEAN MODE)
                OR p.titulo LIKE :q_like1
                OR p.descripcion LIKE :q_like2
                OR EXISTS (
                    SELECT 1 FROM notas_pasos ps2
                    WHERE ps2.id_procedimiento = p.id_procedimiento
                      AND (ps2.titulo LIKE :q_like3 OR ps2.contenido LIKE :q_like4)
                )
                OR EXISTS (
                    SELECT 1 FROM notas_procedimiento_etiqueta pe3
                    JOIN notas_etiquetas e3 ON e3.id_etiqueta = pe3.id_etiqueta
                    WHERE pe3.id_procedimiento = p.id_procedimiento AND e3.nombre LIKE :q_like5
                )
            )";
            // BOOLEAN MODE con comodín '*' para búsquedas parciales tipo "office*"
            $params[':q_ft'] = $q . '*';
            $like = '%' . $q . '%';
            $params[':q_like1'] = $like;
            $params[':q_like2'] = $like;
            $params[':q_like3'] = $like;
            $params[':q_like4'] = $like;
            $params[':q_like5'] = $like;
        }

        $orden = $filtros['orden'] ?? 'recientes';
        $sql .= match ($orden) {
            'mas_usados'  => " ORDER BY p.veces_utilizado DESC, p.fecha_modificacion DESC",
            'alfabetico'  => " ORDER BY p.titulo ASC",
            'antiguos'    => " ORDER BY p.fecha_creacion ASC",
            default       => " ORDER BY p.fecha_modificacion DESC",
        };

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($filas as &$fila) {
            $fila['etiquetas'] = $fila['etiquetas_raw'] ? explode('||', $fila['etiquetas_raw']) : [];
            unset($fila['etiquetas_raw']);
            $fila['es_favorito'] = (bool)$fila['es_favorito'];
        }
        unset($fila);

        return $filas;
    }

    // ==================== DETALLE COMPLETO ====================

    /**
     * Devuelve un procedimiento completo: cabecera + pasos (con sus ítems
     * de checklist) + etiquetas + adjuntos. Ideal para el modal de "ver".
     */
    public function obtenerCompleto(int $id, ?int $idUsuario = null): ?array
    {
        $stmt = $this->db->prepare("
            SELECT p.*, c.nombre AS categoria_nombre, c.color AS categoria_color,
                   u.nombre AS autor_nombre, u.apellido AS autor_apellido
            FROM notas_procedimientos p
            JOIN notas_categorias c ON c.id_categoria = p.id_categoria
            LEFT JOIN usuarios u ON u.id = p.id_usuario_autor
            WHERE p.id_procedimiento = :id
        ");
        $stmt->execute([':id' => $id]);
        $proc = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$proc) {
            return null;
        }

        $proc['pasos'] = $this->obtenerPasos($id);
        $proc['etiquetas'] = $this->obtenerEtiquetas($id);
        $proc['adjuntos'] = $this->obtenerAdjuntos($id);

        if ($idUsuario) {
            $stmtFav = $this->db->prepare("SELECT COUNT(*) FROM notas_favoritos WHERE id_usuario = :u AND id_procedimiento = :p");
            $stmtFav->execute([':u' => $idUsuario, ':p' => $id]);
            $proc['es_favorito'] = (bool)$stmtFav->fetchColumn();
        } else {
            $proc['es_favorito'] = false;
        }

        return $proc;
    }

    private function obtenerPasos(int $idProcedimiento): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM notas_pasos WHERE id_procedimiento = :id ORDER BY orden ASC, id_paso ASC
        ");
        $stmt->execute([':id' => $idProcedimiento]);
        $pasos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($pasos)) {
            return [];
        }

        $idsPasos = array_column($pasos, 'id_paso');
        $placeholders = implode(',', array_fill(0, count($idsPasos), '?'));
        $stmtItems = $this->db->prepare("
            SELECT * FROM notas_checklist_items
            WHERE id_paso IN ($placeholders)
            ORDER BY orden ASC, id_item ASC
        ");
        $stmtItems->execute($idsPasos);
        $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

        $itemsPorPaso = [];
        foreach ($items as $item) {
            $itemsPorPaso[(int)$item['id_paso']][] = $item;
        }

        foreach ($pasos as &$paso) {
            $paso['checklist_items'] = $itemsPorPaso[(int)$paso['id_paso']] ?? [];
        }
        unset($paso);

        return $pasos;
    }

    private function obtenerEtiquetas(int $idProcedimiento): array
    {
        $stmt = $this->db->prepare("
            SELECT e.id_etiqueta, e.nombre
            FROM notas_procedimiento_etiqueta pe
            JOIN notas_etiquetas e ON e.id_etiqueta = pe.id_etiqueta
            WHERE pe.id_procedimiento = :id
            ORDER BY e.nombre ASC
        ");
        $stmt->execute([':id' => $idProcedimiento]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function obtenerAdjuntos(int $idProcedimiento): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM notas_adjuntos WHERE id_procedimiento = :id ORDER BY fecha_subida DESC
        ");
        $stmt->execute([':id' => $idProcedimiento]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ==================== CREAR / ACTUALIZAR ====================

    /**
     * Crea un procedimiento completo con sus pasos y etiquetas en una transacción.
     *
     * @param array $datos {
     *   titulo, descripcion, id_categoria, icono, color, estado, id_usuario_autor,
     *   etiquetas: string[] (nombres),
     *   pasos: array<array{titulo?, descripcion?, tipo, contenido?, lenguaje?, checklist_items?: string[]}>
     * }
     */
    public function crear(array $datos): array
    {
        $validacion = $this->validarCabecera($datos);
        if (!$validacion['ok']) {
            return $validacion;
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                INSERT INTO notas_procedimientos
                    (titulo, descripcion, id_categoria, icono, color, estado, visible, id_usuario_autor)
                VALUES
                    (:titulo, :descripcion, :id_categoria, :icono, :color, :estado, 1, :autor)
            ");
            $stmt->execute([
                ':titulo'       => trim($datos['titulo']),
                ':descripcion'  => trim((string)($datos['descripcion'] ?? '')) ?: null,
                ':id_categoria' => (int)$datos['id_categoria'],
                ':icono'        => trim((string)($datos['icono'] ?? '')) ?: null,
                ':color'        => trim((string)($datos['color'] ?? '')) ?: '#4f6ef7',
                ':estado'       => $datos['estado'] ?? 'publicado',
                ':autor'        => $datos['id_usuario_autor'] ?? null,
            ]);

            $idProcedimiento = (int)$this->db->lastInsertId();

            $this->guardarPasos($idProcedimiento, $datos['pasos'] ?? []);
            $this->guardarEtiquetas($idProcedimiento, $datos['etiquetas'] ?? []);
            $this->guardarVersionInicial($idProcedimiento, $datos);

            $this->db->commit();

            return ['ok' => true, 'message' => 'Procedimiento creado correctamente.', 'id_procedimiento' => $idProcedimiento];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            error_log('[NotaProcedimiento::crear] ' . $e->getMessage());
            return ['ok' => false, 'message' => 'No se pudo crear el procedimiento.'];
        }
    }

    /**
     * Actualiza un procedimiento. Antes de sobrescribir, guarda un snapshot
     * de la versión anterior en notas_versiones (historial de cambios).
     */
    public function actualizar(int $id, array $datos): array
    {
        $actual = $this->obtenerCompleto($id);
        if (!$actual) {
            return ['ok' => false, 'message' => 'Procedimiento no encontrado.'];
        }

        $validacion = $this->validarCabecera($datos);
        if (!$validacion['ok']) {
            return $validacion;
        }

        $this->db->beginTransaction();
        try {
            // 1) Snapshot de la versión ANTERIOR antes de tocar nada
            $this->guardarSnapshotVersion($actual, $datos['id_usuario_modifico'] ?? null);

            // 2) Actualizar cabecera
            $stmt = $this->db->prepare("
                UPDATE notas_procedimientos SET
                    titulo = :titulo, descripcion = :descripcion, id_categoria = :id_categoria,
                    icono = :icono, color = :color, estado = :estado,
                    version_actual = version_actual + 1
                WHERE id_procedimiento = :id
            ");
            $stmt->execute([
                ':titulo'       => trim($datos['titulo']),
                ':descripcion'  => trim((string)($datos['descripcion'] ?? '')) ?: null,
                ':id_categoria' => (int)$datos['id_categoria'],
                ':icono'        => trim((string)($datos['icono'] ?? '')) ?: null,
                ':color'        => trim((string)($datos['color'] ?? '')) ?: '#4f6ef7',
                ':estado'       => $datos['estado'] ?? 'publicado',
                ':id'           => $id,
            ]);

            // 3) Reemplazar pasos y etiquetas (más simple y seguro que hacer diffs)
            $this->db->prepare("DELETE FROM notas_pasos WHERE id_procedimiento = :id")->execute([':id' => $id]);
            $this->db->prepare("DELETE FROM notas_procedimiento_etiqueta WHERE id_procedimiento = :id")->execute([':id' => $id]);

            $this->guardarPasos($id, $datos['pasos'] ?? []);
            $this->guardarEtiquetas($id, $datos['etiquetas'] ?? []);

            $this->db->commit();

            return ['ok' => true, 'message' => 'Procedimiento actualizado correctamente.'];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            error_log('[NotaProcedimiento::actualizar] ' . $e->getMessage());
            return ['ok' => false, 'message' => 'No se pudo actualizar el procedimiento.'];
        }
    }

    public function eliminar(int $id): array
    {
        $stmt = $this->db->prepare("SELECT id_procedimiento FROM notas_procedimientos WHERE id_procedimiento = :id");
        $stmt->execute([':id' => $id]);
        if (!$stmt->fetch()) {
            return ['ok' => false, 'message' => 'Procedimiento no encontrado.'];
        }

        // Los adjuntos físicos en disco no se pueden borrar aquí (el modelo no
        // conoce rutas del sistema de archivos); eso lo hace el controlador
        // antes de llamar a este método, iterando notas_adjuntos.
        $this->db->prepare("DELETE FROM notas_procedimientos WHERE id_procedimiento = :id")->execute([':id' => $id]);

        return ['ok' => true, 'message' => 'Procedimiento eliminado correctamente.'];
    }

    private function validarCabecera(array $datos): array
    {
        if (trim((string)($datos['titulo'] ?? '')) === '') {
            return ['ok' => false, 'message' => 'El título es obligatorio.'];
        }
        if (empty($datos['id_categoria'])) {
            return ['ok' => false, 'message' => 'Debes seleccionar una categoría.'];
        }
        $estado = $datos['estado'] ?? 'publicado';
        if (!in_array($estado, ['borrador', 'publicado', 'archivado'], true)) {
            return ['ok' => false, 'message' => 'Estado inválido.'];
        }
        if (empty($datos['pasos'])) {
            return ['ok' => false, 'message' => 'Agrega al menos un paso al procedimiento.'];
        }
        return ['ok' => true];
    }

    private function guardarPasos(int $idProcedimiento, array $pasos): void
    {
        $stmtPaso = $this->db->prepare("
            INSERT INTO notas_pasos (id_procedimiento, titulo, descripcion, tipo, contenido, lenguaje, orden)
            VALUES (:proc, :titulo, :descripcion, :tipo, :contenido, :lenguaje, :orden)
        ");
        $stmtItem = $this->db->prepare("
            INSERT INTO notas_checklist_items (id_paso, texto, orden) VALUES (:paso, :texto, :orden)
        ");

        $orden = 0;
        foreach ($pasos as $paso) {
            $tipo = $paso['tipo'] ?? 'texto';
            if (!in_array($tipo, self::TIPOS_PASO, true)) {
                $tipo = 'texto';
            }

            $stmtPaso->execute([
                ':proc'        => $idProcedimiento,
                ':titulo'      => trim((string)($paso['titulo'] ?? '')) ?: null,
                ':descripcion' => trim((string)($paso['descripcion'] ?? '')) ?: null,
                ':tipo'        => $tipo,
                ':contenido'   => $paso['contenido'] ?? null,
                ':lenguaje'    => trim((string)($paso['lenguaje'] ?? '')) ?: null,
                ':orden'       => $orden,
            ]);

            if ($tipo === 'checklist' && !empty($paso['checklist_items']) && is_array($paso['checklist_items'])) {
                $idPaso = (int)$this->db->lastInsertId();
                $ordenItem = 0;
                foreach ($paso['checklist_items'] as $textoItem) {
                    $textoItem = trim((string)$textoItem);
                    if ($textoItem === '') {
                        continue;
                    }
                    $stmtItem->execute([':paso' => $idPaso, ':texto' => $textoItem, ':orden' => $ordenItem]);
                    $ordenItem++;
                }
            }

            $orden++;
        }
    }

    private function guardarEtiquetas(int $idProcedimiento, array $nombresEtiquetas): void
    {
        if (empty($nombresEtiquetas)) {
            return;
        }

        $stmtBuscar = $this->db->prepare("SELECT id_etiqueta FROM notas_etiquetas WHERE nombre = :n");
        $stmtCrear = $this->db->prepare("INSERT INTO notas_etiquetas (nombre, slug) VALUES (:n, :s)");
        $stmtVincular = $this->db->prepare("
            INSERT IGNORE INTO notas_procedimiento_etiqueta (id_procedimiento, id_etiqueta) VALUES (:p, :e)
        ");

        foreach (array_unique($nombresEtiquetas) as $nombre) {
            $nombre = trim((string)$nombre);
            if ($nombre === '') {
                continue;
            }

            $stmtBuscar->execute([':n' => $nombre]);
            $idEtiqueta = $stmtBuscar->fetchColumn();

            if ($idEtiqueta === false) {
                $slug = preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($nombre, 'UTF-8')) ?? $nombre;
                $slug = trim((string)$slug, '-') ?: uniqid('etq-');
                try {
                    $stmtCrear->execute([':n' => $nombre, ':s' => $slug]);
                    $idEtiqueta = (int)$this->db->lastInsertId();
                } catch (\PDOException $e) {
                    // Carrera entre dos guardados simultáneos con la misma etiqueta nueva
                    $stmtBuscar->execute([':n' => $nombre]);
                    $idEtiqueta = $stmtBuscar->fetchColumn();
                    if ($idEtiqueta === false) {
                        continue;
                    }
                }
            }

            $stmtVincular->execute([':p' => $idProcedimiento, ':e' => (int)$idEtiqueta]);
        }
    }

    // ==================== VERSIONES ====================

    private function guardarVersionInicial(int $idProcedimiento, array $datos): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO notas_versiones (id_procedimiento, numero_version, titulo, descripcion, contenido_json, id_usuario_modifico)
            VALUES (:proc, 1, :titulo, :descripcion, :json, :usuario)
        ");
        $stmt->execute([
            ':proc'        => $idProcedimiento,
            ':titulo'      => trim($datos['titulo']),
            ':descripcion' => trim((string)($datos['descripcion'] ?? '')) ?: null,
            ':json'        => json_encode($datos['pasos'] ?? [], JSON_UNESCAPED_UNICODE),
            ':usuario'     => $datos['id_usuario_autor'] ?? null,
        ]);
    }

    private function guardarSnapshotVersion(array $procedimientoActual, ?int $idUsuarioModifico): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO notas_versiones (id_procedimiento, numero_version, titulo, descripcion, contenido_json, id_usuario_modifico)
            VALUES (:proc, :num, :titulo, :descripcion, :json, :usuario)
        ");
        $stmt->execute([
            ':proc'        => $procedimientoActual['id_procedimiento'],
            ':num'         => (int)$procedimientoActual['version_actual'],
            ':titulo'      => $procedimientoActual['titulo'],
            ':descripcion' => $procedimientoActual['descripcion'],
            ':json'        => json_encode($procedimientoActual['pasos'], JSON_UNESCAPED_UNICODE),
            ':usuario'     => $idUsuarioModifico,
        ]);
    }

    public function listarVersiones(int $idProcedimiento): array
    {
        $stmt = $this->db->prepare("
            SELECT v.id_version, v.numero_version, v.titulo, v.descripcion, v.fecha_version,
                   u.nombre AS usuario_nombre, u.apellido AS usuario_apellido
            FROM notas_versiones v
            LEFT JOIN usuarios u ON u.id = v.id_usuario_modifico
            WHERE v.id_procedimiento = :id
            ORDER BY v.numero_version DESC
        ");
        $stmt->execute([':id' => $idProcedimiento]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerVersion(int $idVersion): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM notas_versiones WHERE id_version = :id");
        $stmt->execute([':id' => $idVersion]);
        $version = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$version) {
            return null;
        }
        $version['pasos'] = json_decode($version['contenido_json'], true) ?: [];
        return $version;
    }

    // ==================== FAVORITOS ====================

    public function alternarFavorito(int $idProcedimiento, int $idUsuario): array
    {
        $stmt = $this->db->prepare("SELECT 1 FROM notas_favoritos WHERE id_usuario = :u AND id_procedimiento = :p");
        $stmt->execute([':u' => $idUsuario, ':p' => $idProcedimiento]);

        if ($stmt->fetch()) {
            $this->db->prepare("DELETE FROM notas_favoritos WHERE id_usuario = :u AND id_procedimiento = :p")
                ->execute([':u' => $idUsuario, ':p' => $idProcedimiento]);
            return ['ok' => true, 'favorito' => false, 'message' => 'Quitado de favoritos.'];
        }

        $this->db->prepare("INSERT INTO notas_favoritos (id_usuario, id_procedimiento) VALUES (:u, :p)")
            ->execute([':u' => $idUsuario, ':p' => $idProcedimiento]);
        return ['ok' => true, 'favorito' => true, 'message' => 'Agregado a favoritos.'];
    }

    // ==================== HISTORIAL DE USO / CONTADORES ====================

    /**
     * Registra que se abrió/copió/exportó un procedimiento y actualiza
     * los contadores agregados (veces_utilizado, veces_copiado).
     */
    public function registrarUso(int $idProcedimiento, string $accion, ?int $idUsuario, ?int $idPaso = null): void
    {
        if (!in_array($accion, ['abrir', 'copiar', 'exportar'], true)) {
            return;
        }

        $stmt = $this->db->prepare("
            INSERT INTO notas_historial_uso (id_procedimiento, id_usuario, id_paso, accion)
            VALUES (:proc, :usuario, :paso, :accion)
        ");
        $stmt->execute([
            ':proc'    => $idProcedimiento,
            ':usuario' => $idUsuario,
            ':paso'    => $idPaso,
            ':accion'  => $accion,
        ]);

        if ($accion === 'abrir') {
            $this->db->prepare("UPDATE notas_procedimientos SET veces_utilizado = veces_utilizado + 1 WHERE id_procedimiento = :id")
                ->execute([':id' => $idProcedimiento]);
        } elseif ($accion === 'copiar') {
            $this->db->prepare("UPDATE notas_procedimientos SET veces_copiado = veces_copiado + 1 WHERE id_procedimiento = :id")
                ->execute([':id' => $idProcedimiento]);
        }
    }

    /** Últimos procedimientos abiertos por el usuario (para "Recientes"). */
    public function recientesPorUsuario(int $idUsuario, int $limite = 8): array
    {
        $limite = max(1, min(50, $limite));
        $stmt = $this->db->prepare("
            SELECT p.id_procedimiento, p.titulo, p.icono, p.color, MAX(h.fecha) AS ultima_apertura
            FROM notas_historial_uso h
            JOIN notas_procedimientos p ON p.id_procedimiento = h.id_procedimiento
            WHERE h.id_usuario = :u AND h.accion = 'abrir' AND p.visible = 1
            GROUP BY p.id_procedimiento, p.titulo, p.icono, p.color
            ORDER BY ultima_apertura DESC
            LIMIT $limite
        ");
        $stmt->execute([':u' => $idUsuario]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ==================== ESTADÍSTICAS ====================

    public function estadisticas(): array
    {
        $totalProcedimientos = (int)$this->db->query("SELECT COUNT(*) FROM notas_procedimientos WHERE visible = 1")->fetchColumn();
        $totalCategorias = (int)$this->db->query("SELECT COUNT(*) FROM notas_categorias")->fetchColumn();
        $totalCopiados = (int)$this->db->query("SELECT COALESCE(SUM(veces_copiado),0) FROM notas_procedimientos")->fetchColumn();
        $totalFavoritos = (int)$this->db->query("SELECT COUNT(*) FROM notas_favoritos")->fetchColumn();

        $stmtTop = $this->db->query("
            SELECT id_procedimiento, titulo, veces_utilizado
            FROM notas_procedimientos
            WHERE visible = 1
            ORDER BY veces_utilizado DESC, veces_copiado DESC
            LIMIT 5
        ");
        $masUsados = $stmtTop->fetchAll(PDO::FETCH_ASSOC);

        return [
            'total_procedimientos' => $totalProcedimientos,
            'total_categorias'     => $totalCategorias,
            'total_copiados'       => $totalCopiados,
            'total_favoritos'      => $totalFavoritos,
            'mas_usados'           => $masUsados,
        ];
    }

    // ==================== ADJUNTOS ====================

    public function registrarAdjunto(array $datos): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO notas_adjuntos
                (id_procedimiento, id_paso, nombre_original, ruta_archivo, tipo_mime, tamano_bytes, id_usuario_subio)
            VALUES
                (:proc, :paso, :nombre, :ruta, :mime, :tamano, :usuario)
        ");
        $stmt->execute([
            ':proc'    => $datos['id_procedimiento'],
            ':paso'    => $datos['id_paso'] ?? null,
            ':nombre'  => $datos['nombre_original'],
            ':ruta'    => $datos['ruta_archivo'],
            ':mime'    => $datos['tipo_mime'] ?? null,
            ':tamano'  => $datos['tamano_bytes'] ?? null,
            ':usuario' => $datos['id_usuario_subio'] ?? null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function obtenerAdjunto(int $idAdjunto): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM notas_adjuntos WHERE id_adjunto = :id");
        $stmt->execute([':id' => $idAdjunto]);
        $adj = $stmt->fetch(PDO::FETCH_ASSOC);
        return $adj ?: null;
    }

    public function eliminarAdjunto(int $idAdjunto): ?array
    {
        $adj = $this->obtenerAdjunto($idAdjunto);
        if (!$adj) {
            return null;
        }
        $this->db->prepare("DELETE FROM notas_adjuntos WHERE id_adjunto = :id")->execute([':id' => $idAdjunto]);
        return $adj;
    }
}
