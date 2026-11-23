<?php
require __DIR__ . '/admin_comun.php';          // sesión, CSRF, utilidades del panel
include("conexion.php");
include_once __DIR__ . '/mapa_datos.php';      // selects de ubicación del mapa vivo
require __DIR__ . '/admin_layout.php';         // cabecera, barra lateral y pie

// ── DATOS DEL RESUMEN ──
function plec_admin_valor($conn, string $sql) {
    $r = mysqli_query($conn, $sql);
    $f = $r ? mysqli_fetch_row($r) : null;
    return $f ? $f[0] : null;
}
$total_noticias = (int)plec_admin_valor($conn, "SELECT COUNT(*) FROM noticias");
$total_hoy      = (int)plec_admin_valor($conn, "SELECT COUNT(*) FROM noticias WHERE fecha_publicacion >= CURDATE()");
$total_vistas   = (int)plec_admin_valor($conn, "SELECT COALESCE(SUM(vistas), 0) FROM noticias");
$res_ultima     = mysqli_query($conn, "SELECT id, titulo, fecha_publicacion FROM noticias ORDER BY id DESC LIMIT 1");
$ultima         = $res_ultima ? mysqli_fetch_assoc($res_ultima) : null;

$stream_admin = plec_admin_stream($conn);

// ── UTILIDADES DE PRESENTACIÓN ──
const PLEC_MESES_CORTOS = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

function plec_fecha_corta($fecha): string {
    $t = $fecha ? strtotime((string)$fecha) : false;
    if (!$t) return '';
    return date('j', $t) . ' ' . PLEC_MESES_CORTOS[(int)date('n', $t) - 1] . ' ' . date('Y', $t) . ' · ' . date('H:i', $t);
}

function plec_ruta_imagen($img): string {
    $img = (string)$img;
    if ($img === '') return '';
    return (strpos($img, 'img/') === 0 || strpos($img, 'http') === 0) ? $img : 'img/' . $img;
}

/** Tipo de publicación (para el filtro y el color de la fila) */
function plec_tipo_publicacion(array $f): string {
    if ((int)$f['es_especial'] === 1) return 'especial';
    switch ((int)$f['categoria_id']) {
        case PLEC_CAT_EDITORIAL: return 'editorial';
        case PLEC_CAT_OPINION:   return 'opinion';
        case PLEC_CAT_INCLUSION: return 'inclusion';
    }
    return 'general';
}

$ahora   = new DateTime('now', new DateTimeZone('America/Bogota'));
$hora    = (int)$ahora->format('G');
$saludo  = $hora < 12 ? 'Buenos días' : ($hora < 19 ? 'Buenas tardes' : 'Buenas noches');
$dias    = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
$meses   = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
$hoy_txt = ucfirst($dias[(int)$ahora->format('w')]) . ' ' . $ahora->format('j') . ' de ' . $meses[(int)$ahora->format('n') - 1] . ' de ' . $ahora->format('Y');
$nombre_corto = explode(' ', trim((string)($_SESSION['admin_nombre'] ?? '')))[0] ?? '';

