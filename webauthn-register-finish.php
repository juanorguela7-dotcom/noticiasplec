<?php
require_once __DIR__ . '/webauthn-config.php';

if (!isset($_SESSION['admin_id'])) {
    passkey_error('Debes iniciar sesión primero.', 401);
}
if (!isset($_SESSION['webauthn_challenge'])) {
    passkey_error('No hay un registro en curso. Vuelve a intentarlo.', 400);
}

$body = json_decode(file_get_contents('php://input'));
if (!$body) {
    passkey_error('Datos inválidos.', 400);
}

try {
    $clientDataJSON    = base64_decode(strtr($body->clientDataJSON, '-_', '+/'));
    $attestationObject  = base64_decode(strtr($body->attestationObject, '-_', '+/'));

    $data = $WebAuthn->processCreate(
        $clientDataJSON,
        $attestationObject,
        $_SESSION['webauthn_challenge'],
        false,  // requireUserVerification
        true,   // requireUserPresent
        false   // failIfRootMismatch
    );

    $admin_id      = intval($_SESSION['admin_id']);
    $credential_id = $data->credentialId->getBase64Url();
    $public_key    = $data->credentialPublicKey;
    $sign_count    = $data->signatureCounter ?? 0;
    $nombre_disp   = isset($body->nombreDispositivo)
        ? trim(mysqli_real_escape_string($conn, $body->nombreDispositivo))
        : 'Dispositivo sin nombre';

    $cred_esc = mysqli_real_escape_string($conn, $credential_id);
    $pk_esc   = mysqli_real_escape_string($conn, $public_key);

    mysqli_query($conn, "
        INSERT INTO passkeys (admin_id, credential_id, public_key, sign_count, nombre_dispositivo)
        VALUES ($admin_id, '$cred_esc', '$pk_esc', " . intval($sign_count) . ", '$nombre_disp')
    ");

    unset($_SESSION['webauthn_challenge']);

    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);

} catch (Throwable $e) {
    passkey_error('No se pudo verificar la huella: ' . $e->getMessage(), 400);
}