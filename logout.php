<?php
session_start();
session_unset(); // Borra las variables de sesión de la memoria
session_destroy(); // Destruye la sesión por completo

header("Location: login.php"); // Redirige al login
exit();
?>