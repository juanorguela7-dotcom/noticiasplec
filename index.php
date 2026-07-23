<?php
// 1. Iniciar el búfer para controlar el flujo del navegador
ob_start();

// 2. Zona horaria
date_default_timezone_set('America/Bogota');

include("conexion.php");

$categoria_id = isset($_GET['cat']) ? intval($_GET['cat']) : 0;
$busqueda = isset($_GET['s']) ? mysqli_real_escape_string($conn, $_GET['s']) : '';

$esta_filtrando = ($categoria_id > 0 || !empty($busqueda));

// ── PAGINACIÓN ──
$por_pagina = 50; // noticias por página en vista filtrada
$pagina_actual = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
$offset = ($pagina_actual - 1) * $por_pagina;

// ── SEO: título y descripción dinámicos ──
$dominio = "https://noticiasplec-production.up.railway.app"; // <-- cambia por tu dominio real
$seo_titulo   = "Noticias PLEC - #SomosTodos | Noticiero Independiente";
$seo_desc     = "Noticias PLEC, noticiero independiente del Tolima, Colombia. Colombia, política, salud, internacional, educación y farándula.";
$seo_imagen   = $dominio . "/img/logo-plec.jpg";
$seo_url      = $dominio . "/index.php";

if ($categoria_id > 0 && isset($conn)) {
    $res_cat_seo = mysqli_query($conn, "SELECT nombre FROM categorias WHERE id = " . intval($categoria_id) . " LIMIT 1");
    if ($res_cat_seo && $row_cat_seo = mysqli_fetch_assoc($res_cat_seo)) {
        $seo_titulo = htmlspecialchars($row_cat_seo['nombre']) . " - Noticias PLEC";
        $seo_desc   = "Últimas noticias de " . htmlspecialchars($row_cat_seo['nombre']) . " en Noticias PLEC, noticiero independiente del Tolima.";
        $seo_url    = $dominio . "/index.php?cat=" . $categoria_id;
    }
}
if (!empty($busqueda)) {
    $seo_titulo = "Búsqueda: " . htmlspecialchars($busqueda) . " - Noticias PLEC";
    $seo_desc   = 'Resultados de búsqueda para "' . htmlspecialchars($busqueda) . '" en Noticias PLEC.';
}
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
    <meta name="keywords" content="noticias Colombia, Tolima, política, salud, educación, farándula, noticiero independiente, PLEC">
    <link rel="canonical" href="<?php echo $seo_url; ?>">

    <!-- Open Graph (Facebook, WhatsApp, etc.) -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?php echo $seo_titulo; ?>">
    <meta property="og:description" content="<?php echo $seo_desc; ?>">
    <meta property="og:image" content="<?php echo $seo_imagen; ?>">
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
        :root { --azul: #1a73e8; --bg: #121212; --card: #1e1e1e; --rojo: #ff4d4d; --texto-gris: #aaa; --inclusion: #8e44ad; }
        body { font-family: 'Libre Franklin', sans-serif; margin: 0; background: var(--bg); color: #e0e0e0; overflow-x: hidden; }

        /* BARRA SUPERIOR */
        .top-bar { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            max-width: 100%; 
            margin: 0 auto; 
            padding: 12px 20px 12px 75px; 
            border-bottom: 1px solid #333;
            gap: 15px;
            flex-wrap: wrap;
            box-sizing: border-box;
        }
        .search-box { 
            display: flex;
            align-items: center;
            background: #181818;
            border: 1px solid #383838;
            border-radius: 25px;
            overflow: hidden;
            flex-shrink: 0;
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        .search-box:focus-within {
            border-color: var(--azul);
            box-shadow: 0 0 0 3px rgba(26,115,232,0.15);
        }
        .search-box input { 
            border: none; 
            padding: 7px 14px;
            background: transparent;
            color: #ddd; 
            width: 160px; 
            outline: none; 
            font-size: 11px;
            letter-spacing: 0.3px;
        }
        .search-box input::placeholder { color: #555; }
        .search-box button { 
            background: var(--rojo);
            border: none; 
            color: #fff; 
            padding: 0 14px;
            cursor: pointer;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 0 25px 25px 0;
            transition: background 0.2s;
            font-size: 12px;
        }
        .search-box button:hover { background: #cc3333; }
        .search-cat-select { 
            background: transparent;
            border: none;
            border-right: 1px solid #2a2a2a;
            color: #666; 
            padding: 0 8px;
            font-size: 10px; 
            outline: none; 
            cursor: pointer; 
            max-width: 80px;
            height: 32px;
        }
        .search-cat-select:hover { color: #aaa; }

        /* REDES SOCIALES FLOTANTES */
        .social-icons { display: flex; align-items: center; gap: 8px; }
        .social-icons a {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            text-decoration: none;
            font-size: 14px;
            transition: transform 0.3s, box-shadow 0.3s;
            animation: flotar 3s ease-in-out infinite;
        }
        .social-icons a:nth-child(1) {
            background: #1877f2;
            color: #fff;
            box-shadow: 0 4px 14px rgba(24,119,242,0.5);
            animation-delay: 0s;
        }
        .social-icons a:nth-child(2) {
            background: linear-gradient(45deg, #f09433, #e6683c, #dc2743, #cc2366, #bc1888);
            color: #fff;
            box-shadow: 0 4px 14px rgba(220,39,67,0.5);
            animation-delay: 0.4s;
        }
        .social-icons a:nth-child(3) {
            background: #25d366;
            color: #fff;
            box-shadow: 0 4px 14px rgba(37,211,102,0.5);
            animation-delay: 0.8s;
        }
        .social-icons a:hover {
            transform: translateY(-4px) scale(1.15) !important;
            animation-play-state: paused;
        }
        @keyframes flotar {
            0%, 100% { transform: translateY(0); }
            50%       { transform: translateY(-4px); }
        }

        /* LOGO */
        header { text-align: center; padding: 25px 20px; box-sizing: border-box; }
        .logo-text { 
            font-family: 'Playfair Display', serif; 
            font-size: 60px; 
            color: #fff; 
            font-weight: 900; 
            text-transform: uppercase; 
            text-decoration: none;
            display: inline-block;
            line-height: 1.1;
            margin: 0;
        }
        .logo-text span { color: var(--rojo); }
        .slogan { 
            color: #777; 
            letter-spacing: 4px; 
            font-size: 12px; 
            font-weight: bold; 
            text-transform: uppercase;
            margin-top: 8px;
        }

        /* NAVEGACIÓN */
        nav { background: #181818; border-top: 4px solid var(--azul); border-bottom: 1px solid #333; position: relative; z-index: 999; overflow-x: auto; }
        .nav-container { display: flex; justify-content: center; list-style: none; margin: 0; padding: 0; flex-wrap: wrap; gap: 0; }
        .nav-container a { 
            padding: 16px 18px; 
            color: #fff; 
            text-decoration: none; 
            font-weight: bold; 
            font-size: 13px; 
            text-transform: uppercase; 
            transition: 0.3s; 
            display: block;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .nav-container a:hover { color: #ffcc00; background: rgba(255,255,255,0.05); }

        /* BADGE ÚLTIMA HORA EN NAV */
        .nav-ultimahora-wrap { 
            position: absolute; 
            left: 12px; 
            top: 50%; 
            transform: translateY(-50%);
            display: flex;
            align-items: center;
        }
        .nav-ultimahora {
            background: var(--rojo);
            color: #fff;
            font-size: 11px;
            font-weight: 900;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            padding: 5px 12px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            animation: pulso 1.8s ease-in-out infinite;
            white-space: nowrap;
        }
        .nav-ultimahora i { font-size: 10px; }

        /* SIDEBAR */
        .menu-toggle { 
            position: fixed; 
            top: 0px; 
            left: 0px; 
            z-index: 10005; 
            background: var(--azul); 
            color: white; 
            border: none; 
            width: 38px; 
            height: 60px; 
            border-radius: 0 8px 8px 0; 
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            transition: 0.3s;
            box-shadow: 2px 0 10px rgba(0,0,0,0.4);
        }
        .menu-toggle:active { transform: scale(0.95); }
        .sidebar { 
            height: 100vh; 
            width: 0; 
            position: fixed; 
            z-index: 10010; 
            top: 0; 
            left: 0; 
            background: #1a1a1a; 
            overflow-y: auto; 
            transition: 0.5s; 
            border-right: 3px solid var(--azul);
            -webkit-overflow-scrolling: touch;
        }
        .sidebar-content a { 
            padding: 8px 20px; 
            text-decoration: none; 
            font-size: 12px; 
            color: #bbb; 
            display: block; 
            border-bottom: 1px solid #222; 
            text-transform: uppercase;
            min-height: 32px;
            display: flex;
            align-items: center;
            transition: 0.2s;
        }
        .sidebar-content a:active { background: rgba(26,115,232,0.2); }
        .btn-impreso-side { color: var(--rojo) !important; font-weight: bold; }

        /* BOTÓN REVISTA FLOTANTE */
        .btn-revista-flotante { 
            position: fixed; 
            bottom: 30px; 
            right: 30px; 
            background: linear-gradient(135deg, var(--azul), var(--rojo)); 
            color: white; 
            text-decoration: none; 
            padding: 8px 16px; 
            border-radius: 50px; 
            display: flex; 
            align-items: center; 
            gap: 7px; 
            box-shadow: 0 4px 14px rgba(0,0,0,0.4); 
            z-index: 9999;
            font-size: 11px;
            font-weight: bold;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            transition: 0.3s;
        }
        .btn-revista-flotante:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.5); }

        /* MAQUETADO GENERAL */
        main { 
            max-width: 1350px; 
            margin: 30px auto; 
            padding: 0 20px; 
            box-sizing: border-box;
        }
        .card { 
            background: var(--card); 
            border-radius: 8px; 
            border: 1px solid #2a2a2a; 
            cursor: pointer; 
            margin-bottom: 25px; 
            overflow: hidden; 
            transition: 0.3s;
            display: flex;
            flex-direction: column;
        }
        .card:active { transform: scale(0.98); }
        .card:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.3); }
        .img-box { width: 100%; height: 100%; object-fit: cover; display: block; position: relative; }
        .img-box img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .info-card { padding: 16px; flex: 1; display: flex; flex-direction: column; }
        .cat-tag { color: #ffcc00; font-size: 10px; font-weight: bold; border-left: 3px solid var(--rojo); padding-left: 8px; text-transform: uppercase; display: inline-block; margin-bottom: 8px; }
        .cat-tag.inclusion { color: #fff; background: linear-gradient(90deg, #e40303, #ff8c00, #ffed00, #008026, #004dff, #750787); border-left: none; padding: 3px 8px; border-radius: 3px; font-weight: 900; }
        .seccion-titulo.inclusion { border-bottom: 3px solid; border-image: linear-gradient(90deg, #e40303, #ff8c00, #ffed00, #008026, #004dff, #750787) 1; color: #c39bd3; }
        /* NAV FARÁNDULA */
        .nav-farandula {
            color: #ff6ec7 !important;
            font-weight: 900 !important;
            letter-spacing: 1px;
            position: relative;
            text-shadow: 0 0 12px rgba(255, 110, 199, 0.5);
        }
        .nav-farandula::after {
            content: '';
            position: absolute;
            bottom: 8px;
            left: 18px;
            right: 18px;
            height: 2px;
            background: #ff6ec7;
            border-radius: 2px;
            opacity: 0.7;
        }
        .nav-farandula:hover {
            color: #ff9dda !important;
            text-shadow: 0 0 18px rgba(255, 110, 199, 0.8) !important;
        }
        /* NAV INCLUSIÓN */
        .nav-inclusion {
            background: linear-gradient(90deg, #e40303, #ff8c00, #ffed00, #008026, #004dff, #750787);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 900 !important;
            letter-spacing: 1px;
            position: relative;
        }
        .nav-inclusion::after {
            content: '';
            position: absolute;
            bottom: 8px;
            left: 18px;
            right: 18px;
            height: 2px;
            background: linear-gradient(90deg, #e40303, #ff8c00, #ffed00, #008026, #004dff, #750787);
            border-radius: 2px;
            opacity: 0.7;
        }
        .nav-inclusion i { font-size: 13px; margin: 0 3px; -webkit-text-fill-color: initial; }
        .nav-inclusion i.fa-universal-access { color: #60a5fa; }
        .nav-inclusion i.fa-heart         { color: #f87171; }
        .nav-inclusion i.fa-hands-helping  { color: #86efac; }
        /* ICONOS INCLUSIÓN EN SECCIÓN */
        .inclusion-icons { margin-left: 10px; display: inline-flex; gap: 6px; align-items: center; font-size: 18px; vertical-align: middle; }
        .inclusion-icons .fa-universal-access { color: #60a5fa; }
        .inclusion-icons .fa-heart         { color: #f87171; }
        .inclusion-icons .fa-hands-helping  { color: #86efac; }
        .resumen-text { color: var(--texto-gris); font-size: 14px; line-height: 1.4; margin-top: 10px; }

        /* ESTRUCTURA BLOQUE PRINCIPAL */
        .bloque-principal { display: grid; grid-template-columns: 320px 1fr 320px; gap: 25px; margin-bottom: 50px; }
        .principal-centro .img-box { height: 450px; }
        .principal-centro h2 { font-family: 'Playfair Display', serif; font-size: 38px; color: #fff; margin: 10px 0; line-height: 1.1; }
        .principal-laterales .img-box { height: 180px; }
        .principal-laterales h3 { font-family: 'Playfair Display', serif; font-size: 18px; color: #fff; margin: 8px 0; line-height: 1.3; }

        /* SECCIONES TEMÁTICAS */
        .seccion-bloque { margin-bottom: 50px; border-top: 1px solid #333; padding-top: 20px; }
        .seccion-titulo { font-family: 'Playfair Display', serif; font-size: 24px; color: #fff; margin-bottom: 20px; text-transform: uppercase; border-bottom: 2px solid var(--azul); display: inline-block; padding-bottom: 5px; }
        .seccion-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; }
        .seccion-grid .img-box { height: 160px; }
        .seccion-grid h4 { font-family: 'Playfair Display', serif; font-size: 16px; color: #fff; margin: 8px 0; line-height: 1.3; }
        /* CABECERA DE SECCIÓN CON VER MÁS */
        .seccion-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; flex-wrap: wrap; gap: 8px; }
        .sec-contador { font-size: 11px; color: #555; font-weight: bold; background: #1e1e1e; border: 1px solid #2a2a2a; padding: 3px 10px; border-radius: 20px; }
        /* BOTÓN VER MÁS */
        .btn-ver-mas { 
            font-size: 11px; 
            font-weight: bold; 
            color: var(--azul); 
            text-decoration: none; 
            border: 1px solid var(--azul); 
            padding: 8px 16px; 
            border-radius: 20px; 
            text-transform: uppercase; 
            letter-spacing: 1px; 
            transition: 0.2s; 
            white-space: nowrap;
            min-height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .btn-ver-mas:hover { background: var(--azul); color: #fff; }
        /* BADGE NUEVO */
        .badge-nuevo { position: absolute; top: 10px; left: 10px; background: var(--rojo); color: #fff; font-size: 10px; font-weight: 900; padding: 3px 9px; border-radius: 20px; letter-spacing: 1px; text-transform: uppercase; z-index: 2; box-shadow: 0 2px 8px rgba(255,77,77,0.5); animation: pulso 1.8s ease-in-out infinite; }
        .badge-nuevo-lg { font-size: 12px; padding: 5px 13px; top: 14px; left: 14px; }
        @keyframes pulso { 0%,100% { box-shadow: 0 2px 8px rgba(255,77,77,0.5); } 50% { box-shadow: 0 2px 18px rgba(255,77,77,0.9); } }
        /* FECHA RELATIVA EN CARD */
        .fecha-noticia { font-size: 10px; color: #555; margin-top: 6px; display: block; }
        .fecha-noticia i { margin-right: 4px; color: #444; }

        /* SECCIÓN INFERIOR COMPUESTA */
        .bloque-inferior { display: grid; grid-template-columns: 1.2fr 2fr 1fr; gap: 1px; background: #222; border-radius: 10px; margin-bottom: 40px; overflow: hidden; border: 1px solid #222; }
        .bloque-inferior > div { background: #161616; padding: 32px 28px; }
        .bloque-inferior > div:first-child { border-right: 1px solid #1e1e1e; }
        .bloque-inferior > div:last-child { border-left: 1px solid #1e1e1e; }
        .col-titulo { font-family: 'Playfair Display', serif; font-size: 13px; color: #888; margin-top: 0; margin-bottom: 22px; text-transform: uppercase; letter-spacing: 3px; font-weight: 400; border-bottom: 1px solid #1e1e1e; padding-bottom: 12px; }
        
        .editorial-box { background: transparent; padding: 0; border-radius: 0; border: none; }
        .editorial-box h3 { font-family: 'Playfair Display', serif; font-size: 20px; color: #e0e0e0; margin: 0 0 12px; line-height: 1.4; font-weight: 700; }

        .columnistas-list { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
        .columnista-item { display: flex; gap: 12px; align-items: center; padding: 10px; border-radius: 6px; border: 1px solid #1e1e1e; transition: border-color 0.2s, background 0.2s; }
        .columnista-item:hover { border-color: #2a2a2a; background: #1a1a1a; }
        .columnista-avatar { width: 46px; height: 46px; border-radius: 50%; background: #222; object-fit: cover; flex-shrink: 0; border: 1px solid #2a2a2a; }
        .columnista-info h4 { font-size: 13px; margin: 0 0 3px 0; color: #ddd; font-family: 'Playfair Display', serif; line-height: 1.3; font-weight: 700; }
        .columnista-info p { font-size: 10px; margin: 0; color: #555; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .columnista-info span { font-size: 10px; color: #444; }

        .impreso-box { text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: center; }
        .impreso-img { width: 100%; max-width: 160px; border-radius: 3px; box-shadow: 0 8px 24px rgba(0,0,0,0.5); margin-bottom: 18px; }
        .btn-impreso { 
            background: transparent; 
            border: 1px solid #333; 
            color: #888; 
            padding: 8px 20px; 
            border-radius: 3px; 
            text-decoration: none; 
            font-size: 10px; 
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            display: inline-flex; 
            align-items: center; 
            justify-content: center;
            transition: border-color 0.2s, color 0.2s;
        }
        .btn-impreso:hover { border-color: #555; color: #ccc; }

        /* MODAL */
        .modal { display: none; position: fixed; z-index: 11000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.95); overflow-y: auto; }
        .modal-content { background: #1e1e1e; max-width: 900px; margin: 40px auto; padding: 30px; border-radius: 10px; position: relative; }

        .grid-busqueda { display: grid; grid-template-columns: repeat(3, 1fr); gap: 25px; }
        footer { background: #0a0a0a; border-top: 3px solid var(--azul); color: #888; padding: 40px 20px; font-size: 14px; margin-top: 40px; }
        .footer-container { max-width: 1350px; margin: 0 auto; display: grid; grid-template-columns: auto 1fr auto; align-items: center; gap: 30px; }
        .footer-info p { margin: 6px 0; }
        .footer-info i { color: var(--azul); margin-right: 8px; width: 16px; }
        .footer-right { text-align: right; }
        .footer-right p { margin: 5px 0; }

        /* MENSAJE MOTIVADOR DEL FOOTER */
        .footer-mensaje {
            text-align: center;
            padding: 0 20px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 6px;
            border-left: 1px solid #2a2a2a;
            border-right: 1px solid #2a2a2a;
        }
        .footer-mensaje p {
            margin: 0;
            font-size: 13px;
            line-height: 1.7;
            color: #888;
            font-style: italic;
        }
        .footer-mensaje span {
            font-size: 12px;
            color: #666;
        }
        .footer-mensaje strong {
            background: linear-gradient(90deg, #1a73e8, #ff4d4d);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-style: normal;
        }
        body.modo-claro .footer-mensaje { border-color: #ddd; }
        body.modo-claro .footer-mensaje p { color: #777; }
        body.modo-claro .footer-mensaje span { color: #888; }

        /* ===================== RESPONSIVE MEJORADO ===================== */

        /* DESKTOP GRANDE (1200px+) */
        @media (min-width: 1200px) {
            main { max-width: 1400px; }
            .bloque-principal { grid-template-columns: 320px 1fr 320px; gap: 30px; }
            .seccion-grid { grid-template-columns: repeat(4, 1fr); gap: 25px; }
        }

        /* TABLET GRANDE (1025px - 1199px) */
        @media (max-width: 1199px) {
            .bloque-principal { grid-template-columns: 1fr 1fr; }
            .principal-laterales:last-child { display: none; }
            .principal-centro .img-box { height: 340px; }
            .seccion-grid { grid-template-columns: repeat(3, 1fr); gap: 22px; }
            .bloque-inferior { grid-template-columns: 1fr 1fr; }
            .bloque-inferior > div:last-child { grid-column: 1 / -1; }
        }

        /* TABLET (769px - 1024px) */
        @media (max-width: 1024px) {
            main { max-width: 100%; margin: 20px auto; }
            .bloque-principal { grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 40px; }
            .principal-laterales:last-child { display: none; }
            .principal-centro .img-box { height: 280px; }
            .principal-centro h2 { font-size: 32px; }
            .seccion-grid { grid-template-columns: repeat(2, 1fr); gap: 18px; }
            .seccion-grid .img-box { height: 140px; }
            .bloque-inferior { grid-template-columns: 1fr 1fr; gap: 25px; padding: 30px 20px; }
            .bloque-inferior > div:last-child { grid-column: 1 / -1; }
            .columnistas-list { grid-template-columns: 1fr 1fr; gap: 15px; }
            .grid-busqueda { grid-template-columns: repeat(2, 1fr); gap: 20px; }
            .top-bar { padding: 12px 18px 12px 75px; gap: 12px; }
            .search-box input { width: 120px; }
            .search-cat-select { max-width: 80px; font-size: 11px; }
            .logo-text { font-size: 58px; }
            .nav-container a { padding: 18px 16px; font-size: 12px; }
            .btn-revista-flotante { bottom: 20px; right: 20px; padding: 11px 20px; font-size: 12px; }
            .btn-arriba { bottom: 95px; right: 20px; }
        }

        /* MÓVIL GRANDE (651px - 768px) */
        @media (max-width: 768px) {
            main { max-width: 100%; margin: 15px auto; padding: 0 15px; }
            .top-bar { flex-direction: column; gap: 8px; padding: 10px 15px 10px 65px; align-items: flex-start; }
            .top-bar > div { width: 100%; }
            .top-bar > div:last-child { display: flex; flex-direction: column; gap: 8px; }
            .search-box { width: 100%; }
            .search-box input { width: 100%; }
            .search-cat-select { max-width: 100%; padding: 8px 6px; font-size: 11px; }
            .social-icons { display: flex; gap: 15px; }
            .social-icons a { font-size: 13px; }
            .climate-widget { font-size: 10px; }
            .logo-text { font-size: 44px; margin: 0; }
            header { padding: 16px 0 8px 0; }
            .slogan { font-size: 10px; letter-spacing: 3px; }
            nav { border-top: 3px solid var(--azul); }
            .nav-container { flex-wrap: wrap; }
            .nav-container a { padding: 14px 12px; font-size: 11px; }
            .nav-container a:hover { background: rgba(26, 115, 232, 0.1); }
            .bloque-principal { grid-template-columns: 1fr; gap: 15px; margin-bottom: 35px; }
            .principal-laterales { display: none; }
            .principal-centro .img-box { height: 240px; }
            .principal-centro h2 { font-size: 26px; margin: 12px 0; }
            .seccion-grid { grid-template-columns: repeat(2, 1fr); gap: 14px; }
            .seccion-grid .img-box { height: 120px; }
            .seccion-grid h4 { font-size: 14px; }
            .seccion-titulo { font-size: 20px; margin-bottom: 15px; }
            .seccion-header { margin-bottom: 12px; }
            .bloque-inferior { grid-template-columns: 1fr; gap: 20px; padding: 25px 15px; }
            .col-titulo { font-size: 20px; margin-bottom: 15px; }
            .columnistas-list { grid-template-columns: 1fr; gap: 12px; }
            .columnista-item { gap: 12px; }
            .columnista-avatar { width: 55px; height: 55px; }
            .grid-busqueda { grid-template-columns: 1fr; gap: 15px; }
            .modal-content { margin: 15px; padding: 25px 20px; }
            .btn-revista-flotante { bottom: 12px; right: 12px; padding: 9px 14px; font-size: 11px; gap: 8px; }
            .btn-arriba { bottom: 85px; right: 15px; width: 40px; height: 40px; font-size: 16px; }
            .footer-container { flex-direction: column; gap: 20px; }
            .footer-right { text-align: left; }
        }

        /* MÓVIL ESTÁNDAR (481px - 650px) */
        @media (max-width: 650px) {
            main { padding: 0 12px; margin: 12px auto; }
            .top-bar { flex-direction: column; gap: 6px; padding: 8px 12px 8px 60px; }
            .top-bar > div { width: 100%; }
            .search-box { width: 100%; }
            .search-box input { width: 100%; font-size: 11px; }
            .search-cat-select { width: 100%; padding: 7px 5px; font-size: 10px; }
            .social-icons { gap: 12px; }
            .social-icons a { font-size: 12px; }
            .logo-text { font-size: 36px; }
            header { padding: 12px 0 6px 0; }
            .slogan { font-size: 9px; letter-spacing: 2px; }
            .nav-container a { padding: 11px 10px; font-size: 10px; }
            .bloque-principal { margin-bottom: 30px; }
            .principal-centro .img-box { height: 200px; }
            .principal-centro h2 { font-size: 22px; }
            .seccion-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
            .seccion-grid .img-box { height: 100px; }
            .seccion-grid h4 { font-size: 13px; }
            .seccion-titulo { font-size: 16px; margin-bottom: 12px; }
            .bloque-inferior { gap: 15px; padding: 18px 12px; }
            .col-titulo { font-size: 17px; }
            .columnistas-list { gap: 10px; }
            .columnista-avatar { width: 50px; height: 50px; }
            .columnista-info h4 { font-size: 12px; }
            .columnista-info p { font-size: 11px; }
            .grid-busqueda { gap: 12px; }
            .modal-content { margin: 10px; padding: 20px 15px; }
            .btn-revista-flotante { bottom: 10px; right: 10px; padding: 8px 12px; font-size: 10px; }
            .btn-arriba { bottom: 78px; right: 12px; }
            .ticker-item { font-size: 11px; margin: 0 20px; }
        }

        /* MÓVIL MEDIANO (421px - 480px) */
        @media (max-width: 480px) {
            main { padding: 0 10px; margin: 10px auto; }
            .top-bar { padding: 6px 10px 6px 55px; gap: 4px; }
            .logo-text { font-size: 32px; line-height: 1.1; }
            .slogan { font-size: 8px; letter-spacing: 1.5px; }
            .nav-container { gap: 0; }
            .nav-container a { padding: 10px 8px; font-size: 9px; }
            .menu-toggle { width: 40px; height: 40px; left: 10px; top: 12px; }
            .principal-centro .img-box { height: 180px; }
            .principal-centro h2 { font-size: 20px; }
            .seccion-grid { grid-template-columns: 1fr; gap: 8px; }
            .seccion-grid .img-box { height: 150px; }
            .seccion-titulo { font-size: 14px; }
            .bloque-inferior { gap: 12px; padding: 15px 10px; }
            .columnistas-list { gap: 8px; }
            .columnista-avatar { width: 45px; height: 45px; }
            .grid-busqueda { gap: 10px; }
            .modal-content { padding: 15px 12px; margin: 8px; }
            .btn-revista-flotante { padding: 7px 10px; font-size: 9px; bottom: 8px; right: 8px; }
            .btn-arriba { bottom: 70px; right: 10px; width: 38px; height: 38px; }
            .ticker-item { font-size: 10px; margin: 0 15px; }
            .bienvenida-box { width: 95%; padding: 30px 20px; }
            .bienvenida-logo { font-size: 32px; }
            .bienvenida-titular { font-size: 18px; }
            .bienvenida-texto { font-size: 12px; }
        }

        /* MÓVIL PEQUEÑO (361px - 420px) */
        @media (max-width: 420px) {
            main { padding: 0 8px; }
            .top-bar { padding: 5px 8px 5px 50px; }
            .logo-text { font-size: 28px; }
            .slogan { font-size: 7px; letter-spacing: 1px; }
            .nav-container a { padding: 9px 6px; font-size: 8px; }
            .menu-toggle { width: 38px; height: 38px; font-size: 16px; }
            .principal-centro .img-box { height: 160px; }
            .principal-centro h2 { font-size: 18px; margin: 8px 0; }
            .seccion-grid .img-box { height: 130px; }
            .seccion-titulo { font-size: 13px; }
            .btn-revista-flotante { padding: 6px 9px; font-size: 8px; }
            .bienvenida-box { padding: 20px 15px; }
            .bienvenida-logo { font-size: 28px; }
            .bienvenida-titular { font-size: 16px; }
        }

        /* MÓVIL MUY PEQUEÑO (hasta 360px) */
        @media (max-width: 360px) {
            main { padding: 0 5px; margin: 5px auto; }
            .top-bar { padding: 4px 5px 4px 48px; }
            .logo-text { font-size: 24px; }
            .slogan { font-size: 6px; }
            .nav-container a { padding: 8px 5px; font-size: 7px; }
            .menu-toggle { width: 36px; height: 36px; font-size: 14px; left: 5px; }
            .principal-centro .img-box { height: 140px; }
            .principal-centro h2 { font-size: 16px; }
            .seccion-grid .img-box { height: 110px; }
            .seccion-titulo { font-size: 12px; }
            .bloque-inferior { padding: 12px 8px; }
            .btn-revista-flotante { padding: 5px 8px; font-size: 7px; bottom: 5px; right: 5px; }
            .btn-arriba { bottom: 60px; right: 5px; width: 36px; height: 36px; font-size: 14px; }
            .ticker-item { font-size: 9px; margin: 0 10px; }
            .bienvenida-box { padding: 15px 10px; }
            .bienvenida-logo { font-size: 24px; }
            .bienvenida-titular { font-size: 14px; }
            .bienvenida-texto { font-size: 11px; }
        }
        /* MODO CLARO */
        body.modo-claro { background: #f0f0f0; color: #111; }
        body.modo-claro .card { background: #fff; border-color: #ddd; }
        body.modo-claro nav { background: #fff; border-bottom-color: #ddd; }
        body.modo-claro .nav-container a { color: #111; }
        body.modo-claro header { background: #f0f0f0; }
        body.modo-claro .logo-text { color: #111; }
        body.modo-claro .top-bar { border-bottom-color: #ccc; }
        body.modo-claro .sidebar { background: #fff; border-right-color: var(--azul); }
        body.modo-claro .sidebar-content a { color: #333; border-bottom-color: #eee; }
        body.modo-claro .seccion-titulo { color: #111; }
        body.modo-claro .seccion-bloque { border-top-color: #ccc; }
        body.modo-claro .bloque-inferior { background: #e5e5e5; border-color: #ccc; }
        body.modo-claro .bloque-inferior > div { background: #f5f5f5; }
        body.modo-claro .editorial-box h3 { color: #111; }
        body.modo-claro .col-titulo { color: #777; border-bottom-color: #ddd; }
        body.modo-claro .columnista-item { border-color: #ddd; }
        body.modo-claro .columnista-item:hover { background: #ececec; border-color: #ccc; }
        body.modo-claro .columnista-info h4 { color: #111; }
        body.modo-claro .editorial-box { background: #fff; border-color: #ddd; }
        body.modo-claro .editorial-box h3 { color: #111; }
        body.modo-claro .col-titulo { color: #111; }
        body.modo-claro .columnista-info h4 { color: #111; }
        body.modo-claro .resumen-text { color: #444; }
        body.modo-claro .principal-centro h2,
        body.modo-claro .principal-laterales h3,
        body.modo-claro .seccion-grid h4 { color: #111; }
        body.modo-claro footer { background: #ddd; color: #444; }
        body.modo-claro .slogan { color: #555; }
        body.modo-claro .search-box { background: #e0e0e0; border-color: #bbb; }
        body.modo-claro .search-box input { background: #e0e0e0; color: #111; }

        /* BOTÓN TEMA */
        .btn-tema { background: none; border: 1px solid #444; color: #fff; width: 34px; height: 34px; border-radius: 50%; cursor: pointer; font-size: 16px; display:flex; align-items:center; justify-content:center; transition:0.3s; flex-shrink:0; }
        body.modo-claro .btn-tema { border-color: #999; color: #111; }
        .btn-tema:hover { transform: rotate(20deg) scale(1.1); }
        /* ── BIENVENIDA MODAL ────────────────────────────────────────────── */
        #modalBienvenida { display:none; position:fixed; z-index:99999; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.88); backdrop-filter:blur(5px); align-items:center; justify-content:center; }
        #modalBienvenida.show { display:flex; }
        .bienvenida-box { background: linear-gradient(145deg, #1a1a2e, #16213e, #0f3460); border: 1px solid #2a2a5a; border-radius: 12px; max-width: 360px; width: 88%; padding: 28px 26px 22px; text-align: center; position: relative; box-shadow: 0 0 40px rgba(26,115,232,0.25), 0 0 80px rgba(116,0,135,0.1); animation: slideDown 0.4s ease; }
        @keyframes slideDown { from { opacity:0; transform:translateY(-28px); } to { opacity:1; transform:translateY(0); } }
        .bienvenida-logo { font-family:'Playfair Display',serif; font-size:32px; font-weight:900; color:#fff; text-transform:uppercase; margin-bottom:2px; letter-spacing:1px; }
        .bienvenida-logo span { color: var(--rojo); }
        .bienvenida-slogan { font-size:9px; letter-spacing:3px; color:#556; font-weight:700; text-transform:uppercase; margin-bottom:16px; }
        .bienvenida-linea { width:40px; height:2px; background: linear-gradient(90deg, var(--azul), var(--rojo)); margin: 0 auto 16px; border-radius:2px; }
        .bienvenida-titular { font-family:'Playfair Display',serif; font-size:16px; color:#e8e8e8; margin-bottom:10px; line-height:1.4; font-weight:700; min-height:46px; }
        .bienvenida-texto { color:#778; font-size:12px; line-height:1.65; margin-bottom:18px; }
        .bienvenida-fecha { font-size:9px; color:#445; font-weight:700; text-transform:uppercase; letter-spacing:1.5px; margin-bottom:18px; border-top:1px solid #1e2a40; padding-top:14px; }
        .bienvenida-btn { display:inline-block; background: linear-gradient(135deg, var(--azul), #0d47a1); color:#fff; border:none; padding:10px 28px; border-radius:40px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:1.5px; cursor:pointer; transition:0.25s; box-shadow:0 3px 14px rgba(26,115,232,0.35); }
        .bienvenida-btn:hover { transform:translateY(-2px); box-shadow:0 6px 20px rgba(26,115,232,0.55); }
        .bienvenida-puntos { display:flex; justify-content:center; gap:6px; margin-top:14px; }
        .bienvenida-puntos span { width:5px; height:5px; border-radius:50%; background:#1e2a40; display:inline-block; transition:background 0.3s; }
        .bienvenida-puntos span.activo { background: var(--azul); }
        /* TICKER DE NOTICIAS */
        .ticker-wrap { background: #0d0d0d; border-top: 2px solid var(--rojo); border-bottom: 2px solid var(--azul); padding: 8px 0; overflow: hidden; white-space: nowrap; position: relative; z-index: 998; }
        .ticker-label { background: var(--rojo); color: #fff; font-size: 10px; font-weight: 900; letter-spacing: 2px; padding: 4px 14px; text-transform: uppercase; display: inline-block; margin-right: 20px; vertical-align: middle; }
        .ticker-track { display: inline-block; animation: ticker 35s linear infinite; }
        .ticker-track:hover { animation-play-state: paused; }
        .ticker-item { display: inline-block; font-size: 12px; font-weight: bold; color: #ccc; margin: 0 40px; vertical-align: middle; }
        .ticker-item::before { content: "◆"; color: var(--azul); margin-right: 10px; font-size: 8px; }
        @keyframes ticker { 0% { transform: translateX(0); } 100% { transform: translateX(-50%); } }
        body.modo-claro .ticker-wrap { background: #e8e8e8; }
        body.modo-claro .ticker-item { color: #333; }

        /* BOTÓN VOLVER ARRIBA */
        .btn-arriba { 
            position: fixed; 
            bottom: 100px; 
            right: 30px; 
            background: var(--azul); 
            color: #fff; 
            border: none; 
            width: 48px; 
            height: 48px; 
            border-radius: 50%; 
            font-size: 20px; 
            cursor: pointer; 
            z-index: 9998; 
            opacity: 0; 
            transform: translateY(20px); 
            transition: 0.3s; 
            box-shadow: 0 4px 15px rgba(26,115,232,0.5); 
            display: flex; 
            align-items: center; 
            justify-content: center;
            min-height: 48px;
            min-width: 48px;
        }
        .btn-arriba.visible { opacity: 1; transform: translateY(0); }
        .btn-arriba:active { transform: scale(0.95); }
        .btn-arriba:hover { background: #0d47a1; transform: translateY(-3px); }

        /* LECTOR DE VOZ */
        .btn-voz { display: inline-flex; align-items: center; gap: 6px; background: transparent; border: 1px solid #444; color: #aaa; font-size: 11px; font-weight: bold; padding: 5px 12px; border-radius: 20px; cursor: pointer; margin-top: 12px; transition: 0.3s; text-transform: uppercase; letter-spacing: 1px; }
        .btn-voz:hover, .btn-voz.leyendo { background: var(--azul); border-color: var(--azul); color: #fff; }
        body.modo-claro .btn-voz { border-color: #bbb; color: #555; }

        /* BOTÓN WHATSAPP */
        .btn-whatsapp { display: inline-flex; align-items: center; gap: 6px; background: #25d366; color: #fff; font-size: 11px; font-weight: bold; padding: 5px 12px; border-radius: 20px; cursor: pointer; margin-top: 12px; margin-left: 8px; transition: 0.3s; text-decoration: none; text-transform: uppercase; letter-spacing: 1px; border: none; }
        .btn-whatsapp:hover { background: #128c4a; color: #fff; transform: scale(1.05); }

        /* WIDGET CLIMA */
        .clima-widget { display: flex; align-items: center; gap: 6px; font-size: 11px; font-weight: bold; color: #aaa; background: #1a1a1a; border: 1px solid #333; border-radius: 20px; padding: 4px 12px; white-space: nowrap; }
        .clima-widget .clima-temp { color: #fff; font-size: 14px; font-weight: 900; }
        body.modo-claro .clima-widget { background: #e0e0e0; border-color: #bbb; color: #555; }
        body.modo-claro .clima-widget .clima-temp { color: #111; }

        /* BANNER AVISO TRANSMISIÓN EN VIVO */
        #bannerTransmision {
            display: none;
            position: fixed;
            bottom: 216px;
            right: 30px;
            z-index: 9998;
            background: linear-gradient(135deg, #1a0000, #2a0505);
            border: 1px solid #cc0000;
            border-left: 4px solid #ff2222;
            border-radius: 10px;
            padding: 12px 36px 12px 14px;
            max-width: 260px;
            box-shadow: 0 6px 24px rgba(255,0,0,0.25);
            animation: slideInBanner 0.4s ease;
        }
        #bannerTransmision.visible { display: block; }
        @keyframes slideInBanner {
            from { opacity: 0; transform: translateX(20px); }
            to   { opacity: 1; transform: translateX(0); }
        }
        .banner-trans-titulo {
            font-size: 13px;
            font-weight: 900;
            color: #fff;
            line-height: 1.3;
            margin: 0 0 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .banner-trans-sub {
            font-size: 11px;
            color: #ff9999;
            margin: 0;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .banner-trans-sub .dot {
            width: 7px; height: 7px;
            background: #ff2222;
            border-radius: 50%;
            display: inline-block;
            animation: parpadeoLive 1s infinite;
            flex-shrink: 0;
        }
        .banner-trans-cerrar {
            position: absolute;
            top: 6px; right: 8px;
            background: none;
            border: none;
            color: #666;
            font-size: 18px;
            cursor: pointer;
            line-height: 1;
            padding: 0 2px;
            transition: color 0.2s;
        }
        .banner-trans-cerrar:hover { color: #fff; }
        @media (max-width: 600px) {
            #bannerTransmision { bottom: 160px; right: 10px; max-width: 220px; }
        }

        /* BOTÓN FLOTANTE TRANSMISIÓN EN VIVO */
        .btn-transmision-live {
            position: fixed;
            bottom: 160px;
            right: 30px;
            background: linear-gradient(135deg, #cc0000, #ff2222);
            color: white;
            text-decoration: none;
            padding: 12px 22px;
            border-radius: 50px;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 6px 20px rgba(255, 0, 0, 0.45);
            z-index: 9999;
            min-height: 44px;
            transition: 0.3s;
            animation: pulsoLive 2.5s infinite;
        }
        .btn-transmision-live:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 28px rgba(255,0,0,0.65);
            color: white;
        }
        .live-dot {
            width: 10px;
            height: 10px;
            background: #fff;
            border-radius: 50%;
            display: inline-block;
            animation: parpadeoLive 1s infinite;
            flex-shrink: 0;
        }
        @keyframes parpadeoLive {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.2; }
        }
        @keyframes pulsoLive {
            0%, 100% { box-shadow: 0 6px 20px rgba(255,0,0,0.45); }
            50% { box-shadow: 0 6px 30px rgba(255,0,0,0.75); }
        }
        /* Ajuste responsivo del botón transmisión */
        @media (max-width: 600px) {
            .btn-transmision-live { bottom: 95px; right: 10px; padding: 8px 12px; font-size: 10px; }
        }
        /* MODAL TRANSMISIÓN EN VIVO (index) */
        .modal-transmision-overlay {
            display: none;
            position: fixed;
            z-index: 99990;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.88);
            backdrop-filter: blur(4px);
            align-items: center;
            justify-content: center;
        }
        .modal-transmision-overlay.show { display: flex; }
        .modal-transmision-box {
            background: #121212;
            border: 1px solid #333;
            border-top: 4px solid #ff0000;
            border-radius: 12px;
            width: 90%;
            max-width: 900px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.8);
            animation: slideDown 0.3s ease;
        }
        .modal-transmision-header {
            background: #181818;
            padding: 14px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #2a2a2a;
        }
        .modal-transmision-header h5 {
            color: #fff;
            font-weight: bold;
            margin: 0;
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .modal-transmision-close {
            background: none;
            border: none;
            color: #aaa;
            font-size: 24px;
            cursor: pointer;
            line-height: 1;
            padding: 0 4px;
        }
        .modal-transmision-close:hover { color: #fff; }
        .modal-transmision-body {
            padding: 20px;
        }
        .transmision-iframe-wrap {
            aspect-ratio: 16/9;
            background: #000;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #333;
        }
        .transmision-iframe-wrap iframe {
            width: 100%;
            height: 100%;
            border: none;
        }

        .btn-ver-mas-destacado {
            background: transparent !important;
            color: #777 !important;
            border: 1px solid #2e2e2e !important;
            padding: 4px 11px !important;
            border-radius: 3px !important;
            font-size: 10px !important;
            font-weight: 600 !important;
            letter-spacing: 0.8px;
            box-shadow: none !important;
            transition: border-color 0.2s, color 0.2s !important;
            text-transform: uppercase;
            display: inline-flex !important;
            align-items: center;
            gap: 5px;
            animation: none !important;
        }
        .btn-ver-mas-destacado:hover {
            border-color: #555 !important;
            color: #bbb !important;
            transform: none !important;
            box-shadow: none !important;
        }
        .sec-buscar-hint {
            font-size: 11px;
            color: #666;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-style: italic;
            white-space: nowrap;
        }
        .sec-buscar-hint i { color: var(--rojo); font-size: 10px; }

        /* BANNER INFO EN VISTA CATEGORÍA */
        .cat-info-banner {
            background: linear-gradient(135deg, #0d1b2e, #162032);
            border: 1px solid #1e3a5f;
            border-left: 4px solid var(--azul);
            border-radius: 8px;
            padding: 14px 20px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
        }
        .cat-info-banner .banner-icon {
            font-size: 28px;
            flex-shrink: 0;
        }
        .cat-info-banner .banner-texto {
            flex: 1;
            min-width: 200px;
        }
        .cat-info-banner .banner-texto strong {
            display: block;
            color: #fff;
            font-size: 14px;
            margin-bottom: 3px;
        }
        .cat-info-banner .banner-texto span {
            font-size: 12px;
            color: #777;
        }
        .cat-info-banner .banner-texto span b { color: var(--azul); }
        .cat-info-banner .banner-lupa {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(26,115,232,0.15);
            border: 1px solid rgba(26,115,232,0.3);
            border-radius: 20px;
            padding: 6px 14px;
            font-size: 12px;
            color: var(--azul);
            font-weight: bold;
            cursor: pointer;
            transition: 0.2s;
            text-decoration: none;
            white-space: nowrap;
        }
        .cat-info-banner .banner-lupa:hover {
            background: rgba(26,115,232,0.3);
        }
        body.modo-claro .cat-info-banner {
            background: #e8f0fe;
            border-color: #c5d8fa;
        }
        body.modo-claro .cat-info-banner .banner-texto strong { color: #111; }

            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin: 40px 0 20px;
            flex-wrap: wrap;
        }
        .paginacion a, .paginacion span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 40px;
            height: 40px;
            padding: 0 12px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: bold;
            text-decoration: none;
            transition: 0.2s;
            border: 1px solid #333;
        }
        .paginacion a {
            color: #ccc;
            background: #1e1e1e;
        }
        .paginacion a:hover {
            background: var(--azul);
            border-color: var(--azul);
            color: #fff;
        }
        .paginacion .pag-activa {
            background: var(--azul);
            border-color: var(--azul);
            color: #fff;
            cursor: default;
        }
        .paginacion .pag-dots {
            color: #555;
            border-color: transparent;
            background: transparent;
        }
        .paginacion .pag-prev, .paginacion .pag-next {
            background: #181818;
            color: var(--azul);
            border-color: var(--azul);
            gap: 6px;
        }
        .paginacion .pag-prev:hover, .paginacion .pag-next:hover {
            background: var(--azul);
            color: #fff;
        }
        .paginacion .pag-disabled {
            color: #444;
            border-color: #2a2a2a;
            background: #161616;
            cursor: not-allowed;
            pointer-events: none;
        }
        .paginacion-info {
            text-align: center;
            font-size: 12px;
            color: #555;
            margin-bottom: 16px;
        }
        body.modo-claro .paginacion a { background: #e8e8e8; border-color: #ccc; color: #333; }
        body.modo-claro .paginacion .pag-activa { background: var(--azul); color: #fff; }
        body.modo-claro .paginacion .pag-prev, body.modo-claro .paginacion .pag-next { background: #f0f0f0; }

        /* ── ALTO CONTRASTE ─────────────────────────────────────────────── */
        body.alto-contraste { background: #000 !important; color: #fff !important; filter: contrast(1.5) brightness(1.1); }
        body.alto-contraste .card, body.alto-contraste .bloque-inferior, body.alto-contraste nav { border-color: #fff !important; }
        .btn-accesibilidad { background: none; border: 1px solid #444; color: #fff; width: 34px; height: 34px; border-radius: 50%; cursor: pointer; font-size: 14px; display:flex; align-items:center; justify-content:center; transition:0.3s; flex-shrink:0; title: "Alto contraste"; }
        body.modo-claro .btn-accesibilidad { border-color: #999; color: #111; }

        /* ── TICKER LATERAL VERTICAL ────────────────────────────────────── */
        .ticker-lateral {
            position: fixed; right: 0; top: 50%; transform: translateY(-50%);
            width: 36px; height: 320px; background: #111; border: 1px solid #333;
            border-radius: 8px 0 0 8px; z-index: 9000; overflow: hidden;
            display: flex; flex-direction: column; align-items: center;
        }
        .ticker-lateral-label {
            writing-mode: vertical-rl; text-orientation: mixed;
            font-size: 9px; font-weight: 900; color: var(--rojo);
            letter-spacing: 2px; text-transform: uppercase; padding: 10px 0 6px;
            flex-shrink: 0;
        }
        .ticker-lateral-track-wrap { flex: 1; overflow: hidden; width: 100%; position: relative; }
        .ticker-lateral-track {
            display: flex; flex-direction: column; gap: 0;
            animation: tickerV 22s linear infinite;
        }
        .ticker-lateral-track:hover { animation-play-state: paused; }
        .ticker-lateral-item {
            writing-mode: vertical-rl; text-orientation: mixed;
            font-size: 10px; color: #bbb; padding: 12px 0; cursor: pointer;
            border-top: 1px solid #222; white-space: nowrap; max-height: 160px;
            overflow: hidden; text-overflow: ellipsis; transition: color 0.2s;
            flex-shrink: 0;
        }
        .ticker-lateral-item:hover { color: var(--azul); }
        @keyframes tickerV {
            0%   { transform: translateY(0); }
            100% { transform: translateY(-50%); }
        }
        @media (max-width: 768px) { .ticker-lateral { display: none; } }

        /* ── ENCUESTA SIDEBAR ───────────────────────────────────────────── */
        .encuesta-box {
            background: #111; border: 1px solid #2a2a2a; border-radius: 8px;
            padding: 18px; margin: 16px 12px;
        }
        .encuesta-box h4 { font-size: 13px; color: #fff; margin: 0 0 12px; font-family: 'Playfair Display', serif; }
        .encuesta-pregunta { font-size: 12px; color: #ccc; margin-bottom: 12px; line-height: 1.5; }
        .encuesta-opcion {
            display: flex; flex-direction: column; gap: 4px; margin-bottom: 8px; cursor: pointer;
        }
        .encuesta-opcion-label { font-size: 12px; color: #bbb; display: flex; justify-content: space-between; }
        .encuesta-barra-wrap { background: #222; border-radius: 20px; height: 6px; overflow: hidden; }
        .encuesta-barra { height: 100%; background: var(--azul); border-radius: 20px; transition: width 0.5s ease; }
        .encuesta-opcion:hover .encuesta-barra { background: var(--rojo); }
        .encuesta-voted { font-size: 11px; color: #555; margin-top: 8px; text-align: center; }
        body.modo-claro .encuesta-box { background: #f0f0f0; border-color: #ddd; }
        body.modo-claro .encuesta-pregunta, body.modo-claro .encuesta-opcion-label { color: #333; }

        /* ── FAVORITOS ──────────────────────────────────────────────────── */
        .btn-favorito {
            background: none; border: none; cursor: pointer;
            font-size: 16px; color: #555; transition: 0.2s;
            padding: 2px 4px; line-height: 1;
        }
        .btn-favorito.guardado { color: #ffcc00; }
        .btn-favorito:hover { transform: scale(1.3); }
        .favoritos-box {
            background: #111; border: 1px solid #2a2a2a; border-radius: 8px;
            padding: 14px; margin: 0 12px 16px;
        }
        .favoritos-box h4 { font-size: 12px; color: #fff; margin: 0 0 10px; text-transform: uppercase; letter-spacing: 1px; }
        .favorito-item { font-size: 11px; color: #aaa; padding: 6px 0; border-bottom: 1px solid #1e1e1e; line-height: 1.4; cursor: pointer; }
        .favorito-item:hover { color: var(--azul); }
        .favoritos-vacio { font-size: 11px; color: #444; text-align: center; padding: 10px 0; }

        /* ── TARJETAS RÁPIDAS SIDEBAR ────────────────────────────────────── */
        .sidebar-quick-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            padding: 12px 14px;
        }
        .sidebar-quick-card {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            gap: 6px; padding: 14px 8px; border-radius: 10px; border: 1px solid #2a2a2a;
            font-size: 12px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.5px;
            cursor: pointer; text-decoration: none; transition: 0.2s; position: relative;
            min-height: 70px;
        }
        .sidebar-quick-card i { font-size: 22px; }
        .sidebar-quick-card span { font-size: 11px; }
        .sidebar-quick-inicio  { background: #ffcc00; color: #000; border-color: #ffcc00; }
        .sidebar-quick-inicio:hover  { background: #ffe033; }
        .sidebar-quick-opinion { background: #1e1e1e; color: #fff; border-color: #333; }
        .sidebar-quick-opinion:hover { border-color: #ff8c00; color: #ff8c00; }
        .sidebar-quick-impreso { background: #1e1e1e; color: #fff; border-color: #333; }
        .sidebar-quick-impreso:hover { border-color: var(--rojo); color: var(--rojo); }
        .sidebar-quick-guardados { background: #1e1e1e; color: #fff; border-color: #333; }
        .sidebar-quick-guardados:hover { border-color: #ffcc00; color: #ffcc00; }
        .sidebar-quick-card .badge-cnt {
            position: absolute; top: 6px; right: 6px;
            background: var(--rojo); color: #fff; font-size: 9px; font-weight: 900;
            border-radius: 50%; width: 16px; height: 16px;
            display: inline-flex; align-items: center; justify-content: center;
        }

        /* ── BOTÓN MIS GUARDADOS EN SIDEBAR ─────────────────────────────── */
        .btn-guardados-side {
            display: flex; align-items: center; gap: 10px;
            padding: 8px 20px; width: 100%; box-sizing: border-box;
            background: none; border: none; border-bottom: 1px solid #222;
            color: #ffcc00; font-size: 12px; font-weight: 900;
            text-transform: uppercase; letter-spacing: 1px; cursor: pointer;
            transition: 0.2s; text-align: left; min-height: 32px;
        }
        .btn-guardados-side:hover { background: rgba(255,204,0,0.08); }

        /* ── BOTÓN OPINIÓN EN SIDEBAR ────────────────────────────────────── */
        .btn-opinion-side {
            display: flex; align-items: center; gap: 10px;
            padding: 8px 20px; width: 100%; box-sizing: border-box;
            background: none; border: none; border-bottom: 1px solid #222;
            color: #ff8c00; font-size: 12px; font-weight: 900;
            text-transform: uppercase; letter-spacing: 1px; cursor: pointer;
            transition: 0.2s; text-align: left; min-height: 32px;
        }
        .btn-opinion-side:hover { background: rgba(255,140,0,0.08); }

        /* ── MODAL GUARDADOS ─────────────────────────────────────────────── */
        #modalGuardados { display:none; position:fixed; z-index:20000; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.92); overflow-y:auto; }
        #modalGuardados.show { display:block; }
        .guardados-box { background:#1a1a1a; max-width:700px; margin:40px auto; border-radius:12px; border:1px solid #333; border-top:4px solid #ffcc00; overflow:hidden; }
        .guardados-header { background:#111; padding:20px 24px; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #2a2a2a; }
        .guardados-header h2 { margin:0; color:#ffcc00; font-family:'Playfair Display',serif; font-size:22px; }
        .guardados-cerrar { background:none; border:none; color:#aaa; font-size:28px; cursor:pointer; line-height:1; }
        .guardados-cerrar:hover { color:#fff; }
        .guardados-lista { padding:16px 24px; }
        .guardado-card { display:flex; align-items:flex-start; gap:12px; padding:12px 0; border-bottom:1px solid #222; cursor:pointer; transition:0.2s; }
        .guardado-card:hover { background:rgba(255,204,0,0.05); margin:0 -24px; padding:12px 24px; border-radius:4px; }
        .guardado-num { font-size:20px; font-weight:900; color:#333; font-family:'Playfair Display',serif; width:28px; flex-shrink:0; line-height:1.2; }
        .guardado-titulo { font-size:14px; color:#ddd; line-height:1.5; flex:1; }
        .guardado-titulo:hover { color:#ffcc00; }
        .guardado-eliminar { background:none; border:none; color:#444; font-size:16px; cursor:pointer; flex-shrink:0; transition:0.2s; padding:2px 4px; }
        .guardado-eliminar:hover { color:var(--rojo); transform:scale(1.2); }
        .guardados-vacio { text-align:center; padding:40px 20px; color:#444; font-size:14px; }
        .guardados-vacio div { font-size:40px; margin-bottom:12px; }
        .btn-cargar-mas { display:block; width:calc(100% - 48px); margin:16px 24px; padding:10px; background:transparent; border:1px solid #333; color:#aaa; border-radius:6px; cursor:pointer; font-size:12px; font-weight:bold; text-transform:uppercase; letter-spacing:1px; transition:0.2s; }
        .btn-cargar-mas:hover { border-color:#ffcc00; color:#ffcc00; }

        /* ── MODAL OPINIÓN / COLUMNISTAS ─────────────────────────────────── */
        #modalOpinion { display:none; position:fixed; z-index:20000; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.92); overflow-y:auto; }
        #modalOpinion.show { display:block; }
        .opinion-box { background:#1a1a1a; max-width:800px; margin:40px auto; border-radius:12px; border:1px solid #333; border-top:4px solid #ff8c00; overflow:hidden; }
        .opinion-header { background:#111; padding:20px 24px; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #2a2a2a; }
        .opinion-header h2 { margin:0; color:#ff8c00; font-family:'Playfair Display',serif; font-size:22px; }
        .opinion-cerrar { background:none; border:none; color:#aaa; font-size:28px; cursor:pointer; line-height:1; }
        .opinion-cerrar:hover { color:#fff; }
        .opinion-grid { padding:20px 24px; display:grid; grid-template-columns:1fr 1fr; gap:16px; }
        @media(max-width:600px){ .opinion-grid { grid-template-columns:1fr; } }
        .opinion-card { background:#111; border:1px solid #2a2a2a; border-radius:8px; padding:16px; display:flex; gap:14px; align-items:flex-start; cursor:pointer; transition:0.2s; }
        .opinion-card:hover { border-color:#ff8c00; background:#1a1000; }
        .opinion-avatar { width:56px; height:56px; border-radius:50%; object-fit:cover; flex-shrink:0; border:2px solid #333; }
        .opinion-info h4 { font-size:14px; color:#fff; margin:0 0 4px; font-family:'Playfair Display',serif; line-height:1.3; }
        .opinion-info p { font-size:11px; color:#ff8c00; margin:0 0 4px; font-weight:bold; }
        .opinion-info span { font-size:11px; color:#555; }
        .opinion-footer { padding:0 24px 20px; }
        .btn-cargar-mas-opinion { display:block; width:100%; padding:10px; background:transparent; border:1px solid #333; color:#aaa; border-radius:6px; cursor:pointer; font-size:12px; font-weight:bold; text-transform:uppercase; letter-spacing:1px; transition:0.2s; }
        .btn-cargar-mas-opinion:hover { border-color:#ff8c00; color:#ff8c00; }

        /* ── LO MÁS LEÍDO ───────────────────────────────────────────────── */
        .mas-leido-box { background: #111; border: 1px solid #1e1e1e; border-radius: 12px; padding: 22px 20px; }
        .mas-leido-box h3 { font-family: 'Playfair Display', serif; font-size: 13px; color: #aaa; margin: 0 0 18px; text-transform: uppercase; letter-spacing: 3px; font-weight: 400; display: flex; align-items: center; gap: 8px; }
        .mas-leido-box h3 i { font-size: 11px; color: var(--rojo); }
        .mas-leido-item { display: flex; gap: 14px; align-items: flex-start; padding: 9px 0; border-bottom: 1px solid #1e1e1e; cursor: pointer; transition: 0.15s; }
        .mas-leido-item:last-child { border-bottom: none; }
        .mas-leido-item:hover .mas-leido-titulo { color: #fff; }
        .mas-leido-num { font-size: 22px; font-weight: 900; color: #2e2e2e; font-family: 'Playfair Display', serif; line-height: 1.1; flex-shrink: 0; width: 28px; }
        .mas-leido-num.top1 { color: var(--rojo); }
        .mas-leido-num.top2 { color: #777; }
        .mas-leido-num.top3 { color: #555; }
        .mas-leido-titulo { font-size: 13px; color: #ccc; line-height: 1.5; }

        /* ── EFEMÉRIDES ─────────────────────────────────────────────────── */
        .efemerides-box { background: #111; border: 1px solid #1e1e1e; border-radius: 12px; padding: 22px 20px; }
        .efemerides-box h3 { font-family: 'Playfair Display', serif; font-size: 13px; color: #aaa; margin: 0 0 4px; text-transform: uppercase; letter-spacing: 3px; font-weight: 400; }
        .efemerides-fecha { font-size: 10px; color: #666; letter-spacing: 2px; text-transform: uppercase; margin-bottom: 18px; }
        .efemerides-item { display: flex; gap: 16px; padding: 10px 0; border-bottom: 1px solid #1e1e1e; align-items: center; }
        .efemerides-item:last-child { border-bottom: none; }
        .efemerides-anio { font-size: 17px; font-weight: 900; color: var(--azul); font-family: 'Playfair Display', serif; flex-shrink: 0; width: 44px; }
        .efemerides-texto { font-size: 13px; color: #ccc; line-height: 1.5; }

        /* ── CONTADOR REGRESIVO ──────────────────────────────────────────── */
        .contador-box { background: #111; border: 1px solid #1e1e1e; border-radius: 12px; padding: 22px 20px; text-align: center; display: flex; flex-direction: column; justify-content: center; }
        .contador-titulo { font-size: 10px; font-weight: 700; color: #888; letter-spacing: 3px; text-transform: uppercase; margin-bottom: 12px; }
        .contador-evento { font-family: 'Playfair Display', serif; font-size: 17px; color: var(--rojo); margin-bottom: 18px; line-height: 1.3; }
        .contador-digitos { display: flex; justify-content: center; align-items: flex-start; gap: 6px; }
        .contador-bloque { display: flex; flex-direction: column; align-items: center; }
        .contador-num { font-size: 36px; font-weight: 900; color: #fff; font-family: 'Playfair Display', serif; line-height: 1; }
        .contador-label { font-size: 8px; color: #666; letter-spacing: 2px; text-transform: uppercase; margin-top: 6px; }

        /* ── COMPARTIR MEJORADO ──────────────────────────────────────────── */
        .btn-telegram { display: inline-flex; align-items: center; gap: 6px; background: #2ca5e0; color: #fff; font-size: 11px; font-weight: bold; padding: 5px 12px; border-radius: 20px; cursor: pointer; margin-top: 12px; margin-left: 8px; transition: 0.3s; text-decoration: none; text-transform: uppercase; letter-spacing: 1px; border: none; }
        .btn-telegram:hover { background: #1a8fc0; color: #fff; transform: scale(1.05); }
        .btn-qr { display: inline-flex; align-items: center; gap: 6px; background: transparent; border: 1px solid #444; color: #aaa; font-size: 11px; font-weight: bold; padding: 5px 12px; border-radius: 20px; cursor: pointer; margin-top: 12px; margin-left: 8px; transition: 0.3s; text-transform: uppercase; letter-spacing: 1px; }
        .btn-qr:hover { background: #333; color: #fff; }
        .btn-ig { display: inline-flex; align-items: center; gap: 6px; background: linear-gradient(45deg,#f09433,#e6683c,#dc2743,#cc2366,#bc1888); color: #fff; font-size: 11px; font-weight: bold; padding: 5px 12px; border-radius: 20px; cursor: pointer; margin-top: 12px; margin-left: 8px; transition: 0.3s; border: none; text-transform: uppercase; letter-spacing: 1px; }
        .btn-ig:hover { opacity: 0.85; transform: scale(1.05); }

        /* Modal QR */
        #modalQR { display:none; position:fixed; z-index:99999; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.85); align-items:center; justify-content:center; }
        #modalQR.show { display:flex; }
        .qr-box { background:#1e1e1e; border-radius:12px; padding:30px; text-align:center; max-width:300px; width:90%; }
        .qr-box h4 { color:#fff; font-family:'Playfair Display',serif; margin:0 0 16px; }
        #qrCanvas { border-radius:8px; }
        .qr-box small { display:block; color:#555; font-size:11px; margin-top:10px; }
        .qr-cerrar { margin-top:16px; background:none; border:1px solid #444; color:#aaa; padding:6px 20px; border-radius:20px; cursor:pointer; font-size:12px; }

        /* Modal imagen Instagram */
        #modalIG { display:none; position:fixed; z-index:99999; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.9); align-items:center; justify-content:center; }
        #modalIG.show { display:flex; }
        .ig-box { background:#1e1e1e; border-radius:12px; padding:20px; text-align:center; max-width:420px; width:92%; }
        .ig-box h4 { color:#fff; font-family:'Playfair Display',serif; margin:0 0 12px; font-size:16px; }
        #igCanvas { border-radius:8px; max-width:100%; }
        .ig-acciones { display:flex; gap:10px; justify-content:center; margin-top:14px; flex-wrap:wrap; }
        .ig-btn-dl { background:linear-gradient(45deg,#f09433,#dc2743,#bc1888); color:#fff; border:none; padding:8px 20px; border-radius:20px; cursor:pointer; font-size:12px; font-weight:bold; }
        .ig-btn-cerrar { background:none; border:1px solid #444; color:#aaa; padding:8px 20px; border-radius:20px; cursor:pointer; font-size:12px; }


        /* ═══════════════════════════════════════════════════════════════
           ALINEACIÓN FINAL DE BOTONES FLOTANTES (evita superposición)
           Este bloque va al final a propósito: las reglas base de
           .btn-arriba / .btn-transmision-live / #bannerTransmision están
           escritas sin @media más abajo en el CSS y por eso "ganaban" en
           móvil aunque hubiera media queries antes. Aquí se fija el orden
           final: Revista (abajo) -> Arriba -> En Vivo -> Aviso (arriba).
        ═══════════════════════════════════════════════════════════════ */
        @media (max-width: 1024px) {
            .btn-transmision-live { bottom: 155px; right: 20px; }
            #bannerTransmision { bottom: 211px; right: 20px; }
        }
        @media (max-width: 768px) {
            .btn-transmision-live { bottom: 133px; right: 15px; padding: 8px 14px; font-size: 10px; }
            #bannerTransmision { bottom: 179px; right: 15px; max-width: 200px; }
        }
        @media (max-width: 650px) {
            .btn-transmision-live { bottom: 126px; right: 12px; padding: 8px 12px; font-size: 10px; }
            #bannerTransmision { bottom: 168px; right: 12px; max-width: 190px; }
        }
        @media (max-width: 480px) {
            .btn-transmision-live { bottom: 116px; right: 10px; padding: 7px 10px; font-size: 9px; gap: 6px; }
            #bannerTransmision { bottom: 154px; right: 10px; max-width: 175px; padding: 10px 30px 10px 12px; }
        }
        @media (max-width: 360px) {
            .btn-transmision-live { bottom: 104px; right: 5px; padding: 6px 9px; font-size: 8px; gap: 5px; }
            #bannerTransmision { bottom: 138px; right: 5px; max-width: 155px; }
        }

        /* ═══════════════════════════════════════════════════════════════
           REAGRUPAR FILA DE ICONOS DEL TOP-BAR EN MÓVIL
           Antes: flex-direction:column ponía CADA elemento (redes, correo,
           buscador, botón tema, botón contraste) en su propia fila entera,
           por eso los botones circulares se veían regados y feos.
           Ahora: redes+correo en una fila, buscador ocupa su propia fila
           completa, y los 2 botones circulares quedan juntos como en PC.
        ═══════════════════════════════════════════════════════════════ */
        @media (max-width: 768px) {
            .top-bar > div:last-child {
                display: flex;
                flex-direction: row;
                flex-wrap: wrap;
                align-items: center;
                justify-content: center;
                gap: 10px;
            }
            .top-bar > div:last-child .social-icons,
            .top-bar > div:last-child a[href^="mailto"],
            .top-bar > div:last-child .btn-tema,
            .top-bar > div:last-child .btn-accesibilidad {
                flex: 0 0 auto;
            }
            .top-bar > div:last-child .search-box {
                flex: 1 1 100%;
                order: 3;
                width: 100%;
            }
        }
    </style>
</head>
<body>

<!-- MODAL BIENVENIDA -->
<script>
function cerrarBienvenida() {
    var m = document.getElementById('modalBienvenida');
    if (m) { m.classList.remove('show'); m.style.display = 'none'; }
    document.body.style.overflow = '';
    try { sessionStorage.setItem('plec-bienvenida-vista', '1'); } catch(e){}
}
</script>
<div id="modalBienvenida">
    <div class="bienvenida-box">
        <div class="bienvenida-logo">Noticias <span>PLEC</span></div>
        <div class="bienvenida-slogan">#SomosTodos | Noticiero Independiente</div>
        <div class="bienvenida-linea"></div>
        <div class="bienvenida-titular" id="bienvenidaTitular">La verdad no espera.<br>Bienvenido al periodismo libre.</div>
        <p class="bienvenida-texto">Desde el corazón del Tolima, te traemos las noticias que importan. Sin filtros, sin censura, con la pasión de quienes creen en la información como herramienta de cambio.</p>
        <div class="bienvenida-fecha">
            📅 <?php
                $dias = array("Domingo","Lunes","Martes","Miércoles","Jueves","Viernes","Sábado");
                $meses = array("Enero","Febrero","Marzo","Abril","Mayo","Junio","Julio","Agosto","Septiembre","Octubre","Noviembre","Diciembre");
                echo $dias[date('w')].", ".date('d')." de ".$meses[date('n')-1]." de ".date('Y');
            ?> — Edición Digital
        </div>
        <button class="bienvenida-btn" onclick="cerrarBienvenida()">📰 Leer las noticias</button>
        <div class="bienvenida-puntos">
            <span class="activo"></span><span></span><span></span>
        </div>
    </div>
</div>

<button class="menu-toggle" onclick="abrirNav()"><i class="fas fa-bars"></i></button>

<div id="sidebarMenu" class="sidebar">
    <div style="display:flex; align-items:center; justify-content:space-between; padding:12px 16px; border-bottom:1px solid #222;">
        <img src="img/logo-plec.jpg" style="width:55px; border-radius:4px;">
        <a href="javascript:void(0)" onclick="cerrarNav()" style="color:#777; font-size:28px; text-decoration:none; line-height:1;">&times;</a>
    </div>
    <!-- TARJETAS RÁPIDAS -->
    <div class="sidebar-quick-grid">
        <a href="index.php" class="sidebar-quick-card sidebar-quick-inicio">
            <i class="fas fa-home"></i>
            <span>Inicio</span>
        </a>
        <button class="sidebar-quick-card sidebar-quick-opinion" onclick="abrirOpinion()">
            <i class="fas fa-quote-right"></i>
            <span>Opinión</span>
        </button>
        <a href="impresos.php" class="sidebar-quick-card sidebar-quick-impreso">
            <i class="fas fa-newspaper"></i>
            <span>Impreso</span>
        </a>
        <button class="sidebar-quick-card sidebar-quick-guardados" onclick="abrirGuardados()">
            <i class="fas fa-star"></i>
            <span>Guardados</span>
            <span class="badge-cnt" id="badgeGuardadosCnt" style="display:none;">0</span>
        </button>
    </div>

    <!-- BUSCADOR INTERNO -->
    <div style="padding:10px 14px;">
        <form action="index.php" method="GET" style="display:flex; background:#1a1a1a; border:1px solid #333; border-radius:8px; overflow:hidden;">
            <input type="text" name="s" placeholder="Buscar noticias..." value="<?php echo htmlspecialchars($busqueda); ?>" style="flex:1; border:none; background:transparent; color:#ccc; padding:9px 12px; font-size:12px; outline:none;">
            <button type="submit" style="background:var(--rojo); border:none; color:#fff; padding:0 14px; cursor:pointer; font-size:14px;"><i class="fas fa-search"></i></button>
        </form>
    </div>

    <div class="sidebar-content">
        <div style="padding:8px 14px 4px; font-size:10px; font-weight:900; color:#555; text-transform:uppercase; letter-spacing:2px;">Categorías principales</div>
        <?php
        if (isset($conn)) {
            $res_cat_side = mysqli_query($conn, "SELECT * FROM categorias WHERE nombre NOT IN ('Opinión','Opinion') ORDER BY nombre ASC");
            if($res_cat_side) {
                while($c = mysqli_fetch_assoc($res_cat_side)) {
                    echo "<a href='index.php?cat=".$c['id']."'><i class='fas fa-chevron-right' style='color:var(--rojo);font-size:9px;margin-right:8px;'></i>".htmlspecialchars($c['nombre'])."</a>";
                }
                mysqli_free_result($res_cat_side);
            }
        }
        ?>
    </div>
</div>
</div>

<!-- MODAL MIS GUARDADOS -->
<div id="modalGuardados">
    <div class="guardados-box">
        <div class="guardados-header">
            <h2>⭐ Mis Guardados</h2>
            <button class="guardados-cerrar" onclick="cerrarGuardados()">&times;</button>
        </div>
        <div class="guardados-lista" id="guardadosLista">
            <div class="guardados-vacio"><div>⭐</div>Aún no has guardado noticias.<br><small style="color:#555;">Abre una noticia y toca ★ Guardar.</small></div>
        </div>
        <button class="btn-cargar-mas" id="btnCargarMasGuardados" style="display:none;" onclick="cargarMasGuardados()">
            ⬇ Cargar más
        </button>
    </div>
</div>

<!-- MODAL OPINIÓN / COLUMNISTAS -->
<div id="modalOpinion">
    <div class="opinion-box">
        <div class="opinion-header">
            <h2>✍️ Opinión</h2>
            <button class="opinion-cerrar" onclick="cerrarOpinion()">&times;</button>
        </div>
        <div class="opinion-grid" id="opinionGrid">
            <div style="grid-column:1/-1; text-align:center; padding:30px; color:#555;">Cargando columnistas...</div>
        </div>
        <div class="opinion-footer">
            <button class="btn-cargar-mas-opinion" id="btnCargarMasOpinion" style="display:none;" onclick="cargarMasOpinion()">
                ⬇ Cargar más
            </button>
        </div>
    </div>
</div>

<!-- MODAL QR -->
<div id="modalQR">
    <div class="qr-box">
        <h4>📲 Escanea para compartir</h4>
        <canvas id="qrCanvas" width="200" height="200"></canvas>
        <small id="qrUrl"></small>
        <button class="qr-cerrar" onclick="cerrarQR()">Cerrar</button>
    </div>
</div>

<!-- MODAL IMAGEN INSTAGRAM -->
<div id="modalIG">
    <div class="ig-box">
        <h4>📸 Imagen para Historia</h4>
        <canvas id="igCanvas" width="380" height="380"></canvas>
        <div class="ig-acciones">
            <button class="ig-btn-dl" onclick="descargarIG()">⬇️ Descargar</button>
            <button class="ig-btn-cerrar" onclick="cerrarIG()">Cerrar</button>
        </div>
    </div>
</div>

<!-- BANNER AVISO TRANSMISIÓN EN VIVO -->
<div id="bannerTransmision">
    <button class="banner-trans-cerrar" onclick="cerrarBannerTransmision()" title="Cerrar aviso">&times;</button>
    <p class="banner-trans-titulo" id="bannerTransTitulo">Transmisión en vivo</p>
    <p class="banner-trans-sub">
        <span class="dot"></span> Toca el botón rojo para ver
    </p>
</div>

<!-- BOTÓN FLOTANTE TRANSMISIÓN EN VIVO (se muestra solo si hay una activa desde el admin) -->
<a href="javascript:void(0)" class="btn-transmision-live" id="btnTransmisionLive" onclick="abrirTransmisionLive()" title="Ver transmisión en vivo" style="display:none;">
    <span class="live-dot"></span>
    <div style="display:flex; flex-direction:column; line-height:1;">
        <small style="font-size:9px; font-weight:bold; letter-spacing:1px;">EN VIVO</small>
        <b style="font-size:14px;">TRANSMISIÓN</b>
    </div>
    <i class="fas fa-broadcast-tower"></i>
</a>

<a href="revista.php" class="btn-revista-flotante">
    <i class="fas fa-star" style="font-size:11px;"></i> Edición Especial
</a>

<div class="top-bar">
    <div style="display:flex; align-items:center; gap:15px;">
        <img src="img/logo-plec.jpg" style="height:35px; border-radius:3px;">
        <span style="font-size:11px; font-weight:bold; color:#777; text-transform:uppercase;">
            <?php
            $dias = array("Domingo","Lunes","Martes","Miércoles","Jueves","Viernes","Sábado");
            $meses = array("Enero","Febrero","Marzo","Abril","Mayo","Junio","Julio","Agosto","Septiembre","Octubre","Noviembre","Diciembre");
            echo $dias[date('w')].", ".date('d')." ".$meses[date('n')-1]." ".date('Y');
            ?>
        </span>
        <div class="clima-widget" id="climaWidget">
            <span id="climaIcono">🌤️</span>
            <span class="clima-temp" id="climaTemp">--°C</span>
            <span id="climaDesc" style="font-size:10px;">Ibagué</span>
        </div>
    </div>
    <div style="display:flex; align-items:center; gap:20px;">
        <div class="social-icons">
            <a href="https://www.facebook.com/NoticiasPLEC" target="_blank" title="Facebook"><i class="fab fa-facebook-f"></i></a>
            <a href="https://www.instagram.com/noticiaspleccolombia" target="_blank" title="Instagram"><i class="fab fa-instagram"></i></a>
            <a href="https://wa.me/573103322482" target="_blank" title="WhatsApp"><i class="fab fa-whatsapp"></i></a>
        </div>
        <a href="mailto:noticiasplec@gmail.com" title="Correo electrónico" style="display:flex; align-items:center; gap:6px; color:#aaa; text-decoration:none; font-size:11px; font-weight:bold; white-space:nowrap; transition:color 0.2s;" onmouseover="this.style.color='#1a73e8'" onmouseout="this.style.color='#aaa'">
            <i class="fas fa-envelope" style="font-size:14px;"></i> noticiasplec@gmail.com
        </a>
        <div class="search-box">
            <form action="index.php" method="GET" style="display:flex; align-items:stretch;">
                <select name="cat" class="search-cat-select">
                    <option value="0" <?php echo ($categoria_id == 0) ? 'selected' : ''; ?>>Todas</option>
                    <option value="6"  <?php echo ($categoria_id == 6)  ? 'selected' : ''; ?>>Colombia</option>
                    <option value="7"  <?php echo ($categoria_id == 7)  ? 'selected' : ''; ?>>Política</option>
                    <option value="4"  <?php echo ($categoria_id == 4)  ? 'selected' : ''; ?>>Salud</option>
                    <option value="9"  <?php echo ($categoria_id == 9)  ? 'selected' : ''; ?>>Internacional</option>
                    <option value="3"  <?php echo ($categoria_id == 3)  ? 'selected' : ''; ?>>Educación</option>
                    <option value="14" <?php echo ($categoria_id == 14) ? 'selected' : ''; ?>>Farándula</option>
                    <option value="16" <?php echo ($categoria_id == 16) ? 'selected' : ''; ?>>Inclusión</option>
                </select>
                <input type="text" name="s" placeholder="BUSCAR NOTICIA..." value="<?php echo htmlspecialchars($busqueda); ?>">
                <button type="submit"><i class="fas fa-search"></i></button>
            </form>
        </div>
        <button class="btn-tema" onclick="toggleTema()" title="Cambiar tema" id="btnTema">☀️</button>
        <button class="btn-accesibilidad" onclick="toggleAltoContraste()" title="Alto contraste" id="btnContraste">◑</button>
    </div>
</div>

<header>
    <a href="index.php" style="text-decoration:none;"><h1 class="logo-text">Noticias <span>PLEC</span></h1></a>
    <div class="slogan">#SOMOSTODOS | NOTICIERO INDEPENDIENTE</div>
</header>

<nav style="position:relative;">
    <div class="nav-ultimahora-wrap">
        <span class="nav-ultimahora"><i class="fas fa-bolt"></i> ÚLTIMA HORA</span>
    </div>
    <ul class="nav-container">
        <li><a href="index.php">INICIO</a></li>
        <li><a href="index.php?cat=6">COLOMBIA</a></li>
        <li><a href="index.php?cat=7">POLÍTICA</a></li>
        <li><a href="index.php?cat=4">SALUD</a></li>
        <li><a href="index.php?cat=9">INTERNACIONAL</a></li>
        <li><a href="index.php?cat=3">EDUCACIÓN</a></li>
        <li><a href="index.php?cat=14" class="nav-farandula">FARÁNDULA</a></li>
        <li><a href="index.php?cat=16" class="nav-inclusion">INCLUSIÓN</a></li>
    </ul>
</nav>

<!-- TICKER DE NOTICIAS -->
<div class="ticker-wrap">
    <div class="ticker-track" id="tickerTrack">
        <?php
        if(isset($conn)) {
            $res_tick = mysqli_query($conn, "SELECT n.titulo, c.nombre as cat FROM noticias n LEFT JOIN categorias c ON n.categoria_id = c.id ORDER BY n.id DESC LIMIT 10");
            $titulos_tick = [];
            if($res_tick) {
                while($t = mysqli_fetch_assoc($res_tick)) {
                    $titulos_tick[] = '<span class="ticker-item">' . htmlspecialchars(mb_substr($t['titulo'],0,80)) . '</span>';
                }
                mysqli_free_result($res_tick);
            }
            if(empty($titulos_tick)) {
                $titulos_tick[] = '<span class="ticker-item">Bienvenido a Noticias PLEC — El noticiero independiente del Tolima</span>';
            }
            $doble = implode('', $titulos_tick) . implode('', $titulos_tick);
            echo $doble;
        }
        ?>
    </div>
</div>

    <?php
    function obtenerRutaImagen($img_db) {
        if (empty($img_db)) return "img/placeholder.svg";
        return (strpos($img_db, 'img/') === 0) ? $img_db : "img/" . $img_db;
    }

    // ── FUNCIÓN PARA RENDERIZAR PAGINACIÓN ──
    function renderPaginacion($pagina_actual, $total_paginas, $params_base) {
        if ($total_paginas <= 1) return;
        $qs = http_build_query(array_filter($params_base, fn($v) => $v !== '' && $v !== 0 && $v !== null));
        $base = 'index.php' . ($qs ? '?' . $qs . '&' : '?');
        $anchor = '#resultados';
        echo "<div class='paginacion-info'>Página {$pagina_actual} de {$total_paginas}</div>";
        echo "<div class='paginacion'>";
        // Anterior
        if ($pagina_actual > 1) {
            echo "<a href='{$base}p=" . ($pagina_actual - 1) . "{$anchor}' class='pag-prev'><i class='fas fa-chevron-left'></i> Anterior</a>";
        } else {
            echo "<span class='pag-prev pag-disabled'><i class='fas fa-chevron-left'></i> Anterior</span>";
        }
        // Páginas numeradas
        $rango = 2;
        for ($i = 1; $i <= $total_paginas; $i++) {
            if ($i === 1 || $i === $total_paginas || abs($i - $pagina_actual) <= $rango) {
                if ($i === $pagina_actual) {
                    echo "<span class='pag-activa'>{$i}</span>";
                } else {
                    echo "<a href='{$base}p={$i}{$anchor}'>{$i}</a>";
                }
            } elseif (abs($i - $pagina_actual) === $rango + 1) {
                echo "<span class='pag-dots'>…</span>";
            }
        }
        // Siguiente
        if ($pagina_actual < $total_paginas) {
            echo "<a href='{$base}p=" . ($pagina_actual + 1) . "{$anchor}' class='pag-next'>Siguiente <i class='fas fa-chevron-right'></i></a>";
        } else {
            echo "<span class='pag-next pag-disabled'>Siguiente <i class='fas fa-chevron-right'></i></span>";
        }
        echo "</div>";
    }

    if(isset($conn)) {
        if($esta_filtrando) {
            echo '<a id="resultados"></a>';
            // Título de resultados con contexto
            if (!empty($busqueda) && $categoria_id > 0) {
                $res_cat_nombre = mysqli_query($conn, "SELECT nombre FROM categorias WHERE id = " . intval($categoria_id) . " LIMIT 1");
                $nom_cat_busq = ($res_cat_nombre && $r = mysqli_fetch_assoc($res_cat_nombre)) ? htmlspecialchars($r['nombre']) : '';
                echo "<h2 class='seccion-titulo'>Resultados para \"" . htmlspecialchars($busqueda) . "\" en " . $nom_cat_busq . "</h2>";
            } elseif (!empty($busqueda)) {
                echo "<h2 class='seccion-titulo'>Resultados para \"" . htmlspecialchars($busqueda) . "\"</h2>";
            } else {
                $res_cat_nombre = mysqli_query($conn, "SELECT nombre FROM categorias WHERE id = " . intval($categoria_id) . " LIMIT 1");
                $nom_cat_busq = ($res_cat_nombre && $r = mysqli_fetch_assoc($res_cat_nombre)) ? htmlspecialchars($r['nombre']) : '';
                echo "<h2 class='seccion-titulo'>Categoría: " . $nom_cat_busq . "</h2>";

                // Banner informativo: 20 recientes + buscar el resto
                $res_total_cat = mysqli_query($conn, "SELECT COUNT(*) as total FROM noticias WHERE categoria_id = " . intval($categoria_id));
                $total_en_cat = ($res_total_cat) ? mysqli_fetch_assoc($res_total_cat)['total'] : 0;
                if ($total_en_cat > 50) {
                    $resto = $total_en_cat - 50;
                    echo "<div class='cat-info-banner'>";
                    echo "  <div class='banner-icon'>📰</div>";
                    echo "  <div class='banner-texto'>";
                    echo "    <strong>Mostrando las 50 noticias más recientes de <em>{$nom_cat_busq}</em></strong>";
                    echo "    <span>Hay <b>{$total_en_cat}</b> noticias en total. Usa la <b>🔍 lupa</b> para encontrar las {$resto} noticias anteriores.</span>";
                    echo "  </div>";
                    echo "  <a href='#' class='banner-lupa' onclick=\"document.querySelector('.search-box input').focus(); document.querySelector('.search-cat-select').value='{$categoria_id}'; window.scrollTo({top:0,behavior:'smooth'}); return false;\">";
                    echo "    <i class='fas fa-search'></i> Buscar en {$nom_cat_busq}";
                    echo "  </a>";
                    echo "</div>";
                }
            }
            echo "<div class='grid-busqueda'>";
            
            // Búsqueda con RELEVANCIA: título coincide = peso alto, contenido = peso bajo
            if ($categoria_id > 0 && !empty($busqueda)) {
                $like = "%$busqueda%";
                // Contar total para paginación
                $stmt_count = mysqli_prepare($conn, "SELECT COUNT(*) as total FROM noticias n WHERE n.categoria_id = ? AND (n.titulo LIKE ? OR n.contenido LIKE ?)");
                mysqli_stmt_bind_param($stmt_count, "iss", $categoria_id, $like, $like);
                mysqli_stmt_execute($stmt_count);
                $r_count = mysqli_stmt_get_result($stmt_count);
                $total_resultados = mysqli_fetch_assoc($r_count)['total'];
                mysqli_stmt_close($stmt_count);

                $stmt = mysqli_prepare($conn, "SELECT n.*, c.nombre as cat_nombre,
                    (CASE WHEN n.titulo LIKE ? THEN 10 ELSE 0 END +
                     CASE WHEN n.contenido LIKE ? THEN 3 ELSE 0 END) AS relevancia
                    FROM noticias n LEFT JOIN categorias c ON n.categoria_id = c.id
                    WHERE n.categoria_id = ? AND (n.titulo LIKE ? OR n.contenido LIKE ?)
                    ORDER BY relevancia DESC, n.id DESC LIMIT ? OFFSET ?");
                mysqli_stmt_bind_param($stmt, "ssssii", $like, $like, $categoria_id, $like, $like, $por_pagina, $offset);
            } elseif ($categoria_id > 0) {
                // Contar total
                $stmt_count = mysqli_prepare($conn, "SELECT COUNT(*) as total FROM noticias n WHERE n.categoria_id = ?");
                mysqli_stmt_bind_param($stmt_count, "i", $categoria_id);
                mysqli_stmt_execute($stmt_count);
                $r_count = mysqli_stmt_get_result($stmt_count);
                $total_resultados = mysqli_fetch_assoc($r_count)['total'];
                mysqli_stmt_close($stmt_count);

                $stmt = mysqli_prepare($conn, "SELECT n.*, c.nombre as cat_nombre, 1 AS relevancia
                    FROM noticias n LEFT JOIN categorias c ON n.categoria_id = c.id
                    WHERE n.categoria_id = ? ORDER BY n.id DESC LIMIT ? OFFSET ?");
                mysqli_stmt_bind_param($stmt, "iii", $categoria_id, $por_pagina, $offset);
            } else {
                $like = "%$busqueda%";
                // Contar total
                $stmt_count = mysqli_prepare($conn, "SELECT COUNT(*) as total FROM noticias n WHERE (n.titulo LIKE ? OR n.contenido LIKE ?)");
                mysqli_stmt_bind_param($stmt_count, "ss", $like, $like);
                mysqli_stmt_execute($stmt_count);
                $r_count = mysqli_stmt_get_result($stmt_count);
                $total_resultados = mysqli_fetch_assoc($r_count)['total'];
                mysqli_stmt_close($stmt_count);

                $stmt = mysqli_prepare($conn, "SELECT n.*, c.nombre as cat_nombre,
                    (CASE WHEN n.titulo LIKE ? THEN 10 ELSE 0 END +
                     CASE WHEN n.contenido LIKE ? THEN 3 ELSE 0 END) AS relevancia
                    FROM noticias n LEFT JOIN categorias c ON n.categoria_id = c.id
                    WHERE (n.titulo LIKE ? OR n.contenido LIKE ?)
                    ORDER BY relevancia DESC, n.id DESC LIMIT ? OFFSET ?");
                mysqli_stmt_bind_param($stmt, "ssssii", $like, $like, $like, $like, $por_pagina, $offset);
            }
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $total_paginas = (int) ceil($total_resultados / $por_pagina);
            $noticias_pagina = 0;
            if($res) {
                while($row = mysqli_fetch_assoc($res)) {
                    $noticias_pagina++;
                    $img = obtenerRutaImagen($row['imagen']);
                    // Resaltar término buscado en el resumen
                    $contenido_plano = strip_tags($row['contenido']);
                    if (!empty($busqueda)) {
                        $pos = stripos($contenido_plano, $busqueda);
                        if ($pos !== false) {
                            $inicio = max(0, $pos - 40);
                            $txt_res = ($inicio > 0 ? '...' : '') . substr($contenido_plano, $inicio, 160) . '...';
                        } else {
                            $txt_res = substr($contenido_plano, 0, 130) . '...';
                        }
                        // Resaltar en título y resumen
                        $titulo_hl = preg_replace('/(' . preg_quote(htmlspecialchars($busqueda), '/') . ')/i', '<mark style="background:#ffcc00;color:#000;border-radius:2px;padding:0 2px;">$1</mark>', htmlspecialchars($row['titulo']));
                        $resumen_hl = preg_replace('/(' . preg_quote(htmlspecialchars($busqueda), '/') . ')/i', '<mark style="background:#ffcc00;color:#000;border-radius:2px;padding:0 2px;">$1</mark>', htmlspecialchars($txt_res));
                    } else {
                        $txt_res = substr($contenido_plano, 0, 130) . '...';
                        $titulo_hl = htmlspecialchars($row['titulo']);
                        $resumen_hl = htmlspecialchars($txt_res);
                    }
                    $es_inclusion = (isset($row['categoria_id']) && $row['categoria_id'] == 16);
                    $cls_inc = $es_inclusion ? ' inclusion' : '';
                    ?>
                    <div class='card' onclick="abrirModal(<?php echo htmlspecialchars(json_encode($row['titulo']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($img), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($row['contenido']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($row['video_url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>)">
                        <div class='img-box' style="height:180px;"><img src='<?php echo $img; ?>' loading="lazy"></div>
                        <div class='info-card'>
                            <span class='cat-tag<?php echo $cls_inc; ?>'><?php echo isset($row['cat_nombre']) ? htmlspecialchars($row['cat_nombre']) : 'General'; ?></span>
                            <h4><?php echo $titulo_hl; ?></h4>
                            <p class='resumen-text' style="font-size:13px;"><?php echo $resumen_hl; ?></p>
                        </div>
                    </div>
                    <?php
                }
                mysqli_free_result($res);
            }
            if ($noticias_pagina === 0 && $pagina_actual === 1) {
                echo "<div style='grid-column:1/-1; text-align:center; padding:60px 20px; color:#555;'>
                    <div style='font-size:48px; margin-bottom:15px;'>🔍</div>
                    <h3 style='color:#777; font-family:Playfair Display,serif;'>No se encontraron noticias</h3>
                    <p style='font-size:14px;'>Intenta con otras palabras clave o cambia la categoría.</p>
                    <a href='index.php' style='color:var(--azul); font-size:13px;'>← Volver al inicio</a>
                </div>";
            } else {
                echo "<div style='grid-column:1/-1; font-size:12px; color:#555; margin-bottom:10px;'>" . $total_resultados . " resultado" . ($total_resultados != 1 ? 's' : '') . " encontrado" . ($total_resultados != 1 ? 's' : '') . " — mostrando página {$pagina_actual} de {$total_paginas}</div>";
            }
            echo "</div>"; // .grid-busqueda

            // Paginación
            $params_pag = [];
            if ($categoria_id > 0) $params_pag['cat'] = $categoria_id;
            if (!empty($busqueda))  $params_pag['s']   = $busqueda;
            renderPaginacion($pagina_actual, $total_paginas, $params_pag);

            // Si es vista de categoría (sin búsqueda) y hay más de 20 noticias, recordar la lupa
            if ($categoria_id > 0 && empty($busqueda) && $total_resultados > 20) {
                echo "<div style='text-align:center; margin:10px 0 30px; padding:14px 20px; background:#0d0d0d; border:1px dashed #2a2a2a; border-radius:8px; font-size:13px; color:#555;'>";
                echo "<i class='fas fa-lightbulb' style='color:#ffcc00; margin-right:6px;'></i>";
                echo "¿No encontraste lo que buscas? Usa la <strong style='color:#aaa;'><i class='fas fa-search'></i> lupa</strong> arriba para buscar entre <strong style='color:var(--azul);'>{$total_resultados} noticias</strong> de esta sección.";
                echo "</div>";
            }

        } else { 
            // 1. BLOQUE PRINCIPAL SUPERIOR BLINDADO
            $sql_top = "SELECT n.*, c.nombre as cat_nombre, TIMESTAMPDIFF(HOUR, n.fecha_publicacion, NOW()) as horas_publicada FROM noticias n LEFT JOIN categorias c ON n.categoria_id = c.id WHERE n.categoria_id NOT IN (13, 15) AND n.es_especial = 0 ORDER BY n.fecha_publicacion DESC LIMIT 5";
            $res_top = mysqli_query($conn, $sql_top);
            
            $noticias_top = [];
            if($res_top) {
                while($r = mysqli_fetch_assoc($res_top)) { $noticias_top[] = $r; }
                mysqli_free_result($res_top);
            }

            if(count($noticias_top) > 0) {
                ?>
                <div class="bloque-principal">
                    <div class="principal-laterales">
                        <?php for($i = 1; $i <= 2; $i++): if(isset($noticias_top[$i])): 
                            $img = obtenerRutaImagen($noticias_top[$i]['imagen']);
                            $txt_res = substr(strip_tags($noticias_top[$i]['contenido']), 0, 110) . "...";
                        ?>
                            <div class='card' onclick="abrirModal(<?php echo htmlspecialchars(json_encode($noticias_top[$i]['titulo']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($img), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($noticias_top[$i]['contenido']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($noticias_top[$i]['video_url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>)">
                                <div class='img-box'><img src='<?php echo $img; ?>'></div>
                                <div class='info-card'>
                                    <span class='cat-tag'><?php echo htmlspecialchars($noticias_top[$i]['cat_nombre']); ?></span>
                                    <h3><?php echo htmlspecialchars($noticias_top[$i]['titulo']); ?></h3>
                                    <p class='resumen-text'><?php echo htmlspecialchars($txt_res); ?></p>
                                </div>
                            </div>
                        <?php endif; endfor; ?>
                    </div>

                    <div class="principal-centro">
                        <?php 
                        if(isset($noticias_top[0])):
                            $img_c = obtenerRutaImagen($noticias_top[0]['imagen']);
                            $txt_res_c = substr(strip_tags($noticias_top[0]['contenido']), 0, 220) . "...";
                            $es_nuevo_c = (intval($noticias_top[0]['horas_publicada']) <= 48);
                        ?>
                        <div class='card' onclick="abrirModal(<?php echo htmlspecialchars(json_encode($noticias_top[0]['titulo']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($img_c), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($noticias_top[0]['contenido']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($noticias_top[0]['video_url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>)">
                            <div class='img-box' style="position:relative;">
                                <img src='<?php echo $img_c; ?>'>
                                <?php if($es_nuevo_c): ?><span class='badge-nuevo badge-nuevo-lg'><i class="fas fa-bolt"></i> NUEVO</span><?php endif; ?>
                            </div>
                            <div class='info-card'>
                                <span class='cat-tag'><?php echo htmlspecialchars($noticias_top[0]['cat_nombre']); ?></span>
                                <h2><?php echo htmlspecialchars($noticias_top[0]['titulo']); ?></h2>
                                <p class='resumen-text' style="font-size:15px;"><?php echo htmlspecialchars($txt_res_c); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="principal-laterales">
                        <?php for($i = 3; $i <= 4; $i++): if(isset($noticias_top[$i])): 
                            $img = obtenerRutaImagen($noticias_top[$i]['imagen']);
                            $txt_res = substr(strip_tags($noticias_top[$i]['contenido']), 0, 110) . "...";
                            $es_nuevo_l = (intval($noticias_top[$i]['horas_publicada']) <= 48);
                        ?>
                            <div class='card' onclick="abrirModal(<?php echo htmlspecialchars(json_encode($noticias_top[$i]['titulo']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($img), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($noticias_top[$i]['contenido']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($noticias_top[$i]['video_url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>)">
                                <div class='img-box' style="position:relative;">
                                    <img src='<?php echo $img; ?>'>
                                    <?php if($es_nuevo_l): ?><span class='badge-nuevo'><i class="fas fa-bolt"></i> NUEVO</span><?php endif; ?>
                                </div>
                                <div class='info-card'>
                                    <span class='cat-tag'><?php echo htmlspecialchars($noticias_top[$i]['cat_nombre']); ?></span>
                                    <h3><?php echo htmlspecialchars($noticias_top[$i]['titulo']); ?></h3>
                                    <p class='resumen-text'><?php echo htmlspecialchars($txt_res); ?></p>
                                </div>
                            </div>
                        <?php endif; endfor; ?>
                    </div>
                </div>
                <?php
            }

            // 2. FILAS HORIZONTALES PARA CATEGORÍAS
            $secciones_home = array(
                array("id" => 6,  "nombre" => "Colombia"),
                array("id" => 7,  "nombre" => "Política"),
                array("id" => 4,  "nombre" => "Salud"),
                array("id" => 9,  "nombre" => "Internacional"),
                array("id" => 3,  "nombre" => "Educación"),
                array("id" => 14, "nombre" => "Farándula"),
                array("id" => 16, "nombre" => "Inclusión")
            );

            // Límite de horas para badge NUEVO
            $horas_nuevo = 48;

            foreach($secciones_home as $sec) {
                $id_cat = $sec['id'];

                // Total de noticias en esta categoría (para el contador y "Ver más")
                $res_count = mysqli_query($conn, "SELECT COUNT(*) as total FROM noticias WHERE categoria_id = $id_cat AND es_especial = 0");
                $total_cat = ($res_count) ? mysqli_fetch_assoc($res_count)['total'] : 0;
                if($res_count) mysqli_free_result($res_count);

                // Siempre las 4 MÁS RECIENTES por fecha_publicacion
                $sql_sec = "SELECT n.*, c.nombre as cat_nombre,
                    TIMESTAMPDIFF(HOUR, n.fecha_publicacion, NOW()) as horas_publicada
                    FROM noticias n
                    LEFT JOIN categorias c ON n.categoria_id = c.id
                    WHERE n.categoria_id = $id_cat AND n.es_especial = 0
                    ORDER BY n.fecha_publicacion DESC
                    LIMIT 4";
                $res_sec = mysqli_query($conn, $sql_sec);

                if($res_sec && mysqli_num_rows($res_sec) > 0) {
                    echo "<div class='seccion-bloque'>";
                    $clase_extra = ($id_cat == 16) ? ' inclusion' : '';
                    $icono_inclusion = ($id_cat == 16) ? ' <span class="inclusion-icons"><i class="fas fa-universal-access"></i><i class="fas fa-heart"></i><i class="fas fa-hands-helping"></i></span>' : '';

                    // Cabecera de sección con contador y "Ver más"
                    echo "<div class='seccion-header'>";
                    echo "<div style='display:flex; align-items:center; gap:12px; flex-wrap:wrap;'>";
                    echo "  <h3 class='seccion-titulo{$clase_extra}' style='margin:0;'>".htmlspecialchars($sec['nombre']).$icono_inclusion."</h3>";
                    if ($total_cat > 4) {
                        echo "  <span class='sec-contador'>{$total_cat} noticias</span>";
                    }
                    echo "</div>";
                    if ($total_cat > 4) {
                        echo "<div style='display:flex;align-items:center;gap:10px;flex-wrap:wrap;'>";
                        echo "<a href='index.php?cat={$id_cat}' class='btn-ver-mas btn-ver-mas-destacado'><i class='fas fa-th-list'></i> Ver todo el contenido reciente <i class='fas fa-arrow-right'></i></a>";
                        if ($total_cat > 50) {
                            echo "<span class='sec-buscar-hint'><i class='fas fa-search'></i> +".($total_cat - 50)." más disponibles por búsqueda</span>";
                        }
                        echo "</div>";
                    }
                    echo "</div>";

                    echo "<div class='seccion-grid'>";
                    while($row_sec = mysqli_fetch_assoc($res_sec)) {
                        $img = obtenerRutaImagen($row_sec['imagen']);
                        $txt_res = substr(strip_tags($row_sec['contenido']), 0, 100) . "...";
                        $es_nuevo = (intval($row_sec['horas_publicada']) <= $horas_nuevo);
                        $cls_inc = ($id_cat == 16) ? ' inclusion' : '';
                        ?>
                        <div class='card card-seccion' onclick="abrirModal(<?php echo htmlspecialchars(json_encode($row_sec['titulo']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($img), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($row_sec['contenido']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($row_sec['video_url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>)">
                            <div class='img-box' style="position:relative;">
                                <img src='<?php echo $img; ?>' loading="lazy">
                                <?php if($es_nuevo): ?>
                                <span class='badge-nuevo'><i class="fas fa-bolt"></i> NUEVO</span>
                                <?php endif; ?>
                            </div>
                            <div class='info-card'>
                                <span class='cat-tag<?php echo $cls_inc; ?>'><?php echo htmlspecialchars($row_sec['cat_nombre']); ?></span>
                                <h4><?php echo htmlspecialchars($row_sec['titulo']); ?></h4>
                                <p class='resumen-text' style="font-size:12px;"><?php echo htmlspecialchars($txt_res); ?></p>
                                <span class='fecha-noticia'>
                                    <i class="fas fa-clock"></i>
                                    <?php
                                    $h = intval($row_sec['horas_publicada']);
                                    if ($h < 1) echo "Hace menos de 1 hora";
                                    elseif ($h < 24) echo "Hace " . $h . " hora" . ($h != 1 ? 's' : '');
                                    elseif ($h < 48) echo "Ayer";
                                    else echo date('d/m/Y', strtotime($row_sec['fecha_publicacion']));
                                    ?>
                                </span>
                            </div>
                        </div>
                        <?php
                    }
                    echo "</div>"; // seccion-grid
                    echo "</div>"; // seccion-bloque
                }
                if($res_sec) { mysqli_free_result($res_sec); }
            }

            // 3. BLOQUE INFERIOR COMPUESTO (solo se muestra en el inicio, no en vista de categoría)
            if (!$esta_filtrando):
            ?>
            <div class="bloque-inferior">
                <div>
                    <h3 class="col-titulo">Editorial</h3>
                    <div class="editorial-box">
                        <?php
                        $res_edi = mysqli_query($conn, "SELECT * FROM noticias WHERE categoria_id = 15 ORDER BY id DESC LIMIT 1");
                        if($res_edi && mysqli_num_rows($res_edi) > 0) {
                            $r_edi = mysqli_fetch_assoc($res_edi);
                            $img_edi = obtenerRutaImagen($r_edi['imagen']);
                            ?>
                            <div onclick="abrirModal(<?php echo htmlspecialchars(json_encode($r_edi['titulo']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($img_edi), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($r_edi['contenido']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($r_edi['video_url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>)" style="cursor:pointer;">
                                <h3><?php echo htmlspecialchars($r_edi['titulo']); ?></h3>
                                <p class='resumen-text' style="color:#aaa;"><?php echo htmlspecialchars(substr(strip_tags($r_edi['contenido']), 0, 180)) . "..."; ?></p>
                            </div>
                            <?php
                        } else {
                            echo "<h3>Editorial</h3>";
                            echo "<p class='resumen-text'>No hay editoriales registradas actualmente.</p>";
                        }
                        if($res_edi) { mysqli_free_result($res_edi); }
                        ?>
                    </div>
                </div>

                <div>
                    <h3 class="col-titulo">Columnistas</h3>
                    <div class="columnistas-list">
                        <?php
                        $res_col = mysqli_query($conn, "SELECT * FROM noticias WHERE categoria_id = 13 ORDER BY id DESC LIMIT 4");
                        
                        if($res_col && mysqli_num_rows($res_col) > 0) {
                            while($col_data = mysqli_fetch_assoc($res_col)) {
                                $foto_columnista = obtenerRutaImagen($col_data['imagen']);
                                $tit_col = $col_data['titulo'];
                                $cont_col = $col_data['contenido'];
                                ?>
                                <div class="columnista-item" onclick="abrirModal(<?php echo htmlspecialchars(json_encode($tit_col), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($foto_columnista), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($cont_col), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($col_data['video_url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>)" style="cursor:pointer;">
                                    <img src="<?php echo $foto_columnista; ?>" class="columnista-avatar" onerror="this.src='img/avatar.png'">
                                    <div class="columnista-info">
                                        <h4><?php echo htmlspecialchars($tit_col); ?></h4>
                                        <p>Opinión PLEC</p> 
                                        <span style="color:#666; font-size:11px;">Click para leer columna</span>
                                    </div>
                                </div>
                                <?php
                            }
                        } else {
                            ?>
                            <div class="columnista-item">
                                <img src="img/avatar.png" class="columnista-avatar">
                                <div class="columnista-info"><h4>Espacio Disponible</h4><p>Columna de Opinión</p></div>
                            </div>
                            <?php
                        }
                        if($res_col) { mysqli_free_result($res_col); }
                        ?>
                    </div>
                </div>

                <div class="impreso-box">
                    <h3 class="col-titulo">Edición Impresa</h3>
                    <?php
                    $img_impreso = 'img/placeholder.svg';
                    $res_impreso = mysqli_query($conn, "SELECT portada FROM impresos ORDER BY id DESC LIMIT 1");
                    if ($res_impreso && mysqli_num_rows($res_impreso) > 0) {
                        $row_impreso = mysqli_fetch_assoc($res_impreso);
                        if (!empty($row_impreso['portada'])) {
                            $val = $row_impreso['portada'];
                            if (strpos($val, 'http') === 0) {
                                $img_impreso = $val;
                            } elseif (strpos($val, 'impresos/') === 0 || strpos($val, 'img/') === 0 || strpos($val, 'uploads/') === 0) {
                                $img_impreso = $val;
                            } else {
                                $img_impreso = 'impresos/' . $val;
                            }
                        }
                        mysqli_free_result($res_impreso);
                    }
                    ?>
                    <img src="<?php echo htmlspecialchars($img_impreso); ?>" class="impreso-img" onerror="this.src='img/placeholder.svg'">
                    <br>
                    <a href="impresos.php" class="btn-impreso">Ver edición</a>
                </div>
            </div>
            <?php
            endif; // fin if (!$esta_filtrando)
            }

            mysqli_close($conn); 
    }
    ?>
</main>

<!-- LO MÁS LEÍDO + EFEMÉRIDES + CONTADOR -->
<?php if (!$esta_filtrando): ?>
<div style="max-width:1350px; margin:0 auto 40px; padding:0 20px; box-sizing:border-box; display:grid; grid-template-columns:1fr 1.4fr 1fr; gap:20px;">

    <!-- LO MÁS LEÍDO -->
    <div class="mas-leido-box">
        <h3><i class="fas fa-fire" style="color:var(--rojo);margin-right:8px;font-size:16px;"></i>Lo más leído hoy</h3>
        <div id="masLeidoLista">
            <div style="color:#444;font-size:13px;padding:20px 0;text-align:center;">Cargando...</div>
        </div>
    </div>

    <!-- EFEMÉRIDES DEL TOLIMA -->
    <div class="efemerides-box">
        <h3>📅 Efemérides del Tolima</h3>
        <div class="efemerides-fecha"><?php
            $dias_ef = ["Domingo","Lunes","Martes","Miércoles","Jueves","Viernes","Sábado"];
            $meses_ef = ["enero","febrero","marzo","abril","mayo","junio","julio","agosto","septiembre","octubre","noviembre","diciembre"];
            echo $dias_ef[date('w')] . ", " . date('j') . " de " . $meses_ef[date('n')-1];
        ?></div>
        <?php
        // Efemérides históricas del Tolima (estáticas - puedes expandir o cargar desde DB)
        $efemerides = [
            ["1887","Ibagué es declarada capital definitiva del departamento del Tolima."],
            ["1943","Nace el escritor tolimense Héctor Sánchez, figura de las letras colombianas."],
            ["1967","Se funda la Universidad del Tolima, institución educativa emblemática de la región."],
            ["2002","El río Magdalena registra una de sus mayores crecientes afectando municipios del norte del Tolima."],
            ["1958","El Tolima FC disputa su primer partido oficial en el fútbol profesional colombiano."],
        ];
        // Seleccionar 3 basadas en el día del mes para que varíen
        $idx = date('j') % count($efemerides);
        for($e = 0; $e < 3; $e++) {
            $ef = $efemerides[($idx + $e) % count($efemerides)];
            echo "<div class='efemerides-item'>";
            echo "<div class='efemerides-anio'>" . $ef[0] . "</div>";
            echo "<div class='efemerides-texto'>" . htmlspecialchars($ef[1]) . "</div>";
            echo "</div>";
        }
        ?>
    </div>

    <!-- CONTADOR REGRESIVO -->
    <div class="contador-box">
        <div class="contador-titulo">⏳ Cuenta regresiva</div>
        <div class="contador-evento">Elecciones Regionales 2026</div>
        <div class="contador-digitos">
            <div class="contador-bloque"><div class="contador-num" id="cntDias">--</div><div class="contador-label">Días</div></div>
            <div class="contador-bloque" style="color:#444;font-size:22px;padding-top:2px;font-weight:300;">:</div>
            <div class="contador-bloque"><div class="contador-num" id="cntHoras">--</div><div class="contador-label">Horas</div></div>
            <div class="contador-bloque" style="color:#444;font-size:22px;padding-top:2px;font-weight:300;">:</div>
            <div class="contador-bloque"><div class="contador-num" id="cntMins">--</div><div class="contador-label">Min</div></div>
        </div>
    </div>
</div>
<?php endif; ?>

<div id="miModal" class="modal"><div class="modal-content"><span onclick="cerrarModal()" style="position:absolute; right:20px; top:10px; font-size:40px; color:#fff; cursor:pointer;">&times;</span><div id="modalBody"></div></div></div>

<!-- BOTÓN VOLVER ARRIBA -->
<button class="btn-arriba" id="btnArriba" onclick="window.scrollTo({top:0,behavior:'smooth'})" title="Volver arriba">▲</button>

<footer>
    <div class="footer-container">
        <div class="footer-info">
            <h3 style="color:#fff; font-family:'Playfair Display', serif; margin-top:0;">Noticias PLEC</h3>
            <p><i class="fas fa-user-tie"></i> <strong>Director:</strong> Fleybher Martínez</p>
            <p><i class="fas fa-map-marker-alt"></i> Tolima, Colombia</p>
            <p><i class="fas fa-phone-alt"></i> +57 310 3322482</p>
            <p><i class="fas fa-envelope" style="color:var(--azul);"></i> <a href="mailto:noticiasplec@gmail.com" style="color:#aaa; text-decoration:none; transition:color 0.2s;" onmouseover="this.style.color='#1a73e8'" onmouseout="this.style.color='#aaa'">noticiasplec@gmail.com</a></p>
        </div>
        <div class="footer-mensaje">
            <p>Gracias por leernos. Cada noticia que lees es un paso hacia una comunidad más informada, más unida y más libre.</p>
            <span>Sigue compartiendo, sigue preguntando, sigue siendo parte de Noticias PLEC. <strong>#SomosTodos</strong></span>
        </div>
        <div class="footer-right">
            <p>&copy; <?php echo date('Y'); ?> Todos los derechos reservados.</p>
            <p style="font-size: 12px; color: #555;">Desarrollo web por J.C.O.B</p>
        </div>
    </div>
</footer>

<!-- MODAL TRANSMISIÓN EN VIVO -->
<div class="modal-transmision-overlay" id="modalTransmisionLive">
    <div class="modal-transmision-box">
        <div class="modal-transmision-header">
            <h5>
                <span style="width:12px; height:12px; background:#ff0000; border-radius:50%; display:inline-block; animation: parpadeoLive 1s infinite;"></span>
                <i class="fas fa-broadcast-tower" style="color:#ff4444;"></i>
                Transmisión en Vivo — Noticias PLEC
            </h5>
            <button class="modal-transmision-close" onclick="cerrarTransmisionLive()">&times;</button>
        </div>
        <div class="modal-transmision-body">
            <div class="transmision-iframe-wrap">
                <iframe 
                    id="iframeTransmisionLive"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    allowfullscreen>
                </iframe>
            </div>
        </div>
    </div>
</div>

<script>
// ── TRANSMISIÓN EN VIVO — INDEX ──
function verificarTransmisionLive() {
    const activa = localStorage.getItem('plec-transmision-activa');
    const titulo = localStorage.getItem('plec-transmision-titulo');
    const btn = document.getElementById('btnTransmisionLive');
    const banner = document.getElementById('bannerTransmision');
    const bannerTitulo = document.getElementById('bannerTransTitulo');
    const bannerOculto = sessionStorage.getItem('plec-banner-trans-cerrado');

    if (activa === '1' && btn) {
        btn.style.display = 'flex';
        // Mostrar el banner si no fue cerrado en esta sesión
        if (banner && !bannerOculto) {
            if (titulo && bannerTitulo) bannerTitulo.textContent = titulo;
            banner.classList.add('visible');
        }
    } else if (btn) {
        btn.style.display = 'none';
        if (banner) banner.classList.remove('visible');
    }
}

function cerrarBannerTransmision() {
    const banner = document.getElementById('bannerTransmision');
    if (banner) banner.classList.remove('visible');
    // Guardar en sessionStorage: el banner no vuelve a aparecer hasta cerrar/reabrir el tab
    sessionStorage.setItem('plec-banner-trans-cerrado', '1');
}

function abrirTransmisionLive() {
    const embedUrl = localStorage.getItem('plec-transmision-embed');
    if (!embedUrl) return;
    document.getElementById('iframeTransmisionLive').src = embedUrl;
    document.getElementById('modalTransmisionLive').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function cerrarTransmisionLive() {
    document.getElementById('modalTransmisionLive').classList.remove('show');
    document.getElementById('iframeTransmisionLive').src = '';
    document.body.style.overflow = '';
}

// Verificar al cargar la página
window.addEventListener('DOMContentLoaded', verificarTransmisionLive);

// Cerrar al hacer click fuera del modal
document.getElementById('modalTransmisionLive').addEventListener('click', function(e) {
    if (e.target === this) cerrarTransmisionLive();
});
</script>

<script>
function abrirNav() { document.getElementById("sidebarMenu").style.width = "280px"; }
function cerrarNav() { document.getElementById("sidebarMenu").style.width = "0"; }
function plecVideoEmbed(url) {
    if (!url || url.trim() === '') return '';
    url = url.trim();
    const u = url.toLowerCase();
    let embed = '';
    if (u.includes('youtube.com') || u.includes('youtu.be')) {
        let vid = null;
        if (url.includes('watch?v='))      vid = url.split('watch?v=')[1].split(/[&?]/)[0];
        else if (url.includes('/live/'))   vid = url.split('/live/')[1].split(/[&?]/)[0];
        else if (url.includes('/shorts/')) vid = url.split('/shorts/')[1].split(/[&?]/)[0];
        else { const m = url.match(/youtu\.be\/([a-zA-Z0-9_-]{5,15})/); if (m) vid = m[1]; }
        if (vid) embed = `https://www.youtube.com/embed/${vid}?rel=0`;
    } else if (u.includes('vimeo.com')) {
        const m = url.match(/vimeo\.com\/(\d+)/);
        if (m) embed = `https://player.vimeo.com/video/${m[1]}`;
    } else if (u.includes('facebook.com') || u.includes('fb.watch')) {
        embed = `https://www.facebook.com/plugins/video.php?href=${encodeURIComponent(url)}&show_text=false`;
    } else if (u.includes('tiktok.com')) {
        const m = url.split('/video/');
        if (m[1]) embed = `https://www.tiktok.com/embed/v2/${m[1].split('?')[0]}`;
    } else if (u.includes('instagram.com')) {
        const m = url.split('/p/');
        if (m[1]) embed = `https://www.instagram.com/p/${m[1].split('/')[0]}/embed`;
    } else if (u.includes('twitch.tv')) {
        const canal = url.split('twitch.tv/')[1]?.split('/')[0];
        if (canal) embed = `https://player.twitch.tv/?channel=${canal}&parent=${location.hostname}`;
    }
    if (!embed) return '';
    return `<div style="margin:22px 0;">
        <span style="background:#ff4d4d;color:#fff;font-size:11px;font-weight:bold;padding:3px 10px;border-radius:20px;letter-spacing:.5px;display:inline-flex;align-items:center;gap:5px;margin-bottom:10px;">
            <i class="fas fa-play-circle"></i> VIDEO
        </span>
        <div style="position:relative;padding-bottom:56.25%;height:0;border-radius:10px;overflow:hidden;background:#000;">
            <iframe src="${embed}"
                style="position:absolute;top:0;left:0;width:100%;height:100%;border:none;"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                allowfullscreen loading="lazy"></iframe>
        </div>
    </div>`;
}

function abrirModal(t, i, c, v) {
    v = v || '';
    const textoPlano = c.replace(/<[^>]*>/g,'');
    const urlPagina = encodeURIComponent('https://noticiasplec-production.up.railway.app');
    const urlCompartirWA = encodeURIComponent('📰 ' + t + ' — Noticias PLEC: https://noticiasplec-production.up.railway.app');
    const urlCompartirTG = encodeURIComponent('https://noticiasplec-production.up.railway.app') + '&text=' + encodeURIComponent('📰 ' + t + ' — Noticias PLEC');
    const favKey = 'fav_' + btoa(unescape(encodeURIComponent(t))).substring(0,20);
    const yaGuardado = localStorage.getItem(favKey);
    const videoHtml = plecVideoEmbed(v);
    document.getElementById('modalBody').innerHTML = `
        <h1 style="color:#fff; font-family:'Playfair Display';">${t}</h1>
        <img src="${i}" style="width:100%; border-radius:8px; margin:20px 0;">
        <div style="color:#ccc; line-height:1.7; font-size:18px;" id="modalContenidoTexto">${c}</div>
        ${videoHtml}
        <div style="margin-top:20px; border-top:1px solid #333; padding-top:15px; display:flex; flex-wrap:wrap; gap:4px;">
            <button class="btn-voz" id="btnVozModal" onclick="toggleVoz(this, '${textoPlano.replace(/'/g,"\\'")}')">
                🔊 <span>Escuchar noticia</span>
            </button>
            <a class="btn-whatsapp" href="https://wa.me/?text=${urlCompartirWA}" target="_blank">
                <i class="fab fa-whatsapp"></i> WhatsApp
            </a>
            <a class="btn-telegram" href="https://t.me/share/url?url=${urlCompartirTG}" target="_blank">
                <i class="fab fa-telegram-plane"></i> Telegram
            </a>
            <button class="btn-qr" onclick="abrirQR('${encodeURIComponent(t)}')">
                ◻ QR
            </button>
            <button class="btn-ig" onclick="abrirIG('${t.replace(/'/g,"\\'")}', '${i}')">
                <i class="fab fa-instagram"></i> Historia
            </button>
            <button class="btn-favorito ${yaGuardado ? 'guardado' : ''}" id="btnFavModal" onclick="toggleFavorito('${favKey}','${t.replace(/'/g,"\\'")}',this)" title="Guardar noticia">
                ${yaGuardado ? '★' : '☆'} Guardar
            </button>
        </div>`;
    document.getElementById('miModal').style.display = "block";
    registrarClick(t);
}
function cerrarModal() { document.getElementById('miModal').style.display = "none"; }

// TEMA CLARO / OSCURO
function toggleTema() {
    const body = document.body;
    const btn = document.getElementById('btnTema');
    const esModoClaro = body.classList.toggle('modo-claro');
    btn.textContent = esModoClaro ? '🌙' : '☀️';
    localStorage.setItem('tema-plec', esModoClaro ? 'claro' : 'oscuro');
}
// Restaurar tema guardado
(function() {
    const tema = localStorage.getItem('tema-plec');
    if (tema === 'claro') {
        document.body.classList.add('modo-claro');
        window.addEventListener('DOMContentLoaded', function() {
            const btn = document.getElementById('btnTema');
            if(btn) btn.textContent = '🌙';
        });
    }
})();
const titulares = [
    "La verdad no espera.<br>Bienvenido al periodismo libre.",
    "Cada noticia, una historia.<br>Cada historia, una voz del Tolima.",
    "Informar es un acto de valentía.<br>Leer, uno de libertad."
];
let tIdx = 0;
// cerrarBienvenida definida en bloque inline sobre el modal
window.addEventListener('DOMContentLoaded', function() {
    const puntos = document.querySelectorAll('.bienvenida-puntos span');
    setInterval(function() {
        tIdx = (tIdx + 1) % titulares.length;
        document.getElementById('bienvenidaTitular').innerHTML = titulares[tIdx];
        puntos.forEach((p, i) => p.classList.toggle('activo', i === tIdx));
    }, 2800);
    // Mostrar solo si no ha visto el modal en esta sesión
    if (!sessionStorage.getItem('plec-bienvenida-vista')) {
        setTimeout(function() {
            var m = document.getElementById('modalBienvenida');
            if (m) { m.style.display = 'flex'; m.classList.add('show'); }
            document.body.style.overflow = 'hidden';
        }, 300);
    }

    // BOTÓN VOLVER ARRIBA
    const btnArriba = document.getElementById('btnArriba');
    window.addEventListener('scroll', function() {
        btnArriba.classList.toggle('visible', window.scrollY > 400);
    });

    // CLIMA — OpenWeatherMap (Ibagué, Tolima)
    fetch('https://api.open-meteo.com/v1/forecast?latitude=4.4389&longitude=-75.2322&current_weather=true&temperature_unit=celsius')
        .then(r => r.json())
        .then(d => {
            const temp = Math.round(d.current_weather.temperature);
            const cod = d.current_weather.weathercode;
            const iconos = { 0:'☀️', 1:'🌤️', 2:'⛅', 3:'☁️', 45:'🌫️', 48:'🌫️', 51:'🌦️', 61:'🌧️', 80:'🌦️', 95:'⛈️' };
            const icono = iconos[cod] || iconos[Math.floor(cod/10)*10] || '🌡️';
            document.getElementById('climaTemp').textContent = temp + '°C';
            document.getElementById('climaIcono').textContent = icono;
        })
        .catch(() => { document.getElementById('climaTemp').textContent = '--°C'; });

});

// LECTOR DE VOZ
let vozActiva = null;
function toggleVoz(btn, texto) {
    if (vozActiva && window.speechSynthesis.speaking) {
        window.speechSynthesis.cancel();
        btn.classList.remove('leyendo');
        btn.querySelector('span').textContent = 'Escuchar noticia';
        vozActiva = null;
        return;
    }
    const utterance = new SpeechSynthesisUtterance(texto.substring(0, 2000));
    utterance.lang = 'es-CO';
    utterance.rate = 0.95;
    utterance.onstart = () => { btn.classList.add('leyendo'); btn.querySelector('span').textContent = 'Detener lectura'; };
    utterance.onend = () => { btn.classList.remove('leyendo'); btn.querySelector('span').textContent = 'Escuchar noticia'; vozActiva = null; };
    window.speechSynthesis.speak(utterance);
    vozActiva = utterance;
}

// ── ALTO CONTRASTE ────────────────────────────────────────────────────────────
function toggleAltoContraste() {
    document.body.classList.toggle('alto-contraste');
    localStorage.setItem('plec-contraste', document.body.classList.contains('alto-contraste') ? '1' : '0');
}
(function(){ if(localStorage.getItem('plec-contraste')==='1') document.body.classList.add('alto-contraste'); })();

// ── ENCUESTA ──────────────────────────────────────────────────────────────────
(function initEncuesta() {
    const votos = JSON.parse(localStorage.getItem('plec-encuesta-votos') || '[10,6,14]');
    const votado = localStorage.getItem('plec-encuesta-votado');
    actualizarEncuesta(votos, votado);
})();
function votarEncuesta(idx) {
    if (localStorage.getItem('plec-encuesta-votado')) return;
    const votos = JSON.parse(localStorage.getItem('plec-encuesta-votos') || '[10,6,14]');
    votos[idx]++;
    localStorage.setItem('plec-encuesta-votos', JSON.stringify(votos));
    localStorage.setItem('plec-encuesta-votado', '1');
    actualizarEncuesta(votos, '1');
}
function actualizarEncuesta(votos, votado) {
    const total = votos.reduce((a,b)=>a+b,0);
    for(let i=0;i<3;i++){
        const pct = total > 0 ? Math.round(votos[i]/total*100) : 0;
        const b = document.getElementById('barra'+i);
        const p = document.getElementById('pct'+i);
        if(b) b.style.width = pct+'%';
        if(p) p.textContent = pct+'%';
    }
    const vEl = document.getElementById('encuestaVoted');
    if(vEl) vEl.textContent = votado ? total+' votos en total' : 'Haz clic para votar';
}

// ── FAVORITOS ─────────────────────────────────────────────────────────────────
function toggleFavorito(key, titulo, btn) {
    if(localStorage.getItem(key)) {
        localStorage.removeItem(key);
        btn.classList.remove('guardado');
        btn.textContent = '☆ Guardar';
    } else {
        localStorage.setItem(key, titulo);
        btn.classList.add('guardado');
        btn.textContent = '★ Guardado';
    }
    actualizarBadgeGuardados();
}
function actualizarBadgeGuardados() {
    const keys = [];
    for(let i=0;i<localStorage.length;i++){
        const k = localStorage.key(i);
        if(k && k.startsWith('fav_')) keys.push(k);
    }
    const badge = document.getElementById('badgeGuardadosCnt');
    if(!badge) return;
    if(keys.length > 0){ badge.textContent = keys.length; badge.style.display='inline-flex'; }
    else { badge.style.display='none'; }
}
window.addEventListener('DOMContentLoaded', actualizarBadgeGuardados);

// ── MODAL MIS GUARDADOS ───────────────────────────────────────────────────────
const GUARDADOS_POR_PAGINA = 8;
let guardadosPagina = 1;
let todosGuardados = [];

function abrirGuardados() {
    cerrarNav();
    todosGuardados = [];
    for(let i=0;i<localStorage.length;i++){
        const k = localStorage.key(i);
        if(k && k.startsWith('fav_')) todosGuardados.push({ key: k, titulo: localStorage.getItem(k) });
    }
    guardadosPagina = 1;
    renderGuardadosModal();
    document.getElementById('modalGuardados').classList.add('show');
    document.body.style.overflow='hidden';
}
function cerrarGuardados() {
    document.getElementById('modalGuardados').classList.remove('show');
    document.body.style.overflow='';
}
function renderGuardadosModal() {
    const lista = document.getElementById('guardadosLista');
    const btnMas = document.getElementById('btnCargarMasGuardados');
    if(todosGuardados.length === 0){
        lista.innerHTML = '<div class="guardados-vacio"><div>⭐</div>Aún no has guardado noticias.<br><small style="color:#555;">Abre una noticia y toca ★ Guardar.</small></div>';
        btnMas.style.display='none'; return;
    }
    const visibles = todosGuardados.slice(0, guardadosPagina * GUARDADOS_POR_PAGINA);
    lista.innerHTML = visibles.map((item, idx) => `
        <div class="guardado-card" onclick="abrirGuardadoPorTitulo('${item.titulo.replace(/'/g,"\\'").replace(/"/g,'&quot;')}')">
            <div class="guardado-num">${idx+1}</div>
            <div class="guardado-titulo">${item.titulo}</div>
            <button class="guardado-eliminar" onclick="event.stopPropagation(); eliminarGuardado('${item.key}')" title="Eliminar">✕</button>
        </div>`).join('');
    btnMas.style.display = (visibles.length < todosGuardados.length) ? 'block' : 'none';
}
function cargarMasGuardados() {
    guardadosPagina++;
    renderGuardadosModal();
}
function eliminarGuardado(key) {
    localStorage.removeItem(key);
    todosGuardados = todosGuardados.filter(i => i.key !== key);
    renderGuardadosModal();
    actualizarBadgeGuardados();
}
function abrirGuardadoPorTitulo(titulo) {
    cerrarGuardados();
    // Buscar la noticia en el DOM por título y abrirla si existe, si no mostrar aviso
    const cards = document.querySelectorAll('.card');
    for(const card of cards){
        const h = card.querySelector('h2,h3,h4');
        if(h && h.textContent.trim() === titulo.trim()){
            card.click(); return;
        }
    }
    // Si no está en pantalla, mostrar aviso
    alert('Busca esta noticia en el sitio:\n\n' + titulo);
}
document.getElementById('modalGuardados').addEventListener('click', function(e){ if(e.target===this) cerrarGuardados(); });

// ── MODAL OPINIÓN / COLUMNISTAS ───────────────────────────────────────────────
const OPINION_POR_PAGINA = 6;
let opinionPagina = 1;
let opinionData = [];

function abrirOpinion() {
    cerrarNav();
    opinionPagina = 1;
    opinionData = [];
    document.getElementById('modalOpinion').classList.add('show');
    document.body.style.overflow='hidden';
    cargarColumnistas();
}
function cerrarOpinion() {
    document.getElementById('modalOpinion').classList.remove('show');
    document.body.style.overflow='';
}
function cargarColumnistas() {
    fetch('get_columnistas.php?limit=' + OPINION_POR_PAGINA + '&offset=' + ((opinionPagina-1)*OPINION_POR_PAGINA))
    .then(r=>r.json())
    .then(data => {
        opinionData = opinionData.concat(data.items || []);
        renderOpinionModal(data.total || 0);
    })
    .catch(()=>{
        // Fallback: mostrar los que ya están en el DOM (sección opinión/columnistas)
        const cols = document.querySelectorAll('.columnista-item');
        if(cols.length > 0){
            const grid = document.getElementById('opinionGrid');
            grid.innerHTML = Array.from(cols).map(col=>{
                const img = col.querySelector('img') ? col.querySelector('img').src : 'img/avatar.png';
                const titulo = col.querySelector('h4') ? col.querySelector('h4').textContent : '';
                const sub = col.querySelector('p') ? col.querySelector('p').textContent : 'Opinión PLEC';
                return `<div class="opinion-card" onclick="${col.getAttribute('onclick') || ''}">
                    <img src="${img}" class="opinion-avatar" onerror="this.src='img/avatar.png'">
                    <div class="opinion-info"><h4>${titulo}</h4><p>${sub}</p><span>Toca para leer columna</span></div>
                </div>`;
            }).join('');
            document.getElementById('btnCargarMasOpinion').style.display='none';
        } else {
            document.getElementById('opinionGrid').innerHTML='<div style="grid-column:1/-1;text-align:center;padding:30px;color:#555;">No hay columnistas disponibles.</div>';
        }
    });
}
function renderOpinionModal(total) {
    const grid = document.getElementById('opinionGrid');
    if(opinionData.length === 0){
        grid.innerHTML='<div style="grid-column:1/-1;text-align:center;padding:30px;color:#555;">No hay columnistas disponibles.</div>';
        document.getElementById('btnCargarMasOpinion').style.display='none';
        return;
    }
    grid.innerHTML = opinionData.map(col=>`
        <div class="opinion-card" onclick="abrirModal(${JSON.stringify(col.titulo)}, ${JSON.stringify(col.imagen)}, ${JSON.stringify(col.contenido)}, ${JSON.stringify(col.video_url || "")})">
            <img src="${col.imagen}" class="opinion-avatar" onerror="this.src='img/avatar.png'">
            <div class="opinion-info">
                <h4>${col.titulo}</h4>
                <p>Opinión PLEC</p>
                <span>Toca para leer columna</span>
            </div>
        </div>`).join('');
    const btnMas = document.getElementById('btnCargarMasOpinion');
    btnMas.style.display = (opinionData.length < total) ? 'block' : 'none';
}
function cargarMasOpinion() {
    opinionPagina++;
    cargarColumnistas();
}
document.getElementById('modalOpinion').addEventListener('click', function(e){ if(e.target===this) cerrarOpinion(); });

// ── LO MÁS LEÍDO (simulado con localStorage clicks) ──────────────────────────
function registrarClick(titulo) {
    const datos = JSON.parse(localStorage.getItem('plec-clicks') || '{}');
    datos[titulo] = (datos[titulo] || 0) + 1;
    localStorage.setItem('plec-clicks', JSON.stringify(datos));
    renderMasLeido();
}
function renderMasLeido() {
    const datos = JSON.parse(localStorage.getItem('plec-clicks') || '{}');
    const sorted = Object.entries(datos).sort((a,b)=>b[1]-a[1]).slice(0,5);
    const el = document.getElementById('masLeidoLista');
    if(!el) return;
    if(sorted.length===0){ el.innerHTML='<div style="color:#444;font-size:13px;padding:20px 0;text-align:center;">Aún no hay datos</div>'; return; }
    const clases = ['top1','top2','top3','',''];
    el.innerHTML = sorted.map(([t,v],i)=>`
        <div class="mas-leido-item">
            <div class="mas-leido-num ${clases[i]||''}">${i+1}</div>
            <div class="mas-leido-titulo">${t.substring(0,80)}${t.length>80?'…':''}<br><span style="font-size:10px;color:#555;">${v} lectura${v!==1?'s':''}</span></div>
        </div>`).join('');
}
window.addEventListener('DOMContentLoaded', renderMasLeido);

// ── TICKER LATERAL VERTICAL ───────────────────────────────────────────────────
window.addEventListener('DOMContentLoaded', function() {
    const items = document.querySelectorAll('.ticker-item');
    const track = document.getElementById('tickerLateralTrack');
    if(!track || items.length === 0) return;
    let html = '';
    items.forEach(el => {
        html += `<div class="ticker-lateral-item" title="${el.textContent.trim()}">${el.textContent.trim()}</div>`;
    });
    track.innerHTML = html + html; // duplicar para loop infinito
});

// ── CONTADOR REGRESIVO ────────────────────────────────────────────────────────
(function initContador() {
    const meta = new Date('2026-10-25T00:00:00'); // Fecha objetivo
    function tick() {
        const diff = meta - new Date();
        if(diff <= 0) { document.querySelector('.contador-evento') && (document.querySelector('.contador-evento').textContent = '¡Ya llegó el día!'); return; }
        const dias  = Math.floor(diff / 86400000);
        const horas = Math.floor((diff % 86400000) / 3600000);
        const mins  = Math.floor((diff % 3600000) / 60000);
        const d = document.getElementById('cntDias');
        const h = document.getElementById('cntHoras');
        const m = document.getElementById('cntMins');
        if(d) d.textContent = String(dias).padStart(2,'0');
        if(h) h.textContent = String(horas).padStart(2,'0');
        if(m) m.textContent = String(mins).padStart(2,'0');
    }
    tick(); setInterval(tick, 60000);
})();

// ── QR ────────────────────────────────────────────────────────────────────────
function abrirQR(tituloEnc) {
    const url = 'https://noticiasplec-production.up.railway.app';
    document.getElementById('qrUrl').textContent = url;
    const canvas = document.getElementById('qrCanvas');
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#fff';
    ctx.fillRect(0,0,200,200);
    ctx.fillStyle = '#1a73e8';
    ctx.font = 'bold 11px sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText('noticiasplec-production', 100, 100);
    ctx.font = '9px sans-serif';
    ctx.fillStyle = '#555';
    ctx.fillText('Escanea con tu cámara', 100, 120);
    // Cargar QR real via API pública
    const img = new Image();
    img.crossOrigin = 'anonymous';
    img.onload = () => { ctx.clearRect(0,0,200,200); ctx.drawImage(img,0,0,200,200); };
    img.src = `https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(url)}`;
    document.getElementById('modalQR').classList.add('show');
}
function cerrarQR() { document.getElementById('modalQR').classList.remove('show'); }
document.getElementById('modalQR').addEventListener('click', function(e){ if(e.target===this) cerrarQR(); });

// ── IMAGEN INSTAGRAM ──────────────────────────────────────────────────────────
let igTitulo = '';
function abrirIG(titulo, imgSrc) {
    igTitulo = titulo;
    const canvas = document.getElementById('igCanvas');
    const ctx = canvas.getContext('2d');
    const W = 380, H = 380;
    // Fondo degradado
    const grad = ctx.createLinearGradient(0,0,W,H);
    grad.addColorStop(0,'#121212'); grad.addColorStop(1,'#1a1a2e');
    ctx.fillStyle = grad; ctx.fillRect(0,0,W,H);
    // Intentar cargar imagen de fondo
    const bg = new Image(); bg.crossOrigin='anonymous';
    bg.onload = () => {
        ctx.globalAlpha = 0.3; ctx.drawImage(bg,0,0,W,H); ctx.globalAlpha = 1;
        dibujarTextoIG(ctx, titulo, W, H);
    };
    bg.onerror = () => dibujarTextoIG(ctx, titulo, W, H);
    bg.src = imgSrc;
    document.getElementById('modalIG').classList.add('show');
}
function dibujarTextoIG(ctx, titulo, W, H) {
    // Overlay oscuro inferior
    const overlay = ctx.createLinearGradient(0, H*0.4, 0, H);
    overlay.addColorStop(0,'rgba(0,0,0,0)'); overlay.addColorStop(1,'rgba(0,0,0,0.92)');
    ctx.fillStyle = overlay; ctx.fillRect(0,0,W,H);
    // Logo
    ctx.fillStyle = '#fff'; ctx.font = 'bold 22px "Playfair Display", serif';
    ctx.textAlign = 'center'; ctx.fillText('Noticias PLEC', W/2, 44);
    ctx.fillStyle = '#ff4d4d'; ctx.fillRect(W/2-40, 52, 80, 3);
    // Título con wrap
    ctx.fillStyle = '#fff'; ctx.font = 'bold 18px sans-serif';
    const palabras = titulo.split(' '), lineas = [], maxW = W - 40;
    let linea = '';
    palabras.forEach(p => { const t = linea ? linea+' '+p : p; if(ctx.measureText(t).width > maxW){ lineas.push(linea); linea=p; } else linea=t; });
    lineas.push(linea);
    const yStart = H - 80 - (lineas.length-1)*26;
    lineas.forEach((l,i) => ctx.fillText(l, W/2, yStart + i*26));
    // URL
    ctx.fillStyle = '#1a73e8'; ctx.font = '12px sans-serif';
    ctx.fillText('noticiasplec-production.up.railway.app · #SomosTodos', W/2, H-20);
}
function cerrarIG() { document.getElementById('modalIG').classList.remove('show'); }
function descargarIG() {
    const a = document.createElement('a');
    a.download = 'historia-plec.png';
    a.href = document.getElementById('igCanvas').toDataURL('image/png');
    a.click();
}
document.getElementById('modalIG').addEventListener('click', function(e){ if(e.target===this) cerrarIG(); });
</script>
</body>
</html>
<?php
if (ob_get_length()) {
    ob_end_flush();
}
?>