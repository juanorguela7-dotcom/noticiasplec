<?php
/* ════════════════════════════════════════════════════════════════════
   NOTICIAS PLEC — GENERADOR DEL MAPA VIVO (solo línea de comandos)

   Convierte los límites oficiales del DANE en los archivos livianos que
   usa el mapa de la portada:
     · assets/mapa/colombia.svg             33 departamentos (32 + Bogotá D.C.)
     · assets/mapa/municipios/{slug}.json   municipios de UN departamento
     · sql/mapa_municipios_{slug}.sql       sus filas para la tabla `municipios`

   La simplificación respeta los bordes compartidos: cada frontera entre dos
   vecinos se simplifica una sola vez y la usan los dos, así que no aparecen
   huecos ni montes entre departamentos (o municipios).

   FUENTE DE LOS DATOS
   DANE — Marco Geoestadístico Nacional (MGN), publicado por OCHA en HDX
   ("Colombia - Subnational Administrative Boundaries", licencia CC BY-IGO):
       https://data.humdata.org/dataset/cod-ab-col
   Descarga "COL Administrative Divisions Shapefiles.zip", descomprímelo y deja
   la carpeta en herramientas/mapa/fuente/ (o indica otra con --fuente).
   Se usan col_admbnda_adm1_* (departamentos) y col_admbnda_adm2_* (municipios),
   cada uno con sus archivos .shp, .shx y .dbf.

   USO (desde la carpeta del proyecto)
       php herramientas/mapa/generar_mapa.php pais
       php herramientas/mapa/generar_mapa.php tolima
       php herramientas/mapa/generar_mapa.php antioquia --fuente="C:\Descargas\col-shp"
   El departamento se puede indicar por su slug (antioquia, valle-del-cauca…)
   o por su código DANE (05, 76…). Al agregar uno nuevo:
       1) se crea assets/mapa/municipios/{slug}.json (el mapa lo detecta solo);
       2) ejecuta en la base sql/mapa_municipios_{slug}.sql (local y Railway),
          para que el admin pueda elegir sus municipios.
   ════════════════════════════════════════════════════════════════════ */

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

error_reporting(E_ALL);
ini_set('memory_limit', '2048M');
set_time_limit(0);
date_default_timezone_set('America/Bogota');

// ── Marco y proyección (Mercator). NO cambiarlos: el SVG nacional y los
//    archivos de municipios deben usar exactamente los mismos para encajar ──
const MV_VERSION = 1;
const MV_LON0    = -79.3;   // borde oeste del marco, en grados
const MV_LATTOP  = 12.75;   // borde norte del marco, en grados
const MV_K       = 64.0;    // unidades del SVG por grado

// Recuadro de San Andrés y Providencia: arriba a la izquierda, sobre el mar
const MV_RECUADRO = [8.0, 8.0, 150.0, 204.0];          // x, y, ancho, alto
const MV_ISLAS_K  = 620.0;                             // escala de las islas en el recuadro
const MV_ISLAS    = [
    // centro de la isla (lon, lat), ventana para descartar cayos lejanos y
    // dónde queda su centro dentro del recuadro (x, y)
    'san-andres'  => ['nombre' => 'San Andrés',  'lon' => -81.712, 'lat' => 12.545, 'rlon' => 0.09, 'rlat' => 0.11, 'x' => 52.0,  'y' => 128.0],
    'providencia' => ['nombre' => 'Providencia', 'lon' => -81.372, 'lat' => 13.362, 'rlon' => 0.07, 'rlat' => 0.08, 'x' => 112.0, 'y' => 62.0],
];

// Departamentos: código DANE => [slug, nombre que se muestra]
const MV_DEPTOS = [
    '05' => ['antioquia', 'Antioquia'],
    '08' => ['atlantico', 'Atlántico'],
    '11' => ['bogota', 'Bogotá D.C.'],
    '13' => ['bolivar', 'Bolívar'],
    '15' => ['boyaca', 'Boyacá'],
    '17' => ['caldas', 'Caldas'],
    '18' => ['caqueta', 'Caquetá'],
    '19' => ['cauca', 'Cauca'],
    '20' => ['cesar', 'Cesar'],
    '23' => ['cordoba', 'Córdoba'],
    '25' => ['cundinamarca', 'Cundinamarca'],
    '27' => ['choco', 'Chocó'],
    '41' => ['huila', 'Huila'],
    '44' => ['la-guajira', 'La Guajira'],
    '47' => ['magdalena', 'Magdalena'],
    '50' => ['meta', 'Meta'],
    '52' => ['narino', 'Nariño'],
    '54' => ['norte-de-santander', 'Norte de Santander'],
    '63' => ['quindio', 'Quindío'],
    '66' => ['risaralda', 'Risaralda'],
    '68' => ['santander', 'Santander'],
    '70' => ['sucre', 'Sucre'],
    '73' => ['tolima', 'Tolima'],
    '76' => ['valle-del-cauca', 'Valle del Cauca'],
    '81' => ['arauca', 'Arauca'],
    '85' => ['casanare', 'Casanare'],
    '86' => ['putumayo', 'Putumayo'],
    '88' => ['san-andres', 'San Andrés y Providencia'],
    '91' => ['amazonas', 'Amazonas'],
    '94' => ['guainia', 'Guainía'],
    '95' => ['guaviare', 'Guaviare'],
    '97' => ['vaupes', 'Vaupés'],
    '99' => ['vichada', 'Vichada'],
];

