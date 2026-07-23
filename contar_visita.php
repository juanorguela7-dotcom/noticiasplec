<?php
include("conexion.php");

if (isset($_POST['id'])) {
    $id = intval($_POST['id']);
    // Sumamos la visita en la base de datos
    mysqli_query($conn, "UPDATE noticias SET visitas = visitas + 1 WHERE id = $id");
}
?>