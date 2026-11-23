-- ════════════════════════════════════════════════════════════════════
-- MAPA VIVO — NOTICIAS DE EJEMPLO · SOLO PARA PROBAR EN LOCAL (XAMPP)
-- ¡NO las cargues en Railway! Saldrían publicadas en la portada real.
-- · Todas empiezan por "[EJEMPLO MAPA]".
-- · Para borrarlas: sql/mapa_ejemplos_borrar.sql
-- · Las fechas son relativas al momento en que se ejecuta (NOW() - …),
--   así el mapa se ve "vivo": hay noticias de las últimas 24 h y de los
--   últimos 30 días.
-- · Incluye dos casos de control que NO deben salir en el mapa: una noticia
--   internacional sin ubicación y una historia especial (es_especial = 1).
-- Requiere sql/mapa_migracion.sql.
-- ════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;

INSERT INTO noticias (titulo, contenido, imagen, video_url, categoria_id, es_especial, fecha_publicacion, fecha, departamento_id, municipio_id) VALUES
-- Tolima (73)
('[EJEMPLO MAPA] Ibagué alista el Festival Folclórico con 40 comparsas', '<p>Noticia de ejemplo para probar el mapa vivo de la portada. Se borra con sql/mapa_ejemplos_borrar.sql.</p><p>Las comparsas recorrerán la carrera Tercera durante tres días.</p>', 'img/logo-plec.jpg', NULL, 10, 0, NOW() - INTERVAL 2 HOUR, NOW() - INTERVAL 2 HOUR, 73, 73001),
('[EJEMPLO MAPA] Concejo de Ibagué debate el plan de movilidad para 2027', '<p>Noticia de ejemplo para probar el mapa vivo de la portada. Se borra con sql/mapa_ejemplos_borrar.sql.</p>', NULL, NULL, 7, 0, NOW() - INTERVAL 20 HOUR, NOW() - INTERVAL 20 HOUR, 73, 73001),
('[EJEMPLO MAPA] Jornada de salud rural llega a 12 veredas de Chaparral', '<p>Noticia de ejemplo para probar el mapa vivo de la portada. Se borra con sql/mapa_ejemplos_borrar.sql.</p>', 'img/logo-plec.jpg', NULL, 4, 0, NOW() - INTERVAL 9 HOUR, NOW() - INTERVAL 9 HOUR, 73, 73168),
('[EJEMPLO MAPA] Cosecha de arroz en El Espinal supera lo previsto', '<p>Noticia de ejemplo para probar el mapa vivo de la portada. Se borra con sql/mapa_ejemplos_borrar.sql.</p>', NULL, NULL, 8, 0, NOW() - INTERVAL 3 DAY, NOW() - INTERVAL 3 DAY, 73, 73268),
('[EJEMPLO MAPA] Honda recupera sus calles coloniales para el turismo', '<p>Noticia de ejemplo para probar el mapa vivo de la portada. Se borra con sql/mapa_ejemplos_borrar.sql.</p>', NULL, NULL, 10, 0, NOW() - INTERVAL 6 DAY, NOW() - INTERVAL 6 DAY, 73, 73349),
('[EJEMPLO MAPA] Colegio del Líbano gana olimpiada regional de ciencias', '<p>Noticia de ejemplo para probar el mapa vivo de la portada. Se borra con sql/mapa_ejemplos_borrar.sql.</p>', NULL, NULL, 3, 0, NOW() - INTERVAL 12 DAY, NOW() - INTERVAL 12 DAY, 73, 73411),
('[EJEMPLO MAPA] Melgar espera 60.000 visitantes en la temporada de fin de año', '<p>Noticia de ejemplo para probar el mapa vivo de la portada. Se borra con sql/mapa_ejemplos_borrar.sql.</p>', 'img/logo-plec.jpg', NULL, 6, 0, NOW() - INTERVAL 15 DAY, NOW() - INTERVAL 15 DAY, 73, 73449),
('[EJEMPLO MAPA] Gobernación del Tolima anuncia 120 km de vías terciarias', '<p>Noticia de ejemplo para probar el mapa vivo de la portada (ubicada en todo el departamento, sin municipio). Se borra con sql/mapa_ejemplos_borrar.sql.</p>', NULL, NULL, 6, 0, NOW() - INTERVAL 2 DAY, NOW() - INTERVAL 2 DAY, 73, NULL),
('[EJEMPLO MAPA] Ibagué estrena ciclorruta hacia el Cañón del Combeima', '<p>Noticia de ejemplo para probar el mapa vivo de la portada. Se borra con sql/mapa_ejemplos_borrar.sql.</p>', NULL, NULL, 2, 0, NOW() - INTERVAL 5 DAY, NOW() - INTERVAL 5 DAY, 73, 73001),
-- Otros departamentos (sin municipios cargados todavía)
('[EJEMPLO MAPA] Debate en el Congreso por la reforma a la salud', '<p>Noticia de ejemplo para probar el mapa vivo de la portada. Se borra con sql/mapa_ejemplos_borrar.sql.</p>', 'img/logo-plec.jpg', NULL, 7, 0, NOW() - INTERVAL 5 HOUR, NOW() - INTERVAL 5 HOUR, 11, NULL),
('[EJEMPLO MAPA] Bogotá amplía horarios de su red de bibliotecas públicas', '<p>Noticia de ejemplo para probar el mapa vivo de la portada. Se borra con sql/mapa_ejemplos_borrar.sql.</p>', NULL, NULL, 3, 0, NOW() - INTERVAL 25 DAY, NOW() - INTERVAL 25 DAY, 11, NULL),
('[EJEMPLO MAPA] Medellín inaugura un centro de innovación para jóvenes', '<p>Noticia de ejemplo para probar el mapa vivo de la portada. Se borra con sql/mapa_ejemplos_borrar.sql.</p>', NULL, NULL, 1, 0, NOW() - INTERVAL 4 DAY, NOW() - INTERVAL 4 DAY, 5, NULL),
('[EJEMPLO MAPA] Cali será sede del torneo nacional de atletismo', '<p>Noticia de ejemplo para probar el mapa vivo de la portada. Se borra con sql/mapa_ejemplos_borrar.sql.</p>', NULL, NULL, 2, 0, NOW() - INTERVAL 8 DAY, NOW() - INTERVAL 8 DAY, 76, NULL),
('[EJEMPLO MAPA] Neiva amplía su red de vacunación en los barrios', '<p>Noticia de ejemplo para probar el mapa vivo de la portada. Se borra con sql/mapa_ejemplos_borrar.sql.</p>', NULL, NULL, 4, 0, NOW() - INTERVAL 26 HOUR, NOW() - INTERVAL 26 HOUR, 41, NULL),
('[EJEMPLO MAPA] Barranquilla presenta a la reina del Carnaval 2027', '<p>Noticia de ejemplo para probar el mapa vivo de la portada. Se borra con sql/mapa_ejemplos_borrar.sql.</p>', NULL, NULL, 14, 0, NOW() - INTERVAL 3 DAY, NOW() - INTERVAL 3 DAY, 8, NULL),
('[EJEMPLO MAPA] Soacha suma buses accesibles para personas con discapacidad', '<p>Noticia de ejemplo para probar el mapa vivo de la portada. Se borra con sql/mapa_ejemplos_borrar.sql.</p>', NULL, NULL, 16, 0, NOW() - INTERVAL 10 DAY, NOW() - INTERVAL 10 DAY, 25, NULL),
('[EJEMPLO MAPA] Pasto prepara el Carnaval de Negros y Blancos', '<p>Noticia de ejemplo para probar el mapa vivo de la portada. Se borra con sql/mapa_ejemplos_borrar.sql.</p>', NULL, NULL, 10, 0, NOW() - INTERVAL 20 DAY, NOW() - INTERVAL 20 DAY, 52, NULL),
('[EJEMPLO MAPA] Bucaramanga: noticia de hace mes y medio (fuera de los 30 días)', '<p>Noticia de ejemplo: Santander debe verse gris, pero su tooltip muestra esta como la última. Se borra con sql/mapa_ejemplos_borrar.sql.</p>', NULL, NULL, 6, 0, NOW() - INTERVAL 45 DAY, NOW() - INTERVAL 45 DAY, 68, NULL),
-- Casos de control: NO deben aparecer en el mapa
('[EJEMPLO MAPA] (control, sin ubicación) Cumbre climática termina sin acuerdo', '<p>Noticia de ejemplo internacional, sin ubicación: no debe salir en el mapa. Se borra con sql/mapa_ejemplos_borrar.sql.</p>', NULL, NULL, 9, 0, NOW() - INTERVAL 1 HOUR, NOW() - INTERVAL 1 HOUR, NULL, NULL),
('[EJEMPLO MAPA] (control, historia especial) Crónica de la Revista', '<p>Historia especial (es_especial = 1) con ubicación: no debe salir en el mapa de la portada. Se borra con sql/mapa_ejemplos_borrar.sql.</p>', NULL, NULL, 10, 1, NOW() - INTERVAL 3 HOUR, NOW() - INTERVAL 3 HOUR, 73, 73001);

SELECT COUNT(*) AS ejemplos_cargados FROM noticias WHERE titulo LIKE '[EJEMPLO MAPA]%';
