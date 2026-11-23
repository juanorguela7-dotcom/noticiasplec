<?php
require __DIR__ . '/admin_comun.php';          // sesión, CSRF, utilidades del panel
include("conexion.php");
include_once __DIR__ . '/mapa_datos.php';      // ubicación de la noticia en el mapa vivo
require __DIR__ . '/admin_layout.php';

// ── CARGAR NOTICIA ──
$id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
$n  = null;
if ($id > 0) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM noticias WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $n = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
}
if (!$n) {
    if (plec_admin_es_ajax()) plec_admin_json(['ok' => false, 'mensaje' => 'Esa publicación ya no existe.'], 404);
    plec_admin_flash('danger', 'Esa publicación ya no existe.');
    header("Location: admin.php#publicaciones");
    exit();
}

$error = null;
$campo_error = null;
$valores = $n;   // lo que muestra el formulario (si hay error, lo que se escribió)

// ── GUARDAR CAMBIOS ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fallar = function (string $mensaje, ?string $campo = null, array $extra = []) use (&$error, &$campo_error) {
        if (plec_admin_es_ajax()) {
            plec_admin_json(array_merge(['ok' => false, 'mensaje' => $mensaje, 'campo' => $campo], $extra), 422);
        }
        $error = $mensaje;
        $campo_error = $campo;
    };

    // Antes, un error mostraba otra vez lo guardado y se perdía lo editado
    $valores = array_merge($n, [
        'titulo'          => (string)($_POST['titulo'] ?? $n['titulo']),
        'contenido'       => (string)($_POST['contenido'] ?? $n['contenido']),
        'categoria_id'    => (int)($_POST['categoria'] ?? $n['categoria_id']),
        'es_especial'     => !empty($_POST['es_especial']) ? 1 : 0,
        'video_url'       => (string)($_POST['video_url'] ?? ''),
        'departamento_id' => (int)($_POST['departamento_id'] ?? 0) ?: null,
        'municipio_id'    => (int)($_POST['municipio_id'] ?? 0) ?: null,
    ]);

    if (plec_admin_post_demasiado_grande()) {
        $valores = $n;
        $fallar('La imagen pesa más de ' . plec_admin_mb(plec_admin_limite_subida()) . '. Usa una más liviana.', 'imagen');
    } elseif (!plec_admin_csrf_valido($_POST['csrf'] ?? null)) {
        $fallar('La página estaba desactualizada. Vuelve a intentarlo.', null, ['motivo' => 'csrf', 'csrf' => plec_admin_csrf()]);
    } else {
        [$datos, $err] = plec_admin_validar_campos($_POST, true);
        $cat           = (int)($_POST['categoria'] ?? 0);
        $es_especial   = !empty($_POST['es_especial']) ? 1 : 0;
        $quitar_imagen = !empty($_POST['quitar_imagen']);

        if ($err) {
            $fallar($err['mensaje'], $err['campo']);
        } elseif (!plec_admin_categoria_existe($conn, $cat)) {
            $fallar('Elige una categoría.', 'categoria');
        } else {
            $sube_imagen = isset($_FILES['imagen']) && ($_FILES['imagen']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
            $queda_sin_foto = ($quitar_imagen || empty($n['imagen'])) && !$sube_imagen;
            if ($cat === PLEC_CAT_OPINION && $queda_sin_foto) {
                $fallar('Una columna de opinión necesita la foto del columnista.', 'imagen');
            } else {
                [$ruta, $err_img] = plec_admin_subir_imagen('imagen');
                if ($err_img) {
                    $fallar($err_img, 'imagen');
                } else {
                    // Consulta preparada; la imagen solo cambia si subieron otra o pidieron quitarla
                    $sets    = "titulo = ?, contenido = ?, categoria_id = ?, es_especial = ?, video_url = ?";
                    $tipos   = "ssiis";
                    $params  = [$datos['titulo'], $datos['contenido'], $cat, $es_especial, $datos['video_url']];
                    $cambia_imagen = ($ruta !== null) || $quitar_imagen;
                    if ($cambia_imagen) {
                        $sets .= ", imagen = ?";
                        $tipos .= "s";
                        $params[] = $ruta;   // null si solo la quitaron
                    }
                    try {
                        if (plec_mapa_disponible($conn)) {
                            [$departamento_id, $municipio_id] = plec_mapa_leer_post($conn, $_POST);
                            $sets .= ", departamento_id = ?, municipio_id = ?";
                            $tipos .= "ii";
                            $params[] = $departamento_id;
                            $params[] = $municipio_id;
                        }
                        $tipos .= "i";
                        $params[] = $id;
                        $stmt = mysqli_prepare($conn, "UPDATE noticias SET $sets WHERE id = ?");
                        mysqli_stmt_bind_param($stmt, $tipos, ...$params);
                        mysqli_stmt_execute($stmt);
                        mysqli_stmt_close($stmt);
                    } catch (Throwable $e) {
                        error_log('Noticias PLEC - error al editar noticia: ' . $e->getMessage());
                        if ($ruta) @unlink(__DIR__ . '/' . $ruta);
                        $fallar('No se pudieron guardar los cambios. Inténtalo de nuevo en un momento.');
                        $cambia_imagen = false;
                        $ruta = false;
                    }
                    if ($ruta !== false && $error === null) {
                        if ($cambia_imagen) plec_admin_borrar_imagen_huerfana($conn, $n['imagen']);
                        plec_admin_flash('success', 'Cambios guardados.', ['enlace' => 'noticia.php?id=' . $id]);
                        $destino = 'admin.php?nueva=' . $id . '#publicaciones';
                        if (plec_admin_es_ajax()) {
                            plec_admin_json(['ok' => true, 'mensaje' => 'Cambios guardados.', 'redirigir' => $destino]);
                        }
                        header("Location: " . $destino);
                        exit();
                    }
                }
            }
        }
    }
}

