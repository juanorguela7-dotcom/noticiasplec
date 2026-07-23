<?php
// ── HEADERS DE SEGURIDAD ──
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: no-referrer");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Pragma: no-cache");

// ── SESIÓN SEGURA: configurar ANTES de session_start() ──
ini_set('session.cookie_httponly', 1);   // JS no puede leer la cookie
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);
ini_set('session.gc_maxlifetime', 1800); // sesión máx. 30 min sin actividad
// En hosting con HTTPS, descomentar esta línea:
// ini_set('session.cookie_secure', 1);

session_start();
include("conexion.php");

// ── PROTECCIÓN: Si ya está logueado, al panel ──
if (isset($_SESSION['admin_id'])) {
    header("Location: admin.php");
    exit();
}

// ── BLOQUEO POR INTENTOS FALLIDOS ──
if (!isset($_SESSION['intentos'])) $_SESSION['intentos'] = 0;
if (!isset($_SESSION['bloqueado_hasta'])) $_SESSION['bloqueado_hasta'] = 0;

$bloqueado = false;
$segundos_restantes = 0;
if ($_SESSION['bloqueado_hasta'] > time()) {
    $bloqueado = true;
    $segundos_restantes = $_SESSION['bloqueado_hasta'] - time();
}

$error = '';
$bienvenida = '';
$aviso_timeout = isset($_GET['timeout']) ? true : false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$bloqueado) {

    $usuario  = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';
    $pin      = trim($_POST['pin'] ?? '');

    // 1. Validar PIN (primero, antes de tocar la BD)
    $pin_correcto = '2525'; // PIN: 25 25
    if ($pin !== $pin_correcto) {
        $_SESSION['intentos']++;
        $error = 'PIN incorrecto.';
    } else {
        // 2. Buscar usuario por nombre de usuario
        $usuario_esc = mysqli_real_escape_string($conn, $usuario);
        $q = mysqli_query($conn, "SELECT * FROM usuarios WHERE nombre = '$usuario_esc' LIMIT 1");
        $u = $q ? mysqli_fetch_assoc($q) : null;

        // Acepta texto plano Y hash bcrypt
        $password_ok = false;
        if ($u) {
            if (password_verify($password, $u['password'])) {
                $password_ok = true;
            } elseif ($u['password'] === $password) {
                $password_ok = true;
                $h = password_hash($password, PASSWORD_BCRYPT);
                $he = mysqli_real_escape_string($conn, $h);
                mysqli_query($conn, "UPDATE usuarios SET `password`='$he' WHERE id=" . intval($u['id']));
            }
        }
        if ($password_ok) {
            // ✅ LOGIN CORRECTO
            $_SESSION['intentos'] = 0;
            $_SESSION['bloqueado_hasta'] = 0;

            // ── SEGURIDAD: regenerar ID de sesión para evitar session fixation ──
            session_regenerate_id(true);

            $_SESSION['admin_id']       = $u['id'];
            $_SESSION['admin_nombre']   = $u['nombre'];
            $_SESSION['admin_login_at'] = time();       // marca de inicio de sesión
            $_SESSION['admin_last_act'] = time();       // última actividad
            header("Location: admin.php");
            exit();
        } else {
            $_SESSION['intentos']++;
            $error = 'Usuario o contraseña incorrectos.';
        }
    }

    // Bloquear tras 5 intentos fallidos (2 minutos)
    if ($_SESSION['intentos'] >= 5) {
        $_SESSION['bloqueado_hasta'] = time() + 120;
        $error = '';
        $bloqueado = true;
        $segundos_restantes = 120;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Admin - Noticias PLEC</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Libre+Franklin:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            min-height: 100vh;
            background: #0a0a0a;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Libre Franklin', sans-serif;
            padding: 20px;
        }

        .login-wrapper {
            display: flex;
            width: 100%;
            max-width: 900px;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 30px 80px rgba(0,0,0,0.7);
        }

        /* PANEL IZQUIERDO - FOTO */
        .panel-foto {
            width: 42%;
            position: relative;
            background: #111;
            flex-shrink: 0;
        }
        .panel-foto img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center top;
            display: block;
            opacity: 0.85;
        }
        .panel-foto-overlay {
            position: absolute;
            bottom: 0; left: 0; right: 0;
            background: linear-gradient(transparent, rgba(0,0,0,0.92));
            padding: 30px 25px 25px;
        }
        .panel-foto-overlay .nombre {
            font-family: 'Playfair Display', serif;
            font-size: 20px;
            color: #fff;
            font-weight: 700;
            line-height: 1.2;
        }
        .panel-foto-overlay .cargo {
            color: #1a73e8;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 4px;
        }
        .panel-foto-overlay .plec-badge {
            display: inline-block;
            background: #ff4d4d;
            color: #fff;
            font-size: 10px;
            font-weight: bold;
            padding: 2px 8px;
            border-radius: 20px;
            margin-top: 6px;
            letter-spacing: 1px;
        }

        /* PANEL DERECHO - FORMULARIO */
        .panel-form {
            flex: 1;
            background: #1a1a1a;
            padding: 45px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .logo-plec {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 30px;
        }
        .logo-plec img { height: 38px; border-radius: 4px; }
        .logo-plec span {
            font-family: 'Playfair Display', serif;
            font-size: 22px;
            color: #fff;
            font-weight: 700;
        }
        .logo-plec span b { color: #ff4d4d; }

        h2 {
            color: #fff;
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 6px;
        }
        .subtitulo {
            color: #666;
            font-size: 13px;
            margin-bottom: 28px;
        }

        .form-group { margin-bottom: 18px; }
        .form-group label {
            display: block;
            color: #888;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 7px;
        }
        .input-wrap {
            position: relative;
        }
        .input-wrap i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #555;
            font-size: 14px;
        }
        .input-wrap input {
            width: 100%;
            background: #111;
            border: 1px solid #333;
            border-radius: 8px;
            padding: 12px 14px 12px 40px;
            color: #fff;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s;
            font-family: 'Libre Franklin', sans-serif;
        }
        .input-wrap input:focus { border-color: #1a73e8; }
        .input-wrap input::placeholder { color: #444; }

        /* PIN - 4 cajitas */
        .pin-boxes {
            display: flex;
            gap: 10px;
        }
        .pin-boxes input {
            width: 52px;
            height: 52px;
            background: #111;
            border: 1px solid #333;
            border-radius: 8px;
            color: #fff;
            font-size: 22px;
            font-weight: bold;
            text-align: center;
            outline: none;
            transition: border-color 0.2s;
            -webkit-text-security: disc;
        }
        .pin-boxes input:focus { border-color: #1a73e8; }

        .btn-login {
            width: 100%;
            background: linear-gradient(135deg, #1a73e8, #0d47a1);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 13px;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 8px;
            transition: opacity 0.2s;
            font-family: 'Libre Franklin', sans-serif;
            letter-spacing: 0.5px;
        }
        .btn-login:hover { opacity: 0.9; }
        .btn-login:disabled { opacity: 0.4; cursor: not-allowed; }

        .alerta {
            background: rgba(255, 77, 77, 0.12);
            border: 1px solid #ff4d4d;
            color: #ff7070;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 13px;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .alerta-bloqueo {
            background: rgba(255, 150, 0, 0.1);
            border-color: #ff9600;
            color: #ffb347;
        }

        .seguridad-info {
            margin-top: 20px;
            display: flex;
            align-items: center;
            gap: 6px;
            color: #444;
            font-size: 11px;
        }
        .seguridad-info i { color: #1a73e8; }

        /* ===================== RESPONSIVE MEJORADO ===================== */
        
        /* TABLET GRANDE (1025px - 1199px) */
        @media (max-width: 1199px) {
            .login-wrapper { max-width: 850px; }
            .panel-foto { width: 40%; }
            .panel-form { padding: 40px 35px; }
        }

        /* TABLET (768px - 1024px) */
        @media (max-width: 1024px) {
            .login-wrapper { max-width: 700px; }
            .panel-foto { width: 38%; }
            .panel-form { padding: 35px 30px; }
            h2 { font-size: 19px; }
            .subtitulo { font-size: 12px; }
        }

        /* TABLET PEQUEÑO / MÓVIL GRANDE (651px - 767px) */
        @media (max-width: 767px) {
            .panel-foto { display: none; }
            .login-wrapper { max-width: 480px; }
            .panel-form { padding: 32px 28px; }
            h2 { font-size: 18px; margin-bottom: 8px; }
            .subtitulo { font-size: 12px; margin-bottom: 24px; }
            .logo-plec { margin-bottom: 25px; }
            .logo-plec span { font-size: 20px; }
            .pin-boxes { gap: 10px; }
            .pin-boxes input { width: 50px; height: 50px; }
        }

        /* MÓVIL ESTÁNDAR (481px - 650px) */
        @media (max-width: 650px) {
            .panel-foto { display: none; }
            .login-wrapper { max-width: 100%; border-radius: 12px; }
            .panel-form { padding: 28px 24px; }
            h2 { font-size: 17px; }
            .subtitulo { font-size: 11px; margin-bottom: 20px; }
            .logo-plec { margin-bottom: 22px; }
            .logo-plec img { height: 32px; }
            .logo-plec span { font-size: 19px; }
            .form-group { margin-bottom: 16px; }
            .input-wrap input { padding: 11px 14px 11px 38px; font-size: 13px; }
            .pin-boxes input { width: 46px; height: 46px; font-size: 18px; }
            .btn-login { padding: 12px; font-size: 14px; margin-top: 10px; }
            .alerta { font-size: 12px; padding: 10px 12px; }
        }

        /* MÓVIL MEDIANO (421px - 480px) */
        @media (max-width: 480px) {
            body { padding: 8px; }
            .login-wrapper { max-width: 100%; border-radius: 10px; }
            .panel-form { padding: 24px 20px; }
            .logo-plec { margin-bottom: 18px; }
            .logo-plec img { height: 28px; }
            .logo-plec span { font-size: 17px; }
            h2 { font-size: 16px; margin-bottom: 6px; }
            .subtitulo { font-size: 10px; margin-bottom: 18px; }
            .form-group { margin-bottom: 13px; }
            .form-group label { font-size: 10px; }
            .input-wrap input { padding: 10px 12px 10px 35px; font-size: 12px; }
            .input-wrap i { left: 11px; font-size: 12px; }
            .pin-boxes { gap: 7px; justify-content: center; }
            .pin-boxes input { width: 42px; height: 42px; font-size: 17px; }
            .btn-login { padding: 10px; font-size: 13px; margin-top: 8px; }
            .alerta { font-size: 11px; padding: 8px 10px; }
            .seguridad-info { font-size: 9px; margin-top: 12px; flex-wrap: wrap; }
        }

        /* MÓVIL PEQUEÑO (361px - 420px) */
        @media (max-width: 420px) {
            body { padding: 6px; }
            .panel-form { padding: 20px 16px; }
            .logo-plec { margin-bottom: 14px; }
            .logo-plec img { height: 24px; }
            .logo-plec span { font-size: 15px; }
            h2 { font-size: 14px; }
            .subtitulo { font-size: 9px; margin-bottom: 14px; }
            .form-group { margin-bottom: 11px; }
            .form-group label { font-size: 9px; }
            .input-wrap input { padding: 9px 10px 9px 33px; font-size: 11px; }
            .pin-boxes { gap: 6px; }
            .pin-boxes input { width: 40px; height: 40px; font-size: 16px; }
            .btn-login { padding: 9px; font-size: 12px; margin-top: 6px; }
            .alerta { font-size: 10px; padding: 7px 8px; }
        }

        /* MÓVIL MUY PEQUEÑO (hasta 360px) */
        @media (max-width: 360px) {
            body { padding: 4px; }
            .panel-form { padding: 16px 14px; }
            .logo-plec { margin-bottom: 12px; }
            .logo-plec img { height: 22px; }
            .logo-plec span { font-size: 13px; }
            h2 { font-size: 13px; margin-bottom: 4px; }
            .subtitulo { font-size: 8px; margin-bottom: 12px; }
            .form-group { margin-bottom: 9px; }
            .form-group label { font-size: 8px; }
            .input-wrap input { padding: 8px 9px 8px 30px; font-size: 10px; }
            .input-wrap i { left: 9px; font-size: 11px; }
            .pin-boxes { gap: 4px; }
            .pin-boxes input { width: 36px; height: 36px; font-size: 14px; }
            .btn-login { padding: 8px; font-size: 11px; }
            .alerta { font-size: 9px; padding: 6px 7px; }
            .seguridad-info { font-size: 8px; margin-top: 10px; }
        }
    </style>
</head>
<body>

<div class="login-wrapper">

    <!-- PANEL FOTO -->
    <div class="panel-foto">
        <img src="img/foto-fleybher.jpg" alt="Fleybher Martínez" onerror="this.parentElement.style.background='#111'">
        <div class="panel-foto-overlay">
            <div class="nombre">Fleybher Leandro<br>Martínez Urbano</div>
            <div class="cargo">Director General</div>
            <div class="plec-badge">NOTICIAS PLEC</div>
        </div>
    </div>

    <!-- PANEL FORMULARIO -->
    <div class="panel-form">

        <div class="logo-plec">
            <img src="img/logo-plec.jpg" alt="PLEC" onerror="this.style.display='none'">
            <span>Noticias <b>PLEC</b></span>
        </div>

        <h2>Panel Administrativo</h2>
        <p class="subtitulo">Ingresa tus credenciales para continuar</p>

        <?php if ($aviso_timeout): ?>
            <div class="alerta" style="background:rgba(255,150,0,0.1); border-color:#ff9600; color:#ffb347;">
                <i class="fas fa-clock"></i>
                Tu sesión se cerró por inactividad. Vuelve a ingresar.
            </div>
        <?php elseif ($bloqueado): ?>
            <div class="alerta alerta-bloqueo">
                <i class="fas fa-lock"></i>
                Acceso bloqueado por demasiados intentos. Espera <span id="countdown"><?php echo $segundos_restantes; ?></span> segundos.
            </div>
        <?php elseif ($error): ?>
            <div class="alerta">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
                <?php if ($_SESSION['intentos'] >= 3): ?>
                    <span style="margin-left:auto; color:#ff4d4d; font-weight:bold;"><?php echo 5 - $_SESSION['intentos']; ?> intentos restantes</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="loginForm" autocomplete="off">

            <!-- Campo trampa para engañar al autocompletado del navegador -->
            <input type="text" name="usuario_fake" style="display:none;" tabindex="-1" aria-hidden="true">
            <input type="password" name="password_fake" style="display:none;" tabindex="-1" aria-hidden="true">

            <div class="form-group">
                <label><i class="fas fa-user me-1"></i> Usuario</label>
                <div class="input-wrap">
                    <i class="fas fa-user"></i>
                    <input type="text" name="usuario" placeholder="Nombre de usuario"
                           autocomplete="off" spellcheck="false"
                           <?php echo $bloqueado ? 'disabled' : ''; ?> required>
                </div>
            </div>

            <div class="form-group">
                <label><i class="fas fa-lock me-1"></i> Contraseña</label>
                <div class="input-wrap">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" placeholder="••••••••••••"
                           autocomplete="new-password"
                           <?php echo $bloqueado ? 'disabled' : ''; ?> required>
                </div>
            </div>

            <div class="form-group">
                <label><i class="fas fa-shield-alt me-1"></i> PIN de seguridad (4 dígitos)</label>
                <div class="pin-boxes">
                    <input type="password" maxlength="1" id="pin1" inputmode="numeric" pattern="[0-9]" <?php echo $bloqueado ? 'disabled' : ''; ?>>
                    <input type="password" maxlength="1" id="pin2" inputmode="numeric" pattern="[0-9]" <?php echo $bloqueado ? 'disabled' : ''; ?>>
                    <input type="password" maxlength="1" id="pin3" inputmode="numeric" pattern="[0-9]" <?php echo $bloqueado ? 'disabled' : ''; ?>>
                    <input type="password" maxlength="1" id="pin4" inputmode="numeric" pattern="[0-9]" <?php echo $bloqueado ? 'disabled' : ''; ?>>
                    <input type="hidden" name="pin" id="pin_hidden">
                </div>
            </div>

            <button type="submit" class="btn-login" <?php echo $bloqueado ? 'disabled' : ''; ?>>
                <i class="fas fa-sign-in-alt me-2"></i> INGRESAR AL PANEL
            </button>
        </form>

        <div class="seguridad-info">
            <i class="fas fa-shield-alt"></i>
            Acceso protegido · Sesión cifrada · Noticias PLEC <?php echo date('Y'); ?>
        </div>
    </div>
</div>

<script>
// ── AUTO-AVANCE ENTRE CAJITAS DEL PIN ──
const pinInputs = [
    document.getElementById('pin1'),
    document.getElementById('pin2'),
    document.getElementById('pin3'),
    document.getElementById('pin4')
];
const pinHidden = document.getElementById('pin_hidden');

pinInputs.forEach((input, i) => {
    input.addEventListener('input', function() {
        // Solo números
        this.value = this.value.replace(/[^0-9]/g, '');
        if (this.value && i < 3) pinInputs[i + 1].focus();
        actualizarPin();
    });
    input.addEventListener('keydown', function(e) {
        if (e.key === 'Backspace' && !this.value && i > 0) {
            pinInputs[i - 1].focus();
            pinInputs[i - 1].value = '';
            actualizarPin();
        }
    });
});

function actualizarPin() {
    pinHidden.value = pinInputs.map(i => i.value).join('');
}

// Validar PIN antes de enviar
document.getElementById('loginForm').addEventListener('submit', function(e) {
    const pin = pinHidden.value;
    if (pin.length !== 4) {
        e.preventDefault();
        pinInputs[0].focus();
        alert('Ingresa los 4 dígitos del PIN de seguridad.');
    }
});

// ── CONTADOR REGRESIVO DE BLOQUEO ──
<?php if ($bloqueado): ?>
let segundos = <?php echo $segundos_restantes; ?>;
const counter = document.getElementById('countdown');
const interval = setInterval(() => {
    segundos--;
    if (counter) counter.textContent = segundos;
    if (segundos <= 0) {
        clearInterval(interval);
        location.reload();
    }
}, 1000);
<?php endif; ?>
</script>

</body>
</html>