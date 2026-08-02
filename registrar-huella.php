<?php
require_once __DIR__ . '/webauthn-config.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

// Listar los dispositivos ya registrados, para poder borrarlos si hace falta
$admin_id = intval($_SESSION['admin_id']);
$dispositivos = [];
$q = mysqli_query($conn, "SELECT id, nombre_dispositivo, creado_en FROM passkeys WHERE admin_id = $admin_id ORDER BY creado_en DESC");
while ($fila = mysqli_fetch_assoc($q)) {
    $dispositivos[] = $fila;
}

// Borrar un dispositivo (opcional, vía ?borrar=ID)
if (isset($_GET['borrar'])) {
    $id_borrar = intval($_GET['borrar']);
    mysqli_query($conn, "DELETE FROM passkeys WHERE id = $id_borrar AND admin_id = $admin_id");
    header('Location: registrar-huella.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar huella - Noticias PLEC</title>
    <style>
        body { font-family: 'Libre Franklin', sans-serif; background:#0a0a0a; color:#fff; padding:30px; max-width:560px; margin:0 auto; }
        h2 { margin-bottom: 6px; }
        p.subtitulo { color:#999; margin-bottom: 24px; font-size:14px; }
        .btn { background:#1a73e8; color:#fff; border:none; padding:12px 20px; border-radius:8px; font-size:14px; cursor:pointer; }
        .btn:hover { background:#1558b0; }
        .lista { margin-top:24px; }
        .item { display:flex; justify-content:space-between; align-items:center; background:#1a1a1a; padding:12px 16px; border-radius:8px; margin-bottom:8px; }
        .item a { color:#ff4d4d; text-decoration:none; font-size:13px; }
        .mensaje { padding:10px 14px; border-radius:8px; margin-bottom:16px; font-size:14px; }
        .ok { background:rgba(0,200,120,0.12); color:#2ecc71; }
        .error { background:rgba(255,60,60,0.12); color:#ff6b6b; }
        a.volver { color:#999; font-size:13px; display:inline-block; margin-top:24px; }
        input#nombreDispositivo { padding:10px; border-radius:8px; border:1px solid #333; background:#111; color:#fff; margin-bottom:12px; width:100%; }
    </style>
</head>
<body>
    <h2>🔐 Registrar huella / Face ID</h2>
    <p class="subtitulo">Conectado como <b><?php echo htmlspecialchars($_SESSION['admin_nombre']); ?></b>. Registra este dispositivo para entrar sin escribir la clave la próxima vez.</p>

    <div id="mensaje"></div>

    <input type="text" id="nombreDispositivo" placeholder="Nombre para este dispositivo (ej: Celular, PC oficina)">
    <button class="btn" id="btnRegistrar">Registrar huella de este dispositivo</button>

    <div class="lista">
        <?php if (empty($dispositivos)): ?>
            <p style="color:#777; font-size:13px;">Todavía no tienes ninguna huella registrada.</p>
        <?php else: ?>
            <?php foreach ($dispositivos as $d): ?>
                <div class="item">
                    <span><?php echo htmlspecialchars($d['nombre_dispositivo']); ?> — <small style="color:#777;"><?php echo $d['creado_en']; ?></small></span>
                    <a href="?borrar=<?php echo $d['id']; ?>" onclick="return confirm('¿Quitar esta huella?');">Quitar</a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <a class="volver" href="admin.php">← Volver al panel</a>

    <script>
    function bufferDecode(value) {
        value = value.replace(/-/g, '+').replace(/_/g, '/');
        while (value.length % 4) value += '=';
        return Uint8Array.from(atob(value), c => c.charCodeAt(0));
    }
    function bufferEncode(buffer) {
        let binario = '';
        const bytes = new Uint8Array(buffer);
        for (let b of bytes) binario += String.fromCharCode(b);
        return btoa(binario).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    }

    document.getElementById('btnRegistrar').addEventListener('click', async function() {
        const mensaje = document.getElementById('mensaje');
        mensaje.innerHTML = '';

        if (!window.PublicKeyCredential) {
            mensaje.innerHTML = '<div class="mensaje error">Este navegador no soporta huella/Face ID.</div>';
            return;
        }

        try {
            const resp = await fetch('webauthn-register-begin.php', { method: 'POST' });
            const options = await resp.json();
            if (options.error) throw new Error(options.error);

            const publicKey = options.publicKey;
            publicKey.challenge = bufferDecode(publicKey.challenge);
            publicKey.user.id = bufferDecode(publicKey.user.id);
            if (publicKey.excludeCredentials) {
                publicKey.excludeCredentials = publicKey.excludeCredentials.map(c => ({
                    ...c, id: bufferDecode(c.id)
                }));
            }

            const credential = await navigator.credentials.create({ publicKey });

            const nombreDispositivo = document.getElementById('nombreDispositivo').value || 'Dispositivo sin nombre';

            const resp2 = await fetch('webauthn-register-finish.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    clientDataJSON: bufferEncode(credential.response.clientDataJSON),
                    attestationObject: bufferEncode(credential.response.attestationObject),
                    nombreDispositivo: nombreDispositivo
                })
            });
            const resultado = await resp2.json();
            if (resultado.error) throw new Error(resultado.error);

            mensaje.innerHTML = '<div class="mensaje ok">✅ Huella registrada con éxito.</div>';
            setTimeout(() => location.reload(), 1200);

        } catch (err) {
            mensaje.innerHTML = '<div class="mensaje error">' + err.message + '</div>';
        }
    });
    </script>
</body>
</html>