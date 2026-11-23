<?php
/* ════════════════════════════════════════════════════════════════════
   NOTICIAS PLEC — DATOS DEL MAPA VIVO DE COLOMBIA
   · Consultas del mapa de la portada (las usa get_mapa.php).
   · Selects de departamento y municipio del panel admin, y validación de
     lo que llega del formulario (procesar_noticia.php, editar_noticia.php).
   · Solo consultas preparadas.
   · Si la migración (sql/mapa_migracion.sql) aún no se aplicó,
     plec_mapa_disponible() devuelve false y el admin sigue como antes.
   ════════════════════════════════════════════════════════════════════ */

include_once __DIR__ . '/biblioteca_datos.php';   // plec_biblioteca_imagen() y colores de las secciones

const PLEC_MAPA_DIAS    = 30;   // ventana del color del mapa
const PLEC_MAPA_PULSO   = 8;    // noticias en "Pulso del día"
const PLEC_MAPA_LISTA   = 5;    // noticias en el panel lateral

// ── Utilidades ───────────────────────────────────────────────────────

/** Ejecuta una consulta preparada y devuelve todas sus filas */
function plec_mapa_filas($conn, string $sql, string $tipos = '', array $valores = []): array {
    $stmt = mysqli_prepare($conn, $sql);
    if ($tipos !== '') mysqli_stmt_bind_param($stmt, $tipos, ...$valores);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $filas = $res ? mysqli_fetch_all($res, MYSQLI_ASSOC) : [];
    mysqli_stmt_close($stmt);
    return $filas;
}

