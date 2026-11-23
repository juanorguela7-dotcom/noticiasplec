<?php
session_start();
include("conexion.php");
$mensaje = $_SESSION['mensaje_impreso'] ?? null;
unset($_SESSION['mensaje_impreso']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edición Impresa - PLEC</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=Libre+Franklin:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script>document.documentElement.classList.add('plec-js');</script>
    <style>
        :root { --azul: #1a73e8; --bg: #121212; --card: #1e1e1e; --rojo: #ff4d4d; }
        body { font-family: 'Libre Franklin', sans-serif; margin: 0; background: var(--bg); color: #e0e0e0; overflow-x: hidden; }

        header.plec-hero { border-bottom: 3px solid var(--azul); min-height: 200px; }
        .logo-header { height: 60px; border-radius: 6px; box-shadow: 0 12px 26px -10px rgba(0,0,0,.8); }
        header h1 { font-family: 'Playfair Display', serif; margin: 12px 0 0; font-size: 38px; color: #fff;
            text-shadow: 0 1px 0 #2b4f8f, 0 2px 0 #24447c, 0 3px 0 #1d3969, 0 4px 0 #172e56, 0 10px 18px rgba(0,0,0,.55); }
        .back-btn { position: absolute; top: 22px; left: 20px; color: #fff; text-decoration: none; font-weight: bold; font-size: 14px; z-index: 2; display: inline-flex; gap: 6px; align-items: center; transition: gap .3s; }
        .back-btn:hover { gap: 10px; }

        .container { max-width: 1200px; margin: 0 auto; padding: 40px 20px 60px; }
        .grid-impresos { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 30px; }

        .edicion-card { background: var(--card); padding: 15px; border-radius: 14px; text-align: center; border: 1px solid #2a2a2a; }
        .portada-wrap { overflow: hidden; border-radius: 8px; margin-bottom: 15px; }
        .portada-img { width: 100%; height: 380px; object-fit: cover; border: 1px solid #333; display: block; border-radius: 8px; }
        .portada-placeholder { width: 100%; height: 380px; background: #2a2a2a; border-radius: 8px; margin-bottom: 15px; display: flex; align-items: center; justify-content: center; color: #555; font-size: 13px; }
        .edicion-card h3 { font-family: 'Playfair Display', serif; margin: 6px 0; }

        .btn-leer { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 10px 25px; background: linear-gradient(135deg, #ff4d4d, #cc0000); color: #fff; text-decoration: none; border-radius: 30px; font-weight: bold; width: 80%; box-sizing: border-box; transition: transform .3s cubic-bezier(.2,.8,.2,1), box-shadow .3s; }
        .btn-leer:hover { transform: translateY(-2px); box-shadow: 0 12px 24px -10px rgba(255,77,77,.8); }

        .aviso { max-width: 1200px; margin: 20px auto 0; padding: 12px 16px; border-radius: 10px; background: rgba(46,204,113,.12); color: #2ecc71; box-sizing: border-box; }

        @media (max-width: 768px) {
            .grid-impresos { grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; }
            .portada-img, .portada-placeholder { height: 280px; }
            header h1 { font-size: 28px; }
        }
    </style>
    <link rel="stylesheet" href="assets/css/plec-3d.css?v=3">
</head>
<body>

<header class="plec-hero">
    <canvas class="plec-globe" aria-hidden="true"></canvas>
    <a href="index.php" class="back-btn"><i class="fas fa-arrow-left"></i> VOLVER</a>
    <div class="plec-logo-wrap">
        <img src="img/logo-plec.jpg" alt="Noticias PLEC" class="logo-header" onerror="this.style.display='none'">
        <h1>EDICIÓN IMPRESA</h1>
    </div>
</header>

<?php if ($mensaje && $mensaje['tipo'] === 'ok'): ?>
    <div class="aviso"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($mensaje['texto']); ?></div>
<?php endif; ?>

<div class="container">
    <div class="grid-impresos">
        <?php
        $resultado = mysqli_query($conn, "SELECT * FROM impresos ORDER BY id DESC");

        if ($resultado && mysqli_num_rows($resultado) > 0) {
            while ($row = mysqli_fetch_assoc($resultado)) {
                // Construir ruta de portada sin duplicar "img/"
                $portada = trim($row['portada'] ?? '');
                $pdf = trim($row['archivo_pdf'] ?? '');
                ?>
                <div class="edicion-card">
                    <?php if ($portada === ''): ?>
                        <div class="portada-placeholder"><i class="fas fa-image fa-2x"></i></div>
                    <?php else:
                        $ruta_img = (strpos($portada, 'img/') === 0 || strpos($portada, 'http') === 0 || strpos($portada, '/') === 0) ? $portada : 'img/' . $portada; ?>
                        <div class="portada-wrap">
                            <img src="<?php echo htmlspecialchars($ruta_img); ?>" alt="Portada edición <?php echo htmlspecialchars($row['edicion_nro']); ?>" class="portada-img" loading="lazy"
                                 onerror="this.onerror=null;this.src='img/placeholder.svg'">
                        </div>
                    <?php endif; ?>
                    <h3>Edición #<?php echo htmlspecialchars($row['edicion_nro']); ?></h3>
                    <p style="color: #888; font-size: 13px;"><?php echo !empty($row['fecha']) ? date("d/m/Y", strtotime($row['fecha'])) : ''; ?></p>
                    <?php if ($pdf !== ''): ?>
                        <a href="<?php echo htmlspecialchars($pdf); ?>" target="_blank" rel="noopener" class="btn-leer">
                            <i class="fas fa-file-pdf"></i> LEER EDICIÓN
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
<script src="assets/js/plec-3d.js?v=3" defer></script>
</body>
</html>
