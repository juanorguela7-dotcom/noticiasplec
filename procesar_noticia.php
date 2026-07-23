<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}
include("conexion.php");

// ── BORRAR NOTICIA (desde admin.php) ──
if (isset($_GET['borrar'])) {
    $id = intval($_GET['borrar']);
    mysqli_query($conn, "DELETE FROM noticias WHERE id = $id");
    header("Location: admin.php");
    exit();
}

// ── GUARDAR NOTICIA (POST desde el formulario) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $titulo       = mysqli_real_escape_string($conn, trim($_POST['titulo'] ?? ''));
    $contenido    = mysqli_real_escape_string($conn, trim($_POST['contenido'] ?? ''));
    $categoria_id = intval($_POST['categoria'] ?? 0);
    $es_especial  = isset($_POST['es_especial']) ? 1 : 0;
    
    // INTEGRADO: Recepción limpia de la URL de video o transmisión en vivo externa
    $video_url    = mysqli_real_escape_string($conn, trim($_POST['video_url'] ?? ''));

    // Validación mínima
    if (empty($titulo) || empty($contenido) || $categoria_id === 0) {
        $_SESSION['mensaje'] = ['tipo' => 'danger', 'texto' => 'Faltan datos obligatorios (título, contenido o categoría).'];
        header("Location: admin.php");
        exit();
    }

    // ── SUBIDA DE IMAGEN ──
    $nombre_imagen = null;
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $ext_permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));

        if (in_array($ext, $ext_permitidas)) {
            $archivo = uniqid('noticia_', true) . '.' . $ext;
            $destino = 'img/' . $archivo;

            if (move_uploaded_file($_FILES['imagen']['tmp_name'], $destino)) {
                $nombre_imagen = 'img/' . $archivo;
            }
        }
    }

    // ── INSERT EN LA BASE DE DATOS EXTENDIDO CON LA COLUMNA VIDEO_URL ──
    $img_sql = $nombre_imagen ? "'$nombre_imagen'" : "NULL";
    $video_sql = !empty($video_url) ? "'$video_url'" : "NULL";

    $sql = "INSERT INTO noticias (titulo, contenido, categoria_id, es_especial, imagen, video_url, fecha_publicacion)
            VALUES ('$titulo', '$contenido', $categoria_id, $es_especial, $img_sql, $video_sql, NOW())";

    if (mysqli_query($conn, $sql)) {
        $_SESSION['mensaje'] = ['tipo' => 'success', 'texto' => 'Noticia publicada con éxito.'];
    } else {
        $_SESSION['mensaje'] = ['tipo' => 'danger', 'texto' => 'Error al guardar en la base de datos: ' . mysqli_error($conn)];
    }
    header("Location: admin.php");
    exit();
}
?>