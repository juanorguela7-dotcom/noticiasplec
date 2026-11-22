<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}
include("conexion.php");

// ── BORRAR NOTICIA (requiere el mismo token CSRF que usa admin.php) ──
if (isset($_GET['borrar'])) {
    if (isset($_GET['token'], $_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_GET['token'])) {
        $id = intval($_GET['borrar']);
        $stmt = mysqli_prepare($conn, "DELETE FROM noticias WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
    }
    header("Location: admin.php");
    exit();
}

// ── GUARDAR NOTICIA (POST desde el formulario) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $titulo       = trim($_POST['titulo'] ?? '');
    $contenido    = trim($_POST['contenido'] ?? '');
    $categoria_id = intval($_POST['categoria'] ?? 0);
    $es_especial  = isset($_POST['es_especial']) ? 1 : 0;
    $video_url    = trim($_POST['video_url'] ?? '');
    if ($video_url === '') {
        $video_url = null;
    } elseif (!preg_match('#^https?://#i', $video_url)) {
        $video_url = 'https://' . ltrim($video_url, '/'); // "youtu.be/xxx" → "https://youtu.be/xxx"
    }

    // Validación mínima
    if ($titulo === '' || $contenido === '' || $categoria_id === 0) {
        $_SESSION['mensaje'] = ['tipo' => 'danger', 'texto' => 'Faltan datos obligatorios (título, contenido o categoría).'];
        header("Location: admin.php");
        exit();
    }

    // ── SUBIDA DE IMAGEN (se valida el contenido real, no solo la extensión) ──
    $nombre_imagen = null;
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $mimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
        $mime  = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['imagen']['tmp_name']);

        if (isset($mimes[$mime]) && @getimagesize($_FILES['imagen']['tmp_name']) !== false) {
            $destino = 'img/' . uniqid('noticia_', true) . '.' . $mimes[$mime];
            if (move_uploaded_file($_FILES['imagen']['tmp_name'], $destino)) {
                $nombre_imagen = $destino;
            }
        }
    }

    $stmt = mysqli_prepare($conn,
        "INSERT INTO noticias (titulo, contenido, categoria_id, es_especial, imagen, video_url, fecha_publicacion)
         VALUES (?, ?, ?, ?, ?, ?, NOW())");
    mysqli_stmt_bind_param($stmt, "ssiiss", $titulo, $contenido, $categoria_id, $es_especial, $nombre_imagen, $video_url);

    try {
        mysqli_stmt_execute($stmt);
        $_SESSION['mensaje'] = ['tipo' => 'success', 'texto' => 'Noticia publicada con éxito.'];
    } catch (Throwable $e) {
        error_log('Noticias PLEC - error al guardar noticia: ' . $e->getMessage());
        $_SESSION['mensaje'] = ['tipo' => 'danger', 'texto' => 'Error al guardar en la base de datos. Inténtalo de nuevo.'];
    }
    header("Location: admin.php");
    exit();
}

header("Location: admin.php");
exit();
