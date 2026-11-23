-- ════════════════════════════════════════════════════════════════════
-- MAPA VIVO — BORRAR LAS NOTICIAS DE EJEMPLO (las que empiezan por
-- "[EJEMPLO MAPA]", cargadas con sql/mapa_ejemplos_LOCAL.sql).
-- Borra también sus comentarios y su conteo de visitas por día.
-- No toca ninguna otra noticia. Se puede ejecutar varias veces.
-- ════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;

DELETE c FROM comentarios c JOIN noticias n ON n.id = c.noticia_id WHERE n.titulo LIKE '[EJEMPLO MAPA]%';
DELETE v FROM visitas_log v JOIN noticias n ON n.id = v.noticia_id WHERE n.titulo LIKE '[EJEMPLO MAPA]%';
DELETE FROM noticias WHERE titulo LIKE '[EJEMPLO MAPA]%';

SELECT COUNT(*) AS ejemplos_que_quedan FROM noticias WHERE titulo LIKE '[EJEMPLO MAPA]%';
