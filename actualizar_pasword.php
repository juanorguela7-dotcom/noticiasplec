<?php
// ══════════════════════════════════════════════════════
// SCRIPT DE USO ÚNICO — ELIMÍNALO DESPUÉS DE EJECUTARLO
// Actualiza la contraseña del admin con hash seguro
// ══════════════════════════════════════════════════════
include("conexion.php");

$nuevo_hash = password_hash('Leandro1106772624', PASSWORD_BCRYPT);

// Actualiza el usuario cuyo nombre es fleybherMartinez
$sql = "UPDATE usuarios SET password = '$nuevo_hash' WHERE nombre = 'fleybherMartinez'";

if (mysqli_query($conn, $sql) && mysqli_affected_rows($conn) > 0) {
    echo "<h2 style='color:green;'>✅ Contraseña actualizada correctamente con hash seguro.</h2>";
    echo "<p>Ya puedes <a href='login.php'>ir al login</a> y eliminar este archivo del servidor.</p>";
    echo "<pre>Hash generado: $nuevo_hash</pre>";
} else {
    echo "<h2 style='color:red;'>❌ No se encontró el usuario o hubo un error.</h2>";
    echo "<p>Error: " . mysqli_error($conn) . "</p>";
    echo "<p>Verifica que el campo <b>nombre</b> del usuario en la tabla <b>usuarios</b> sea exactamente: <b>fleybherMartinez</b></p>";
}
?>