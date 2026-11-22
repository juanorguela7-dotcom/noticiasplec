/* ════════════════════════════════════════════════════════════════════
   NOTICIAS PLEC — BIBLIOTECA INTERACTIVA (sin dependencias)
   · Cada sección es un PANEL horizontal: se arrastra, se desliza con un
     resorte suave y se queda alineado al soltar.
   · Dentro de cada panel, las noticias van en un ESTANTE con inercia.
   · Mouse (rueda y arrastre), teclado (flechas, Inicio, Fin) y gestos
     táctiles, todo con Pointer Events.
   · Las imágenes de los estantes se cargan solo cuando se van a ver.
   · Si algo falla, se quita y vuelve la portada clásica.

   Teclado:
     ← →   cambian de sección (o de libro cuando el foco está en un libro;
           al llegar al final del estante se pasa a la sección siguiente)
     ↓     de las pestañas al estante      ↑  del estante a las pestañas
     Inicio / Fin  primer / último libro (o sección, en las pestañas)

   Eventos para las siguientes etapas (se emiten en document):
     biblio:panel     { indice, clave, nombre, total, origen }
     biblio:deslizar  { tipo: 'panel' | 'estante' }
     biblio:abrir     { id, noticia }
   API: window.PlecBiblioteca
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
    var PAUSA_RUEDA = 180;   // ms sin eventos de rueda = gesto terminado

    var mqReduce = window.matchMedia('(prefers-reduced-motion: reduce)');
    var mqMovil = window.matchMedia('(max-width: 700px)');
    var reduce = mqReduce.matches;
    var esMovil = mqMovil.matches;

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
    function htmlLibro(n) {
        var color = colorCat[n.ci] || '#8a8a99';
        var icono = iconoCat[n.ci] || 'fa-newspaper';
        var fecha = fechaTexto(n);
        var etiqueta = n.t + (n.k ? ', ' + n.k : '') + (fecha ? ', ' + fecha : '');
        var img = n.i && n.i !== PLACEHOLDER
            ? '<img alt="" draggable="false" decoding="async" data-src="' + esc(n.i) + '">'
            : '';
        return '<li class="biblio-item">' +
            '<a class="biblio-libro" href="index.php?id=' + n.id + '" data-id="' + n.id + '" draggable="false" tabindex="-1"' +
            ' aria-label="' + esc(etiqueta) + '" style="--acento:' + color + ';--acento-rgb:' + rgbDe(color) + '">' +
                '<span class="biblio-portada"><i class="fas ' + esc(icono) + ' biblio-portada-icono" aria-hidden="true"></i>' + img + '</span>' +
                (n.k ? '<span class="biblio-cat" aria-hidden="true">' + esc(n.k) + '</span>' : '') +
                (n.h <= 48 ? '<span class="biblio-nuevo" aria-hidden="true"><i class="fas fa-bolt"></i> Nuevo</span>' : '') +
                '<span class="biblio-info" aria-hidden="true">' +
                    '<span class="biblio-titulo">' + esc(n.t) + '</span>' +
                    (fecha ? '<span class="biblio-fecha"><i class="fas fa-clock"></i>' + esc(fecha) + '</span>' : '') +
                '</span>' +
            '</a></li>';
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
        return '<section class="biblio-panel" role="tabpanel" id="biblio-panel-' + esc(p.clave) + '" aria-labelledby="biblio-tab-' + esc(p.clave) + '"' +
            ' style="--acento:' + p.color + ';--acento-rgb:' + rgbDe(p.color) + '">' +
            '<span class="biblio-panel-marca" aria-hidden="true">' + esc(p.nombre) + '</span>' +
            // <div> y no <header>: el sitio tiene reglas globales para header (centrado, fondo en modo claro)
            '<div class="biblio-panel-cab">' +
                '<span class="biblio-panel-icono" aria-hidden="true"><i class="fas ' + esc(p.icono) + '"></i></span>' +
                '<div class="biblio-panel-textos">' +
                    '<h2 class="biblio-panel-titulo">' + esc(p.nombre) + '</h2>' +
                    '<p class="biblio-panel-sub">' + esc(p.sub) + ' · ' + total + ' noticia' + (total !== 1 ? 's' : '') + '</p>' +
                '</div>' + accion +
            '</div>' +
            '<div class="biblio-estante">' +
                '<button type="button" class="biblio-flecha biblio-flecha-izq" data-dir="-1" tabindex="-1" aria-hidden="true"><i class="fas fa-chevron-left"></i></button>' +
                '<div class="biblio-estante-vista">' +
                    '<ul class="biblio-estante-fila" role="list" aria-label="Noticias de ' + esc(p.nombre) + '">' + libros.map(htmlLibro).join('') + '</ul>' +
                '</div>' +
                '<button type="button" class="biblio-flecha biblio-flecha-der" data-dir="1" tabindex="-1" aria-hidden="true"><i class="fas fa-chevron-right"></i></button>' +
                '<div class="biblio-repisa" aria-hidden="true"></div>' +
                '<div class="biblio-progreso" aria-hidden="true"><span></span></div>' +
            '</div>' +
        '</section>';
    }

    function construir() {
        var tabs = paneles.map(function (p) {
            return '<button type="button" class="biblio-pestana" role="tab" id="biblio-tab-' + esc(p.clave) + '"' +
                ' aria-controls="biblio-panel-' + esc(p.clave) + '" aria-selected="false" tabindex="-1"' +
                ' style="--acento:' + p.color + ';--acento-rgb:' + rgbDe(p.color) + '">' +
                '<i class="fas ' + esc(p.icono) + '" aria-hidden="true"></i><span>' + esc(p.nombre) + '</span></button>';
        }).join('');
        var puntos = paneles.map(function () { return '<span></span>'; }).join('');

        var cont = doc.createElement('div');
        cont.className = 'biblio-montaje';
        cont.innerHTML =
            '<div class="biblio-cabecera">' +
                '<div class="biblio-pestanas" role="tablist" aria-label="Secciones de la biblioteca">' + tabs + '</div>' +
                '<p class="biblio-ayuda">' +
                    '<span class="biblio-ayuda-raton"><kbd>←</kbd> <kbd>→</kbd> cambia de sección · arrastra o gira la rueda sobre los libros · <kbd>Enter</kbd> abre</span>' +
                    '<span class="biblio-ayuda-tactil">Desliza ↔ los libros y las secciones · toca un libro para leerlo</span>' +
                '</p>' +
            '</div>' +
            '<div class="biblio-escenario"><div class="biblio-pista">' + paneles.map(htmlPanel).join('') + '</div></div>' +
            '<div class="biblio-puntos" aria-hidden="true">' + puntos + '</div>' +
            '<p class="biblio-sr" aria-live="polite" aria-atomic="true"></p>';
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
        if (instantaneo || reduce) { this.x = this.objetivo; this.objetivo = null; this.pintar(); }
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
    // Carga diferida: solo los libros que están (o casi) a la vista y en paneles cercanos
    Estante.prototype.cargarVisibles = function (forzar) {
        if (!forzar && Math.abs(this.indice - objetivo) > 1) return;
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

    /* ── ESTADO DE LOS PANELES ─────────────────────────────────────── */
    var montaje, pestanasEl, pestanas, panelesEl, puntosEl, escenario, pista, vivo, estantes;
    var pos = 0, vel = 0, objetivo = -1, arrastrandoPanel = false, pos0 = 0, pasoPx = 1000;
    var sucio = true, raf = 0, ultimoT = 0, iniciado = false;
    var gesto = null, suprimirClick = false, ultimoLibro = null;

    function medirPaneles() {
        var hueco = parseFloat(getComputedStyle(raiz).getPropertyValue('--biblio-hueco')) || 30;
        pasoPx = (panelesEl[0].offsetWidth || 900) + hueco;
    }

    function pintarPaneles() {
        var giro = reduce ? 0 : (esMovil ? 9 : 24);
        for (var i = 0; i < N; i++) {
            var el = panelesEl[i];
            var d = i - pos, ad = Math.abs(d);
            if (ad > 2.6) {
                if (!el._oculto) { el.style.visibility = 'hidden'; el._oculto = true; }
                continue;
            }
            if (el._oculto) { el.style.visibility = ''; el._oculto = false; }
            var c = clamp(d, -1, 1);
            var z = reduce ? 0 : -Math.min(ad, 1.5) * 140;
            var s = reduce ? 1 : 1 - Math.min(ad, 1) * 0.06;
            var o = ad <= 1 ? 1 - ad * 0.5 : Math.max(0, 0.5 - (ad - 1) * 0.5);
            el.style.transform = 'translate3d(' + (d * pasoPx).toFixed(1) + 'px,0,' + z.toFixed(1) + 'px) rotateY(' + (-c * giro).toFixed(2) + 'deg) scale(' + s.toFixed(4) + ')';
            el.style.opacity = o.toFixed(3);
            el.style.zIndex = String(100 - Math.round(ad * 10));
        }
    }

    // Resorte casi crítico: llega rápido, sin rebotar de más, y conserva el impulso del gesto
    function pasoPaneles(dt) {
        if (arrastrandoPanel) return true;
        if (pos === objetivo && vel === 0) return false;
        if (reduce) { pos = objetivo; vel = 0; return true; }
        var K = 210, C = 2 * Math.sqrt(K) * 0.9;
        var t = Math.min(dt, 0.05), n = Math.ceil(t / 0.008), h = t / n;
        for (var i = 0; i < n; i++) {
            var a = -K * (pos - objetivo) - C * vel;
            vel += a * h;
            pos += vel * h;
        }
        if (Math.abs(pos - objetivo) < 0.0008 && Math.abs(vel) < 0.003) { pos = objetivo; vel = 0; }
        return true;
    }

    function despertar() {
        if (!raf) { ultimoT = performance.now(); raf = requestAnimationFrame(bucle); }
    }
    function bucle(t) {
        var dt = clamp(t - ultimoT, 1, 64);
        ultimoT = t;
        var k = dt / 16.67;
        var activo = false;
        if (pasoPaneles(dt / 1000) || sucio) { pintarPaneles(); sucio = false; activo = true; }
        for (var i = 0; i < estantes.length; i++) if (estantes[i].paso(k)) activo = true;
        if (gesto) activo = true;
        raf = activo ? requestAnimationFrame(bucle) : 0;
    }

    /* ── CAMBIO DE SECCIÓN ─────────────────────────────────────────── */
    function irA(i, opc) {
        opc = opc || {};
        i = clamp(Math.round(i), 0, N - 1);
        var cambio = i !== objetivo;
        objetivo = i;
        if (opc.instantaneo || reduce) { pos = i; vel = 0; }
        else if (typeof opc.vel === 'number') vel = opc.vel;
        sucio = true;
        despertar();
        if (cambio) alCambiar(opc.origen || 'codigo');
        return cambio;
    }

    function alCambiar(origen) {
        var p = paneles[objetivo];
        pestanas.forEach(function (t, j) {
            var sel = j === objetivo;
            t.setAttribute('aria-selected', sel ? 'true' : 'false');
            t.tabIndex = sel ? 0 : -1;
        });
        panelesEl.forEach(function (el, j) {
            var act = j === objetivo;
            el.classList.toggle('is-activo', act);
            if (act) el.removeAttribute('inert'); else el.setAttribute('inert', '');
        });
        puntosEl.forEach(function (s, j) { s.classList.toggle('is-activo', j === objetivo); });
        raiz.style.setProperty('--biblio-acento', p.color);
        raiz.style.setProperty('--biblio-acento-rgb', rgbDe(p.color));
        centrarPestana(objetivo);
        for (var j = objetivo - 1; j <= objetivo + 1; j++) if (estantes[j]) estantes[j].cargarVisibles(true);

        var total = p.total || p.ids.length;
        if (iniciado) {
            vivo.textContent = 'Sección ' + p.nombre + ': ' + total + ' noticia' + (total !== 1 ? 's' : '') + '.';
            try {
                var url = objetivo === 0 ? location.pathname + location.search : '#seccion-' + p.clave;
                history.replaceState(history.state, '', url);
            } catch (e) { /* file:// o navegador sin soporte */ }
        }
        emitir('biblio:panel', { indice: objetivo, clave: p.clave, nombre: p.nombre, total: total, origen: origen });
    }

    function centrarPestana(i) {
        var t = pestanas[i];
        if (!t || pestanasEl.scrollWidth <= pestanasEl.clientWidth + 1) return;
        var destino = t.offsetLeft - (pestanasEl.clientWidth - t.offsetWidth) / 2;
        try { pestanasEl.scrollTo({ left: destino, behavior: reduce || !iniciado ? 'auto' : 'smooth' }); }
        catch (e) { pestanasEl.scrollLeft = destino; }
    }

    /* ── ARRASTRE DE PANELES ───────────────────────────────────────── */
    function empezarPanel() { arrastrandoPanel = true; pos0 = pos; vel = 0; }
    function arrastrarPanel(dx) {
        var p = pos0 - dx / pasoPx, ultimo = N - 1;
        if (p < 0) p = -goma(-p * pasoPx, pasoPx) / pasoPx;
        else if (p > ultimo) p = ultimo + goma((p - ultimo) * pasoPx, pasoPx) / pasoPx;
        pos = p;
        sucio = true;
    }
    function soltarPanel(vpx) {
        arrastrandoPanel = false;
        var destino;
        if (Math.abs(vpx) > 0.3) destino = vpx < 0 ? Math.floor(pos + 1e-4) + 1 : Math.ceil(pos - 1e-4) - 1;
        else destino = Math.round(pos);
        var base = Math.round(pos0);
        destino = clamp(destino, base - 1, base + 1);     // un gesto = una sección como máximo
        irA(destino, { vel: clamp(-vpx * 1000 / pasoPx, -8, 8), origen: 'arrastre' });
    }

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
        var activoEl = panelesEl[objetivo];
        var enEstante = activoEl.contains(e.target) && e.target.closest('.biblio-estante-vista');
        gesto = {
            id: e.pointerId, x0: e.clientX, y0: e.clientY, estado: 'espera',
            estante: enEstante ? estantes[objetivo] : null,
            muestras: [[e.clientX, e.timeStamp]], atrapo: false, congelado: false
        };
        // Tocar algo que se está moviendo lo detiene (y ese toque no abre un libro)
        if (gesto.estante && gesto.estante.enMovimiento()) { gesto.estante.agarrar(); gesto.atrapo = true; }
        if (Math.abs(pos - objetivo) > 0.02) { empezarPanel(); gesto.congelado = true; gesto.atrapo = true; }
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
            var dir = dx < 0 ? 1 : -1;
            if (gesto.congelado) gesto.estado = 'panel';
            else if (gesto.estante && gesto.estante.puede(dir)) gesto.estado = 'estante';
            else gesto.estado = 'panel';
            if (gesto.estado === 'estante') gesto.estante.empezar();
            else if (!gesto.congelado) empezarPanel();
            try { escenario.setPointerCapture(e.pointerId); } catch (err) { /* nada */ }
            raiz.classList.add('is-arrastrando');
            emitir('biblio:deslizar', { tipo: gesto.estado });
        }
        if (gesto.estado === 'estante') gesto.estante.arrastrar(dx);
        else arrastrarPanel(dx);
        despertar();
        if (e.cancelable && e.pointerType === 'mouse') e.preventDefault();
    }

    function terminar(e, cancelado) {
        if (!gesto || (e && e.pointerId !== gesto.id)) return;
        var g = gesto;
        gesto = null;
        raiz.classList.remove('is-arrastrando');
        var v = cancelado ? 0 : velocidad(g.muestras, e ? e.timeStamp : performance.now());
        if (g.estado === 'estante') g.estante.soltar(v);
        else if (g.estado === 'panel' || g.congelado) soltarPanel(v);
        if (g.estado !== 'espera' || g.atrapo) {
            suprimirClick = true;
            setTimeout(function () { suprimirClick = false; }, 400);
        }
        despertar();
    }

    /* ── RUEDA DEL MOUSE / TRACKPAD ────────────────────────────────── */
    var rueda = { ultima: 0, acum: 0, bloqueada: false, estanteHasta: 0 };

    function ruedaPanel(d, ahora) {
        if (rueda.bloqueada) return;
        rueda.acum += d;
        if (Math.abs(rueda.acum) >= 40) {
            irA(objetivo + (rueda.acum > 0 ? 1 : -1), { origen: 'rueda' });
            rueda.acum = 0;
            rueda.bloqueada = true;              // una sección por gesto de rueda
        }
    }

    function alRueda(e) {
        if (e.ctrlKey) return;                    // zoom del navegador
        var ahora = performance.now();
        if (ahora - rueda.ultima > PAUSA_RUEDA) { rueda.bloqueada = false; rueda.acum = 0; }
        rueda.ultima = ahora;

        var d = ruedaPx(e);
        var horizontal = Math.abs(d.x) > Math.abs(d.y);
        var delta = horizontal ? d.x : d.y;
        if (!delta) return;

        if (e.currentTarget === pestanasEl) { e.preventDefault(); ruedaPanel(delta, ahora); return; }

        var activoEl = panelesEl[objetivo];
        var est = activoEl.contains(e.target) && e.target.closest('.biblio-estante-vista') ? estantes[objetivo] : null;
        if (est) {
            if (est.puede(delta > 0 ? 1 : -1)) {
                e.preventDefault();
                est.rueda(delta);
                rueda.estanteHasta = ahora + PAUSA_RUEDA;
                despertar();
                return;
            }
            // El estante llegó al final en medio del gesto: no saltar de golpe a la página
            if (ahora < rueda.estanteHasta) { e.preventDefault(); rueda.estanteHasta = ahora + PAUSA_RUEDA; return; }
            if (horizontal) { e.preventDefault(); ruedaPanel(delta, ahora); }
            return;                               // rueda vertical al final: la página baja como siempre
        }
        if (horizontal) { e.preventDefault(); ruedaPanel(delta, ahora); }
    }

    /* ── CLIC ──────────────────────────────────────────────────────── */
    function alClic(e) {
        if (suprimirClick) { e.preventDefault(); e.stopPropagation(); suprimirClick = false; return; }

        var tab = e.target.closest('.biblio-pestana');
        if (tab) { irA(pestanas.indexOf(tab), { origen: 'pestana' }); return; }

        // Clic en un panel vecino (está inerte, así que el clic llega al escenario)
        var activoEl = panelesEl[objetivo];
        if (escenario.contains(e.target) && !activoEl.contains(e.target)) {
            var r = activoEl.getBoundingClientRect();
            if (e.clientX < r.left) irA(objetivo - 1, { origen: 'clic' });
            else if (e.clientX > r.right) irA(objetivo + 1, { origen: 'clic' });
            return;
        }

        var flecha = e.target.closest('.biblio-flecha');
        if (flecha) { estantes[objetivo].porPagina(+flecha.getAttribute('data-dir')); despertar(); return; }

        if (e.target.closest('[data-accion="opinion"]')) {
            if (typeof window.abrirOpinion === 'function') window.abrirOpinion();
            return;
        }

        var libro = e.target.closest('.biblio-libro');
        if (libro) {
            if (e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0) return;   // abrir en otra pestaña
            e.preventDefault();
            abrirNoticia(+libro.getAttribute('data-id'), libro);
        }
    }

    function abrirNoticia(id, libro) {
        var n = DATOS.noticias[id];
        if (!n || typeof window.abrirModal !== 'function') { location.href = 'index.php?id=' + id; return; }
        ultimoLibro = libro || null;
        emitir('biblio:abrir', { id: id, noticia: n });
        window.abrirModal(n.t, n.i, n.c, n.v, n.id);
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
    function bibliotecaALaVista() {
        var r = raiz.getBoundingClientRect(), h = window.innerHeight;
        return r.bottom > h * 0.25 && r.top < h * 0.75;
    }
    function enfocarLibro(pi, li) {
        var est = estantes[pi];
        if (!est || !est.libros.length) return false;
        li = clamp(li, 0, est.libros.length - 1);
        est.marcarActual(li);
        try { est.libros[li].focus({ preventScroll: true }); } catch (e) { est.libros[li].focus(); }
        est.revelar(li);
        despertar();
        return true;
    }
    function enfocarPestana() {
        try { pestanas[objetivo].focus({ preventScroll: true }); } catch (e) { pestanas[objetivo].focus(); }
    }

    function alTeclado(e) {
        if (e.defaultPrevented || e.altKey || e.ctrlKey || e.metaKey) return;
        var k = e.key;
        if (k !== 'ArrowLeft' && k !== 'ArrowRight' && k !== 'ArrowUp' && k !== 'ArrowDown' && k !== 'Home' && k !== 'End') return;
        var t = e.target;
        if (esCampo(t) || hayVentanaAbierta()) return;
        var dir = k === 'ArrowRight' ? 1 : (k === 'ArrowLeft' ? -1 : 0);

        // 1) Foco en las pestañas: comportamiento estándar de pestañas
        if (t.closest && t.closest('.biblio-pestanas')) {
            if (dir) irA(objetivo + dir, { origen: 'teclado' });
            else if (k === 'Home') irA(0, { origen: 'teclado' });
            else if (k === 'End') irA(N - 1, { origen: 'teclado' });
            else if (k === 'ArrowDown') { enfocarLibro(objetivo, estantes[objetivo].actual); e.preventDefault(); return; }
            else return;
            enfocarPestana();
            e.preventDefault();
            return;
        }

        // 2) Foco en un libro: moverse por el estante y pasar de sección en los extremos
        var libro = t.closest && t.closest('.biblio-libro');
        if (libro && raiz.contains(libro)) {
            var est = estantes[objetivo], i = est.libros.indexOf(libro);
            if (k === 'ArrowUp') { enfocarPestana(); e.preventDefault(); return; }
            if (k === 'ArrowDown') return;
            if (k === 'Home') enfocarLibro(objetivo, 0);
            else if (k === 'End') enfocarLibro(objetivo, est.libros.length - 1);
            else if (i + dir >= 0 && i + dir < est.libros.length) enfocarLibro(objetivo, i + dir);
            else if (objetivo + dir >= 0 && objetivo + dir < N) {
                irA(objetivo + dir, { origen: 'teclado' });
                enfocarLibro(objetivo, dir > 0 ? 0 : estantes[objetivo].libros.length - 1);
            }
            e.preventDefault();
            return;
        }

        // 3) Sin foco en nada concreto (o en otro control de la biblioteca): ← → cambian de sección
        if (!dir) return;
        var dentro = raiz.contains(t);
        if (!dentro && !(t === doc.body || t === html)) return;
        if (!dentro && !bibliotecaALaVista()) return;
        irA(objetivo + dir, { origen: 'teclado' });
        if (dentro) enfocarPestana();
        e.preventDefault();
    }

    /* ── TAMAÑO DE PANTALLA ────────────────────────────────────────── */
    var rzRaf = 0;
    function alRedimensionar() {
        cancelAnimationFrame(rzRaf);
        rzRaf = requestAnimationFrame(function () {
            html.style.setProperty('--biblio-sbw', Math.max(0, window.innerWidth - html.clientWidth) + 'px');
            esMovil = mqMovil.matches;
            medirPaneles();
            estantes.forEach(function (est) { est.medir(); });
            if (objetivo >= 0) estantes[objetivo].revelar(estantes[objetivo].actual, true);
            sucio = true;
            despertar();
        });
    }

    /* ── ARRANQUE ──────────────────────────────────────────────────── */
    function iniciar() {
        montaje = construir();

        // Visible antes de medir (con display:none todo mide 0)
        html.classList.add('biblio-activa');
        html.classList.remove('biblio-pendiente');
        var carga = raiz.querySelector('.biblio-carga');
        if (carga) carga.parentNode.removeChild(carga);
        html.style.setProperty('--biblio-sbw', Math.max(0, window.innerWidth - html.clientWidth) + 'px');

        pestanasEl = raiz.querySelector('.biblio-pestanas');
        pestanas = Array.prototype.slice.call(raiz.querySelectorAll('.biblio-pestana'));
        panelesEl = Array.prototype.slice.call(raiz.querySelectorAll('.biblio-panel'));
        puntosEl = Array.prototype.slice.call(raiz.querySelectorAll('.biblio-puntos span'));
        escenario = raiz.querySelector('.biblio-escenario');
        pista = raiz.querySelector('.biblio-pista');
        vivo = raiz.querySelector('.biblio-sr');
        estantes = panelesEl.map(function (el, i) { return new Estante(el, i); });

        medirPaneles();
        estantes.forEach(function (est) { est.medir(); });

        // Sección inicial: la del enlace (#seccion-politica) o Inicio
        var inicial = 0, m = /^#seccion-([a-z]+)$/.exec(location.hash || '');
        if (m) paneles.forEach(function (p, i) { if (p.clave === m[1]) inicial = i; });
        irA(inicial, { instantaneo: true, origen: 'inicio' });
        pintarPaneles();
        iniciado = true;

        // Eventos
        escenario.addEventListener('pointerdown', alPresionar);
        window.addEventListener('pointermove', alMover, { passive: false });
        window.addEventListener('pointerup', function (e) { terminar(e, false); });
        window.addEventListener('pointercancel', function (e) { terminar(e, true); });
        // Solo cuenta cuando el ESCENARIO pierde la captura: en pantallas táctiles el libro tocado
        // tiene la captura al principio y, al pasarla al escenario, el libro emite este mismo evento.
        escenario.addEventListener('lostpointercapture', function (e) {
            if (e.target === escenario && gesto && gesto.estado !== 'espera') terminar(e, false);
        });
        escenario.addEventListener('wheel', alRueda, { passive: false });
        pestanasEl.addEventListener('wheel', alRueda, { passive: false });
        raiz.addEventListener('click', alClic, true);
        raiz.addEventListener('dragstart', function (e) { e.preventDefault(); });
        doc.addEventListener('keydown', alTeclado);

        // Si el navegador desplaza un estante por su cuenta (al enfocar con Tab), lo convertimos a nuestra posición
        estantes.forEach(function (est) {
            est.vista.addEventListener('scroll', function () {
                var s = est.vista.scrollLeft;
                if (!s) return;
                est.vista.scrollLeft = 0;
                est.irA(est.x - s, true);
            });
        });
        raiz.addEventListener('focusin', function (e) {
            var libro = e.target.closest && e.target.closest('.biblio-libro');
            if (!libro) return;
            var est = estantes[objetivo], i = est.libros.indexOf(libro);
            if (i >= 0) { est.marcarActual(i); est.revelar(i); despertar(); }
        });

        window.addEventListener('resize', alRedimensionar);
        if ('ResizeObserver' in window) new ResizeObserver(alRedimensionar).observe(escenario);
        if (doc.fonts && doc.fonts.ready) doc.fonts.ready.then(alRedimensionar);
        window.addEventListener('hashchange', function () {
            var mm = /^#seccion-([a-z]+)$/.exec(location.hash || '');
            if (!mm) return;
            paneles.forEach(function (p, i) { if (p.clave === mm[1]) irA(i, { origen: 'enlace' }); });
        });
        var alCambiarMovimiento = function () { reduce = mqReduce.matches; sucio = true; despertar(); };
        if (mqReduce.addEventListener) mqReduce.addEventListener('change', alCambiarMovimiento);
        else if (mqReduce.addListener) mqReduce.addListener(alCambiarMovimiento);

        // Al cerrar la noticia, el foco vuelve al libro que la abrió (útil con teclado)
        if (typeof window.cerrarModal === 'function') {
            var cerrarOriginal = window.cerrarModal;
            window.cerrarModal = function () {
                var r = cerrarOriginal.apply(this, arguments);
                if (ultimoLibro && doc.contains(ultimoLibro)) {
                    try { ultimoLibro.focus({ preventScroll: true }); } catch (e) { /* nada */ }
                }
                ultimoLibro = null;
                return r;
            };
        }

        window.PlecBiblioteca = {
            datos: DATOS,
            irA: function (i) { return irA(i, { origen: 'api' }); },
            irAClave: function (clave) {
                for (var i = 0; i < N; i++) if (paneles[i].clave === clave) return irA(i, { origen: 'api' });
                return false;
            },
            siguiente: function () { return irA(objetivo + 1, { origen: 'api' }); },
            anterior: function () { return irA(objetivo - 1, { origen: 'api' }); },
            actual: function () { return { indice: objetivo, panel: paneles[objetivo] }; },
            abrir: function (id) { abrirNoticia(+id, null); },
            // Para diagnosticar desde la consola del navegador
            estado: function () {
                return {
                    objetivo: objetivo, pos: pos, gesto: gesto && { estado: gesto.estado, estante: !!gesto.estante },
                    estantes: estantes.map(function (e) { return { x: e.x, v: e.v, objetivo: e.objetivo, min: e.min, cabe: e.cabe }; })
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
