<?php
// ── HEADERS DE SEGURIDAD ──
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: no-referrer");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Pragma: no-cache");

// ── SESIÓN SEGURA ──
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);
ini_set('session.gc_maxlifetime', 1800);
// En hosting con HTTPS, descomentar:
// ini_set('session.cookie_secure', 1);

session_start();

// ── TIMEOUT POR INACTIVIDAD: 30 minutos ──
$timeout_inactividad = 1800; // segundos
if (isset($_SESSION['admin_last_act']) && (time() - $_SESSION['admin_last_act']) > $timeout_inactividad) {
    // Sesión expirada por inactividad → destruir y redirigir
    session_unset();
    session_destroy();
    header("Location: login.php?timeout=1");
    exit();
}

// Actualizar última actividad en cada request
if (isset($_SESSION['admin_id'])) {
    $_SESSION['admin_last_act'] = time();
}

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}
include("conexion.php");

// ── SEGURIDAD: Token CSRF para borrar ──
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Borrar noticia (solo si el token es válido)
if (isset($_GET['borrar']) && isset($_GET['token'])) {
    if ($_GET['token'] === $_SESSION['csrf_token']) {
        $id = intval($_GET['borrar']);
        mysqli_query($conn, "DELETE FROM noticias WHERE id = $id");
    }
    header("Location: admin.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de Administración - Noticias PLEC</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { 
            --azul: #1a73e8; 
            --negro-bg: #121212; 
            --rojo-plec: #ff4d4d;
            --inclusion: #8e44ad;
        }

        body { 
            background-color: #f4f7f6; 
            font-family: 'Libre Franklin', sans-serif; 
            position: relative;
            min-height: 100vh;
        }

        /* LOGO DE FONDO TRASLÚCIDO */
        body::before {
            content: "";
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 600px;
            height: 600px;
            background-image: url('img/logo-plec.jpg');
            background-repeat: no-repeat;
            background-position: center;
            background-size: contain;
            opacity: 0.05; 
            z-index: -1;
            pointer-events: none;
        }

        .navbar-plec {
            background-color: var(--negro-bg);
            border-bottom: 4px solid var(--azul);
            padding: 15px 0;
        }

        .card { border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); border: 1px solid #e0e0e0; }

        .card-header-plec {
            background-color: var(--negro-bg);
            color: white;
            border-radius: 11px 11px 0 0 !important;
            font-weight: bold;
            padding: 12px 20px;
        }

        /* ESTILO PARA EL SWITCH DE REVISTA/ESPECIAL */
        .seccion-especial {
            background-color: rgba(26, 115, 232, 0.05);
            border: 1px dashed var(--azul);
            border-radius: 8px;
            padding: 15px;
        }

        .btn-azul { background-color: var(--azul); color: white; border: none; }
        .btn-azul:hover { background-color: #155cb0; color: white; transform: translateY(-2px); }

        /* NUEVOS BOTONES LATERALES CON EL ESTILO COMPATIBLE */
        .btn-impresos { background-color: var(--rojo-plec); color: white; border: none; font-weight: bold; }
        .btn-impresos:hover { background-color: #e03b3b; color: white; }

        .btn-editorial-seccion { background-color: #4a148c; color: white; border: none; font-weight: bold; }
        .btn-editorial-seccion:hover { background-color: #320b61; color: white; }

        .btn-opinion-seccion { background-color: #00796b; color: white; border: none; font-weight: bold; }
        .btn-opinion-seccion:hover { background-color: #004d40; color: white; }

        .btn-inclusion-seccion { background-color: var(--inclusion); color: white; border: none; font-weight: bold; }
        .btn-inclusion-seccion:hover { background-color: #6c3483; color: white; }
        
        .form-label { font-weight: 700; font-size: 0.85rem; color: #444; text-transform: uppercase; }

        /* TEMA CLARO/OSCURO */
        body.tema-claro {
            background-color: #ffffff !important;
            color: #222;
        }
        body.tema-claro .card {
            background: #fff;
            border-color: #ddd;
        }
        body.tema-claro .card-header-plec {
            background-color: #f8f9fa;
            color: #333;
            border-bottom: 2px solid #1a73e8;
        }
        body.tema-claro table tbody tr:hover {
            background-color: #f5f5f5;
        }
        body.tema-claro table tbody tr {
            background-color: #fff;
        }
        body.tema-claro .form-control,
        body.tema-claro .form-select {
            background: #fff;
            border-color: #ddd;
            color: #333;
        }
        body.tema-claro .modal-content {
            background: #fff;
            color: #333;
        }
        body.tema-claro .text-muted {
            color: #666 !important;
        }

        /* BARRA DE BÚSQUEDA */
        .search-bar-container {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        body.tema-claro .search-bar-container {
            background: #f8f9fa;
            border: 1px solid #ddd;
        }
        .search-input {
            position: relative;
        }
        .search-input input {
            padding-left: 40px;
            font-size: 14px;
        }
        .search-input i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            font-size: 16px;
        }
        .btn-tema {
            background: transparent;
            border: 1px solid #ddd;
            color: #333;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 14px;
            transition: 0.3s;
        }
        .btn-tema:hover {
            background: #f0f0f0;
            border-color: #999;
        }
        body.tema-claro .btn-tema {
            border-color: #ccc;
            color: #333;
        }

        .noticia-row {
            transition: all 0.3s ease;
        }
        .noticia-row.oculto {
            display: none;
        }

        /* BOTÓN FLOTANTE TRANSMISIONES */
        .btn-transmisiones-flotante {
            position: fixed;
            top: 80px;
            right: 20px;
            background: linear-gradient(135deg, #FF0000, #FF4444);
            color: white;
            padding: 12px 24px;
            border-radius: 50px;
            border: none;
            cursor: pointer;
            z-index: 1000;
            font-weight: bold;
            font-size: 13px;
            box-shadow: 0 4px 15px rgba(255, 0, 0, 0.3);
            transition: 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .btn-transmisiones-flotante:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 0, 0, 0.5);
        }
        body.tema-claro .btn-transmisiones-flotante {
            box-shadow: 0 4px 15px rgba(255, 0, 0, 0.25);
        }

        /* VALIDACIÓN DE INPUT VIDEO */
        .video-help {
            font-size: 11px;
            color: #666;
            margin-top: 5px;
        }
        body.tema-claro .video-help {
            color: #555;
        }
        .plataformas-soportadas {
            display: flex;
            gap: 8px;
            margin-top: 8px;
            flex-wrap: wrap;
        }
        .plataforma-badge {
            background: #e9ecef;
            color: #333;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
        }
        body.tema-claro .plataforma-badge {
            background: #ddd;
            color: #333;
        }
        @keyframes parpadeo {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.2; }
        }
    </style>
</head>
<body>

<nav class="navbar-plec mb-5">
    <div class="container d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center">
            <img src="img/logo-plec.jpg" alt="Logo" style="height: 50px; border-radius: 5px; margin-right: 15px;">
            <h4 class="text-white m-0">Panel Administrativo</h4>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-tema" id="btnTema" onclick="cambiarTema()">
                <i class="fas fa-moon me-1"></i> <span id="textTema">Modo Claro</span>
            </button>
            <a href="index.php" class="btn btn-outline-light btn-sm me-2" target="_blank"><i class="fas fa-external-link-alt"></i> Ver Sitio</a>
            <a href="logout.php" class="btn btn-danger btn-sm px-3">Salir</a>
        </div>
    </div>
</nav>

<!-- BOTÓN FLOTANTE TRANSMISIONES EN VIVO -->
<button class="btn-transmisiones-flotante" onclick="mostrarModalTransmisiones()" title="Ver transmisiones en vivo">
    <i class="fas fa-broadcast-tower"></i> TRANSMISIONES EN VIVO
</button>

<div class="container">

    <!-- ── ESTADÍSTICAS RÁPIDAS ── -->
    <?php
    $total_noticias  = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM noticias"))[0];
    $total_hoy       = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM noticias WHERE DATE(fecha) = CURDATE()"))[0];
    $ultima          = mysqli_fetch_assoc(mysqli_query($conn, "SELECT titulo, fecha FROM noticias ORDER BY id DESC LIMIT 1"));
    $total_cats      = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM categorias"))[0];
    ?>
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card text-center p-3 h-100" style="border-left: 4px solid #1a73e8;">
                <div style="font-size:2rem; font-weight:900; color:#1a73e8;"><?php echo $total_noticias; ?></div>
                <div class="text-muted small fw-bold text-uppercase">Total Noticias</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center p-3 h-100" style="border-left: 4px solid #28a745;">
                <div style="font-size:2rem; font-weight:900; color:#28a745;"><?php echo $total_hoy; ?></div>
                <div class="text-muted small fw-bold text-uppercase">Publicadas Hoy</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center p-3 h-100" style="border-left: 4px solid #ff4d4d;">
                <div style="font-size:2rem; font-weight:900; color:#ff4d4d;"><?php echo $total_cats; ?></div>
                <div class="text-muted small fw-bold text-uppercase">Categorías</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card p-3 h-100" style="border-left: 4px solid #4a148c;">
                <div class="text-muted small fw-bold text-uppercase mb-1">Última Publicación</div>
                <?php if($ultima): ?>
                    <div class="fw-bold" style="font-size:13px; color:#333; line-height:1.3;"><?php echo htmlspecialchars(mb_substr($ultima['titulo'], 0, 55)) . (mb_strlen($ultima['titulo']) > 55 ? '...' : ''); ?></div>
                    <?php if(!empty($ultima['fecha'])): ?>
                        <div class="text-muted" style="font-size:11px;"><?php echo date('d/m/Y H:i', strtotime($ultima['fecha'])); ?></div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="text-muted small">Sin publicaciones</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <!-- ── FIN ESTADÍSTICAS ── -->

    <div class="row g-4">
        <div class="col-lg-4">
            
            <div class="card bg-white shadow-sm text-center p-3 mb-3">
                <i class="fas fa-feather-alt fa-2x mb-2" style="color: #4a148c;"></i>
                <h6 class="fw-bold mb-2">Sección Editorial</h6>
                <button type="button" class="btn btn-editorial-seccion w-100" data-bs-toggle="modal" data-bs-target="#modalEditorial">
                    <i class="fas fa-edit me-1"></i> REDACTAR EDITORIAL
                </button>
            </div>

            <div class="card bg-white shadow-sm text-center p-3 mb-3">
                <i class="fas fa-user-tie fa-2x mb-2" style="color: #00796b;"></i>
                <h6 class="fw-bold mb-2">Sección Columnistas</h6>
                <button type="button" class="btn btn-opinion-seccion w-100" data-bs-toggle="modal" data-bs-target="#modalOpinion">
                    <i class="fas fa-plus me-1"></i> NUEVA OPINIÓN
                </button>
            </div>

            <div class="card bg-white shadow-sm text-center p-3 mb-3">
                <i class="fas fa-hands-helping fa-2x mb-2" style="color: #8e44ad;"></i>
                <h6 class="fw-bold mb-2">Sección Inclusión</h6>
                <button type="button" class="btn btn-inclusion-seccion w-100" data-bs-toggle="modal" data-bs-target="#modalInclusion">
                    <i class="fas fa-plus me-1"></i> NUEVA NOTICIA INCLUSIÓN
                </button>
            </div>

            <div class="card bg-white border-primary shadow-sm text-center p-3 mb-4">
                <i class="fas fa-file-pdf fa-2x mb-2" style="color: var(--rojo-plec);"></i>
                <h6 class="fw-bold mb-2">Gestión de Impresos</h6>
                <a href="admin_impresos.php" class="btn btn-impresos w-100">SUBIR PDF</a>
            </div>

            <div class="card mb-4">
                <div class="card-header-plec"><i class="fas fa-pen-nib me-2"></i> Nueva Noticia General</div>
                <div class="card-body p-4">
                    <form action="procesar_noticia.php" method="POST" enctype="multipart/form-data">
                        
                        <div class="mb-3">
                            <label class="form-label">Título</label>
                            <input type="text" name="titulo" class="form-control" required>
                        </div>

                        <div class="mb-3 seccion-especial">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="es_especial" id="es_especial" value="1">
                                <label class="form-check-label fw-bold text-primary" for="es_especial">
                                    <i class="fas fa-star me-1"></i> ¿ESTA ES UNA HISTORIA ESPECIAL?
                                </label>
                            </div>
                            <small class="text-muted">Márcalo si es contenido para la sección de Revista.</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Categoría</label>
                            <select name="categoria" class="form-select" required>
                                <option value="">Seleccionar...</option>
                                <?php
                                // Excluidos los IDs 13 y 15 para que las secciones especiales vayan netamente por sus botones separados
                                $cat_res = mysqli_query($conn, "SELECT * FROM categorias WHERE id NOT IN (13, 15) ORDER BY nombre ASC");
                                while($c = mysqli_fetch_assoc($cat_res)) {
                                    echo "<option value='".$c['id']."'>".$c['nombre']."</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Cuerpo de la Noticia</label>
                            <textarea name="contenido" class="form-control" rows="6" required></textarea>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label">Imagen</label>
                            <input type="file" name="imagen" class="form-control" accept="image/*">
                        </div>

                        <div class="mb-4">
                            <label class="form-label"><i class="fas fa-video me-1" style="color:#FF0000;"></i> URL del Video (Opcional)</label>
                            <input 
                                type="url" 
                                name="video_url" 
                                class="form-control" 
                                placeholder="ej: https://www.youtube.com/watch?v=dQw4w9WgXcQ"
                            >
                            <div class="video-help">
                                <i class="fas fa-info-circle me-1"></i> 
                                Pega el enlace completo del video de cualquiera de estas plataformas:
                            </div>
                            <div class="plataformas-soportadas">
                                <span class="plataforma-badge">YouTube</span>
                                <span class="plataforma-badge">Vimeo</span>
                                <span class="plataforma-badge">TikTok</span>
                                <span class="plataforma-badge">Facebook</span>
                                <span class="plataforma-badge">Instagram</span>
                                <span class="plataforma-badge">Twitch</span>
                            </div>
                        </div>
                        
                        <button type="button" class="btn btn-outline-secondary w-100 py-2 fw-bold mb-2" onclick="mostrarPreview()">
                            <i class="fas fa-eye me-1"></i> VISTA PREVIA
                        </button>
                        <button type="submit" class="btn btn-azul w-100 py-2 fw-bold">PUBLICAR NOTICIA</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <!-- BARRA DE BÚSQUEDA -->
            <div class="search-bar-container">
                <div class="search-input">
                    <i class="fas fa-search"></i>
                    <input 
                        type="text" 
                        id="searchInput" 
                        class="form-control" 
                        placeholder="Buscar noticias por título o categoría..."
                        onkeyup="filtrarNoticias(this.value)"
                    >
                </div>
                <small class="text-muted d-block mt-2"><i class="fas fa-info-circle me-1"></i> Escribe para buscar en tiempo real</small>
            </div>

            <div class="card">
                <div class="card-header-plec d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-history me-2"></i> Publicaciones Recientes</span>
                    <span id="countNoticias" class="badge bg-primary rounded-pill" style="font-size: 12px;">0</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-4">Noticia / Columna / Editorial</th>
                                    <th class="text-end pe-4">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="noticias-tabla">
                                <?php
                                $sql = "SELECT n.id, n.titulo, c.nombre as cat_nombre, c.id as cat_id FROM noticias n LEFT JOIN categorias c ON n.categoria_id = c.id ORDER BY n.id DESC";
                                $res = mysqli_query($conn, $sql);
                                $count = 0;
                                while($f = mysqli_fetch_assoc($res)){ 
                                    $count++;
                                    $badge_style = ($f['cat_id'] == 16) ? 'background:#8e44ad; color:#fff;' : 'background:#e9ecef; color:#333;';
                                    $cat_label = $f['cat_nombre'] ? htmlspecialchars($f['cat_nombre']) : 'General';
                                    ?>
                                    <tr class="noticia-row" data-titulo="<?php echo strtolower(htmlspecialchars($f['titulo'])); ?>" data-categoria="<?php echo strtolower($cat_label); ?>">
                                        <td class="ps-4">
                                            <div class="fw-bold"><?php echo $f['titulo']; ?></div>
                                            <span style="font-size:11px; font-weight:bold; padding:2px 8px; border-radius:20px; <?php echo $badge_style; ?>"><?php echo $cat_label; ?></span>
                                        </td>
                                        <td class="text-end pe-4">
                                            <div class="btn-group">
                                                <a href="editar_noticia.php?id=<?php echo $f['id']; ?>" class="btn btn-sm btn-outline-primary border-0" title="Editar"><i class="fas fa-edit"></i></a>
                                                <a href="admin.php?borrar=<?php echo $f['id']; ?>&token=<?php echo $csrf_token; ?>" class="btn btn-sm btn-outline-danger border-0" title="Eliminar" onclick="return confirm('¿Seguro que quieres eliminar esta noticia? Esta acción no se puede deshacer.')"><i class="fas fa-trash"></i></a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer text-muted text-center py-3" id="mensaje-busqueda" style="display:none;">
                        <i class="fas fa-search me-2"></i> No se encontraron resultados
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalInclusion" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content text-dark" style="background-color: #fff;">
            <div class="modal-header text-white" style="background-color: #8e44ad;">
                <h5 class="modal-title fw-bold"><i class="fas fa-hands-helping me-2"></i> Publicar Noticia de Inclusión</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="procesar_noticia.php" method="POST" enctype="multipart/form-data">
                <div class="modal-body p-4">
                    <input type="hidden" name="categoria" value="16">
                    
                    <div class="mb-3">
                        <label class="form-label">Título de la Noticia</label>
                        <input type="text" name="titulo" class="form-control" placeholder="Ej: Nuevas políticas de accesibilidad en el Tolima" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contenido / Texto Completo</label>
                        <textarea name="contenido" class="form-control" rows="8" placeholder="Escribe aquí el contenido de la noticia de inclusión..." required></textarea>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Imagen Destacada (Opcional)</label>
                        <input type="file" name="imagen" class="form-control" accept="image/*">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-inclusion-seccion px-4">GUARDAR NOTICIA</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEditorial" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content text-dark" style="background-color: #fff;">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold"><i class="fas fa-feather-alt text-warning me-2"></i> Publicar Editorial</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="procesar_noticia.php" method="POST" enctype="multipart/form-data">
                <div class="modal-body p-4">
                    <input type="hidden" name="categoria" value="15">
                    
                    <div class="mb-3">
                        <label class="form-label">Título de la Nota Editorial</label>
                        <input type="text" name="titulo" class="form-control" placeholder="Ej: De rottweiler a perrito faldero" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contenido / Texto Completo</label>
                        <textarea name="contenido" class="form-control" rows="8" placeholder="Escribe aquí la opinión del periódico..." required></textarea>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Imagen Destacada (Opcional)</label>
                        <input type="file" name="imagen" class="form-control" accept="image/*">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-editorial-seccion px-4">GUARDAR EDITORIAL</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalOpinion" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content text-dark" style="background-color: #fff;">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold"><i class="fas fa-user-tie text-info me-2"></i> Publicar Columna de Opinión</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="procesar_noticia.php" method="POST" enctype="multipart/form-data">
                <div class="modal-body p-4">
                    <input type="hidden" name="categoria" value="13">
                    
                    <div class="mb-3">
                        <label class="form-label">Título de la Columna</label>
                        <input type="text" name="titulo" class="form-control" placeholder="Ej: El cartel del 'acepto'" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Texto de la Columna</label>
                        <textarea name="contenido" class="form-control" rows="8" placeholder="Escribe lo redactado por el columnista aquí..." required></textarea>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Foto de Perfil del Columnista</label>
                        <input type="file" name="imagen" class="form-control" accept="image/*" required>
                        <small class="text-muted">Es necesaria la fotografía para renderizar su avatar redondo en la web.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-opinion-seccion px-4">GUARDAR COLUMNA</button>
                </div>
            </form>
        </div>
    </div>
</div>

<footer class="text-center py-4 mt-5 text-muted small">
    &copy; <?php echo date('Y'); ?> Noticias PLEC
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- MODAL TRANSMISIONES EN VIVO -->
<div class="modal fade" id="modalTransmisiones" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="background:#121212; color:#e0e0e0; border:1px solid #333;">
            <div class="modal-header" style="background:#181818; border-bottom:4px solid #FF0000;">
                <h5 class="modal-title text-white fw-bold">
                    <i class="fas fa-broadcast-tower me-2" style="color:#FF0000;"></i> 
                    Transmisiones en Vivo
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <h6 class="text-white fw-bold mb-3">
                        <i class="fas fa-tag me-2" style="color:#FFD700;"></i> ¿Qué se está transmitiendo?
                    </h6>
                    <input 
                        type="text" 
                        id="tituloTransmision" 
                        class="form-control" 
                        placeholder="Ej: Debate electoral Tolima 2026, Rueda de prensa alcaldía..."
                        style="background:#2a2a2a; border-color:#444; color:#fff; font-size:14px;"
                        maxlength="80"
                    >
                    <small class="text-muted d-block mt-1">
                        <i class="fas fa-info-circle me-1"></i>
                        Este texto aparecerá como aviso para los visitantes del sitio.
                    </small>
                </div>

                <div class="mb-4">
                    <h6 class="text-white fw-bold mb-3">
                        <i class="fas fa-video me-2" style="color:#FF0000;"></i> Pega el link de la transmisión en vivo:
                    </h6>
                    <input 
                        type="text" 
                        id="urlTransmision" 
                        class="form-control form-control-lg" 
                        placeholder="Ej: https://www.youtube.com/watch?v=... o youtu.be/..."
                        style="background:#2a2a2a; border-color:#444; color:#fff; font-size:14px;"
                    >
                    <small class="text-muted d-block mt-2">
                        <i class="fas fa-info-circle me-1"></i>
                        YouTube Live • Facebook Live • Instagram Live • TikTok Live • Twitch
                    </small>
                </div>

                <div id="previewTransmision" style="display:none; margin-top:20px;">
                    <h6 class="text-white fw-bold mb-3">Vista previa:</h6>
                    <div style="aspect-ratio: 16/9; background:#1a1a1a; border-radius:8px; overflow:hidden; border:1px solid #333;">
                        <iframe 
                            id="iframeTransmision"
                            style="width:100%; height:100%; border:none;"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                            allowfullscreen>
                        </iframe>
                    </div>
                </div>

                <div class="mt-4 p-3" style="background:#1a1a1a; border-left:4px solid #FF0000; border-radius:4px;">
                    <h6 class="text-white fw-bold mb-2">
                        <i class="fas fa-lightbulb me-2" style="color:#FFD700;"></i> Cómo obtener el link:
                    </h6>
                    <ul class="small text-muted mb-0" style="font-size:12px;">
                        <li><strong>YouTube:</strong> Copia la URL de la transmisión en vivo</li>
                        <li><strong>Facebook:</strong> Click derecho en video → Copiar URL del video</li>
                        <li><strong>Instagram:</strong> Copia el link del perfil o publicación</li>
                        <li><strong>TikTok:</strong> Click en compartir → Copiar link</li>
                        <li><strong>Twitch:</strong> Copia la URL del canal o transmisión</li>
                    </ul>
                </div>
            </div>
            <div class="modal-footer" style="background:#181818; border-top:1px solid #333;">
                <div id="estadoTransmision" style="display:none; align-items:center; gap:8px; margin-right:auto;">
                    <span style="width:10px; height:10px; background:#ff0000; border-radius:50%; display:inline-block; animation: parpadeo 1s infinite;"></span>
                    <small style="color:#ff4444; font-weight:bold;">EN VIVO activo en el sitio</small>
                </div>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" id="btnDetenerTrans" class="btn btn-outline-warning btn-sm" onclick="detenerTransmision()" style="display:none;">
                    <i class="fas fa-stop me-1"></i> Detener
                </button>
                <button type="button" class="btn btn-danger" onclick="abrirTransmision()">
                    <i class="fas fa-play me-1"></i> Ver Transmisión
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ── FUNCIONES DE TEMA Y BÚSQUEDA ── -->
<script>
// ── CAMBIAR TEMA (CLARO/OSCURO) ──
function cambiarTema() {
    const body = document.body;
    const btnTema = document.getElementById('btnTema');
    const textTema = document.getElementById('textTema');
    
    body.classList.toggle('tema-claro');
    
    // Guardar preferencia en localStorage
    if (body.classList.contains('tema-claro')) {
        localStorage.setItem('tema-admin', 'claro');
        btnTema.innerHTML = '<i class="fas fa-sun me-1"></i> <span id="textTema">Modo Oscuro</span>';
    } else {
        localStorage.setItem('tema-admin', 'oscuro');
        btnTema.innerHTML = '<i class="fas fa-moon me-1"></i> <span id="textTema">Modo Claro</span>';
    }
}

// ── CARGAR TEMA AL INICIAR ──
document.addEventListener('DOMContentLoaded', function() {
    const temaSaved = localStorage.getItem('tema-admin');
    if (temaSaved === 'claro') {
        document.body.classList.add('tema-claro');
        document.getElementById('btnTema').innerHTML = '<i class="fas fa-sun me-1"></i> <span id="textTema">Modo Oscuro</span>';
    }
    
    // Actualizar contador de noticias
    actualizarContador();
});

// ── FILTRAR NOTICIAS POR BÚSQUEDA ──
function filtrarNoticias(texto) {
    const textoLower = texto.toLowerCase().trim();
    const filas = document.querySelectorAll('.noticia-row');
    let visibles = 0;
    let totales = filas.length;
    
    filas.forEach(fila => {
        const titulo = fila.dataset.titulo;
        const categoria = fila.dataset.categoria;
        
        if (titulo.includes(textoLower) || categoria.includes(textoLower)) {
            fila.classList.remove('oculto');
            visibles++;
        } else {
            fila.classList.add('oculto');
        }
    });
    
    // Mostrar/ocultar mensaje de no resultados
    const mensajeBusqueda = document.getElementById('mensaje-busqueda');
    if (visibles === 0 && textoLower !== '') {
        mensajeBusqueda.style.display = 'block';
    } else {
        mensajeBusqueda.style.display = 'none';
    }
    
    // Actualizar contador
    document.getElementById('countNoticias').textContent = visibles + ' / ' + totales;
}

// ── ACTUALIZAR CONTADOR ──
function actualizarContador() {
    const filas = document.querySelectorAll('.noticia-row:not(.oculto)');
    const totales = document.querySelectorAll('.noticia-row').length;
    document.getElementById('countNoticias').textContent = filas.length + ' / ' + totales;
}

// ── LIMPIAR BÚSQUEDA AL CARGAR ──
window.addEventListener('load', function() {
    actualizarContador();
});

// ── VALIDAR URL DE VIDEO ──
function validarURLVideo(url) {
    // Validación desactivada — acepta cualquier URL
}

// ── MOSTRAR MODAL TRANSMISIONES ──
function mostrarModalTransmisiones() {
    // Verificar si hay transmisión activa en localStorage
    const activa = localStorage.getItem('plec-transmision-activa');
    const urlGuardada = localStorage.getItem('plec-transmision-url');
    const embedGuardada = localStorage.getItem('plec-transmision-embed');
    const tituloGuardado = localStorage.getItem('plec-transmision-titulo');

    if (activa && urlGuardada) {
        document.getElementById('urlTransmision').value = urlGuardada;
        if (tituloGuardado) document.getElementById('tituloTransmision').value = tituloGuardado;
        document.getElementById('estadoTransmision').style.display = 'flex';
        document.getElementById('btnDetenerTrans').style.display = 'inline-block';
        // Mostrar preview automáticamente
        document.getElementById('previewTransmision').style.display = 'block';
        document.getElementById('iframeTransmision').src = embedGuardada;
    } else {
        document.getElementById('estadoTransmision').style.display = 'none';
        document.getElementById('btnDetenerTrans').style.display = 'none';
    }

    new bootstrap.Modal(document.getElementById('modalTransmisiones')).show();
}

// ── ABRIR TRANSMISIÓN ──
function abrirTransmision() {
    const url = document.getElementById('urlTransmision').value.trim();
    
    if (!url) {
        alert('Pega el enlace de la transmisión primero');
        return;
    }
    
    // Obtener embed URL según plataforma
    const embedUrl = extraerEmbedURL(url);
    
    if (!embedUrl) {
        alert('❌ URL no válida. Asegúrate de copiar el enlace correcto.');
        return;
    }
    
    // ── GUARDAR EN localStorage para que index.php muestre el botón flotante ──
    const titulo = document.getElementById('tituloTransmision').value.trim() || 'Transmisión en vivo';
    localStorage.setItem('plec-transmision-url', url);
    localStorage.setItem('plec-transmision-embed', embedUrl);
    localStorage.setItem('plec-transmision-activa', '1');
    localStorage.setItem('plec-transmision-titulo', titulo);

    // Mostrar preview
    document.getElementById('previewTransmision').style.display = 'block';
    document.getElementById('iframeTransmision').src = embedUrl;

    // Mostrar mensaje de confirmación
    const btnVer = document.querySelector('.modal-footer .btn-danger');
    if (btnVer) {
        btnVer.innerHTML = '<i class="fas fa-check me-1"></i> ¡Activa en el sitio!';
        btnVer.style.background = '#28a745';
        setTimeout(() => {
            btnVer.innerHTML = '<i class="fas fa-play me-1"></i> Ver Transmisión';
            btnVer.style.background = '';
        }, 2500);
    }
}

// ── DETENER TRANSMISIÓN ──
function detenerTransmision() {
    localStorage.removeItem('plec-transmision-url');
    localStorage.removeItem('plec-transmision-embed');
    localStorage.removeItem('plec-transmision-activa');
    localStorage.removeItem('plec-transmision-titulo');
    document.getElementById('previewTransmision').style.display = 'none';
    document.getElementById('iframeTransmision').src = '';
    document.getElementById('urlTransmision').value = '';
    document.getElementById('tituloTransmision').value = '';
    alert('✅ Transmisión detenida. El botón y el aviso desaparecerán del sitio.');
}

// ── EXTRAER URL DE EMBED SEGÚN PLATAFORMA ──
function extraerEmbedURL(url) {
    url = url.trim();
    const urlLower = url.toLowerCase();

    // YOUTUBE — todos los formatos posibles
    if (urlLower.includes('youtube.com') || urlLower.includes('youtu.be')) {
        let videoId = null;

        if (url.includes('watch?v=')) {
            // https://www.youtube.com/watch?v=XXXX
            videoId = url.split('watch?v=')[1].split(/[&?]/)[0];
        } else {
            // youtu.be/XXXX  (con o sin https://)
            const m = url.match(/youtu\.be\/([a-zA-Z0-9_-]{5,15})/);
            if (m) videoId = m[1];
        }

        if (!videoId && url.includes('/live/')) {
            videoId = url.split('/live/')[1].split(/[&?]/)[0];
        }
        if (!videoId && url.includes('/shorts/')) {
            videoId = url.split('/shorts/')[1].split(/[&?]/)[0];
        }
        if (!videoId && url.includes('/embed/')) {
            return url.split('?')[0];
        }

        if (videoId) return 'https://www.youtube.com/embed/' + videoId + '?autoplay=1';
        if (urlLower.includes('/live')) return url;
        return null;
    }

    // VIMEO
    if (urlLower.includes('vimeo.com')) {
        const m = url.match(/vimeo\.com\/(\d+)/);
        return m ? 'https://player.vimeo.com/video/' + m[1] : null;
    }

    // TIKTOK
    if (urlLower.includes('tiktok.com')) {
        return 'https://www.tiktok.com/embed/v2/' + extraerTikTokId(url);
    }

    // FACEBOOK
    if (urlLower.includes('facebook.com') || urlLower.includes('fb.watch')) {
        return 'https://www.facebook.com/plugins/video.php?href=' + encodeURIComponent(url) + '&show_text=false';
    }

    // INSTAGRAM
    if (urlLower.includes('instagram.com')) {
        return 'https://www.instagram.com/p/' + extraerInstagramId(url) + '/embed';
    }

    // TWITCH
    if (urlLower.includes('twitch.tv')) {
        const canal = extraerTwitchCanal(url);
        const dominio = location.hostname || 'localhost';
        return canal ? 'https://player.twitch.tv/?channel=' + canal + '&parent=' + dominio : null;
    }

    // Cualquier otra URL http — dejar pasar directo
    if (urlLower.startsWith('http')) return url;

    return null;
}

// ── EXTRAER ID TIKTOK ──
function extraerTikTokId(url) {
    const partes = url.split('/video/');
    if (partes[1]) {
        return partes[1].split('?')[0];
    }
    return null;
}

// ── EXTRAER ID INSTAGRAM ──
function extraerInstagramId(url) {
    const partes = url.split('/p/');
    if (partes[1]) {
        return partes[1].split('/')[0];
    }
    return null;
}

// ── EXTRAER CANAL TWITCH ──
function extraerTwitchCanal(url) {
    const partes = url.split('twitch.tv/');
    if (partes[1]) {
        return partes[1].split('/')[0];
    }
    return null;
}
</script>

<!-- ── MODAL VISTA PREVIA ── -->
<div class="modal fade" id="modalPreview" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content" style="background:#121212; color:#e0e0e0; border:1px solid #333;">
            <div class="modal-header" style="background:#181818; border-bottom:4px solid #1a73e8;">
                <h5 class="modal-title text-white fw-bold"><i class="fas fa-eye me-2 text-warning"></i> Vista Previa de la Noticia</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Imagen preview -->
                <div id="preview-img-box" style="width:100%; height:300px; background:#2a2a2a; border-radius:8px; margin-bottom:20px; overflow:hidden; display:none;">
                    <img id="preview-img" src="" style="width:100%; height:100%; object-fit:cover;">
                </div>
                <div id="preview-img-placeholder" style="width:100%; height:200px; background:#2a2a2a; border-radius:8px; margin-bottom:20px; display:flex; align-items:center; justify-content:center;">
                    <span style="color:#555; font-size:14px;"><i class="fas fa-image me-2"></i> Sin imagen seleccionada</span>
                </div>
                <!-- Tag categoría -->
                <div style="color:#ffcc00; font-size:11px; font-weight:bold; border-left:3px solid #ff4d4d; padding-left:8px; text-transform:uppercase; margin-bottom:12px;" id="preview-cat">Categoría</div>
                <!-- Título -->
                <h2 id="preview-titulo" style="font-family:Georgia,serif; font-size:28px; color:#fff; margin-bottom:15px; line-height:1.2;">El título aparecerá aquí...</h2>
                <!-- Contenido -->
                <div id="preview-contenido" style="color:#aaa; font-size:15px; line-height:1.8; border-top:1px solid #333; padding-top:15px;">
                    El contenido aparecerá aquí...
                </div>
            </div>
            <div class="modal-footer" style="background:#181818; border-top:1px solid #333;">
                <small class="text-muted me-auto"><i class="fas fa-info-circle me-1"></i> Así se verá la noticia al abrirla en el sitio</small>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
function mostrarPreview() {
    // Leer campos del formulario
    const titulo    = document.querySelector('input[name="titulo"]').value.trim();
    const contenido = document.querySelector('textarea[name="contenido"]').value.trim();
    const catSelect = document.querySelector('select[name="categoria"]');
    const catNombre = catSelect.options[catSelect.selectedIndex]?.text || 'Sin categoría';
    const imgInput  = document.querySelector('input[name="imagen"]');

    // Validar mínimo
    if (!titulo && !contenido) {
        alert('Escribe al menos el título o el contenido para ver la vista previa.');
        return;
    }

    // Rellenar modal
    document.getElementById('preview-titulo').textContent    = titulo || 'Sin título';
    document.getElementById('preview-contenido').textContent = contenido || 'Sin contenido.';
    document.getElementById('preview-cat').textContent       = catNombre;

    // Imagen: si eligió una, mostrarla
    const imgBox         = document.getElementById('preview-img-box');
    const imgPlaceholder = document.getElementById('preview-img-placeholder');
    const imgEl          = document.getElementById('preview-img');

    if (imgInput && imgInput.files && imgInput.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            imgEl.src = e.target.result;
            imgBox.style.display = 'block';
            imgPlaceholder.style.display = 'none';
        };
        reader.readAsDataURL(imgInput.files[0]);
    } else {
        imgBox.style.display = 'none';
        imgPlaceholder.style.display = 'flex';
    }

    // Abrir modal
    new bootstrap.Modal(document.getElementById('modalPreview')).show();
}
</script>

<!-- ── AVISO DE EXPIRACIÓN DE SESIÓN ── -->
<div id="avisoSesion" style="display:none; position:fixed; bottom:24px; left:50%; transform:translateX(-50%); background:#1a1a1a; border:1px solid #ff9600; border-radius:10px; padding:14px 22px; z-index:99999; box-shadow:0 8px 30px rgba(0,0,0,0.6); display:none; align-items:center; gap:14px; min-width:320px; max-width:90vw;">
    <i class="fas fa-clock" style="color:#ff9600; font-size:20px;"></i>
    <div>
        <div style="color:#fff; font-weight:bold; font-size:13px;">Tu sesión expirará pronto</div>
        <div style="color:#aaa; font-size:12px;">Quedan <span id="cuentaRegresivaSesion">5:00</span> minutos de inactividad</div>
    </div>
    <button onclick="extenderSesion()" style="background:#1a73e8; color:#fff; border:none; border-radius:6px; padding:7px 14px; font-size:12px; font-weight:bold; cursor:pointer; white-space:nowrap;">
        <i class="fas fa-refresh me-1"></i> Continuar
    </button>
</div>

<script>
// ── CONTADOR DE INACTIVIDAD: avisa 5 min antes, cierra a los 30 min ──
(function() {
    const TIMEOUT_MS       = 30 * 60 * 1000; // 30 minutos (debe coincidir con PHP)
    const AVISO_ANTES_MS   = 5  * 60 * 1000; // avisar 5 min antes
    let timerAviso, timerCierre, timerCuenta;
    const avisoEl = document.getElementById('avisoSesion');
    const cuentaEl = document.getElementById('cuentaRegresivaSesion');

    function resetTimers() {
        clearTimeout(timerAviso);
        clearTimeout(timerCierre);
        clearInterval(timerCuenta);
        if (avisoEl) avisoEl.style.display = 'none';

        // Mostrar aviso 5 min antes del cierre
        timerAviso = setTimeout(function() {
            if (avisoEl) avisoEl.style.display = 'flex';
            let segs = 300;
            timerCuenta = setInterval(function() {
                segs--;
                const m = Math.floor(segs / 60);
                const s = segs % 60;
                if (cuentaEl) cuentaEl.textContent = m + ':' + String(s).padStart(2, '0');
                if (segs <= 0) clearInterval(timerCuenta);
            }, 1000);
        }, TIMEOUT_MS - AVISO_ANTES_MS);

        // Redirigir al login al cumplirse el timeout
        timerCierre = setTimeout(function() {
            window.location.href = 'login.php?timeout=1';
        }, TIMEOUT_MS);
    }

    // Reiniciar timers en cualquier actividad del usuario
    ['mousemove', 'keydown', 'click', 'scroll', 'touchstart'].forEach(function(ev) {
        document.addEventListener(ev, resetTimers, { passive: true });
    });

    resetTimers(); // arrancar al cargar la página
})();

function extenderSesion() {
    // Hace un ping silencioso al servidor para renovar la sesión PHP
    fetch('admin.php?ping=1', { method: 'GET', credentials: 'same-origin' })
        .catch(function() {});
    document.getElementById('avisoSesion').style.display = 'none';
}
</script>

</body>
</html>