<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}
include("conexion.php");

// Crear tabla configuracion si no existe
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS configuracion (
    clave VARCHAR(100) PRIMARY KEY,
    valor TEXT,
    actualizado DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

$stream_url    = mysqli_real_escape_string($conn, trim($_POST['stream_url'] ?? ''));
$stream_titulo = mysqli_real_escape_string($conn, trim($_POST['stream_titulo'] ?? ''));
$stream_activo = isset($_POST['stream_activo']) ? '1' : '0';

$pares = [
    'stream_url'    => $stream_url,
    'stream_titulo' => $stream_titulo,
    'stream_activo' => $stream_activo,
];

foreach ($pares as $clave => $valor) {
    $v = mysqli_real_escape_string($conn, $valor);
    mysqli_query($conn, "INSERT INTO configuracion (clave, valor) VALUES ('$clave', '$v')
        ON DUPLICATE KEY UPDATE valor = '$v'");
}

$_SESSION['mensaje'] = ['tipo' => 'success', 'texto' => '✅ Configuración de streaming guardada correctamente.'];
header("Location: admin.php");
exit();
