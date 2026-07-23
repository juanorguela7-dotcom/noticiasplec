<?php
$host     = getenv('MYSQLHOST')     ?: 'localhost';
$user     = getenv('MYSQLUSER')     ?: 'root';
$password = getenv('MYSQLPASSWORD') ?: '';
$db       = getenv('MYSQLDATABASE') ?: 'noticias_plec';
$port     = getenv('MYSQLPORT')     ?: '3306';

// Conexión procedimental adaptada para Railway y XAMPP
$conn = mysqli_connect($host, $user, $password, $db, (int)$port);

if (!$conn) {
    die("Error de conexión: " . mysqli_connect_error());
}

// Configurar juego de caracteres para evitar problemas con tildes y eñes
mysqli_set_charset($conn, "utf8mb4");
?>