const MV_FUENTE_TXT = 'DANE, Marco Geoestadístico Nacional (vía OCHA/HDX COD-AB, CC BY-IGO)';
const MV_MAX_MUNICIPIOS = 145 * 1024;   // peso máximo de un archivo de municipios (el requisito es < 150 KB)

// ════════════════════════════════════════════════════════════════════
// 1. LECTURA DEL SHAPEFILE (.shp + .shx + .dbf), sin librerías
// ════════════════════════════════════════════════════════════════════

function mv_leer_dbf(string $ruta): array {
    $fp = fopen($ruta, 'rb');
    if (!$fp) throw new RuntimeException("No se pudo abrir $ruta");
    $h  = fread($fp, 32);
    $n  = unpack('V', substr($h, 4, 4))[1];
    $hl = unpack('v', substr($h, 8, 2))[1];
    $rl = unpack('v', substr($h, 10, 2))[1];
    $campos = [];
    while (ftell($fp) < $hl - 1) {
        $d = fread($fp, 32);
        if ($d === '' || $d === false || $d[0] === "\x0D") break;
        $campos[] = [rtrim(substr($d, 0, 11), "\0"), ord($d[16])];
    }
    fseek($fp, $hl);
    $filas = [];
    for ($i = 0; $i < $n; $i++) {
        $r = fread($fp, $rl);
        $pos = 1;
        $fila = [];
        foreach ($campos as [$nombre, $largo]) {
            $fila[$nombre] = trim(substr($r, $pos, $largo));
            $pos += $largo;
        }
        $filas[] = $fila;
    }
    fclose($fp);
    return $filas;
}

function mv_leer_shx(string $ruta): array {
    $datos = file_get_contents($ruta);
    $n = intdiv(strlen($datos) - 100, 8);
    $out = [];
    for ($i = 0; $i < $n; $i++) $out[] = unpack('N', substr($datos, 100 + $i * 8, 4))[1] * 2;
    return $out;
}

/** Anillos de un registro tipo polígono: lista de [lon[], lat[]] */
function mv_leer_poligono($fp, int $offset): array {
    fseek($fp, $offset);
    $h = fread($fp, 8);
    $largo = unpack('N', substr($h, 4, 4))[1] * 2;
    $c = fread($fp, $largo);
    $tipo = unpack('V', substr($c, 0, 4))[1];
    if ($tipo === 0) return [];
    if (!in_array($tipo, [5, 15, 25], true)) throw new RuntimeException("Tipo de figura no soportado: $tipo");
    $nPartes = unpack('V', substr($c, 36, 4))[1];
    $nPuntos = unpack('V', substr($c, 40, 4))[1];
    $partes  = array_values(unpack("V$nPartes", substr($c, 44, 4 * $nPartes)));
    $pts     = array_values(unpack('e' . (2 * $nPuntos), substr($c, 44 + 4 * $nPartes, 16 * $nPuntos)));
    $anillos = [];
    for ($p = 0; $p < $nPartes; $p++) {
        $ini = $partes[$p];
        $fin = ($p + 1 < $nPartes) ? $partes[$p + 1] : $nPuntos;
        $lon = []; $lat = [];
        for ($i = $ini; $i < $fin; $i++) { $lon[] = $pts[2 * $i]; $lat[] = $pts[2 * $i + 1]; }
        $anillos[] = [$lon, $lat];
    }
    return $anillos;
}

/** Busca en la carpeta de la fuente los archivos de un nivel ('adm1' o 'adm2') */
function mv_buscar_capa(string $carpeta, string $nivel): array {
    if (!is_dir($carpeta)) {
        mv_fallar("No existe la carpeta de la fuente: $carpeta\n"
            . "Descarga \"COL Administrative Divisions Shapefiles.zip\" de https://data.humdata.org/dataset/cod-ab-col,\n"
            . "descomprímelo en herramientas/mapa/fuente/ o indica la carpeta con --fuente=\"RUTA\".");
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($carpeta, FilesystemIterator::SKIP_DOTS));
    $it->setMaxDepth(2);
    foreach ($it as $f) {
        $nombre = strtolower($f->getFilename());
        if (substr($nombre, -4) !== '.shp' || strpos($nombre, 'admbnda_' . $nivel) === false) continue;
        $base = substr($f->getPathname(), 0, -4);
        foreach (['.shx', '.dbf'] as $ext) {
            if (!is_file($base . $ext)) mv_fallar("Falta el archivo $base$ext");
        }
        return ['shp' => $base . '.shp', 'shx' => $base . '.shx', 'dbf' => $base . '.dbf'];
    }
    mv_fallar("No encontré col_admbnda_{$nivel}_*.shp dentro de $carpeta");
}

// ════════════════════════════════════════════════════════════════════
// 2. PROYECCIÓN
// ════════════════════════════════════════════════════════════════════

function mv_merc(float $lat): float { return rad2deg(log(tan(M_PI / 4 + deg2rad($lat) / 2))); }

function mv_xy_pais(float $lon, float $lat): array {
    static $y0 = null;
    if ($y0 === null) $y0 = mv_merc(MV_LATTOP);
    return [($lon - MV_LON0) * MV_K, ($y0 - mv_merc($lat)) * MV_K];
}

