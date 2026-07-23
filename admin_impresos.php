<?php include("conexion.php"); ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel Admin - Subir Edición Impresa</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; margin: 0; padding: 20px; }
        .container { max-width: 500px; margin: 50px auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        h2 { color: #1a73e8; text-align: center; margin-bottom: 25px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-weight: bold; margin-bottom: 8px; color: #333; }
        input[type="text"], input[type="date"], input[type="file"] {
            width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box; font-size: 14px;
        }
        button {
            width: 100%; background: #1a73e8; color: white; border: none; padding: 15px;
            border-radius: 8px; font-size: 16px; font-weight: bold; cursor: pointer; transition: 0.3s;
        }
        button:hover { background: #1557b0; }
        .footer-link { text-align: center; margin-top: 20px; }
        .footer-link a { color: #666; text-decoration: none; font-size: 14px; }
    </style>
</head>
<body>

<div class="container">
    <h2><i class="fas fa-newspaper"></i> Nueva Edición Impresa</h2>
    
    <form action="guardar_impreso.php" method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label>Número de Edición:</label>
            <input type="text" name="edicion_nro" placeholder="Ejemplo: 105" required>
        </div>

        <div class="form-group">
            <label>Fecha de Publicación:</label>
            <input type="date" name="fecha" required>
        </div>

        <div class="form-group">
            <label>Imagen de Portada (JPG o PNG):</label>
            <input type="file" name="portada" accept="image/*" required>
        </div>

        <div class="form-group">
            <label>Archivo del Periódico (PDF):</label>
            <input type="file" name="archivo_pdf" accept="application/pdf" required>
        </div>

        <button type="submit">PUBLICAR EDICIÓN</button>
    </form>

    <div class="footer-link">
        <a href="index.php">← Volver al Portal de Noticias</a>
    </div>
</div>

</body>
</html>