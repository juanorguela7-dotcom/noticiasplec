<?php
// ── CONEXIÓN A LA BASE DE DATOS ──
// Las credenciales se leen de las variables de entorno que Railway inyecta
// automáticamente al enlazar el servicio MySQL (MYSQLHOST, MYSQLUSER, ...).
// Los valores de respaldo solo existen para no tumbar el sitio mientras se
// configuran esas variables: cuando estén puestas en Railway, ROTA la
// contraseña de MySQL y borra los valores de respaldo de este archivo.

function plec_env($claves, $defecto = '', $permitir_vacio = false) {
    foreach ((array)$claves as $clave) {
        $v = getenv($clave);
        if ($v === false && isset($_ENV[$clave]))    $v = $_ENV[$clave];
        if ($v === false && isset($_SERVER[$clave])) $v = $_SERVER[$clave];
        if ($v !== false && ($v !== '' || $permitir_vacio)) return $v;
    }
    return $defecto;
}

$host     = plec_env(['MYSQLHOST', 'DB_HOST'], 'mysql.railway.internal');
$user     = plec_env(['MYSQLUSER', 'DB_USER'], 'root');
// Vacío permitido solo si la variable está definida (p. ej. XAMPP local sin clave)
$password = plec_env(['MYSQLPASSWORD', 'DB_PASSWORD'], 'fypBMcEqPSzfbinvVsFHxvIBnMdhLtsP', true);
$db       = plec_env(['MYSQLDATABASE', 'MYSQL_DATABASE', 'DB_NAME'], 'noticias_plec');
$port     = (int) plec_env(['MYSQLPORT', 'DB_PORT'], '3306');

// Configuración local (XAMPP): si existe conexion.local.php, sus valores mandan.
// Ese archivo NO se sube a git (ver .gitignore), así que en Railway no existe.
if (file_exists(__DIR__ . '/conexion.local.php')) {
    include __DIR__ . '/conexion.local.php';
}

// Dominio público del sitio (para Open Graph, sitemap y enlaces compartidos)
if (!defined('PLEC_DOMINIO')) {
    define('PLEC_DOMINIO', rtrim(plec_env('SITE_URL', 'https://noticiasplec-production.up.railway.app'), '/'));
}

try {
    $conn = mysqli_connect($host, $user, $password, $db, $port);
} catch (Throwable $e) {
    $conn = false;
}

if (!$conn) {
    // No mostrar detalles técnicos al visitante (pueden revelar el host o el usuario)
    error_log('Noticias PLEC - error de conexión MySQL: ' . mysqli_connect_error());
    http_response_code(503);
    die("El sitio está en mantenimiento. Intenta de nuevo en unos minutos.");
}

mysqli_set_charset($conn, "utf8mb4");
