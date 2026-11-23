<?php
/* ════════════════════════════════════════════════════════════════════
   NOTICIAS PLEC — DATOS DEL BÚHO INTELIGENTE (Etapa 5)
   · Índice ampliado para "Pregúntale al búho": las noticias que no caben
     en los estantes de la biblioteca, en versión ligera (título, resumen,
     palabras clave, categoría y fecha; sin el contenido completo).
     La búsqueda se hace después en el navegador (plec-buho-busca.js):
     no hay consultas al servidor mientras se busca.
   · Contexto de la página (portada, categoría o búsqueda) para que el
     búho explique dónde está el lector.
   · Solo LEE la base de datos. Si algo falla, devuelve listas vacías.
   ════════════════════════════════════════════════════════════════════ */

// Cuántas noticias, además de las de los estantes, entran en el índice del búho.
// Cada una pesa ~0,5 KB en la página (≈ 150 KB con 300, unos 45 KB comprimidos).
const PLEC_BUHO_INDICE_MAX = 300;

// Palabras frecuentes del texto (sin las vacías ni las que ya están en el título o el resumen)
function plec_buho_palabras($html, $ya, $max = 30) {
    static $vacias = null;
    if ($vacias === null) {
        $vacias = array_flip(explode(' ',
            'para pero como este esta estos estas esto sobre entre desde hasta donde cuando también tambien porque según segun ' .
            'tiene tienen tenía había habia habían sido será sera serán ellos ellas otro otra otros otras todo todos toda todas ' .
            'cada más mismo misma mismos durante parte través traves solo sólo sino aunque mientras quien quienes cual cuales ' .
            'fueron están estan está eran haber hacer hecho luego entonces dicho dicha ello aquí allí ahora después despues antes ' .
            'nuevo nueva nuevos nuevas gran grandes contra hacia puede pueden podría podrán debe deben dijo afirmó aseguró indicó ' .
            'explicó señaló agregó manifestó además ademas mayor menor sobre año años días semana mes meses tras ante cerca ' .
            'algunos algunas varios varias muchos muchas mucho mucha donde quienes cuyo cuya siendo estar está estaba estaban ' .
            'tener tuvo hizo hace hacen había sean sería serían tanto tanta forma manera caso casos personas persona lugar ' .
            'momento través menos vez veces bien todo primer primera segundo segunda último última embargo asimismo'));
    }
    $txt = mb_strtolower(html_entity_decode(strip_tags(str_replace('<', ' <', (string)$html)), ENT_QUOTES | ENT_HTML5, 'UTF-8'), 'UTF-8');
    $ya = array_flip(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower((string)$ya, 'UTF-8'), -1, PREG_SPLIT_NO_EMPTY) ?: []);
    $cuenta = [];
    foreach (preg_split('/[^\p{L}\p{N}]+/u', $txt, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $w) {
        if (mb_strlen($w) < 4 || isset($vacias[$w]) || isset($ya[$w]) || preg_match('/^\d+$/', $w)) continue;
        $cuenta[$w] = ($cuenta[$w] ?? 0) + 1;
    }
    arsort($cuenta);      // las más repetidas primero (a igual cuenta, en el orden del texto)
    return implode(' ', array_slice(array_keys($cuenta), 0, $max));
}

/**
 * Noticias para el índice del búho que NO vienen ya en la biblioteca.
 * $excluir: ids que ya trae la biblioteca (con su contenido completo).
 */
function plec_buho_indice_extra($conn, $excluir, $max = PLEC_BUHO_INDICE_MAX) {
    if (!$conn || $max <= 0) return [];
    try {
        $ids = array_filter(array_map('intval', (array)$excluir));
        $no = $ids ? ' AND n.id NOT IN (' . implode(',', $ids) . ')' : '';
        $res = mysqli_query($conn, "SELECT n.id, n.titulo, n.contenido, n.imagen, n.video_url, n.categoria_id,
                n.fecha_publicacion, c.nombre AS cat_nombre,
                TIMESTAMPDIFF(HOUR, n.fecha_publicacion, NOW()) AS horas_publicada
            FROM noticias n
            LEFT JOIN categorias c ON n.categoria_id = c.id
            WHERE n.es_especial = 0$no
            ORDER BY n.fecha_publicacion DESC
            LIMIT " . (int)$max);
        if (!$res) return [];
        $out = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $resumen = plec_biblioteca_resumen($row['contenido'], 140);
            $ts = strtotime((string)$row['fecha_publicacion']);
            $out[] = [
                'id' => (int)$row['id'],
                't'  => (string)$row['titulo'],
                'r'  => $resumen,
                'p'  => plec_buho_palabras($row['contenido'], $row['titulo'] . ' ' . $resumen, 30),
                'i'  => plec_biblioteca_imagen($row['imagen']),
                'k'  => (string)($row['cat_nombre'] ?? ''),
                'ci' => (int)$row['categoria_id'],
                'f'  => $ts ? date('Y-m-d', $ts) : '',
                'h'  => (int)$row['horas_publicada'],
            ];
        }
        mysqli_free_result($res);
        return $out;
    } catch (Throwable $e) {
        error_log('Noticias PLEC - índice del búho: ' . $e->getMessage());
        return [];
    }
}

// Qué explica el búho en la página de una categoría (index.php?cat=N)
function plec_buho_guia_categoria($cat_id) {
    $cat_id = (int)$cat_id;
    $guias = plec_biblioteca_guias();
    foreach (plec_biblioteca_secciones() as $sec) {
        if (is_array($sec['cats']) && in_array($cat_id, $sec['cats'], true)) return $guias[$sec['clave']] ?? '';
    }
    $otras = [
        1  => 'Tecnología: inventos, internet, aplicaciones y cómo cambian nuestra vida.',
        2  => 'Deportes: fútbol, ciclismo y los deportistas que nos representan.',
        5  => 'Entretenimiento: series, música, cine y planes para desconectarse.',
        8  => 'Economía: precios, empleo y el dinero de todos los días.',
        10 => 'Cultura: arte, música, tradiciones y la vida cultural de la región.',
    ];
    return $otras[$cat_id] ?? '';
}

// Imprime datos en JSON para el JS (mismo cuidado que la biblioteca)
function plec_buho_json($id, $datos) {
    $json = json_encode(
        $datos,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP |
        JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE
    );
    if ($json === false) return;
    echo '<script type="application/json" id="' . htmlspecialchars($id, ENT_QUOTES) . '">' . $json . '</script>' . "\n";
}
