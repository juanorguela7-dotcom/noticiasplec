<?php
// ── GUARDA EL ESTADO DE LA TRANSMISIÓN EN VIVO EN LA BASE DE DATOS ──
// Antes el panel lo guardaba en el localStorage del navegador del admin, por lo
// que los visitantes nunca veían el botón "EN VIVO". Ahora queda en la tabla
// `configuracion` y index.php lo lee para todos.
session_start();

$es_ajax = (($_POST['ajax'] ?? '') === '1');

function stream_responder($ok, $texto, $es_ajax, $extra = []) {
    if ($es_ajax) {
        header('Content-Type: application/json; charset=utf-8');
        if (!$ok) http_response_code(400);
        echo json_encode(array_merge(['ok' => $ok, 'mensaje' => $texto], $extra));
    } else {
        $_SESSION['mensaje'] = ['tipo' => $ok ? 'success' : 'danger', 'texto' => $texto];
        header("Location: admin.php");
    }
    exit();
}

if (!isset($_SESSION['admin_id'])) {
    if ($es_ajax) { http_response_code(401); echo json_encode(['ok' => false, 'mensaje' => 'Sesión expirada.']); exit(); }
    header("Location: login.php");
    exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: admin.php");
    exit();
}
if (!isset($_POST['csrf'], $_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf'])) {
    stream_responder(false, 'Token de seguridad inválido. Recarga el panel.', $es_ajax);
}

include("conexion.php");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS configuracion (
    clave VARCHAR(100) PRIMARY KEY,
    valor TEXT,
    actualizado DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

$accion = $_POST['accion'] ?? 'activar';

if ($accion === 'detener') {
    $pares = ['stream_activo' => '0'];
} else {
    $url    = trim($_POST['stream_url'] ?? '');
    $embed  = trim($_POST['stream_embed'] ?? '');
    $titulo = mb_substr(trim($_POST['stream_titulo'] ?? ''), 0, 80);

    // El embed termina dentro de un <iframe> en la portada: solo se aceptan URLs https
    if ($embed === '' || !filter_var($embed, FILTER_VALIDATE_URL) || stripos($embed, 'https://') !== 0) {
        stream_responder(false, 'El enlace de la transmisión no es válido (debe ser https).', $es_ajax);
    }

    $pares = [
        'stream_url'    => $url,
        'stream_embed'  => $embed,
        'stream_titulo' => $titulo !== '' ? $titulo : 'Transmisión en vivo',
        'stream_activo' => '1',
    ];
}

$stmt = mysqli_prepare($conn, "INSERT INTO configuracion (clave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
foreach ($pares as $clave => $valor) {
    mysqli_stmt_bind_param($stmt, "ss", $clave, $valor);
    mysqli_stmt_execute($stmt);
}

stream_responder(
    true,
    $accion === 'detener' ? 'Transmisión detenida. El botón desaparecerá del sitio.' : 'Transmisión activa: ya la ven todos los visitantes.',
    $es_ajax
);
