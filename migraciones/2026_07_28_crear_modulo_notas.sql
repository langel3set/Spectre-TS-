-- ============================================================================
-- Migración: Módulo NOTAS (Base de Conocimiento Técnica)
-- Ejecutar en la base de datos `bd_spectre`.
--
-- Diseño: 8 tablas normalizadas (3FN), con claves foráneas e índices.
-- Se explica el propósito de cada tabla justo encima de su CREATE TABLE.
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 1) notas_categorias
--    Árbol de categorías (self-referencing). Cada categoría puede tener un
--    padre (subcategoría) o ser raíz (id_categoria_padre = NULL).
--    Ej: Software > Office > Office 2024 LTSC
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notas_categorias (
    id_categoria        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre              VARCHAR(100)      NOT NULL,
    slug                VARCHAR(120)      NOT NULL,
    icono               VARCHAR(40)       DEFAULT NULL,   -- emoji o código de ícono
    color               VARCHAR(7)        DEFAULT '#4f6ef7',
    id_categoria_padre  INT UNSIGNED      DEFAULT NULL,
    orden               INT UNSIGNED      NOT NULL DEFAULT 0,
    fecha_creacion      DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_notas_categorias_slug (slug),
    KEY idx_notas_categorias_padre (id_categoria_padre),

    CONSTRAINT fk_notascat_padre
        FOREIGN KEY (id_categoria_padre) REFERENCES notas_categorias(id_categoria)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 2) notas_procedimientos
