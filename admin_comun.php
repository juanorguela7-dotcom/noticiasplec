<?php
/* ════════════════════════════════════════════════════════════════════
   NOTICIAS PLEC — BASE COMÚN DEL PANEL ADMINISTRATIVO
   Lo incluyen todas las páginas del panel ANTES de imprimir nada:
   · Headers de seguridad y sesión segura (cookie httponly + SameSite).
   · Cierre por inactividad (30 min) IGUAL en todas las páginas: antes
     procesar_noticia.php y editar_noticia.php no lo revisaban.
   · Token CSRF y respuestas JSON para los envíos hechos con JavaScript.
   · Validación y guardado de imágenes y de los campos de una noticia.
   ════════════════════════════════════════════════════════════════════ */

if (!defined('PLEC_ADMIN_TIMEOUT')) define('PLEC_ADMIN_TIMEOUT', 1800); // segundos (30 min)

include_once __DIR__ . '/funciones_video.php';   // validarURLVideo()

const PLEC_CAT_OPINION   = 13;
const PLEC_CAT_EDITORIAL = 15;
const PLEC_CAT_INCLUSION = 16;

header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: no-referrer");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Pragma: no-cache");

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', 1);
    ini_set('session.gc_maxlifetime', PLEC_ADMIN_TIMEOUT);
    // En hosting con HTTPS, descomentar:
    // ini_set('session.cookie_secure', 1);
    session_start();
}

/** ¿La petición la hizo el JavaScript del panel? (entonces se responde JSON) */
function plec_admin_es_ajax(): bool {
    return ($_SERVER['HTTP_X_PLEC_AJAX'] ?? '') === '1';
}

function plec_admin_json(array $datos, int $codigo = 200): void {
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit();
}

// ── Sesión: vencida por inactividad o sin iniciar ──
$plec_sesion_vencida = isset($_SESSION['admin_last_act'])
    && (time() - (int)$_SESSION['admin_last_act']) > PLEC_ADMIN_TIMEOUT;
if ($plec_sesion_vencida) {
    session_unset();
    session_destroy();
}
if ($plec_sesion_vencida || !isset($_SESSION['admin_id'])) {
    if (plec_admin_es_ajax()) {
        plec_admin_json([
            'ok'      => false,
            'sesion'  => false,
            'mensaje' => 'Tu sesión se cerró. Inicia sesión en otra pestaña y vuelve a intentarlo: lo que escribiste sigue aquí.',
        ], 401);
    }
    header('Location: login.php' . ($plec_sesion_vencida ? '?timeout=1' : ''));
    exit();
}
$_SESSION['admin_last_act'] = time();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function plec_admin_csrf(): string {
    return $_SESSION['csrf_token'];
}

function plec_admin_csrf_valido($token): bool {
    return is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
}

/** Guarda un aviso para mostrarlo en la siguiente página del panel */
function plec_admin_flash(string $tipo, string $texto, array $extra = []): void {
    $_SESSION['mensaje'] = array_merge(['tipo' => $tipo, 'texto' => $texto], $extra);
}

function plec_admin_tomar_flash(): ?array {
    $m = $_SESSION['mensaje'] ?? null;
    unset($_SESSION['mensaje']);
    return is_array($m) ? $m : null;
}

// ── Límites de subida ────────────────────────────────────────────────

function plec_admin_bytes_ini(string $valor): int {
    $valor = trim($valor);
    if ($valor === '') return 0;
    $n = (float)$valor;
    switch (strtolower(substr($valor, -1))) {
        case 'g': $n *= 1024;
        case 'm': $n *= 1024;
        case 'k': $n *= 1024;
    }
    return (int)$n;
}

/** Lo máximo que PHP acepta en una subida (el menor de los dos límites) */
function plec_admin_limite_subida(): int {
    $limites = array_filter([
        plec_admin_bytes_ini((string)ini_get('upload_max_filesize')),
        plec_admin_bytes_ini((string)ini_get('post_max_size')),
    ]);
    return $limites ? min($limites) : 8 * 1024 * 1024;
}

function plec_admin_mb(int $bytes): string {
    $mb = $bytes / 1048576;
    return ($mb >= 10 ? round($mb) : round($mb, 1)) . ' MB';
}

/** PHP descarta TODO el formulario si supera post_max_size: hay que detectarlo aparte */
function plec_admin_post_demasiado_grande(): bool {
    return empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0;
}

// ── Imágenes ─────────────────────────────────────────────────────────

/**
 * Valida (por su contenido real) y guarda la imagen del campo $campo.
 * Devuelve [ruta, null] si se guardó, [null, null] si no enviaron imagen
 * y [null, 'mensaje'] si la enviaron pero no sirve. Antes, una imagen
 * rechazada se ignoraba en silencio y la noticia salía sin foto.
 */
