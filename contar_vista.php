<?php
// ── CONTADOR DE VISITAS POR NOTICIA ──────────────────────────────────────
// Este archivo recibe el id de una noticia (por POST o GET) y suma 1 visita
// en la base de datos, evitando contar varias veces la misma noticia dentro
// de la misma sesión del navegador (por ejemplo, si el usuario recarga la
// página varias veces seguidas).

session_start();
header('Content-Type: application/json; charset=utf-8');

include("conexion.php");

$id = 0;
if (isset($_POST['id'])) {
    $id = intval($_POST['id']);
} elseif (isset($_GET['id'])) {
    $id = intval($_GET['id']);
}

if ($id <= 0) {
    echo json_encode(["ok" => false, "error" => "id_invalido"]);
    exit;
}

// Guardamos en sesión qué noticias ya contamos, para no inflar el número
// con recargas seguidas de la misma persona.
if (!isset($_SESSION['plec_vistas_contadas']) || !is_array($_SESSION['plec_vistas_contadas'])) {
    $_SESSION['plec_vistas_contadas'] = [];
}

$ya_contada = in_array($id, $_SESSION['plec_vistas_contadas'], true);

if (!$ya_contada) {
    $stmt = mysqli_prepare($conn, "UPDATE noticias SET vistas = vistas + 1 WHERE id = ?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    $_SESSION['plec_vistas_contadas'][] = $id;

    // Evitar que la sesión crezca sin límite en visitas muy largas
    if (count($_SESSION['plec_vistas_contadas']) > 500) {
        $_SESSION['plec_vistas_contadas'] = array_slice($_SESSION['plec_vistas_contadas'], -500);
    }
}

// Devolvemos el total actualizado para mostrarlo al instante en pantalla
$vistas = 0;
$stmt2 = mysqli_prepare($conn, "SELECT vistas FROM noticias WHERE id = ? LIMIT 1");
if ($stmt2) {
    mysqli_stmt_bind_param($stmt2, "i", $id);
    mysqli_stmt_execute($stmt2);
    $res2 = mysqli_stmt_get_result($stmt2);
    if ($res2 && $row2 = mysqli_fetch_assoc($res2)) {
        $vistas = intval($row2['vistas']);
    }
    mysqli_stmt_close($stmt2);
}

echo json_encode(["ok" => true, "vistas" => $vistas, "contada" => !$ya_contada]);