// ── TIPOS DE PUBLICACIÓN DEL COMPOSITOR ──
$tipos = [
    'general' => [
        'icono' => 'fa-newspaper', 'titulo' => 'Noticia general', 'sub' => 'Portada y secciones',
        'desc'  => 'Sale en la portada, en la sección de su categoría y, si le pones ubicación, en el mapa de Colombia.',
        'boton' => 'Publicar noticia',
        'ph_titulo' => 'Ej: El Concejo de Ibagué aprueba el presupuesto 2027',
        'ph_texto'  => 'Escribe aquí la noticia completa…',
    ],
    'editorial' => [
        'icono' => 'fa-feather-pointed', 'titulo' => 'Editorial', 'sub' => 'La voz del periódico',
        'desc'  => 'La opinión de Noticias PLEC. En la portada se muestra siempre la editorial más reciente.',
        'boton' => 'Publicar editorial',
        'ph_titulo' => 'Ej: De rottweiler a perrito faldero',
        'ph_texto'  => 'Escribe aquí la opinión del periódico…',
    ],
    'opinion' => [
        'icono' => 'fa-user-tie', 'titulo' => 'Columna de opinión', 'sub' => 'Columnistas',
        'desc'  => 'Aparece en Columnistas. La foto del columnista se muestra como un avatar redondo.',
        'boton' => 'Publicar columna',
        'ph_titulo' => 'Ej: El cartel del "acepto"',
        'ph_texto'  => 'Pega aquí el texto del columnista…',
    ],
    'inclusion' => [
        'icono' => 'fa-universal-access', 'titulo' => 'Inclusión', 'sub' => 'Diversidad y derechos',
        'desc'  => 'Aparece en la sección Inclusión de la portada y, si le pones ubicación, en el mapa.',
        'boton' => 'Publicar en Inclusión',
        'ph_titulo' => 'Ej: Nuevas políticas de accesibilidad en el Tolima',
        'ph_texto'  => 'Escribe aquí la noticia de inclusión…',
    ],
];

// ── LISTA DE PUBLICACIONES ──
$con_mapa = plec_mapa_disponible($conn);
$sql_lista = "SELECT n.id, n.titulo, n.imagen, n.vistas, n.es_especial, n.fecha_publicacion, n.categoria_id, c.nombre AS cat_nombre"
           . ($con_mapa ? ", d.nombre AS dep_nombre, m.nombre AS mun_nombre" : "")
           . " FROM noticias n LEFT JOIN categorias c ON c.id = n.categoria_id"
           . ($con_mapa ? " LEFT JOIN departamentos d ON d.id = n.departamento_id LEFT JOIN municipios m ON m.id = n.municipio_id" : "")
           . " ORDER BY n.id DESC";
$res_lista = mysqli_query($conn, $sql_lista);
$publicaciones = $res_lista ? mysqli_fetch_all($res_lista, MYSQLI_ASSOC) : [];
$conteo_tipos = ['general' => 0, 'especial' => 0, 'editorial' => 0, 'opinion' => 0, 'inclusion' => 0];
foreach ($publicaciones as &$p) {
    $p['tipo'] = plec_tipo_publicacion($p);
    $conteo_tipos[$p['tipo']]++;
}
unset($p);
$id_nueva = (int)($_GET['nueva'] ?? 0);

$res_cats = mysqli_query($conn, "SELECT id, nombre FROM categorias WHERE id NOT IN (" . PLEC_CAT_OPINION . ", " . PLEC_CAT_EDITORIAL . ") ORDER BY nombre ASC");
$categorias = $res_cats ? mysqli_fetch_all($res_cats, MYSQLI_ASSOC) : [];

$limite_txt = plec_admin_mb(plec_admin_limite_subida());

/** Zona para arrastrar o elegir la imagen, con vista previa */
function plec_campo_imagen(string $tipo, string $etiqueta, bool $requerida, string $limite_txt, bool $redonda = false): void {
    $id = $tipo . '-imagen';
    ?>
    <div class="pa-campo">
        <span class="pa-label" id="<?php echo $id; ?>-label">
            <?php echo plec_e($etiqueta); ?>
            <?php echo $requerida ? '<span class="pa-req" aria-hidden="true">*</span>' : '<span class="pa-opcional">opcional</span>'; ?>
        </span>
        <div class="pa-drop<?php echo $redonda ? ' is-redonda' : ''; ?>" data-pa-drop>
            <input type="file" name="imagen" id="<?php echo $id; ?>" accept="image/jpeg,image/png,image/webp,image/gif"
                   aria-labelledby="<?php echo $id; ?>-label" aria-describedby="<?php echo $id; ?>-ayuda"<?php echo $requerida ? ' required' : ''; ?>>
            <div class="pa-drop-vacio">
                <i class="fas <?php echo $redonda ? 'fa-user' : 'fa-cloud-arrow-up'; ?>"></i>
                <b>Arrastra la imagen o haz clic</b>
                <small id="<?php echo $id; ?>-ayuda">JPG, PNG, WEBP o GIF · hasta <?php echo plec_e($limite_txt); ?></small>
            </div>
            <div class="pa-drop-lleno" hidden>
                <img alt="Vista previa de la imagen elegida">
                <div class="pa-drop-info"><b data-pa-drop-nombre></b><small data-pa-drop-peso></small></div>
            </div>
            <button type="button" class="pa-drop-quitar" data-pa-drop-quitar aria-label="Quitar la imagen elegida" hidden><i class="fas fa-xmark"></i></button>
        </div>
    </div>
    <?php
}