function plec_admin_subir_imagen(string $campo = 'imagen'): array {
    $f = $_FILES[$campo] ?? null;
    if (!$f || !isset($f['error']) || is_array($f['error']) || $f['error'] === UPLOAD_ERR_NO_FILE) {
        return [null, null];
    }
    switch ($f['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return [null, 'La imagen pesa más de ' . plec_admin_mb(plec_admin_limite_subida()) . '. Usa una más liviana.'];
        case UPLOAD_ERR_PARTIAL:
            return [null, 'La imagen no terminó de subir. Revisa tu conexión e inténtalo de nuevo.'];
        default:
            return [null, 'El servidor no pudo recibir la imagen (código ' . (int)$f['error'] . ').'];
    }

    $mimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    $mime  = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!isset($mimes[$mime]) || @getimagesize($f['tmp_name']) === false) {
        return [null, 'La imagen debe ser JPG, PNG, WEBP o GIF. Las fotos HEIC del iPhone hay que pasarlas a JPG.'];
    }

    $ruta = 'img/' . uniqid('noticia_', true) . '.' . $mimes[$mime];
    if (!@move_uploaded_file($f['tmp_name'], __DIR__ . '/' . $ruta)) {
        error_log('Noticias PLEC - no se pudo mover la imagen a ' . $ruta);
        return [null, 'No se pudo guardar la imagen en el servidor (revisa los permisos de la carpeta img/).'];
    }
    return [$ruta, null];
}

/**
 * Borra del disco una imagen subida desde el panel si ya ninguna
 * publicación la usa. Solo toca archivos img/noticia_* (los que crea el panel).
 */
function plec_admin_borrar_imagen_huerfana($conn, ?string $ruta): void {
    if (!$ruta || !preg_match('#^img/noticia_[A-Za-z0-9._-]+\.(jpe?g|png|gif|webp)$#i', $ruta)) return;
    try {
        $stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM noticias WHERE imagen = ?");
        mysqli_stmt_bind_param($stmt, "s", $ruta);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $usos);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);
        if ((int)$usos === 0 && is_file(__DIR__ . '/' . $ruta)) {
            @unlink(__DIR__ . '/' . $ruta);
        }
    } catch (Throwable $e) {
        error_log('Noticias PLEC - borrar imagen: ' . $e->getMessage());
    }
}

// ── Campos de una noticia ────────────────────────────────────────────

/** "youtu.be/xxx" → "https://youtu.be/xxx"; vacío → null */
function plec_admin_normalizar_video($url): ?string {
    $url = trim((string)$url);
    if ($url === '') return null;
    if (!preg_match('#^https?://#i', $url)) $url = 'https://' . ltrim($url, '/');
    return $url;
}

/**
 * Revisa título, contenido y video. Devuelve [datos, null] o
 * [null, ['mensaje' => ..., 'campo' => ...]] con el campo a corregir.
 * Los límites son los de la tabla: en MySQL estricto (Railway) pasarse
 * hacía fallar el INSERT con un "Error al guardar" sin explicación.
 */
function plec_admin_validar_campos(array $post, bool $con_video): array {
    $titulo    = trim((string)($post['titulo'] ?? ''));
    $contenido = trim((string)($post['contenido'] ?? ''));

    if ($titulo === '') {
        return [null, ['mensaje' => 'Escribe el título.', 'campo' => 'titulo']];
    }
    if (mb_strlen($titulo) > 255) {
        return [null, ['mensaje' => 'El título tiene ' . mb_strlen($titulo) . ' caracteres; el máximo es 255.', 'campo' => 'titulo']];
    }
    if ($contenido === '') {
        return [null, ['mensaje' => 'Escribe el contenido.', 'campo' => 'contenido']];
    }
    if (strlen($contenido) > 65535) {
        return [null, ['mensaje' => 'El texto es demasiado largo para guardarlo (máximo unos 60.000 caracteres). Divídelo en dos publicaciones.', 'campo' => 'contenido']];
    }

    $video = null;
    if ($con_video) {
        $video = plec_admin_normalizar_video($post['video_url'] ?? '');
        if ($video !== null) {
            if (strlen($video) > 500) {
                return [null, ['mensaje' => 'El enlace del video es demasiado largo.', 'campo' => 'video_url']];
            }
            if (!validarURLVideo($video)) {
                return [null, ['mensaje' => 'No reconocemos ese enlace de video, así que no se vería en el sitio. Usa un enlace de YouTube, Vimeo, Facebook, TikTok, Instagram o Twitch.', 'campo' => 'video_url']];
            }
        }
    }
    return [['titulo' => $titulo, 'contenido' => $contenido, 'video_url' => $video], null];
}

function plec_admin_categoria_existe($conn, int $id): bool {
    if ($id <= 0) return false;
    $stmt = mysqli_prepare($conn, "SELECT 1 FROM categorias WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    $existe = mysqli_stmt_num_rows($stmt) > 0;
    mysqli_stmt_close($stmt);
    return $existe;
}

/** Estado de la transmisión en vivo (tabla `configuracion`) */
function plec_admin_stream($conn): array {
    $stream = ['activo' => false, 'url' => '', 'embed' => '', 'titulo' => ''];
    try {
        $res = mysqli_query($conn, "SELECT clave, valor FROM configuracion WHERE clave IN ('stream_url','stream_embed','stream_titulo','stream_activo')");
        while ($res && $fila = mysqli_fetch_assoc($res)) {
            $k = str_replace('stream_', '', $fila['clave']);
            $stream[$k] = ($k === 'activo') ? ($fila['valor'] === '1') : (string)$fila['valor'];
        }
    } catch (Throwable $e) {
        // La tabla aún no existe: se crea la primera vez que se guarda una transmisión
    }
    return $stream;
}
