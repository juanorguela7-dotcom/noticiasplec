<?php
include("conexion.php");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $edicion = mysqli_real_escape_string($conn, $_POST['edicion_nro']);
    $fecha = $_POST['fecha'];

    // Configuración de rutas
    $folder_img = "img/";
    $folder_pdf = "pdf/";

    // Procesar Imagen de Portada
    $img_name = time() . "_" . $_FILES['portada']['name'];
    $img_tmp = $_FILES['portada']['tmp_name'];
    $img_destino = $folder_img . $img_name;

    // Procesar PDF
    $pdf_name = time() . "_" . $_FILES['archivo_pdf']['name'];
    $pdf_tmp = $_FILES['archivo_pdf']['tmp_name'];
    $pdf_destino = $folder_pdf . $pdf_name;

    // Intentar mover los archivos a las carpetas
    if (move_uploaded_file($img_tmp, $img_destino) && move_uploaded_file($pdf_tmp, $pdf_destino)) {
        
        // Guardar en la tabla 'impresos'
        $sql = "INSERT INTO impresos (edicion_nro, portada, archivo_pdf, fecha) 
                VALUES ('$edicion', '$img_destino', '$pdf_destino', '$fecha')";

        if (mysqli_query($conn, $sql)) {
            echo "<script>alert('Edición #$edicion publicada correctamente'); window.location='impresos.php';</script>";
        } else {
            echo "Error en Base de Datos: " . mysqli_error($conn);
        }
    } else {
        echo "Error: No se pudieron subir los archivos. Verifica que las carpetas img/ y pdf/ existan y tengan permisos.";
    }
}
?>