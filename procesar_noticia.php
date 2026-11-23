<?php
/* ════════════════════════════════════════════════════════════════════
   NOTICIAS PLEC — PUBLICAR Y ELIMINAR (formularios del panel)
   · Publicar: valida todo ANTES de guardar y explica qué corregir.
     Si lo envía el JavaScript del panel responde JSON (el formulario
     no se pierde); si no, vuelve a admin.php con un aviso.
   · Eliminar: POST con token CSRF; borra también la imagen si ya
     ninguna otra publicación la usa.
   ════════════════════════════════════════════════════════════════════ */
require __DIR__ . '/admin_comun.php';
include("conexion.php");
include_once __DIR__ . '/mapa_datos.php';   // ubicación de la noticia en el mapa vivo

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: admin.php");
    exit();
}

const PLEC_ORIGENES = ['general', 'editorial', 'opinion', 'inclusion'];
$origen = in_array($_POST['origen'] ?? '', PLEC_ORIGENES, true) ? $_POST['origen'] : 'general';

/** Responde y termina. En error, $extra puede traer 'campo' (a corregir) */
function plec_responder(bool $ok, string $texto, array $extra = [], string $volver = 'admin.php'): void {
    global $origen;
    if (plec_admin_es_ajax()) {
        if ($ok) {
            // El aviso se muestra en admin.php después de que el JS recarga
            plec_admin_flash('success', $texto, array_intersect_key($extra, ['enlace' => 1]));
        }
        plec_admin_json(array_merge(['ok' => $ok, 'mensaje' => $texto], $extra), $ok ? 200 : 422);
    }
    if ($ok) {
        plec_admin_flash('success', $texto, array_merge(['borrador' => $origen], array_intersect_key($extra, ['enlace' => 1])));
    } else {
        plec_admin_flash('danger', $texto);
    }
    header("Location: " . $volver);
    exit();
}

// Si la subida supera post_max_size PHP vacía $_POST y $_FILES: se avisa antes que nada
if (plec_admin_post_demasiado_grande()) {
    plec_responder(false, 'Lo que intentaste subir pesa más de ' . plec_admin_mb(plec_admin_limite_subida()) . '. Usa una imagen más liviana.', ['campo' => 'imagen'], 'admin.php#publicar-' . $origen);
}

if (!plec_admin_csrf_valido($_POST['csrf'] ?? null)) {
    // El JS reintenta solo con el token nuevo (la página quedó de una sesión anterior)
    plec_responder(false, 'La página estaba desactualizada. Vuelve a intentarlo.', ['motivo' => 'csrf', 'csrf' => plec_admin_csrf()]);
}

