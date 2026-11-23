/* ════════════════════════════════════════════════════════════════════
   NOTICIAS PLEC — MAPA VIVO DE COLOMBIA (portada)
   · Nivel 1: los 33 departamentos (assets/mapa/colombia.svg), coloreados
     por noticias de los últimos 30 días, con un punto que late donde hubo
     noticia en las últimas 24 h. El Tolima siempre destacado.
   · Nivel 2: los municipios del departamento, cargados solo al entrar
     (assets/mapa/municipios/{slug}.json). Sin ese archivo, el departamento
     se ve entero y sus noticias funcionan igual.
   · Panel con las últimas 5 noticias del lugar y "Pulso del día" con las
     últimas 8. Las noticias se abren con abrirModal() de index.php.
   · Datos: get_mapa.php. Si falla, el bloque muestra un aviso amable.
   · Carga diferida: no pide nada hasta que el bloque se acerca a la pantalla.
   · Teclado: Tab recorre los lugares, Enter/Espacio abre, Escape vuelve.
   · Táctil: el primer toque muestra el resumen, el segundo abre.
   · Reducir movimiento: sin zoom animado ni puntos que laten.
   ════════════════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    var doc = document;
    var raiz = doc.getElementById('mapaVivo');
    if (!raiz) return;
    if (!window.fetch || !window.DOMParser || !window.Promise || !doc.createElementNS) { raiz.hidden = true; return; }

    var NS = 'http://www.w3.org/2000/svg';
    var TOLIMA = '73';
    var VERSION = raiz.getAttribute('data-v') || '1';
    var mqReduce = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : { matches: false };

    function $(id) { return doc.getElementById(id); }
    var el = {
        cuerpo: $('mvCuerpo'), mapa: $('mvMapa'), lienzo: $('mvLienzo'), capa: $('mvCapa'), tip: $('mvTip'),
        migas: $('mvMigas'), volver: $('mvVolver'), resumen: $('mvResumen'), leyMax: $('mvLeyMax'),
        rapidos: $('mvRapidos'), panelTitulo: $('mvPanelTitulo'), panelMeta: $('mvPanelMeta'),
        badge: $('mvBadgeCasa'), lista: $('mvLista'), pulsoCaja: $('mvPulsoCaja'), pulso: $('mvPulso'),
        aviso: $('mvAviso'), reintentar: $('mvReintentar'), vivo: $('mvVivo'), saltar: $('mvSaltar')
    };

    // ── Estado ───────────────────────────────────────────────────────
    var E = {
        cargado: false,
        nivel: 1,            // 1 = Colombia, 2 = un departamento
        dep: null,           // código DANE del departamento abierto ("73")
        mun: null,           // código DANE del municipio elegido ("73001")
        geo: null,           // archivo de municipios del departamento abierto
        pais: null,          // respuesta de get_mapa.php (nivel 1)
        ahora: 0, recibido: 0,
        cacheDep: {}, cacheMun: {}, cacheGeo: {},
        vb: null,            // viewBox actual [x, y, ancho, alto]
        objetivo: null,      // caja que se está encuadrando (null = todo el país)
        maxZoom: 6,
        maxPais: 0, maxMunis: 0,
        tam: { w: 1, h: 1 }, // tamaño del lienzo en px (sin transformaciones)
        puntero: 'mouse',
        armado: null,        // región tocada una vez (táctil)
        tipRegion: null,
        foco: null,
        pedido: 0            // sube con cada cambio de lugar: las respuestas viejas se ignoran
    };
    var svg, gDeptos, gMunis, pCasa, pSel, pResalte;
    var deptos = {};         // dane → { el, dane, slug, nombre, l, b, datos, tipo: 'dep' }
    var munis = {};          // dane → { el, dane, nombre, l, b, datos, tipo: 'mun' }
    var vbPais = [0, 0, 800, 1100];
    var noticias = {};       // id → noticia (para abrirla desde el panel o el pulso)
    var capaItems = [];
    var animacion = 0, movRaf = 0, movEvento = null;

    // ── Utilidades ───────────────────────────────────────────────────
    function svgEl(tag, attrs) {
        var n = doc.createElementNS(NS, tag);
        for (var k in attrs) if (Object.prototype.hasOwnProperty.call(attrs, k)) n.setAttribute(k, attrs[k]);
        return n;
    }
    function h(tag, clase, texto) {
        var n = doc.createElement(tag);
        if (clase) n.className = clase;
        if (texto != null) n.textContent = texto;
        return n;
    }
    function numeros(txt) { return String(txt || '').trim().split(/[\s,]+/).map(Number); }
    function plural(n, uno, varios) { return n + ' ' + (n === 1 ? uno : varios); }
    function recortar(t, max) { t = String(t || ''); return t.length > max ? t.slice(0, max - 1).replace(/\s+\S*$/, '') + '…' : t; }
    function enfocar(n) { if (!n) return; try { n.focus({ preventScroll: true }); } catch (e) { try { n.focus(); } catch (e2) { /* nada */ } } }
    function focoVisible(n) { try { return n.matches(':focus-visible'); } catch (e) { return true; } }
    function anunciar(txt) {
        el.vivo.textContent = '';
        setTimeout(function () { el.vivo.textContent = txt; }, 80);
    }

    // Fechas en hora de Colombia, contadas desde el reloj del servidor
    function formato(op, idioma) {
        var o = {}; for (var k in op) o[k] = op[k];
        try { o.timeZone = 'America/Bogota'; return new Intl.DateTimeFormat(idioma || 'es-CO', o); }
        catch (e) { delete o.timeZone; return new Intl.DateTimeFormat(idioma || 'es-CO', o); }
    }
    var fHora = formato({ hour: 'numeric', minute: '2-digit' });
    var fDia = formato({ day: 'numeric', month: 'short' });
    var fClave = formato({ year: 'numeric', month: '2-digit', day: '2-digit' }, 'en-CA');
    function ahoraServidor() { return E.ahora + (Date.now() - E.recibido) / 1000; }
    function cualDia(ts) {
        var ahora = ahoraServidor(), d = fClave.format(ts * 1000);
        if (d === fClave.format(ahora * 1000)) return 'hoy';
        if (d === fClave.format((ahora - 86400) * 1000)) return 'ayer';
        return '';
    }
    function hace(ts) {
        var s = Math.max(0, ahoraServidor() - ts);
        if (s < 60) return 'hace un momento';
        if (s < 3600) return 'hace ' + Math.max(1, Math.floor(s / 60)) + ' min';
        if (s < 6 * 3600) return 'hace ' + Math.floor(s / 3600) + ' h';
        var dia = cualDia(ts);
        if (dia) return dia + ', ' + fHora.format(ts * 1000);
        return fDia.format(ts * 1000);
    }
    function horaCorta(ts) {
        var dia = cualDia(ts);
        if (dia === 'hoy') return fHora.format(ts * 1000);
        if (dia === 'ayer') return 'Ayer';
        return fDia.format(ts * 1000);
    }
    function lugarDe(n) { return n.mn ? n.mn + ', ' + n.dn : n.dn; }

    // Escala de color: azul → morado → rojo (logarítmica; con pocas noticias no todo es rojo)
    var PARADAS = [[58, 111, 216], [142, 68, 173], [255, 77, 77]];
    function colorPara(n, max) {
        if (!n) return '';
        var t = Math.log(1 + n) / Math.log(1 + Math.max(max, 4));
        t = Math.pow(Math.max(0, Math.min(1, t)), 1.5);     // los valores bajos se quedan en azul
        var a = t < 0.5 ? PARADAS[0] : PARADAS[1], b = t < 0.5 ? PARADAS[1] : PARADAS[2];
        var k = t < 0.5 ? t / 0.5 : (t - 0.5) / 0.5;
        return 'rgb(' + [0, 1, 2].map(function (i) { return Math.round(a[i] + (b[i] - a[i]) * k); }).join(',') + ')';
    }
    function etiquetaAria(nombre, d, casa) {
        var t = nombre + ', ' + plural(d.n, 'noticia', 'noticias') + ' en los últimos 30 días';
        if (d.h) t += ', ' + d.h + ' en las últimas 24 horas';
        return t + (casa ? '. Nuestra casa' : '');
    }
    var VACIO = { n: 0, t: 0, h: 0, u: null };

    // ── Datos ────────────────────────────────────────────────────────
    function pedirJSON(consulta) {
        return fetch(raiz.getAttribute('data-api') + consulta, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then(function (r) {
                return r.json().catch(function () { return null; }).then(function (d) {
                    if (!r.ok || !d || d.ok !== true) throw new Error('get_mapa.php respondió ' + r.status);
                    return d;
                });
            });
    }
    function cargarDep(dane) {
        if (E.cacheDep[dane]) return Promise.resolve(E.cacheDep[dane]);
        return pedirJSON('?depto=' + parseInt(dane, 10)).then(function (d) { E.cacheDep[dane] = d; return d; });
    }
    function cargarMun(dep, mun) {
        if (E.cacheMun[mun]) return Promise.resolve(E.cacheMun[mun]);
        return pedirJSON('?depto=' + parseInt(dep, 10) + '&muni=' + parseInt(mun, 10)).then(function (d) { E.cacheMun[mun] = d; return d; });
    }
    function cargarGeo(slug) {
        if (E.cacheGeo[slug]) return Promise.resolve(E.cacheGeo[slug]);
        return fetch(raiz.getAttribute('data-geo') + encodeURIComponent(slug) + '.json?v=' + encodeURIComponent(VERSION))
            .then(function (r) { if (!r.ok) throw new Error('municipios ' + r.status); return r.json(); })
            .then(function (j) {
                if (!j || !j.m || !j.m.length) throw new Error('municipios vacíos');
                E.cacheGeo[slug] = j;
                return j;
            });
    }

    // ── Arranque ─────────────────────────────────────────────────────
    function iniciar() {
        if ('IntersectionObserver' in window) {
            // Lo que se mueve solo se pausa mientras el bloque no se ve
            new IntersectionObserver(function (en) {
                raiz.classList.toggle('mv-en-vista', en[en.length - 1].isIntersecting);
            }).observe(raiz);
            var io = new IntersectionObserver(function (en) {
                if (!en[en.length - 1].isIntersecting) return;
                io.disconnect();
                cargar();
            }, { rootMargin: '700px 0px' });
            io.observe(raiz);
        } else {
            raiz.classList.add('mv-en-vista');
            cargar();
        }
        conectarPanel();
    }

    function cargar() {
        raiz.setAttribute('aria-busy', 'true');
        Promise.all([
            fetch(raiz.getAttribute('data-svg')).then(function (r) { if (!r.ok) throw new Error('colombia.svg ' + r.status); return r.text(); }),
            pedirJSON('')
        ]).then(function (res) {
            armarSvg(res[0]);
            recibirPais(res[1]);
            E.cargado = true;
            raiz.classList.add('mv-listo');
            raiz.removeAttribute('aria-busy');
        }).catch(fallo);
    }

    function fallo(err) {
        if (window.console && console.warn) console.warn('Mapa vivo: no se pudo cargar.', err && err.message ? err.message : err);
        raiz.removeAttribute('aria-busy');
        el.cuerpo.hidden = true;
        el.pulsoCaja.hidden = true;
        el.resumen.textContent = '';
        el.aviso.hidden = false;
    }

    function reintentar() {
        el.aviso.hidden = true;
        el.cuerpo.hidden = false;
        el.pulsoCaja.hidden = false;
        if (!svg) {
            var c = h('div', 'mv-carga', 'Cargando el mapa…');
            el.lienzo.textContent = '';
            el.lienzo.appendChild(c);
            cargar();
            return;
        }
        pedirJSON('').then(function (d) {
            recibirPais(d);
            E.cargado = true;
            raiz.classList.add('mv-listo');
        }).catch(fallo);
    }

    // ── El SVG nacional ──────────────────────────────────────────────
    function armarSvg(texto) {
        var dom = new DOMParser().parseFromString(texto, 'image/svg+xml');
        var nodo = dom.documentElement;
        if (!nodo || nodo.nodeName.toLowerCase() !== 'svg' || dom.getElementsByTagName('parsererror').length) throw new Error('colombia.svg no es válido');
        svg = doc.importNode(nodo, true);
        svg.setAttribute('class', 'mv-svg');
        svg.setAttribute('role', 'group');
        svg.setAttribute('aria-label', 'Mapa de Colombia por departamentos');
        svg.setAttribute('aria-describedby', 'mvAyuda');
        svg.setAttribute('preserveAspectRatio', 'xMidYMid meet');

        // Degradado del borde del Tolima (azul → morado → rojo, como la marca)
        var defs = svgEl('defs', {});
        var grad = svgEl('linearGradient', { id: 'mvGradCasa', x1: '0', y1: '0', x2: '1', y2: '1' });
        [['0', '#1a73e8'], ['0.5', '#8e44ad'], ['1', '#ff4d4d']].forEach(function (s) {
            grad.appendChild(svgEl('stop', { offset: s[0], 'stop-color': s[1] }));
        });
        defs.appendChild(grad);
        svg.insertBefore(defs, svg.firstChild);

        var recuadro = svg.querySelector('.mv-recuadro');
        if (recuadro) recuadro.setAttribute('aria-hidden', 'true');
        gDeptos = svg.querySelector('.mv-deptos');
        if (!gDeptos) throw new Error('colombia.svg sin departamentos');
        gMunis = svgEl('g', { 'class': 'mv-munis' });
        pCasa = svgEl('path', { 'class': 'mv-casa', d: '' });
        pSel = svgEl('path', { 'class': 'mv-sel', d: '' });
        pResalte = svgEl('path', { 'class': 'mv-resalte', d: '' });
        svg.appendChild(gMunis);
        svg.appendChild(pCasa);
        svg.appendChild(pSel);
        svg.appendChild(pResalte);

        Array.prototype.forEach.call(gDeptos.querySelectorAll('.mv-depto'), function (p) {
            var d = {
                el: p, tipo: 'dep', dane: p.getAttribute('data-dane'), slug: p.getAttribute('data-slug'),
                nombre: p.getAttribute('data-nombre'), l: numeros(p.getAttribute('data-l')), b: numeros(p.getAttribute('data-b')),
                datos: VACIO
            };
            deptos[d.dane] = d;
            p.setAttribute('role', 'button');
            p.setAttribute('tabindex', '0');
            p.setAttribute('aria-label', d.nombre);
        });

        var vb = svg.viewBox && svg.viewBox.baseVal;
        if (vb && vb.width) vbPais = [vb.x, vb.y, vb.width, vb.height];
        el.lienzo.textContent = '';
        el.lienzo.appendChild(svg);
        medir();
        ponerVista(vistaDe(null));
        if (deptos[TOLIMA]) pCasa.setAttribute('d', deptos[TOLIMA].el.getAttribute('d'));
        conectarMapa();
    }

    // ── Encuadre y zoom ──────────────────────────────────────────────
    function medir() {
        E.tam = { w: el.lienzo.offsetWidth || 1, h: el.lienzo.offsetHeight || 1 };
    }
    function margenes() {
        var movil = E.tam.w < 560;
        return { t: 54, b: movil ? 58 : 18, l: 14, r: 14 };
    }
    function escalaPais() {
        var m = margenes();
        return Math.min(Math.max(40, E.tam.w - m.l - m.r) / vbPais[2], Math.max(40, E.tam.h - m.t - m.b) / vbPais[3]);
    }
    // viewBox con el mismo aspecto del lienzo que muestra la caja b (null = todo el país)
    function vistaDe(b, maxZoom) {
        var m = margenes(), W = E.tam.w, H = E.tam.h;
        var aw = Math.max(40, W - m.l - m.r), ah = Math.max(40, H - m.t - m.b);
        var caja = b || vbPais, holgura = b ? 1.12 : 1;
        var s = Math.min(aw / (caja[2] * holgura), ah / (caja[3] * holgura));
        if (b && maxZoom) s = Math.min(s, escalaPais() * maxZoom);
        var cx = caja[0] + caja[2] / 2, cy = caja[1] + caja[3] / 2;
        return [cx - (m.l + aw / 2) / s, cy - (m.t + ah / 2) / s, W / s, H / s];
    }
    function ponerVista(vb) {
        E.vb = vb;
        svg.setAttribute('viewBox', vb[0].toFixed(2) + ' ' + vb[1].toFixed(2) + ' ' + vb[2].toFixed(2) + ' ' + vb[3].toFixed(2));
    }
    function aPantalla(x, y) {
        var k = E.tam.w / E.vb[2];
        return [(x - E.vb[0]) * k, (y - E.vb[1]) * k];
    }
    // Zoom animado: el centro se mueve en línea recta y el ancho cambia en proporción (se siente parejo)
    function irA(destino, alTerminar) {
        cancelAnimationFrame(animacion);
        if (mqReduce.matches || !E.vb) {
            ponerVista(destino);
            raiz.classList.remove('mv-moviendo');
            if (alTerminar) alTerminar();
            return;
        }
        var desde = E.vb.slice(), t0 = 0, dur = 720;
        var c0 = [desde[0] + desde[2] / 2, desde[1] + desde[3] / 2], c1 = [destino[0] + destino[2] / 2, destino[1] + destino[3] / 2];
        var razon = destino[2] / desde[2], asp = destino[3] / destino[2];
        raiz.classList.add('mv-moviendo');
        function paso(t) {
            if (!t0) t0 = t;
            var k = Math.min(1, (t - t0) / dur);
            var e = k < 0.5 ? 4 * k * k * k : 1 - Math.pow(-2 * k + 2, 3) / 2;
            if (k >= 1) {
                ponerVista(destino);
                raiz.classList.remove('mv-moviendo');
                if (alTerminar) alTerminar();
                return;
            }
            // Todas las vistas tienen el aspecto del lienzo: el alto sale del ancho
            var w = desde[2] * Math.pow(razon, e), hh = w * asp;
            var cx = c0[0] + (c1[0] - c0[0]) * e, cy = c0[1] + (c1[1] - c0[1]) * e;
            ponerVista([cx - w / 2, cy - hh / 2, w, hh]);
            animacion = requestAnimationFrame(paso);
        }
        animacion = requestAnimationFrame(paso);
    }

    // ── Capa HTML encima del mapa: puntos de 24 h y "Nuestra casa" ───
    function agregarCapa(nodo, x, y) {
        capaItems.push({ el: nodo, x: x, y: y });
        el.capa.appendChild(nodo);
    }
    function nuevoPunto(x, y, ping) {
        var p = h('span', 'mv-punto' + (ping ? ' mv-ping' : ''));
        agregarCapa(p, x, y);
        return p;
    }
    function rehacerCapa() {
        el.capa.textContent = '';
        capaItems = [];
        if (!E.cargado && !E.pais) return;
        if (E.nivel === 1) {
            Object.keys(deptos).forEach(function (k) {
                var d = deptos[k];
                if (d.datos.h > 0) nuevoPunto(d.l[0], d.l[1]);
            });
            var t = deptos[TOLIMA];
            if (t) {
                var etq = h('span', 'mv-casa-etq'), s = h('span'), ico = h('i', 'fas fa-house');
                ico.setAttribute('aria-hidden', 'true');
                s.appendChild(ico);
                s.appendChild(doc.createTextNode('Nuestra casa'));
                etq.appendChild(s);
                agregarCapa(etq, t.l[0], t.l[1]);
            }
        } else if (E.geo) {
            Object.keys(munis).forEach(function (k) {
                var m = munis[k];
                if (m.datos.h > 0) nuevoPunto(m.l[0], m.l[1]);
            });
        } else if (deptos[E.dep] && deptos[E.dep].datos.h > 0) {
            nuevoPunto(deptos[E.dep].l[0], deptos[E.dep].l[1]);
        }
        colocarCapa();
    }
    function colocarCapa() {
        capaItems.forEach(function (it) {
            var p = aPantalla(it.x, it.y);
            it.el.style.transform = 'translate(' + p[0].toFixed(1) + 'px,' + p[1].toFixed(1) + 'px)';
            // La etiqueta se abre hacia el lado donde cabe
            if (it.el.classList.contains('mv-casa-etq')) it.el.classList.toggle('mv-derecha', p[0] < 150);
        });
    }

    // ── Tooltip ──────────────────────────────────────────────────────
    var tipTam = [0, 0];
    function contenidoTip(r) {
        var d = r.datos || VACIO, f = doc.createDocumentFragment();
        var nom = h('span', 'mv-tip-nombre', r.nombre);
        if (r.tipo === 'dep' && r.dane === TOLIMA) nom.appendChild(h('span', 'mv-tip-casa', 'Nuestra casa'));
        f.appendChild(nom);
        var num = h('span', 'mv-tip-num');
        if (d.n) {
            num.appendChild(h('b', '', String(d.n)));
            num.appendChild(doc.createTextNode(' ' + (d.n === 1 ? 'noticia' : 'noticias') + ' en 30 días' + (d.t > d.n ? ' · ' + d.t + ' en total' : '')));
        } else {
            num.textContent = 'Sin noticias en los últimos 30 días';
        }
        f.appendChild(num);
        if (d.h) {
            f.appendChild(doc.createTextNode(' · '));
            f.appendChild(h('span', 'mv-tip-hoy', plural(d.h, 'nueva', 'nuevas') + ' en 24 h'));
        }
        if (d.u) {
            var u = h('span', 'mv-tip-ultima');
            u.appendChild(doc.createTextNode('Última: '));
            u.appendChild(h('q', '', recortar(d.u.t, 90)));
            u.appendChild(doc.createTextNode(' · ' + hace(d.u.ts)));
            f.appendChild(u);
        }
        var accion;
        if (E.puntero === 'touch') accion = 'Toca otra vez para abrir';
        else if (r.tipo === 'dep' && !(E.nivel === 2 && E.dep === r.dane)) accion = E.pais && E.pais.geo.indexOf(r.slug) >= 0 ? 'Clic para ver sus municipios' : 'Clic para ver sus noticias';
        else accion = 'Clic para ver sus noticias';
        f.appendChild(h('span', 'mv-tip-accion', accion));
        return f;
    }
    function mostrarTip(r, px, py, anclado) {
        el.tip.textContent = '';
        el.tip.appendChild(contenidoTip(r));
        el.tip.hidden = false;
        tipTam = [el.tip.offsetWidth, el.tip.offsetHeight];
        moverTip(px, py, anclado);
        el.tip.classList.add('mv-on');
        E.tipRegion = r;
    }
    function moverTip(px, py, anclado) {
        var W = E.tam.w, H = E.tam.h, w = tipTam[0], hh = tipTam[1], x, y;
        if (anclado) {
            x = px - w / 2; y = py - hh - 16;
            if (y < 50) y = py + 18;
        } else {
            x = px + 16; y = py + 18;
            if (x + w > W - 8) x = px - w - 16;
            if (y + hh > H - 8) y = py - hh - 14;
        }
        x = Math.max(8, Math.min(W - w - 8, x));
        y = Math.max(8, Math.min(H - hh - 8, y));
        el.tip.style.transform = 'translate(' + Math.round(x) + 'px,' + Math.round(y) + 'px)';
    }
    function ocultarTip() {
        el.tip.classList.remove('mv-on');
        el.tip.hidden = true;
        E.tipRegion = null;
    }
    function tipEnRegion(r) {
        var p = aPantalla(r.l[0], r.l[1]);
        mostrarTip(r, p[0], p[1], true);
    }
    function resaltar(r) { pResalte.setAttribute('d', r ? r.el.getAttribute('d') : ''); }

    // ── Eventos del mapa ─────────────────────────────────────────────
    function regionDe(t) {
        if (!t || !t.getAttribute || !t.classList) return null;
        if (t.classList.contains('mv-muni')) return munis[t.getAttribute('data-dane')] || null;
        if (t.classList.contains('mv-depto') || t.classList.contains('mv-recuadro-marco')) return deptos[t.getAttribute('data-dane')] || null;
        return null;
    }
    function puntoEnMapa(e) {
        var r = el.mapa.getBoundingClientRect();
        return [e.clientX - r.left, e.clientY - r.top];
    }
    function conectarMapa() {
        svg.addEventListener('pointerdown', function (e) { E.puntero = e.pointerType || 'mouse'; }, { passive: true });
        svg.addEventListener('pointerover', function (e) {
            if (e.pointerType === 'touch' || raiz.classList.contains('mv-moviendo')) return;
            var r = regionDe(e.target);
            if (!r) return;
            E.puntero = e.pointerType || 'mouse';
            resaltar(r);
            var p = puntoEnMapa(e);
            mostrarTip(r, p[0], p[1], false);
        });
        svg.addEventListener('pointermove', function (e) {
            if (e.pointerType === 'touch' || !E.tipRegion || E.armado) return;
            movEvento = e;
            if (!movRaf) movRaf = requestAnimationFrame(function () {
                movRaf = 0;
                if (!movEvento || !E.tipRegion) return;
                var p = puntoEnMapa(movEvento);       // primero se lee...
                moverTip(p[0], p[1], false);          // ...y después se escribe
            });
        }, { passive: true });
        svg.addEventListener('pointerout', function (e) {
            if (e.pointerType === 'touch' || E.armado) return;
            if (regionDe(e.relatedTarget)) return;
            ocultarTip();
            resaltar(E.foco);
        });
        svg.addEventListener('click', function (e) {
            var r = regionDe(e.target);
            if (!r) {
                if (E.armado) { E.armado = null; ocultarTip(); resaltar(null); }
                return;
            }
            if (E.puntero === 'touch' && E.armado !== r) {
                // Primer toque: solo muestra el resumen del lugar
                E.armado = r;
                resaltar(r);
                tipEnRegion(r);
                return;
            }
            E.armado = null;
            activar(r, false);
        });
        svg.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' && e.key !== ' ' && e.key !== 'Spacebar') return;
            var r = regionDe(e.target);
            if (!r) return;
            e.preventDefault();
            activar(r, true);
        });
        // Los oyentes de foco van en el contenedor HTML, no en el <svg>: en Chrome
        // un elemento SVG con oyentes de foco se vuelve enfocable (un Tab de más)
        el.lienzo.addEventListener('focusin', function (e) {
            var r = regionDe(e.target);
            if (!r) return;
            if (!focoVisible(e.target)) { E.foco = null; return; }   // con el mouse ya lo muestra el puntero
            E.foco = r;
            E.puntero = 'teclado';
            resaltar(r);
            tipEnRegion(r);
        });
        el.lienzo.addEventListener('focusout', function (e) {
            if (regionDe(e.relatedTarget)) return;
            E.foco = null;
            if (!E.armado) { ocultarTip(); resaltar(null); }
        });
        // Un toque fuera del mapa desarma el primer toque
        doc.addEventListener('pointerdown', function (e) {
            if (E.armado && !el.mapa.contains(e.target)) { E.armado = null; ocultarTip(); resaltar(null); }
        }, { passive: true });

        if ('ResizeObserver' in window) {
            new ResizeObserver(function () { alRedimensionar(); }).observe(el.lienzo);
        } else {
            window.addEventListener('resize', alRedimensionar);
        }
    }
    function alRedimensionar() {
        var w = el.lienzo.offsetWidth, hh = el.lienzo.offsetHeight;
        if (!w || !hh || (Math.abs(w - E.tam.w) < 1 && Math.abs(hh - E.tam.h) < 1)) return;
        E.tam = { w: w, h: hh };
        if (!svg) return;
        cancelAnimationFrame(animacion);
        raiz.classList.remove('mv-moviendo');
        ponerVista(vistaDe(E.objetivo, E.maxZoom));
        ocultarTip();
        colocarCapa();
    }

    // ── Navegación ───────────────────────────────────────────────────
    function activar(r, teclado) {
        if (!E.cargado) return;
        if (r.tipo === 'mun') { elegirMun(r.dane); return; }
        if (E.nivel === 2 && E.dep === r.dane) {
            if (E.mun) volverAlDepartamento();
            return;
        }
        entrarDep(r.dane, teclado);
    }

    function tabDeptos(nivel1, activo) {
        Object.keys(deptos).forEach(function (k) {
            deptos[k].el.setAttribute('tabindex', nivel1 || (activo && k === activo) ? '0' : '-1');
        });
    }

    function entrarDep(dane, teclado) {
        var d = deptos[dane];
        if (!d || !E.cargado) return;
        var pedido = ++E.pedido;
        var volverTeniaFoco = doc.activeElement === el.volver;
        ocultarTip();
        resaltar(null);
        E.armado = null;
        if (E.dep && deptos[E.dep]) deptos[E.dep].el.classList.remove('mv-activo');
        E.nivel = 2; E.dep = dane; E.mun = null; E.geo = null;
        munis = {};
        gMunis.textContent = '';
        leyenda(E.maxPais);
        pSel.setAttribute('d', '');
        pCasa.setAttribute('d', '');
        raiz.classList.add('mv-nivel2');
        raiz.classList.remove('mv-con-munis');
        raiz.classList.toggle('mv-en-88', dane === '88');
        d.el.classList.add('mv-activo');
        tabDeptos(false, dane);
        el.volver.hidden = false;
        svg.setAttribute('aria-label', 'Mapa de ' + d.nombre);
        pintarMigas();
        marcarRapido(dane);
        panelCargando(d.nombre, dane === TOLIMA);

        var tieneGeo = !!(E.pais && E.pais.geo.indexOf(d.slug) >= 0);
        E.objetivo = d.b;
        E.maxZoom = tieneGeo ? 14 : 6;
        irA(vistaDe(d.b, E.maxZoom), function () { if (pedido === E.pedido) rehacerCapa(); });
        if (teclado || volverTeniaFoco) enfocar(el.volver);

        Promise.all([
            tieneGeo ? cargarGeo(d.slug).catch(function () { return null; }) : Promise.resolve(null),
            cargarDep(dane)
        ]).then(function (res) {
            if (pedido !== E.pedido) return;      // ya se fue a otro lugar
            var geo = res[0], datos = res[1];
            if (geo) pintarMunis(geo, datos);
            panelLugar({ nombre: d.nombre, resumen: datos.depto, noticias: datos.noticias, casa: dane === TOLIMA });
            anunciar(d.nombre + ': ' + plural(datos.depto.n30, 'noticia', 'noticias') + ' en los últimos 30 días.'
                + (geo ? ' ' + geo.m.length + ' municipios en el mapa.' : ''));
            if (!raiz.classList.contains('mv-moviendo')) rehacerCapa();
        }).catch(function () {
            if (pedido === E.pedido) panelError();
        });
    }

    function pintarMunis(geo, datos) {
        E.geo = geo;
        var cuenta = datos.munis || {}, max = 0, frag = doc.createDocumentFragment();
        Object.keys(cuenta).forEach(function (k) { if (cuenta[k].n > max) max = cuenta[k].n; });
        geo.m.forEach(function (m) {
            var p = svgEl('path', { 'class': 'mv-muni', d: m.d, 'data-dane': m.c, role: 'button', tabindex: '0' });
            var r = { el: p, tipo: 'mun', dane: m.c, nombre: m.n, l: m.l, b: m.b, datos: cuenta[m.c] || VACIO };
            p.style.fill = colorPara(r.datos.n, max);
            p.setAttribute('aria-label', etiquetaAria(m.n, r.datos, false));
            munis[m.c] = r;
            frag.appendChild(p);
        });
        gMunis.appendChild(frag);
        E.maxMunis = max;
        leyenda(max);
        tabDeptos(false, null);
        raiz.classList.add('mv-con-munis');
        if (E.dep === TOLIMA && geo.contorno) pCasa.setAttribute('d', geo.contorno);
        svg.setAttribute('aria-label', 'Municipios de ' + (deptos[E.dep] ? deptos[E.dep].nombre : ''));
    }

    function elegirMun(c) {
        var m = munis[c];
        if (!m || !E.dep) return;
        E.mun = c;
        pSel.setAttribute('d', m.el.getAttribute('d'));
        pintarMigas();
        marcarRapido(null);
        panelCargando(m.nombre, false);
        var pedido = E.pedido, dep = E.dep;
        cargarMun(dep, c).then(function (datos) {
            if (pedido !== E.pedido || E.mun !== c) return;
            panelLugar({ nombre: m.nombre, sub: deptos[dep].nombre, resumen: datos.muni, noticias: datos.noticias, casa: false });
            anunciar(m.nombre + ': ' + plural(datos.muni.n30, 'noticia', 'noticias') + ' en los últimos 30 días.');
        }).catch(function () {
            if (pedido === E.pedido && E.mun === c) panelError();
        });
    }

    function volverAlDepartamento() {
        if (E.nivel !== 2) return;
        E.mun = null;
        pSel.setAttribute('d', '');
        pintarMigas();
        marcarRapido(E.dep);
        var d = deptos[E.dep], datos = E.cacheDep[E.dep];
        if (datos) panelLugar({ nombre: d.nombre, resumen: datos.depto, noticias: datos.noticias, casa: E.dep === TOLIMA });
        anunciar(d.nombre + '.');
    }

    function volverPais(conFoco) {
        if (E.nivel === 1) return;
        var dane = E.dep;
        ++E.pedido;
        ocultarTip();
        resaltar(null);
        E.armado = null;
        E.nivel = 1; E.dep = null; E.mun = null; E.geo = null;
        munis = {};
        gMunis.textContent = '';
        leyenda(E.maxPais);
        pSel.setAttribute('d', '');
        raiz.classList.remove('mv-nivel2', 'mv-con-munis', 'mv-en-88');
        if (deptos[dane]) deptos[dane].el.classList.remove('mv-activo');
        if (deptos[TOLIMA]) pCasa.setAttribute('d', deptos[TOLIMA].el.getAttribute('d'));
        tabDeptos(true);
        var volverTeniaFoco = doc.activeElement === el.volver;
        el.volver.hidden = true;
        svg.setAttribute('aria-label', 'Mapa de Colombia por departamentos');
        pintarMigas();
        marcarRapido('pais');
        panelPais();
        E.objetivo = null;
        irA(vistaDe(null), rehacerCapa);
        anunciar('Mapa de Colombia.');
        if ((conFoco || volverTeniaFoco) && deptos[dane]) enfocar(deptos[dane].el);
    }

    function pintarMigas() {
        var ol = el.migas;
        ol.textContent = '';
        var items = [{ txt: 'Colombia', ir: E.nivel > 1 ? 'pais' : '' }];
        if (E.nivel > 1 && deptos[E.dep]) items.push({ txt: deptos[E.dep].nombre, ir: E.mun ? 'dep' : '' });
        if (E.mun && munis[E.mun]) items.push({ txt: munis[E.mun].nombre, ir: '' });
        items.forEach(function (it) {
            var li = h('li');
            if (it.ir) {
                var b = h('button', '', it.txt);
                b.type = 'button';
                b.setAttribute('data-miga', it.ir);
                li.appendChild(b);
            } else {
                var s = h('span', '', it.txt);
                s.setAttribute('aria-current', 'location');
                li.appendChild(s);
            }
            ol.appendChild(li);
        });
    }

    function marcarRapido(valor) {
        Array.prototype.forEach.call(el.rapidos.querySelectorAll('button[data-ir]'), function (b) {
            b.setAttribute('aria-pressed', b.getAttribute('data-ir') === valor ? 'true' : 'false');
        });
    }

    // ── Panel ────────────────────────────────────────────────────────
    function recibirPais(d) {
        E.pais = d;
        E.ahora = d.ahora;
        E.recibido = Date.now();
        E.cacheDep = {}; E.cacheMun = {};
        if (!d.geo) d.geo = [];
        var max = 0;
        Object.keys(d.deptos || {}).forEach(function (k) { if (d.deptos[k].n > max) max = d.deptos[k].n; });
        Object.keys(deptos).forEach(function (k) {
            var dep = deptos[k];
            dep.datos = (d.deptos && d.deptos[k]) || VACIO;
            dep.el.style.fill = colorPara(dep.datos.n, max);
            dep.el.setAttribute('aria-label', etiquetaAria(dep.nombre, dep.datos, k === TOLIMA));
        });
        E.maxPais = max;
        leyenda(E.nivel === 2 && E.geo ? E.maxMunis : max);
        pintarResumen(d.resumen);
        pintarPulso(d.pulso || []);
        if (E.nivel === 1) panelPais();
        pintarMigas();
        rehacerCapa();
    }

    // Máximo de la escala que se está viendo (país o municipios del departamento)
    function leyenda(max) {
        if (el.leyMax) el.leyMax.textContent = max >= 1 ? String(max) : '—';
    }

    function pintarResumen(r) {
        el.resumen.textContent = '';
        if (!r || !r.n30) return;
        var datos = [[r.n30, r.n30 === 1 ? 'noticia en 30 días' : 'noticias en 30 días'],
                     [r.lugares, r.lugares === 1 ? 'departamento' : 'departamentos']];
        if (r.h24) datos.push([r.h24, r.h24 === 1 ? 'nueva en 24 h' : 'nuevas en 24 h']);
        datos.forEach(function (x) {
            var li = h('li');
            li.appendChild(h('b', '', String(x[0])));
            li.appendChild(doc.createTextNode(x[1]));
            el.resumen.appendChild(li);
        });
    }

    function panelPais() {
        var p = E.pais;
        el.panelTitulo.textContent = 'Lo último del país';
        el.badge.hidden = true;
        var r = p ? p.resumen : null;
        el.panelMeta.textContent = r && r.n30
            ? plural(r.n30, 'noticia', 'noticias') + ' con lugar en los últimos 30 días'
            : 'Las noticias con ubicación aparecen aquí y en el mapa.';
        pintarLista(p ? p.pulso.slice(0, 5) : [], 'Todavía no hay noticias ubicadas en el mapa. Cuando las publiquemos, las verás aquí.');
    }
    function panelLugar(o) {
        el.panelTitulo.textContent = o.nombre;
        el.badge.hidden = !o.casa;
        var r = o.resumen || { n30: 0, t: 0 }, partes = [];
        if (o.sub) partes.push(o.sub);
        partes.push(r.n30 ? plural(r.n30, 'noticia', 'noticias') + ' en 30 días' : 'Sin noticias en los últimos 30 días');
        if (r.t > r.n30) partes.push(r.t + ' en total');
        el.panelMeta.textContent = partes.join(' · ');
        pintarLista(o.noticias, 'Todavía no hay noticias ubicadas en ' + o.nombre + '.');
    }
    function panelCargando(nombre, casa) {
        el.panelTitulo.textContent = nombre;
        el.badge.hidden = !casa;
        el.panelMeta.textContent = 'Cargando noticias…';
        el.lista.setAttribute('aria-busy', 'true');
    }
    function panelError() {
        el.lista.removeAttribute('aria-busy');
        el.lista.textContent = '';
        el.panelMeta.textContent = 'No pudimos cargar las noticias de este lugar. Vuelve a intentarlo en un momento.';
    }

    function pintarLista(lista, vacio) {
        el.lista.removeAttribute('aria-busy');
        el.lista.textContent = '';
        if (!lista || !lista.length) {
            var li0 = h('li');
            li0.appendChild(h('p', 'mv-vacio-txt', vacio));
            el.lista.appendChild(li0);
            return;
        }
        lista.forEach(function (n) {
            noticias[n.id] = n;
            var li = h('li'), b = h('button', 'mv-nota');
            b.type = 'button';
            b.setAttribute('data-noticia', n.id);
            b.setAttribute('aria-label', n.t + '. ' + lugarDe(n) + ', ' + hace(n.ts));
            var img = h('img', 'mv-nota-img');
            img.alt = '';
            img.width = 64; img.height = 64;
            img.loading = 'lazy';
            img.decoding = 'async';
            img.addEventListener('error', function () {
                if (img.getAttribute('src') !== 'img/placeholder.svg') img.src = 'img/placeholder.svg';
            });
            img.src = n.i || 'img/placeholder.svg';
            var txt = h('span', 'mv-nota-txt');
            var cat = h('span', 'mv-cat', n.k || 'Noticia');
            cat.style.setProperty('--c', n.col);
            txt.appendChild(cat);
            txt.appendChild(h('span', 'mv-nota-titulo', n.t));
            txt.appendChild(h('span', 'mv-nota-meta', lugarDe(n) + ' · ' + hace(n.ts)));
            b.appendChild(img);
            b.appendChild(txt);
            li.appendChild(b);
            el.lista.appendChild(li);
        });
    }

    function pintarPulso(lista) {
        el.pulso.textContent = '';
        if (!lista.length) {
            var li0 = h('li');
            li0.appendChild(h('p', 'mv-pulso-vacio', 'Aún no hay noticias con lugar en el mapa.'));
            el.pulso.appendChild(li0);
            return;
        }
        lista.forEach(function (n) {
            noticias[n.id] = n;
            var li = h('li'), b = h('button', 'mv-pulso-item');
            b.type = 'button';
            b.setAttribute('data-noticia', n.id);
            b.setAttribute('data-dep', n.d);
            if (n.m) b.setAttribute('data-mun', n.m);
            b.setAttribute('aria-label', n.t + '. ' + (n.k ? n.k + ', ' : '') + lugarDe(n) + ', ' + hace(n.ts));
            b.style.setProperty('--c', n.col);
            var t = h('time', 'mv-pulso-hora', horaCorta(n.ts));
            t.setAttribute('datetime', new Date(n.ts * 1000).toISOString());
            b.appendChild(t);
            var cat = h('span', 'mv-cat', n.k || 'Noticia');
            cat.style.setProperty('--c', n.col);
            b.appendChild(cat);
            var lugar = h('span', 'mv-pulso-lugar'), ico = h('i', 'fas fa-location-dot');
            ico.setAttribute('aria-hidden', 'true');
            lugar.appendChild(ico);
            lugar.appendChild(doc.createTextNode(lugarDe(n)));
            b.appendChild(lugar);
            b.appendChild(h('span', 'mv-pulso-titulo-n', n.t));
            li.appendChild(b);
            el.pulso.appendChild(li);
        });
    }

    function abrirNoticia(id) {
        var n = noticias[id];
        if (!n) return;
        if (typeof window.abrirModal === 'function') window.abrirModal(n.t, n.i, n.c, n.v, n.id);
        else location.href = 'index.php?id=' + encodeURIComponent(n.id);
    }

    // Pulso: al pasar sobre una noticia se ilumina su lugar en el mapa
    var iluminado = null;
    function iluminar(b) {
        if (!E.cargado || iluminado === b) return;
        apagar();
        var dep = b.getAttribute('data-dep'), mun = b.getAttribute('data-mun');
        var r = (E.nivel === 2 && E.dep === dep && mun && munis[mun]) ? munis[mun] : deptos[dep];
        if (!r) return;
        iluminado = b;
        b.classList.add('mv-iluminado');
        resaltar(r);
        if (!raiz.classList.contains('mv-moviendo')) {
            var p = nuevoPunto(r.l[0], r.l[1], true);
            p.setAttribute('data-ping', '1');
            colocarCapa();
        }
    }
    function apagar() {
        if (!iluminado) return;
        iluminado.classList.remove('mv-iluminado');
        iluminado = null;
        resaltar(E.foco);
        capaItems = capaItems.filter(function (it) {
            if (it.el.getAttribute('data-ping')) { it.el.remove(); return false; }
            return true;
        });
    }

    function conectarPanel() {
        el.rapidos.addEventListener('click', function (e) {
            var b = e.target.closest('button[data-ir]');
            if (!b || !E.cargado) return;
            var ir = b.getAttribute('data-ir');
            if (ir === 'pais') volverPais(false);
            else if (E.nivel === 2 && E.dep === ir) { if (E.mun) volverAlDepartamento(); }
            else entrarDep(ir, false);
        });
        el.migas.addEventListener('click', function (e) {
            var b = e.target.closest('button[data-miga]');
            if (!b) return;
            if (b.getAttribute('data-miga') === 'pais') volverPais(true);
            else volverAlDepartamento();
        });
        el.volver.addEventListener('click', function () { volverPais(true); });
        el.lista.addEventListener('click', function (e) {
            var b = e.target.closest('[data-noticia]');
            if (b) abrirNoticia(b.getAttribute('data-noticia'));
        });
        el.pulso.addEventListener('click', function (e) {
            var b = e.target.closest('[data-noticia]');
            if (b) abrirNoticia(b.getAttribute('data-noticia'));
        });
        el.pulso.addEventListener('pointerover', function (e) {
            if (e.pointerType === 'touch') return;
            var b = e.target.closest('.mv-pulso-item');
            if (b) iluminar(b);
        });
        el.pulso.addEventListener('pointerout', function (e) {
            var b = e.target.closest('.mv-pulso-item');
            if (b && !b.contains(e.relatedTarget) && doc.activeElement !== b) apagar();
        });
        el.pulso.addEventListener('focusin', function (e) {
            var b = e.target.closest('.mv-pulso-item');
            if (b) iluminar(b);
        });
        el.pulso.addEventListener('focusout', function (e) {
            if (!el.pulso.contains(e.relatedTarget)) apagar();
        });
        if (el.saltar) el.saltar.addEventListener('click', function (e) {
            e.preventDefault();
            enfocar(el.panelTitulo);
            try { el.panelTitulo.scrollIntoView({ block: 'nearest', behavior: mqReduce.matches ? 'auto' : 'smooth' }); } catch (err) { /* nada */ }
        });
        if (el.reintentar) el.reintentar.addEventListener('click', reintentar);

        // Escape: del municipio al departamento, y del departamento a Colombia
        raiz.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape' || !E.cargado) return;
            if (E.armado) { E.armado = null; ocultarTip(); resaltar(null); return; }
            if (E.nivel !== 2) return;
            if (E.mun) {
                var m = munis[E.mun];
                volverAlDepartamento();
                if (m && raiz.contains(doc.activeElement) && doc.activeElement !== doc.body) enfocar(m.el);
            } else {
                volverPais(true);
            }
        });
    }

    iniciar();

    // Para depurar desde la consola: PlecMapa.ir('73'), PlecMapa.volver()
    window.PlecMapa = {
        ir: function (dane) { entrarDep(String(dane), false); },
        volver: function () { volverPais(false); },
        estado: function () { return { nivel: E.nivel, dep: E.dep, mun: E.mun, cargado: E.cargado }; }
    };
})();