plec_admin_cabecera('Panel', 'panel', [
    'stream_activo' => $stream_admin['activo'],
    'stream'        => $stream_admin,
    'total'         => $total_noticias,
]);
?>

<!-- ── ENCABEZADO ── -->
<header class="pa-encabezado">
    <div>
        <p class="pa-fecha"><?php echo plec_e($hoy_txt); ?></p>
        <h1 class="pa-h1"><?php echo plec_e($saludo . ($nombre_corto !== '' ? ', ' . $nombre_corto : '')); ?></h1>
    </div>
    <div class="pa-encabezado-acciones">
        <button type="button" class="btn pa-btn pa-btn-vivo<?php echo $stream_admin['activo'] ? ' is-activo' : ''; ?>" data-pa-vivo>
            <i class="fas fa-tower-broadcast"></i>
            <span data-pa-vivo-texto><?php echo $stream_admin['activo'] ? 'En vivo ahora' : 'Transmitir en vivo'; ?></span>
        </button>
        <a class="btn pa-btn pa-btn-sec" href="index.php" target="_blank" rel="noopener"><i class="fas fa-arrow-up-right-from-square"></i> Ver el sitio</a>
    </div>
</header>

<!-- ── RESUMEN ── -->
<section class="pa-stats" aria-label="Resumen">
    <div class="pa-stat" style="--c: var(--pa-c-general)">
        <span class="pa-stat-icono"><i class="fas fa-newspaper"></i></span>
        <div><b><?php echo number_format($total_noticias, 0, ',', '.'); ?></b><small>Publicaciones</small></div>
    </div>
    <div class="pa-stat" style="--c: var(--pa-ok)">
        <span class="pa-stat-icono"><i class="fas fa-calendar-day"></i></span>
        <div><b><?php echo number_format($total_hoy, 0, ',', '.'); ?></b><small>Publicadas hoy</small></div>
    </div>
    <div class="pa-stat" style="--c: var(--pa-c-editorial)">
        <span class="pa-stat-icono"><i class="fas fa-eye"></i></span>
        <div><b><?php echo number_format($total_vistas, 0, ',', '.'); ?></b><small>Lecturas en total</small></div>
    </div>
    <div class="pa-stat pa-stat-ultima" style="--c: var(--pa-c-inclusion)">
        <span class="pa-stat-icono"><i class="fas fa-clock"></i></span>
        <div>
            <small>Última publicación</small>
            <?php if ($ultima): ?>
                <a href="noticia.php?id=<?php echo (int)$ultima['id']; ?>" target="_blank" rel="noopener" class="pa-stat-titulo"><?php echo plec_e($ultima['titulo']); ?></a>
                <small><?php echo plec_e(plec_fecha_corta($ultima['fecha_publicacion'])); ?></small>
            <?php else: ?>
                <span class="pa-stat-titulo">Aún no hay publicaciones</span>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- ── PUBLICAR ── -->
