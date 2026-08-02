<?php
// 1. Iniciar el búfer para controlar el flujo del navegador
ob_start();

// 2. Zona horaria
date_default_timezone_set('America/Bogota');

include("conexion.php");

$dominio = "https://noticiasplec-production.up.railway.app"; // <-- mismo dominio que index.php

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    header("Location: index.php");
    exit;
}

$stmt = mysqli_prepare($conn, "SELECT n.*, c.nombre as cat_nombre FROM noticias n LEFT JOIN categorias c ON n.categoria_id = c.id WHERE n.id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$noticia = $res ? mysqli_fetch_assoc($res) : null;
mysqli_stmt_close($stmt);

if (!$noticia) {
    header("Location: index.php");
    exit;
}

function obtenerRutaImagen($img_db) {
    if (empty($img_db)) return "img/placeholder.svg";
    return (strpos($img_db, 'img/') === 0) ? $img_db : "img/" . $img_db;
}

// ── SEO / Open Graph dinámico basado en ESTA noticia ──
$seo_titulo = htmlspecialchars($noticia['titulo']) . " - Noticias PLEC";
$texto_plano = trim(strip_tags($noticia['contenido']));
$seo_desc = htmlspecialchars(mb_substr($texto_plano, 0, 160)) . (mb_strlen($texto_plano) > 160 ? "..." : "");
$img_relativa = obtenerRutaImagen($noticia['imagen']);
$seo_imagen = (strpos($img_relativa, 'http') === 0) ? $img_relativa : $dominio . "/" . ltrim($img_relativa, "/");
$seo_url = $dominio . "/noticia.php?id=" . $id;
$fecha_pub = isset($noticia['fecha_publicacion']) ? date('d/m/Y H:i', strtotime($noticia['fecha_publicacion'])) : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $seo_titulo; ?></title>

    <!-- SEO básico -->
    <meta name="description" content="<?php echo $seo_desc; ?>">
    <meta name="robots" content="index, follow">
    <meta name="author" content="Noticias PLEC - Fleybher Martínez">
    <link rel="canonical" href="<?php echo $seo_url; ?>">

    <!-- Open Graph (Facebook, WhatsApp, etc.) -->
    <meta property="og:type" content="article">
    <meta property="og:title" content="<?php echo $seo_titulo; ?>">
    <meta property="og:description" content="<?php echo $seo_desc; ?>">
    <meta property="og:image" content="<?php echo $seo_imagen; ?>">
    <meta property="og:image:secure_url" content="<?php echo $seo_imagen; ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:url" content="<?php echo $seo_url; ?>">
    <meta property="og:site_name" content="Noticias PLEC">
    <meta property="og:locale" content="es_CO">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo $seo_titulo; ?>">
    <meta name="twitter:description" content="<?php echo $seo_desc; ?>">
    <meta name="twitter:image" content="<?php echo $seo_imagen; ?>">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=Libre+Franklin:wght@300;400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --azul: #1a73e8; --bg: #121212; --card: #1e1e1e; --rojo: #ff4d4d; --texto-gris: #aaa; }
        body { font-family: 'Libre Franklin', sans-serif; margin: 0; background: var(--bg); color: #e0e0e0; }
        .contenedor { max-width: 760px; margin: 0 auto; padding: 20px; box-sizing: border-box; }
        .volver { display: inline-flex; align-items: center; gap: 6px; color: #aaa; text-decoration: none; font-size: 13px; font-weight: bold; text-transform: uppercase; margin-bottom: 18px; }
        .volver:hover { color: #fff; }
        .cat-badge { display: inline-block; background: var(--azul); color: #fff; font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: .5px; padding: 4px 12px; border-radius: 20px; margin-bottom: 14px; }
        h1 { font-family: 'Playfair Display', serif; color: #fff; font-size: 32px; line-height: 1.25; margin: 0 0 10px; }
        .fecha { color: var(--texto-gris); font-size: 13px; margin-bottom: 20px; }
        .imagen-noticia { width: 100%; border-radius: 10px; margin-bottom: 22px; display: block; }
        .cuerpo { line-height: 1.8; font-size: 18px; color: #ddd; }
        .cuerpo img { max-width: 100%; border-radius: 8px; }
        .compartir { margin-top: 30px; border-top: 1px solid #333; padding-top: 18px; display: flex; flex-wrap: wrap; gap: 10px; }
        .compartir a { display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px; border-radius: 8px; text-decoration: none; color: #fff; font-size: 13px; font-weight: bold; }
        .btn-whatsapp { background: #25d366; }
        .btn-telegram { background: #229ED9; }
        .btn-facebook { background: #1877f2; }
    </style>
</head>
<body>
    <div class="contenedor">
        <a class="volver" href="index.php"><i class="fas fa-arrow-left"></i> Volver a Noticias PLEC</a>

        <?php if (!empty($noticia['cat_nombre'])): ?>
            <span class="cat-badge"><?php echo htmlspecialchars($noticia['cat_nombre']); ?></span>
        <?php endif; ?>

        <h1><?php echo htmlspecialchars($noticia['titulo']); ?></h1>
        <?php if ($fecha_pub): ?><div class="fecha"><?php echo $fecha_pub; ?></div><?php endif; ?>

        <img class="imagen-noticia" src="<?php echo htmlspecialchars($img_relativa); ?>" alt="<?php echo htmlspecialchars($noticia['titulo']); ?>">

        <div class="cuerpo"><?php echo $noticia['contenido']; ?></div>

        <div class="compartir">
            <a class="btn-whatsapp" href="https://wa.me/?text=<?php echo urlencode('📰 ' . $noticia['titulo'] . ' — Noticias PLEC: ' . $seo_url); ?>" target="_blank"><i class="fab fa-whatsapp"></i> WhatsApp</a>
            <a class="btn-telegram" href="https://t.me/share/url?url=<?php echo urlencode($seo_url); ?>&text=<?php echo urlencode('📰 ' . $noticia['titulo'] . ' — Noticias PLEC'); ?>" target="_blank"><i class="fab fa-telegram-plane"></i> Telegram</a>
            <a class="btn-facebook" href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($seo_url); ?>" target="_blank"><i class="fab fa-facebook"></i> Facebook</a>
        </div>
    </div>
</body>
</html>
<?php ob_end_flush(); ?>