<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}
include("conexion.php");

// ── CARGAR NOTICIA ──
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $resultado = mysqli_query($conn, "SELECT * FROM noticias WHERE id = $id");
    $n = mysqli_fetch_assoc($resultado);
    if (!$n) { die("Noticia no encontrada."); }
} else {
    header("Location: admin.php");
    exit();
}

// ── GUARDAR CAMBIOS ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id          = intval($_POST['id']);
    $titulo      = mysqli_real_escape_string($conn, trim($_POST['titulo']));
    $desc        = mysqli_real_escape_string($conn, trim($_POST['descripcion']));
    $cont        = mysqli_real_escape_string($conn, trim($_POST['contenido']));
    $cat         = intval($_POST['categoria_id']);
    $es_especial = isset($_POST['es_especial']) ? 1 : 0;
    $video_url   = mysqli_real_escape_string($conn, trim($_POST['video_url'] ?? ''));

    // Imagen: si suben una nueva, procesarla
    $img_sql = "";
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $ext_ok = ['jpg','jpeg','png','gif','webp'];
        $ext = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $ext_ok)) {
            $archivo = uniqid('noticia_', true) . '.' . $ext;
            $destino = 'img/' . $archivo;
            if (move_uploaded_file($_FILES['imagen']['tmp_name'], $destino)) {
                $ruta = mysqli_real_escape_string($conn, 'img/' . $archivo);
                $img_sql = ", imagen='$ruta'";
            }
        }
    }

    $video_sql = !empty($video_url) ? ", video_url='$video_url'" : ", video_url=NULL";
    $sql = "UPDATE noticias SET titulo='$titulo', descripcion='$desc', contenido='$cont', categoria_id=$cat, es_especial=$es_especial$video_sql $img_sql WHERE id=$id";
    if (mysqli_query($conn, $sql)) {
        $_SESSION['mensaje'] = ['tipo' => 'success', 'texto' => '✅ Noticia actualizada correctamente.'];
        header("Location: admin.php");
        exit();
    } else {
        $error = mysqli_error($conn);
    }
}

// ── OBTENER NOMBRE DE CATEGORÍA ACTUAL ──
$cat_actual = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nombre FROM categorias WHERE id = " . intval($n['categoria_id'])));
$nombre_cat_actual = $cat_actual ? $cat_actual['nombre'] : 'Sin categoría';

