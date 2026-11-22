-- ════════════════════════════════════════════════════════════════════
-- MIGRACIÓN NOTICIAS PLEC — ajusta la base a la versión nueva del sitio
-- · Se puede ejecutar varias veces: cada paso revisa antes si ya existe.
-- · No borra ninguna noticia, usuario ni columna existente.
-- · Compatible con MariaDB 10.4 (XAMPP) y MySQL 8 (Railway).
-- Uso local:  phpMyAdmin → base `noticias_plec` → pestaña SQL → pegar → Continuar
-- ════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;

-- ── 1) CONFIGURACIÓN: estado de la transmisión en vivo ───────────────
--    (antes vivía en el navegador del admin y los visitantes no la veían)
CREATE TABLE IF NOT EXISTS configuracion (
    clave       VARCHAR(100) NOT NULL PRIMARY KEY,
    valor       TEXT,
    actualizado DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO configuracion (clave, valor) VALUES
    ('stream_activo', '0'),
    ('stream_url',    ''),
    ('stream_embed',  ''),
    ('stream_titulo', '');

-- ── 2) PASSKEYS: huellas / Face ID del login (faltaba la tabla) ──────
CREATE TABLE IF NOT EXISTS passkeys (
    id                 INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    admin_id           INT NOT NULL,
    credential_id      VARCHAR(255) NOT NULL,
    public_key         TEXT NOT NULL,
    sign_count         INT UNSIGNED NOT NULL DEFAULT 0,
    nombre_dispositivo VARCHAR(100) NOT NULL DEFAULT 'Dispositivo sin nombre',
    creado_en          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_passkeys_credential (credential_id),
    KEY idx_passkeys_admin (admin_id),
    CONSTRAINT fk_passkeys_usuario FOREIGN KEY (admin_id) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── 3) VISITAS: el código usa `vistas`, la base tenía `visitas` ──────
SET @hay_vistas := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'noticias' AND COLUMN_NAME = 'vistas');
SET @sql := IF(@hay_vistas = 0,
    'ALTER TABLE noticias ADD COLUMN vistas INT UNSIGNED NOT NULL DEFAULT 0',
    'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- Pasar los conteos viejos de `visitas` a `vistas` (sin perder nada; GREATEST
-- evita restar si se ejecuta dos veces). La columna `visitas` se conserva.
SET @hay_visitas := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'noticias' AND COLUMN_NAME = 'visitas');
SET @sql := IF(@hay_visitas = 1,
    'UPDATE noticias SET vistas = GREATEST(vistas, COALESCE(visitas, 0))',
    'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- ── 4) VISITAS POR DÍA: alimenta "Lo más leído" de los últimos 7 días ─
CREATE TABLE IF NOT EXISTS visitas_log (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    noticia_id INT NOT NULL,
    fecha      DATE NOT NULL,
    cantidad   INT UNSIGNED NOT NULL DEFAULT 1,
    UNIQUE KEY uq_noticia_dia (noticia_id, fecha),
    KEY idx_noticia (noticia_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET @existe := (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'visitas_log' AND INDEX_NAME = 'idx_visitas_fecha');
SET @sql := IF(@existe = 0, 'ALTER TABLE visitas_log ADD INDEX idx_visitas_fecha (fecha, noticia_id)', 'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- ── 5) IMPRESOS: el formulario permite números de edición de hasta 20 ─
SET @largo := (SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'impresos' AND COLUMN_NAME = 'edicion_nro');
SET @sql := IF(@largo IS NOT NULL AND @largo < 20,
    'ALTER TABLE impresos MODIFY edicion_nro VARCHAR(20) NOT NULL',
    'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- ── 6) ÍNDICES para las consultas de la portada ──────────────────────
-- Secciones: WHERE categoria_id = ? AND es_especial = 0 ORDER BY fecha_publicacion DESC
SET @existe := (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'noticias' AND INDEX_NAME = 'idx_cat_esp_fpub');
SET @sql := IF(@existe = 0,
    'ALTER TABLE noticias ADD INDEX idx_cat_esp_fpub (categoria_id, es_especial, fecha_publicacion)', 'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- Portada principal: WHERE es_especial = 0 ORDER BY fecha_publicacion DESC
SET @existe := (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'noticias' AND INDEX_NAME = 'idx_esp_fpub');
SET @sql := IF(@existe = 0,
    'ALTER TABLE noticias ADD INDEX idx_esp_fpub (es_especial, fecha_publicacion)', 'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- Lo más leído (respaldo cuando no hay visitas recientes)
SET @existe := (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'noticias' AND INDEX_NAME = 'idx_esp_vistas');
SET @sql := IF(@existe = 0,
    'ALTER TABLE noticias ADD INDEX idx_esp_vistas (es_especial, vistas)', 'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- ── 7) COMPROBACIÓN ──────────────────────────────────────────────────
SELECT 'Migración aplicada' AS resultado,
       (SELECT COUNT(*) FROM configuracion)                    AS filas_configuracion,
       (SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'passkeys') AS tabla_passkeys,
       (SELECT SUM(vistas) FROM noticias)                      AS total_vistas,
       (SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'impresos' AND COLUMN_NAME = 'edicion_nro') AS largo_edicion;
