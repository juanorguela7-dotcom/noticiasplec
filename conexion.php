<?php
$host     = 'mysql.railway.internal';
$user     = 'root';
$password = 'fypBMcEqPSzfbinvVsFHxvIBnMdhLtsP';
$db       = 'noticias_plec';
$port     = 3306;

$conn = mysqli_connect($host, $user, $password, $db, $port);

if (!$conn) {
    die("Error de conexión: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8mb4");
?>