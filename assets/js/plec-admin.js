/* ════════════════════════════════════════════════════════════════════
   NOTICIAS PLEC — PANEL ADMINISTRATIVO
   · Tema claro/oscuro, menú lateral en celular, avisos (toasts).
   · Compositor por pestañas + borrador automático en este navegador.
   · Envío sin recargar con barra de progreso: si algo falla, el
     formulario NO se pierde y se marca el campo a corregir.
   · Sesión: se mantiene viva mientras hay actividad y avisa antes de
     cerrarse por inactividad.
   · Lista de publicaciones: búsqueda sin tildes, filtros y "mostrar más".
   · Transmisión en vivo (guarda en el servidor vía guardar_stream.php).
   ════════════════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    var CONFIG = {};
    try { CONFIG = JSON.parse(document.getElementById('plecAdminConfig').textContent) || {}; } catch (e) { CONFIG = {}; }
    var TIMEOUT_MS = (CONFIG.timeout || 1800) * 1000;
    var LIMITE = CONFIG.limite || 8 * 1024 * 1024;

    function $(sel, raiz) { return (raiz || document).querySelector(sel); }
    function $$(sel, raiz) { return Array.prototype.slice.call((raiz || document).querySelectorAll(sel)); }

    var almacen = {
        leer: function (k) { try { return JSON.parse(localStorage.getItem(k)); } catch (e) { return null; } },
        guardar: function (k, v) { try { localStorage.setItem(k, JSON.stringify(v)); return true; } catch (e) { return false; } },
        borrar: function (k) { try { localStorage.removeItem(k); } catch (e) {} }
    };

    function mb(bytes) {
        var m = bytes / 1048576;
        return (m >= 10 ? Math.round(m) : Math.round(m * 10) / 10) + ' MB';
    }
    function pesoLegible(bytes) {
        return bytes < 1048576 ? Math.max(1, Math.round(bytes / 1024)) + ' KB' : mb(bytes);
    }
    function hora(d) {
        return String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
    }
    function sinTildes(s) {
        return String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
    }

    function ponerCsrf(token) {
        if (!token) return;
        CONFIG.csrf = token;
        $$('input[name="csrf"]').forEach(function (i) { i.value = token; });
    }

    /* ── Avisos ─────────────────────────────────────────────────────── */
    var zonaToasts = $('#paToasts');

    function cerrarToast(t) {
        if (!t || t.classList.contains('is-saliendo')) return;
        t.classList.add('is-saliendo');
        setTimeout(function () { if (t.parentNode) t.parentNode.removeChild(t); }, 260);
    }
    function programarCierre(t, ms) {
        if (ms) setTimeout(function () { cerrarToast(t); }, ms);
    }
    function toast(tipo, texto, opciones) {
        opciones = opciones || {};
        if (!zonaToasts) return null;
        var t = document.createElement('div');
        t.className = 'pa-toast ' + (tipo === 'ok' ? 'is-ok' : tipo === 'error' ? 'is-error' : '');
        t.setAttribute('role', tipo === 'error' ? 'alert' : 'status');
        var icono = document.createElement('i');
        icono.className = 'fas ' + (tipo === 'ok' ? 'fa-circle-check' : tipo === 'error' ? 'fa-triangle-exclamation' : 'fa-circle-info');
        var cuerpo = document.createElement('div');
        cuerpo.className = 'pa-toast-texto';
        cuerpo.textContent = texto;
        if (opciones.enlace) {
            var a = document.createElement('a');
            a.href = opciones.enlace.href;
            a.textContent = opciones.enlace.texto + ' ';
            if (opciones.enlace.nueva) { a.target = '_blank'; a.rel = 'noopener'; }
            cuerpo.appendChild(document.createTextNode(' '));
            cuerpo.appendChild(a);
        }
        var cerrar = document.createElement('button');
        cerrar.type = 'button';
        cerrar.className = 'pa-toast-cerrar';
        cerrar.setAttribute('aria-label', 'Cerrar aviso');
        cerrar.innerHTML = '<i class="fas fa-xmark"></i>';
        t.appendChild(icono); t.appendChild(cuerpo); t.appendChild(cerrar);
        zonaToasts.appendChild(t);
        programarCierre(t, opciones.fijo ? 0 : (tipo === 'error' ? 10000 : 6000));
        return t;
    }
    if (zonaToasts) {
        zonaToasts.addEventListener('click', function (e) {
            var b = e.target.closest('.pa-toast-cerrar');
            if (b) cerrarToast(b.closest('.pa-toast'));
        });
    }
    // Aviso que dejó el servidor (publicada, eliminada, error…)
    var flash = $('[data-pa-flash]');
    if (flash) {
        var limpiar = flash.getAttribute('data-pa-borrador-limpiar');
        if (limpiar) almacen.borrar('plec-borrador-' + limpiar);
        programarCierre(flash, flash.classList.contains('is-error') ? 12000 : 8000);
    }

    /* ── Confirmación ───────────────────────────────────────────────── */
    var modalConfirmar = $('#paConfirmar');
    function confirmar(opc, alAceptar) {
        if (!modalConfirmar || !window.bootstrap) { if (window.confirm(opc.texto)) alAceptar(); return; }
        $('#paConfirmarTitulo').textContent = opc.titulo || '¿Seguro?';
        $('#paConfirmarTexto').textContent = opc.texto || '';
        var si = $('#paConfirmarSi');
        si.textContent = opc.boton || 'Aceptar';
        var m = bootstrap.Modal.getOrCreateInstance(modalConfirmar);
        var nuevo = si.cloneNode(true);       // quita listeners anteriores
        si.parentNode.replaceChild(nuevo, si);
        nuevo.addEventListener('click', function () {
            nuevo.disabled = true;
            m.hide();
            alAceptar();
        });
        modalConfirmar.addEventListener('shown.bs.modal', function enfocar() {
            nuevo.focus();
            modalConfirmar.removeEventListener('shown.bs.modal', enfocar);
        });
        m.show();
    }
    // Formularios que piden confirmación (eliminar)
    document.addEventListener('submit', function (e) {
        var f = e.target;
        if (!f.matches || !f.matches('[data-pa-confirmar]') || f.dataset.confirmado === '1') return;
        e.preventDefault();
        confirmar({ titulo: '¿Eliminar esta publicación?', texto: f.getAttribute('data-pa-confirmar'), boton: 'Sí, eliminar' }, function () {
            f.dataset.confirmado = '1';
            f.submit();
        });
    });

    // Bootstrap 5.3.0 marca el modal con aria-hidden mientras el foco sigue dentro: se suelta antes
    document.addEventListener('hide.bs.modal', function (e) {
        if (document.activeElement && e.target.contains(document.activeElement)) document.activeElement.blur();
    });

    /* ── Tema claro / oscuro ────────────────────────────────────────── */
    $$('[data-pa-tema]').forEach(function (b) {
        b.addEventListener('click', function () {
            var html = document.documentElement;
            var nuevo = html.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-bs-theme', nuevo);
            try { localStorage.setItem('plec-admin-tema', nuevo); } catch (e) {}
        });
    });

    /* ── Menú lateral en celular ────────────────────────────────────── */
    var lateral = $('#pa-side');
    var fondo = $('.pa-side-fondo');
    var botonMenu = $('[data-pa-menu]');
    function abrirMenu() {
        if (!lateral) return;
        lateral.classList.add('is-abierto');
        if (fondo) fondo.hidden = false;
        if (botonMenu) botonMenu.setAttribute('aria-expanded', 'true');
        var cerrar = $('.pa-side-cerrar', lateral);
        if (cerrar) cerrar.focus();
    }
    function cerrarMenu() {
        if (!lateral || !lateral.classList.contains('is-abierto')) return;
        lateral.classList.remove('is-abierto');
        if (fondo) fondo.hidden = true;
        if (botonMenu) { botonMenu.setAttribute('aria-expanded', 'false'); botonMenu.focus(); }
    }
    if (botonMenu) botonMenu.addEventListener('click', abrirMenu);
    $$('[data-pa-menu-cerrar]').forEach(function (b) { b.addEventListener('click', cerrarMenu); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') cerrarMenu(); });
    if (lateral) lateral.addEventListener('click', function (e) { if (e.target.closest('a.pa-nav-item')) cerrarMenu(); });

    /* ── Compositor: pestañas ───────────────────────────────────────── */
    var pestanas = $$('[data-pa-tab]');

    function activarTipo(tipo, enfocar) {
        var tab = $('[data-pa-tab="' + tipo + '"]');
        if (!tab) return false;
        pestanas.forEach(function (t) {
            var activa = t === tab;
            t.classList.toggle('is-activo', activa);
            t.setAttribute('aria-selected', activa ? 'true' : 'false');
            t.tabIndex = activa ? 0 : -1;
            var pane = document.getElementById(t.getAttribute('aria-controls'));
            if (pane) pane.classList.toggle('is-activo', activa);
        });
        if (history.replaceState) history.replaceState(null, '', location.pathname + location.search + '#publicar-' + tipo);
        if (enfocar === 'tab') tab.focus();
        if (enfocar === 'titulo') {
            var pane = document.getElementById('publicar-' + tipo);
            var campo = pane && $('input[name="titulo"]', pane);
            if (campo) campo.focus({ preventScroll: true });
        }
        return true;
    }

    pestanas.forEach(function (t, i) {
        t.addEventListener('click', function () { activarTipo(t.getAttribute('data-pa-tab')); });
        t.addEventListener('keydown', function (e) {
            var dest = null;
            if (e.key === 'ArrowRight' || e.key === 'ArrowDown') dest = pestanas[(i + 1) % pestanas.length];
            if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') dest = pestanas[(i - 1 + pestanas.length) % pestanas.length];
            if (e.key === 'Home') dest = pestanas[0];
            if (e.key === 'End') dest = pestanas[pestanas.length - 1];
            if (dest) { e.preventDefault(); activarTipo(dest.getAttribute('data-pa-tab'), 'tab'); }
        });
    });

    // Enlaces de la barra lateral "Publicar → …": en el panel cambian de pestaña sin recargar
    $$('[data-pa-ir-tipo]').forEach(function (a) {
        a.addEventListener('click', function (e) {
            if (!pestanas.length) return;
            e.preventDefault();
            activarTipo(a.getAttribute('data-pa-ir-tipo'), 'titulo');
            var comp = $('#publicar');
            if (comp) comp.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });
    var hashTipo = (location.hash.match(/^#publicar-(\w+)$/) || [])[1];
    if (hashTipo && activarTipo(hashTipo)) {
        var comp = $('#publicar');
        if (comp) setTimeout(function () { comp.scrollIntoView({ block: 'start' }); }, 0);
    }

    /* ── Video: reconoce la plataforma (igual que funciones_video.php) ── */
    function embedVideo(url) {
        url = String(url || '').trim();
        if (!url) return null;
        if (!/^https?:\/\//i.test(url)) url = 'https://' + url.replace(/^\/+/, '');
        var u = url.toLowerCase(), m;
        if (u.indexOf('youtube.com') > -1 || u.indexOf('youtu.be') > -1) {
            m = url.match(/(?:watch\?(?:.*&)?v=|\/live\/|\/shorts\/|\/embed\/|youtu\.be\/)([A-Za-z0-9_-]{5,20})/);
            return m ? { plataforma: 'YouTube', src: 'https://www.youtube.com/embed/' + m[1] + '?rel=0' } : false;
        }
        if (u.indexOf('vimeo.com') > -1) {
            m = url.match(/vimeo\.com\/(?:video\/)?(\d+)/);
            return m ? { plataforma: 'Vimeo', src: 'https://player.vimeo.com/video/' + m[1] } : false;
        }
        if (u.indexOf('facebook.com') > -1 || u.indexOf('fb.watch') > -1) {
            return { plataforma: 'Facebook', src: 'https://www.facebook.com/plugins/video.php?href=' + encodeURIComponent(url) + '&show_text=false' };
        }
        if (u.indexOf('tiktok.com') > -1) {
            m = url.match(/\/video\/(\d+)/);
            return m ? { plataforma: 'TikTok', src: 'https://www.tiktok.com/embed/v2/' + m[1] } : false;
        }
        if (u.indexOf('instagram.com') > -1) {
            m = url.match(/instagram\.com\/(?:p|reel)\/([A-Za-z0-9_-]+)/);
            return m ? { plataforma: 'Instagram', src: 'https://www.instagram.com/p/' + m[1] + '/embed' } : false;
        }
        if (u.indexOf('twitch.tv') > -1) {
            m = url.match(/twitch\.tv\/([A-Za-z0-9_]+)/);
            return m ? { plataforma: 'Twitch', src: 'https://player.twitch.tv/?channel=' + m[1] + '&parent=' + encodeURIComponent(location.hostname || 'localhost') } : false;
        }
        return false;
    }

    /* ── Formularios de publicación ─────────────────────────────────── */
    var CAMPOS_BORRADOR = ['titulo', 'contenido', 'categoria', 'departamento_id', 'municipio_id', 'video_url', 'es_especial'];

    function iniciarFormulario(form) {
        var claveBorrador = form.getAttribute('data-pa-borrador') ? 'plec-borrador-' + form.getAttribute('data-pa-borrador') : null;
        var titulo = $('input[name="titulo"]', form);
        var contenido = $('textarea[name="contenido"]', form);
        var contador = $('[data-pa-contador]', form);
        var palabras = $('[data-pa-palabras-cuenta]', form);
        var drop = $('[data-pa-drop]', form);
        var video = $('[data-pa-video]', form);
        var estadoVideo = $('[data-pa-video-estado]', form);
        var textoVideoInicial = estadoVideo ? estadoVideo.textContent : '';
        var cajaError = $('.pa-form-error', form);
        var progreso = $('.pa-progreso', form);
        var botonEnviar = $('[data-pa-enviar]', form);
        var botonLimpiar = $('[data-pa-limpiar]', form);
        var estadoBorrador = $('[data-pa-borrador-estado]', form);
        var textoBorradorInicial = estadoBorrador ? estadoBorrador.innerHTML : '';
        var sucio = false;
        var enviando = false;

        // Contadores
        function contar() {
            if (titulo && contador) {
                var n = titulo.value.length;
                contador.textContent = n + ' / 255';
                contador.classList.toggle('is-cerca', n >= 220 && n < 255);
                contador.classList.toggle('is-limite', n >= 255);
            }
            if (contenido && palabras) {
                var p = (contenido.value.trim().match(/\S+/g) || []).length;
                palabras.textContent = p === 0 ? '0 palabras'
                    : p + (p === 1 ? ' palabra' : ' palabras') + ' · ' + Math.max(1, Math.ceil(p / 200)) + ' min de lectura';
            }
            if (botonLimpiar) botonLimpiar.hidden = !tieneContenido();
        }

        function tieneContenido() {
            return !!((titulo && titulo.value.trim()) || (contenido && contenido.value.trim()) || (video && video.value.trim()));
        }

        // Video
        function revisarVideo() {
            if (!video || !estadoVideo) return;
            var v = video.value.trim();
            estadoVideo.classList.remove('is-ok', 'is-error');
            if (!v) { estadoVideo.textContent = textoVideoInicial; return; }
            var r = embedVideo(v);
            if (r) {
                estadoVideo.classList.add('is-ok');
                estadoVideo.innerHTML = '<i class="fas fa-circle-check"></i> ';
                estadoVideo.appendChild(document.createTextNode('Video de ' + r.plataforma + ': se verá en la noticia.'));
            } else {
                estadoVideo.classList.add('is-error');
                estadoVideo.innerHTML = '<i class="fas fa-triangle-exclamation"></i> No reconocemos este enlace: no se vería en el sitio.';
            }
        }

        // Imagen
        var dropInput = drop && $('input[type="file"]', drop);
        var dropImg = drop && $('.pa-drop-lleno img', drop);
        var urlTemporal = null;
        var checkQuitar = $('[data-pa-quitar-imagen]', form);

        function mostrarDrop(src, nombre, peso, esNueva) {
            if (!drop) return;
            var lleno = $('.pa-drop-lleno', drop), vacio = $('.pa-drop-vacio', drop), quitar = $('[data-pa-drop-quitar]', drop);
            if (src) {
                dropImg.src = src;
                $('[data-pa-drop-nombre]', drop).textContent = nombre;
                $('[data-pa-drop-peso]', drop).textContent = peso;
                lleno.hidden = false; vacio.hidden = true;
                drop.classList.add('is-llena');
            } else {
                dropImg.removeAttribute('src');
                lleno.hidden = true; vacio.hidden = false;
                drop.classList.remove('is-llena');
            }
            if (quitar) quitar.hidden = !esNueva;
        }
        function imagenInicial() {
            var actual = drop && drop.getAttribute('data-actual');
            if (actual) mostrarDrop(actual, 'Imagen actual', 'Elige otra para reemplazarla', false);
            else mostrarDrop(null);
        }
        function soltarUrl() {
            if (urlTemporal) { URL.revokeObjectURL(urlTemporal); urlTemporal = null; }
        }
        function limpiarArchivo() {
            if (!dropInput) return;
            dropInput.value = '';
            soltarUrl();
            imagenInicial();
        }
        function revisarArchivo() {
            if (!dropInput) return;
            drop.classList.remove('is-invalid');
            var f = dropInput.files && dropInput.files[0];
            if (!f) { limpiarArchivo(); return; }
            if (f.type && !/^image\/(jpeg|png|webp|gif)$/i.test(f.type)) {
                var heic = /heic|heif/i.test(f.type) || /\.(heic|heif)$/i.test(f.name);
                limpiarArchivo();
                mostrarError(heic ? 'Las fotos HEIC del iPhone no se ven en todos los navegadores. Pásala a JPG (o compártela por WhatsApp y guárdala de nuevo) y vuelve a elegirla.'
                                  : 'Ese archivo no es una imagen JPG, PNG, WEBP o GIF.', 'imagen');
                return;
            }
            if (f.size > LIMITE) {
                limpiarArchivo();
                mostrarError('La imagen pesa ' + pesoLegible(f.size) + ' y el máximo es ' + mb(LIMITE) + '. Usa una más liviana.', 'imagen');
                return;
            }
            ocultarError();
            soltarUrl();
            urlTemporal = URL.createObjectURL(f);
            mostrarDrop(urlTemporal, f.name, pesoLegible(f.size), true);
            if (checkQuitar) { checkQuitar.checked = false; drop.classList.remove('is-quitada'); }
        }
        if (drop) {
            imagenInicial();
            dropInput.addEventListener('change', revisarArchivo);
            ['dragenter', 'dragover'].forEach(function (ev) { drop.addEventListener(ev, function () { drop.classList.add('is-sobre'); }); });
            ['dragleave', 'drop'].forEach(function (ev) { drop.addEventListener(ev, function () { drop.classList.remove('is-sobre'); }); });
            var quitar = $('[data-pa-drop-quitar]', drop);
            if (quitar) quitar.addEventListener('click', function () { limpiarArchivo(); dropInput.focus(); });
        }
        if (checkQuitar && drop) {
            checkQuitar.addEventListener('change', function () {
                if (checkQuitar.checked && dropInput.files.length) limpiarArchivo();
                drop.classList.toggle('is-quitada', checkQuitar.checked);
            });
        }

        // Errores
        function ocultarError() {
            if (cajaError) { cajaError.hidden = true; cajaError.textContent = ''; }
            $$('.is-invalid', form).forEach(function (el) { el.classList.remove('is-invalid'); });
        }
        function mostrarError(texto, campo, enlace) {
            if (cajaError) {
                cajaError.innerHTML = '<i class="fas fa-triangle-exclamation"></i> ';
                var s = document.createElement('span');
                s.textContent = texto;
                if (enlace) {
                    var a = document.createElement('a');
                    a.href = enlace.href; a.textContent = enlace.texto; a.target = '_blank'; a.rel = 'noopener';
                    s.appendChild(document.createTextNode(' ')); s.appendChild(a);
                }
                cajaError.appendChild(s);
                cajaError.hidden = false;
            }
            if (campo) {
                var el = campo === 'imagen' ? drop : form.elements[campo];
                if (el && el.classList) {
                    el.classList.add('is-invalid');
                    var foco = campo === 'imagen' ? dropInput : el;
                    if (foco && foco.focus) foco.focus({ preventScroll: true });
                    (campo === 'imagen' ? drop : el).scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            } else if (cajaError) {
                cajaError.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }
        form.addEventListener('input', function (e) {
            if (e.target.classList && e.target.classList.contains('is-invalid')) e.target.classList.remove('is-invalid');
        });

        // Borrador en este navegador (no incluye la imagen: el navegador no deja guardarla)
        var temporizador = null;
        function datosBorrador() {
            var d = {};
            CAMPOS_BORRADOR.forEach(function (n) {
                var el = form.elements[n];
                if (!el) return;
                d[n] = el.type === 'checkbox' ? el.checked : el.value;
            });
            return d;
        }
        function guardarBorrador() {
            if (!claveBorrador || enviando) return;
            if (!tieneContenido()) { almacen.borrar(claveBorrador); if (estadoBorrador) { estadoBorrador.className = 'pa-borrador'; estadoBorrador.innerHTML = textoBorradorInicial; } return; }
            var ok = almacen.guardar(claveBorrador, { v: 1, t: Date.now(), campos: datosBorrador() });
            if (estadoBorrador && ok) {
                estadoBorrador.className = 'pa-borrador is-guardado';
                estadoBorrador.innerHTML = '<i class="fas fa-circle-check"></i> <span>Borrador guardado en este navegador · ' + hora(new Date()) + '</span>';
            }
        }
        function restaurarBorrador() {
            if (!claveBorrador) return;
            var b = almacen.leer(claveBorrador);
            if (!b || !b.campos) return;
            var c = b.campos;
            CAMPOS_BORRADOR.forEach(function (n) {
                var el = form.elements[n];
                if (!el || c[n] === undefined || n === 'municipio_id') return;
                if (el.type === 'checkbox') el.checked = !!c[n];
                else el.value = c[n];
                if (n === 'departamento_id') el.dispatchEvent(new Event('change', { bubbles: true }));   // llena los municipios
            });
            if (form.elements.municipio_id && c.municipio_id) form.elements.municipio_id.value = c.municipio_id;
            if (estadoBorrador) {
                var d = new Date(b.t);
                estadoBorrador.className = 'pa-borrador is-recuperado';
                estadoBorrador.innerHTML = '<i class="fas fa-rotate-left"></i> <span>Recuperamos tu borrador del ' + d.toLocaleDateString('es-CO', { day: 'numeric', month: 'short' }) + ', ' + hora(d) + '. La imagen hay que elegirla de nuevo.</span>';
            }
        }
        // Punto en la pestaña cuando ese tipo tiene un borrador sin publicar
        function marcarPestana() {
            var tab = pestanas.length && $('[data-pa-tab="' + form.getAttribute('data-pa-tipo') + '"]');
            if (!tab || !claveBorrador) return;
            var hay = tieneContenido();
            tab.classList.toggle('tiene-borrador', hay);
            if (hay) tab.setAttribute('title', 'Tiene un borrador sin publicar');
            else tab.removeAttribute('title');
        }
        function vaciar() {
            form.reset();
            if (form.elements.departamento_id) form.elements.departamento_id.dispatchEvent(new Event('change', { bubbles: true }));
            limpiarArchivo();
            if (claveBorrador) almacen.borrar(claveBorrador);
            if (estadoBorrador) { estadoBorrador.className = 'pa-borrador'; estadoBorrador.innerHTML = textoBorradorInicial; }
            ocultarError();
            sucio = false;
            contar(); revisarVideo(); marcarPestana();
        }
        if (botonLimpiar) {
            botonLimpiar.addEventListener('click', function () {
                confirmar({ titulo: '¿Vaciar el formulario?', texto: 'Se borrará lo escrito y el borrador guardado en este navegador.', boton: 'Sí, vaciar' }, vaciar);
            });
        }

        var restaurando = false;   // los "change" que dispara restaurar el borrador no cuentan como edición
        form.addEventListener('input', function () {
            if (restaurando) return;
            sucio = true;
            contar();
            marcarPestana();
            if (claveBorrador) { clearTimeout(temporizador); temporizador = setTimeout(guardarBorrador, 600); }
        });
        form.addEventListener('change', function () {
            if (restaurando) return;
            sucio = true;
            if (claveBorrador) { clearTimeout(temporizador); temporizador = setTimeout(guardarBorrador, 300); }
        });
        if (video) video.addEventListener('input', revisarVideo);

        // En el formulario de edición (sin borrador) se avisa antes de salir con cambios sin guardar
        if (!claveBorrador) {
            window.addEventListener('beforeunload', function (e) {
                if (sucio && !enviando) { e.preventDefault(); e.returnValue = ''; }
            });
        }

        restaurando = true;
        restaurarBorrador();
        restaurando = false;
        contar();
        revisarVideo();
        marcarPestana();

        // Envío sin recargar: el formulario no se pierde si algo falla
        var reintentoCsrf = false;
        form.addEventListener('submit', function (e) {
            if (!window.FormData || !window.XMLHttpRequest) return;   // navegador muy viejo: envío normal
            e.preventDefault();
            if (enviando) return;   // evita la publicación doble por doble clic
            ocultarError();

            var f = dropInput && dropInput.files && dropInput.files[0];
            if (f && f.size > LIMITE) {
                mostrarError('La imagen pesa ' + pesoLegible(f.size) + ' y el máximo es ' + mb(LIMITE) + '.', 'imagen');
                return;
            }
            enviar(!!f);
        });

        function ponerEnviando(si, textoBoton) {
            enviando = si;
            if (botonEnviar) {
                if (si) {
                    botonEnviar.dataset.html = botonEnviar.innerHTML;
                    botonEnviar.disabled = true;
                    botonEnviar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>' + (textoBoton || 'Guardando…') + '</span>';
                } else if (botonEnviar.dataset.html) {
                    botonEnviar.disabled = false;
                    botonEnviar.innerHTML = botonEnviar.dataset.html;
                }
            }
            if (progreso) progreso.hidden = !si;
        }

        function enviar(conArchivo) {
            guardarBorrador();
            ponerEnviando(true, conArchivo ? 'Subiendo…' : 'Guardando…');
            var barra = progreso && $('.pa-progreso-barra', progreso);
            var texto = progreso && $('.pa-progreso-texto', progreso);
            if (barra) barra.style.width = conArchivo ? '0%' : '60%';
            if (texto) texto.textContent = conArchivo ? 'Subiendo… 0%' : 'Guardando…';

            var xhr = new XMLHttpRequest();
            xhr.open('POST', form.getAttribute('action') || location.href);
            xhr.setRequestHeader('X-PLEC-Ajax', '1');
            xhr.responseType = 'text';
            if (xhr.upload && conArchivo) {
                xhr.upload.addEventListener('progress', function (ev) {
                    if (!ev.lengthComputable) return;
                    var p = Math.round(ev.loaded / ev.total * 100);
                    if (barra) barra.style.width = p + '%';
                    if (texto) texto.textContent = p < 100 ? 'Subiendo… ' + p + '%' : 'Guardando…';
                });
            }
            xhr.onload = function () {
                var r = null;
                try { r = JSON.parse(xhr.responseText); } catch (err) { r = null; }

                if (r && r.ok) {
                    if (barra) barra.style.width = '100%';
                    if (texto) texto.textContent = '¡Listo!';
                    if (claveBorrador) almacen.borrar(claveBorrador);
                    sucio = false;
                    location.href = r.redirigir || ('admin.php?nueva=' + encodeURIComponent(r.id || '') + '#publicaciones');
                    return;
                }
                ponerEnviando(false);
                if (r && r.motivo === 'csrf' && r.csrf && !reintentoCsrf) {
                    reintentoCsrf = true;
                    ponerCsrf(r.csrf);
                    enviar(conArchivo);
                    return;
                }
                reintentoCsrf = false;
                if (xhr.status === 401 || (r && r.sesion === false)) {
                    mostrarError((r && r.mensaje) || 'Tu sesión se cerró.', null, { href: 'login.php', texto: 'Iniciar sesión en otra pestaña' });
                    return;
                }
                if (r && r.mensaje) { mostrarError(r.mensaje, r.campo); return; }
                if (xhr.status === 413) { mostrarError('La imagen es demasiado pesada para el servidor. Usa una más liviana.', 'imagen'); return; }
                mostrarError('El servidor respondió con un error (código ' + xhr.status + '). Lo que escribiste sigue aquí: inténtalo de nuevo en un momento.');
            };
            xhr.onerror = function () {
                ponerEnviando(false);
                mostrarError('No hay conexión con el servidor. Revisa tu internet: lo que escribiste sigue aquí y está guardado como borrador.');
            };
            xhr.send(new FormData(form));
        }

        // Vista previa
        var botonPreview = $('[data-pa-preview]', form);
        if (botonPreview) botonPreview.addEventListener('click', function () { vistaPrevia(form, dropInput, drop, checkQuitar); });
    }

    $$('[data-pa-form]').forEach(iniciarFormulario);

    /* ── Vista previa ───────────────────────────────────────────────── */
    var NOMBRES_TIPO = { editorial: 'Editorial', opinion: 'Opinión', inclusion: 'Inclusión' };

    // Inserta el contenido como lo hace noticia.php (acepta HTML), sin scripts ni eventos
    function htmlSeguro(texto) {
        var doc = new DOMParser().parseFromString('<div>' + texto + '</div>', 'text/html');
        $$('script,style,iframe,object,embed,link,meta,form,input,button,textarea,select,base', doc).forEach(function (n) { n.remove(); });
        $$('*', doc).forEach(function (el) {
            Array.prototype.slice.call(el.attributes).forEach(function (a) {
                var n = a.name.toLowerCase();
                if (n.indexOf('on') === 0 || (/^(href|src|xlink:href|action|formaction|srcdoc)$/.test(n) && /^\s*(javascript|data):/i.test(a.value) && !(n === 'src' && /^\s*data:image\//i.test(a.value)))) {
                    el.removeAttribute(a.name);
                }
            });
        });
        return doc.body.firstChild;
    }

    function vistaPrevia(form, dropInput, drop, checkQuitar) {
        var modal = $('#paPreview');
        if (!modal || !window.bootstrap) return;
        var titulo = (form.elements.titulo && form.elements.titulo.value.trim()) || '';
        var contenido = (form.elements.contenido && form.elements.contenido.value.trim()) || '';
        var tipo = form.getAttribute('data-pa-tipo');
        var sel = form.elements.categoria;
        var cat = (sel && sel.tagName === 'SELECT' && sel.value) ? sel.options[sel.selectedIndex].text : (NOMBRES_TIPO[tipo] || 'Sin categoría');
        var esOpinion = tipo === 'opinion' || (sel && sel.value === '13');

        $('[data-pv-cat]', modal).textContent = cat;
        var t = $('[data-pv-titulo]', modal);
        t.textContent = titulo || 'Aquí va el título';
        var ahora = new Date();
        $('[data-pv-fecha]', modal).innerHTML = '<i class="far fa-calendar"></i> ';
        $('[data-pv-fecha]', modal).appendChild(document.createTextNode(ahora.toLocaleDateString('es-CO', { day: '2-digit', month: '2-digit', year: 'numeric' }) + ' ' + hora(ahora)));
        var p = (contenido.match(/\S+/g) || []).length;
        $('[data-pv-lectura]', modal).innerHTML = '<i class="far fa-clock"></i> ' + Math.max(1, Math.ceil(p / 200)) + ' min de lectura';

        var cuerpo = $('[data-pv-cuerpo]', modal);
        cuerpo.innerHTML = '';
        if (contenido) {
            var div = htmlSeguro(contenido);
            while (div && div.firstChild) cuerpo.appendChild(div.firstChild);
        } else {
            cuerpo.innerHTML = '<span class="pa-preview-vacio">Aquí va el contenido…</span>';
        }

        var cajaImg = $('[data-pv-img]', modal), img = $('img', cajaImg);
        var archivo = dropInput && dropInput.files && dropInput.files[0];
        var src = archivo ? $('.pa-drop-lleno img', drop).src
                : (drop && drop.getAttribute('data-actual') && !(checkQuitar && checkQuitar.checked) ? drop.getAttribute('data-actual') : '');
        cajaImg.hidden = !src;
        cajaImg.classList.toggle('is-redonda', !!esOpinion);
        if (src) img.src = src; else img.removeAttribute('src');

        var cajaVideo = $('[data-pv-video]', modal);
        cajaVideo.innerHTML = '';
        var v = form.elements.video_url && embedVideo(form.elements.video_url.value);
        if (v) {
            var f = document.createElement('iframe');
            f.src = v.src;
            f.title = 'Video de ' + v.plataforma;
            f.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture';
            f.allowFullscreen = true;
            cajaVideo.appendChild(f);
        }
        cajaVideo.hidden = !v;

        var acc = getComputedStyle(form).getPropertyValue('--acc').trim();
        if (acc) $('.pa-preview', modal).style.setProperty('--pv-acc', acc);
        modal.addEventListener('hidden.bs.modal', function quitarVideo() {
            cajaVideo.innerHTML = '';
            modal.removeEventListener('hidden.bs.modal', quitarVideo);
        });
        bootstrap.Modal.getOrCreateInstance(modal).show();
    }

    /* ── Lista de publicaciones ─────────────────────────────────────── */
    var lista = $('[data-pa-lista]');
    if (lista) {
        var POR_PAGINA = 20;
        var items = $$('[data-pa-item]', lista).map(function (li) {
            return { el: li, tipo: li.getAttribute('data-tipo'), texto: sinTildes(li.getAttribute('data-texto')) };
        });
        var buscar = $('[data-pa-buscar]');
        var chips = $$('[data-pa-filtro]');
        var botonMas = $('[data-pa-mas]');
        var cuenta = $('[data-pa-cuenta]');
        var sinResultados = $('[data-pa-sin-resultados]');
        var filtro = 'todas';
        var mostrar = POR_PAGINA;

        function aplicar() {
            var q = sinTildes(buscar ? buscar.value.trim() : '');
            var palabras = q ? q.split(/\s+/) : [];
            var coinciden = 0, visibles = 0;
            items.forEach(function (it) {
                var ok = (filtro === 'todas' || it.tipo === filtro) &&
                         palabras.every(function (w) { return it.texto.indexOf(w) > -1; });
                if (ok) coinciden++;
                var ver = ok && coinciden <= mostrar;
                if (ver) visibles++;
                it.el.hidden = !ver;
            });
            if (cuenta) cuenta.textContent = (q || filtro !== 'todas') ? coinciden + ' de ' + items.length : items.length;
            if (sinResultados) sinResultados.hidden = coinciden > 0;
            if (botonMas) {
                var faltan = coinciden - visibles;
                botonMas.hidden = faltan <= 0;
                botonMas.textContent = 'Mostrar ' + Math.min(faltan, POR_PAGINA) + ' más (quedan ' + faltan + ')';
            }
        }
        var espera = null;
        if (buscar) buscar.addEventListener('input', function () {
            clearTimeout(espera);
            espera = setTimeout(function () { mostrar = POR_PAGINA; aplicar(); }, 120);
        });
        chips.forEach(function (c) {
            c.addEventListener('click', function () {
                filtro = c.getAttribute('data-pa-filtro');
                chips.forEach(function (o) {
                    o.classList.toggle('is-activo', o === c);
                    o.setAttribute('aria-pressed', o === c ? 'true' : 'false');
                });
                mostrar = POR_PAGINA;
                aplicar();
            });
        });
        if (botonMas) botonMas.addEventListener('click', function () { mostrar += POR_PAGINA; aplicar(); });

        // Recién publicada / editada: que se vea y se resalte
        var nueva = $('.pa-item.is-nueva', lista);
        if (nueva) {
            var pos = items.findIndex(function (it) { return it.el === nueva; });
            if (pos >= mostrar) mostrar = pos + 1;
        }
        aplicar();
        if (nueva) setTimeout(function () { nueva.scrollIntoView({ block: 'center' }); }, 60);
        if (/[?&]nueva=/.test(location.search) && history.replaceState) {
            history.replaceState(null, '', location.pathname + location.hash);
        }
    }

    /* ── Sesión: mantener viva mientras se trabaja, avisar antes de cerrar ── */
    (function sesion() {
        var aviso = $('#paSesion');
        var cuentaAviso = $('#paSesionCuenta');
        var seguir = $('#paSesionSeguir');
        var ultimaActividad = Date.now();
        var ultimoPing = Date.now();      // cargar la página ya renovó la sesión
        var pidiendo = false;
        var cerrada = false;
        var reloj = null;

        function ping() {
            if (pidiendo || cerrada) return;
            pidiendo = true;
            fetch('admin_ping.php', { headers: { 'X-PLEC-Ajax': '1' }, credentials: 'same-origin', cache: 'no-store' })
                .then(function (r) {
                    if (r.status === 401) throw { sesion: false };
                    return r.json();
                })
                .then(function (d) {
                    if (d && d.ok) { ultimoPing = Date.now(); ponerCsrf(d.csrf); revisar(); }
                })
                .catch(function (err) {
                    if (err && err.sesion === false) {
                        cerrada = true;
                        if (aviso) aviso.hidden = true;
                        toast('error', 'Tu sesión se cerró. Lo que escribiste está guardado como borrador.', {
                            fijo: true, enlace: { href: 'login.php', texto: 'Iniciar sesión', nueva: false }
                        });
                    }
                })
                .then(function () { pidiendo = false; });
        }

        function revisar() {
            if (cerrada) return;
            var ahora = Date.now();
            var restante = ultimoPing + TIMEOUT_MS - ahora;
            if (restante <= 0) {
                location.href = 'login.php?timeout=1';
                return;
            }
            // Hubo actividad reciente y la última renovación tiene más de 4 min: renovar
            if (ahora - ultimaActividad < 2 * 60 * 1000 && ahora - ultimoPing > 4 * 60 * 1000) ping();
            var avisar = restante <= 5 * 60 * 1000;
            if (aviso) {
                aviso.hidden = !avisar;
                if (avisar && cuentaAviso) {
                    var s = Math.max(0, Math.ceil(restante / 1000));
                    cuentaAviso.textContent = Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
                }
            }
            clearTimeout(reloj);
            reloj = setTimeout(revisar, avisar ? 1000 : 20000);
        }

        var ultimoRegistro = 0;
        function actividad() {
            var ahora = Date.now();
            if (ahora - ultimoRegistro < 1000) return;
            ultimoRegistro = ahora;
            ultimaActividad = ahora;
            if (aviso && !aviso.hidden) ping();   // volvió a trabajar con el aviso a la vista
        }
        ['mousemove', 'keydown', 'click', 'scroll', 'touchstart', 'input'].forEach(function (ev) {
            document.addEventListener(ev, actividad, { passive: true, capture: true });
        });
        if (seguir) seguir.addEventListener('click', function () { ultimaActividad = Date.now(); ping(); });
        document.addEventListener('visibilitychange', function () { if (!document.hidden) revisar(); });
        revisar();
    })();

    /* ── Transmisión en vivo ────────────────────────────────────────── */
    var modalVivo = $('#paVivo');
    var STREAM = CONFIG.stream || { activo: false, url: '', embed: '', titulo: '' };

    function extraerEmbedStream(url) {
        url = url.trim();
        var u = url.toLowerCase(), m;
        if (u.indexOf('youtube.com') > -1 || u.indexOf('youtu.be') > -1) {
            m = url.match(/(?:watch\?(?:.*&)?v=|\/live\/|\/shorts\/|youtu\.be\/)([A-Za-z0-9_-]{5,20})/);
            if (m) return 'https://www.youtube.com/embed/' + m[1] + '?autoplay=1';
            if (url.indexOf('/embed/') > -1) return url.split('?')[0];
            return null;
        }
        if (u.indexOf('vimeo.com') > -1) {
            m = url.match(/vimeo\.com\/(?:video\/)?(\d+)/);
            return m ? 'https://player.vimeo.com/video/' + m[1] : null;
        }
        if (u.indexOf('tiktok.com') > -1) {
            m = url.match(/\/video\/(\d+)/);
            return m ? 'https://www.tiktok.com/embed/v2/' + m[1] : null;
        }
        if (u.indexOf('facebook.com') > -1 || u.indexOf('fb.watch') > -1) {
            return 'https://www.facebook.com/plugins/video.php?href=' + encodeURIComponent(url) + '&show_text=false';
        }
        if (u.indexOf('instagram.com') > -1) {
            m = url.match(/instagram\.com\/(?:p|reel|tv)\/([A-Za-z0-9_-]+)/);
            return m ? 'https://www.instagram.com/p/' + m[1] + '/embed' : null;
        }
        if (u.indexOf('twitch.tv') > -1) {
            m = url.match(/twitch\.tv\/([A-Za-z0-9_]+)/);
            return m ? 'https://player.twitch.tv/?channel=' + m[1] + '&parent=' + encodeURIComponent(location.hostname || 'localhost') : null;
        }
        return /^https:\/\//i.test(url) ? url : null;
    }

    function pintarEstadoVivo() {
        $$('[data-pa-vivo-pill]').forEach(function (p) { p.hidden = !STREAM.activo; });
        $$('.pa-btn-vivo').forEach(function (b) { b.classList.toggle('is-activo', !!STREAM.activo); });
        $$('[data-pa-vivo-texto]').forEach(function (s) { s.textContent = STREAM.activo ? 'En vivo ahora' : 'Transmitir en vivo'; });
        if (!modalVivo) return;
        var estado = $('[data-pa-vivo-estado]', modalVivo);
        estado.hidden = !STREAM.activo;
        $('[data-pa-vivo-actual]', modalVivo).textContent = STREAM.titulo || '';
        $('#btnDetenerTrans').hidden = !STREAM.activo;
        var texto = $('#btnActivarTrans span');   // no existe mientras muestra "Guardando…"
        if (texto) texto.textContent = STREAM.activo ? 'Actualizar' : 'Poner al aire';
    }

    function guardarStream(datos) {
        var fd = new FormData();
        fd.append('ajax', '1');
        fd.append('csrf', CONFIG.csrf || '');
        Object.keys(datos).forEach(function (k) { fd.append(k, datos[k]); });
        return fetch('guardar_stream.php', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json().catch(function () { return { ok: false, mensaje: 'Respuesta inválida del servidor.' }; }); });
    }

    function abrirVivo() {
        if (!modalVivo || !window.bootstrap) return false;
        $('#urlTransmision').value = STREAM.activo ? (STREAM.url || '') : $('#urlTransmision').value;
        $('#tituloTransmision').value = STREAM.activo ? (STREAM.titulo || '') : $('#tituloTransmision').value;
        var prev = $('#previewTransmision');
        if (STREAM.activo && STREAM.embed) { $('#iframeTransmision').src = STREAM.embed; prev.hidden = false; }
        pintarEstadoVivo();
        bootstrap.Modal.getOrCreateInstance(modalVivo).show();
        return true;
    }

    if (modalVivo) {
        modalVivo.addEventListener('hidden.bs.modal', function () {
            $('#iframeTransmision').src = 'about:blank';   // que no siga sonando
            $('#previewTransmision').hidden = true;
        });
        $('#btnActivarTrans').addEventListener('click', function () {
            var url = $('#urlTransmision').value.trim();
            if (!url) { toast('error', 'Pega primero el enlace de la transmisión.'); $('#urlTransmision').focus(); return; }
            var embed = extraerEmbedStream(url);
            if (!embed || !/^https:\/\//i.test(embed)) { toast('error', 'Ese enlace no sirve: revisa que sea el de la transmisión y que empiece por https.'); return; }
            var titulo = $('#tituloTransmision').value.trim() || 'Transmisión en vivo';
            var b = this, html = b.innerHTML;
            b.disabled = true;
            b.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando…';
            guardarStream({ accion: 'activar', stream_url: url, stream_embed: embed, stream_titulo: titulo })
                .then(function (r) {
                    if (!r.ok) throw new Error(r.mensaje || 'No se pudo guardar.');
                    STREAM = { activo: true, url: url, embed: embed, titulo: titulo };
                    $('#iframeTransmision').src = embed;
                    $('#previewTransmision').hidden = false;
                    pintarEstadoVivo();
                    toast('ok', 'Transmisión al aire: ya la ven todos los visitantes.');
                })
                .catch(function (err) { toast('error', err.message); })
                .then(function () { b.disabled = false; b.innerHTML = html; pintarEstadoVivo(); });
        });
        $('#btnDetenerTrans').addEventListener('click', function () {
            var b = this;
            b.disabled = true;
            guardarStream({ accion: 'detener' })
                .then(function (r) {
                    if (!r.ok) throw new Error(r.mensaje || 'No se pudo detener.');
                    STREAM = { activo: false, url: '', embed: '', titulo: '' };
                    $('#iframeTransmision').src = 'about:blank';
                    $('#previewTransmision').hidden = true;
                    $('#urlTransmision').value = '';
                    $('#tituloTransmision').value = '';
                    pintarEstadoVivo();
                    toast('ok', 'Transmisión detenida: el aviso desaparece del sitio.');
                })
                .catch(function (err) { toast('error', err.message); })
                .then(function () { b.disabled = false; });
        });
    }
    $$('[data-pa-vivo]').forEach(function (el) {
        el.addEventListener('click', function (e) { if (abrirVivo()) e.preventDefault(); });
    });
    if (location.hash === '#en-vivo') abrirVivo();
})();
