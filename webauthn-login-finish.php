<?php
require_once __DIR__ . '/webauthn-config.php';

if (!isset($_SESSION['webauthn_challenge'])) {
    passkey_error('No hay un acceso en curso. Vuelve a intentarlo.', 400);
}

$body = json_decode(file_get_contents('php://input'));
if (!$body) {
    passkey_error('Datos inválidos.', 400);
}

try {
    $credentialIdB64url = str_replace(['+', '/'], ['-', '_'], $body->credentialId);
    $credentialIdB64url = rtrim($credentialIdB64url, '=');

    $cred_esc = mysqli_real_escape_string($conn, $credentialIdB64url);
    $q = mysqli_query($conn, "SELECT * FROM passkeys WHERE credential_id = '$cred_esc' LIMIT 1");
    $fila = $q ? mysqli_fetch_assoc($q) : null;

    if (!$fila) {
        passkey_error('Esa huella no está registrada en el sistema.', 404);
    }

    $clientDataJSON    = base64_decode(strtr($body->clientDataJSON, '-_', '+/'));
    $authenticatorData = base64_decode(strtr($body->authenticatorData, '-_', '+/'));
    $signature         = base64_decode(strtr($body->signature, '-_', '+/'));

    $nuevoContador = $WebAuthn->processGet(
        $clientDataJSON,
        $authenticatorData,
        $signature,
        $fila['public_key'],
        $_SESSION['webauthn_challenge'],
        intval($fila['sign_count']),
        false, // requireUserVerification
        true   // requireUserPresent
    );

    // Actualizar el contador anti-clonación
    mysqli_query($conn, "UPDATE passkeys SET sign_count = " . intval($nuevoContador) . " WHERE id = " . intval($fila['id']));

    // Traer los datos del admin dueño de esta huella
    $admin_id = intval($fila['admin_id']);
    $qu = mysqli_query($conn, "SELECT * FROM usuarios WHERE id = $admin_id LIMIT 1");
    $u = $qu ? mysqli_fetch_assoc($qu) : null;

    if (!$u) {
        passkey_error('El usuario dueño de esta huella ya no existe.', 404);
    }

    // ── LOGIN CORRECTO: mismo bloque de sesión que usa login.php con clave ──
    unset($_SESSION['webauthn_challenge']);
    $_SESSION['intentos'] = 0;
    $_SESSION['bloqueado_hasta'] = 0;
    session_regenerate_id(true);

    $_SESSION['admin_id']       = $u['id'];
    $_SESSION['admin_nombre']   = $u['nombre'];
    $_SESSION['admin_login_at'] = time();
    $_SESSION['admin_last_act'] = time();

    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'redirect' => 'admin.php']);

} catch (Throwable $e) {
    passkey_error('No se pudo verificar la huella: ' . $e->getMessage(), 400);
}