/** Isla del archipiélago a la que pertenece un anillo (null = cayo lejano, se descarta) */
function mv_isla(array $lon, array $lat): ?string {
    $cx = (min($lon) + max($lon)) / 2;
    $cy = (min($lat) + max($lat)) / 2;
    foreach (MV_ISLAS as $clave => $i) {
        if (abs($cx - $i['lon']) <= $i['rlon'] && abs($cy - $i['lat']) <= $i['rlat']) return $clave;
    }
    return null;
}

function mv_xy_isla(float $lon, float $lat, string $isla): array {
    $i = MV_ISLAS[$isla];
    return [$i['x'] + ($lon - $i['lon']) * MV_ISLAS_K, $i['y'] - (mv_merc($lat) - mv_merc($i['lat'])) * MV_ISLAS_K];
}

/** Proyecta los anillos de una figura: lista de [lon[], lat[], x[], y[]] */
function mv_preparar(array $anillos, string $dane): array {
    $out = [];
    foreach ($anillos as [$lon, $lat]) {
        $xs = []; $ys = [];
        if ($dane === '88') {
            $isla = mv_isla($lon, $lat);
            if ($isla === null) continue;
            foreach ($lon as $i => $lo) { [$xs[], $ys[]] = mv_xy_isla($lo, $lat[$i], $isla); }
        } else {
            if (max($lon) < -80.0) continue;   // isla de Malpelo: a 500 km de la costa, fuera del marco
            foreach ($lon as $i => $lo) { [$xs[], $ys[]] = mv_xy_pais($lo, $lat[$i]); }
        }
        $out[] = [$lon, $lat, $xs, $ys];
    }
    return $out;
}

// ════════════════════════════════════════════════════════════════════
// 3. TOPOLOGÍA: anillos → arcos compartidos (cada frontera una sola vez)
// ════════════════════════════════════════════════════════════════════

function mv_clave(float $lon, float $lat): int {
    // ~0,1 m de precisión; los vértices compartidos del MGN coinciden exactamente
    return ((int) round(($lon + 180.0) * 1e6)) * 1000000000 + (int) round(($lat + 90.0) * 1e6);
}

/**
 * @param array $figuras lista de ['anillos' => [[lon[], lat[], x[], y[]], ...], 'umbral' => float]
 * @return array ['arcos' => [['x','y','usos','umbral','cerrado'], ...], 'anillos' => [fig => [[[arco, dir], ...], ...]]]
 */
function mv_topologia(array $figuras): array {
    $anillos = [];
    foreach ($figuras as $fi => $fig) {
        foreach ($fig['anillos'] as [$lon, $lat, $px, $py]) {
            $k = []; $x = []; $y = []; $ult = null;
            $n = count($lon);
            for ($i = 0; $i < $n; $i++) {
                $c = mv_clave($lon[$i], $lat[$i]);
                if ($c === $ult) continue;
                $k[] = $c; $x[] = $px[$i]; $y[] = $py[$i]; $ult = $c;
            }
            $m = count($k);
            if ($m > 1 && $k[0] === $k[$m - 1]) { array_pop($k); array_pop($x); array_pop($y); $m--; }
            if ($m < 3) continue;
            $anillos[] = [$fi, $k, $x, $y];
        }
    }

    // Uniones: donde se encuentran 3 o más figuras, o donde termina un borde compartido
    $v1 = []; $v2 = []; $union = [];
    foreach ($anillos as [, $k]) {
        $m = count($k);
        for ($i = 0; $i < $m; $i++) {
            $c = $k[$i];
            if (isset($union[$c])) continue;
            $p = $k[$i > 0 ? $i - 1 : $m - 1];
            $q = $k[$i + 1 < $m ? $i + 1 : 0];
            if (!isset($v1[$c])) { $v1[$c] = $p; $v2[$c] = $q; }
            elseif (!(($v1[$c] === $p && $v2[$c] === $q) || ($v1[$c] === $q && $v2[$c] === $p))) $union[$c] = true;
        }
    }
    unset($v1, $v2);

    $arcos = []; $porFirma = []; $salida = [];
    foreach ($anillos as [$fi, $k, $x, $y]) {
        $m = count($k);
        $cortes = [];
        for ($i = 0; $i < $m; $i++) if (isset($union[$k[$i]])) $cortes[] = $i;
        $tramos = [];
        if (!$cortes) {
            // Sin uniones: un solo arco cerrado que empieza en su clave menor
            // (así dos anillos idénticos, como un enclave y su hueco, coinciden)
            $ini = 0;
            for ($i = 1; $i < $m; $i++) if ($k[$i] < $k[$ini]) $ini = $i;
            $idx = [];
            for ($j = 0; $j <= $m; $j++) $idx[] = ($ini + $j) % $m;
            $tramos[] = $idx;
        } else {
            $nc = count($cortes);
            for ($c = 0; $c < $nc; $c++) {
                $a = $cortes[$c];
                $b = $cortes[($c + 1) % $nc];
                $idx = [$a];
                $j = $a;
                do { $j = ($j + 1) % $m; $idx[] = $j; } while ($j !== $b);
                $tramos[] = $idx;
            }
        }
        $lista = [];
        foreach ($tramos as $idx) {
            $ks = [];
            foreach ($idx as $j) $ks[] = $k[$j];
            $n = count($ks);
            $f = $ks[0]; $l = $ks[$n - 1];
            if ($f !== $l) $dir = $f < $l ? 1 : -1;
            else $dir = ($n > 2 && $ks[1] > $ks[$n - 2]) ? -1 : 1;
            $firma = md5(implode(',', $dir === 1 ? $ks : array_reverse($ks)));
            if (isset($porFirma[$firma])) {
                $id = $porFirma[$firma];
                $arcos[$id]['usos']++;
                $arcos[$id]['umbral'] = min($arcos[$id]['umbral'], $figuras[$fi]['umbral']);
            } else {
                $xs = []; $ys = [];
                foreach ($idx as $j) { $xs[] = $x[$j]; $ys[] = $y[$j]; }
                if ($dir === -1) { $xs = array_reverse($xs); $ys = array_reverse($ys); }
                $id = count($arcos);
                $arcos[] = ['x' => $xs, 'y' => $ys, 'usos' => 1, 'umbral' => $figuras[$fi]['umbral'], 'cerrado' => $f === $l];
                $porFirma[$firma] = $id;
            }
            $lista[] = [$id, $dir];
        }
        $salida[$fi][] = $lista;
    }
    return ['arcos' => $arcos, 'anillos' => $salida];
}

