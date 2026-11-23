<?php
// Solo un administrador con sesión iniciada (y no vencida) puede publicar ediciones impresas.
require __DIR__ . '/admin_comun.php';
include("conexion.php");

function volver_con_error($msg) {
    $_SESSION['mensaje_impreso'] = ['tipo' => 'error', 'texto' => $msg];
    header("Location: admin_impresos.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: admin_impresos.php");
    exit();
}

// Si los archivos superan post_max_size, PHP vacía $_POST y $_FILES: se explica en vez de "sesión expirada"
if (plec_admin_post_demasiado_grande()) {
    volver_con_error('Los archivos pesan más de ' . plec_admin_mb(plec_admin_limite_subida()) . '. Comprime el PDF e inténtalo de nuevo.');
}

if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf'])) {
    volver_con_error('La sesión expiró. Recarga la página e inténtalo de nuevo.');
}

$edicion = trim($_POST['edicion_nro'] ?? '');
$fecha   = trim($_POST['fecha'] ?? '');

if ($edicion === '' || mb_strlen($edicion) > 20) {
    volver_con_error('Número de edición inválido.');
}
$fecha_dt = DateTime::createFromFormat('Y-m-d', $fecha);
if (!$fecha_dt || $fecha_dt->format('Y-m-d') !== $fecha) {
    volver_con_error('Fecha inválida.');
}

// ── Validación estricta de archivos ──
// Se valida el CONTENIDO real (no solo la extensión que manda el navegador)
// y el nombre final lo genera el servidor, así nadie puede subir un .php.
$finfo = new finfo(FILEINFO_MIME_TYPE);

$portada = $_FILES['portada'] ?? null;
if (!$portada || $portada['error'] !== UPLOAD_ERR_OK) {
    volver_con_error('No se recibió la imagen de portada.');
}
$mimes_img = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
$mime_img  = $finfo->file($portada['tmp_name']);
if (!isset($mimes_img[$mime_img]) || @getimagesize($portada['tmp_name']) === false) {
    volver_con_error('La portada debe ser una imagen JPG, PNG o WEBP.');
}

$pdf = $_FILES['archivo_pdf'] ?? null;
if (!$pdf || $pdf['error'] !== UPLOAD_ERR_OK) {
    volver_con_error('No se recibió el archivo PDF.');
}
if ($finfo->file($pdf['tmp_name']) !== 'application/pdf') {
    volver_con_error('El archivo del periódico debe ser un PDF.');
}

$base        = time() . '_' . bin2hex(random_bytes(4));
$img_destino = 'img/impreso_' . $base . '.' . $mimes_img[$mime_img];
$pdf_destino = 'pdf/impreso_' . $base . '.pdf';

if (!move_uploaded_file($portada['tmp_name'], $img_destino)) {
    volver_con_error('No se pudo guardar la portada. Verifica permisos de la carpeta img/.');
}
if (!move_uploaded_file($pdf['tmp_name'], $pdf_destino)) {
    @unlink($img_destino);
    volver_con_error('No se pudo guardar el PDF. Verifica permisos de la carpeta pdf/.');
}

$stmt = mysqli_prepare($conn, "INSERT INTO impresos (edicion_nro, portada, archivo_pdf, fecha) VALUES (?, ?, ?, ?)");
mysqli_stmt_bind_param($stmt, "ssss", $edicion, $img_destino, $pdf_destino, $fecha);

try {
    mysqli_stmt_execute($stmt);
} catch (Throwable $e) {
    @unlink($img_destino);
    @unlink($pdf_destino);
    error_log('Noticias PLEC - error guardando impreso: ' . $e->getMessage());
    volver_con_error('No se pudo guardar la edición en la base de datos.');
}

$_SESSION['mensaje_impreso'] = ['tipo' => 'ok', 'texto' => 'Edición #' . $edicion . ' publicada correctamente.'];
header("Location: impresos.php");
exit();