// ── DATOS PARA MOSTRAR ──
$res_c = mysqli_query($conn, "SELECT id, nombre FROM categorias ORDER BY nombre ASC");
$categorias = $res_c ? mysqli_fetch_all($res_c, MYSQLI_ASSOC) : [];

$cat_actual = (int)$valores['categoria_id'];
if ((int)$valores['es_especial'] === 1)              { $tipo = 'general';   $tipo_txt = 'Historia especial (Revista)'; $tipo_icono = 'fa-star'; }
elseif ($cat_actual === PLEC_CAT_EDITORIAL)          { $tipo = 'editorial'; $tipo_txt = 'Editorial';                   $tipo_icono = 'fa-feather-pointed'; }
elseif ($cat_actual === PLEC_CAT_OPINION)            { $tipo = 'opinion';   $tipo_txt = 'Columna de opinión';          $tipo_icono = 'fa-user-tie'; }
elseif ($cat_actual === PLEC_CAT_INCLUSION)          { $tipo = 'inclusion'; $tipo_txt = 'Inclusión';                   $tipo_icono = 'fa-universal-access'; }
else                                                 { $tipo = 'general';   $tipo_txt = 'Noticia general';             $tipo_icono = 'fa-newspaper'; }

$img_actual = (string)($n['imagen'] ?? '');
if ($img_actual !== '' && strpos($img_actual, 'img/') !== 0 && strpos($img_actual, 'http') !== 0) $img_actual = 'img/' . $img_actual;
$es_opinion = ($cat_actual === PLEC_CAT_OPINION);
$limite_txt = plec_admin_mb(plec_admin_limite_subida());

$fecha_pub = '';
if (!empty($n['fecha_publicacion']) && ($t = strtotime($n['fecha_publicacion']))) {
    $meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    $fecha_pub = date('j', $t) . ' ' . $meses[(int)date('n', $t) - 1] . ' ' . date('Y', $t) . ', ' . date('H:i', $t);
}

plec_admin_cabecera('Editar: ' . mb_substr($n['titulo'], 0, 40), 'editar', ['stream_activo' => plec_admin_stream($conn)['activo']]);
?>

