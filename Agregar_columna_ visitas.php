-- Ejecuta esto UNA SOLA VEZ en tu base de datos (phpMyAdmin, Adminer, o consola MySQL)
-- Agrega la columna que guardará el número de visitas de cada noticia.

ALTER TABLE noticias
ADD COLUMN vistas INT UNSIGNED NOT NULL DEFAULT 0;