<?php
require_once __DIR__ . '/webauthn-config.php';

// Solo un admin YA logueado (con usuario+clave+PIN) puede registrar una huella nueva.
if (!isset($_SESSION['admin_id'])) {
    passkey_error('Debes iniciar sesión primero.', 401);
}

$admin_id     = $_SESSION['admin_id'];
$admin_nombre = $_SESSION['admin_nombre'];

// Traer las credenciales que ya tenga registradas, para no duplicar el mismo
// dispositivo y para que el navegador no lo vuelva a ofrecer.
$admin_id_esc = intval($admin_id);
$q = mysqli_query($conn, "SELECT credential_id FROM passkeys WHERE admin_id = $admin_id_esc");
$excluir = [];
while ($fila = mysqli_fetch_assoc($q)) {
    $excluir[] = \lbuchs\WebAuthn\Binary\ByteBuffer::fromBase64Url($fila['credential_id']);
}

try {
    $createArgs = $WebAuthn->getCreateArgs(
        (string)$admin_id,   // id de usuario para WebAuthn
        $admin_nombre,       // nombre de usuario
        $admin_nombre,       // nombre para mostrar
        20,                  // timeout en segundos... la librería lo convierte a ms
        true,                // requireResidentKey: permite iniciar sesión sin escribir usuario
        'preferred'          // userVerification
    );
} catch (Throwable $e) {
    passkey_error('No se pudo iniciar el registro: ' . $e->getMessage(), 500);
}

// Excluir credenciales ya registradas, si las hay
if (!empty($excluir) && isset($createArgs->publicKey)) {
    $createArgs->publicKey->excludeCredentials = array_map(function ($id) {
        return (object)['type' => 'public-key', 'id' => $id];
    }, $excluir);
}

// Guardamos el reto y el objeto WebAuthn en sesión para verificarlo en el paso 2
$_SESSION['webauthn_challenge'] = $WebAuthn->getChallenge();

header('Content-Type: application/json');
echo json_encode($createArgs);