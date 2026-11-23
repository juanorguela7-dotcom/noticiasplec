/* ════════════════════════════════════════════════════════════════════
   NOTICIAS PLEC — EL BÚHO INTELIGENTE: LA GUÍA (Etapa 5, sin dependencias)
   Decide qué dice el búho desde su rincón (plec-buho.js) y arma la barra
   "Pregúntale al búho". Todo pasa en el navegador: sin servidor ni costo.

   · Al entrar en cada sección de la biblioteca (la que más se ve al bajar,
     o la que se elige en las pestañas) el búho explica en una o dos frases
     qué hay ahí. Cada sección se explica una vez por sesión al bajar
     (sessionStorage "plec-buho-guia"); con la pestaña, siempre. Espera a
     que la sección se quede quieta un momento: bajar rápido no lo hace
     hablar de todas. En la página de una categoría o de una búsqueda,
     explica dónde está el lector al llegar.
   · "Pregúntale al búho": una barra encima de las pestañas. Busca por
     palabras (sin tildes ni mayúsculas, con sinónimos), sección, fecha y
     tema (plec-buho-busca.js). Los resultados salen como libros en un
     estante más, arriba de las secciones (PlecBiblioteca.resultados), y el
     búho los comenta ("Encontré 5 noticias sobre…"). Si no hay nada,
     propone alternativas que sí dan resultados.
   · Tocar al búho (o la tecla /) lleva a la barra.
   · Etapa 6: con ↓ en la barra se entra a las respuestas (y con ↑ se vuelve);
     el campo anuncia su atajo (/) y su ayuda a los lectores de pantalla; en
     el celular el ejemplo es corto y la fila de temas muestra sombra en el
     borde donde quedan más.
   · Minimizado, el búho no habla: lo que habría dicho de una búsqueda se
     lee igual en la cabecera del estante de resultados.

   Datos: #buhoIndice (índice ampliado y la fecha de hoy) y #buhoContexto
   (qué página es), que pone index.php con buho_datos.php.
   API: window.PlecBuhoGuia  .preguntar(texto)  .limpiar()  .estado()
   ════════════════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    var doc = document;
    var CLAVE_SESION = 'plec-buho-guia';
    var MAX_LIBROS = 40;               // libros como máximo en el estante de respuestas
    var ESPERA_SECCION = 900;          // ms quieta en una sección antes de explicarla
    var PAUSA_ENTRE = 2500;            // ms mínimos entre dos explicaciones seguidas
    var ESPERA_COMENTARIO = 1100;      // ms sin escribir antes de que el búho comente la búsqueda

    function leerJSON(id) {
        var el = doc.getElementById(id);
        if (!el) return null;
        try { return JSON.parse(el.textContent); } catch (e) { return null; }
    }
    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function sesion() {
        try { return JSON.parse(sessionStorage.getItem(CLAVE_SESION)) || {}; } catch (e) { return {}; }
    }
    function guardarSesion(s) {
        try { sessionStorage.setItem(CLAVE_SESION, JSON.stringify(s)); } catch (e) { /* modo privado */ }
    }
    function buho() { return window.PlecBuho; }
    function biblio() { return window.PlecBiblioteca; }
    // Una ventana de las de siempre (noticia, guardados, opinión…) bloquea el scroll del body
    function hayVentana() {
        if (doc.body.style.overflow === 'hidden') return true;
        var sb = doc.getElementById('sidebarMenu');
        return !!(sb && sb.style.width && parseFloat(sb.style.width) > 0);
    }
    function puedeHablar() {
        var B = buho();
        return !!(B && B.anclado() && !B.minimizado() && !B.abierto() && !hayVentana());
    }
    function fechaDicha(n) {
        if (!n) return '';
        var h = n.h;
        if (h < 1) return 'de hace un rato';
        if (h < 24) return 'de hace ' + h + ' hora' + (h !== 1 ? 's' : '');
        if (h < 48) return 'de ayer';
        var f = window.PlecBuscador ? window.PlecBuscador.fechaBonita(n.f) : n.f;
        return f ? 'del ' + f : '';
    }

    var CARA = '<svg class="buho-cara" viewBox="0 0 64 56" aria-hidden="true" focusable="false">' +
        '<path d="M11 19Q6 9 4 2Q14 8 21 12ZM53 19Q58 9 60 2Q50 8 43 12Z" fill="#26335f"/>' +
        '<ellipse cx="32" cy="30" rx="26" ry="23" fill="#2b3a6c"/>' +
        '<path d="M32 22C27 15 12 15 11 29C10 41 22 46 32 42C42 46 54 41 53 29C52 15 37 15 32 22Z" fill="#e3e9f6"/>' +
        '<circle cx="23" cy="30" r="7.5" fill="#f6b021" stroke="#18213f" stroke-width="1.6"/>' +
        '<circle cx="41" cy="30" r="7.5" fill="#f6b021" stroke="#18213f" stroke-width="1.6"/>' +
        '<circle cx="23" cy="30" r="3.6" fill="#0f1530"/><circle cx="41" cy="30" r="3.6" fill="#0f1530"/>' +
        '<circle cx="21.6" cy="28.4" r="1.3" fill="#fff"/><circle cx="39.6" cy="28.4" r="1.3" fill="#fff"/>' +
        '<path d="M28.5 37Q32 34.5 35.5 37Q34 42 32 44Q30 42 28.5 37Z" fill="#f59e2f"/></svg>';

    var contexto = leerJSON('buhoContexto') || { vista: 'portada' };
    var indice = leerJSON('buhoIndice');

    /* ══ 1. EXPLICAR CADA SECCIÓN ═════════════════════════════════════ */
    var guia = { pendiente: 0, ultimaVez: 0, busquedaHasta: 0, llego: false, seccion: null };
    var INTROS = ['Estás en %.', 'Llegaste a %.', 'Esta es la sección %.'];

    function explicacionSeccion(i) {
        var B = biblio();
        var p = B && B.datos.paneles[i];
        if (!p) return null;
        var n = B.datos.noticias[p.ids[0]];
        var intro = INTROS[i % INTROS.length].replace('%', p.nombre);
        var texto = intro + ' ' + (p.guia || p.sub + '.');
        var f = fechaDicha(n);
        if (p.clave !== 'inicio' && f) texto += ' La más reciente es ' + f + '.';
        return { texto: texto, panel: p };
    }

    function explicar(i, siempre) {
        var B = biblio();
        if (!B || !puedeHablar()) return false;
        var e = explicacionSeccion(i);
        if (!e) return false;
        var s = sesion();
        s.secciones = s.secciones || [];
        if (!siempre && s.secciones.indexOf(e.panel.clave) >= 0) return false;
        var texto = e.texto;
        if (!s.hola) texto = 'Me quedo aquí, a un lado. ' + texto + ' Si buscas algo, tócame.';
        var acciones = [];
        if (barra && e.panel.clave !== 'inicio' && opcionSeccion(e.panel.clave)) {
            acciones.push({ texto: 'Buscar en ' + e.panel.nombre, alElegir: function () {
                barra.seccion.value = e.panel.clave;
                enfocarBarra();
                buscarAhora(true);
            } });
        }
        if (!buho().comentar(texto, { tipo: 'seccion', acciones: acciones })) return false;
        if (s.secciones.indexOf(e.panel.clave) < 0) s.secciones.push(e.panel.clave);
        s.hola = 1;
        guardarSesion(s);
        guia.ultimaVez = Date.now();
        return true;
    }

    function alCambiarSeccion(d) {
        guia.seccion = d;
        clearTimeout(guia.pendiente);
        if (!guia.llego) return;                          // aún no está en su rincón: lo dirá al llegar
        var siempre = d.origen === 'pestana';
        var espera = siempre ? 650 : ESPERA_SECCION;
        guia.pendiente = setTimeout(function intentar() {
            var B = biblio(), ahora = Date.now();
            if (!B || !guia.seccion || B.actual().indice !== guia.seccion.indice) return;
            // Una búsqueda reciente o una explicación que acaba de salir tienen prioridad
            var libre = Math.max(guia.busquedaHasta, guia.ultimaVez + PAUSA_ENTRE);
            if (ahora < libre) { guia.pendiente = setTimeout(intentar, libre - ahora + 50); return; }
            explicar(guia.seccion.indice, siempre);
        }, espera);
    }

    // Lo que dice al llegar a su rincón por primera vez en esta página
    function alLlegar() {
        if (guia.llego) return;
        guia.llego = true;
        var s = sesion();
        setTimeout(function () {
            if (!puedeHablar()) return;
            if (contexto.vista === 'categoria') {
                var clave = 'cat-' + contexto.id;
                s.secciones = s.secciones || [];
                if (s.secciones.indexOf(clave) >= 0 && s.hola) return;
                var t = 'Estás en ' + contexto.nombre + '.' + (contexto.guia ? ' ' + contexto.guia : '') +
                    (contexto.total ? ' Aquí hay ' + (contexto.total === 1 ? 'una noticia' : contexto.total + ' noticias') + '.' : '');
                if (!s.hola) t = 'Me quedo aquí, a un lado. ' + t;
                if (buho().comentar(t, { tipo: 'seccion', acciones: [{ texto: 'Volver a la biblioteca', alElegir: function () { location.href = 'index.php'; } }] })) {
                    s.secciones.push(clave);
                    s.hola = 1;
                    guardarSesion(s);
                }
            } else if (contexto.vista === 'busqueda') {
                var n = contexto.total | 0, q = '«' + contexto.texto + '»';
                var dicho = n ? 'Busqué ' + q + ' en todo el archivo y encontré ' + (n === 1 ? 'una noticia' : n + ' noticias') + '.'
                              : 'Busqué ' + q + ' en todo el archivo y no encontré nada. Prueba con otra palabra, o vuelve a la biblioteca y pregúntame.';
                buho().comentar(dicho, { tipo: 'busqueda', acciones: n ? [] : [{ texto: 'Ir a la biblioteca', alElegir: function () { location.href = 'index.php#preguntar'; } }] });
                s.hola = 1;
                guardarSesion(s);
            } else if (biblio()) {
                var a = biblio().actual();
                if (!(a && a.indice >= 0 && explicar(a.indice, false)) && !s.hola) {
                    if (buho().comentar('Me quedo aquí, a un lado. Si buscas alguna noticia, tócame.', { tipo: 'hola' })) {
                        s.hola = 1;
                        guardarSesion(s);
                    }
                }
            } else if (!s.hola) {
                if (buho().comentar('Me quedo aquí, a un lado, por si me necesitas.', { tipo: 'hola' })) {
                    s.hola = 1;
                    guardarSesion(s);
                }
            }
        }, 700);
    }

    doc.addEventListener('biblio:panel', function (e) { alCambiarSeccion(e.detail || {}); });
    doc.addEventListener('buho:anclado', alLlegar);

    /* ══ 2. "PREGÚNTALE AL BÚHO" ══════════════════════════════════════ */
    var barra = null;          // nodos de la barra
    var motor = null;          // PlecBuscador
    var actual = null;         // último resultado mostrado
    var temaActivo = '';
    var tCorrer = 0, tComentar = 0, firmaMostrada = '', firmaComentada = '';

    var FECHAS = [
        ['', 'Cualquier fecha'], ['hoy', 'Hoy'], ['semana', 'Últimos 7 días'],
        ['mes', 'Últimos 30 días'], ['anio', 'Este año'], ['antes', 'Hace más de un año']
    ];

    // El ejemplo del campo: en el celular no cabe entero (se cortaba a la mitad)
    var mqAngosto = window.matchMedia ? window.matchMedia('(max-width: 520px)') : null;
    function textoEjemplo() {
        return mqAngosto && mqAngosto.matches ? 'Pregúntale al búho…' : 'Pregúntale al búho: «vacunas», «política de este mes»…';
    }
    // Sombra en el borde de una fila que se desliza (temas en el celular), solo del lado donde queda más
    function bordesTira(tira) {
        if (!tira) return;
        var pintar = function () {
            var max = tira.scrollWidth - tira.clientWidth;
            tira.classList.toggle('is-mas-izq', max > 1 && tira.scrollLeft > 2);
            tira.classList.toggle('is-mas-der', max > 1 && tira.scrollLeft < max - 2);
        };
        tira.addEventListener('scroll', pintar, { passive: true });
        window.addEventListener('resize', pintar);
        pintar();
    }

    function opcionSeccion(clave) {
        return barra && !!barra.seccion.querySelector('option[value="' + clave + '"]');
    }

    function crearBarra() {
        var B = biblio();
        var cab = doc.querySelector('#biblioteca .biblio-cabecera');
        if (!B || !cab || !window.PlecBuscador) return false;
        var extra = (indice && indice.extra) || [];
        var noticias = Object.keys(B.datos.noticias).map(function (k) { return B.datos.noticias[k]; }).concat(extra);
        motor = window.PlecBuscador.crear({
            noticias: noticias,
            secciones: (indice && indice.secciones) || B.datos.paneles.filter(function (p) { return p.cats; }),
            hoy: indice && indice.hoy
        });

        var secciones = motor.secciones();
        var temas = motor.temas().slice(0, 8);
        var form = doc.createElement('form');
        form.className = 'buho-pregunta';
        form.id = 'preguntar';
        form.setAttribute('role', 'search');
        form.setAttribute('aria-label', 'Pregúntale al búho');
        form.setAttribute('novalidate', '');
        form.innerHTML =
            '<div class="buho-pregunta-fila">' +
                '<label class="buho-pregunta-campo">' +
                    '<span class="buho-pregunta-cara">' + CARA + '</span>' +
                    '<span class="buho-sr">Pregúntale al búho</span>' +
                    '<input type="search" name="q" maxlength="100" autocomplete="off" spellcheck="false" enterkeyhint="search"' +
                        ' aria-keyshortcuts="/" aria-describedby="buhoPreguntaAyuda" placeholder="' + esc(textoEjemplo()) + '">' +
                    '<kbd class="buho-pregunta-atajo" aria-hidden="true">/</kbd>' +
                '</label>' +
                '<button type="submit" class="buho-pregunta-ir" aria-label="Preguntar al búho"><i class="fas fa-magnifying-glass" aria-hidden="true"></i><span>Preguntar</span></button>' +
            '</div>' +
            '<div class="buho-pregunta-filtros">' +
                '<label class="buho-pregunta-menu"><span>Sección</span><select name="seccion">' +
                    '<option value="">Todas las secciones</option>' +
                    secciones.map(function (s) { return '<option value="' + esc(s.clave) + '">' + esc(s.nombre) + '</option>'; }).join('') +
                '</select></label>' +
                '<label class="buho-pregunta-menu"><span>Fecha</span><select name="fecha">' +
                    FECHAS.map(function (f) { return '<option value="' + f[0] + '">' + f[1] + '</option>'; }).join('') +
                '</select></label>' +
                (temas.length ? '<div class="buho-pregunta-temas" role="group" aria-label="Temas">' +
                    temas.map(function (t) {
                        return '<button type="button" class="buho-tema" data-tema="' + esc(t.clave) + '" aria-pressed="false">' +
                            '<i class="fas ' + esc(t.icono) + '" aria-hidden="true"></i>' + esc(t.nombre) +
                            '<small aria-label="' + t.total + ' noticias">' + t.total + '</small></button>';
                    }).join('') + '</div>' : '') +
            '</div>' +
            '<p class="buho-sr buho-pregunta-sr" aria-live="polite"></p>' +
            '<p class="buho-sr" id="buhoPreguntaAyuda">Busca por palabras, sección o fecha. Con la flecha abajo vas a las respuestas; la tecla barra trae aquí desde cualquier parte.</p>';
        cab.insertBefore(form, cab.firstChild);

        barra = {
            form: form, input: form.querySelector('input'), seccion: form.querySelector('select[name="seccion"]'),
            fecha: form.querySelector('select[name="fecha"]'), sr: form.querySelector('.buho-pregunta-sr'),
            temas: Array.prototype.slice.call(form.querySelectorAll('.buho-tema'))
        };

        barra.input.addEventListener('input', function () {
            clearTimeout(tCorrer);
            tCorrer = setTimeout(function () { buscarAhora(false); }, 260);
        });
        barra.input.addEventListener('keydown', function (e) {
            // Escape en la barra: si está vacía, limpia también los filtros
            if (e.key === 'Escape' && !barra.input.value && (actual || barra.seccion.value || barra.fecha.value || temaActivo)) {
                e.preventDefault();
                e.stopPropagation();
                limpiar();
            }
            // ↓ entra a las respuestas (primer libro, o la primera idea si no hubo nada)
            if (e.key === 'ArrowDown' && !e.altKey && !e.ctrlKey && !e.metaKey && !e.shiftKey) {
                if (barra.input.value.trim() && !actual) { clearTimeout(tCorrer); buscarAhora(true); }
                var B = biblio();
                if (B && B.enfocarResultados && B.enfocarResultados()) e.preventDefault();
            }
        });
        if (mqAngosto) {
            var cambiarEjemplo = function () { barra.input.placeholder = textoEjemplo(); };
            if (mqAngosto.addEventListener) mqAngosto.addEventListener('change', cambiarEjemplo);
            else if (mqAngosto.addListener) mqAngosto.addListener(cambiarEjemplo);
        }
        bordesTira(form.querySelector('.buho-pregunta-temas'));
        barra.seccion.addEventListener('change', function () { buscarAhora(true); });
        barra.fecha.addEventListener('change', function () { buscarAhora(true); });
        barra.temas.forEach(function (b) {
            b.addEventListener('click', function () {
                ponerTema(temaActivo === b.getAttribute('data-tema') ? '' : b.getAttribute('data-tema'));
                buscarAhora(true);
            });
        });
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            clearTimeout(tCorrer);
            buscarAhora(true, true);
            // En el teléfono, que se cierre el teclado para ver los libros
            if (window.matchMedia && window.matchMedia('(hover: none)').matches) barra.input.blur();
        });
        return true;
    }

    function ponerTema(clave) {
        temaActivo = clave || '';
        barra.temas.forEach(function (b) {
            var si = b.getAttribute('data-tema') === temaActivo;
            b.setAttribute('aria-pressed', si ? 'true' : 'false');
            b.classList.toggle('is-activo', si);
        });
    }
    function consultaActual() {
        return { texto: barra.input.value, seccion: barra.seccion.value, fecha: barra.fecha.value, tema: temaActivo };
    }
    function ponerConsulta(q) {
        barra.input.value = q.texto || '';
        barra.seccion.value = opcionSeccion(q.seccion) ? q.seccion : '';
        barra.fecha.value = q.fecha || '';
        ponerTema(q.tema || '');
    }

    function htmlSugerencias(r) {
        var items = r.sugerencias.map(function (s, i) {
            var icono = s.enlace ? 'fa-box-archive' : (s.correccion ? 'fa-spell-check' : (s.tema ? 'fa-tag' : 'fa-wand-magic-sparkles'));
            return '<button type="button" class="buho-sugerencia" data-i="' + i + '">' +
                '<i class="fas ' + icono + '" aria-hidden="true"></i>' + esc(s.texto) +
                (s.cuantas ? ' <small>' + s.cuantas + '</small>' : '') + '</button>';
        }).join('');
        return '<div class="buho-vacio">' +
            '<span class="buho-vacio-cara">' + CARA + '</span>' +
            '<div class="buho-vacio-textos"><p>' + esc(r.comentario) + '</p>' +
            (items ? '<div class="buho-sugerencias">' + items + '</div>' : '') + '</div></div>';
    }

    function usarSugerencia(s) {
        if (!s) return;
        if (s.enlace) { location.href = s.enlace; return; }
        if (s.consulta) {
            ponerConsulta(s.consulta);
            buscarAhora(true, true);
        }
    }

    function firma(r) {
        return r.comentario + '|' + r.noticias.slice(0, MAX_LIBROS).map(function (n) { return n.id; }).join(',');
    }

    // Busca con lo que hay en la barra; comentar: el búho lo dice ya (si no, al dejar de escribir)
    function buscarAhora(comentar, insistir) {
        if (!barra) return;
        var r = motor.buscar(consultaActual());
        guia.busquedaHasta = Date.now() + 9000;
        if (!r.activa) {
            quitarResultados();
            return;
        }
        actual = r;
        var f = firma(r);
        if (f !== firmaMostrada) {
            firmaMostrada = f;
            var B = biblio();
            var sub = r.resumen + (r.total > MAX_LIBROS ? ' Te muestro las ' + MAX_LIBROS + ' más cercanas.' : '');
            var panel = B.resultados(r.noticias.slice(0, MAX_LIBROS), {
                titulo: 'Respuestas del búho', sub: sub, iconoHtml: CARA,
                vacioHtml: r.total ? '' : htmlSugerencias(r),
                alCerrar: function () { limpiar(true); }
            });
            panel.addEventListener('click', function (e) {
                var b = e.target.closest && e.target.closest('.buho-sugerencia');
                if (b && actual) usarSugerencia(actual.sugerencias[+b.getAttribute('data-i')]);
            });
        }
        clearTimeout(tComentar);
        tComentar = setTimeout(function () { comentarResultado(r, insistir); }, comentar ? 150 : ESPERA_COMENTARIO);
    }

    function comentarResultado(r, insistir) {
        if (r !== actual) return;
        var f = firma(r);
        if (f === firmaComentada && !insistir) return;
        firmaComentada = f;
        guia.busquedaHasta = Date.now() + 9000;
        var acciones = [];
        if (!r.total) {
            r.sugerencias.slice(0, 3).forEach(function (s) {
                acciones.push({ texto: s.texto + (s.cuantas ? ' (' + s.cuantas + ')' : ''), alElegir: function () { usarSugerencia(s); } });
            });
        }
        var dijo = puedeHablar() && buho().comentar(r.comentario, { tipo: 'busqueda', acciones: acciones });
        // Si el búho no pudo decirlo (minimizado…), que lo oiga igual quien usa lector de pantalla
        barra.sr.textContent = dijo ? '' : r.resumen;
    }

    function quitarResultados() {
        clearTimeout(tComentar);
        actual = null;
        firmaMostrada = firmaComentada = '';
        var B = biblio();
        if (B && B.hayResultados()) B.quitarResultados();
    }

    function limpiar(yaQuitado) {
        if (!barra) return;
        clearTimeout(tCorrer);
        ponerConsulta({});
        if (!yaQuitado) quitarResultados();
        else { clearTimeout(tComentar); actual = null; firmaMostrada = firmaComentada = ''; }
        if (buho()) buho().callar();
        barra.sr.textContent = '';
        try { barra.input.focus({ preventScroll: true }); } catch (e) { barra.input.focus(); }
    }

    function enfocarBarra() {
        if (!barra) return;
        try { barra.form.scrollIntoView({ block: 'center', behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' }); }
        catch (e) { barra.form.scrollIntoView(); }
        try { barra.input.focus({ preventScroll: true }); } catch (e) { barra.input.focus(); }
    }

    // Tocar al búho en su rincón
    doc.addEventListener('buho:tocar', function () {
        var B = buho();
        if (!B) return;
        if (!barra) {
            B.comentar('Para preguntarme por noticias, ven a la biblioteca de la portada.', {
                tipo: 'ayuda', acciones: [{ texto: 'Ir a la biblioteca', alElegir: function () { location.href = 'index.php#preguntar'; } }]
            });
            return;
        }
        enfocarBarra();
        // Ideas para empezar, sacadas de lo que hay en los estantes
        var ideas = [{ texto: 'Lo más reciente', q: { texto: 'lo más reciente' } }];
        var temas = motor.temas();
        if (temas[0]) ideas.push({ texto: temas[0].nombre, q: { tema: temas[0].clave } });
        var secs = motor.secciones().sort(function (a, b) { return b.total - a.total; });
        if (secs[0]) ideas.push({ texto: secs[0].nombre, q: { seccion: secs[0].clave } });
        B.comentar('¿Qué quieres leer? Escríbeme una palabra, una sección o una fecha, como «salud de esta semana».', {
            tipo: 'ayuda',
            acciones: ideas.map(function (i) {
                return { texto: i.texto, alElegir: function () { ponerConsulta(i.q); buscarAhora(true, true); } };
            })
        });
    });

    // La tecla / lleva a la barra (como en muchos buscadores)
    doc.addEventListener('keydown', function (e) {
        if (e.key !== '/' || e.ctrlKey || e.metaKey || e.altKey || !barra) return;
        var t = e.target;
        if (t && (t.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName))) return;
        if (hayVentana() || (buho() && buho().abierto())) return;
        e.preventDefault();
        enfocarBarra();
    });

    /* ══ ARRANQUE ═════════════════════════════════════════════════════ */
    function iniciar() {
        try { crearBarra(); } catch (err) {
            barra = null;
            try { console.warn('[Búho PLEC] La barra "Pregúntale al búho" no se pudo armar:', err); } catch (e) { /* nada */ }
        }
        // Enlace directo a la barra (index.php#preguntar), p. ej. desde otra página
        if (barra && location.hash === '#preguntar') setTimeout(enfocarBarra, 400);
        // Si el búho ya estaba en su rincón antes de que cargara este archivo
        if (buho() && buho().anclado() && buho().estado().enRincon && !buho().estado().volando) alLlegar();
    }

    window.PlecBuhoGuia = {
        preguntar: function (texto) { if (!barra) return false; ponerConsulta({ texto: texto }); buscarAhora(true, true); return true; },
        limpiar: function () { limpiar(); },
        estado: function () {
            return { barra: !!barra, consulta: barra ? consultaActual() : null, resultados: actual ? actual.total : null,
                     comentario: actual ? actual.comentario : null, sesion: sesion(), contexto: contexto };
        }
    };

    if (doc.readyState === 'loading') doc.addEventListener('DOMContentLoaded', iniciar);
    else iniciar();
})();
