<?php
$host = "localhost";
$user = "root";
$password = "";
$db = "noticias_plec";

// Conexión procedimental correcta para que sea compatible con index.php
$conn = mysqli_connect($host, $user, $password, $db);

if (!$conn) {
    die("Error de conexión: " . mysqli_connect_error());
}

// Configurar juego de caracteres para evitar problemas con tildes y eñes
mysqli_set_charset($conn, "utf8mb4");
?>