// ════════════════════════════════════════════════════════════════════
// 4. SIMPLIFICACIÓN (Visvalingam–Whyatt, extremos del arco fijos)
// ════════════════════════════════════════════════════════════════════

function mv_area3(array $xs, array $ys, int $a, int $b, int $c): float {
    return abs(($xs[$b] - $xs[$a]) * ($ys[$c] - $ys[$a]) - ($xs[$c] - $xs[$a]) * ($ys[$b] - $ys[$a])) * 0.5;
}

function mv_visvalingam(array $xs, array $ys, float $umbral, bool $cerrado): array {
    $n = count($xs);
    if ($n <= 2) return [$xs, $ys];
    $minimo = $cerrado ? 3 : 0;          // un anillo cerrado conserva al menos un triángulo
    $prev = []; $next = [];
    for ($i = 0; $i < $n; $i++) { $prev[$i] = $i - 1; $next[$i] = $i + 1; }
    $ver = array_fill(0, $n, 0);
    $cola = new SplPriorityQueue();
    $cola->setExtractFlags(SplPriorityQueue::EXTR_BOTH);
    for ($i = 1; $i < $n - 1; $i++) $cola->insert([$i, 0], -mv_area3($xs, $ys, $i - 1, $i, $i + 1));
    $quedan = $n - 2;
    $max = 0.0;
    $fuera = [];
    while ($quedan > $minimo && !$cola->isEmpty()) {
        $e = $cola->extract();
        [$i, $v] = $e['data'];
        if ($v !== $ver[$i] || isset($fuera[$i])) continue;
        $a = max(-$e['priority'], $max);
        if ($a >= $umbral) break;
        $max = $a;
        $fuera[$i] = true;
        $quedan--;
        $p = $prev[$i]; $q = $next[$i];
        $next[$p] = $q; $prev[$q] = $p;
        if ($p > 0)      { $ver[$p]++; $cola->insert([$p, $ver[$p]], -mv_area3($xs, $ys, $prev[$p], $p, $q)); }
        if ($q < $n - 1) { $ver[$q]++; $cola->insert([$q, $ver[$q]], -mv_area3($xs, $ys, $p, $q, $next[$q])); }
    }
    $ox = []; $oy = [];
    for ($i = 0; $i < $n; $i = $next[$i]) { $ox[] = $xs[$i]; $oy[] = $ys[$i]; }
    return [$ox, $oy];
}

/** Simplifica todos los arcos y rearma los anillos de cada figura */
function mv_simplificar(array $topo, float $areaMin): array {
    $simples = [];
    $antes = 0; $despues = 0;
    foreach ($topo['arcos'] as $id => $a) {
        $simples[$id] = mv_visvalingam($a['x'], $a['y'], $a['umbral'], $a['cerrado']);
        $antes += count($a['x']);
        $despues += count($simples[$id][0]);
    }
    $figuras = [];
    foreach ($topo['anillos'] as $fi => $lista) {
        $anillos = [];
        foreach ($lista as $arcosAnillo) {
            $xs = []; $ys = []; $compartido = false;
            foreach ($arcosAnillo as $t => [$id, $dir]) {
                [$ax, $ay] = $simples[$id];
                if ($dir === -1) { $ax = array_reverse($ax); $ay = array_reverse($ay); }
                $n = count($ax);
                for ($i = ($t === 0 ? 0 : 1); $i < $n; $i++) { $xs[] = $ax[$i]; $ys[] = $ay[$i]; }
                if ($topo['arcos'][$id]['usos'] > 1) $compartido = true;
            }
            $m = count($xs);
            if ($m > 1 && $xs[0] === $xs[$m - 1] && $ys[0] === $ys[$m - 1]) { array_pop($xs); array_pop($ys); }
            if (count($xs) < 3) continue;
            $anillos[] = ['x' => $xs, 'y' => $ys, 'area' => mv_area_anillo($xs, $ys), 'compartido' => $compartido];
        }
        if (!$anillos) { $figuras[$fi] = []; continue; }
        // Las islas diminutas (que no comparten borde con nadie) se quitan;
        // el anillo más grande de cada figura se queda siempre
        $mayor = 0;
        foreach ($anillos as $i => $an) if (abs($an['area']) > abs($anillos[$mayor]['area'])) $mayor = $i;
        $quedan = [];
        foreach ($anillos as $i => $an) {
            if ($i !== $mayor && !$an['compartido'] && abs($an['area']) < $areaMin) continue;
            $quedan[] = $an;
        }
        $figuras[$fi] = $quedan;
    }
    return ['figuras' => $figuras, 'antes' => $antes, 'despues' => $despues, 'simples' => $simples];
}

