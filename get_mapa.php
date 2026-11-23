<?php
/* ════════════════════════════════════════════════════════════════════
   NOTICIAS PLEC — DATOS DEL MAPA VIVO (JSON para assets/js/plec-mapa.js)
     get_mapa.php                      país: conteo por departamento + "Pulso del día"
     get_mapa.php?depto=73             departamento: conteo por municipio + últimas 5
     get_mapa.php?depto=73&muni=73001  municipio: últimas 5
   Los lugares se piden por su código DANE (que también es su id en la base).
   Solo cuentan las noticias con ubicación y es_especial = 0 (las de la revista
   no salen en la portada). Si algo falla responde {"ok": false} y el mapa
   muestra un mensaje amable.
   ════════════════════════════════════════════════════════════════════ */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-cache');
date_default_timezone_set('America/Bogota');

function plec_mapa_responder(array $datos, int $codigo = 200) {
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

$depto = isset($_GET['depto']) ? (string)$_GET['depto'] : '';
$muni  = isset($_GET['muni'])  ? (string)$_GET['muni']  : '';
if (($depto !== '' && !preg_match('/^\d{1,2}$/', $depto))
    || ($muni !== '' && ($depto === '' || !preg_match('/^\d{4,5}$/', $muni)))) {
    plec_mapa_responder(['ok' => false, 'error' => 'parametro'], 400);
}

include __DIR__ . '/conexion.php';   // si la base no responde, conexion.php corta con 503
include_once __DIR__ . '/mapa_datos.php';

try {
    if ($depto === '')     $datos = plec_mapa_pais($conn);
    elseif ($muni === '')  $datos = plec_mapa_departamento($conn, (int)$depto);
    else                   $datos = plec_mapa_municipio($conn, (int)$depto, (int)$muni);
    if ($datos === null) plec_mapa_responder(['ok' => false, 'error' => 'no_existe'], 404);
    plec_mapa_responder(['ok' => true, 'ahora' => time()] + $datos);
} catch (Throwable $e) {
    // Por ejemplo: la migración del mapa (sql/mapa_migracion.sql) aún no se aplicó
    error_log('Noticias PLEC - get_mapa: ' . $e->getMessage());
    plec_mapa_responder(['ok' => false, 'error' => 'no_disponible'], 503);
}
