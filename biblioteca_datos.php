<?php
/* ════════════════════════════════════════════════════════════════════
   NOTICIAS PLEC — DATOS DE LA BIBLIOTECA INTERACTIVA (portada)
   Prepara los "paneles" (secciones) y sus "estantes" (noticias) que
   assets/js/plec-biblioteca.js convierte en la biblioteca deslizante.
   · Solo LEE la base de datos; no modifica nada.
   · Si algo falla aquí, devuelve una lista vacía y la portada clásica
     se muestra como siempre.
   ════════════════════════════════════════════════════════════════════ */

// Secciones de la biblioteca, en el orden en que se deslizan.
// 'cats' => null significa "todas menos Opinión (13) y Editorial (15)",
// igual que el bloque principal de la portada clásica.
function plec_biblioteca_secciones() {
    return [
        ['clave' => 'inicio',        'nombre' => 'Inicio',        'sub' => 'Lo último',             'icono' => 'fa-house',             'color' => '#3b8cff', 'cats' => null],
        ['clave' => 'colombia',      'nombre' => 'Colombia',      'sub' => 'Noticias del país',     'icono' => 'fa-flag',              'color' => '#f2c230', 'cats' => [6]],
        ['clave' => 'politica',      'nombre' => 'Política',      'sub' => 'Poder y decisiones',    'icono' => 'fa-landmark',          'color' => '#ff5a5a', 'cats' => [7]],
        ['clave' => 'salud',         'nombre' => 'Salud',         'sub' => 'Bienestar y medicina',  'icono' => 'fa-heart-pulse',       'color' => '#2ecc71', 'cats' => [4]],
        ['clave' => 'internacional', 'nombre' => 'Internacional', 'sub' => 'El mundo hoy',          'icono' => 'fa-earth-americas',    'color' => '#22c3e6', 'cats' => [9]],
        ['clave' => 'educacion',     'nombre' => 'Educación',     'sub' => 'Aulas y aprendizaje',   'icono' => 'fa-graduation-cap',    'color' => '#ff8a3d', 'cats' => [3]],
        ['clave' => 'farandula',     'nombre' => 'Farándula',     'sub' => 'Espectáculo y cultura', 'icono' => 'fa-masks-theater',     'color' => '#ff5fb0', 'cats' => [14]],
        ['clave' => 'inclusion',     'nombre' => 'Inclusión',     'sub' => 'Diversidad y derechos', 'icono' => 'fa-universal-access',  'color' => '#b07cff', 'cats' => [16]],
        ['clave' => 'opinion',       'nombre' => 'Opinión',       'sub' => 'Editorial y columnas',  'icono' => 'fa-quote-right',       'color' => '#d9c29a', 'cats' => [13, 15]],
    ];
}

function plec_biblioteca_imagen($img) {
    $img = (string)$img;
    if ($img === '') return 'img/placeholder.svg';
    if (strpos($img, 'http') === 0 || strpos($img, 'img/') === 0) return $img;
    return 'img/' . $img;
}