function mv_area_anillo(array $xs, array $ys): float {
    $s = 0.0; $n = count($xs);
    for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) $s += $xs[$j] * $ys[$i] - $xs[$i] * $ys[$j];
    return $s / 2;
}

// ════════════════════════════════════════════════════════════════════
// 5. PUNTO DE LA ETIQUETA (polylabel: el punto interior más alejado del borde)
// ════════════════════════════════════════════════════════════════════

function mv_dist_seg2(float $px, float $py, float $ax, float $ay, float $bx, float $by): float {
    $dx = $bx - $ax; $dy = $by - $ay;
    if ($dx != 0 || $dy != 0) {
        $t = (($px - $ax) * $dx + ($py - $ay) * $dy) / ($dx * $dx + $dy * $dy);
        if ($t > 1) { $ax = $bx; $ay = $by; }
        elseif ($t > 0) { $ax += $dx * $t; $ay += $dy * $t; }
    }
    $dx = $px - $ax; $dy = $py - $ay;
    return $dx * $dx + $dy * $dy;
}

function mv_dist_poligono(float $px, float $py, array $anillos): float {
    $dentro = false; $min = INF;
    foreach ($anillos as $an) {
        $xs = $an['x']; $ys = $an['y']; $n = count($xs);
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $ax = $xs[$i]; $ay = $ys[$i]; $bx = $xs[$j]; $by = $ys[$j];
            if ((($ay > $py) !== ($by > $py)) && ($px < ($bx - $ax) * ($py - $ay) / ($by - $ay) + $ax)) $dentro = !$dentro;
            $d = mv_dist_seg2($px, $py, $ax, $ay, $bx, $by);
            if ($d < $min) $min = $d;
        }
    }
    return ($dentro ? 1 : -1) * sqrt($min);
}

function mv_celda(float $x, float $y, float $h, array $anillos): array {
    $d = mv_dist_poligono($x, $y, $anillos);
    return ['x' => $x, 'y' => $y, 'h' => $h, 'd' => $d, 'max' => $d + $h * M_SQRT2];
}

function mv_polylabel(array $anillos, float $precision): array {
    $xs = $anillos[0]['x']; $ys = $anillos[0]['y'];
    $minX = min($xs); $maxX = max($xs); $minY = min($ys); $maxY = max($ys);
    $w = $maxX - $minX; $h = $maxY - $minY;
    $tam = min($w, $h);
    if ($tam <= 0) return [$minX, $minY];
    $mitad = $tam / 2;
    $cola = new SplPriorityQueue();
    for ($x = $minX; $x < $maxX; $x += $tam) {
        for ($y = $minY; $y < $maxY; $y += $tam) {
            $c = mv_celda($x + $mitad, $y + $mitad, $mitad, $anillos);
            $cola->insert($c, $c['max']);
        }
    }
    // Primer candidato: el centroide del anillo exterior
    $a = 0.0; $cx = 0.0; $cy = 0.0; $n = count($xs);
    for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
        $f = $xs[$i] * $ys[$j] - $xs[$j] * $ys[$i];
        $cx += ($xs[$i] + $xs[$j]) * $f; $cy += ($ys[$i] + $ys[$j]) * $f; $a += $f * 3;
    }
    $mejor = $a != 0 ? mv_celda($cx / $a, $cy / $a, 0, $anillos) : mv_celda($minX + $w / 2, $minY + $h / 2, 0, $anillos);
    $centro = mv_celda($minX + $w / 2, $minY + $h / 2, 0, $anillos);
    if ($centro['d'] > $mejor['d']) $mejor = $centro;
    while (!$cola->isEmpty()) {
        $c = $cola->extract();
        if ($c['d'] > $mejor['d']) $mejor = $c;
        if ($c['max'] - $mejor['d'] <= $precision) continue;
        $h2 = $c['h'] / 2;
        foreach ([[-1, -1], [1, -1], [-1, 1], [1, 1]] as [$dx, $dy]) {
            $nc = mv_celda($c['x'] + $dx * $h2, $c['y'] + $dy * $h2, $h2, $anillos);
            $cola->insert($nc, $nc['max']);
        }
    }
    return [$mejor['x'], $mejor['y']];
}

/** Etiqueta de una figura: polylabel sobre su anillo exterior más grande (con sus huecos) */
function mv_etiqueta(array $anillos, float $precision): array {
    $mayor = 0;
    foreach ($anillos as $i => $an) if (abs($an['area']) > abs($anillos[$mayor]['area'])) $mayor = $i;
    $ext = $anillos[$mayor];
    $signo = $ext['area'] >= 0 ? 1 : -1;
    $poli = [$ext];
    foreach ($anillos as $i => $an) {
        if ($i === $mayor || ($an['area'] >= 0 ? 1 : -1) === $signo) continue;
        if (mv_dist_poligono($an['x'][0], $an['y'][0], [$ext]) > 0) $poli[] = $an;   // hueco dentro del exterior
    }
    return mv_polylabel($poli, $precision);
}

function mv_caja(array $anillos): array {
    $minX = INF; $minY = INF; $maxX = -INF; $maxY = -INF;
    foreach ($anillos as $an) {
        $minX = min($minX, min($an['x'])); $maxX = max($maxX, max($an['x']));
        $minY = min($minY, min($an['y'])); $maxY = max($maxY, max($an['y']));
    }
    return [$minX, $minY, $maxX - $minX, $maxY - $minY];
}

