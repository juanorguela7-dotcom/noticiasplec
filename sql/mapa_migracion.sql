-- ════════════════════════════════════════════════════════════════════
-- MAPA VIVO DE COLOMBIA — migración (Noticias PLEC)
-- · Crea `departamentos` (los 33: 32 + Bogotá D.C.) y `municipios`
--   (por ahora los 47 del Tolima; otros departamentos se agregan con
--   herramientas/mapa/generar_mapa.php, que genera su propio SQL).
-- · Agrega a `noticias` las columnas `departamento_id` y `municipio_id`
--   (NULL = sin ubicación: noticias antiguas, nacionales o internacionales,
--   que no salen en el mapa).
-- · Los id son el código DANE (Tolima = 73, Ibagué = 73001): así son
--   iguales en la base local y en la de Railway.
-- · Se puede ejecutar varias veces: cada paso revisa antes si ya existe.
--   No borra ni modifica ninguna noticia.
-- · Compatible con MariaDB 10.4 (XAMPP) y MySQL 8 (Railway).
-- Uso: phpMyAdmin → base `noticias_plec` → pestaña SQL → pegar → Continuar
-- Fuente de nombres y códigos: DANE, Marco Geoestadístico Nacional
-- (vía OCHA/HDX COD-AB).
-- ════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;

-- ── 1) DEPARTAMENTOS ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS departamentos (
    id          INT NOT NULL PRIMARY KEY,        -- código DANE como número (5 = Antioquia)
    nombre      VARCHAR(80) NOT NULL,
    slug        VARCHAR(80) NOT NULL,
    codigo_dane CHAR(2) NOT NULL,
    UNIQUE KEY uq_departamentos_slug (slug),
    UNIQUE KEY uq_departamentos_dane (codigo_dane)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO departamentos (id, nombre, slug, codigo_dane) VALUES
    (5,  'Antioquia', 'antioquia', '05'),
    (8,  'Atlántico', 'atlantico', '08'),
    (11, 'Bogotá D.C.', 'bogota', '11'),
    (13, 'Bolívar', 'bolivar', '13'),
    (15, 'Boyacá', 'boyaca', '15'),
    (17, 'Caldas', 'caldas', '17'),
    (18, 'Caquetá', 'caqueta', '18'),
    (19, 'Cauca', 'cauca', '19'),
    (20, 'Cesar', 'cesar', '20'),
    (23, 'Córdoba', 'cordoba', '23'),
    (25, 'Cundinamarca', 'cundinamarca', '25'),
    (27, 'Chocó', 'choco', '27'),
    (41, 'Huila', 'huila', '41'),
    (44, 'La Guajira', 'la-guajira', '44'),
    (47, 'Magdalena', 'magdalena', '47'),
    (50, 'Meta', 'meta', '50'),
    (52, 'Nariño', 'narino', '52'),
    (54, 'Norte de Santander', 'norte-de-santander', '54'),
    (63, 'Quindío', 'quindio', '63'),
    (66, 'Risaralda', 'risaralda', '66'),
    (68, 'Santander', 'santander', '68'),
    (70, 'Sucre', 'sucre', '70'),
    (73, 'Tolima', 'tolima', '73'),
    (76, 'Valle del Cauca', 'valle-del-cauca', '76'),
    (81, 'Arauca', 'arauca', '81'),
    (85, 'Casanare', 'casanare', '85'),
    (86, 'Putumayo', 'putumayo', '86'),
    (88, 'San Andrés y Providencia', 'san-andres', '88'),
    (91, 'Amazonas', 'amazonas', '91'),
    (94, 'Guainía', 'guainia', '94'),
    (95, 'Guaviare', 'guaviare', '95'),
    (97, 'Vaupés', 'vaupes', '97'),
    (99, 'Vichada', 'vichada', '99')
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), slug = VALUES(slug), codigo_dane = VALUES(codigo_dane);

