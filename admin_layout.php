<?php
/* ════════════════════════════════════════════════════════════════════
   NOTICIAS PLEC — ESTRUCTURA VISUAL DEL PANEL
   <head>, barra lateral y pie compartidos por admin.php,
   editar_noticia.php y admin_impresos.php. Requiere admin_comun.php.
   ════════════════════════════════════════════════════════════════════ */

const PLEC_ADMIN_VERSION = '2';   // súbelo al cambiar plec-admin.css / plec-admin.js

function plec_e($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/**
 * Abre la página: <head>, barra lateral y el inicio del contenido.
 * $opciones: stream_activo (bool), total (int), stream (array para el JS).
 */
function plec_admin_cabecera(string $titulo, string $activo, array $opciones = []): void {
    $nombre  = (string)($_SESSION['admin_nombre'] ?? 'Administrador');
    $inicial = mb_strtoupper(mb_substr($nombre, 0, 1));
    $vivo    = !empty($opciones['stream_activo']);
    $total   = $opciones['total'] ?? null;
    $en_panel = ($activo === 'panel');

    $config = [
        'csrf'    => plec_admin_csrf(),
        'timeout' => PLEC_ADMIN_TIMEOUT,
        'limite'  => plec_admin_limite_subida(),
        'stream'  => $opciones['stream'] ?? null,
    ];

    // Enlaces de publicación: en el panel cambian de pestaña sin recargar
    $tipos = [
        'general'   => ['fa-newspaper',         'Noticia general'],
        'editorial' => ['fa-feather-pointed',   'Editorial'],
        'opinion'   => ['fa-user-tie',          'Columna de opinión'],
        'inclusion' => ['fa-universal-access',  'Inclusión'],
    ];
    ?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo plec_e($titulo); ?> · Panel PLEC</title>
    <script>
    (function () {
        var t = null;
        try { t = localStorage.getItem('plec-admin-tema'); } catch (e) {}
        if (t !== 'light' && t !== 'dark') {
            t = (window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
        }
        document.documentElement.setAttribute('data-bs-theme', t);
        document.documentElement.classList.add('pa-js');
    })();
    </script>
    <link rel="icon" href="img/logo-plec.jpg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Libre+Franklin:wght@400;500;600;700;800&family=Playfair+Display:wght@700;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/plec-admin.css?v=<?php echo PLEC_ADMIN_VERSION; ?>">
</head>
<body class="pa-body">
<script type="application/json" id="plecAdminConfig"><?php echo json_encode($config, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
<a class="pa-saltar" href="#pa-contenido">Saltar al contenido</a>

<!-- Barra superior (solo celular / tableta) -->
<header class="pa-topbar">
    <button type="button" class="pa-icon-btn" data-pa-menu aria-label="Abrir menú" aria-controls="pa-side" aria-expanded="false">
        <i class="fas fa-bars"></i>
    </button>
    <a class="pa-topbar-marca" href="admin.php"><img src="img/logo-plec.jpg" alt=""> Panel <span>PLEC</span></a>
    <button type="button" class="pa-icon-btn" data-pa-tema aria-label="Cambiar tema claro / oscuro">
        <i class="fas fa-moon pa-tema-oscuro"></i><i class="fas fa-sun pa-tema-claro"></i>
    </button>
</header>

<div class="pa-shell">
    <aside class="pa-side" id="pa-side" aria-label="Menú del panel">
        <div class="pa-side-head">
            <a class="pa-marca" href="admin.php">
                <img src="img/logo-plec.jpg" alt="">
                <span><b>Noticias <em>PLEC</em></b><small>Panel editorial</small></span>
            </a>
            <button type="button" class="pa-icon-btn pa-side-cerrar" data-pa-menu-cerrar aria-label="Cerrar menú"><i class="fas fa-xmark"></i></button>
        </div>

        <nav class="pa-nav">
            <div class="pa-nav-grupo">Publicar</div>
            <?php foreach ($tipos as $clave => [$icono, $texto]): ?>
                <a class="pa-nav-item pa-acento-<?php echo $clave; ?>" href="admin.php#publicar-<?php echo $clave; ?>" data-pa-ir-tipo="<?php echo $clave; ?>">
                    <i class="fas <?php echo $icono; ?>"></i><span><?php echo $texto; ?></span>
                </a>
            <?php endforeach; ?>

            <div class="pa-nav-grupo">Gestionar</div>
            <a class="pa-nav-item<?php echo $activo === 'panel' ? ' is-activo' : ''; ?>" href="admin.php#publicaciones" data-pa-ir="publicaciones">
                <i class="fas fa-list-ul"></i><span>Publicaciones</span>
                <?php if ($total !== null): ?><span class="pa-nav-num"><?php echo (int)$total; ?></span><?php endif; ?>
            </a>
            <?php if ($activo === 'editar'): ?>
                <span class="pa-nav-item is-activo"><i class="fas fa-pen-to-square"></i><span>Editando</span></span>
            <?php endif; ?>
            <a class="pa-nav-item" href="admin.php#en-vivo" data-pa-vivo>
                <i class="fas fa-tower-broadcast"></i><span>Transmisión en vivo</span>
                <span class="pa-vivo-pill" data-pa-vivo-pill<?php echo $vivo ? '' : ' hidden'; ?>><i></i>En vivo</span>
            </a>
            <a class="pa-nav-item<?php echo $activo === 'impresos' ? ' is-activo' : ''; ?>" href="admin_impresos.php">
                <i class="fas fa-file-pdf"></i><span>Ediciones impresas</span>
            </a>

            <div class="pa-nav-grupo">Sitio</div>
            <a class="pa-nav-item" href="index.php" target="_blank" rel="noopener">
                <i class="fas fa-arrow-up-right-from-square"></i><span>Ver el sitio</span>
            </a>
        </nav>

        <div class="pa-side-pie">
            <div class="pa-usuario">
                <span class="pa-avatar" aria-hidden="true"><?php echo plec_e($inicial); ?></span>
                <span class="pa-usuario-texto"><b><?php echo plec_e($nombre); ?></b><small>Administrador</small></span>
            </div>
            <div class="pa-side-acciones">
                <button type="button" class="pa-side-btn" data-pa-tema>
                    <i class="fas fa-moon pa-tema-oscuro"></i><i class="fas fa-sun pa-tema-claro"></i>
                    <span class="pa-tema-oscuro">Modo oscuro</span><span class="pa-tema-claro">Modo claro</span>
                </button>
                <a class="pa-side-btn pa-salir" href="logout.php"><i class="fas fa-right-from-bracket"></i> Salir</a>
            </div>
        </div>
    </aside>
    <div class="pa-side-fondo" data-pa-menu-cerrar hidden></div>

    <main class="pa-main" id="pa-contenido" tabindex="-1">
    <?php
}

/** Cierra la página: avisos, modal de confirmación, aviso de sesión y scripts */
function plec_admin_pie(array $scripts_extra = []): void {
    $flash = plec_admin_tomar_flash();
    ?>
        <footer class="pa-pie">&copy; <?php echo date('Y'); ?> Noticias PLEC · Panel administrativo</footer>
    </main>
</div>

<!-- Avisos (toasts) -->
<div class="pa-toasts" id="paToasts" aria-live="polite" aria-atomic="false">
    <?php if ($flash): $ok = ($flash['tipo'] ?? '') === 'success'; ?>
        <div class="pa-toast <?php echo $ok ? 'is-ok' : 'is-error'; ?>" role="status"
             data-pa-flash
             <?php if (!empty($flash['borrador'])): ?>data-pa-borrador-limpiar="<?php echo plec_e($flash['borrador']); ?>"<?php endif; ?>>
            <i class="fas <?php echo $ok ? 'fa-circle-check' : 'fa-triangle-exclamation'; ?>"></i>
            <div class="pa-toast-texto">
                <?php echo plec_e($flash['texto'] ?? ''); ?>
                <?php if (!empty($flash['enlace'])): ?>
                    <a href="<?php echo plec_e($flash['enlace']); ?>" target="_blank" rel="noopener">Ver en el sitio <i class="fas fa-arrow-up-right-from-square"></i></a>
                <?php endif; ?>
            </div>
            <button type="button" class="pa-toast-cerrar" aria-label="Cerrar aviso"><i class="fas fa-xmark"></i></button>
        </div>
    <?php endif; ?>
</div>

<!-- ── VISTA PREVIA ── -->
<div class="modal fade" id="paPreview" tabindex="-1" aria-labelledby="paPreviewTitulo" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content pa-preview">
            <div class="modal-header">
                <h2 class="modal-title h6 fw-bold" id="paPreviewTitulo"><i class="fas fa-eye me-2"></i> Vista previa · así se verá en el sitio</h2>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <article class="pa-preview-art">
                    <span class="pa-preview-cat" data-pv-cat></span>
                    <h1 class="pa-preview-titular" data-pv-titulo></h1>
                    <div class="pa-preview-meta"><span data-pv-fecha></span><span data-pv-lectura></span></div>
                    <div class="pa-preview-img" data-pv-img hidden><img alt=""></div>
                    <div class="pa-preview-cuerpo" data-pv-cuerpo></div>
                    <div class="pa-preview-video" data-pv-video hidden></div>
                </article>
            </div>
        </div>
    </div>
</div>

<!-- Confirmación (eliminar, descartar…) -->
<div class="modal fade" id="paConfirmar" tabindex="-1" aria-labelledby="paConfirmarTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content pa-modal">
            <div class="modal-body p-4">
                <div class="pa-confirmar-icono"><i class="fas fa-trash-can"></i></div>
                <h2 class="h5 fw-bold mt-3 mb-2" id="paConfirmarTitulo">¿Eliminar?</h2>
                <p class="pa-muted mb-0" id="paConfirmarTexto"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn pa-btn pa-btn-sec" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn pa-btn pa-btn-peligro" id="paConfirmarSi">Eliminar</button>
            </div>
        </div>
    </div>
</div>

<!-- Aviso de sesión por vencer -->
<div class="pa-sesion" id="paSesion" role="alertdialog" aria-labelledby="paSesionTitulo" hidden>
    <i class="fas fa-clock"></i>
    <div>
        <b id="paSesionTitulo">Tu sesión se cerrará pronto</b>
        <small>Por inactividad, en <span id="paSesionCuenta">5:00</span>. Lo que estés escribiendo queda guardado como borrador.</small>
    </div>
    <button type="button" class="btn pa-btn pa-btn-pri btn-sm" id="paSesionSeguir">Seguir trabajando</button>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/plec-admin.js?v=<?php echo PLEC_ADMIN_VERSION; ?>"></script>
<?php foreach ($scripts_extra as $s): ?>
<script><?php echo $s; ?></script>
<?php endforeach; ?>
</body>
</html>
    <?php
}