// ════════════════════════════════════════════════════════════════════
// 6. SALIDA: trazos SVG compactos (M absoluto + l relativo, sin deriva)
// ════════════════════════════════════════════════════════════════════

function mv_num(float $v, int $dec): string {
    $s = number_format($v, $dec, '.', '');
    if ($dec > 0) $s = rtrim(rtrim($s, '0'), '.');
    if ($s === '-0' || $s === '' || $s === '-') $s = '0';
    if (strncmp($s, '0.', 2) === 0) $s = substr($s, 1);
    elseif (strncmp($s, '-0.', 3) === 0) $s = '-' . substr($s, 2);
    return $s;
}

function mv_par(float $a, float $b, int $dec): string {
    $sb = mv_num($b, $dec);
    return mv_num($a, $dec) . ($sb[0] === '-' ? '' : ' ') . $sb;
}

/** Trazo de una lista de anillos (o de líneas abiertas si $cerrar = false) */
function mv_trazo(array $lineas, int $dec, bool $cerrar = true): string {
    $d = '';
    foreach ($lineas as $ln) {
        $xs = $ln['x']; $ys = $ln['y'];
        $n = count($xs);
        $px = round($xs[0], $dec); $py = round($ys[0], $dec);
        $partes = [];
        for ($i = 1; $i < $n; $i++) {
            $x = round($xs[$i], $dec); $y = round($ys[$i], $dec);
            $dx = round($x - $px, $dec); $dy = round($y - $py, $dec);
            if ($dx == 0 && $dy == 0) continue;
            $partes[] = mv_par($dx, $dy, $dec);
            $px = $x; $py = $y;
        }
        if (count($partes) < ($cerrar ? 2 : 1)) continue;
        $d .= 'M' . mv_par(round($xs[0], $dec), round($ys[0], $dec), $dec) . 'l';
        foreach ($partes as $j => $p) $d .= ($j === 0 || $p[0] === '-') ? $p : ' ' . $p;
        if ($cerrar) $d .= 'z';
    }
    return $d;
}

function mv_slug(string $s): string {
    $s = strtr($s, [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ü' => 'u', 'Ñ' => 'n',
        'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
    ]);
    $s = preg_replace('/[^a-z0-9]+/', '-', strtolower($s));
    return trim($s, '-');
}

function mv_r(float $v, int $dec = 1): float { return round($v, $dec); }

function mv_fallar(string $msg) {
    fwrite(STDERR, "\n[ERROR] $msg\n\n");
    exit(1);
}

function mv_escribir(string $ruta, string $contenido): void {
    if (!is_dir(dirname($ruta))) mkdir(dirname($ruta), 0775, true);
    if (file_put_contents($ruta, $contenido) === false) mv_fallar("No se pudo escribir $ruta");
    printf("  → %s (%s KB)\n", str_replace('\\', '/', substr($ruta, strlen(MV_RAIZ) + 1)), number_format(strlen($contenido) / 1024, 1));
}

// ════════════════════════════════════════════════════════════════════
// 7. TAREAS
// ════════════════════════════════════════════════════════════════════