-- ── 2) MUNICIPIOS ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS municipios (
    id              INT NOT NULL PRIMARY KEY,    -- código DANE como número (73001 = Ibagué)
    departamento_id INT NOT NULL,
    nombre          VARCHAR(100) NOT NULL,
    slug            VARCHAR(100) NOT NULL,
    codigo_dane     CHAR(5) NOT NULL,
    UNIQUE KEY uq_municipios_dane (codigo_dane),
    UNIQUE KEY uq_municipios_dep_slug (departamento_id, slug),
    CONSTRAINT fk_municipios_departamento FOREIGN KEY (departamento_id) REFERENCES departamentos (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Los 47 municipios del Tolima (DANE 73)
INSERT INTO municipios (id, departamento_id, nombre, slug, codigo_dane) VALUES
    (73024, 73, 'Alpujarra', 'alpujarra', '73024'),
    (73026, 73, 'Alvarado', 'alvarado', '73026'),
    (73030, 73, 'Ambalema', 'ambalema', '73030'),
    (73043, 73, 'Anzoátegui', 'anzoategui', '73043'),
    (73055, 73, 'Armero', 'armero', '73055'),
    (73067, 73, 'Ataco', 'ataco', '73067'),
    (73124, 73, 'Cajamarca', 'cajamarca', '73124'),
    (73148, 73, 'Carmen de Apicalá', 'carmen-de-apicala', '73148'),
    (73152, 73, 'Casabianca', 'casabianca', '73152'),
    (73168, 73, 'Chaparral', 'chaparral', '73168'),
    (73200, 73, 'Coello', 'coello', '73200'),
    (73217, 73, 'Coyaima', 'coyaima', '73217'),
    (73226, 73, 'Cunday', 'cunday', '73226'),
    (73236, 73, 'Dolores', 'dolores', '73236'),
    (73268, 73, 'Espinal', 'espinal', '73268'),
    (73270, 73, 'Falan', 'falan', '73270'),
    (73275, 73, 'Flandes', 'flandes', '73275'),
    (73283, 73, 'Fresno', 'fresno', '73283'),
    (73319, 73, 'Guamo', 'guamo', '73319'),
    (73347, 73, 'Herveo', 'herveo', '73347'),
    (73349, 73, 'Honda', 'honda', '73349'),
    (73001, 73, 'Ibagué', 'ibague', '73001'),
    (73352, 73, 'Icononzo', 'icononzo', '73352'),
    (73408, 73, 'Lérida', 'lerida', '73408'),
    (73411, 73, 'Líbano', 'libano', '73411'),
    (73449, 73, 'Melgar', 'melgar', '73449'),
    (73461, 73, 'Murillo', 'murillo', '73461'),
    (73483, 73, 'Natagaima', 'natagaima', '73483'),
    (73504, 73, 'Ortega', 'ortega', '73504'),
    (73520, 73, 'Palocabildo', 'palocabildo', '73520'),
    (73547, 73, 'Piedras', 'piedras', '73547'),
    (73555, 73, 'Planadas', 'planadas', '73555'),
    (73563, 73, 'Prado', 'prado', '73563'),
    (73585, 73, 'Purificación', 'purificacion', '73585'),
    (73616, 73, 'Rioblanco', 'rioblanco', '73616'),
    (73622, 73, 'Roncesvalles', 'roncesvalles', '73622'),
    (73624, 73, 'Rovira', 'rovira', '73624'),
    (73671, 73, 'Saldaña', 'saldana', '73671'),
    (73675, 73, 'San Antonio', 'san-antonio', '73675'),
    (73678, 73, 'San Luis', 'san-luis', '73678'),
    (73443, 73, 'San Sebastián de Mariquita', 'san-sebastian-de-mariquita', '73443'),
    (73686, 73, 'Santa Isabel', 'santa-isabel', '73686'),
    (73770, 73, 'Suárez', 'suarez', '73770'),
    (73854, 73, 'Valle de San Juan', 'valle-de-san-juan', '73854'),
    (73861, 73, 'Venadillo', 'venadillo', '73861'),
    (73870, 73, 'Villahermosa', 'villahermosa', '73870'),
    (73873, 73, 'Villarrica', 'villarrica', '73873')
ON DUPLICATE KEY UPDATE departamento_id = VALUES(departamento_id), nombre = VALUES(nombre),
    slug = VALUES(slug), codigo_dane = VALUES(codigo_dane);

-- ── 3) UBICACIÓN DE CADA NOTICIA (opcional: NULL = sin ubicación) ────
SET @hay := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'noticias' AND COLUMN_NAME = 'departamento_id');
SET @sql := IF(@hay = 0,
    'ALTER TABLE noticias ADD COLUMN departamento_id INT NULL DEFAULT NULL AFTER categoria_id',
    'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @hay := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'noticias' AND COLUMN_NAME = 'municipio_id');
SET @sql := IF(@hay = 0,
    'ALTER TABLE noticias ADD COLUMN municipio_id INT NULL DEFAULT NULL AFTER departamento_id',
    'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- Índices para las consultas del mapa:
-- WHERE departamento_id = ? AND es_especial = 0 ORDER BY fecha_publicacion DESC
SET @existe := (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'noticias' AND INDEX_NAME = 'idx_mapa_dep');
SET @sql := IF(@existe = 0,
    'ALTER TABLE noticias ADD INDEX idx_mapa_dep (departamento_id, es_especial, fecha_publicacion)', 'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @existe := (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'noticias' AND INDEX_NAME = 'idx_mapa_mun');
SET @sql := IF(@existe = 0,
    'ALTER TABLE noticias ADD INDEX idx_mapa_mun (municipio_id, es_especial, fecha_publicacion)', 'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- Llaves foráneas: no se puede guardar un lugar que no exista. Si algún día
-- se borra un departamento o municipio, sus noticias quedan "sin ubicación".
SET @existe := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'noticias' AND CONSTRAINT_NAME = 'fk_noticias_departamento');
SET @sql := IF(@existe = 0,
    'ALTER TABLE noticias ADD CONSTRAINT fk_noticias_departamento FOREIGN KEY (departamento_id) REFERENCES departamentos (id) ON DELETE SET NULL ON UPDATE CASCADE',
    'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @existe := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'noticias' AND CONSTRAINT_NAME = 'fk_noticias_municipio');
SET @sql := IF(@existe = 0,
    'ALTER TABLE noticias ADD CONSTRAINT fk_noticias_municipio FOREIGN KEY (municipio_id) REFERENCES municipios (id) ON DELETE SET NULL ON UPDATE CASCADE',
    'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- ── 4) COMPROBACIÓN ──────────────────────────────────────────────────
SELECT 'Mapa vivo: migración aplicada' AS resultado,
       (SELECT COUNT(*) FROM departamentos)                        AS departamentos,
       (SELECT COUNT(*) FROM municipios)                           AS municipios,
       (SELECT COUNT(*) FROM noticias WHERE departamento_id IS NOT NULL) AS noticias_con_ubicacion;
