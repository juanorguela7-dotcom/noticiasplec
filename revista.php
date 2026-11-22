<?php
include("conexion.php");
include("funciones_video.php");

function rutaImagenRevista($img) {
    if (empty($img)) return 'img/placeholder.svg';
    return (strpos($img, 'img/') === 0 || strpos($img, 'http') === 0) ? $img : 'img/' . $img;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Revista PLEC - Ediciones Especiales</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=Libre+Franklin:wght@300;400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script>document.documentElement.classList.add('plec-js');</script>
    <style>
        :root { --azul: #1a73e8; --bg: #0f0f0f; --oro: #ffcc00; }
        body { font-family: 'Libre Franklin', sans-serif; background: var(--bg); color: #fff; margin: 0; overflow-x: hidden; }

        .header-revista { text-align: center; padding: 70px 20px 50px; min-height: 230px; }
        .header-revista h1 {
            font-family: 'Playfair Display', serif; font-size: 56px; margin: 0; color: var(--oro);
            text-shadow: 0 1px 0 #d9ad00, 0 2px 0 #c29a00, 0 3px 0 #ab8800, 0 4px 0 #947500, 0 5px 0 #7d6300, 0 12px 20px rgba(0,0,0,.6), 0 0 50px rgba(255,204,0,.3);
        }
        .header-revista p { letter-spacing: 4px; opacity: 0.7; font-size: 12px; margin: 0 0 10px; }
        .header-revista .linea-oro { width: 60px; height: 3px; background: linear-gradient(90deg, transparent, var(--oro), transparent); margin: 20px auto 0; }

        .grid-revista { max-width: 1200px; margin: 0 auto; padding: 40px 20px 60px; display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 30px; }

        .card-especial { background: #1a1a1a; border: 1px solid #333; border-radius: 16px; overflow: hidden; cursor: pointer; transition: border-color .3s; }
        .card-especial:hover { border-color: var(--oro); }
        .card-especial > img { width: 100%; height: 250px; object-fit: cover; display: block; }
        .card-especial .texto { padding: 20px; }
        .card-especial h2 { font-family: 'Playfair Display', serif; margin: 0 0 10px 0; font-size: 24px; }
        .card-especial .leer { color: var(--oro); font-weight: bold; display: inline-flex; gap: 6px; align-items: center; }
        .card-especial .leer i { transition: transform .35s cubic-bezier(.2,.8,.2,1); }
        .card-especial:hover .leer i { transform: translateX(6px); }

        .btn-volver { position: fixed; top: 20px; left: 20px; background: var(--azul); color: white; padding: 10px 20px; border-radius: 30px; text-decoration: none; font-weight: bold; z-index: 100; box-shadow: 0 8px 20px -6px rgba(26,115,232,.7); }

        /* MODAL */
        .modal-overlay { display: none; position: fixed; z-index: 11000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); overflow-y: auto; }
        .modal-box { background: #1e1e1e; max-width: 900px; margin: 40px auto; padding: 40px; border-radius: 14px; position: relative; border: 1px solid #333; box-shadow: 0 40px 90px -20px rgba(0,0,0,.9), 0 0 60px -20px rgba(255,204,0,.35); }
        .modal-img { width: 100%; max-height: 420px; object-fit: cover; border-radius: 10px; margin-bottom: 25px; display: block; }
        .modal-titulo { font-family: 'Playfair Display', serif; font-size: 32px; color: #fff; margin: 0 0 20px 0; line-height: 1.2; padding-right: 50px; }
        .modal-contenido { color: #ccc; font-size: 16px; line-height: 1.8; border-top: 1px solid #333; padding-top: 20px; white-space: pre-line; }

        @media (max-width: 768px) {
            .modal-box { margin: 10px; padding: 22px 18px; }
            .modal-titulo { font-size: 22px; }
            .header-revista h1 { font-size: 36px; }
            .btn-volver { top: auto; bottom: 16px; left: 16px; }
        }
    </style>
    <link rel="stylesheet" href="assets/css/plec-3d.css?v=1">
</head>
<body>

<a href="index.php" class="btn-volver"><i class="fas fa-arrow-left"></i> Volver al Inicio</a>

<header class="header-revista plec-hero">
    <canvas class="plec-globe" aria-hidden="true"></canvas>
    <div class="plec-logo-wrap">
        <p>EDICIÓN EXCLUSIVA</p>
        <h1>HISTORIAS PLEC</h1>
        <div class="linea-oro"></div>
    </div>
</header>

<main class="grid-revista">
    <?php
    $especiales = [];
    $res = mysqli_query($conn, "SELECT * FROM noticias WHERE es_especial = 1 ORDER BY id DESC");
    while ($res && $row = mysqli_fetch_assoc($res)) {
        $especiales[] = [
            'titulo'    => $row['titulo'],
            'imagen'    => rutaImagenRevista($row['imagen'] ?? ''),
            'contenido' => $row['contenido'],
            'video'     => renderizarVideoEmbed($row['video_url'] ?? ''),
        ];
    }

    if (count($especiales) > 0) {
        foreach ($especiales as $idx => $e) {
            $resumen = trim(preg_replace('/\s+/u', ' ', strip_tags($e['contenido'])));
            $resumen = mb_strlen($resumen) > 150 ? mb_substr($resumen, 0, 150) . '...' : $resumen;
            ?>
            <div class="card-especial" onclick="abrirCronica(<?php echo $idx; ?>)" role="button" tabindex="0">
                <img src="<?php echo htmlspecialchars($e['imagen']); ?>" alt="<?php echo htmlspecialchars($e['titulo']); ?>" loading="lazy" onerror="this.onerror=null;this.src='img/placeholder.svg'">
                <div class="texto">
                    <h2><?php echo htmlspecialchars($e['titulo']); ?></h2>
                    <p style="color:#bbb; font-size:14px;"><?php echo htmlspecialchars($resumen); ?></p>
                    <span class="leer">Leer crónica completa <i class="fas fa-arrow-right"></i></span>
                </div>
            </div>
            <?php
        }
    } else {
        echo "<div style='grid-column: 1/-1; text-align:center; padding: 50px; opacity:0.5;'>
                <i class='fas fa-feather-alt fa-3x'></i>
                <p>Aún no hay historias especiales publicadas.</p>
              </div>";
    }
    ?>
</main>

<!-- MODAL CRÓNICA COMPLETA -->
<div class="modal-overlay" id="modalCronica" role="dialog" aria-modal="true" aria-label="Crónica">
    <div class="modal-box" id="modalCronicaBox">
        <button type="button" class="plec-modal-cerrar" onclick="cerrarCronica()" aria-label="Cerrar">&times;</button>
        <img id="cronica-img" src="" alt="" class="modal-img plec-modal-img">
        <h2 id="cronica-titulo" class="modal-titulo"></h2>
        <div id="cronica-video"></div>
        <div id="cronica-contenido" class="modal-contenido"></div>
    </div>
</div>

<script>
const CRONICAS = <?php echo json_encode($especiales, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>;

function abrirCronica(idx) {
    const c = CRONICAS[idx];
    if (!c) return;
    document.getElementById('cronica-titulo').textContent = c.titulo;
    document.getElementById('cronica-video').innerHTML = c.video || '';
    document.getElementById('cronica-contenido').innerHTML = c.contenido;
    const imgEl = document.getElementById('cronica-img');
    imgEl.onerror = function () { this.style.display = 'none'; };
    imgEl.src = c.imagen;
    imgEl.alt = c.titulo;
    imgEl.style.display = 'block';
    const modal = document.getElementById('modalCronica');
    // Reiniciar la animación de entrada 3D en cada apertura
    const box = document.getElementById('modalCronicaBox');
    box.style.animation = 'none'; void box.offsetWidth; box.style.animation = '';
    modal.style.display = 'block';
    modal.scrollTop = 0;
    document.body.style.overflow = 'hidden';
}
function cerrarCronica() {
    document.getElementById('modalCronica').style.display = 'none';
    document.getElementById('cronica-video').innerHTML = ''; // detener el video
    document.body.style.overflow = '';
}
document.getElementById('modalCronica').addEventListener('click', function (e) {
    if (e.target === this) cerrarCronica();
});
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') return cerrarCronica();
    if ((e.key === 'Enter' || e.key === ' ') && e.target.classList && e.target.classList.contains('card-especial')) {
        e.preventDefault();
        e.target.click();
    }
});
</script>
<script src="assets/js/plec-3d.js?v=1" defer></script>
</body>
</html>