// ── TIPO DE NOTICIA ──
if ($n['es_especial'] == 1) {
    $tipo_badge = '<span style="background:#ffcc00; color:#000; padding:4px 12px; border-radius:20px; font-size:12px; font-weight:bold;"><i class="fas fa-star me-1"></i>HISTORIA ESPECIAL (Revista)</span>';
} elseif ($n['categoria_id'] == 15) {
    $tipo_badge = '<span style="background:#4a148c; color:#fff; padding:4px 12px; border-radius:20px; font-size:12px; font-weight:bold;"><i class="fas fa-feather-alt me-1"></i>EDITORIAL</span>';
} elseif ($n['categoria_id'] == 13) {
    $tipo_badge = '<span style="background:#00796b; color:#fff; padding:4px 12px; border-radius:20px; font-size:12px; font-weight:bold;"><i class="fas fa-user-tie me-1"></i>COLUMNA DE OPINIÓN</span>';
} else {
    $tipo_badge = '<span style="background:#1a73e8; color:#fff; padding:4px 12px; border-radius:20px; font-size:12px; font-weight:bold;"><i class="fas fa-newspaper me-1"></i>NOTICIA GENERAL</span>';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Noticia - PLEC</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --azul: #1a73e8; --negro: #121212; }
        body { background: #f4f7f6; font-family: 'Segoe UI', sans-serif; }

        .navbar-plec { background: var(--negro); border-bottom: 4px solid var(--azul); padding: 14px 0; }

        .card-edit { border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); border: 1px solid #e0e0e0; }

        /* Ficha de info actual */
        .ficha-actual { background: #1e1e1e; border-radius: 10px; padding: 18px 20px; margin-bottom: 24px; border-left: 4px solid var(--azul); }
        .ficha-actual .label { color: #888; font-size: 11px; font-weight: bold; text-transform: uppercase; margin-bottom: 3px; }
        .ficha-actual .valor { color: #fff; font-size: 14px; }

        .imagen-preview { width: 100%; max-height: 220px; object-fit: cover; border-radius: 8px; border: 1px solid #ddd; }
        .sin-imagen { background: #eee; border-radius: 8px; height: 120px; display: flex; align-items: center; justify-content: center; color: #aaa; font-size: 13px; }

        .seccion-especial { background: rgba(26,115,232,0.05); border: 1px dashed var(--azul); border-radius: 8px; padding: 14px; }
        .form-label { font-weight: 700; font-size: 0.82rem; color: #444; text-transform: uppercase; }
        .btn-guardar { background: var(--azul); color: #fff; border: none; font-weight: bold; }
        .btn-guardar:hover { background: #155cb0; color: #fff; }
    </style>
</head>
<body>

<nav class="navbar-plec mb-4">
    <div class="container d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <img src="img/logo-plec.jpg" style="height:45px; border-radius:5px;">
            <h5 class="text-white m-0">Editar Noticia</h5>
        </div>
        <a href="admin.php" class="btn btn-outline-light btn-sm">
            <i class="fas fa-arrow-left me-1"></i> Volver al Panel
        </a>
    </div>
</nav>

<div class="container pb-5" style="max-width: 780px;">

    <?php if (isset($error)): ?>
        <div class="alert alert-danger mb-3"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- ── FICHA DE INFORMACIÓN ACTUAL ── -->
    <div class="ficha-actual mb-4">
        <div class="row g-3 align-items-center">
            <div class="col-md-5">
                <div class="label">Tipo de contenido</div>
                <div class="mt-1"><?php echo $tipo_badge; ?></div>
            </div>
            <div class="col-md-4">
                <div class="label">Categoría actual</div>
                <div class="valor fw-bold"><?php echo htmlspecialchars($nombre_cat_actual); ?></div>
            </div>
            <div class="col-md-3">
                <div class="label">ID de noticia</div>
                <div class="valor">#<?php echo $n['id']; ?></div>
            </div>
        </div>
        <?php if (!empty($n['fecha_publicacion'])): ?>
        <div class="mt-2">
            <span class="label">Publicada el: </span>
            <span class="valor"><?php echo date('d/m/Y H:i', strtotime($n['fecha_publicacion'])); ?></span>
        </div>
        <?php endif; ?>
    </div>

    <div class="card card-edit p-4">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?php echo $n['id']; ?>">

            <!-- TÍTULO -->
            <div class="mb-3">
                <label class="form-label">Título</label>
                <input type="text" name="titulo" class="form-control" value="<?php echo htmlspecialchars($n['titulo']); ?>" required>
            </div>

            <!-- DESCRIPCIÓN -->
            <div class="mb-3">
                <label class="form-label">Descripción Corta</label>
                <textarea name="descripcion" class="form-control" rows="2"><?php echo htmlspecialchars($n['descripcion'] ?? ''); ?></textarea>
            </div>

            <!-- CONTENIDO -->
            <div class="mb-3">
                <label class="form-label">Contenido Completo</label>
                <textarea name="contenido" class="form-control" rows="7" required><?php echo htmlspecialchars($n['contenido']); ?></textarea>
            </div>

            <!-- CATEGORÍA -->
            <div class="mb-3">
                <label class="form-label">Categoría</label>
                <select name="categoria_id" class="form-select">
                    <?php
                    $res_c = mysqli_query($conn, "SELECT * FROM categorias ORDER BY nombre ASC");
                    while($c = mysqli_fetch_assoc($res_c)){
                        $sel = ($c['id'] == $n['categoria_id']) ? "selected" : "";
                        echo "<option value='".$c['id']."' $sel>".htmlspecialchars($c['nombre'])."</option>";
                    }
                    ?>
                </select>
            </div>

            <!-- ES ESPECIAL -->
            <div class="mb-3 seccion-especial">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="es_especial" id="es_especial" value="1"
                        <?php echo ($n['es_especial'] == 1) ? 'checked' : ''; ?>>
                    <label class="form-check-label fw-bold text-primary" for="es_especial">
                        <i class="fas fa-star me-1"></i> ¿Historia Especial? (aparece en Revista / Edición Especial)
                    </label>
                </div>
                <small class="text-muted">Si está activo, esta noticia NO aparece en el noticiero normal.</small>
            </div>

            <!-- VIDEO URL -->
            <div class="mb-3">
                <label class="form-label">URL del Video (YouTube/Vimeo/Facebook)</label>
                <input type="url" name="video_url" class="form-control" placeholder="https://www.youtube.com/embed/VIDEO_ID" value="<?php echo htmlspecialchars($n['video_url'] ?? ''); ?>">
                <small class="text-muted">Deja vacío para quitar el video. Usa enlaces de inserción (embed).</small>
            </div>

            <!-- IMAGEN ACTUAL + NUEVA -->
            <div class="mb-4">
                <label class="form-label">Imagen</label>
                <?php if (!empty($n['imagen'])): ?>
                    <div class="mb-2">
                        <small class="text-muted d-block mb-1">Imagen actual:</small>
                        <img src="<?php echo htmlspecialchars($n['imagen']); ?>" class="imagen-preview"
                             onerror="this.style.display='none'; document.getElementById('sin-img').style.display='flex';">
                        <div id="sin-img" class="sin-imagen mt-1" style="display:none;">
                            <span><i class="fas fa-image me-1"></i> No se puede cargar la imagen actual</span>
                        </div>
                        <small class="text-muted">Ruta: <?php echo htmlspecialchars($n['imagen']); ?></small>
                    </div>
                <?php else: ?>
                    <div class="sin-imagen mb-2"><span><i class="fas fa-image me-2"></i>Sin imagen</span></div>
                <?php endif; ?>
                <input type="file" name="imagen" class="form-control mt-2" accept="image/*">
                <small class="text-muted">Deja vacío para mantener la imagen actual.</small>
            </div>

            <button type="submit" class="btn btn-guardar w-100 py-2">
                <i class="fas fa-save me-2"></i> GUARDAR CAMBIOS
            </button>
            <a href="admin.php" class="btn btn-link w-100 mt-2 text-secondary text-center d-block">Cancelar</a>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>