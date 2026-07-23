<?php 
include("conexion.php"); 
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Edición Impresa - PLEC</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=Libre+Franklin:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --azul: #1a73e8; --bg: #121212; --card: #1e1e1e; }
        body { font-family: 'Libre Franklin', sans-serif; margin: 0; background: var(--bg); color: #e0e0e0; }

        header { padding: 30px; text-align: center; border-bottom: 3px solid var(--azul); background: #181818; position: relative; }
        .logo-header { height: 60px; border-radius: 5px; }
        .back-btn { position: absolute; top: 35px; left: 20px; color: #fff; text-decoration: none; font-weight: bold; font-size: 14px; }

        .container { max-width: 1200px; margin: 0 auto; padding: 40px 20px; }
        .grid-impresos { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 30px; }

        .edicion-card { background: var(--card); padding: 15px; border-radius: 10px; text-align: center; border: 1px solid #2a2a2a; transition: 0.3s; }
        .edicion-card:hover { border-color: var(--azul); transform: translateY(-3px); }
        .portada-img { width: 100%; height: 380px; object-fit: cover; border-radius: 5px; margin-bottom: 15px; border: 1px solid #333; display: block; }
        .portada-placeholder { width: 100%; height: 380px; background: #2a2a2a; border-radius: 5px; margin-bottom: 15px; display: flex; align-items: center; justify-content: center; color: #555; font-size: 13px; }

        .btn-leer { display: inline-block; padding: 10px 25px; background: #ff4d4d; color: #fff; text-decoration: none; border-radius: 5px; font-weight: bold; width: 80%; transition: 0.3s; }
        .btn-leer:hover { background: #cc0000; }

        @media (max-width: 768px) {
            .grid-impresos { grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; }
            .portada-img { height: 280px; }
        }
    </style>
</head>
<body>

<header>
    <a href="index.php" class="back-btn"><i class="fas fa-arrow-left"></i> VOLVER</a>
    <img src="img/logo-plec.jpg" class="logo-header" onerror="this.style.display='none'">
    <h1 style="font-family: 'Playfair Display'; margin: 10px 0;">EDICIÓN IMPRESA</h1>
</header>

<div class="container">
    <div class="grid-impresos">
        <?php
        $query = "SELECT * FROM impresos ORDER BY id DESC";
        $resultado = mysqli_query($conn, $query);

        if($resultado && mysqli_num_rows($resultado) > 0) {
            while($row = mysqli_fetch_assoc($resultado)) {

                // Construir ruta de portada sin duplicar "img/"
                $portada = trim($row['portada']);
                if (empty($portada)) {
                    $img_html = "<div class='portada-placeholder'><i class='fas fa-image fa-2x'></i></div>";
                } else {
                    // Si ya viene con ruta completa (img/... o http...) la usamos tal cual
                    // Si solo viene el nombre del archivo, le agregamos img/
                    if (strpos($portada, 'img/') === 0 || strpos($portada, 'http') === 0 || strpos($portada, '/') === 0) {
                        $ruta_img = $portada;
                    } else {
                        $ruta_img = 'img/' . $portada;
                    }
                    $img_html = "<img src='" . htmlspecialchars($ruta_img) . "' class='portada-img' 
                                      onerror=\"this.style.display='none'; this.nextElementSibling.style.display='flex';\">
                                 <div class='portada-placeholder' style='display:none;'>
                                     <span><i class='fas fa-image fa-2x'></i><br>Sin portada</span>
                                 </div>";
                }

                // Ruta del PDF
                $pdf = htmlspecialchars($row['archivo_pdf'] ?? '');
                ?>
                <div class="edicion-card">
                    <?php echo $img_html; ?>
                    <h3 style="font-family: 'Playfair Display';">Edición #<?php echo htmlspecialchars($row['edicion_nro']); ?></h3>
                    <p style="color: #888; font-size: 13px;"><?php echo date("d/m/Y", strtotime($row['fecha'])); ?></p>
                    <?php if(!empty($pdf)): ?>
                        <a href="<?php echo $pdf; ?>" target="_blank" class="btn-leer">
                            <i class="fas fa-file-pdf me-1"></i> LEER EDICIÓN
                        </a>
                    <?php else: ?>
                        <span style="color:#555; font-size:13px;">PDF no disponible</span>
                    <?php endif; ?>
                </div>
                <?php
            }
        } else {
            echo "<div style='grid-column: 1/-1; text-align: center; padding: 50px;'>
                    <i class='fas fa-exclamation-circle' style='font-size: 40px; color: var(--azul);'></i>
                    <p>No hay ediciones publicadas aún.</p>
                  </div>";
        }
        ?>
    </div>
</div>
</body>
</html>