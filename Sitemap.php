<?php
// sitemap.xml dinámico — sube este archivo como sitemap.php
// y regístralo en Google Search Console como: https://tudominio.com/sitemap.php

header('Content-Type: application/xml; charset=utf-8');
date_default_timezone_set('America/Bogota');
include("conexion.php");

$dominio = "https://www.noticiasplec.com"; // <-- cambia por tu dominio real

echo '<?xml version="1.0" encoding="UTF-8"?>';
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

// Página de inicio
echo "<url>
    <loc>{$dominio}/index.php</loc>
    <changefreq>hourly</changefreq>
    <priority>1.0</priority>
    <lastmod>" . date('Y-m-d') . "</lastmod>
</url>";

// Páginas de categorías
$categorias = [
    6  => "Colombia",
    7  => "Política",
    4  => "Salud",
    9  => "Internacional",
    3  => "Educación",
    14 => "Farándula"
];
foreach ($categorias as $id => $nombre) {
    echo "<url>
    <loc>{$dominio}/index.php?cat={$id}</loc>
    <changefreq>daily</changefreq>
    <priority>0.8</priority>
    <lastmod>" . date('Y-m-d') . "</lastmod>
</url>";
}

// Páginas de impresos y revista
$paginas_extra = ['impresos.php', 'revista.php'];
foreach ($paginas_extra as $pagina) {
    echo "<url>
    <loc>{$dominio}/{$pagina}</loc>
    <changefreq>weekly</changefreq>
    <priority>0.6</priority>
</url>";
}

echo '</urlset>';

if (isset($conn)) mysqli_close($conn);
?>