--    La entidad principal: un procedimiento/guía técnica reutilizable.
--    id_categoria usa ON DELETE RESTRICT para evitar borrar por accidente
--    una categoría que todavía tiene procedimientos dentro.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notas_procedimientos (
    id_procedimiento    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    titulo              VARCHAR(200)      NOT NULL,
    descripcion         TEXT              DEFAULT NULL,
    id_categoria        INT UNSIGNED      NOT NULL,
    icono               VARCHAR(40)       DEFAULT NULL,
    color               VARCHAR(7)        DEFAULT '#4f6ef7',
    estado              ENUM('borrador','publicado','archivado') NOT NULL DEFAULT 'publicado',
    visible             TINYINT(1)        NOT NULL DEFAULT 1,
    id_usuario_autor    INT               DEFAULT NULL,
    veces_utilizado     INT UNSIGNED      NOT NULL DEFAULT 0,
    veces_copiado       INT UNSIGNED      NOT NULL DEFAULT 0,
    version_actual      INT UNSIGNED      NOT NULL DEFAULT 1,
    fecha_creacion      DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion  DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    KEY idx_notasproc_categoria (id_categoria),
    KEY idx_notasproc_autor (id_usuario_autor),
    KEY idx_notasproc_estado (estado),
    FULLTEXT KEY ft_notasproc_busqueda (titulo, descripcion),

    CONSTRAINT fk_notasproc_categoria
        FOREIGN KEY (id_categoria) REFERENCES notas_categorias(id_categoria)
        ON DELETE RESTRICT,
    CONSTRAINT fk_notasproc_autor
        FOREIGN KEY (id_usuario_autor) REFERENCES usuarios(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 3) notas_pasos
--    Los pasos ilimitados de cada procedimiento, ordenados. `tipo` define
--    cómo se renderiza el paso en el frontend (código con resaltado,
--    checklist, advertencia, enlace, archivo, video, imagen, etc.)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notas_pasos (
    id_paso             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_procedimiento    INT UNSIGNED      NOT NULL,
    titulo              VARCHAR(200)      DEFAULT NULL,
    descripcion         TEXT              DEFAULT NULL,
    tipo                ENUM(
                            'texto','cmd','powershell','bash','codigo','sql',
                            'json','xml','html','css','javascript','php','reg',
                            'archivo','enlace','video','imagen','nota',
                            'checklist','advertencia','recomendacion'
                         ) NOT NULL DEFAULT 'texto',
    contenido           LONGTEXT          DEFAULT NULL,   -- código / texto / URL
    lenguaje            VARCHAR(30)       DEFAULT NULL,   -- override para resaltado (tipo='codigo')
    orden               INT UNSIGNED      NOT NULL DEFAULT 0,
    fecha_creacion      DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY idx_notaspasos_proc_orden (id_procedimiento, orden),

    CONSTRAINT fk_notaspasos_procedimiento
        FOREIGN KEY (id_procedimiento) REFERENCES notas_procedimientos(id_procedimiento)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 4) notas_checklist_items
--    Ítems marcables de un paso tipo 'checklist' (ej: "Abrir CMD",
--    "Ejecutar DISM", "Reiniciar"...).
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notas_checklist_items (
    id_item             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_paso             INT UNSIGNED      NOT NULL,
    texto               VARCHAR(300)      NOT NULL,
    orden               INT UNSIGNED      NOT NULL DEFAULT 0,

    KEY idx_notaschecklist_paso (id_paso, orden),

    CONSTRAINT fk_notaschecklist_paso
        FOREIGN KEY (id_paso) REFERENCES notas_pasos(id_paso)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 5) notas_etiquetas  +  notas_procedimiento_etiqueta (pivote N:M)
--    Etiquetas reutilizables para clasificar/buscar procedimientos.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notas_etiquetas (
    id_etiqueta         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre              VARCHAR(60)       NOT NULL,
    slug                VARCHAR(70)       NOT NULL,

    UNIQUE KEY uq_notasetq_nombre (nombre),
    UNIQUE KEY uq_notasetq_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notas_procedimiento_etiqueta (
    id_procedimiento    INT UNSIGNED      NOT NULL,
    id_etiqueta         INT UNSIGNED      NOT NULL,

    PRIMARY KEY (id_procedimiento, id_etiqueta),
    KEY idx_notasproceqt_etiqueta (id_etiqueta),

    CONSTRAINT fk_notasproceqt_procedimiento
        FOREIGN KEY (id_procedimiento) REFERENCES notas_procedimientos(id_procedimiento)
        ON DELETE CASCADE,
    CONSTRAINT fk_notasproceqt_etiqueta
        FOREIGN KEY (id_etiqueta) REFERENCES notas_etiquetas(id_etiqueta)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 6) notas_adjuntos
--    Archivos subidos (ZIP, RAR, BAT, REG, XML, EXE, PDF, imágenes, Office...).
--    Puede pertenecer a todo el procedimiento o a un paso específico.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notas_adjuntos (
    id_adjunto          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_procedimiento    INT UNSIGNED      NOT NULL,
    id_paso             INT UNSIGNED      DEFAULT NULL,
    nombre_original     VARCHAR(255)      NOT NULL,
    ruta_archivo        VARCHAR(500)      NOT NULL,
    tipo_mime           VARCHAR(120)      DEFAULT NULL,
    tamano_bytes        INT UNSIGNED      DEFAULT NULL,
    id_usuario_subio    INT               DEFAULT NULL,
    fecha_subida        DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY idx_notasadj_procedimiento (id_procedimiento),
    KEY idx_notasadj_paso (id_paso),

    CONSTRAINT fk_notasadj_procedimiento
        FOREIGN KEY (id_procedimiento) REFERENCES notas_procedimientos(id_procedimiento)
        ON DELETE CASCADE,
    CONSTRAINT fk_notasadj_paso
        FOREIGN KEY (id_paso) REFERENCES notas_pasos(id_paso)
        ON DELETE CASCADE,
    CONSTRAINT fk_notasadj_usuario
        FOREIGN KEY (id_usuario_subio) REFERENCES usuarios(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 7) notas_favoritos
--    Relación N:M usuario <-> procedimiento favorito.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notas_favoritos (
    id_usuario          INT               NOT NULL,
    id_procedimiento    INT UNSIGNED      NOT NULL,
    fecha_marcado       DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id_usuario, id_procedimiento),
    KEY idx_notasfav_procedimiento (id_procedimiento),

    CONSTRAINT fk_notasfav_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_notasfav_procedimiento
        FOREIGN KEY (id_procedimiento) REFERENCES notas_procedimientos(id_procedimiento)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 8) notas_historial_uso
--    Log de aperturas / copiados / exportaciones. Alimenta "Recientes",
--    "Más usados", "Más copiados" y las estadísticas del módulo.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notas_historial_uso (
    id_historial        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_procedimiento    INT UNSIGNED      NOT NULL,
    id_usuario          INT               DEFAULT NULL,
    id_paso             INT UNSIGNED      DEFAULT NULL,
    accion               ENUM('abrir','copiar','exportar') NOT NULL,
    fecha                DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY idx_notashist_proc_fecha (id_procedimiento, fecha),
    KEY idx_notashist_usuario_fecha (id_usuario, fecha),

    CONSTRAINT fk_notashist_procedimiento
        FOREIGN KEY (id_procedimiento) REFERENCES notas_procedimientos(id_procedimiento)
        ON DELETE CASCADE,
    CONSTRAINT fk_notashist_paso
        FOREIGN KEY (id_paso) REFERENCES notas_pasos(id_paso)
        ON DELETE SET NULL,
    CONSTRAINT fk_notashist_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 9) notas_versiones
--    Historial de cambios. Cada vez que se edita un procedimiento se guarda
--    una "foto" (snapshot en JSON) del título, descripción y pasos previos,
--    ANTES de aplicar los cambios nuevos.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notas_versiones (
    id_version           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_procedimiento     INT UNSIGNED      NOT NULL,
    numero_version       INT UNSIGNED      NOT NULL,
    titulo                VARCHAR(200)      NOT NULL,
    descripcion           TEXT              DEFAULT NULL,
    contenido_json        LONGTEXT          NOT NULL,      -- snapshot de los pasos en JSON
    id_usuario_modifico   INT               DEFAULT NULL,
    fecha_version         DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY idx_notasver_procedimiento (id_procedimiento, numero_version),

    CONSTRAINT fk_notasver_procedimiento
        FOREIGN KEY (id_procedimiento) REFERENCES notas_procedimientos(id_procedimiento)
        ON DELETE CASCADE,
    CONSTRAINT fk_notasver_usuario
        FOREIGN KEY (id_usuario_modifico) REFERENCES usuarios(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- Categorías iniciales sugeridas (árbol de ejemplo tomado de tu solicitud).
-- Puedes editarlas/borrarlas libremente desde el módulo una vez creado.
-- ----------------------------------------------------------------------------
INSERT INTO notas_categorias (nombre, slug, icono, color, id_categoria_padre, orden) VALUES
    ('Software',        'software',        '💿', '#4f6ef7', NULL, 1),
    ('Scripts',          'scripts',         '📜', '#10b981', NULL, 2),
    ('Programación',     'programacion',    '💻', '#f59e0b', NULL, 3),
    ('Networking',       'networking',      '🌐', '#06b6d4', NULL, 4),
    ('Drivers',          'drivers',         '🔧', '#8b5cf6', NULL, 5),
    ('Herramientas',     'herramientas',    '🛠️', '#ec4899', NULL, 6);

INSERT INTO notas_categorias (nombre, slug, icono, color, id_categoria_padre, orden)
SELECT 'Windows', 'windows', NULL, '#4f6ef7', id_categoria, 1 FROM notas_categorias WHERE slug='software'
UNION ALL
SELECT 'Office', 'office', NULL, '#4f6ef7', id_categoria, 2 FROM notas_categorias WHERE slug='software'
UNION ALL
SELECT 'Adobe', 'adobe', NULL, '#4f6ef7', id_categoria, 3 FROM notas_categorias WHERE slug='software'
UNION ALL
SELECT 'BIOS', 'bios', NULL, '#8b5cf6', id_categoria, 1 FROM notas_categorias WHERE slug='drivers'
UNION ALL
SELECT 'PowerShell', 'powershell', NULL, '#10b981', id_categoria, 1 FROM notas_categorias WHERE slug='scripts'
UNION ALL
SELECT 'CMD / Batch', 'cmd-batch', NULL, '#10b981', id_categoria, 2 FROM notas_categorias WHERE slug='scripts'
UNION ALL
SELECT 'SQL', 'sql', NULL, '#10b981', id_categoria, 3 FROM notas_categorias WHERE slug='scripts'
UNION ALL
SELECT 'PHP', 'php', NULL, '#f59e0b', id_categoria, 1 FROM notas_categorias WHERE slug='programacion'
UNION ALL
SELECT 'JavaScript', 'javascript', NULL, '#f59e0b', id_categoria, 2 FROM notas_categorias WHERE slug='programacion'
UNION ALL
SELECT 'MySQL', 'mysql', NULL, '#f59e0b', id_categoria, 3 FROM notas_categorias WHERE slug='programacion';