function plec_biblioteca_resumen($html, $largo) {
    $txt = html_entity_decode(strip_tags((string)$html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $txt = trim(preg_replace('/\s+/u', ' ', $txt));
    return mb_strlen($txt) > $largo ? rtrim(mb_substr($txt, 0, $largo)) . '…' : $txt;
}

/**
 * Devuelve ['paneles' => [...], 'noticias' => [id => {...}]].
 * Cada noticia aparece una sola vez en 'noticias' aunque esté en varios
 * paneles (por ejemplo, en Inicio y en su categoría).
 */
function plec_biblioteca_datos($conn, $por_estante = 12) {
    $vacio = ['paneles' => [], 'noticias' => []];
    if (!$conn) return $vacio;

    try {
        // Totales por categoría en una sola consulta (para "34 noticias")
        $totales = [];
        $res = mysqli_query($conn, "SELECT categoria_id, COUNT(*) AS total FROM noticias WHERE es_especial = 0 GROUP BY categoria_id");
        if ($res) {
            while ($f = mysqli_fetch_assoc($res)) $totales[(int)$f['categoria_id']] = (int)$f['total'];
            mysqli_free_result($res);
        }

        $limite   = max(1, min(30, (int)$por_estante));
        $paneles  = [];
        $noticias = [];

        foreach (plec_biblioteca_secciones() as $sec) {
            if ($sec['cats'] === null) {
                $donde = "n.categoria_id NOT IN (13, 15)";
                $total = 0;
                foreach ($totales as $cid => $t) if ($cid !== 13 && $cid !== 15 && $cid !== 0) $total += $t;
            } else {
                $ids   = implode(',', array_map('intval', $sec['cats']));
                $donde = "n.categoria_id IN ($ids)";
                $total = 0;
                foreach ($sec['cats'] as $cid) $total += $totales[(int)$cid] ?? 0;
            }

            $res = mysqli_query($conn, "SELECT n.*, c.nombre AS cat_nombre,
                    TIMESTAMPDIFF(HOUR, n.fecha_publicacion, NOW()) AS horas_publicada
                FROM noticias n
                LEFT JOIN categorias c ON n.categoria_id = c.id
                WHERE n.es_especial = 0 AND $donde
                ORDER BY n.fecha_publicacion DESC
                LIMIT $limite");
            if (!$res) continue;

            $ids_panel = [];
            while ($row = mysqli_fetch_assoc($res)) {
                $id = (int)$row['id'];
                $ids_panel[] = $id;
                if (isset($noticias[$id])) continue;
                $ts = strtotime((string)$row['fecha_publicacion']);
                $noticias[$id] = [
                    'id' => $id,
                    't'  => (string)$row['titulo'],                        // título
                    'r'  => plec_biblioteca_resumen($row['contenido'], 150), // resumen en texto plano
                    'c'  => (string)$row['contenido'],                     // contenido tal cual (lo usa abrirModal)
                    'i'  => plec_biblioteca_imagen($row['imagen']),        // imagen
                    'v'  => (string)($row['video_url'] ?? ''),             // video
                    'k'  => (string)($row['cat_nombre'] ?? ''),            // nombre de la categoría
                    'ci' => (int)$row['categoria_id'],                     // id de la categoría
                    'f'  => $ts ? date('Y-m-d', $ts) : '',                 // fecha de publicación
                    'h'  => (int)$row['horas_publicada'],                  // horas desde que se publicó
                ];
            }
            mysqli_free_result($res);

            // Igual que la portada clásica: las secciones sin noticias no se muestran
            if (empty($ids_panel)) continue;

            $enlace = null;
            if (is_array($sec['cats']) && count($sec['cats']) === 1) {
                $enlace = 'index.php?cat=' . (int)$sec['cats'][0];
            }

            $paneles[] = [
                'clave'  => $sec['clave'],
                'nombre' => $sec['nombre'],
                'sub'    => $sec['sub'],
                'icono'  => $sec['icono'],
                'color'  => $sec['color'],
                'cats'   => $sec['cats'],
                'total'  => $total,
                'enlace' => $enlace,
                'ids'    => $ids_panel,
            ];
        }

        return ['paneles' => $paneles, 'noticias' => $noticias];
    } catch (Throwable $e) {
        error_log('Noticias PLEC - biblioteca: ' . $e->getMessage());
        return $vacio;
    }
}

/**
 * Imprime el contenedor de la biblioteca con sus datos en JSON.
 * El JS lo arma; sin JS este bloque queda oculto por CSS.
 */
function plec_biblioteca_render($datos) {
    if (empty($datos['paneles'])) return;
    // Objeto {id: noticia}, nunca lista, aunque los ids quedaran consecutivos
    $datos['noticias'] = (object)$datos['noticias'];
    $json = json_encode(
        $datos,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP |
        JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE
    );
    if ($json === false) return;
    echo '<section class="biblio" id="biblioteca" aria-label="Biblioteca de noticias">';
    echo '<div class="biblio-carga" aria-hidden="true"><span></span><span></span><span></span><span></span><span></span></div>';
    echo '<script type="application/json" id="biblioDatos">' . $json . '</script>';
    echo '</section>';
}
