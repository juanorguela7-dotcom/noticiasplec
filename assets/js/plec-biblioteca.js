/* ════════════════════════════════════════════════════════════════════
   NOTICIAS PLEC — BIBLIOTECA INTERACTIVA (sin dependencias)
   · Cada sección (Inicio, Colombia, Política…) es un bloque propio, uno
     debajo del otro, como en la portada clásica. Las pestañas de arriba
     son un índice: llevan a cada sección.
   · Dentro de cada sección, las noticias van en un ESTANTE horizontal con
     inercia: se arrastra (mouse o dedo), con la rueda horizontal del
     trackpad o Shift + rueda, o con las flechas. La rueda normal baja la
     página como siempre.
   · Las imágenes se cargan solo cuando su sección se acerca a la pantalla,
     y solo flotan los libros de las secciones que se ven.
   · Si algo falla, se quita y vuelve la portada clásica.
   · Etapa 2: cada noticia es un libro 3D (tapa, lomo y cantos) que flota;
     con el mouse encima (o el foco) gira despacio hasta quedar casi de
     frente, como la edición impresa de la portada; al abrirlo vuela al
     centro, pasa la tapa y unas hojas como páginas y se convierte en la
     ventana de la noticia de siempre. Al cerrarla vuelve a su sitio.
     Un clic o una tecla durante la animación la adelanta.
   · Etapa 5: PlecBiblioteca.resultados(noticias, op) pone un estante más,
     arriba de las secciones, con las respuestas de "Pregúntale al búho"
     (plec-buho-guia.js). Sus libros son iguales a los demás (arrastre,
     teclado, sonido, animación al abrir); su sección es -1 en los eventos.
     Las noticias del índice ampliado no traen contenido: se abren con
     su enlace (index.php?id=…).

   · Etapa 6 (pulido): cada libro dice cómo se usa el teclado a los lectores
     de pantalla (aria-describedby); Enter sobre una pestaña lleva el foco
     al título de la sección; la tira de pestañas se desliza sola hasta la
     pestaña marcada y muestra una sombra donde quedan más; ↓ en la barra
     del búho entra a las respuestas y ↑ en ellas vuelve a la barra.

   Teclado (con el foco en un libro):
     ← →          libro anterior / siguiente del estante
     ↑ ↓          al estante de la sección de arriba / de abajo
                  (↑ en las respuestas del búho: vuelve a la barra)
     Inicio / Fin primer / último libro del estante
     Enter        abre la noticia

   Eventos para las siguientes etapas (se emiten en document):
     biblio:panel     { indice, clave, nombre, total, origen }  (sección que más se ve)
     biblio:deslizar  { tipo: 'estante' }                      (empieza un arrastre)
     biblio:mover     { indice, velocidad, libro, pasa, fuera, arrastre }
                      en cada cuadro en que se mueve un estante; velocidad en px/ms,
                      libro = el que queda en el centro, pasa = ese libro acaba de
                      cambiar, fuera = el estante se pasó del borde (efecto goma)
     biblio:sobre     { id, seccion, posicion, origen: 'puntero' | 'teclado' }
                      el mouse entra en un libro, o el teclado lo enfoca
     biblio:abrir     { id, noticia, seccion }
     biblio:animacion { fase: 'abrir' | 'cerrar' | 'adelantar', marcas }
                      empieza la animación del libro; marcas = cuándo pasa cada
                      cosa, en ms desde ahora (vuelo, tapa, hojas…)
     biblio:cerrar    { id }
   API: window.PlecBiblioteca (irA, irAClave, abrir, resultados, quitarResultados,
        hayResultados, enfocarResultados, actual, estado…)
   ════════════════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    var doc = document, html = doc.documentElement;
    var raiz = doc.getElementById('biblioteca');
    var datosEl = doc.getElementById('biblioDatos');

    function aviso(motivo) {
        try { console.warn('[Biblioteca PLEC] Se muestra la portada clásica:', motivo); } catch (e) { /* nada */ }
    }
    function usarPortadaClasica(motivo) {
        html.classList.remove('biblio-pendiente', 'biblio-activa');
        if (motivo) aviso(motivo);
    }

    if (!raiz || !datosEl) { usarPortadaClasica(); return; }

    var DATOS;
    try { DATOS = JSON.parse(datosEl.textContent); } catch (e) { usarPortadaClasica(e); return; }
    if (!DATOS || !DATOS.paneles || !DATOS.paneles.length || !DATOS.noticias) { usarPortadaClasica('sin datos'); return; }
    if (getComputedStyle(raiz).getPropertyValue('--biblio-ok').trim() !== '1') { usarPortadaClasica('el CSS de la biblioteca no cargó'); return; }

    /* ── CONSTANTES Y PREFERENCIAS ─────────────────────────────────── */
    var PLACEHOLDER = 'img/placeholder.svg';
    var UMBRAL = 7;          // px que hay que mover antes de considerar que es un arrastre
    var VMAX = 4.5;          // velocidad máxima del estante (px/ms)
    var LINEA = 20;          // px por "línea" de rueda (Firefox)

    var mqReduce = window.matchMedia('(prefers-reduced-motion: reduce)');
    var reduce = mqReduce.matches;

    var paneles = DATOS.paneles;
    var N = paneles.length;

    /* ── UTILIDADES ────────────────────────────────────────────────── */
    function clamp(v, a, b) { return v < a ? a : (v > b ? b : v); }
    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function rgbDe(hex) {
        var m = /^#?([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i.exec(hex || '');
        return m ? parseInt(m[1], 16) + ', ' + parseInt(m[2], 16) + ', ' + parseInt(m[3], 16) : '138, 138, 153';
    }
    // Resistencia elástica al pasarse del borde (como en iOS)
    function goma(exceso, dim) { return (1 - 1 / (exceso * 0.55 / dim + 1)) * dim; }
    function emitir(nombre, detalle) {
        try { doc.dispatchEvent(new CustomEvent(nombre, { detail: detalle })); } catch (e) { /* navegadores muy viejos */ }
    }
    function ruedaPx(e) {
        var f = e.deltaMode === 1 ? LINEA : (e.deltaMode === 2 ? window.innerHeight : 1);
        return { x: e.deltaX * f, y: e.deltaY * f };
    }
    function fechaTexto(n) {
        var h = n.h;
        if (h < 1) return 'Hace menos de 1 hora';
        if (h < 24) return 'Hace ' + h + ' hora' + (h !== 1 ? 's' : '');
        if (h < 48) return 'Ayer';
        var p = (n.f || '').split('-');
        return p.length === 3 ? p[2] + '/' + p[1] + '/' + p[0] : '';
    }

    // Color de cada categoría según su panel (para el borde y la etiqueta de cada libro)
    var colorCat = {}, iconoCat = {};
    paneles.forEach(function (p) {
        (p.cats || []).forEach(function (c) { colorCat[c] = p.color; iconoCat[c] = p.icono; });
    });

    /* ── CONSTRUCCIÓN DEL HTML ─────────────────────────────────────── */
    // Número fijo por noticia (siempre el mismo para el mismo id), para variar un poco cada libro
    function variacion(id, n) { return ((id * 2654435761) >>> 0) % n; }

    function htmlLibro(n, i) {
        var color = colorCat[n.ci] || '#8a8a99';
        var icono = iconoCat[n.ci] || 'fa-newspaper';
        var fecha = fechaTexto(n);
        var etiqueta = n.t + (n.k ? ', ' + n.k : '') + (fecha ? ', ' + fecha : '');
        var img = n.i && n.i !== PLACEHOLDER
            ? '<img alt="" draggable="false" decoding="async" data-src="' + esc(n.i) + '">'
            : '';
        // Cada libro gira un poco distinto (19°–27°) y flota a su propio ritmo, desfasado del vecino
        var giro = 19 + variacion(n.id, 9);
        var dur = 4.6 + variacion(n.id + 7, 6) * 0.35;
        var retraso = -(((i || 0) * 0.83 + variacion(n.id + 3, 10) * 0.21) % dur);
        return '<li class="biblio-item">' +
            '<a class="biblio-libro" href="index.php?id=' + n.id + '" data-id="' + n.id + '" draggable="false" tabindex="-1"' +
            ' aria-label="' + esc(etiqueta) + '" aria-describedby="biblio-ayuda-sr" style="--acento:' + color + ';--acento-rgb:' + rgbDe(color) +
            ';--giro:' + giro + 'deg;--flota-dur:' + dur.toFixed(2) + 's;--flota-retraso:' + retraso.toFixed(2) + 's">' +
                '<span class="biblio-libro-flota"><span class="biblio-libro-3d">' +
                    '<span class="biblio-lomo" aria-hidden="true"><span class="biblio-lomo-texto">' + esc(n.k || 'Noticias PLEC') + '</span></span>' +
                    '<span class="biblio-tapa">' +
                        '<span class="biblio-portada"><i class="fas ' + esc(icono) + ' biblio-portada-icono" aria-hidden="true"></i>' + img + '</span>' +
                        (n.k ? '<span class="biblio-cat" aria-hidden="true">' + esc(n.k) + '</span>' : '') +
                        (n.h <= 48 ? '<span class="biblio-nuevo" aria-hidden="true"><i class="fas fa-bolt"></i> Nuevo</span>' : '') +
                        '<span class="biblio-info" aria-hidden="true">' +
                            '<span class="biblio-titulo">' + esc(n.t) + '</span>' +
                            (fecha ? '<span class="biblio-fecha"><i class="fas fa-clock"></i>' + esc(fecha) + '</span>' : '') +
                        '</span>' +
                    '</span>' +
                '</span></span>' +
                '<span class="biblio-sombra" aria-hidden="true"></span>' +
            '</a></li>';
    }

    function htmlEstante(libros, nombre) {
        return '<div class="biblio-estante">' +
                '<button type="button" class="biblio-flecha biblio-flecha-izq" data-dir="-1" tabindex="-1" aria-hidden="true"><i class="fas fa-chevron-left"></i></button>' +
                '<div class="biblio-estante-vista">' +
                    '<ul class="biblio-estante-fila" role="list" aria-label="' + esc(nombre) + '">' + libros.map(htmlLibro).join('') + '</ul>' +
                '</div>' +
                '<button type="button" class="biblio-flecha biblio-flecha-der" data-dir="1" tabindex="-1" aria-hidden="true"><i class="fas fa-chevron-right"></i></button>' +
                '<div class="biblio-repisa" aria-hidden="true"></div>' +
                '<div class="biblio-progreso" aria-hidden="true"><span></span></div>' +
            '</div>';
    }

    function htmlPanel(p) {
        var libros = p.ids.map(function (id) { return DATOS.noticias[id]; }).filter(Boolean);
        var accion = '';
        if (p.enlace) {
            accion = '<a class="biblio-ver-todo" href="' + esc(p.enlace) + '" draggable="false">Ver todo <i class="fas fa-arrow-right" aria-hidden="true"></i></a>';
        } else if (p.clave === 'opinion' && typeof window.abrirOpinion === 'function') {
            accion = '<button type="button" class="biblio-ver-todo" data-accion="opinion">Columnistas <i class="fas fa-arrow-right" aria-hidden="true"></i></button>';
        }
        var total = p.total || libros.length;
        // id="seccion-…": los enlaces #seccion-politica llevan directo a la sección
        return '<section class="biblio-panel" id="seccion-' + esc(p.clave) + '" aria-labelledby="biblio-tit-' + esc(p.clave) + '"' +
            ' style="--acento:' + p.color + ';--acento-rgb:' + rgbDe(p.color) + '">' +
            '<span class="biblio-panel-marca" aria-hidden="true">' + esc(p.nombre) + '</span>' +
            // <div> y no <header>: el sitio tiene reglas globales para header (centrado, fondo en modo claro)
            '<div class="biblio-panel-cab">' +
                '<span class="biblio-panel-icono" aria-hidden="true"><i class="fas ' + esc(p.icono) + '"></i></span>' +
                '<div class="biblio-panel-textos">' +
                    '<h2 class="biblio-panel-titulo" id="biblio-tit-' + esc(p.clave) + '" tabindex="-1">' + esc(p.nombre) + '</h2>' +
                    '<p class="biblio-panel-sub">' + esc(p.sub) + ' · ' + total + ' noticia' + (total !== 1 ? 's' : '') + '</p>' +
                '</div>' + accion +
            '</div>' +
            htmlEstante(libros, 'Noticias de ' + p.nombre) +
        '</section>';
    }

    // Etapa 5: el estante con las respuestas del búho ("Pregúntale al búho").
    // op: { titulo, sub (lo que dice el búho), iconoHtml, color, vacioHtml (si no hay libros) }
    function htmlResultados(libros, op) {
        var color = op.color || '#f6b021';
        return '<section class="biblio-panel biblio-resultados" id="biblio-resultados" aria-labelledby="biblio-tit-resultados"' +
            ' style="--acento:' + color + ';--acento-rgb:' + rgbDe(color) + '">' +
            '<span class="biblio-panel-marca" aria-hidden="true">Búho</span>' +
            '<div class="biblio-panel-cab">' +
                '<span class="biblio-panel-icono" aria-hidden="true">' + (op.iconoHtml || '<i class="fas fa-magnifying-glass"></i>') + '</span>' +
                '<div class="biblio-panel-textos">' +
                    '<h2 class="biblio-panel-titulo" id="biblio-tit-resultados" tabindex="-1">' + esc(op.titulo || 'Respuestas del búho') + '</h2>' +
                    '<p class="biblio-panel-sub biblio-resultados-dice">' + esc(op.sub || '') + '</p>' +
                '</div>' +
                '<button type="button" class="biblio-ver-todo" data-accion="resultados-cerrar">Cerrar <i class="fas fa-xmark" aria-hidden="true"></i></button>' +
            '</div>' +
            (libros.length ? htmlEstante(libros, op.titulo || 'Respuestas del búho')
                           : '<div class="biblio-resultados-vacio">' + (op.vacioHtml || '') + '</div>') +
        '</section>';
    }

    function construir() {
        // Sin índice de pestañas: repetía el menú principal (Inicio, Colombia, Política…),
        // que queda justo arriba. Los enlaces #seccion-… siguen funcionando.
        var cont = doc.createElement('div');
        cont.className = 'biblio-montaje';
        cont.innerHTML =
            '<div class="biblio-cabecera">' +
                '<p class="biblio-ayuda">' +
                    '<span class="biblio-ayuda-raton">Arrastra los libros o usa <kbd>Shift</kbd> + rueda · <kbd>←</kbd> <kbd>→</kbd> recorren el estante · <kbd>Enter</kbd> abre</span>' +
                    '<span class="biblio-ayuda-tactil">Desliza ↔ los libros de cada sección · toca uno para leerlo</span>' +
                '</p>' +
                // Lo que oye quien usa lector de pantalla al llegar a un libro (aria-describedby)
                '<p class="biblio-sr" id="biblio-ayuda-sr">Flechas izquierda y derecha: otro libro del estante. Arriba y abajo: otra sección. Enter: abrir la noticia.</p>' +
            '</div>' +
            '<div class="biblio-secciones">' + paneles.map(htmlPanel).join('') + '</div>';
        raiz.appendChild(cont);
        return cont;
    }

    /* ── ESTANTE CON INERCIA ───────────────────────────────────────── */
    function Estante(panelEl, indice) {
        this.indice = indice;
        this.panel = panelEl;
        this.vista = panelEl.querySelector('.biblio-estante-vista');
        this.fila = panelEl.querySelector('.biblio-estante-fila');
        this.libros = Array.prototype.slice.call(this.fila.querySelectorAll('.biblio-libro'));
        this.izq = panelEl.querySelector('.biblio-flecha-izq');
        this.der = panelEl.querySelector('.biblio-flecha-der');
        this.progreso = panelEl.querySelector('.biblio-progreso');
        this.barra = this.progreso.querySelector('span');
        this.x = 0; this.v = 0; this.objetivo = null;
        this.arrastrando = false; this.x0 = 0;
        this.min = 0; this.max = 0; this.vw = 0; this.cw = 0; this.cabe = true;
        this.pos = []; this.anchos = [];
        this.actual = 0;
        this.cerca = false;          // su sección está en pantalla o cerca (IntersectionObserver)
        this.centro = -1;            // libro que queda en el centro (para biblio:mover)
        this.avisado = 0;            // última posición avisada con biblio:mover
        this.pintado = NaN; this.cargadoEn = NaN;
        if (this.libros[0]) this.libros[0].tabIndex = 0;
    }
    Estante.prototype.medir = function () {
        this.vw = this.vista.clientWidth;
        this.cw = this.fila.offsetWidth;
        this.pw = this.progreso.clientWidth || this.vw;
        this.margen = parseFloat(getComputedStyle(this.fila).paddingLeft) || 16;
        this.pos = this.libros.map(function (l) { return l.parentNode.offsetLeft; });
        this.anchos = this.libros.map(function (l) { return l.parentNode.offsetWidth; });
        if (this.cw <= this.vw) {
            // Caben todos: se centran y no hay nada que desplazar
            this.cabe = true;
            this.min = this.max = Math.round((this.vw - this.cw) / 2);
            this.x = this.min; this.v = 0; this.objetivo = null;
        } else {
            this.cabe = false;
            this.min = this.vw - this.cw;
            this.max = 0;
            this.x = clamp(this.x, this.min, this.max);
            if (this.objetivo != null) this.objetivo = clamp(this.objetivo, this.min, this.max);
        }
        this.pintar(true);
        this.centro = this.libroEnCentro();
        this.avisado = this.x;       // al cambiar el tamaño no "se desliza" nada
    };
    // Índice del libro cuyo centro queda más cerca del centro de la vista
    Estante.prototype.libroEnCentro = function () {
        var c = this.vw / 2 - this.x, mejor = 0, dist = Infinity;
        for (var i = 0; i < this.pos.length; i++) {
            var d = Math.abs(this.pos[i] + this.anchos[i] / 2 - c);
            if (d < dist) { dist = d; mejor = i; }
        }
        return mejor;
    };
    // Si el estante se movió desde el último aviso, lo avisa (biblio:mover); dt = ms transcurridos.
    // Se compara con la última posición avisada porque al arrastrar x cambia fuera de paso().
    Estante.prototype.avisar = function (dt) {
        var dx = this.x - this.avisado;
        if (!dx) return;
        this.avisado = this.x;
        var c = this.libroEnCentro(), pasa = c !== this.centro;
        this.centro = c;
        emitir('biblio:mover', {
            indice: this.indice, velocidad: dx / dt, libro: c, pasa: pasa,
            fuera: this.x > this.max + 0.5 || this.x < this.min - 0.5, arrastre: this.arrastrando
        });
    };
    // dir > 0: avanzar (el contenido se mueve a la izquierda)
    Estante.prototype.puede = function (dir) {
        if (this.cabe) return false;
        var x = this.objetivo != null ? this.objetivo : this.x;
        return dir > 0 ? x > this.min + 1 : x < this.max - 1;
    };
    Estante.prototype.enMovimiento = function () { return Math.abs(this.v) > 0.05 || this.objetivo != null; };
    Estante.prototype.agarrar = function () { this.v = 0; this.objetivo = null; };
    Estante.prototype.empezar = function () { this.arrastrando = true; this.x0 = this.x; this.v = 0; this.objetivo = null; };
    Estante.prototype.arrastrar = function (dx) {
        var r = this.x0 + dx;
        if (r > this.max) r = this.max + goma(r - this.max, this.vw);
        else if (r < this.min) r = this.min - goma(this.min - r, this.vw);
        this.x = r;
    };
    Estante.prototype.soltar = function (v) {
        this.arrastrando = false;
        this.v = reduce ? 0 : clamp(v, -VMAX, VMAX);
    };
    Estante.prototype.irA = function (x, instantaneo) {
        this.v = 0;
        this.objetivo = clamp(x, this.min, this.max);
        if (instantaneo || reduce) {
            this.x = this.objetivo; this.objetivo = null; this.pintar();
            this.avisar(120);        // un salto sin animación cuenta como un deslizamiento corto (≈120 ms)
        }
    };
    Estante.prototype.rueda = function (d) {
        var base = this.objetivo != null ? this.objetivo : this.x;
        this.irA(base - d);
    };
    Estante.prototype.porPagina = function (dir) {
        var base = this.objetivo != null ? this.objetivo : this.x;
        this.irA(base - dir * this.vw * 0.8);
    };
    Estante.prototype.revelar = function (i, instantaneo) {
        if (this.cabe || this.pos[i] == null) return;
        var M = this.margen, izq = this.pos[i], der = izq + this.anchos[i];
        var base = this.objetivo != null ? this.objetivo : this.x;
        if (izq + base < M) this.irA(M - izq, instantaneo);
        else if (der + base > this.vw - M) this.irA(this.vw - M - der, instantaneo);
    };
    Estante.prototype.marcarActual = function (i) {
        if (!this.libros[i]) return;
        if (this.libros[this.actual]) this.libros[this.actual].tabIndex = -1;
        this.actual = i;
        this.libros[i].tabIndex = 0;
    };
    // Un paso de la animación; k = tiempo del cuadro / 16,67 ms. Devuelve true si sigue moviéndose.
    Estante.prototype.paso = function (k) {
        var activo = false;
        if (this.arrastrando) {
            activo = true;
        } else if (this.objetivo != null) {
            this.x += (this.objetivo - this.x) * (1 - Math.pow(0.78, k));
            if (Math.abs(this.objetivo - this.x) < 0.4) { this.x = this.objetivo; this.objetivo = null; }
            else activo = true;
        } else {
            if (Math.abs(this.v) > 0.01) {
                this.x += this.v * k * 16.67;
                this.v *= Math.pow(0.95, k);          // fricción: la inercia se apaga sola
                activo = true;
            } else {
                this.v = 0;
            }
            var b = this.x > this.max ? this.max : (this.x < this.min ? this.min : null);
            if (b !== null) {                          // se pasó del borde: vuelve con un resorte
                this.v *= Math.pow(0.5, k);
                this.x += (b - this.x) * (1 - Math.pow(0.8, k));
                if (Math.abs(b - this.x) < 0.4) { this.x = b; this.v = 0; }
                else activo = true;
            }
        }
        this.pintar();
        return activo;
    };
    Estante.prototype.pintar = function (forzar) {
        if (!forzar && this.x === this.pintado) return;
        this.pintado = this.x;
        this.fila.style.transform = 'translate3d(' + this.x.toFixed(2) + 'px,0,0)';

        var sinIzq = this.cabe || this.x >= this.max - 1;
        var sinDer = this.cabe || this.x <= this.min + 1;
        if (this.izq.disabled !== sinIzq) this.izq.disabled = sinIzq;
        if (this.der.disabled !== sinDer) this.der.disabled = sinDer;

        if (this.progreso.hidden !== this.cabe) this.progreso.hidden = this.cabe;
        if (!this.cabe) {
            var frac = this.vw / this.cw;
            var avance = clamp((this.max - this.x) / (this.max - this.min), 0, 1);
            this.barra.style.transform = 'translateX(' + (avance * (1 - frac) * this.pw).toFixed(1) + 'px) scaleX(' + frac.toFixed(4) + ')';
        }
        if (!(Math.abs(this.x - this.cargadoEn) < 90)) this.cargarVisibles();
    };
    // Carga diferida: solo los libros que están (o casi) a la vista, en secciones cercanas a la pantalla
    Estante.prototype.cargarVisibles = function (forzar) {
        if (!forzar && !this.cerca) return;
        this.cargadoEn = this.x;
        var desde = -this.x - 320, hasta = -this.x + this.vw + 320;
        for (var i = 0; i < this.libros.length; i++) {
            if (this.pos[i] + this.anchos[i] < desde || this.pos[i] > hasta) continue;
            var img = this.libros[i].querySelector('img[data-src]');
            if (img) cargarImagen(img);
        }
    };

    function cargarImagen(img) {
        var src = img.getAttribute('data-src');
        img.removeAttribute('data-src');
        img.addEventListener('load', function () { img.classList.add('is-cargada'); }, { once: true });
        img.addEventListener('error', function () {
            if (img.getAttribute('src') !== PLACEHOLDER) img.src = PLACEHOLDER;
        });
        img.src = src;
    }

    /* ── ESTADO ────────────────────────────────────────────────────── */
    var montaje, panelesEl, estantes;
    var actual = -1;                      // sección que más se ve (índice y evento biblio:panel)
    var raf = 0, ultimoT = 0;
    var gesto = null, suprimirClick = false, ultimoLibro = null;
    var sobre = null;                     // libro bajo el puntero del mouse (biblio:sobre)
    var focoCallado = false;              // true mientras el foco vuelve solo al libro (no es "pasar sobre él")
    var resultado = null;                 // estante de respuestas del búho: { panel, estante, alCerrar }
    var obsCerca = null;                  // IntersectionObserver de "sección cerca de la pantalla"

    // Estante de la sección que contiene a "el"
    function estanteDe(el) {
        var panel = el && el.closest ? el.closest('.biblio-panel') : null;
        if (panel && resultado && panel === resultado.panel) return resultado.estante;
        var i = panel ? panelesEl.indexOf(panel) : -1;
        return i >= 0 ? estantes[i] : null;
    }
    // Todos los estantes en el orden de la página (el de respuestas, si lo hay, va primero)
    function todosEstantes() {
        return resultado && resultado.estante ? [resultado.estante].concat(estantes) : estantes;
    }

    function despertar() {
        if (!raf) { ultimoT = performance.now(); raf = requestAnimationFrame(bucle); }
    }
    function bucle(t) {
        var dt = clamp(t - ultimoT, 1, 64);
        ultimoT = t;
        var k = dt / 16.67, activo = !!gesto, todos = todosEstantes();
        for (var i = 0; i < todos.length; i++) {
            if (todos[i].paso(k)) activo = true;
            todos[i].avisar(dt);
        }
        raf = activo ? requestAnimationFrame(bucle) : 0;
    }

    /* ── SECCIONES: ÍNDICE Y SECCIÓN QUE MÁS SE VE ─────────────────── */
    function indiceDeClave(clave) {
        for (var i = 0; i < N; i++) if (paneles[i].clave === clave) return i;
        return -1;
    }
    // Lleva la página hasta la sección i (el CSS deja espacio para la barra de navegación fija)
    function irASeccion(i, suave) {
        var el = panelesEl[clamp(Math.round(i), 0, N - 1)];
        if (!el) return false;
        try { el.scrollIntoView({ behavior: suave && !reduce ? 'smooth' : 'auto', block: 'start' }); }
        catch (e) { el.scrollIntoView(true); }
        return true;
    }
    function enfocarSinMover(el) {
        if (!el) return;
        try { el.focus({ preventScroll: true }); } catch (e) { el.focus(); }
    }
    function marcarSeccion(i, origen) {
        if (i === actual || i < 0) return;
        actual = i;
        var p = paneles[i];
        emitir('biblio:panel', { indice: i, clave: p.clave, nombre: p.nombre, total: p.total || p.ids.length, origen: origen || 'scroll' });
    }
    // Qué secciones están cerca de la pantalla (cargan imágenes y sus libros flotan) y cuál se ve más
    function observarSecciones() {
        if (!('IntersectionObserver' in window)) {
            estantes.forEach(function (est) { est.cerca = true; est.panel.classList.add('is-visible'); est.cargarVisibles(true); });
            marcarSeccion(0, 'inicio');
            return;
        }
        var cerca = obsCerca = new IntersectionObserver(function (entradas) {
            entradas.forEach(function (en) {
                var est = estanteDe(en.target);
                if (!est) return;
                est.cerca = en.isIntersecting;
                en.target.classList.toggle('is-visible', en.isIntersecting);
                if (en.isIntersecting) est.cargarVisibles(true);
            });
        }, { rootMargin: '300px 0px' });
        var visto = [];
        var cuanto = new IntersectionObserver(function (entradas) {
            entradas.forEach(function (en) {
                visto[panelesEl.indexOf(en.target)] = en.isIntersecting ? en.intersectionRect.height : 0;
            });
            var mejor = -1, max = 0;
            for (var i = 0; i < N; i++) if ((visto[i] || 0) > max) { max = visto[i]; mejor = i; }
            if (mejor >= 0) marcarSeccion(mejor, 'scroll');
        }, { threshold: [0, 0.25, 0.5, 0.75, 1] });
        panelesEl.forEach(function (el) { cerca.observe(el); cuanto.observe(el); });
    }

    /* ── ARRASTRE DE LOS ESTANTES ──────────────────────────────────── */
    function velocidad(muestras, ahora) {
        if (muestras.length < 2) return 0;
        var ult = muestras[muestras.length - 1];
        if (ahora - ult[1] > 90) return 0;            // el dedo se quedó quieto antes de soltar
        var pri = ult;
        for (var i = muestras.length - 1; i >= 0; i--) {
            if (ult[1] - muestras[i][1] > 100) break;
            pri = muestras[i];
        }
        var dt = ult[1] - pri[1];
        return dt > 0 ? (ult[0] - pri[0]) / dt : 0;
    }

    function alPresionar(e) {
        if (gesto || (e.pointerType === 'mouse' && e.button !== 0)) return;
        var vista = e.target.closest && e.target.closest('.biblio-estante-vista');
        var est = vista ? estanteDe(vista) : null;
        if (!est) return;
        gesto = {
            id: e.pointerId, x0: e.clientX, y0: e.clientY, estado: 'espera', estante: est, vista: vista,
            muestras: [[e.clientX, e.timeStamp]], atrapo: false
        };
        // Tocar un estante que se está moviendo lo detiene (y ese toque no abre un libro)
        if (est.enMovimiento()) { est.agarrar(); gesto.atrapo = true; }
    }

    function alMover(e) {
        if (!gesto || e.pointerId !== gesto.id) return;
        var dx = e.clientX - gesto.x0, dy = e.clientY - gesto.y0;
        gesto.muestras.push([e.clientX, e.timeStamp]);
        if (gesto.muestras.length > 8) gesto.muestras.shift();

        if (gesto.estado === 'espera') {
            if (Math.abs(dx) < UMBRAL && Math.abs(dy) < UMBRAL) return;
            if (Math.abs(dy) > Math.abs(dx)) {            // vertical: es de la página
                gesto.estado = 'vertical';                // y al soltar no debe abrir un libro
                terminar(e, true);
                return;
            }
            gesto.estado = 'estante';
            gesto.estante.empezar();
            try { gesto.vista.setPointerCapture(e.pointerId); } catch (err) { /* nada */ }
            raiz.classList.add('is-arrastrando');
            emitir('biblio:deslizar', { tipo: 'estante' });
        }
        gesto.estante.arrastrar(dx);
        despertar();
        if (e.cancelable && e.pointerType === 'mouse') e.preventDefault();
    }

    function terminar(e, cancelado) {
        if (!gesto || (e && e.pointerId !== gesto.id)) return;
        var g = gesto;
        gesto = null;
        raiz.classList.remove('is-arrastrando');
        if (g.estado === 'estante') g.estante.soltar(cancelado ? 0 : velocidad(g.muestras, e ? e.timeStamp : performance.now()));
        if (g.estado !== 'espera' || g.atrapo) {
            suprimirClick = true;
            setTimeout(function () { suprimirClick = false; }, 400);
        }
        despertar();
    }

    /* ── RUEDA DEL MOUSE / TRACKPAD ────────────────────────────────── */
    // La rueda normal baja la página como siempre. La horizontal (trackpad) o Shift + rueda mueven el estante.
    function alRueda(e) {
        if (e.ctrlKey) return;                    // zoom del navegador
        var est = estanteDe(e.currentTarget);
        if (!est) return;
        var d = ruedaPx(e), dx = d.x, dy = d.y;
        if (e.shiftKey && !dx) { dx = dy; dy = 0; }
        if (!dx || Math.abs(dx) <= Math.abs(dy)) return;
        e.preventDefault();                       // también evita el "atrás" del navegador al deslizar en el trackpad
        if (est.puede(dx > 0 ? 1 : -1)) { est.rueda(dx); despertar(); }
    }

    /* ── CLIC ──────────────────────────────────────────────────────── */
    function alClic(e) {
        if (suprimirClick) { e.preventDefault(); e.stopPropagation(); suprimirClick = false; return; }

        var flecha = e.target.closest('.biblio-flecha');
        if (flecha) {
            var est = estanteDe(flecha);
            if (est) { est.porPagina(+flecha.getAttribute('data-dir')); despertar(); }
            return;
        }

        if (e.target.closest('[data-accion="opinion"]')) {
            if (typeof window.abrirOpinion === 'function') window.abrirOpinion();
            return;
        }

        if (e.target.closest('[data-accion="resultados-cerrar"]')) {
            var alCerrar = resultado && resultado.alCerrar;
            quitarResultados();
            if (typeof alCerrar === 'function') alCerrar();
            return;
        }

        var libro = e.target.closest('.biblio-libro');
        if (libro) {
            if (e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0) return;   // abrir en otra pestaña
            e.preventDefault();
            abrirNoticia(+libro.getAttribute('data-id'), libro);
        }
    }

    /* ── LIBRO BAJO EL PUNTERO ─────────────────────────────────────── */
    // El giro hacia el frente (como la edición impresa) lo hace solo el CSS con
    // :hover y :focus-visible; aquí solo se avisa a quien escuche (el sonido).
    // Avisa que el puntero (o el teclado) llegó a un libro
    function avisarSobre(libro, origen) {
        var est = estanteDe(libro);
        emitir('biblio:sobre', {
            id: +libro.getAttribute('data-id'), seccion: est ? est.indice : -1,
            posicion: est ? est.libros.indexOf(libro) : -1, origen: origen
        });
    }
    function alPunteroSobreLibro(e) {
        if (e.pointerType !== 'mouse') return;
        var bajo = !gesto && !lector && e.target.closest ? e.target.closest('.biblio-libro') : null;
        // Durante un arrastre no cuenta: al soltar, el libro que quede debajo sí
        if (!gesto && bajo !== sobre) {
            sobre = bajo;
            if (bajo) avisarSobre(bajo, 'puntero');
        }
    }

    /* ── LIBRO QUE SE ABRE ─────────────────────────────────────────── */
    // Al abrir: una copia grande del libro sale del estante y vuela al centro,
    // la tapa y unas hojas pasan como páginas y la página derecha crece hasta
    // convertirse en la ventana de la noticia de siempre (#miModal: voz,
    // compartir, QR, guardar y contador de visitas siguen igual). Al cerrar,
    // el mismo recorrido al revés. Usa la Web Animations API; sin ella, o con
    // "reducir movimiento", la noticia se abre y se cierra como siempre.
    //
    // Deben coincidir con plec-biblioteca.css. La pose del libro se lee del propio
    // libro (poseReal); POSE_RX solo se usa en navegadores sin DOMMatrix.
    var PERSPECTIVA = 900;      // perspective de .biblio-libro
    var POSE_RX = -6;           // --rx en reposo
    var HOJAS = 3;              // hojas que pasan detrás de la tapa
    var CURVA_VUELO = 'cubic-bezier(.2, .75, .25, 1)';
    var CURVA_HOJA = 'cubic-bezier(.6, .05, .3, 1)';
    var CURVA_SUAVE = 'cubic-bezier(.55, 0, .25, 1)';
    var SIN_CAMBIO = 'translate(0px,0px) scale(1,1)';

    var modalEl = doc.getElementById('miModal');
    var hayWAAPI = typeof window.Animation === 'function' && typeof Element.prototype.animate === 'function' &&
        'finished' in window.Animation.prototype && typeof Promise === 'function';
    var envuelto = false;       // true cuando cerrarModal pasa por aquí (ver iniciar)
    // Noticia abierta desde un libro:
    // { estado: 'abriendo' | 'abierto' | 'cerrando', id, n, libro, partes, anims, fin }
    var lector = null;

    function puedeAnimar() { return hayWAAPI && !reduce && !!modalEl; }
    function avisoLibro(err) {
        try { console.warn('[Biblioteca PLEC] Se omitió la animación del libro:', err); } catch (e) { /* nada */ }
    }
    function copiar(o) { var r = {}; for (var k in o) r[k] = o[k]; return r; }
    function numVar(el, nombre, def) { var v = parseFloat(el.style.getPropertyValue(nombre)); return isNaN(v) ? def : v; }
    function coincide(el, sel) { try { return el.matches(sel); } catch (e) { return false; } }
    function enPantalla(r) { return r.w > 20 && r.y + r.h > 0 && r.y < window.innerHeight && r.x + r.w > 0 && r.x < window.innerWidth; }
    // Parte visible (en la ventana del navegador) de un rectángulo
    function rectVisible(r) {
        var arriba = Math.max(r.top, 0), abajo = Math.min(r.bottom, window.innerHeight);
        return { x: r.left, y: arriba, w: r.width, h: abajo - arriba };
    }
    function tVuelo(x, y, s) { return 'translate(' + x.toFixed(1) + 'px,' + y.toFixed(1) + 'px) scale(' + s.toFixed(4) + ')'; }
    function tLibro(p) {
        return 'translate3d(' + p.tx.toFixed(1) + 'px,' + p.sube.toFixed(1) + 'px,' + p.acerca.toFixed(1) + 'px)' +
            ' rotateX(' + p.rx.toFixed(2) + 'deg) rotateY(' + p.ry.toFixed(2) + 'deg) scale(' + (p.escala || 1).toFixed(4) + ')';
    }
    function tHoja(z, giro) { return 'translateZ(' + z.toFixed(2) + 'px) rotateY(' + giro + 'deg)'; }
    function tMorph(desde, hasta) {
        return 'translate(' + (desde.x - hasta.x).toFixed(1) + 'px,' + (desde.y - hasta.y).toFixed(1) + 'px)' +
            ' scale(' + (desde.w / hasta.w).toFixed(4) + ',' + (desde.h / hasta.h).toFixed(4) + ')';
    }

    // Una animación con varias paradas. puntos = [[ms, {propiedades}, curva para llegar ahí], ...]
    // Todas duran "total" y empiezan juntas, así un clic las adelanta todas a la vez.
    function linea(el, puntos, total) {
        var frames = [];
        for (var i = 0; i < puntos.length; i++) {
            var f = copiar(puntos[i][1]);
            f.offset = clamp(puntos[i][0] / total, 0, 1);
            if (i > 0 && puntos[i][2]) frames[i - 1].easing = puntos[i][2];
            frames.push(f);
        }
        if (frames[0].offset > 0) { var ini = copiar(puntos[0][1]); ini.offset = 0; frames.unshift(ini); }
        if (frames[frames.length - 1].offset < 1) { var fin = copiar(puntos[puntos.length - 1][1]); fin.offset = 1; frames.push(fin); }
        var a = el.animate(frames, { duration: total, fill: 'both' });
        lector.anims.push(a);
        return a;
    }

    // Una parte (k = 0 tapa, 1.. hojas) pasa de derecha a izquierda entre t0 y t1 (o al revés al cerrar)
    function pasar(p, g, k, t0, t1, total, cierra) {
        var der = { transform: tHoja(g.zDer(k), 0) }, izq = { transform: tHoja(g.zIzq(k), -180) };
        linea(p.pasan[k], [[t0, cierra ? izq : der], [t1, cierra ? der : izq, CURVA_HOJA]], total);
        // La cara que se ve se oscurece al ponerse de canto y se aclara al quedar plana
        var mitad = (t0 + t1) / 2, luz = p.luces[k];
        var antes = cierra ? luz.dorso : luz.frente, despues = cierra ? luz.frente : luz.dorso;
        if (antes) linea(antes, [[t0, { opacity: 0 }], [mitad, { opacity: 0.6 }, 'ease-in']], total);
        if (despues) linea(despues, [[mitad, { opacity: 0.6 }], [t1, { opacity: 0 }, 'ease-out']], total);
    }

    // Pose real de .biblio-libro-3d, también a mitad del giro del :hover (dura 0,9 s y
    // se suele hacer clic antes de que termine). Descompone su transformación calculada,
    // translate3d(0, sube, acerca) rotateX(rx) rotateY(ry) scale(escala):
    //   eje Y → escala · (0, cos rx, sen rx)            (rotateY no lo toca)
    //   eje X → escala · (cos ry, sen rx·sen ry, −cos rx·sen ry)
    function poseReal(el) {
        var t = getComputedStyle(el).transform;
        if (!t || t === 'none') return null;
        var m = new DOMMatrixReadOnly(t);
        var escala = Math.sqrt(m.m21 * m.m21 + m.m22 * m.m22 + m.m23 * m.m23);
        if (!(escala > 0.01)) return null;
        var rx = Math.atan2(m.m23, m.m22);
        var ry = Math.atan2(-m.m13 / Math.cos(rx), m.m11);
        return { sube: m.m42, acerca: m.m43, rx: rx * 180 / Math.PI, ry: ry * 180 / Math.PI, escala: escala };
    }
    // Dónde está y cómo está girado el libro del estante (el que vuela sale de esa misma pose)
    function poseEstante(libro) {
        var r = libro.getBoundingClientRect();
        var p = { x: r.left, y: r.top, w: r.width, h: r.height, tx: 0, sube: 0, acerca: 0,
                  rx: POSE_RX, ry: numVar(libro, '--giro', 24), escala: 1 };
        try {
            var t = getComputedStyle(libro.querySelector('.biblio-libro-flota')).transform;
            if (t && t !== 'none') p.sube = new DOMMatrixReadOnly(t).m42;          // lo que flota ahora
            var real = poseReal(libro.querySelector('.biblio-libro-3d'));
            if (real) { p.sube += real.sube; p.acerca = real.acerca; p.rx = real.rx; p.ry = real.ry; p.escala = real.escala; }
        } catch (e) { /* navegador sin DOMMatrix: la pose de reposo */ }
        return p;
    }

    // Tamaño del libro grande y dónde queda cada cosa (en px de la ventana)
    function medidas(ancho, alto) {
        var vw = window.innerWidth, vh = window.innerHeight, prop = alto / ancho;
        var P = Math.max(110, Math.min(vw * (vw < 700 ? 0.42 : 0.3), (vh - 110) / prop, 340));
        var g = { P: P, PH: P * prop, D: clamp(Math.round(P * 0.085), 12, 28), cx: vw / 2, cy: vh / 2 };
        g.lx = g.cx - P / 2;
        g.ly = g.cy - g.PH / 2;
        g.Pp = PERSPECTIVA * P / ancho;          // así el libro grande se ve igual que el del estante
        // Página derecha con el libro abierto: está al fondo del libro y la perspectiva la achica un poco
        var f = g.Pp / (g.Pp + g.D / 2 - 1);
        g.pagina = { x: g.cx, y: g.cy - (g.PH / 2 - 3) * f, w: (P - 4) * f, h: (g.PH - 6) * f };
        // Profundidad: a la derecha la tapa queda encima; al pasar a la izquierda queda debajo de todo
        g.zDer = function (k) { return k === 0 ? g.D / 2 : g.D / 2 - 1 - (k - 1) * 1.2; };
        g.zIzq = function (k) { return -g.D / 2 + 1.5 + k * 1.2; };
        return g;
    }

    function htmlCapa(n, icono) {
        var fecha = fechaTexto(n);
        var foto = n.i && n.i !== PLACEHOLDER ? '<img alt="" draggable="false" src="' + esc(n.i) + '">' : '';
        var luz = '<i class="biblio-lector-luz"></i>';
        var cat = n.k ? '<span class="biblio-lector-cat">' + esc(n.k) + '</span>' : '';
        var hojas = '';
        for (var j = 0; j < HOJAS; j++) {
            var dorso = j === HOJAS - 1
                // La última hoja queda arriba a la izquierda: por detrás lleva la foto, como una lámina
                ? '<div class="biblio-lector-cara biblio-lector-dorso biblio-lector-lamina">' +
                      '<span class="biblio-lector-lamina-img"><i class="fas ' + esc(icono) + '"></i>' + foto + '</span>' +
                      '<span class="biblio-lector-pie">' + esc(n.k || 'Noticias PLEC') + '</span>' + luz + '</div>'
                : '<div class="biblio-lector-cara biblio-lector-dorso biblio-lector-lineas">' + luz + '</div>';
            hojas += '<div class="biblio-lector-hoja">' +
                '<div class="biblio-lector-cara biblio-lector-frente biblio-lector-lineas">' + luz + '</div>' + dorso +
                '</div>';
        }
        return '<div class="biblio-lector-fondo"></div>' +
            '<div class="biblio-lector-vuelo"><div class="biblio-lector-libro">' +
                '<div class="biblio-lector-contra"></div>' +
                '<div class="biblio-lector-lomo"><span>' + esc(n.k || 'Noticias PLEC') + '</span></div>' +
                '<div class="biblio-lector-cantos"></div>' +
                '<div class="biblio-lector-cabeza"></div>' +
                '<div class="biblio-lector-pagina"><div class="biblio-lector-previa">' + cat +
                    '<span class="biblio-lector-titulo">' + esc(n.t) + '</span>' +
                    '<span class="biblio-lector-raya"></span>' +
                    (n.r ? '<span class="biblio-lector-resumen">' + esc(n.r) + '</span>' : '') +
                    (fecha ? '<span class="biblio-lector-fecha">' + esc(fecha) + '</span>' : '') +
                '</div></div>' +
                hojas +
                '<div class="biblio-lector-tapa">' +
                    '<div class="biblio-lector-cara biblio-lector-frente biblio-lector-portada">' +
                        '<i class="fas ' + esc(icono) + ' biblio-lector-icono"></i>' + foto + cat +
                        '<span class="biblio-lector-info"><span class="biblio-lector-titulo">' + esc(n.t) + '</span></span>' + luz +
                    '</div>' +
                    '<div class="biblio-lector-cara biblio-lector-dorso biblio-lector-guarda">' +
                        '<span class="biblio-lector-exlibris"><b>Noticias PLEC</b>' + (n.k ? '<small>' + esc(n.k) + '</small>' : '') + '</span>' + luz +
                    '</div>' +
                '</div>' +
            '</div></div>' +
            '<div class="biblio-lector-morph"></div>';
    }

    function crearCapa(n, g) {
        var color = colorCat[n.ci] || '#8a8a99', icono = iconoCat[n.ci] || 'fa-newspaper';
        var capa = doc.createElement('div');
        capa.className = 'biblio-lector';
        capa.setAttribute('aria-hidden', 'true');
        capa.style.cssText = '--lw:' + g.P.toFixed(1) + 'px;--lh:' + g.PH.toFixed(1) + 'px;--lg:' + g.D + 'px;' +
            '--lx:' + g.lx.toFixed(1) + 'px;--ly:' + g.ly.toFixed(1) + 'px;--lp:' + g.Pp.toFixed(0) + 'px;' +
            '--acento:' + color + ';--acento-rgb:' + rgbDe(color);
        capa.innerHTML = htmlCapa(n, icono);
        // Si la foto no carga, queda el ícono de la sección
        Array.prototype.forEach.call(capa.querySelectorAll('img'), function (img) {
            img.addEventListener('error', function () { if (img.parentNode) img.parentNode.removeChild(img); });
        });
        capa.addEventListener('click', adelantar);
        var q = function (s) { return capa.querySelector(s); };
        var p = {
            capa: capa, fondo: q('.biblio-lector-fondo'), vuelo: q('.biblio-lector-vuelo'), libro: q('.biblio-lector-libro'),
            previa: q('.biblio-lector-previa'), morph: q('.biblio-lector-morph'),
            pasan: [q('.biblio-lector-tapa')].concat(Array.prototype.slice.call(capa.querySelectorAll('.biblio-lector-hoja')))
        };
        p.luces = p.pasan.map(function (el) {
            return { frente: el.querySelector('.biblio-lector-frente .biblio-lector-luz'), dorso: el.querySelector('.biblio-lector-dorso .biblio-lector-luz') };
        });
        doc.body.appendChild(capa);
        return p;
    }
    function posicionar(el, r) {
        el.style.left = r.x.toFixed(1) + 'px';
        el.style.top = r.y.toFixed(1) + 'px';
        el.style.width = r.w.toFixed(1) + 'px';
        el.style.height = r.h.toFixed(1) + 'px';
    }

    // Cuando terminan todas las animaciones (o se adelantan con un clic o una tecla)
    function esperar(L, fin) {
        L.fin = fin;
        Promise.all(L.anims.map(function (a) { return a.finished; })).then(function () {
            if (lector === L && L.fin) { var f = L.fin; L.fin = null; f(); }
        }, function () { /* se canceló: quien la canceló ya limpió */ });
    }
    function adelantar() {
        var L = lector;
        if (!L || !L.fin) return;
        emitir('biblio:animacion', { fase: 'adelantar', id: L.id });
        L.anims.forEach(function (a) { try { a.finish(); } catch (e) { /* nada */ } });
        if (lector === L && L.fin) { var f = L.fin; L.fin = null; f(); }
    }
    function quitarCapa(L) {
        if (!L) return;
        L.fin = null;
        L.anims.forEach(function (a) { try { a.cancel(); } catch (e) { /* nada */ } });
        L.anims = [];
        if (L.partes && L.partes.capa.parentNode) L.partes.capa.parentNode.removeChild(L.partes.capa);
        L.partes = null;
    }
    function soltarModal() {
        if (!modalEl) return;
        modalEl.classList.remove('biblio-modo-libro');
        modalEl.style.removeProperty('--acento');
    }
    // El libro vuelve a verse en el estante y la noticia queda cerrada
    function soltarLibro(L) {
        if (L.libro) L.libro.classList.remove('is-fuera');
        if (lector === L) lector = null;
        emitir('biblio:cerrar', { id: L.id });
    }
    function terminarLector() {
        var L = lector;
        if (!L) return;
        quitarCapa(L);
        soltarModal();
        soltarLibro(L);
    }
    function enfocarModal() {
        var b = modalEl && modalEl.querySelector('.plec-modal-cerrar');
        if (b) { try { b.focus({ preventScroll: true }); } catch (e) { /* nada */ } }
    }
    function devolverFoco() {
        if (ultimoLibro && doc.contains(ultimoLibro)) {
            focoCallado = true;
            try { ultimoLibro.focus({ preventScroll: true }); } catch (e) { /* nada */ }
            focoCallado = false;
        }
        ultimoLibro = null;
    }

    function animarApertura(L, sr) {
        var mc = modalEl.querySelector('.modal-content');
        var T = mc ? rectVisible(mc.getBoundingClientRect()) : null;   // dónde quedó la página de la noticia
        if (!T || T.w < 80 || T.h < 80) return;
        var g = medidas(sr.w, sr.h);
        var p = L.partes = crearCapa(L.n, g);
        L.estado = 'abriendo';
        L.libro.classList.add('is-fuera');

        // Tiempos (ms): vuela, se abre (tapa y hojas), pausa, la página crece y se revela la noticia
        var VUELA = 420, ABRE = 330, TAPA = 560, PASO = 65, HOJA = 480;
        var abierto = ABRE + HOJAS * PASO + HOJA;
        var crece = abierto + 80;
        var revela = crece + 260;
        var total = revela + 150;

        var s0 = sr.w / g.P;
        var desde = { tx: 0, sube: sr.sube / s0, acerca: sr.acerca / s0, rx: sr.rx, ry: sr.ry, escala: sr.escala };
        var centro = { tx: 0, sube: 0, acerca: 0, rx: 0, ry: 12 };
        var abiertoPose = { tx: g.P / 2, sube: 0, acerca: 0, rx: 0, ry: 0 };   // el lomo queda en el centro
        var S = tMorph(g.pagina, T);
        posicionar(p.morph, T);

        linea(p.fondo, [[0, { opacity: 0 }], [VUELA, { opacity: 1 }, 'ease-out'], [revela, { opacity: 1 }], [total, { opacity: 0 }]], total);
        linea(p.vuelo, [[0, { transform: tVuelo(sr.x + sr.w / 2 - g.cx, sr.y + sr.h / 2 - g.cy, s0) }],
                        [VUELA, { transform: tVuelo(0, 0, 1) }, CURVA_VUELO]], total);
        linea(p.vuelo, [[crece, { opacity: 1 }], [crece + 200, { opacity: 0 }, 'ease-in']], total);
        linea(p.libro, [[0, { transform: tLibro(desde) }],
                        [VUELA, { transform: tLibro(centro) }, CURVA_VUELO],
                        [ABRE + TAPA, { transform: tLibro(abiertoPose) }, CURVA_SUAVE]], total);
        pasar(p, g, 0, ABRE, ABRE + TAPA, total, false);
        for (var k = 1; k <= HOJAS; k++) pasar(p, g, k, ABRE + k * PASO, ABRE + k * PASO + HOJA, total, false);
        linea(p.previa, [[crece, { opacity: 1 }], [crece + 120, { opacity: 0 }]], total);
        linea(p.morph, [[crece, { opacity: 0, transform: S }], [crece, { opacity: 1, transform: S }],
                        [revela, { opacity: 1, transform: SIN_CAMBIO }, CURVA_SUAVE],
                        [total, { opacity: 0, transform: SIN_CAMBIO }]], total);
        // La ventana real queda invisible hasta que la hoja llega a su sitio; luego la hoja se desvanece encima
        linea(modalEl, [[revela, { opacity: 0 }], [revela, { opacity: 1 }]], total);

        var hojas = [];
        for (var h = 1; h <= HOJAS; h++) hojas.push([ABRE + h * PASO, ABRE + h * PASO + HOJA]);
        emitir('biblio:animacion', { fase: 'abrir', id: L.id, marcas: {
            vuelo: [0, VUELA], tapa: [ABRE, ABRE + TAPA], hojas: hojas, revela: revela, total: total
        } });

        esperar(L, function () {
            quitarCapa(L);
            L.estado = 'abierto';
            enfocarModal();
        });
    }

    // Devuelve true si se encargó de cerrar (con animación)
    function animarCierre(L, original, ctx, args) {
        var libro = L.libro;
        var mc = modalEl.querySelector('.modal-content');
        if (!mc || !libro || !doc.contains(libro)) return false;
        var T = rectVisible(mc.getBoundingClientRect());
        if (T.w < 80 || T.h < 80) return false;
        var g = medidas(libro.offsetWidth, libro.offsetHeight);
        var p = L.partes = crearCapa(L.n, g);
        L.estado = 'cerrando';
        posicionar(p.morph, T);
        // La ventana real se cierra ya (detiene voz y videos, devuelve el scroll); la capa la tapa
        original.apply(ctx, args);
        soltarModal();
        var sr = poseEstante(libro);             // medido con el scroll ya devuelto
        var vuelve = enPantalla(sr);

        var ENCOGE = 240, PASA = 260, PASO = 45, HOJA = 380, TAPA = 430, VUELA = 400;
        var cerrado = PASA + HOJAS * PASO + TAPA;
        var total = cerrado + VUELA;
        var s0 = sr.w / g.P;
        var abiertoPose = { tx: g.P / 2, sube: 0, acerca: 0, rx: 0, ry: 0 };
        var centro = { tx: 0, sube: 0, acerca: 0, rx: 0, ry: 12 };
        var hasta = vuelve ? { tx: 0, sube: sr.sube / s0, acerca: sr.acerca / s0, rx: sr.rx, ry: sr.ry, escala: sr.escala } : centro;
        var S = tMorph(g.pagina, T);

        linea(p.morph, [[0, { opacity: 1, transform: SIN_CAMBIO }], [ENCOGE, { opacity: 1, transform: S }, CURVA_SUAVE],
                        [ENCOGE + 90, { opacity: 0, transform: S }]], total);
        linea(p.previa, [[ENCOGE - 40, { opacity: 0 }], [ENCOGE + 80, { opacity: 1 }]], total);
        linea(p.fondo, [[0, { opacity: 1 }], [cerrado, { opacity: 1 }], [total, { opacity: 0 }, 'ease-in']], total);
        linea(p.vuelo, [[60, { opacity: 0 }], [220, { opacity: 1 }], [cerrado, { opacity: 1 }], [total, { opacity: vuelve ? 1 : 0 }]], total);
        // Si el libro ya no se ve en el estante, en vez de volar se aleja y se desvanece
        linea(p.vuelo, [[cerrado, { transform: tVuelo(0, 0, 1) }],
                        [total, { transform: vuelve ? tVuelo(sr.x + sr.w / 2 - g.cx, sr.y + sr.h / 2 - g.cy, s0) : tVuelo(0, 60, 0.7) }, CURVA_SUAVE]], total);
        linea(p.libro, [[PASA, { transform: tLibro(abiertoPose) }],
                        [cerrado, { transform: tLibro(centro) }, CURVA_SUAVE],
                        [total, { transform: tLibro(hasta) }, CURVA_SUAVE]], total);
        var hojas = [];
        for (var k = HOJAS; k >= 1; k--) {
            var t0 = PASA + (HOJAS - k) * PASO;
            pasar(p, g, k, t0, t0 + HOJA, total, true);
            hojas.push([t0, t0 + HOJA]);
        }
        pasar(p, g, 0, PASA + HOJAS * PASO, cerrado, total, true);

        emitir('biblio:animacion', { fase: 'cerrar', id: L.id, marcas: {
            encoge: [0, ENCOGE], hojas: hojas, tapa: [PASA + HOJAS * PASO, cerrado], vuelo: [cerrado, total],
            vuelve: vuelve, total: total
        } });

        esperar(L, function () {
            quitarCapa(L);
            soltarLibro(L);
            devolverFoco();
        });
        return true;
    }

    function abrirNoticia(id, libro) {
        var n = DATOS.noticias[id];
        // Las noticias del índice ampliado del búho no traen el contenido: se abren con su enlace
        if (!n || n.c == null || typeof window.abrirModal !== 'function') { location.href = 'index.php?id=' + id; return; }
        adelantar();                 // si quedaba una animación a medias, termina ya
        terminarLector();
        ultimoLibro = libro || null;
        var estLibro = libro ? estanteDe(libro) : null;
        emitir('biblio:abrir', { id: id, noticia: n, seccion: estLibro ? estLibro.indice : -1 });
        if (!envuelto || !modalEl) { window.abrirModal(n.t, n.i, n.c, n.v, n.id); return; }

        var L = lector = { estado: 'abierto', id: id, n: n, libro: libro || null, partes: null, anims: [], fin: null };
        modalEl.classList.add('biblio-modo-libro');
        modalEl.style.setProperty('--acento', colorCat[n.ci] || '#8a8a99');
        var sr = libro && puedeAnimar() ? poseEstante(libro) : null;   // se mide antes de que se quite el scroll
        if (sr && !enPantalla(sr)) sr = null;
        try {
            window.abrirModal(n.t, n.i, n.c, n.v, n.id);
        } catch (err) {
            terminarLector();
            throw err;
        }
        if (sr) {
            try { animarApertura(L, sr); } catch (err) {
                // Si la animación falla, la noticia queda abierta como siempre
                quitarCapa(L);
                if (L.libro) L.libro.classList.remove('is-fuera');
                L.estado = 'abierto';
                avisoLibro(err);
            }
        }
        if (L.estado === 'abierto') enfocarModal();
    }

    // Reemplaza a cerrarModal (la del botón ×, el clic fuera y la tecla Escape)
    function alCerrarNoticia(original, ctx, args) {
        var L = lector;
        if (!L) { var r0 = original.apply(ctx, args); devolverFoco(); return r0; }
        if (L.estado === 'cerrando') { adelantar(); return; }
        if (L.estado === 'abierto' && puedeAnimar()) {
            try {
                if (animarCierre(L, original, ctx, args)) return;
            } catch (err) {
                avisoLibro(err);
                var yaCerrada = L.estado === 'cerrando';
                quitarCapa(L);
                if (yaCerrada) { soltarModal(); soltarLibro(L); devolverFoco(); return; }
            }
        }
        quitarCapa(L);               // cerrar a mitad de la apertura: sin más animación
        var r = original.apply(ctx, args);
        soltarModal();
        soltarLibro(L);
        devolverFoco();
        return r;
    }

    // Durante la animación, una tecla la adelanta (Escape al abrir la deja pasar: cierra la noticia)
    function alTeclaLector(e) {
        var L = lector;
        if (!L || !L.fin || e.ctrlKey || e.metaKey || e.altKey) return;
        if (e.key === 'Escape' && L.estado === 'abriendo') return;
        adelantar();
        e.preventDefault();
        e.stopPropagation();
    }

    /* ── TECLADO ───────────────────────────────────────────────────── */
    function hayVentanaAbierta() {
        if (doc.body.style.overflow === 'hidden') return true;       // bloquearScroll() de las ventanas
        var sb = doc.getElementById('sidebarMenu');
        return !!(sb && sb.style.width && parseFloat(sb.style.width) > 0);
    }
    function esCampo(t) {
        return t && (t.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName));
    }
    function enfocarLibro(est, li, cambiaSeccion) {
        if (!est || !est.libros.length) return false;
        li = clamp(li, 0, est.libros.length - 1);
        est.marcarActual(li);
        try { est.libros[li].focus({ preventScroll: true }); } catch (e) { est.libros[li].focus(); }
        est.revelar(li);
        if (cambiaSeccion) {
            // Al pasar a otra sección, la página se mueve lo justo para que se vea
            try { est.panel.scrollIntoView({ block: 'nearest', behavior: reduce ? 'auto' : 'smooth' }); }
            catch (e) { est.panel.scrollIntoView(false); }
        }
        despertar();
        return true;
    }
    // Libro de "est" cuyo centro queda más cerca (en horizontal) del libro dado
    function libroMasCercano(est, libro) {
        var r = libro.getBoundingClientRect(), cx = r.left + r.width / 2, mejor = 0, dist = Infinity;
        est.libros.forEach(function (l, i) {
            var q = l.getBoundingClientRect(), d = Math.abs(q.left + q.width / 2 - cx);
            if (d < dist) { dist = d; mejor = i; }
        });
        return mejor;
    }

    function alTeclado(e) {
        if (e.defaultPrevented || e.altKey || e.ctrlKey || e.metaKey) return;
        var k = e.key;
        if (k !== 'ArrowLeft' && k !== 'ArrowRight' && k !== 'ArrowUp' && k !== 'ArrowDown' && k !== 'Home' && k !== 'End') return;
        var t = e.target;
        if (esCampo(t) || hayVentanaAbierta()) return;
        // Solo con el foco en un libro; en el resto de la página las flechas hacen lo de siempre
        var libro = t.closest && t.closest('.biblio-libro');
        if (!libro || !raiz.contains(libro)) return;
        var est = estanteDe(libro), i = est ? est.libros.indexOf(libro) : -1;
        if (i < 0) return;
        if (k === 'ArrowLeft') { if (i > 0) enfocarLibro(est, i - 1); }
        else if (k === 'ArrowRight') { if (i < est.libros.length - 1) enfocarLibro(est, i + 1); }
        else if (k === 'Home') enfocarLibro(est, 0);
        else if (k === 'End') enfocarLibro(est, est.libros.length - 1);
        else {
            var orden = todosEstantes();
            var otro = orden[orden.indexOf(est) + (k === 'ArrowDown' ? 1 : -1)];
            // ↑ en las respuestas del búho vuelve a la barra donde se preguntó
            var campo = !otro && k === 'ArrowUp' && resultado && est === resultado.estante ? doc.querySelector('#preguntar input') : null;
            if (campo) {
                enfocarSinMover(campo);
                try { campo.scrollIntoView({ block: 'nearest', behavior: reduce ? 'auto' : 'smooth' }); } catch (err) { /* nada */ }
                e.preventDefault();
                return;
            }
            if (!otro) return;                    // primera o última sección: la página se mueve como siempre
            enfocarLibro(otro, libroMasCercano(otro, libro), true);
        }
        e.preventDefault();
    }

    /* ── TAMAÑO DE PANTALLA ────────────────────────────────────────── */
    var rzRaf = 0;
    function alRedimensionar() {
        cancelAnimationFrame(rzRaf);
        rzRaf = requestAnimationFrame(function () {
            todosEstantes().forEach(function (est) { est.medir(); });
            despertar();
        });
    }

    /* ── RESPUESTAS DEL BÚHO (Etapa 5) ─────────────────────────────── */
    // La rueda y el desplazamiento propio del navegador, en cada estante
    function conectarEstante(est) {
        // La rueda se escucha solo sobre cada estante (no frena el desplazamiento del resto de la página)
        est.vista.addEventListener('wheel', alRueda, { passive: false });
        // Si el navegador desplaza un estante por su cuenta (al enfocar con Tab), lo convertimos a nuestra posición
        est.vista.addEventListener('scroll', function () {
            var s = est.vista.scrollLeft;
            if (!s) return;
            est.vista.scrollLeft = 0;
            est.irA(est.x - s, true);
        });
    }
    // Pone (o cambia) el estante de respuestas encima de las secciones. Las noticias que
    // no estaban en la biblioteca (índice ampliado) se agregan a DATOS sin contenido.
    function mostrarResultados(lista, op) {
        op = op || {};
        quitarResultados();
        var libros = [];
        (lista || []).forEach(function (n) {
            if (!n || n.id == null) return;
            if (!DATOS.noticias[n.id]) DATOS.noticias[n.id] = n;
            libros.push(DATOS.noticias[n.id]);
        });
        var cont = doc.createElement('div');
        cont.innerHTML = htmlResultados(libros, op);
        var panel = cont.firstChild;
        var secs = montaje.querySelector('.biblio-secciones');
        secs.insertBefore(panel, secs.firstChild);
        var est = libros.length ? new Estante(panel, -1) : null;
        resultado = { panel: panel, estante: est, alCerrar: op.alCerrar };
        if (est) { est.medir(); conectarEstante(est); }
        if (obsCerca) obsCerca.observe(panel);
        else if (est) { est.cerca = true; panel.classList.add('is-visible'); est.cargarVisibles(true); }
        return panel;
    }
    function quitarResultados() {
        if (!resultado) return false;
        var r = resultado;
        resultado = null;
        if (obsCerca) obsCerca.unobserve(r.panel);
        if (gesto && gesto.estante === r.estante) { gesto = null; raiz.classList.remove('is-arrastrando'); }
        if (r.panel.parentNode) r.panel.parentNode.removeChild(r.panel);
        return true;
    }

    /* ── ARRANQUE ──────────────────────────────────────────────────── */
    function iniciar() {
        montaje = construir();

        // Visible antes de medir (con display:none todo mide 0)
        html.classList.add('biblio-activa');
        html.classList.remove('biblio-pendiente');
        var carga = raiz.querySelector('.biblio-carga');
        if (carga) carga.parentNode.removeChild(carga);
        raiz.removeAttribute('aria-busy');          // ya está armada (los lectores de pantalla la leen)

        panelesEl = Array.prototype.slice.call(raiz.querySelectorAll('.biblio-panel'));
        estantes = panelesEl.map(function (el, i) { return new Estante(el, i); });
        estantes.forEach(function (est) { est.medir(); });
        observarSecciones();

        // Enlace directo a una sección (#seccion-politica): las secciones las crea el JS,
        // así que al cargar el navegador no pudo saltar solo
        var m = /^#seccion-([a-z]+)$/.exec(location.hash || '');
        if (m && indiceDeClave(m[1]) >= 0) irASeccion(indiceDeClave(m[1]), false);

        // Eventos
        raiz.addEventListener('pointerdown', alPresionar);
        window.addEventListener('pointermove', alMover, { passive: false });
        window.addEventListener('pointerup', function (e) { terminar(e, false); });
        window.addEventListener('pointercancel', function (e) { terminar(e, true); });
        // Solo cuenta cuando el ESTANTE pierde la captura: en pantallas táctiles el libro tocado
        // tiene la captura al principio y, al pasarla al estante, el libro emite este mismo evento.
        raiz.addEventListener('lostpointercapture', function (e) {
            if (gesto && e.target === gesto.vista && gesto.estado !== 'espera') terminar(e, false);
        });
        raiz.addEventListener('click', alClic, true);
        raiz.addEventListener('dragstart', function (e) { e.preventDefault(); });
        doc.addEventListener('keydown', alTeclado);

        estantes.forEach(conectarEstante);
        raiz.addEventListener('focusin', function (e) {
            var libro = e.target.closest && e.target.closest('.biblio-libro');
            if (!libro) return;
            var est = estanteDe(libro), i = est ? est.libros.indexOf(libro) : -1;
            if (i >= 0) { est.marcarActual(i); est.revelar(i); despertar(); }
            // Con teclado, enfocar un libro equivale a pasar sobre él (con el mouse ya avisó el puntero)
            if (!focoCallado && coincide(libro, ':focus-visible')) avisarSobre(libro, 'teclado');
        });

        window.addEventListener('resize', alRedimensionar);
        if ('ResizeObserver' in window) {
            var anchoPrevio = 0;      // solo importa el ancho (el alto cambia al cargar cosas)
            new ResizeObserver(function (ents) {
                var w = Math.round(ents[0].contentRect.width);
                if (w !== anchoPrevio) { anchoPrevio = w; alRedimensionar(); }
            }).observe(raiz);
        }
        if (doc.fonts && doc.fonts.ready) doc.fonts.ready.then(alRedimensionar);
        window.addEventListener('hashchange', function () {
            var mm = /^#seccion-([a-z]+)$/.exec(location.hash || '');
            var i = mm ? indiceDeClave(mm[1]) : -1;
            if (i >= 0) irASeccion(i, true);
        });
        var alCambiarMovimiento = function () { reduce = mqReduce.matches; };
        if (mqReduce.addEventListener) mqReduce.addEventListener('change', alCambiarMovimiento);
        else if (mqReduce.addListener) mqReduce.addListener(alCambiarMovimiento);

        // Libro bajo el puntero (biblio:sobre)
        raiz.addEventListener('pointermove', alPunteroSobreLibro);
        raiz.addEventListener('pointerleave', function () { sobre = null; });

        // Al cerrar la noticia: el libro se cierra y vuelve al estante, y el foco
        // vuelve al libro que la abrió (útil con teclado)
        if (typeof window.cerrarModal === 'function') {
            var cerrarOriginal = window.cerrarModal;
            window.cerrarModal = function () { return alCerrarNoticia(cerrarOriginal, this, arguments); };
            envuelto = true;
        }
        window.addEventListener('keydown', alTeclaLector, true);

        window.PlecBiblioteca = {
            datos: DATOS,
            irA: function (i) { return irASeccion(+i, true); },
            irAClave: function (clave) { var i = indiceDeClave(clave); return i >= 0 && irASeccion(i, true); },
            siguiente: function () { return irASeccion(Math.max(actual, 0) + 1, true); },
            anterior: function () { return irASeccion(Math.max(actual, 0) - 1, true); },
            actual: function () { return { indice: actual, panel: paneles[actual] || null }; },
            // Respuestas del búho (Etapa 5): un estante más, arriba de las secciones
            resultados: mostrarResultados,
            quitarResultados: quitarResultados,
            hayResultados: function () { return !!resultado; },
            // Lleva el foco al primer libro de las respuestas (↓ desde la barra del búho)
            enfocarResultados: function () {
                if (!resultado) return false;
                if (resultado.estante) return enfocarLibro(resultado.estante, 0, true);
                // Sin libros: la primera idea del búho (o, si no hay, "Cerrar")
                var b = resultado.panel.querySelector('.biblio-resultados-vacio button, .biblio-resultados-vacio a[href]') ||
                        resultado.panel.querySelector('button, a[href]');
                if (b) { enfocarSinMover(b); try { resultado.panel.scrollIntoView({ block: 'nearest' }); } catch (e) { /* nada */ } }
                return !!b;
            },
            // Abre la noticia; si su libro está a la vista, con la animación
            abrir: function (id) {
                id = +id;
                var libro = null, h = window.innerHeight;
                todosEstantes().forEach(function (est) {
                    est.libros.forEach(function (l) {
                        if (libro || +l.getAttribute('data-id') !== id) return;
                        var r = l.getBoundingClientRect();
                        if (r.bottom > 0 && r.top < h) libro = l;
                    });
                });
                abrirNoticia(id, libro);
            },
            // Para diagnosticar desde la consola del navegador
            estado: function () {
                return {
                    actual: actual, gesto: gesto && { estado: gesto.estado, seccion: gesto.estante.indice },
                    lector: lector && lector.estado,
                    resultados: resultado ? (resultado.estante ? resultado.estante.libros.length : 0) : null,
                    estantes: todosEstantes().map(function (e) { return { x: e.x, v: e.v, objetivo: e.objetivo, min: e.min, cabe: e.cabe, cerca: e.cerca }; })
                };
            }
        };
    }

    try {
        iniciar();
    } catch (err) {
        // Cualquier error: quitar lo construido y dejar la portada clásica intacta
        try { if (montaje && montaje.parentNode) montaje.parentNode.removeChild(montaje); } catch (e) { /* nada */ }
        usarPortadaClasica(err);
    }
})();
