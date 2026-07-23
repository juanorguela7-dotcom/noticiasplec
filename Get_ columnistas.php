<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

include("conexion.php");

$limit  = isset($_GET['limit'])  ? max(1, min(20, intval($_GET['limit'])))  : 6;
$offset = isset($_GET['offset']) ? max(0, intval($_GET['offset'])) : 0;

if (!isset($conn)) {
    echo json_encode(['items' => [], 'total' => 0]);
    exit;
}

// Contar total de columnistas (categoría 13 = Columnistas/Opinión)
$res_total = mysqli_query($conn, "SELECT COUNT(*) as total FROM noticias WHERE categoria_id = 13");
$total = ($res_total) ? intval(mysqli_fetch_assoc($res_total)['total']) : 0;

// Traer columnistas paginados
$stmt = mysqli_prepare($conn,
    "SELECT titulo, contenido, imagen, COALESCE(video_url, '') AS video_url FROM noticias WHERE categoria_id = 13 ORDER BY id DESC LIMIT ? OFFSET ?"
);
mysqli_stmt_bind_param($stmt, "ii", $limit, $offset);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$items = [];
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        // Resolver ruta de imagen igual que en index.php
        $img = $row['imagen'];
        if (empty($img)) {
            $img = 'img/avatar.png';
        } elseif (strpos($img, 'img/') !== 0 && strpos($img, 'http') !== 0) {
            $img = 'img/' . $img;
        }
        $items[] = [
            'titulo'    => $row['titulo'],
            'contenido' => $row['contenido'],
            'imagen'    => $img,
            'video_url' => $row['video_url'],
        ];
    }
    mysqli_free_result($res);
}
mysqli_stmt_close($stmt);
mysqli_close($conn);

echo json_encode(['items' => $items, 'total' => $total], JSON_UNESCAPED_UNICODE);