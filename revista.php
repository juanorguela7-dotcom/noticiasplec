<?php
include("conexion.php");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Revista PLEC - Ediciones Especiales</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=Libre+Franklin:wght@300;400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --azul: #1a73e8; --bg: #0f0f0f; --oro: #ffcc00; }
        body { font-family: 'Libre Franklin', sans-serif; background: var(--bg); color: #fff; margin: 0; }

        .header-revista { text-align: center; padding: 60px 20px; background: linear-gradient(to bottom, #1a1a1a, var(--bg)); }
        .header-revista h1 { font-family: 'Playfair Display', serif; font-size: 50px; margin: 0; color: var(--oro); }
        .header-revista p { letter-spacing: 4px; opacity: 0.7; font-size: 12px; }

        .grid-revista { max-width: 1200px; margin: 0 auto; padding: 40px 20px; display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 30px; }

        .card-especial { background: #1a1a1a; border: 1px solid #333; border-radius: 15px; overflow: hidden; transition: 0.3s; cursor: pointer; }
        .card-especial:hover { transform: translateY(-10px); border-color: var(--oro); }
        .card-especial img { width: 100%; height: 250px; object-fit: cover; }
        .card-especial div { padding: 20px; }
        .card-especial h2 { font-family: 'Playfair Display', serif; margin: 0 0 10px 0; font-size: 24px; }

        .btn-volver { position: fixed; top: 20px; left: 20px; background: var(--azul); color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; font-weight: bold; z-index: 100; }

        /* MODAL */
        .modal-overlay { display: none; position: fixed; z-index: 11000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.95); overflow-y: auto; }
        .modal-box { background: #1e1e1e; max-width: 900px; margin: 40px auto; padding: 40px; border-radius: 12px; position: relative; border: 1px solid #333; }
        .modal-close { position: absolute; right: 20px; top: 10px; font-size: 40px; color: #fff; cursor: pointer; line-height: 1; background: none; border: none; }
        .modal-img { width: 100%; max-height: 400px; object-fit: cover; border-radius: 8px; margin-bottom: 25px; }
        .modal-titulo { font-family: 'Playfair Display', serif; font-size: 32px; color: #fff; margin: 0 0 20px 0; line-height: 1.2; }
        .modal-contenido { color: #ccc; font-size: 16px; line-height: 1.8; border-top: 1px solid #333; padding-top: 20px; }

        @media (max-width: 768px) {
            .modal-box { margin: 10px; padding: 20px; }
            .modal-titulo { font-size: 22px; }
            .header-revista h1 { font-size: 32px; }
        }
    </style>
</head>
<body>

<a href="index.php" class="btn-volver"><i class="fas fa-arrow-left"></i> Volver al Inicio</a>

<div class="header-revista">
    <p>EDICIÓN EXCLUSIVA</p>
    <h1>HISTORIAS PLEC</h1>
    <div style="width: 50px; height: 3px; background: var(--oro); margin: 20px auto;"></div>
</div>

<main class="grid-revista">
    <?php
    $sql = "SELECT * FROM noticias WHERE es_especial = 1 ORDER BY id DESC";
    $res = mysqli_query($conn, $sql);

    if($res && mysqli_num_rows($res) > 0) {
        while($row = mysqli_fetch_assoc($res)) {
            $img      = htmlspecialchars($row['imagen'] ?? 'img/placeholder.svg');
            $titulo   = htmlspecialchars($row['titulo']);
            $resumen  = htmlspecialchars(substr(strip_tags($row['contenido']), 0, 150));
            $titulo_j = htmlspecialchars(json_encode($row['titulo']),   ENT_QUOTES, 'UTF-8');
            $img_j    = htmlspecialchars(json_encode($row['imagen']),   ENT_QUOTES, 'UTF-8');
            $cont_j   = htmlspecialchars(json_encode($row['contenido']),ENT_QUOTES, 'UTF-8');
            $video_j  = htmlspecialchars(json_encode($row['video_url'] ?? ''),ENT_QUOTES, 'UTF-8');
            echo "
            <div class='card-especial' onclick='abrirCronica($titulo_j, $img_j, $cont_j, $video_j)'>
                <img src='$img' alt='Imagen' onerror=\"this.src='img/placeholder.svg'\">
                <div>
                    <h2>$titulo</h2>
                    <p style='color:#bbb; font-size:14px;'>$resumen...</p>
                    <span style='color:var(--oro); font-weight:bold;'>Leer crónica completa →</span>
                </div>
            </div>";
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
<div class="modal-overlay" id="modalCronica">
    <div class="modal-box">
        <button class="modal-close" onclick="cerrarCronica()">&times;</button>
        <img id="cronica-img" src="" class="modal-img" onerror="this.style.display='none'">
        <h2 id="cronica-titulo" class="modal-titulo"></h2>
        <div id="cronica-contenido" class="modal-contenido"></div>
    </div>
</div>

<script>
function abrirCronica(titulo, img, contenido, video) {
    document.getElementById('cronica-titulo').textContent   = titulo;
    const videoHtml = video ? `<div style="margin:20px 0; position:relative; width:100%; padding-bottom:56.25%; border-radius:8px; overflow:hidden;"><iframe src="${video}" style="position:absolute; top:0; left:0; width:100%; height:100%; border:none;" allowfullscreen loading="lazy"></iframe></div>` : '';
    document.getElementById('cronica-contenido').innerHTML = videoHtml + '<div style="color:#ccc; font-size:16px; line-height:1.8; white-space:pre-wrap;">' + contenido + '</div>';
    const imgEl = document.getElementById('cronica-img');
    if (img) {
        imgEl.src = img;
        imgEl.style.display = 'block';
    } else {
        imgEl.style.display = 'none';
    }
    document.getElementById('modalCronica').style.display = 'block';
    document.body.style.overflow = 'hidden';
}
function cerrarCronica() {
    document.getElementById('modalCronica').style.display = 'none';
    document.body.style.overflow = '';
}
// Cerrar al hacer click fuera del modal
document.getElementById('modalCronica').addEventListener('click', function(e) {
    if (e.target === this) cerrarCronica();
});
</script>
</body>
</html>