// ── ELIMINAR ──
if (($_POST['accion'] ?? '') === 'borrar') {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = mysqli_prepare($conn, "SELECT titulo, imagen FROM noticias WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$fila) {
        plec_responder(false, 'Esa publicación ya no existe.', [], 'admin.php#publicaciones');
    }
    try {
        $stmt = mysqli_prepare($conn, "DELETE FROM noticias WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    } catch (Throwable $e) {
        error_log('Noticias PLEC - error al eliminar noticia: ' . $e->getMessage());
        plec_responder(false, 'No se pudo eliminar la publicación. Inténtalo de nuevo.', [], 'admin.php#publicaciones');
    }
    plec_admin_borrar_imagen_huerfana($conn, $fila['imagen']);
    $titulo_corto = mb_strlen($fila['titulo']) > 60 ? mb_substr($fila['titulo'], 0, 60) . '…' : $fila['titulo'];
    plec_admin_flash('success', 'Se eliminó «' . $titulo_corto . '».');
    header("Location: admin.php#publicaciones");
    exit();
}

// ── PUBLICAR ──
$volver_error = 'admin.php#publicar-' . $origen;

// La categoría de las secciones especiales la decide el servidor, no el formulario
$categorias_fijas = ['editorial' => PLEC_CAT_EDITORIAL, 'opinion' => PLEC_CAT_OPINION, 'inclusion' => PLEC_CAT_INCLUSION];
$categoria_id = $categorias_fijas[$origen] ?? (int)($_POST['categoria'] ?? 0);
$con_video    = ($origen === 'general');
$con_lugar    = ($origen === 'general' || $origen === 'inclusion');
$es_especial  = ($origen === 'general' && !empty($_POST['es_especial'])) ? 1 : 0;

[$datos, $error] = plec_admin_validar_campos($_POST, $con_video);
if ($error) {
    plec_responder(false, $error['mensaje'], ['campo' => $error['campo']], $volver_error);
}
if (!plec_admin_categoria_existe($conn, $categoria_id)) {
    plec_responder(false, 'Elige una categoría.', ['campo' => 'categoria'], $volver_error);
}

$sube_imagen = isset($_FILES['imagen']) && ($_FILES['imagen']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
if ($origen === 'opinion' && !$sube_imagen) {
    plec_responder(false, 'Falta la foto del columnista: se usa como su avatar en el sitio.', ['campo' => 'imagen'], $volver_error);
}

// Doble envío (doble clic, recarga o reintento de red): si la misma
// publicación entró hace menos de 2 minutos, no se duplica.
$stmt = mysqli_prepare($conn, "SELECT id FROM noticias WHERE titulo = ? AND categoria_id = ? AND fecha_publicacion >= (NOW() - INTERVAL 2 MINUTE) ORDER BY id DESC LIMIT 1");
mysqli_stmt_bind_param($stmt, "si", $datos['titulo'], $categoria_id);
mysqli_stmt_execute($stmt);
$repetida = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if ($repetida) {
    plec_responder(true, 'Esta publicación ya se había guardado hace un momento.', [
        'id'     => (int)$repetida['id'],
        'enlace' => 'noticia.php?id=' . (int)$repetida['id'],
    ], 'admin.php?nueva=' . (int)$repetida['id'] . '#publicaciones');
}

// La imagen se guarda al final, cuando todo lo demás ya está validado
[$ruta_imagen, $error_imagen] = plec_admin_subir_imagen('imagen');
if ($error_imagen) {
    plec_responder(false, $error_imagen, ['campo' => 'imagen'], $volver_error);
}

// ── UBICACIÓN EN EL MAPA VIVO (opcional). Sin la migración del mapa, se guarda como siempre ──
$con_mapa = plec_mapa_disponible($conn);
$departamento_id = null;
$municipio_id = null;
if ($con_mapa && $con_lugar) {
    try {
        [$departamento_id, $municipio_id] = plec_mapa_leer_post($conn, $_POST);
    } catch (Throwable $e) {
        error_log('Noticias PLEC - ubicación de la noticia: ' . $e->getMessage());
    }
}

try {
    if ($con_mapa) {
        $stmt = mysqli_prepare($conn,
            "INSERT INTO noticias (titulo, contenido, categoria_id, es_especial, imagen, video_url, departamento_id, municipio_id, fecha_publicacion)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        mysqli_stmt_bind_param($stmt, "ssiissii", $datos['titulo'], $datos['contenido'], $categoria_id, $es_especial, $ruta_imagen, $datos['video_url'], $departamento_id, $municipio_id);
    } else {
        $stmt = mysqli_prepare($conn,
            "INSERT INTO noticias (titulo, contenido, categoria_id, es_especial, imagen, video_url, fecha_publicacion)
             VALUES (?, ?, ?, ?, ?, ?, NOW())");
        mysqli_stmt_bind_param($stmt, "ssiiss", $datos['titulo'], $datos['contenido'], $categoria_id, $es_especial, $ruta_imagen, $datos['video_url']);
    }
    mysqli_stmt_execute($stmt);
    $nuevo_id = (int)mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
} catch (Throwable $e) {
    error_log('Noticias PLEC - error al guardar noticia: ' . $e->getMessage());
    if ($ruta_imagen) @unlink(__DIR__ . '/' . $ruta_imagen);   // no dejar la imagen huérfana
    plec_responder(false, 'No se pudo guardar en la base de datos. Inténtalo de nuevo en un momento.', [], $volver_error);
}

$textos_ok = [
    'general'   => $es_especial ? 'Historia especial publicada en la Revista.' : 'Noticia publicada.',
    'editorial' => 'Editorial publicada.',
    'opinion'   => 'Columna de opinión publicada.',
    'inclusion' => 'Noticia de Inclusión publicada.',
];
plec_responder(true, $textos_ok[$origen], [
    'id'     => $nuevo_id,
    'enlace' => 'noticia.php?id=' . $nuevo_id,
], 'admin.php?nueva=' . $nuevo_id . '#publicaciones');