/** assets/mapa/colombia.svg: los 33 departamentos */
function mv_generar_pais(string $fuente): void {
    $capa = mv_buscar_capa($fuente, 'adm1');
    $filas = mv_leer_dbf($capa['dbf']);
    $offsets = mv_leer_shx($capa['shx']);
    $fp = fopen($capa['shp'], 'rb');

    $umbral = 0.22;     // unidades² (en la vista nacional, 1 unidad ≈ 0,6 px de pantalla)
    $figuras = []; $info = [];
    foreach ($filas as $i => $f) {
        $dane = substr($f['ADM1_PCODE'], 2);
        if (!isset(MV_DEPTOS[$dane])) mv_fallar("Departamento desconocido en la fuente: {$f['ADM1_PCODE']} {$f['ADM1_ES']}");
        $figuras[] = ['anillos' => mv_preparar(mv_leer_poligono($fp, $offsets[$i]), $dane), 'umbral' => $umbral];
        $info[] = $dane;
    }
    fclose($fp);
    if (count(array_unique($info)) !== 33) mv_fallar('La fuente no trae los 33 departamentos');

    echo "  Topología de " . count($figuras) . " departamentos…\n";
    $topo = mv_topologia($figuras);
    $res = mv_simplificar($topo, 3.0);
    printf("  Puntos: %s → %s (%d arcos)\n", number_format($res['antes'], 0, ',', '.'), number_format($res['despues'], 0, ',', '.'), count($topo['arcos']));

    // Marco: todo el territorio continental, con margen
    $maxX = 0; $maxY = 0;
    $deptos = [];
    foreach ($res['figuras'] as $fi => $anillos) {
        $dane = $info[$fi];
        if (!$anillos) mv_fallar("El departamento $dane quedó sin geometría");
        $caja = mv_caja($anillos);
        if ($dane !== '88') { $maxX = max($maxX, $caja[0] + $caja[2]); $maxY = max($maxY, $caja[1] + $caja[3]); }
        else $caja = MV_RECUADRO;
        [$lx, $ly] = mv_etiqueta($anillos, 0.4);
        [$slug, $nombre] = MV_DEPTOS[$dane];
        $deptos[] = ['dane' => $dane, 'slug' => $slug, 'nombre' => $nombre, 'd' => mv_trazo($anillos, 1), 'l' => [$lx, $ly], 'b' => $caja];
    }
    usort($deptos, fn($a, $b) => strcmp($a['slug'], $b['slug']));   // orden alfabético = orden del Tab
    $W = ceil($maxX + 10); $H = ceil($maxY + 10);

    [$rx, $ry, $rw, $rh] = MV_RECUADRO;
    $svg  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $svg .= '<!-- Noticias PLEC · mapa vivo · generado por herramientas/mapa/generar_mapa.php · Fuente: ' . MV_FUENTE_TXT . " -->\n";
    $svg .= '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $W . ' ' . $H . '" data-v="' . MV_VERSION . '">' . "\n";
    $svg .= '<g class="mv-recuadro"><rect class="mv-recuadro-marco" data-dane="88" x="' . $rx . '" y="' . $ry . '" width="' . $rw . '" height="' . $rh . '" rx="10"/>';
    foreach (MV_ISLAS as $isla) {
        $ty = $isla['y'] + ($isla['nombre'] === 'San Andrés' ? 60 : 44);
        $svg .= '<text class="mv-recuadro-txt" x="' . $isla['x'] . '" y="' . $ty . '" text-anchor="middle">' . htmlspecialchars($isla['nombre'], ENT_QUOTES | ENT_XML1) . '</text>';
    }
    $svg .= "</g>\n<g class=\"mv-deptos\">\n";
    foreach ($deptos as $d) {
        $svg .= '<path class="mv-depto" id="mv-d' . $d['dane'] . '" data-dane="' . $d['dane'] . '" data-slug="' . $d['slug'] . '"'
              . ' data-nombre="' . htmlspecialchars($d['nombre'], ENT_QUOTES | ENT_XML1) . '"'
              . ' data-l="' . mv_r($d['l'][0]) . ' ' . mv_r($d['l'][1]) . '"'
              . ' data-b="' . implode(' ', array_map('mv_r', $d['b'])) . '"'
              . ' d="' . $d['d'] . "\"/>\n";
    }
    $svg .= "</g>\n</svg>\n";
    mv_escribir(MV_RAIZ . '/assets/mapa/colombia.svg', $svg);
}

/** assets/mapa/municipios/{slug}.json + sql/mapa_municipios_{slug}.sql */
function mv_generar_departamento(string $fuente, string $dane): void {
    [$slugDep, $nombreDep] = MV_DEPTOS[$dane];
    $capa = mv_buscar_capa($fuente, 'adm2');
    $filas = mv_leer_dbf($capa['dbf']);
    $offsets = mv_leer_shx($capa['shx']);
    $fp = fopen($capa['shp'], 'rb');

    $crudas = []; $info = [];
    foreach ($filas as $i => $f) {
        if ($f['ADM1_PCODE'] !== 'CO' . $dane) continue;
        $anillos = mv_preparar(mv_leer_poligono($fp, $offsets[$i]), $dane);
        if (!$anillos) continue;
        $crudas[] = $anillos;
        $info[] = ['c' => substr($f['ADM2_PCODE'], 2), 'n' => $f['ADM2_ES']];
    }
    fclose($fp);
    if (!$crudas) mv_fallar("No hay municipios del departamento $dane en la fuente");

    // Escala de la vista del departamento: así el detalle se ajusta a su tamaño
    $minX = INF; $minY = INF; $maxX = -INF; $maxY = -INF;
    foreach ($crudas as $anillos) foreach ($anillos as [, , $xs, $ys]) {
        $minX = min($minX, min($xs)); $maxX = max($maxX, max($xs));
        $minY = min($minY, min($ys)); $maxY = max($maxY, max($ys));
    }
    $pxPorUnidad = 620 / max($maxX - $minX, $maxY - $minY);   // el departamento ocupa ~620 px
    $umbral = 0.35 / ($pxPorUnidad * $pxPorUnidad);
    $dec = (0.1 * $pxPorUnidad > 0.6) ? 2 : 1;

    $figuras = [];
    foreach ($crudas as $anillos) $figuras[] = ['anillos' => $anillos, 'umbral' => $umbral];
    echo "  Topología de " . count($figuras) . " municipios de {$nombreDep}…\n";
    $topo = mv_topologia($figuras);

    // Límite de peso: si el archivo pasara de 145 KB, se simplifica un poco más
    for ($intento = 0, $factor = 1.0; ; $intento++, $factor *= 1.6) {
        foreach ($topo['arcos'] as $id => $a) $topo['arcos'][$id]['umbral'] = $umbral * $factor;
        $json = mv_json_departamento($topo, $info, $dane, $pxPorUnidad, $dec, $res);
        if (strlen($json) <= MV_MAX_MUNICIPIOS || $intento >= 6) break;
        printf("  (%s KB: pasa del límite, simplifico más)\n", number_format(strlen($json) / 1024, 1));
    }
    printf("  Puntos: %s → %s (%d arcos, %d decimales)\n", number_format($res['antes'], 0, ',', '.'), number_format($res['despues'], 0, ',', '.'), count($topo['arcos']), $dec);
    mv_escribir(MV_RAIZ . "/assets/mapa/municipios/$slugDep.json", $json);

    // SQL de la tabla `municipios` (id = código DANE, igual en todas las bases)
    $munis = json_decode($json, true)['m'];
    $filasSql = [];
    foreach ($munis as $m) {
        $nombre = str_replace(["\\", "'"], ["\\\\", "''"], $m['n']);
        $filasSql[] = sprintf("    (%d, %d, '%s', '%s', '%s')", (int)$m['c'], (int)$dane, $nombre, $m['s'], $m['c']);
    }
    $sql  = "-- ════════════════════════════════════════════════════════════════════\n";
    $sql .= "-- MAPA VIVO — municipios de $nombreDep (DANE $dane): " . count($munis) . " municipios\n";
    $sql .= "-- Generado por herramientas/mapa/generar_mapa.php el " . date('Y-m-d') . ".\n";
    $sql .= "-- Fuente: " . MV_FUENTE_TXT . ".\n";
    $sql .= "-- Requiere sql/mapa_migracion.sql. Se puede ejecutar varias veces.\n";
    $sql .= "-- ════════════════════════════════════════════════════════════════════\n\n";
    $sql .= "SET NAMES utf8mb4;\n\n";
    $sql .= "INSERT INTO municipios (id, departamento_id, nombre, slug, codigo_dane) VALUES\n";
    $sql .= implode(",\n", $filasSql) . "\n";
    $sql .= "ON DUPLICATE KEY UPDATE departamento_id = VALUES(departamento_id), nombre = VALUES(nombre),\n";
    $sql .= "    slug = VALUES(slug), codigo_dane = VALUES(codigo_dane);\n";
    mv_escribir(MV_RAIZ . "/sql/mapa_municipios_$slugDep.sql", $sql);
}

