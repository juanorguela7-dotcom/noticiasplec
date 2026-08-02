<?php
require_once __DIR__ . '/webauthn-config.php';

// Si el navegador soporta "resident keys" (activado en el registro),
// el usuario ni siquiera necesita escribir su nombre de usuario: el propio
// gestor de contraseñas del sistema le muestra qué cuenta usar.
// Por eso aquí NO exigimos el campo "usuario"; si viene vacío, mostramos
// todas las huellas disponibles y el navegador/OS resuelve cuál usar.

$body = json_decode(file_get_contents('php://input'));
$usuario = isset($body->usuario) ? trim($body->usuario) : '';

$credencialesPermitidas = [];

if ($usuario !== '') {
    $usuario_esc = mysqli_real_escape_string($conn, $usuario);
    $q = mysqli_query($conn, "SELECT id FROM usuarios WHERE nombre = '$usuario_esc' LIMIT 1");
    $u = $q ? mysqli_fetch_assoc($q) : null;

    if ($u) {
        $qp = mysqli_query($conn, "SELECT credential_id FROM passkeys WHERE admin_id = " . intval($u['id']));
        while ($fila = mysqli_fetch_assoc($qp)) {
            $credencialesPermitidas[] = \lbuchs\WebAuthn\Binary\ByteBuffer::fromBase64Url($fila['credential_id']);
        }
    }

    if (empty($credencialesPermitidas)) {
        passkey_error('Ese usuario no tiene ninguna huella registrada.', 404);
    }
}

try {
    $getArgs = $WebAuthn->getGetArgs(
        $credencialesPermitidas, // vacío = usar resident keys (sin pedir usuario)
        20,     // timeout
        true,   // allowUsb
        true,   // allowNfc
        true,   // allowBle
        true,   // allowInternal (huella/Face ID del propio equipo)
        'preferred'
    );
} catch (Throwable $e) {
    passkey_error('No se pudo iniciar el acceso con huella: ' . $e->getMessage(), 500);
}

$_SESSION['webauthn_challenge'] = $WebAuthn->getChallenge();

header('Content-Type: application/json');
echo json_encode($getArgs);