/** ¿Ya existen las tablas y columnas del mapa? (se revisa una vez por petición) */
function plec_mapa_disponible($conn): bool {
    static $cache = null;
    if ($cache !== null) return $cache;
    $cache = false;
    if (!$conn) return false;
    try {
        $f = plec_mapa_filas($conn, "SELECT
              (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'noticias'
                  AND COLUMN_NAME IN ('departamento_id', 'municipio_id')) AS columnas,
              (SELECT COUNT(*) FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('departamentos', 'municipios')) AS tablas");
        $cache = $f && (int)$f[0]['columnas'] === 2 && (int)$f[0]['tablas'] === 2;
    } catch (Throwable $e) {
        error_log('Noticias PLEC - mapa (disponible): ' . $e->getMessage());
    }
    return $cache;
}

/** Color de cada categoría: el mismo de su sección en la biblioteca */
function plec_mapa_color($categoria_id): string {
    static $colores = null;
    if ($colores === null) {
        $colores = [];
        foreach (plec_biblioteca_secciones() as $s) {
            if (is_array($s['cats'])) foreach ($s['cats'] as $id) $colores[(int)$id] = $s['color'];
        }
    }
    return $colores[(int)$categoria_id] ?? '#3b8cff';
}

// Columnas comunes de una noticia del mapa (con su lugar y categoría)
const PLEC_MAPA_SELECT = "SELECT n.id, n.titulo, n.contenido, n.imagen, n.video_url, n.categoria_id,
        c.nombre AS cat_nombre, UNIX_TIMESTAMP(n.fecha_publicacion) AS ts,
        d.codigo_dane AS dep_dane, d.nombre AS dep_nombre,
        m.codigo_dane AS mun_dane, m.nombre AS mun_nombre
    FROM noticias n
    JOIN departamentos d ON d.id = n.departamento_id
    LEFT JOIN municipios m ON m.id = n.municipio_id
    LEFT JOIN categorias c ON c.id = n.categoria_id";

/** Una noticia en el formato corto del mapa: lo que necesitan el panel y abrirModal() */
function plec_mapa_noticia(array $f): array {
    return [
        'id'  => (int)$f['id'],
        't'   => (string)$f['titulo'],
        'i'   => plec_biblioteca_imagen($f['imagen']),
        'c'   => (string)$f['contenido'],
        'v'   => (string)($f['video_url'] ?? ''),
        'k'   => (string)($f['cat_nombre'] ?? ''),
        'col' => plec_mapa_color($f['categoria_id']),
        'ts'  => (int)$f['ts'],
        'd'   => (string)($f['dep_dane'] ?? ''),
        'dn'  => (string)($f['dep_nombre'] ?? ''),
        'm'   => (string)($f['mun_dane'] ?? ''),
        'mn'  => (string)($f['mun_nombre'] ?? ''),
    ];
}

/** Conteos (total, últimos 30 días, últimas 24 h) y titular de la última, agrupados por lugar */
function plec_mapa_conteos(array $conteos, array $ultimas): array {
    $out = [];
    foreach ($conteos as $f) {
        $out[(string)$f['dane']] = ['n' => (int)$f['n30'], 't' => (int)$f['total'], 'h' => (int)$f['n24'], 'u' => null];
    }
    foreach ($ultimas as $f) {
        $clave = (string)$f['dane'];
        if (isset($out[$clave])) $out[$clave]['u'] = ['id' => (int)$f['id'], 't' => (string)$f['titulo'], 'ts' => (int)$f['ts']];
    }
    return $out;
}

/** Departamentos que ya tienen su archivo de municipios (assets/mapa/municipios/{slug}.json) */
function plec_mapa_con_municipios(): array {
    $slugs = [];
    foreach (glob(__DIR__ . '/assets/mapa/municipios/*.json') ?: [] as $ruta) {
        $s = basename($ruta, '.json');
        if (preg_match('/^[a-z0-9-]{2,60}$/', $s)) $slugs[] = $s;
    }
    sort($slugs);
    return $slugs;
}

// ── Consultas del mapa (get_mapa.php) ────────────────────────────────

/** Nivel 1: conteo por departamento, "Pulso del día" y resumen del país */
function plec_mapa_pais($conn): array {
    $dias = PLEC_MAPA_DIAS;
    $conteos = plec_mapa_filas($conn, "SELECT d.codigo_dane AS dane, COUNT(*) AS total,
            SUM(n.fecha_publicacion >= NOW() - INTERVAL ? DAY) AS n30,
            SUM(n.fecha_publicacion >= NOW() - INTERVAL 1 DAY) AS n24
        FROM noticias n JOIN departamentos d ON d.id = n.departamento_id
        WHERE n.es_especial = 0
        GROUP BY d.codigo_dane", 'i', [$dias]);
    $ultimas = plec_mapa_filas($conn, "SELECT t.dane, t.id, t.titulo, t.ts FROM (
            SELECT d.codigo_dane AS dane, n.id, n.titulo, UNIX_TIMESTAMP(n.fecha_publicacion) AS ts,
                   ROW_NUMBER() OVER (PARTITION BY n.departamento_id ORDER BY n.fecha_publicacion DESC, n.id DESC) AS rn
            FROM noticias n JOIN departamentos d ON d.id = n.departamento_id
            WHERE n.es_especial = 0
        ) t WHERE t.rn = 1");
    $limite = PLEC_MAPA_PULSO;
    $pulso = plec_mapa_filas($conn, PLEC_MAPA_SELECT . "
        WHERE n.es_especial = 0
        ORDER BY n.fecha_publicacion DESC, n.id DESC
        LIMIT ?", 'i', [$limite]);

    $deptos = plec_mapa_conteos($conteos, $ultimas);
    $resumen = ['n30' => 0, 'lugares' => 0, 'h24' => 0];
    foreach ($deptos as $d) {
        $resumen['n30'] += $d['n'];
        $resumen['h24'] += $d['h'];
        if ($d['n'] > 0) $resumen['lugares']++;
    }
    return [
        'dias'    => $dias,
        'deptos'  => (object)$deptos,     // siempre objeto {"73": …}, también cuando está vacío
        'resumen' => $resumen,
        'geo'     => plec_mapa_con_municipios(),
        'pulso'   => array_map('plec_mapa_noticia', $pulso),
    ];
}

/** Nivel 2: un departamento, con el conteo de cada municipio y sus últimas noticias */
function plec_mapa_departamento($conn, int $dep_id): ?array {
    $dep = plec_mapa_filas($conn, "SELECT id, codigo_dane, nombre, slug FROM departamentos WHERE id = ?", 'i', [$dep_id]);
    if (!$dep) return null;
    $dep = $dep[0];
    $dias = PLEC_MAPA_DIAS;

    $resumen = plec_mapa_filas($conn, "SELECT COUNT(*) AS total,
            COALESCE(SUM(fecha_publicacion >= NOW() - INTERVAL ? DAY), 0) AS n30,
            COALESCE(SUM(fecha_publicacion >= NOW() - INTERVAL 1 DAY), 0) AS n24
        FROM noticias WHERE departamento_id = ? AND es_especial = 0", 'ii', [$dias, $dep_id])[0];
    $conteos = plec_mapa_filas($conn, "SELECT m.codigo_dane AS dane, COUNT(*) AS total,
            SUM(n.fecha_publicacion >= NOW() - INTERVAL ? DAY) AS n30,
            SUM(n.fecha_publicacion >= NOW() - INTERVAL 1 DAY) AS n24
        FROM noticias n JOIN municipios m ON m.id = n.municipio_id
        WHERE n.departamento_id = ? AND n.es_especial = 0
        GROUP BY m.codigo_dane", 'ii', [$dias, $dep_id]);
    $ultimas = plec_mapa_filas($conn, "SELECT t.dane, t.id, t.titulo, t.ts FROM (
            SELECT m.codigo_dane AS dane, n.id, n.titulo, UNIX_TIMESTAMP(n.fecha_publicacion) AS ts,
                   ROW_NUMBER() OVER (PARTITION BY n.municipio_id ORDER BY n.fecha_publicacion DESC, n.id DESC) AS rn
            FROM noticias n JOIN municipios m ON m.id = n.municipio_id
            WHERE n.departamento_id = ? AND n.es_especial = 0
        ) t WHERE t.rn = 1", 'i', [$dep_id]);
    $limite = PLEC_MAPA_LISTA;
    $noticias = plec_mapa_filas($conn, PLEC_MAPA_SELECT . "
        WHERE n.departamento_id = ? AND n.es_especial = 0
        ORDER BY n.fecha_publicacion DESC, n.id DESC
        LIMIT ?", 'ii', [$dep_id, $limite]);

    return [
        'dias'     => $dias,
        'depto'    => [
            'c' => (string)$dep['codigo_dane'], 's' => (string)$dep['slug'], 'n' => (string)$dep['nombre'],
            'n30' => (int)$resumen['n30'], 't' => (int)$resumen['total'], 'h' => (int)$resumen['n24'],
        ],
        'munis'    => (object)plec_mapa_conteos($conteos, $ultimas),
        'noticias' => array_map('plec_mapa_noticia', $noticias),
    ];
}

/** Un municipio: su resumen y sus últimas noticias */
function plec_mapa_municipio($conn, int $dep_id, int $mun_id): ?array {
    $mun = plec_mapa_filas($conn, "SELECT codigo_dane, nombre FROM municipios WHERE id = ? AND departamento_id = ?", 'ii', [$mun_id, $dep_id]);
    if (!$mun) return null;
    $dias = PLEC_MAPA_DIAS;
    $resumen = plec_mapa_filas($conn, "SELECT COUNT(*) AS total,
            COALESCE(SUM(fecha_publicacion >= NOW() - INTERVAL ? DAY), 0) AS n30,
            COALESCE(SUM(fecha_publicacion >= NOW() - INTERVAL 1 DAY), 0) AS n24
        FROM noticias WHERE municipio_id = ? AND es_especial = 0", 'ii', [$dias, $mun_id])[0];
    $limite = PLEC_MAPA_LISTA;
    $noticias = plec_mapa_filas($conn, PLEC_MAPA_SELECT . "
        WHERE n.municipio_id = ? AND n.es_especial = 0
        ORDER BY n.fecha_publicacion DESC, n.id DESC
        LIMIT ?", 'ii', [$mun_id, $limite]);
    return [
        'dias'     => $dias,
        'muni'     => [
            'c' => (string)$mun[0]['codigo_dane'], 'n' => (string)$mun[0]['nombre'],
            'n30' => (int)$resumen['n30'], 't' => (int)$resumen['total'], 'h' => (int)$resumen['n24'],
        ],
        'noticias' => array_map('plec_mapa_noticia', $noticias),
    ];
}

// ── Panel admin: selects y validación ────────────────────────────────

/** Departamentos (Tolima primero) y municipios de cada uno, para los selects */
function plec_mapa_lugares($conn): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    $cache = ['deptos' => [], 'munis' => []];
    if (!plec_mapa_disponible($conn)) return $cache;
    try {
        foreach (plec_mapa_filas($conn, "SELECT id, nombre FROM departamentos ORDER BY (id = 73) DESC, nombre") as $d) {
            $cache['deptos'][] = [(int)$d['id'], (string)$d['nombre']];
        }
        foreach (plec_mapa_filas($conn, "SELECT id, departamento_id, nombre FROM municipios ORDER BY departamento_id, nombre") as $m) {
            $cache['munis'][(int)$m['departamento_id']][] = [(int)$m['id'], (string)$m['nombre']];
        }
    } catch (Throwable $e) {
        error_log('Noticias PLEC - mapa (lugares): ' . $e->getMessage());
        $cache = ['deptos' => [], 'munis' => []];
    }
    return $cache;
}

/**
 * Campos "Ubicación en el mapa" para un formulario del admin (Bootstrap 5).
 * Devuelve '' si la migración no está aplicada. $prefijo hace únicos los id
 * cuando hay varios formularios en la misma página.
 */
function plec_mapa_campos_admin($conn, $dep_actual, $mun_actual, string $prefijo): string {
    static $script_impreso = false;
    $lugares = plec_mapa_lugares($conn);
    if (!$lugares['deptos']) return '';
    $dep_actual = (int)$dep_actual;
    $mun_actual = (int)$mun_actual;
    $p = htmlspecialchars($prefijo, ENT_QUOTES);

    $ops_dep = '<option value="">Sin ubicación (nacional o internacional)</option>';
    foreach ($lugares['deptos'] as $i => [$id, $nombre]) {
        if ($i === 1 && (int)$lugares['deptos'][0][0] === 73) $ops_dep .= '<optgroup label="Otros departamentos">';
        $ops_dep .= '<option value="' . $id . '"' . ($id === $dep_actual ? ' selected' : '') . '>' . htmlspecialchars($nombre) . '</option>';
    }
    if (count($lugares['deptos']) > 1 && (int)$lugares['deptos'][0][0] === 73) $ops_dep .= '</optgroup>';

    // Los municipios del departamento elegido van ya en el HTML (al editar no hace falta JS)
    $munis = $lugares['munis'][$dep_actual] ?? [];
    $ops_mun = '<option value="">' . ($dep_actual ? 'Todo el departamento' : 'Primero elige un departamento') . '</option>';
    foreach ($munis as [$id, $nombre]) {
        $ops_mun .= '<option value="' . $id . '"' . ($id === $mun_actual ? ' selected' : '') . '>' . htmlspecialchars($nombre) . '</option>';
    }
    $sin_munis = !$dep_actual || !$munis;

    $html  = '<div class="mb-3 plec-ubicacion" data-plec-ubicacion>';
    $html .= '<label class="form-label" for="' . $p . '-dep"><i class="fas fa-map-marker-alt me-1" style="color:#ff4d4d;"></i> Ubicación en el mapa (opcional)</label>';
    $html .= '<div class="row g-2">';
    $html .= '<div class="col-sm-6"><select name="departamento_id" id="' . $p . '-dep" class="form-select" data-plec-dep aria-label="Departamento">' . $ops_dep . '</select></div>';
    $html .= '<div class="col-sm-6"><select name="municipio_id" id="' . $p . '-mun" class="form-select" data-plec-mun aria-label="Municipio"' . ($sin_munis ? ' disabled' : '') . '>' . $ops_mun . '</select></div>';
    $html .= '</div>';
    $html .= '<small class="text-muted d-block mt-1" data-plec-ayuda>' . htmlspecialchars(plec_mapa_ayuda($dep_actual, (bool)$munis)) . '</small>';
    $html .= '</div>';

    if (!$script_impreso) {
        $script_impreso = true;
        $json = json_encode((object)$lugares['munis'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $html .= '<script type="application/json" id="plecMapaMunicipios">' . $json . '</script>';
        $html .= '<script>' . plec_mapa_script_admin() . '</script>';
    }
    return $html;
}

function plec_mapa_ayuda(int $dep, bool $hay_munis): string {
    if (!$dep) return 'Sin ubicación, la noticia no aparece en el mapa de la portada.';
    return $hay_munis ? 'Elige el municipio si la noticia es de un lugar concreto.'
                      : 'Este departamento aún no tiene municipios cargados: la noticia queda en todo el departamento.';
}

/** Select de municipio dependiente del de departamento (uno o varios formularios por página) */
function plec_mapa_script_admin(): string {
    $ayudas = json_encode([
        'sin'   => plec_mapa_ayuda(0, false),
        'con'   => plec_mapa_ayuda(1, true),
        'vacio' => plec_mapa_ayuda(1, false),
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
    return <<<JS
(function () {
    var AYUDA = $ayudas, datos = {};
    try { datos = JSON.parse(document.getElementById('plecMapaMunicipios').textContent) || {}; } catch (e) { datos = {}; }
    function llenar(caja, dep) {
        var sel = caja.querySelector('[data-plec-mun]'), ayuda = caja.querySelector('[data-plec-ayuda]');
        var lista = datos[dep] || [];
        sel.innerHTML = '';
        var op = document.createElement('option');
        op.value = '';
        op.textContent = dep ? 'Todo el departamento' : 'Primero elige un departamento';
        sel.appendChild(op);
        lista.forEach(function (m) {
            var o = document.createElement('option');
            o.value = m[0]; o.textContent = m[1];
            sel.appendChild(o);
        });
        sel.disabled = !dep || !lista.length;
        if (ayuda) ayuda.textContent = !dep ? AYUDA.sin : (lista.length ? AYUDA.con : AYUDA.vacio);
    }
    document.addEventListener('change', function (e) {
        var dep = e.target;
        if (!dep.matches || !dep.matches('[data-plec-dep]')) return;
        var caja = dep.closest('[data-plec-ubicacion]');
        if (caja) llenar(caja, dep.value);
    });
})();
JS;
}

/**
 * Lee y valida la ubicación que llegó del formulario. Devuelve [departamento_id|null, municipio_id|null].
 * El municipio manda: si llega uno válido, el departamento es el suyo.
 */
function plec_mapa_leer_post($conn, array $post): array {
    $dep = isset($post['departamento_id']) ? (int)$post['departamento_id'] : 0;
    $mun = isset($post['municipio_id']) ? (int)$post['municipio_id'] : 0;
    if ($mun > 0) {
        $f = plec_mapa_filas($conn, "SELECT departamento_id FROM municipios WHERE id = ?", 'i', [$mun]);
        if ($f) return [(int)$f[0]['departamento_id'], $mun];
    }
    if ($dep > 0) {
        $f = plec_mapa_filas($conn, "SELECT id FROM departamentos WHERE id = ?", 'i', [$dep]);
        if ($f) return [$dep, null];
    }
    return [null, null];
}