<section class="pa-card pa-compositor" id="publicar" aria-labelledby="publicar-titulo">
    <div class="pa-card-head">
        <div>
            <h2 class="pa-h2" id="publicar-titulo">Publicar</h2>
            <p class="pa-muted mb-0">Elige qué vas a publicar. Cada tipo sale en un lugar distinto del sitio.</p>
        </div>
    </div>

    <div class="pa-tipos" role="tablist" aria-label="Tipo de publicación">
        <?php $primero = true; foreach ($tipos as $clave => $t): ?>
            <button type="button" role="tab" class="pa-tipo pa-acento-<?php echo $clave; ?><?php echo $primero ? ' is-activo' : ''; ?>"
                    id="tab-<?php echo $clave; ?>" aria-controls="publicar-<?php echo $clave; ?>"
                    aria-selected="<?php echo $primero ? 'true' : 'false'; ?>" tabindex="<?php echo $primero ? '0' : '-1'; ?>"
                    data-pa-tab="<?php echo $clave; ?>">
                <span class="pa-tipo-icono"><i class="fas <?php echo $t['icono']; ?>"></i></span>
                <span class="pa-tipo-texto"><b><?php echo plec_e($t['titulo']); ?></b><small><?php echo plec_e($t['sub']); ?></small></span>
            </button>
        <?php $primero = false; endforeach; ?>
    </div>

    <?php $primero = true; foreach ($tipos as $clave => $t): ?>
    <form class="pa-pane pa-acento-<?php echo $clave; ?><?php echo $primero ? ' is-activo' : ''; ?>"
          id="publicar-<?php echo $clave; ?>" role="tabpanel" aria-labelledby="tab-<?php echo $clave; ?>"
          action="procesar_noticia.php" method="POST" enctype="multipart/form-data"
          data-pa-form data-pa-borrador="<?php echo $clave; ?>" data-pa-tipo="<?php echo $clave; ?>">
        <input type="hidden" name="csrf" value="<?php echo plec_e(plec_admin_csrf()); ?>">
        <input type="hidden" name="origen" value="<?php echo $clave; ?>">

        <p class="pa-pane-desc"><i class="fas fa-circle-info"></i> <?php echo plec_e($t['desc']); ?></p>

        <div class="pa-pane-grid">
            <div class="pa-pane-principal">
                <div class="pa-campo">
                    <label class="pa-label" for="<?php echo $clave; ?>-titulo">Título <span class="pa-req" aria-hidden="true">*</span></label>
                    <input type="text" name="titulo" id="<?php echo $clave; ?>-titulo" class="form-control pa-input-titulo"
                           maxlength="255" required autocomplete="off" placeholder="<?php echo plec_e($t['ph_titulo']); ?>" data-pa-contar>
                    <div class="pa-ayuda-fila">
                        <span>Claro y directo: es lo primero que se lee en la portada.</span>
                        <span class="pa-contador" data-pa-contador>0 / 255</span>
                    </div>
                </div>

                <div class="pa-campo pa-campo-texto">
                    <label class="pa-label" for="<?php echo $clave; ?>-contenido">
                        <?php echo $clave === 'opinion' ? 'Texto de la columna' : 'Contenido'; ?> <span class="pa-req" aria-hidden="true">*</span>
                    </label>
                    <textarea name="contenido" id="<?php echo $clave; ?>-contenido" class="form-control pa-textarea" rows="14" required
                              placeholder="<?php echo plec_e($t['ph_texto']); ?>" data-pa-palabras></textarea>
                    <div class="pa-ayuda-fila">
                        <span>Los saltos de línea se respetan tal cual en el sitio.</span>
                        <span class="pa-contador" data-pa-palabras-cuenta>0 palabras</span>
                    </div>
                </div>
            </div>

            <div class="pa-pane-lateral">
                <?php if ($clave === 'general'): ?>
                    <div class="pa-campo">
                        <label class="pa-label" for="general-categoria">Categoría <span class="pa-req" aria-hidden="true">*</span></label>
                        <select name="categoria" id="general-categoria" class="form-select" required>
                            <option value="">Elige una categoría…</option>
                            <?php foreach ($categorias as $c): ?>
                                <option value="<?php echo (int)$c['id']; ?>"><?php echo plec_e($c['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <?php if ($clave === 'general' || $clave === 'inclusion'): ?>
                    <?php echo plec_mapa_campos_admin($conn, null, null, $clave); ?>
                <?php endif; ?>

                <?php
                if ($clave === 'opinion') {
                    plec_campo_imagen($clave, 'Foto del columnista', true, $limite_txt, true);
                } else {
                    plec_campo_imagen($clave, 'Imagen destacada', false, $limite_txt);
                }
                ?>

                <?php if ($clave === 'general'): ?>
                    <div class="pa-campo">
                        <label class="pa-label" for="general-video">Video <span class="pa-opcional">opcional</span></label>
                        <input type="text" inputmode="url" name="video_url" id="general-video" class="form-control"
                               placeholder="Pega el enlace del video" autocomplete="off" data-pa-video aria-describedby="general-video-estado">
                        <div class="pa-video-estado" id="general-video-estado" data-pa-video-estado>YouTube, Vimeo, Facebook, TikTok, Instagram o Twitch</div>
                    </div>

                    <label class="pa-switch form-switch" for="general-especial">
                        <input class="form-check-input" type="checkbox" role="switch" name="es_especial" value="1" id="general-especial">
                        <span>
                            <b><i class="fas fa-star"></i> Historia especial</b>
                            <small>Sale en la Revista y <strong>no</strong> en el noticiero de la portada.</small>
                        </span>
                    </label>
                <?php endif; ?>
            </div>
        </div>

        <div class="pa-form-error" role="alert" hidden></div>
        <div class="pa-progreso" hidden>
            <div class="pa-progreso-pista"><div class="pa-progreso-barra"></div></div>
            <span class="pa-progreso-texto">Subiendo…</span>
        </div>

        <div class="pa-form-pie">
            <div class="pa-borrador" data-pa-borrador-estado>
                <i class="fas fa-cloud"></i>
                <span>Mientras escribes se guarda un borrador en este navegador.</span>
            </div>
            <div class="pa-form-botones">
                <button type="button" class="btn pa-btn pa-btn-sec" data-pa-limpiar hidden aria-label="Vaciar el formulario"><i class="fas fa-rotate-left"></i> <span class="pa-btn-texto">Vaciar</span></button>
                <button type="button" class="btn pa-btn pa-btn-sec" data-pa-preview><i class="fas fa-eye"></i> Vista previa</button>
                <button type="submit" class="btn pa-btn pa-btn-acento" data-pa-enviar>
                    <i class="fas fa-paper-plane"></i> <span><?php echo plec_e($t['boton']); ?></span>
                </button>
            </div>
        </div>
    </form>
    <?php $primero = false; endforeach; ?>
</section>

<!-- ── PUBLICACIONES ── -->
<section class="pa-card" id="publicaciones" aria-labelledby="publicaciones-titulo">
    <div class="pa-card-head pa-card-head-lista">
        <div>
            <h2 class="pa-h2" id="publicaciones-titulo">Publicaciones <span class="pa-badge-num" data-pa-cuenta><?php echo count($publicaciones); ?></span></h2>
            <p class="pa-muted mb-0">Lo más reciente primero. Busca por título, categoría o lugar.</p>
        </div>
        <div class="pa-buscar">
            <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
            <input type="search" class="form-control" placeholder="Buscar publicaciones…" aria-label="Buscar publicaciones" data-pa-buscar>
        </div>
    </div>

    <div class="pa-filtros" role="group" aria-label="Filtrar por tipo">
        <button type="button" class="pa-chip is-activo" data-pa-filtro="todas" aria-pressed="true">Todas <span><?php echo count($publicaciones); ?></span></button>
        <?php
        $etiquetas_filtro = ['general' => 'Noticias', 'especial' => 'Especiales', 'editorial' => 'Editorial', 'opinion' => 'Opinión', 'inclusion' => 'Inclusión'];
        foreach ($etiquetas_filtro as $k => $txt):
            if (!$conteo_tipos[$k]) continue; ?>
            <button type="button" class="pa-chip pa-acento-<?php echo $k; ?>" data-pa-filtro="<?php echo $k; ?>" aria-pressed="false">
                <?php echo $txt; ?> <span><?php echo $conteo_tipos[$k]; ?></span>
            </button>
        <?php endforeach; ?>
    </div>

    <?php if (!$publicaciones): ?>
        <div class="pa-vacio">
            <i class="fas fa-newspaper"></i>
            <b>Todavía no hay publicaciones</b>
            <span>Usa el formulario de arriba para publicar la primera.</span>
        </div>
    <?php else: ?>
    <ul class="pa-lista" data-pa-lista>
        <?php foreach ($publicaciones as $f):
            $img   = plec_ruta_imagen($f['imagen']);
            $cat   = $f['cat_nombre'] ?: 'Sin categoría';
            $lugar = trim(implode(', ', array_filter([$f['mun_nombre'] ?? '', $f['dep_nombre'] ?? ''])));
            $texto = mb_strtolower($f['titulo'] . ' ' . $cat . ' ' . $lugar . ' ' . ($f['tipo'] === 'especial' ? 'especial revista' : ''));
            ?>
            <li class="pa-item pa-acento-<?php echo $f['tipo']; ?><?php echo (int)$f['id'] === $id_nueva ? ' is-nueva' : ''; ?>"
                data-pa-item data-tipo="<?php echo $f['tipo']; ?>" data-texto="<?php echo plec_e($texto); ?>" id="pub-<?php echo (int)$f['id']; ?>">
                <span class="pa-item-img<?php echo $img === '' ? ' is-vacia' : ''; ?>">
                    <?php if ($img !== ''): ?>
                        <img src="<?php echo plec_e($img); ?>" alt="" loading="lazy" onerror="this.parentNode.classList.add('is-vacia'); this.remove();">
                    <?php endif; ?>
                    <i class="fas fa-image" aria-hidden="true"></i>
                </span>
                <div class="pa-item-main">
                    <a class="pa-item-titulo" href="editar_noticia.php?id=<?php echo (int)$f['id']; ?>"><?php echo plec_e($f['titulo']); ?></a>
                    <div class="pa-item-meta">
                        <span class="pa-cat" style="--c: <?php echo plec_e(plec_mapa_color($f['categoria_id'])); ?>"><?php echo plec_e($cat); ?></span>
                        <?php if ($f['tipo'] === 'especial'): ?><span class="pa-etiqueta-especial"><i class="fas fa-star"></i> Revista</span><?php endif; ?>
                        <?php if ($f['fecha_publicacion']): ?><span><?php echo plec_e(plec_fecha_corta($f['fecha_publicacion'])); ?></span><?php endif; ?>
                        <?php if ($lugar !== ''): ?><span><i class="fas fa-location-dot"></i> <?php echo plec_e($lugar); ?></span><?php endif; ?>
                        <span title="Lecturas"><i class="fas fa-eye"></i> <?php echo number_format((int)$f['vistas'], 0, ',', '.'); ?></span>
                    </div>
                </div>
                <div class="pa-item-acciones">
                    <a class="pa-accion" href="noticia.php?id=<?php echo (int)$f['id']; ?>" target="_blank" rel="noopener" title="Ver en el sitio" aria-label="Ver «<?php echo plec_e($f['titulo']); ?>» en el sitio"><i class="fas fa-arrow-up-right-from-square"></i></a>
                    <a class="pa-accion" href="editar_noticia.php?id=<?php echo (int)$f['id']; ?>" title="Editar" aria-label="Editar «<?php echo plec_e($f['titulo']); ?>»"><i class="fas fa-pen-to-square"></i></a>
                    <form method="POST" action="procesar_noticia.php" data-pa-confirmar="Se eliminará «<?php echo plec_e($f['titulo']); ?>». Esta acción no se puede deshacer.">
                        <input type="hidden" name="accion" value="borrar">
                        <input type="hidden" name="id" value="<?php echo (int)$f['id']; ?>">
                        <input type="hidden" name="csrf" value="<?php echo plec_e(plec_admin_csrf()); ?>">
                        <button type="submit" class="pa-accion pa-accion-peligro" title="Eliminar" aria-label="Eliminar «<?php echo plec_e($f['titulo']); ?>»"><i class="fas fa-trash-can"></i></button>
                    </form>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
    <div class="pa-vacio" data-pa-sin-resultados hidden>
        <i class="fas fa-magnifying-glass"></i>
        <b>No hay publicaciones que coincidan</b>
        <span>Prueba con otra palabra o quita el filtro.</span>
    </div>
    <div class="pa-lista-pie">
        <button type="button" class="btn pa-btn pa-btn-sec" data-pa-mas hidden>Mostrar más</button>
    </div>
    <?php endif; ?>
</section>

<!-- ── TRANSMISIÓN EN VIVO ── -->
<div class="modal fade" id="paVivo" tabindex="-1" aria-labelledby="paVivoTitulo" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content pa-modal">
            <div class="modal-header pa-modal-head-vivo">
                <h2 class="modal-title h5 fw-bold" id="paVivoTitulo"><i class="fas fa-tower-broadcast me-2"></i> Transmisión en vivo</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4">
                <div class="pa-vivo-estado" data-pa-vivo-estado hidden>
                    <span class="pa-punto-vivo"></span> <b>Al aire en el sitio:</b> <span data-pa-vivo-actual></span>
                </div>
                <div class="pa-campo">
                    <label class="pa-label" for="tituloTransmision">¿Qué se está transmitiendo?</label>
                    <input type="text" id="tituloTransmision" class="form-control" maxlength="80"
                           placeholder="Ej: Debate electoral Tolima 2026, rueda de prensa de la Alcaldía…">
                    <div class="pa-ayuda-fila"><span>Este texto aparece como aviso para los visitantes del sitio.</span></div>
                </div>
                <div class="pa-campo">
                    <label class="pa-label" for="urlTransmision">Enlace de la transmisión</label>
                    <input type="text" inputmode="url" id="urlTransmision" class="form-control" autocomplete="off"
                           placeholder="https://www.youtube.com/watch?v=… o youtu.be/…">
                    <div class="pa-ayuda-fila"><span>YouTube Live · Facebook Live · Instagram · TikTok · Twitch</span></div>
                </div>
                <div class="pa-vivo-preview" id="previewTransmision" hidden>
                    <iframe id="iframeTransmision" title="Vista previa de la transmisión"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                </div>
                <details class="pa-ayuda-box">
                    <summary><i class="fas fa-circle-info"></i> ¿Cómo obtengo el enlace?</summary>
                    <ul>
                        <li><b>YouTube:</b> copia la URL de la transmisión en vivo.</li>
                        <li><b>Facebook:</b> clic derecho en el video → Copiar URL del video.</li>
                        <li><b>Instagram:</b> copia el enlace de la publicación.</li>
                        <li><b>TikTok:</b> Compartir → Copiar enlace.</li>
                        <li><b>Twitch:</b> copia la URL del canal.</li>
                    </ul>
                </details>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn pa-btn pa-btn-sec" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn pa-btn pa-btn-sec" id="btnDetenerTrans" hidden><i class="fas fa-stop"></i> Detener transmisión</button>
                <button type="button" class="btn pa-btn pa-btn-rojo" id="btnActivarTrans"><i class="fas fa-play"></i> <span>Poner al aire</span></button>
            </div>
        </div>
    </div>
</div>

<?php plec_admin_pie(); ?>
