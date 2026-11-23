/* ════════════════════════════════════════════════════════════════════
   NOTICIAS PLEC — MOTOR DE EFECTOS 3D Y MOVIMIENTO (sin dependencias)
   · Globo 3D de partículas + constelación en la cabecera (canvas 2D)
   · Inclinación 3D de tarjetas con reflejo de luz que sigue al mouse
   · Aparición de secciones en 3D al hacer scroll (IntersectionObserver)
   · Parallax de la cabecera, barra de progreso, navegación fija
   · Botones magnéticos, foco de luz ambiental, contadores animados
   Todo se desactiva con "reducir movimiento" y los efectos de mouse no
   se activan en pantallas táctiles.
   ════════════════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    var doc = document, root = doc.documentElement;
    var mqReduce = window.matchMedia('(prefers-reduced-motion: reduce)');
    var reduce = mqReduce.matches;
    var finePointer = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
    var isSmall = window.matchMedia('(max-width: 768px)').matches;

    function $(sel, ctx) { return (ctx || doc).querySelector(sel); }
    function $$(sel, ctx) { return Array.prototype.slice.call((ctx || doc).querySelectorAll(sel)); }
    function clamp(v, a, b) { return Math.max(a, Math.min(b, v)); }

    /* ── 1. CAPAS GLOBALES: progreso de lectura + luz ambiental ─────── */
    function initCapas() {
        if (!$('.plec-progress')) {
            var bar = doc.createElement('div');
            bar.className = 'plec-progress';
            bar.setAttribute('aria-hidden', 'true');
            doc.body.appendChild(bar);
        }
        if (!$('.plec-ambient')) {
            var amb = doc.createElement('div');
            amb.className = 'plec-ambient';
            amb.setAttribute('aria-hidden', 'true');
            amb.innerHTML = '<span></span><span></span><span></span>';
            doc.body.insertBefore(amb, doc.body.firstChild);
        }
    }

    /* ── 2. SCROLL: progreso, nav fija, parallax de la cabecera ─────── */
    function initScroll() {
        var nav = $('nav.plec-nav');
        var hero = $('header.plec-hero');
        var barra = $('.plec-progress');
        var ticking = false, kPrevio = -1;

        // Primero se lee todo y después se escribe: leer medidas después de cambiar
        // estilos obliga al navegador a recalcularlos en el acto (60 fps al bajar).
        function update() {
            ticking = false;
            var y = window.scrollY || window.pageYOffset;
            var max = Math.max(1, root.scrollHeight - window.innerHeight);
            var pegada = !!nav && nav.getBoundingClientRect().top <= 0 && y > 10;
            var k = hero && !reduce ? clamp(y / (hero.offsetHeight || 1), 0, 1) : -1;

            // La variable va en la barra y no en <html>: en <html> la heredan todos los
            // elementos de la página y había que recalcular sus estilos en cada cuadro
            if (barra) barra.style.setProperty('--plec-p', (y / max).toFixed(4));
            if (nav) nav.classList.toggle('is-stuck', pegada);

            // La cabecera solo se toca mientras cambia (ya fuera de la pantalla, no)
            if (k >= 0 && Math.abs(k - kPrevio) > 0.0005) {
                kPrevio = k;
                hero.style.setProperty('--plec-hero-y', (k * 60).toFixed(1) + 'px');
                hero.style.setProperty('--plec-hero-y2', (k * 30).toFixed(1) + 'px');
                hero.style.setProperty('--plec-hero-o', (1 - k * 0.9).toFixed(3));
            }
        }
        window.addEventListener('scroll', function () {
            if (!ticking) { ticking = true; requestAnimationFrame(update); }
        }, { passive: true });
        window.addEventListener('resize', update);
        update();
    }

    /* ── 3. APARICIÓN 3D AL HACER SCROLL ────────────────────────────── */
    var REVEAL = [
        ['.principal-laterales:first-child > .card', 'left'],
        ['.principal-laterales:last-child > .card', 'right'],
        ['.principal-centro > .card', 'zoom'],
        ['main .seccion-header', 'left'],
        ['main .seccion-grid > .card', 'up'],
        ['main .grid-busqueda > .card', 'up'],
        ['.cat-info-banner', 'zoom'],
        ['.paginacion', 'up'],
        ['.bloque-inferior > div:nth-child(1)', 'left'],
        ['.bloque-inferior > div:nth-child(2)', 'up'],
        ['.bloque-inferior > div:nth-child(3)', 'right'],
        ['.mas-leido-box', 'left'],
        ['.efemerides-box', 'right'],
        ['.footer-container > div', 'up'],
        ['.card-especial', 'up'],
        ['.edicion-card', 'flip'],
        ['[data-reveal-auto]', 'up']
    ];

    function initReveal() {
        if (reduce || !('IntersectionObserver' in window)) return;

        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) {
                if (en.isIntersecting) {
                    en.target.classList.add('is-visible');
                    io.unobserve(en.target);
                }
            });
        }, { rootMargin: '0px 0px -6% 0px', threshold: 0.08 });

        REVEAL.forEach(function (par) {
            $$(par[0]).forEach(function (el) {
                if (el.hasAttribute('data-reveal')) return;
                el.setAttribute('data-reveal', par[1]);
            });
        });

        // Escalonar la entrada de hermanos (efecto dominó)
        $$('[data-reveal]').forEach(function (el) {
            var hermanos = Array.prototype.filter.call(el.parentNode.children, function (c) {
                return c.hasAttribute('data-reveal');
            });
            var i = hermanos.indexOf(el);
            el.style.setProperty('--plec-d', (Math.min(i, 8) * 0.09).toFixed(2) + 's');
            io.observe(el);
        });

        // Los títulos sueltos (p. ej. resultados de búsqueda) dibujan su subrayado al verse
        var ioTitulos = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) {
                if (en.isIntersecting) { en.target.classList.add('is-visible'); ioTitulos.unobserve(en.target); }
            });
        }, { threshold: 0.4 });
        $$('.seccion-titulo').forEach(function (t) { ioTitulos.observe(t); });

        // Contadores "123 noticias" que suben al aparecer
        var ioNum = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) {
                if (!en.isIntersecting) return;
                ioNum.unobserve(en.target);
                animarNumero(en.target);
            });
        }, { threshold: 0.6 });
        $$('.sec-contador').forEach(function (c) { ioNum.observe(c); });
    }

    function animarNumero(el) {
        var m = el.textContent.match(/(\d+)/);
        if (!m) return;
        var fin = parseInt(m[1], 10), plantilla = el.textContent, t0 = null, dur = 1200;
        function paso(t) {
            if (!t0) t0 = t;
            var k = clamp((t - t0) / dur, 0, 1);
            var e = 1 - Math.pow(1 - k, 3);
            el.textContent = plantilla.replace(m[1], String(Math.round(fin * e)));
            if (k < 1) requestAnimationFrame(paso);
        }
        requestAnimationFrame(paso);
    }

    /* ── 4. INCLINACIÓN 3D DE TARJETAS (delegación: sirve también para
             las que se cargan después, como las de Opinión) ─────────── */
    var TILT_SEL = '.card, .card-especial, .edicion-card, .opinion-card';

    function initTilt() {
        if (reduce || !finePointer) return;
        var MAX = 9;
        var actual = null, rect = null, raf = 0, ultimo = null;

        function marcar() { $$(TILT_SEL).forEach(function (el) { el.classList.add('plec-tilt'); }); }
        marcar();
        // Las tarjetas inyectadas por JS (modal de opinión) se marcan al aparecer
        new MutationObserver(marcar).observe(doc.body, { childList: true, subtree: true });

        function reset(el) {
            el.classList.remove('is-hovering');
            ['--plec-rx', '--plec-ry', '--plec-px', '--plec-py'].forEach(function (p) { el.style.removeProperty(p); });
        }
        function pintar() {
            raf = 0;
            if (!actual || !ultimo || !rect) return;
            var px = clamp((ultimo.clientX - rect.left) / rect.width, 0, 1) - 0.5;
            var py = clamp((ultimo.clientY - rect.top) / rect.height, 0, 1) - 0.5;
            var s = actual.style;
            s.setProperty('--plec-rx', (-py * MAX).toFixed(2) + 'deg');
            s.setProperty('--plec-ry', (px * MAX).toFixed(2) + 'deg');
            s.setProperty('--plec-px', px.toFixed(3));
            s.setProperty('--plec-py', py.toFixed(3));
            s.setProperty('--plec-gx', ((px + 0.5) * 100).toFixed(1) + '%');
            s.setProperty('--plec-gy', ((py + 0.5) * 100).toFixed(1) + '%');
        }

        doc.addEventListener('pointermove', function (e) {
            var el = e.target.closest ? e.target.closest(TILT_SEL) : null;
            if (el !== actual) {
                if (actual) reset(actual);
                actual = el;
                if (el) {
                    el.classList.add('plec-tilt', 'is-hovering');
                    rect = el.getBoundingClientRect();
                }
            }
            if (!actual) return;
            ultimo = e;
            if (!raf) raf = requestAnimationFrame(pintar);
        }, { passive: true });

        doc.addEventListener('pointerleave', function () { if (actual) { reset(actual); actual = null; } });
        window.addEventListener('scroll', function () { if (actual) rect = actual.getBoundingClientRect(); }, { passive: true });
    }

    /* ── 5. BOTONES MAGNÉTICOS + LUZ QUE SIGUE AL MOUSE ─────────────── */
    function initMagnetico() {
        if (reduce || !finePointer) return;
        var sel = '.social-icons a, .btn-tema, .btn-accesibilidad, .btn-arriba, .btn-revista-flotante, .btn-transmision-live, .menu-toggle';
        $$(sel).forEach(function (el) {
            el.setAttribute('data-magnetic', '');
            el.addEventListener('pointermove', function (e) {
                var r = el.getBoundingClientRect();
                var dx = (e.clientX - (r.left + r.width / 2)) * 0.28;
                var dy = (e.clientY - (r.top + r.height / 2)) * 0.28;
                el.style.setProperty('--plec-mgx', clamp(dx, -10, 10).toFixed(1) + 'px');
                el.style.setProperty('--plec-mgy', clamp(dy, -10, 10).toFixed(1) + 'px');
            });
            el.addEventListener('pointerleave', function () {
                el.style.removeProperty('--plec-mgx');
                el.style.removeProperty('--plec-mgy');
            });
        });

        var amb = $('.plec-ambient'), raf = 0, mx = 0, my = 0;
        if (!amb) return;
        doc.addEventListener('pointermove', function (e) {
            mx = e.clientX; my = e.clientY;
            if (raf) return;
            raf = requestAnimationFrame(function () {
                raf = 0;
                amb.style.setProperty('--plec-mx', mx + 'px');
                amb.style.setProperty('--plec-my', my + 'px');
            });
        }, { passive: true });
    }

    /* ── 6. INCLINACIÓN SUAVE DE ELEMENTOS SUELTOS (logo, imagen de la
             noticia) ─────────────────────────────────────────────────── */
    function tiltSuave(zona, objetivo, max, prefijo) {
        if (reduce || !finePointer || !zona || !objetivo) return;
        prefijo = prefijo || '--plec-';
        zona.addEventListener('pointermove', function (e) {
            var r = zona.getBoundingClientRect();
            var px = (e.clientX - r.left) / r.width - 0.5;
            var py = (e.clientY - r.top) / r.height - 0.5;
            objetivo.style.setProperty(prefijo + 'rx', (-py * max).toFixed(2) + 'deg');
            objetivo.style.setProperty(prefijo + 'ry', (px * max).toFixed(2) + 'deg');
        });
        zona.addEventListener('pointerleave', function () {
            objetivo.style.setProperty(prefijo + 'rx', '0deg');
            objetivo.style.setProperty(prefijo + 'ry', '0deg');
        });
    }

    function initTiltsSueltos() {
        var hero = $('header.plec-hero'), logo = $('.plec-logo-wrap');
        if (hero && logo && !reduce && finePointer) {
            hero.addEventListener('pointermove', function (e) {
                var r = hero.getBoundingClientRect();
                var px = (e.clientX - r.left) / r.width - 0.5;
                var py = (e.clientY - r.top) / r.height - 0.5;
                logo.style.setProperty('--plec-lx', (-py * 14).toFixed(2) + 'deg');
                logo.style.setProperty('--plec-ly', (px * 18).toFixed(2) + 'deg');
            });
            hero.addEventListener('pointerleave', function () {
                logo.style.setProperty('--plec-lx', '0deg');
                logo.style.setProperty('--plec-ly', '0deg');
            });
        }
        tiltSuave($('.plec-articulo-img-wrap'), $('.plec-articulo-img-wrap .imagen-noticia'), 8);
    }

    /* ── 7. NAV: marcar la categoría activa ─────────────────────────── */
    function initNavActiva() {
        var params = new URLSearchParams(location.search);
        var cat = params.get('cat');
        $$('.nav-container a').forEach(function (a) {
            var href = a.getAttribute('href') || '';
            var m = href.match(/cat=(\d+)/);
            var esInicio = !m && /index\.php$/.test(href);
            if ((cat && m && m[1] === cat) || (!cat && !params.get('s') && esInicio && /index\.php$|\/$/.test(location.pathname))) {
                a.classList.add('activo');
                if (a.scrollIntoView && cat) {
                    // En móvil la barra es deslizable: centrar la categoría actual
                    var ul = a.closest('.nav-container');
                    if (ul && ul.scrollWidth > ul.clientWidth) ul.scrollLeft = a.offsetLeft - ul.clientWidth / 2 + a.offsetWidth / 2;
                }
            }
        });
    }

    /* ── 8. GLOBO 3D DE PARTÍCULAS (cabecera) ───────────────────────── */
    function initGlobo() {
        var canvas = $('canvas.plec-globe');
        if (!canvas || !canvas.getContext) return;
        var ctx = canvas.getContext('2d');
        var hero = canvas.parentNode;
        var W = 0, H = 0, DPR = 1, visible = true, raf = 0;

        var N_GLOBO = isSmall ? 340 : 760;
        var N_RED = isSmall ? 26 : 64;
        var N_ANILLO = isSmall ? 70 : 140;
        var GOLDEN = Math.PI * (3 - Math.sqrt(5));

        // Puntos del globo distribuidos uniformemente (espiral de Fibonacci)
        var globo = [];
        for (var i = 0; i < N_GLOBO; i++) {
            var y = 1 - (i / (N_GLOBO - 1)) * 2;
            var r = Math.sqrt(1 - y * y);
            var phi = i * GOLDEN;
            globo.push([Math.cos(phi) * r, y, Math.sin(phi) * r]);
        }
        // Anillo orbital inclinado
        var anillo = [];
        for (var j = 0; j < N_ANILLO; j++) {
            var a = (j / N_ANILLO) * Math.PI * 2;
            anillo.push([Math.cos(a) * 1.45, (Math.random() - 0.5) * 0.05, Math.sin(a) * 1.45]);
        }
        // Constelación de "noticias" que flota a los lados
        var red = [];
        for (var k = 0; k < N_RED; k++) {
            red.push({
                x: Math.random(), y: Math.random(), z: Math.random(),
                vx: (Math.random() - 0.5) * 0.00045, vy: (Math.random() - 0.5) * 0.00045
            });
        }

        var rotY = 0, rotX = -0.38, mxT = 0, myT = 0, mx = 0, my = 0;

        function resize() {
            var r = hero.getBoundingClientRect();
            DPR = Math.min(window.devicePixelRatio || 1, 2);
            W = Math.max(1, r.width); H = Math.max(1, r.height);
            canvas.width = Math.round(W * DPR);
            canvas.height = Math.round(H * DPR);
            ctx.setTransform(DPR, 0, 0, DPR, 0, 0);
            if (reduce) frame(0);
        }

        function proyectar(p, cosY, sinY, cosX, sinX, R, cx, cy) {
            var x = p[0] * cosY - p[2] * sinY;
            var z = p[0] * sinY + p[2] * cosY;
            var y = p[1] * cosX - z * sinX;
            z = p[1] * sinX + z * cosX;
            var f = 3.2 / (3.2 + z);
            return [cx + x * R * f, cy + y * R * f, z, f];
        }

        function frame(t) {
            raf = 0;
            var claro = doc.body.classList.contains('modo-claro');
            ctx.clearRect(0, 0, W, H);

            mx += (mxT - mx) * 0.05;
            my += (myT - my) * 0.05;
            if (!reduce) rotY += 0.0026;

            var cx = W / 2 + mx * 30, cy = H * 0.55 + my * 16;
            var R = Math.max(110, Math.min(H * 0.95, W * 0.3));
            var cosY = Math.cos(rotY + mx * 0.6), sinY = Math.sin(rotY + mx * 0.6);
            var ax = rotX + my * 0.3;
            var cosX = Math.cos(ax), sinX = Math.sin(ax);

            // Halo detrás del globo
            var halo = ctx.createRadialGradient(cx, cy, R * 0.2, cx, cy, R * 1.5);
            halo.addColorStop(0, claro ? 'rgba(26,115,232,0.10)' : 'rgba(26,115,232,0.16)');
            halo.addColorStop(1, 'rgba(26,115,232,0)');
            ctx.fillStyle = halo;
            ctx.fillRect(0, 0, W, H);

            // Constelación lateral (con líneas entre puntos cercanos)
            var pts = [];
            for (var i = 0; i < red.length; i++) {
                var n = red[i];
                if (!reduce) {
                    n.x += n.vx; n.y += n.vy;
                    if (n.x < 0 || n.x > 1) n.vx *= -1;
                    if (n.y < 0 || n.y > 1) n.vy *= -1;
                }
                var depth = 0.6 + n.z * 0.8;
                pts.push([n.x * W + mx * 40 * depth, n.y * H + my * 20 * depth, depth]);
            }
            var maxD = Math.min(170, W * 0.14);
            ctx.lineWidth = 1;
            for (var a = 0; a < pts.length; a++) {
                for (var b = a + 1; b < pts.length; b++) {
                    var dx = pts[a][0] - pts[b][0], dy = pts[a][1] - pts[b][1];
                    var d = Math.sqrt(dx * dx + dy * dy);
                    if (d < maxD) {
                        var al = (1 - d / maxD) * 0.28;
                        ctx.strokeStyle = claro ? 'rgba(26,90,200,' + al + ')' : 'rgba(120,170,255,' + al + ')';
                        ctx.beginPath(); ctx.moveTo(pts[a][0], pts[a][1]); ctx.lineTo(pts[b][0], pts[b][1]); ctx.stroke();
                    }
                }
            }
            for (var c = 0; c < pts.length; c++) {
                ctx.fillStyle = claro ? 'rgba(26,90,200,0.55)' : 'rgba(180,210,255,0.7)';
                ctx.beginPath(); ctx.arc(pts[c][0], pts[c][1], 1.1 * pts[c][2] + 0.4, 0, Math.PI * 2); ctx.fill();
            }

            // Globo: puntos con profundidad (los de atrás más tenues y pequeños)
            for (var g = 0; g < globo.length; g++) {
                var p = proyectar(globo[g], cosY, sinY, cosX, sinX, R, cx, cy);
                var front = (1 - p[2]) / 2;               // 0 = atrás, 1 = adelante
                var lat = (globo[g][1] + 1) / 2;          // 0 = sur, 1 = norte
                // Norte azul claro → sur rojo coral (colores de la marca, aclarados para fondo oscuro)
                var rr = Math.round(claro ? 26 + 200 * (1 - lat) : 110 + 145 * (1 - lat));
                var gg = Math.round(claro ? 90 - 30 * (1 - lat) : 168 - 58 * (1 - lat));
                var bb = Math.round(claro ? 220 - 150 * (1 - lat) : 255 - 145 * (1 - lat));
                var alpha = (claro ? 0.14 : 0.12) + front * (claro ? 0.6 : 0.8);
                ctx.fillStyle = 'rgba(' + rr + ',' + gg + ',' + bb + ',' + alpha.toFixed(3) + ')';
                var size = (0.8 + front * 1.9) * p[3];
                ctx.fillRect(p[0] - size / 2, p[1] - size / 2, size, size);
            }

            // Anillo orbital + "satélite" brillante
            var tiltCos = Math.cos(0.42), tiltSin = Math.sin(0.42);
            for (var o = 0; o < anillo.length; o++) {
                var q = anillo[o];
                var tq = [q[0], q[1] * tiltCos - q[2] * tiltSin, q[1] * tiltSin + q[2] * tiltCos];
                var pr = proyectar(tq, cosY, sinY, cosX, sinX, R, cx, cy);
                var fr = (1 - pr[2]) / 2;
                ctx.fillStyle = claro ? 'rgba(200,40,40,' + (0.1 + fr * 0.4).toFixed(3) + ')' : 'rgba(255,110,110,' + (0.08 + fr * 0.5).toFixed(3) + ')';
                ctx.fillRect(pr[0] - 0.8, pr[1] - 0.8, 1.6, 1.6);
            }
            var sa = (t || 0) * 0.0006;
            var sat = [Math.cos(sa) * 1.45, 0, Math.sin(sa) * 1.45];
            var ts = [sat[0], sat[1] * tiltCos - sat[2] * tiltSin, sat[1] * tiltSin + sat[2] * tiltCos];
            var ps = proyectar(ts, cosY, sinY, cosX, sinX, R, cx, cy);
            var glow = ctx.createRadialGradient(ps[0], ps[1], 0, ps[0], ps[1], 14 * ps[3]);
            glow.addColorStop(0, 'rgba(255,220,120,0.95)');
            glow.addColorStop(1, 'rgba(255,120,80,0)');
            ctx.fillStyle = glow;
            ctx.beginPath(); ctx.arc(ps[0], ps[1], 14 * ps[3], 0, Math.PI * 2); ctx.fill();

            if (!reduce && visible && !doc.hidden) raf = requestAnimationFrame(frame);
        }

        function arrancar() { if (!raf && !reduce && visible && !doc.hidden) raf = requestAnimationFrame(frame); }

        if (finePointer && !reduce) {
            hero.addEventListener('pointermove', function (e) {
                var r = hero.getBoundingClientRect();
                mxT = ((e.clientX - r.left) / r.width - 0.5) * 2;
                myT = ((e.clientY - r.top) / r.height - 0.5) * 2;
            });
            hero.addEventListener('pointerleave', function () { mxT = 0; myT = 0; });
        }

        // Pausar cuando la cabecera no se ve o la pestaña está oculta (ahorra batería)
        if ('IntersectionObserver' in window) {
            new IntersectionObserver(function (en) {
                visible = en[0].isIntersecting;
                if (visible) arrancar();
            }).observe(hero);
        }
        doc.addEventListener('visibilitychange', arrancar);
        if ('ResizeObserver' in window) new ResizeObserver(resize).observe(hero);
        else window.addEventListener('resize', resize);

        resize();
        if (reduce) frame(0); else arrancar();
    }

    /* ── ARRANQUE ───────────────────────────────────────────────────── */
    function init() {
        initCapas();
        initScroll();
        initReveal();
        initTilt();
        initMagnetico();
        initTiltsSueltos();
        initNavActiva();
        initGlobo();
    }

    if (doc.readyState === 'loading') doc.addEventListener('DOMContentLoaded', init);
    else init();

    // API mínima por si otras partes de la página la necesitan
    window.PLEC3D = { reduce: reduce, finePointer: finePointer };
})();
