<?php
// 1. Iniciar el búfer para controlar el flujo del navegador
ob_start();

// 2. Zona horaria
date_default_timezone_set('America/Bogota');

include("conexion.php");
include("funciones_video.php");

$dominio = PLEC_DOMINIO; // mismo dominio que index.php (se configura en conexion.php)

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
    return (strpos($img_db, 'img/') === 0 || strpos($img_db, 'http') === 0) ? $img_db : "img/" . $img_db;
}

// ── SEO / Open Graph dinámico basado en ESTA noticia ──
$seo_titulo = htmlspecialchars($noticia['titulo']) . " - Noticias PLEC";
$texto_plano = trim(preg_replace('/\s+/u', ' ', strip_tags($noticia['contenido'])));
$seo_desc = htmlspecialchars(mb_substr($texto_plano, 0, 160)) . (mb_strlen($texto_plano) > 160 ? "..." : "");
$img_relativa = obtenerRutaImagen($noticia['imagen']);
$img_og = ($img_relativa === "img/placeholder.svg") ? "img/logo-plec.jpg" : $img_relativa; // las redes no muestran SVG
$seo_imagen = (strpos($img_og, 'http') === 0) ? $img_og : $dominio . "/" . ltrim($img_og, "/");
$seo_url = $dominio . "/noticia.php?id=" . $id;
$fecha_pub = !empty($noticia['fecha_publicacion']) ? date('d/m/Y H:i', strtotime($noticia['fecha_publicacion'])) : '';
$minutos_lectura = max(1, (int) ceil(count(preg_split('/\s+/u', $texto_plano, -1, PREG_SPLIT_NO_EMPTY)) / 200));
$video_html = renderizarVideoEmbed($noticia['video_url'] ?? '');
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
    <link rel="canonical" href="<?php echo htmlspecialchars($seo_url); ?>">

    <!-- Open Graph (Facebook, WhatsApp, etc.) -->
    <meta property="og:type" content="article">
    <meta property="og:title" content="<?php echo $seo_titulo; ?>">
    <meta property="og:description" content="<?php echo $seo_desc; ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($seo_imagen); ?>">
    <meta property="og:image:secure_url" content="<?php echo htmlspecialchars($seo_imagen); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($seo_url); ?>">
    <meta property="og:site_name" content="Noticias PLEC">
    <meta property="og:locale" content="es_CO">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo $seo_titulo; ?>">
    <meta name="twitter:description" content="<?php echo $seo_desc; ?>">
    <meta name="twitter:image" content="<?php echo htmlspecialchars($seo_imagen); ?>">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=Libre+Franklin:wght@300;400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script>document.documentElement.classList.add('plec-js');</script>
    <style>
        :root { --azul: #1a73e8; --bg: #121212; --card: #1e1e1e; --rojo: #ff4d4d; --texto-gris: #aaa; }
        body { font-family: 'Libre Franklin', sans-serif; margin: 0; background: var(--bg); color: #e0e0e0; overflow-x: hidden; }
        header.plec-hero { min-height: 150px; padding: 26px 16px 22px; }
        .logo-text { font-family: 'Playfair Display', serif; font-size: 40px; color: #fff; font-weight: 900; text-transform: uppercase; margin: 0; line-height: 1.1; }
        .logo-text span { color: var(--rojo); }
        .slogan { color: #777; letter-spacing: 4px; font-size: 11px; font-weight: bold; text-transform: uppercase; margin-top: 8px; }
        .contenedor { max-width: 760px; margin: 0 auto; padding: 20px 16px 50px; box-sizing: border-box; }
        .volver { display: inline-flex; align-items: center; gap: 6px; color: #aaa; text-decoration: none; font-size: 13px; font-weight: bold; text-transform: uppercase; margin-bottom: 18px; transition: color .2s, gap .3s; }
        .volver:hover { color: #fff; gap: 10px; }
        .cat-badge { display: inline-block; background: linear-gradient(90deg, var(--azul), #8e44ad); color: #fff; font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: .5px; padding: 4px 12px; border-radius: 20px; margin-bottom: 14px; box-shadow: 0 6px 18px -6px rgba(26,115,232,.6); }
        h1.titular { font-family: 'Playfair Display', serif; color: #fff; font-size: 34px; line-height: 1.22; margin: 0 0 12px; }
        .fecha { color: var(--texto-gris); font-size: 13px; margin-bottom: 22px; display: flex; gap: 16px; flex-wrap: wrap; }
        .fecha i { color: var(--azul); margin-right: 5px; }
        .imagen-noticia { width: 100%; border-radius: 12px; display: block; }
        .cuerpo { line-height: 1.85; font-size: 18px; color: #ddd; white-space: pre-line; }
        .cuerpo img { max-width: 100%; border-radius: 8px; }
        .compartir { margin-top: 34px; border-top: 1px solid #333; padding-top: 18px; display: flex; flex-wrap: wrap; gap: 10px; }
        .compartir a { display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px; border-radius: 10px; text-decoration: none; color: #fff; font-size: 13px; font-weight: bold; transition: transform .3s cubic-bezier(.2,.8,.2,1), box-shadow .3s; }
        .compartir a:hover { transform: translateY(-3px); box-shadow: 0 12px 24px -10px rgba(0,0,0,.8); }
        .btn-whatsapp { background: #25d366; }
        .btn-telegram { background: #229ED9; }
        .btn-facebook { background: #1877f2; }
        .btn-portal { background: #2a2a2a; border: 1px solid #3a3a3a; }
        @media (max-width: 600px) {
            h1.titular { font-size: 26px; }
            .logo-text { font-size: 30px; }
            .cuerpo { font-size: 17px; }
        }
    </style>
    <link rel="stylesheet" href="assets/css/plec-3d.css?v=1">
</head>
<body>
    <header class="plec-hero">
        <canvas class="plec-globe" aria-hidden="true"></canvas>
        <a href="index.php" class="plec-logo-wrap"><div class="logo-text">Noticias <span>PLEC</span></div></a>
        <div class="slogan">#SOMOSTODOS | NOTICIERO INDEPENDIENTE</div>
    </header>

    <article class="contenedor">
        <a class="volver" href="index.php"><i class="fas fa-arrow-left"></i> Volver a Noticias PLEC</a>

        <div data-reveal-auto>
            <?php if (!empty($noticia['cat_nombre'])): ?>
                <span class="cat-badge"><?php echo htmlspecialchars($noticia['cat_nombre']); ?></span>
            <?php endif; ?>

            <h1 class="titular"><?php echo htmlspecialchars($noticia['titulo']); ?></h1>
            <div class="fecha">
                <?php if ($fecha_pub): ?><span><i class="far fa-calendar"></i><?php echo $fecha_pub; ?></span><?php endif; ?>
                <span><i class="far fa-clock"></i><?php echo $minutos_lectura; ?> min de lectura</span>
            </div>
        </div>

        <div class="plec-articulo-img-wrap" data-reveal-auto>
            <img class="imagen-noticia" src="<?php echo htmlspecialchars($img_relativa); ?>" alt="<?php echo htmlspecialchars($noticia['titulo']); ?>" onerror="this.onerror=null;this.src='img/placeholder.svg'">
        </div>

        <div class="cuerpo" data-reveal-auto><?php echo $noticia['contenido']; ?></div>

        <?php echo $video_html; ?>

        <div class="compartir" data-reveal-auto>
            <a class="btn-whatsapp" href="https://wa.me/?text=<?php echo urlencode('📰 ' . $noticia['titulo'] . ' — Noticias PLEC: ' . $seo_url); ?>" target="_blank" rel="noopener"><i class="fab fa-whatsapp"></i> WhatsApp</a>
            <a class="btn-telegram" href="https://t.me/share/url?url=<?php echo urlencode($seo_url); ?>&text=<?php echo urlencode('📰 ' . $noticia['titulo'] . ' — Noticias PLEC'); ?>" target="_blank" rel="noopener"><i class="fab fa-telegram-plane"></i> Telegram</a>
            <a class="btn-facebook" href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($seo_url); ?>" target="_blank" rel="noopener"><i class="fab fa-facebook"></i> Facebook</a>
            <a class="btn-portal" href="index.php"><i class="fas fa-newspaper"></i> Más noticias</a>
        </div>
    </article>

    <script>
    // Contar la visita para "Lo más leído"
    fetch('contar_vista.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'id=<?php echo $id; ?>' }).catch(function () {});
    </script>
    <script src="assets/js/plec-3d.js?v=1" defer></script>
</body>
</html>
<?php ob_end_flush(); ?>