<header class="pa-encabezado">
    <div>
        <a class="pa-volver" href="admin.php#publicaciones"><i class="fas fa-arrow-left"></i> Volver a publicaciones</a>
        <h1 class="pa-h1">Editar publicación</h1>
        <div class="pa-meta-edicion pa-acento-<?php echo $tipo; ?>">
            <span class="pa-tipo-badge"><i class="fas <?php echo $tipo_icono; ?>"></i> <?php echo plec_e($tipo_txt); ?></span>
            <span>#<?php echo (int)$n['id']; ?></span>
            <?php if ($fecha_pub): ?><span><i class="far fa-calendar"></i> <?php echo plec_e($fecha_pub); ?></span><?php endif; ?>
            <span><i class="fas fa-eye"></i> <?php echo number_format((int)($n['vistas'] ?? 0), 0, ',', '.'); ?> lecturas</span>
        </div>
    </div>
    <div class="pa-encabezado-acciones">
        <a class="btn pa-btn pa-btn-sec" href="noticia.php?id=<?php echo (int)$n['id']; ?>" target="_blank" rel="noopener"><i class="fas fa-arrow-up-right-from-square"></i> Ver en el sitio</a>
    </div>
</header>

<section class="pa-card">
    <form class="pa-pane is-activo pa-acento-<?php echo $tipo; ?>" method="POST" action="editar_noticia.php?id=<?php echo (int)$n['id']; ?>"
          enctype="multipart/form-data" data-pa-form data-pa-tipo="<?php echo $tipo; ?>">
        <input type="hidden" name="id" value="<?php echo (int)$n['id']; ?>">
        <input type="hidden" name="csrf" value="<?php echo plec_e(plec_admin_csrf()); ?>">

        <div class="pa-pane-grid">
            <div class="pa-pane-principal">
                <div class="pa-campo">
                    <label class="pa-label" for="ed-titulo">Título <span class="pa-req" aria-hidden="true">*</span></label>
                    <input type="text" name="titulo" id="ed-titulo" class="form-control pa-input-titulo<?php echo $campo_error === 'titulo' ? ' is-invalid' : ''; ?>"
                           maxlength="255" required autocomplete="off" value="<?php echo plec_e($valores['titulo']); ?>" data-pa-contar>
                    <div class="pa-ayuda-fila"><span></span><span class="pa-contador" data-pa-contador></span></div>
                </div>
                <div class="pa-campo pa-campo-texto">
                    <label class="pa-label" for="ed-contenido">Contenido <span class="pa-req" aria-hidden="true">*</span></label>
                    <textarea name="contenido" id="ed-contenido" class="form-control pa-textarea<?php echo $campo_error === 'contenido' ? ' is-invalid' : ''; ?>"
                              rows="16" required data-pa-palabras><?php echo plec_e($valores['contenido']); ?></textarea>
                    <div class="pa-ayuda-fila">
                        <span>Los saltos de línea se respetan tal cual en el sitio.</span>
                        <span class="pa-contador" data-pa-palabras-cuenta></span>
                    </div>
                </div>
            </div>

            <div class="pa-pane-lateral">
                <div class="pa-campo">
                    <label class="pa-label" for="ed-categoria">Categoría <span class="pa-req" aria-hidden="true">*</span></label>
                    <select name="categoria" id="ed-categoria" class="form-select<?php echo $campo_error === 'categoria' ? ' is-invalid' : ''; ?>" required>
                        <?php foreach ($categorias as $c): ?>
                            <option value="<?php echo (int)$c['id']; ?>"<?php echo (int)$c['id'] === $cat_actual ? ' selected' : ''; ?>><?php echo plec_e($c['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php echo plec_mapa_campos_admin($conn, $valores['departamento_id'] ?? null, $valores['municipio_id'] ?? null, 'editar'); ?>

                <div class="pa-campo">
                    <span class="pa-label" id="ed-imagen-label"><?php echo $es_opinion ? 'Foto del columnista' : 'Imagen destacada'; ?></span>
                    <div class="pa-drop<?php echo $es_opinion ? ' is-redonda' : ''; ?><?php echo $campo_error === 'imagen' ? ' is-invalid' : ''; ?>" data-pa-drop
                         <?php if ($img_actual !== ''): ?>data-actual="<?php echo plec_e($img_actual); ?>"<?php endif; ?>>
                        <input type="file" name="imagen" id="ed-imagen" accept="image/jpeg,image/png,image/webp,image/gif"
                               aria-labelledby="ed-imagen-label" aria-describedby="ed-imagen-ayuda">
                        <div class="pa-drop-vacio">
                            <i class="fas fa-cloud-arrow-up"></i>
                            <b><?php echo $img_actual !== '' ? 'Elige otra imagen' : 'Arrastra la imagen o haz clic'; ?></b>
                            <small id="ed-imagen-ayuda">JPG, PNG, WEBP o GIF · hasta <?php echo plec_e($limite_txt); ?></small>
                        </div>
                        <div class="pa-drop-lleno" hidden>
                            <img alt="Imagen de la publicación">
                            <div class="pa-drop-info"><b data-pa-drop-nombre></b><small data-pa-drop-peso></small></div>
                        </div>
                        <button type="button" class="pa-drop-quitar" data-pa-drop-quitar aria-label="Descartar la imagen elegida" hidden><i class="fas fa-xmark"></i></button>
                    </div>
                    <?php if ($img_actual !== ''): ?>
                        <label class="pa-check mt-2">
                            <input type="checkbox" class="form-check-input" name="quitar_imagen" value="1" data-pa-quitar-imagen>
                            <span>Quitar la imagen actual</span>
                        </label>
                    <?php endif; ?>
                </div>

                <div class="pa-campo">
                    <label class="pa-label" for="ed-video">Video <span class="pa-opcional">opcional</span></label>
                    <input type="text" inputmode="url" name="video_url" id="ed-video" class="form-control<?php echo $campo_error === 'video_url' ? ' is-invalid' : ''; ?>"
                           placeholder="Pega el enlace del video" autocomplete="off" value="<?php echo plec_e($valores['video_url'] ?? ''); ?>"
                           data-pa-video aria-describedby="ed-video-estado">
                    <div class="pa-video-estado" id="ed-video-estado" data-pa-video-estado>Déjalo vacío para quitar el video.</div>
                </div>

                <label class="pa-switch form-switch" for="ed-especial">
                    <input class="form-check-input" type="checkbox" role="switch" name="es_especial" value="1" id="ed-especial"<?php echo (int)$valores['es_especial'] === 1 ? ' checked' : ''; ?>>
                    <span>
                        <b><i class="fas fa-star"></i> Historia especial</b>
                        <small>Sale en la Revista y <strong>no</strong> en el noticiero de la portada.</small>
                    </span>
                </label>
            </div>
        </div>

        <div class="pa-form-error" role="alert"<?php echo $error ? '' : ' hidden'; ?>><?php echo $error ? '<i class="fas fa-triangle-exclamation"></i> ' . plec_e($error) : ''; ?></div>
        <div class="pa-progreso" hidden>
            <div class="pa-progreso-pista"><div class="pa-progreso-barra"></div></div>
            <span class="pa-progreso-texto">Subiendo…</span>
        </div>

        <div class="pa-form-pie">
            <a class="btn pa-btn pa-btn-sec" href="admin.php#publicaciones">Cancelar</a>
            <div class="pa-form-botones">
                <button type="button" class="btn pa-btn pa-btn-sec" data-pa-preview><i class="fas fa-eye"></i> Vista previa</button>
                <button type="submit" class="btn pa-btn pa-btn-acento" data-pa-enviar><i class="fas fa-floppy-disk"></i> <span>Guardar cambios</span></button>
            </div>
        </div>
    </form>
</section>

<?php plec_admin_pie(); ?>
