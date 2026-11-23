<?php
require __DIR__ . '/admin_comun.php';          // sesión, CSRF, utilidades del panel
include("conexion.php");
require __DIR__ . '/admin_layout.php';

$mensaje = $_SESSION['mensaje_impreso'] ?? null;
unset($_SESSION['mensaje_impreso']);

$ediciones = [];
try {
    $res = mysqli_query($conn, "SELECT id, edicion_nro, fecha, portada, archivo_pdf FROM impresos ORDER BY fecha DESC, id DESC LIMIT 6");
    $ediciones = $res ? mysqli_fetch_all($res, MYSQLI_ASSOC) : [];
} catch (Throwable $e) {
    error_log('Noticias PLEC - lista de impresos: ' . $e->getMessage());
}
$limite_txt = plec_admin_mb(plec_admin_limite_subida());

plec_admin_cabecera('Ediciones impresas', 'impresos', ['stream_activo' => plec_admin_stream($conn)['activo']]);
?>

<header class="pa-encabezado">
    <div>
        <a class="pa-volver" href="admin.php"><i class="fas fa-arrow-left"></i> Volver al panel</a>
        <h1 class="pa-h1">Ediciones impresas</h1>
        <p class="pa-muted mb-0 mt-1">Sube el PDF del periódico y su portada. Aparece en la sección de impresos del sitio.</p>
    </div>
    <div class="pa-encabezado-acciones">
        <a class="btn pa-btn pa-btn-sec" href="impresos.php" target="_blank" rel="noopener"><i class="fas fa-arrow-up-right-from-square"></i> Ver impresos en el sitio</a>
    </div>
</header>

<div class="pa-impresos-grid">
    <section class="pa-card pa-acento-general">
        <h2 class="pa-h2 mb-3">Nueva edición</h2>

        <?php if ($mensaje): ?>
            <div class="<?php echo ($mensaje['tipo'] ?? '') === 'ok' ? 'pa-form-ok' : 'pa-form-error'; ?> mt-0 mb-3" role="alert">
                <i class="fas <?php echo ($mensaje['tipo'] ?? '') === 'ok' ? 'fa-circle-check' : 'fa-triangle-exclamation'; ?>"></i>
                <?php echo plec_e($mensaje['texto'] ?? ''); ?>
            </div>
        <?php endif; ?>

        <form action="guardar_impreso.php" method="POST" enctype="multipart/form-data" class="pa-impreso-form" data-pa-impreso>
            <input type="hidden" name="csrf" value="<?php echo plec_e(plec_admin_csrf()); ?>">
            <div class="row g-3">
                <div class="col-sm-6 pa-campo">
                    <label class="pa-label" for="imp-numero">Número de edición <span class="pa-req" aria-hidden="true">*</span></label>
                    <input type="text" id="imp-numero" name="edicion_nro" class="form-control" placeholder="Ej: 105" maxlength="20" required>
                </div>
                <div class="col-sm-6 pa-campo">
                    <label class="pa-label" for="imp-fecha">Fecha de publicación <span class="pa-req" aria-hidden="true">*</span></label>
                    <input type="date" id="imp-fecha" name="fecha" class="form-control" value="<?php echo (new DateTime('now', new DateTimeZone('America/Bogota')))->format('Y-m-d'); ?>" required>
                </div>
                <div class="col-12 pa-campo">
                    <label class="pa-label" for="imp-portada"><i class="fas fa-image"></i> Imagen de portada <span class="pa-req" aria-hidden="true">*</span></label>
                    <input type="file" id="imp-portada" name="portada" class="form-control" accept="image/jpeg,image/png,image/webp" required>
                    <div class="pa-ayuda-fila"><span>JPG, PNG o WEBP. Es la miniatura que se ve en el sitio.</span></div>
                </div>
                <div class="col-12 pa-campo">
                    <label class="pa-label" for="imp-pdf"><i class="fas fa-file-pdf"></i> Archivo del periódico (PDF) <span class="pa-req" aria-hidden="true">*</span></label>
                    <input type="file" id="imp-pdf" name="archivo_pdf" class="form-control" accept="application/pdf" required>
                    <div class="pa-ayuda-fila"><span>Entre los dos archivos, máximo <?php echo plec_e($limite_txt); ?>.</span></div>
                </div>
            </div>
            <div class="pa-form-error" role="alert" hidden></div>
            <div class="pa-form-pie">
                <span></span>
                <div class="pa-form-botones">
                    <button type="submit" class="btn pa-btn pa-btn-acento"><i class="fas fa-paper-plane"></i> <span>Publicar edición</span></button>
                </div>
            </div>
        </form>
    </section>

    <section class="pa-card">
        <h2 class="pa-h2 mb-3">Últimas ediciones</h2>
        <?php if (!$ediciones): ?>
            <div class="pa-vacio"><i class="fas fa-file-pdf"></i><b>Aún no hay ediciones</b><span>La primera que subas aparecerá aquí.</span></div>
        <?php else: ?>
            <ul class="pa-ediciones">
                <?php foreach ($ediciones as $ed): ?>
                    <li>
                        <img src="<?php echo plec_e($ed['portada']); ?>" alt="" loading="lazy" onerror="this.style.visibility='hidden'">
                        <div>
                            <b>Edición #<?php echo plec_e($ed['edicion_nro']); ?></b>
                            <small><?php echo plec_e(date('d/m/Y', strtotime($ed['fecha']))); ?></small>
                        </div>
                        <a class="pa-accion" href="<?php echo plec_e($ed['archivo_pdf']); ?>" target="_blank" rel="noopener" title="Abrir PDF" aria-label="Abrir el PDF de la edición <?php echo plec_e($ed['edicion_nro']); ?>"><i class="fas fa-arrow-up-right-from-square"></i></a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>

<?php
// Revisa el peso antes de subir: si pasa el límite de PHP, el servidor descartaría todo sin avisar
$limite = (int)plec_admin_limite_subida();
plec_admin_pie([<<<JS
(function () {
    var f = document.querySelector('[data-pa-impreso]');
    if (!f) return;
    f.addEventListener('submit', function (e) {
        var total = 0;
        f.querySelectorAll('input[type=file]').forEach(function (i) { if (i.files[0]) total += i.files[0].size; });
        var caja = f.querySelector('.pa-form-error');
        if (total > $limite) {
            e.preventDefault();
            caja.textContent = 'Los archivos pesan ' + (total / 1048576).toFixed(1) + ' MB y el máximo es $limite_txt. Comprime el PDF e inténtalo de nuevo.';
            caja.hidden = false;
            return;
        }
        var b = f.querySelector('button[type=submit]');
        b.disabled = true;
        b.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Subiendo…';
    });
})();
JS]);
