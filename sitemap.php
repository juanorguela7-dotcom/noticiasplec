<?php
// sitemap dinámico — regístralo en Google Search Console como: <dominio>/sitemap.php
header('Content-Type: application/xml; charset=utf-8');
date_default_timezone_set('America/Bogota');
include("conexion.php");

$dominio = PLEC_DOMINIO;
$hoy = date('Y-m-d');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

function plec_url($loc, $freq, $prioridad, $lastmod = null) {
    echo "<url><loc>" . htmlspecialchars($loc, ENT_XML1) . "</loc>"
       . ($lastmod ? "<lastmod>{$lastmod}</lastmod>" : "")
       . "<changefreq>{$freq}</changefreq><priority>{$prioridad}</priority></url>\n";
}

// Página de inicio
plec_url("{$dominio}/index.php", 'hourly', '1.0', $hoy);

// Páginas de categorías
foreach ([6, 7, 4, 9, 3, 14, 16] as $id) {
    plec_url("{$dominio}/index.php?cat={$id}", 'daily', '0.8', $hoy);
}

// Impresos y revista
plec_url("{$dominio}/impresos.php", 'weekly', '0.6');
plec_url("{$dominio}/revista.php", 'weekly', '0.6');

// Noticias individuales (las 1000 más recientes)
$res = mysqli_query($conn, "SELECT id, fecha_publicacion FROM noticias ORDER BY id DESC LIMIT 1000");
if ($res) {
    while ($n = mysqli_fetch_assoc($res)) {
        $lastmod = !empty($n['fecha_publicacion']) ? date('Y-m-d', strtotime($n['fecha_publicacion'])) : null;
        plec_url("{$dominio}/noticia.php?id=" . intval($n['id']), 'monthly', '0.7', $lastmod);
    }
}

echo '</urlset>';
mysqli_close($conn);