/** JSON de los municipios de un departamento con el umbral que traen los arcos */
function mv_json_departamento(array $topo, array $info, string $dane, float $pxPorUnidad, int $dec, &$res): string {
    [$slugDep, $nombreDep] = MV_DEPTOS[$dane];
    $res = mv_simplificar($topo, 2.0 / ($pxPorUnidad * $pxPorUnidad));

    // Contorno del departamento: los arcos que no comparte ningún municipio
    $borde = [];
    foreach ($topo['arcos'] as $id => $a) {
        if ($a['usos'] === 1) $borde[] = ['x' => $res['simples'][$id][0], 'y' => $res['simples'][$id][1]];
    }

    $munis = []; $slugs = []; $todas = [];
    foreach ($res['figuras'] as $fi => $anillos) {
        if (!$anillos) continue;
        $slug = mv_slug($info[$fi]['n']);
        if (isset($slugs[$slug])) $slug .= '-' . $info[$fi]['c'];   // nombre repetido en el mismo departamento
        $slugs[$slug] = true;
        [$lx, $ly] = mv_etiqueta($anillos, 1.0 / $pxPorUnidad);
        $munis[] = [
            'c' => $info[$fi]['c'],
            's' => $slug,
            'n' => $info[$fi]['n'],
            'l' => [mv_r($lx, $dec), mv_r($ly, $dec)],
            'b' => array_map(fn($v) => mv_r($v, $dec), mv_caja($anillos)),
            'd' => mv_trazo($anillos, $dec),
        ];
        foreach ($anillos as $an) $todas[] = $an;
    }
    usort($munis, fn($a, $b) => strcmp($a['s'], $b['s']));

    $json = [
        'v'        => MV_VERSION,
        'dane'     => $dane,
        'slug'     => $slugDep,
        'nombre'   => $nombreDep,
        'fuente'   => MV_FUENTE_TXT,
        'b'        => array_map(fn($v) => mv_r($v, $dec), $dane === '88' ? MV_RECUADRO : mv_caja($todas)),
        'contorno' => mv_trazo($borde, $dec, false),
        'm'        => $munis,
    ];
    return json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
}

// ════════════════════════════════════════════════════════════════════
// 8. PROGRAMA
// ════════════════════════════════════════════════════════════════════

define('MV_RAIZ', dirname(__DIR__, 2));

$objetivo = null; $fuente = __DIR__ . DIRECTORY_SEPARATOR . 'fuente';
foreach (array_slice($argv, 1) as $arg) {
    if (strncmp($arg, '--fuente=', 9) === 0) $fuente = trim(substr($arg, 9), "\"'");
    elseif ($arg[0] !== '-') $objetivo = strtolower(trim($arg));
}
if ($objetivo === null) {
    echo "Uso:\n  php herramientas/mapa/generar_mapa.php pais\n  php herramientas/mapa/generar_mapa.php <departamento> [--fuente=RUTA]\n"
       . "Departamentos: " . implode(', ', array_column(MV_DEPTOS, 0)) . "\n";
    exit(1);
}

$t0 = microtime(true);
if ($objetivo === 'pais' || $objetivo === 'colombia') {
    echo "Mapa de Colombia por departamentos\n";
    mv_generar_pais($fuente);
} else {
    $dane = null;
    foreach (MV_DEPTOS as $codigo => [$slug]) {
        if ($objetivo === $slug || $objetivo === (string)$codigo || ltrim($objetivo, '0') === ltrim((string)$codigo, '0')) $dane = (string)$codigo;
    }
    if ($dane === null) mv_fallar("No conozco el departamento \"$objetivo\". Usa uno de: " . implode(', ', array_column(MV_DEPTOS, 0)));
    echo "Municipios de " . MV_DEPTOS[$dane][1] . "\n";
    mv_generar_departamento($fuente, $dane);
}
printf("Listo en %.1f s\n", microtime(true) - $t0);
