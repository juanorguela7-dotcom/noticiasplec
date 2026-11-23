/* ════════════════════════════════════════════════════════════════════
   NOTICIAS PLEC — EL BÚHO GUÍA (Etapa 4, sin dependencias)
   · Un búho propio de PLEC, dibujado en SVG: plumaje azul noche, ojos
     ámbar, pañuelo rojo y un periódico enrollado como percha.
   · Vive: parpadea solo (a veces dos veces), ladea la cabeza, mira al
     puntero, respira, aletea y mueve el pico mientras habla.
   · Da la bienvenida: llega volando, saluda con el ala y dice el texto
     de bienvenida en un globo, línea por línea, como una máquina de
     escribir. "Siguiente" completa la línea o pasa a la otra; "Saltar"
     (o Escape) lo despide. → con el teclado también avanza.
   · Voz opcional con SpeechSynthesis: un interruptor en el globo; la
     elección se guarda en localStorage ("plec-buho-voz"). Los navegadores
     no dejan hablar antes del primer clic: si la voz quedó activa de otra
     visita, empieza a hablar con el primer clic o tecla.
   · Con "reducir movimiento" no vuela, no aletea ni ladea la cabeza:
     aparece y se va con un fundido. Parpadeo, pico y texto siguen.

   El texto lo pone index.php (JSON en #buhoBienvenida); index.php decide
   cuándo mostrarlo (una vez por sesión) y lo pide con PlecBuho.bienvenida().

   Etapa 5 — el búho se queda: al terminar la bienvenida (o al saltarla) no
   se va volando, sino que baja a su rincón (abajo a la izquierda) y se
   queda ahí, vivo: parpadea, mira al puntero y respira. Desde ahí comenta
   cosas cortas en un globo pequeño que no tapa la página ni roba el foco
   (PlecBuho.comentar), que se cierra solo y se puede cerrar con × o Escape.
   Se puede minimizar: se esconde detrás del borde y solo asoman las orejas
   y los ojos (se recuerda en localStorage "plec-buho-dock"); minimizado no
   habla. Si se abre otro diálogo grande, vuela del rincón al diálogo y al
   terminar vuelve. Tocarlo emite buho:tocar (la guía abre "Pregúntale al
   búho"). Quién decide qué decir: assets/js/plec-buho-guia.js.

   Eventos (se emiten en document):
     buho:abrir     { lineas }                  empieza un diálogo
     buho:linea     { indice, total, texto }    el búho empieza una línea
     buho:cerrar    { motivo: 'fin' | 'saltar' }
     buho:anclado   { minimizado }              llegó a su rincón
     buho:tocar     {}                          tocaron al búho en su rincón
     buho:minimizar { minimizado }
     buho:comentar  { texto, tipo }             dijo algo en su rincón
   API: window.PlecBuho
     .bienvenida({ alCerrar, anclar })   el diálogo de bienvenida (anclar: al final va a su rincón)
     .decir(lineas, { final, fecha, marca, lema, alCerrar, anclar })   cualquier diálogo
     .siguiente()  .cerrar()  .abierto()  .voz(true|false)  .estado()
     .anclar()     lo lleva a su rincón (llega volando desde abajo)
     .comentar(texto, { acciones: [{ texto, alElegir }], duracion, tipo, aunMinimizado })
     .callar()     cierra el globo del rincón
     .minimizar(true|false)  .anclado()  .minimizado()
   ════════════════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    var doc = document, html = doc.documentElement;
    var mqReduce = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;
    var hayWAAPI = typeof Element !== 'undefined' && 'animate' in Element.prototype;
    var synth = window.speechSynthesis;
    var hayVoz = !!(synth && window.SpeechSynthesisUtterance);
    var CLAVE_VOZ = 'plec-buho-voz';
    var CLAVE_DOCK = 'plec-buho-dock';
    var VEL = 30;                       // ms por letra de la máquina de escribir
    var PAUSAS = { '.': 320, '!': 320, '?': 320, '…': 320, ',': 160, ';': 180, ':': 180, '—': 160, '\n': 240 };

    function reduce() { return !!(mqReduce && mqReduce.matches); }
    function puedeAnimar() { return hayWAAPI && !reduce(); }
    function nada() { /* nada */ }
    function azar(a, b) { return a + Math.random() * (b - a); }
    function leer(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }
    function guardar(k, v) { try { localStorage.setItem(k, v); } catch (e) { /* modo privado */ } }
    function emitir(nombre, detalle) {
        try { doc.dispatchEvent(new CustomEvent(nombre, { detail: detalle })); } catch (e) { /* navegador viejo */ }
    }
    function terminado(anim) { return anim ? anim.finished.then(nada, nada) : Promise.resolve(); }

    /* ── EL DIBUJO (viewBox 200 × 250) ─────────────────────────────── */
    function marcasPecho() {
        // Plumas del pecho: filas de "v" pequeñas
        var filas = [[150, [88, 100, 112]], [164, [82, 94, 106, 118]], [178, [88, 100, 112]], [192, [82, 94, 106, 118]], [205, [94, 106]]];
        var d = '';
        filas.forEach(function (f) {
            f[1].forEach(function (x) { d += 'M' + (x - 4) + ' ' + (f[0] - 2) + 'L' + x + ' ' + (f[0] + 2.5) + 'L' + (x + 4) + ' ' + (f[0] - 2); });
        });
        return d;
    }
    function ojo(cx, lado) {
        return '<g class="buho-ojo">' +
            '<circle cx="' + cx + '" cy="78" r="17" fill="url(#plecBuhoIris)" stroke="#18213f" stroke-width="3"/>' +
            '<g class="buho-pupila">' +
                '<circle cx="' + cx + '" cy="78" r="8.5" fill="#0f1530"/>' +
                '<circle cx="' + (cx - 4) + '" cy="73" r="3.6" fill="#fff"/>' +
                '<circle cx="' + (cx + 5) + '" cy="83" r="1.6" fill="#fff" opacity=".8"/>' +
            '</g>' +
            '<g clip-path="url(#plecBuhoOjo' + lado + ')">' +
                '<circle class="buho-parpado" cx="' + cx + '" cy="78" r="19" fill="#2b3a6c" stroke="#18213f" stroke-width="2"/>' +
            '</g>' +
        '</g>';
    }
    var SVG =
        '<svg class="buho-svg" viewBox="0 0 200 250" aria-hidden="true" focusable="false">' +
            '<defs>' +
                '<radialGradient id="plecBuhoIris" cx="50%" cy="42%" r="58%">' +
                    '<stop offset="0" stop-color="#ffe07a"/><stop offset=".6" stop-color="#f6b021"/><stop offset="1" stop-color="#d47a0c"/>' +
                '</radialGradient>' +
                '<linearGradient id="plecBuhoPluma" x1="0" y1="0" x2="0" y2="1">' +
                    '<stop offset="0" stop-color="#34447a"/><stop offset="1" stop-color="#1c2649"/>' +
                '</linearGradient>' +
                '<clipPath id="plecBuhoOjoI"><circle cx="78" cy="78" r="18.6"/></clipPath>' +
                '<clipPath id="plecBuhoOjoD"><circle cx="122" cy="78" r="18.6"/></clipPath>' +
            '</defs>' +
            // La percha: un periódico enrollado
            '<g class="buho-periodico">' +
                '<rect x="30" y="222" width="140" height="20" rx="10" fill="#f4eddd" stroke="#c9bc9f" stroke-width="1.5"/>' +
                '<path d="M42 229h26M42 235h18M134 229h22M140 235h16" stroke="#b9ad93" stroke-width="1.6" stroke-linecap="round"/>' +
                '<text x="100" y="236.5" text-anchor="middle" font-family="\'Playfair Display\', Georgia, serif" font-weight="900" font-size="12" fill="#e53935">PLEC</text>' +
                '<ellipse cx="163" cy="232" rx="5" ry="9" fill="#e9dfc8" stroke="#c9bc9f" stroke-width="1.3"/>' +
                '<path d="M163 229a2.6 3.4 0 1 1-1.6 4.4" fill="none" stroke="#b9ad93" stroke-width="1.2"/>' +
            '</g>' +
            '<g class="buho-ave">' +
                // Patas (asoman bajo el cuerpo y agarran el periódico)
                '<path d="M76 214L69 231M76 214L76 234M76 214L83 231M124 214L117 231M124 214L124 234M124 214L131 231" stroke="#f0a03a" stroke-width="5" stroke-linecap="round" fill="none"/>' +
                // Cuerpo, pecho y sus plumas
                '<path d="M100 96C136 96 156 128 156 166C156 202 132 224 100 224C68 224 44 202 44 166C44 128 64 96 100 96Z" fill="url(#plecBuhoPluma)"/>' +
                '<path d="M100 122C123 122 137 147 137 175C137 202 121 218 100 218C79 218 63 202 63 175C63 147 77 122 100 122Z" fill="#f2e7cf"/>' +
                '<path d="' + marcasPecho() + '" stroke="#c8ad7f" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" fill="none"/>' +
                // Alas (giran desde el hombro)
                '<g class="buho-ala buho-ala-i">' +
                    '<path d="M60 128C41 140 33 172 39 204C42 215 52 216 56 207C63 187 68 152 60 128Z" fill="#1a2446"/>' +
                    '<path d="M49 168Q45 186 46 202M55 158Q52 180 52 200" stroke="#3b4d86" stroke-width="2" stroke-linecap="round" fill="none"/>' +
                '</g>' +
                '<g class="buho-ala buho-ala-d">' +
                    '<path d="M140 128C159 140 167 172 161 204C158 215 148 216 144 207C137 187 132 152 140 128Z" fill="#1a2446"/>' +
                    '<path d="M151 168Q155 186 154 202M145 158Q148 180 148 200" stroke="#3b4d86" stroke-width="2" stroke-linecap="round" fill="none"/>' +
                '</g>' +
                // Cabeza (gira desde el cuello)
                '<g class="buho-cabeza">' +
                    // Penachos de plumas (dos puntas cada uno)
                    '<path d="M48 54Q36 34 27 13Q38 19 45 26Q44 14 47 5Q62 20 77 31Z M152 54Q164 34 173 13Q162 19 155 26Q156 14 153 5Q138 20 123 31Z" fill="#26335f"/>' +
                    '<path d="M53 45Q45 32 39 22Q46 26 50 31Q50 22 52 16Q60 26 68 33Z M147 45Q155 32 161 22Q154 26 150 31Q150 22 148 16Q140 26 132 33Z" fill="#4a5c98"/>' +
                    '<ellipse cx="100" cy="76" rx="62" ry="52" fill="url(#plecBuhoPluma)"/>' +
                    '<circle cx="88" cy="38" r="2" fill="#4a5c98"/><circle cx="100" cy="34" r="2" fill="#4a5c98"/><circle cx="112" cy="38" r="2" fill="#4a5c98"/>' +
                    '<path d="M100 60C90 46 56 44 52 74C49 100 74 112 100 104C126 112 151 100 148 74C144 44 110 46 100 60Z" fill="#e3e9f6"/>' +
                    '<circle cx="78" cy="78" r="23" fill="none" stroke="#c6d1ea" stroke-width="2"/>' +
                    '<circle cx="122" cy="78" r="23" fill="none" stroke="#c6d1ea" stroke-width="2"/>' +
                    ojo(78, 'I') + ojo(122, 'D') +
                    '<g class="buho-pico">' +
                        '<ellipse class="buho-boca" cx="100" cy="98" rx="5.5" ry="3.5" fill="#6e2418"/>' +
                        '<path class="buho-pico-inf" d="M94 95Q100 97 106 95Q104 104 100 108Q96 104 94 95Z" fill="#d77b1c"/>' +
                        '<path class="buho-pico-sup" d="M90.5 87.5Q100 81 109.5 87.5Q107 98 100 106Q93 98 90.5 87.5Z" fill="#f59e2f" stroke="#c9701a" stroke-width="1"/>' +
                        '<path d="M96 88Q100 86 104 88" stroke="#ffd08a" stroke-width="1.6" stroke-linecap="round" fill="none"/>' +
                    '</g>' +
                '</g>' +
                // Pañuelo rojo de reportero (queda en el cuello: no gira con la cabeza)
                '<g class="buho-panuelo">' +
                    '<path d="M64 113Q100 138 136 113L138 121Q100 146 62 121Z" fill="#e53935"/>' +
                    '<path d="M85 127L100 160L115 127Z" fill="#ff4d4d"/>' +
                    '<path d="M86 128L100 160" stroke="#c62828" stroke-width="1.5" fill="none"/>' +
                    '<circle cx="100" cy="130" r="5" fill="#c62828"/>' +
                '</g>' +
            '</g>' +
        '</svg>';

    var ICONO_VOZ = '<svg class="buho-voz-icono" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' +
        '<path d="M4 9h4l5-4v14l-5-4H4z" fill="currentColor"/>' +
        '<path d="M16.5 8.5a5 5 0 0 1 0 7M19 6a8.5 8.5 0 0 1 0 12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';

    /* ── ESTADO ────────────────────────────────────────────────────── */
    var esc = null;               // nodos de la escena (se arma la primera vez)
    var guion = null;             // { lineas, final, alCerrar }
    var indice = -1;
    var estado = 'cerrado';       // 'entrando' | 'hablando' | 'saliendo' | 'cerrado'
    var turno = 0;                // cambia al abrir/cerrar: invalida esperas viejas
    var entrada = null;           // animaciones de la llegada (para adelantarlas)
    var salida = null;
    var focoPrevio = null, overflowPrevio = '';
    var tipeo = { timer: 0, texto: '', i: 0, activo: false, pausa: false, id: 0, destino: null };
    var pico = { timer: 0, abierto: false };
    var voz = { activa: hayVoz && leer(CLAVE_VOZ) === '1', hablando: false, pendiente: false, u: null, elegida: null,
                texto: '', vigente: null };
    var vida = { parpadeo: 0, ladeo: 0, enderezar: 0, miradaLibre: 0, raf: 0, px: 0, py: 0, puntero: false };
    // El rincón (Etapa 5)
    var dock = null;              // nodos del rincón (se arman la primera vez)
    var anclado = false;          // el búho vive en su rincón cuando no hay diálogo
    var minimizado = leer(CLAVE_DOCK) === 'min';
    var dockTurno = 0;            // invalida llegadas viejas
    var vueloDock = null;         // animación de llegada al rincón
    var nota = { id: 0, texto: '', visible: false, timer: 0, op: null, pendiente: null };

    /* ── LA ESCENA ─────────────────────────────────────────────────── */
    function crearEscena() {
        var raiz = doc.createElement('div');
        raiz.className = 'buho-escena';
        raiz.id = 'plecBuho';
        raiz.hidden = true;
        raiz.innerHTML =
            '<div class="buho-fondo"></div>' +
            '<div class="buho-dialogo" role="dialog" aria-modal="true" aria-labelledby="plecBuhoMarca">' +
                '<div class="buho-globo">' +
                    '<div class="buho-globo-cab">' +
                        '<div class="buho-marca" id="plecBuhoMarca"><strong></strong><small></small></div>' +
                        '<button type="button" class="buho-voz" role="switch" aria-checked="false" title="Que el búho lea en voz alta">' +
                            ICONO_VOZ + '<span class="buho-voz-txt">Voz</span>' +
                            '<span class="buho-voz-pista" aria-hidden="true"><span class="buho-voz-perilla"></span></span>' +
                        '</button>' +
                    '</div>' +
                    '<p class="buho-linea">' +
                        '<span class="buho-linea-fantasma" aria-hidden="true"></span>' +
                        '<span class="buho-linea-texto" aria-hidden="true"></span>' +
                    '</p>' +
                    '<p class="buho-sr" aria-live="polite"></p>' +
                    '<div class="buho-fecha"></div>' +
                    '<div class="buho-pie">' +
                        '<div class="buho-puntos" aria-hidden="true"></div>' +
                        '<button type="button" class="buho-btn buho-saltar">Saltar</button>' +
                        '<button type="button" class="buho-btn buho-siguiente"><span class="buho-siguiente-txt">Siguiente</span></button>' +
                    '</div>' +
                '</div>' +
                '<div class="buho-figura">' +
                    '<div class="buho-sombra"></div>' +
                    '<div class="buho-vuelo">' + SVG + '</div>' +
                '</div>' +
            '</div>';
        doc.body.appendChild(raiz);

        function $(s) { return raiz.querySelector(s); }
        esc = {
            raiz: raiz, dialogo: $('.buho-dialogo'), globo: $('.buho-globo'),
            marca: $('.buho-marca strong'), lema: $('.buho-marca small'),
            voz: $('.buho-voz'), linea: $('.buho-linea'), fantasma: $('.buho-linea-fantasma'),
            texto: $('.buho-linea-texto'), sr: $('.buho-sr'), fecha: $('.buho-fecha'),
            puntos: $('.buho-puntos'), saltar: $('.buho-saltar'), siguiente: $('.buho-siguiente'),
            siguienteTxt: $('.buho-siguiente-txt'),
            figura: $('.buho-figura'), vuelo: $('.buho-vuelo'), svg: $('.buho-svg'),
            alaI: $('.buho-ala-i'), alaD: $('.buho-ala-d'), cabeza: $('.buho-cabeza'),
            parpados: Array.prototype.slice.call(raiz.querySelectorAll('.buho-parpado')),
            pupilas: Array.prototype.slice.call(raiz.querySelectorAll('.buho-pupila'))
        };

        if (!hayVoz) esc.voz.hidden = true;
        pintarVoz();

        esc.siguiente.addEventListener('click', siguiente);
        esc.saltar.addEventListener('click', function () { cerrar('saltar'); });
        esc.voz.addEventListener('click', alternarVoz);
        // Clic en el texto mientras se escribe: lo completa
        esc.linea.addEventListener('click', function () { if (tipeo.activo) completar(); });
        // Clic en el búho: aletea y ladea la cabeza
        esc.figura.addEventListener('click', function () {
            if (estado !== 'hablando') return;
            aletear(2, 230);
            ladear(Math.random() < .5 ? -12 : 12, 900);
            parpadear();
        });
        // La primera interacción permite hablar (si la voz quedó activa de antes)
        raiz.addEventListener('pointerdown', primeraInteraccion, true);
        raiz.addEventListener('pointermove', alMoverPuntero, { passive: true });
        raiz.addEventListener('pointerleave', function () { vida.puntero = false; mirarAlGlobo(); });
    }

    function partirMarca(m) {
        // "Noticias PLEC" → "Noticias " + <span>PLEC</span>
        var i = m.lastIndexOf(' ');
        esc.marca.textContent = '';
        esc.marca.appendChild(doc.createTextNode(i > 0 ? m.slice(0, i + 1) : m));
        if (i > 0) {
            var s = doc.createElement('span');
            s.textContent = m.slice(i + 1);
            esc.marca.appendChild(s);
        }
    }

    /* ── ABRIR Y CERRAR ────────────────────────────────────────────── */
    function decir(lineas, op) {
        lineas = (lineas || []).map(function (l) { return String(l == null ? '' : l).trim(); }).filter(Boolean);
        if (!lineas.length) return false;
        op = op || {};
        if (!esc) crearEscena();
        if (estado !== 'cerrado') terminar();
        // Si estaba en su rincón, vuela de ahí al diálogo
        var desdeRincon = null;
        if (dock && esc.figura.parentNode === dock.percha) {
            desdeRincon = dock.raiz.hidden ? null : esc.vuelo.getBoundingClientRect();
            salirDelRincon();
        }

        // Quedarse en el rincón al terminar vale también para los diálogos que vengan después
        if (op.anclar) anclado = true;
        var t = ++turno;
        guion = { lineas: lineas, final: op.final || 'Entendido', alCerrar: op.alCerrar, anclar: !!op.anclar };
        partirMarca(op.marca || 'Noticias PLEC');
        esc.lema.textContent = op.lema || '';
        esc.lema.hidden = !op.lema;
        esc.fecha.textContent = op.fecha || '';
        esc.fecha.hidden = !op.fecha;
        esc.puntos.innerHTML = lineas.length > 1 ? new Array(lineas.length + 1).join('<span></span>') : '';
        esc.texto.textContent = esc.fantasma.textContent = '';

        focoPrevio = doc.activeElement;
        overflowPrevio = doc.body.style.overflow;
        doc.body.style.overflow = 'hidden';
        esc.raiz.hidden = false;
        void esc.raiz.offsetWidth;                // que el fondo entre con transición
        esc.raiz.classList.add('is-visible');
        estado = 'entrando';
        doc.addEventListener('keydown', alTecla, true);
        try { esc.siguiente.focus({ preventScroll: true }); } catch (e) { esc.siguiente.focus(); }
        emitir('buho:abrir', { lineas: lineas.length });

        entrar(t, desdeRincon).then(function () {
            if (t !== turno) return;
            estado = 'hablando';
            iniciarVida();
            mostrarLinea(0);
            saludar();
        });
        return true;
    }

    // Recorrido de un vuelo desde el rectángulo "desde" hasta donde está ahora .buho-vuelo:
    // un arco hacia arriba, con un pequeño balanceo al llegar
    function framesDesde(desde) {
        var r = esc.vuelo.getBoundingClientRect();
        if (!desde || !r.width) return null;
        var dx = desde.left + desde.width / 2 - (r.left + r.width / 2);
        var dy = desde.top + desde.height / 2 - (r.top + r.height / 2);
        var s = desde.width / r.width;
        var arco = Math.min(140, 40 + Math.abs(dx) * 0.12);
        var tr = function (x, y, giro, esc2) {
            return 'translate(' + x.toFixed(1) + 'px,' + y.toFixed(1) + 'px) rotate(' + giro + 'deg) scale(' + esc2.toFixed(3) + ')';
        };
        return [
            { transform: tr(dx, dy, 0, s) },
            { transform: tr(dx * 0.45, dy * 0.45 - arco, dx > 0 ? -10 : 10, 1 + (s - 1) * 0.4), offset: 0.5 },
            { transform: tr(0, -14, dx > 0 ? 4 : -4, 1), offset: 0.86 },
            { transform: 'none' }
        ];
    }

    // Llega volando desde arriba a la derecha (o desde su rincón), aleteando, y se posa
    function entrar(t, desde) {
        if (!puedeAnimar()) { posar(); return Promise.resolve(); }
        var alas = aletear(Infinity, 210, 100);
        var vuelo = esc.vuelo.animate(framesDesde(desde) || [
            { transform: 'translate(62vw, -80vh) rotate(-14deg) scale(.5)', opacity: 0 },
            { opacity: 1, offset: .12 },
            { transform: 'translate(20vw, -30vh) rotate(9deg) scale(.82)', opacity: 1, offset: .55 },
            { transform: 'translate(0, -16px) rotate(-3deg) scale(1)', opacity: 1, offset: .86 },
            { transform: 'none', opacity: 1 }
        ], { duration: desde ? 1100 : 1350, easing: 'cubic-bezier(.3, .7, .4, 1)' });
        entrada = { anims: [vuelo], alas: alas, apurar: false };

        return terminado(vuelo).then(function () {
            if (t !== turno) return null;
            frenarAleteo(alas);
            posar();
            if (!entrada || entrada.apurar) return null;
            // Al posarse se aplasta un poco y rebota
            var golpe = esc.svg.animate([
                { transform: 'scale(1.1, .86)' },
                { transform: 'scale(.96, 1.05)', offset: .45 },
                { transform: 'none' }
            ], { duration: 420, easing: 'ease-out' });
            entrada.anims.push(golpe);
            return terminado(golpe);
        }).then(function () { if (t === turno) entrada = null; });
    }
    function posar() { esc.raiz.classList.add('is-posado', 'is-globo'); olvidarOjos(); }
    function adelantarEntrada() {
        if (!entrada) return;
        entrada.apurar = true;
        entrada.anims.forEach(function (a) { try { a.finish(); } catch (e) { a.cancel(); } });
    }

    function cerrar(motivo) {
        if (estado === 'cerrado' || estado === 'saliendo') return;
        var llegando = estado === 'entrando';
        var g = guion;
        var desde = esc.vuelo.getBoundingClientRect();      // dónde está ahora (también a mitad del vuelo)
        estado = 'saliendo';
        var t = ++turno;
        pararTipeo();
        callarVoz();
        pararVida();
        ponerPico(0);
        cancelarEntrada();
        esc.raiz.classList.remove('is-visible', 'is-globo', 'is-posado');

        var fin = function () { if (t === turno) terminar(); };
        if (anclado || (g && g.anclar)) {
            // Etapa 5: no se va; baja a su rincón mientras el diálogo se desvanece
            anclado = true;
            html.classList.add('buho-anclado');
            esc.raiz.classList.add('is-fundido');
            llegarAlRincon(desde && desde.width ? desde : null);
            setTimeout(fin, 300);
        } else if (puedeAnimar() && !llegando) {
            // Se despide: aletea y se va volando hacia arriba
            var alas = aletear(Infinity, 190, 100);
            var a = esc.vuelo.animate([
                { transform: 'none', opacity: 1 },
                { transform: 'translate(-2vw, -5vh) rotate(-5deg) scale(1.02)', opacity: 1, offset: .22 },
                { transform: 'translate(-34vw, -100vh) rotate(-16deg) scale(.5)', opacity: 0 }
            ], { duration: 950, easing: 'cubic-bezier(.45, 0, .75, .5)', fill: 'forwards' });
            salida = { anim: a, alas: alas };
            terminado(a).then(fin);
        } else {
            // Sin animación (o si aún venía volando): fundido
            esc.raiz.classList.add('is-fundido');
            setTimeout(fin, 280);
        }

        emitir('buho:cerrar', { motivo: motivo });
        if (g && typeof g.alCerrar === 'function') {
            try { g.alCerrar(motivo); } catch (e) { /* que no rompa el cierre */ }
        }
    }

    // Lo deja todo como antes de abrir, sin animación
    function terminar() {
        turno++;
        pararTipeo();
        callarVoz();
        pararVida();
        cancelarEntrada();
        if (salida) { salida.anim.cancel(); if (salida.alas) salida.alas.cancelar(); salida = null; }
        doc.removeEventListener('keydown', alTecla, true);
        esc.raiz.classList.remove('is-visible', 'is-globo', 'is-posado', 'is-fundido');
        esc.globo.classList.remove('is-escribiendo', 'is-nueva');
        esc.raiz.hidden = true;
        esc.cabeza.style.transform = '';
        ponerPico(0);
        if (estado !== 'cerrado') doc.body.style.overflow = overflowPrevio;
        estado = 'cerrado';
        indice = -1;
        guion = null;
        var f = focoPrevio;
        focoPrevio = null;
        if (f && f !== doc.body && f.focus && doc.contains(f)) {
            try { f.focus({ preventScroll: true }); } catch (e) { /* nada */ }
        }
        // Si ya está posado en su rincón (sin animación ya llegó), que siga vivo
        if (enRincon() && !vueloDock) iniciarVida();
    }
    function cancelarEntrada() {
        if (!entrada) return;
        entrada.anims.forEach(function (a) { a.cancel(); });
        if (entrada.alas) entrada.alas.cancelar();
        entrada = null;
    }

    /* ── EL DIÁLOGO ────────────────────────────────────────────────── */
    function mostrarLinea(i) {
        indice = i;
        var texto = guion.lineas[i];
        var ultima = i === guion.lineas.length - 1;
        // Lo corto (los titulares) va en letra de titular; el párrafo, en texto normal
        esc.linea.classList.toggle('is-titular', texto.length <= 90);
        esc.sr.textContent = texto.replace(/\s*\n\s*/g, ' ');
        esc.siguienteTxt.textContent = ultima ? guion.final : 'Siguiente';
        esc.siguiente.classList.toggle('is-final', ultima);
        Array.prototype.forEach.call(esc.puntos.children, function (p, k) {
            p.classList.toggle('activo', k === i);
            p.classList.toggle('visto', k < i);
        });
        if (i > 0 && !reduce()) {
            esc.globo.classList.remove('is-nueva');
            void esc.globo.offsetWidth;
            esc.globo.classList.add('is-nueva');
        }
        escribir(texto);
        hablar(texto);
        mirarAlGlobo();
        if (i > 0) ladear(i % 2 ? 9 : -9, 1000);
        emitir('buho:linea', { indice: i, total: guion.lineas.length, texto: texto });
    }

    function siguiente() {
        if (estado === 'entrando') { adelantarEntrada(); return; }
        if (estado !== 'hablando') return;
        if (tipeo.activo) { completar(); return; }
        if (indice < guion.lineas.length - 1) {
            aletear(1, 260);
            mostrarLinea(indice + 1);
        } else {
            cerrar('fin');
        }
    }

    /* ── MÁQUINA DE ESCRIBIR ───────────────────────────────────────── */
    // destino: { texto, fantasma, globo } (por defecto, el globo del diálogo)
    function escribir(texto, destino) {
        pararTipeo();
        destino = destino || { texto: esc.texto, fantasma: esc.fantasma, globo: esc.globo };
        var id = tipeo.id;
        tipeo.destino = destino;
        tipeo.texto = texto;
        tipeo.i = 0;
        tipeo.activo = true;
        tipeo.pausa = false;
        destino.fantasma.textContent = texto;     // reserva el alto de la línea completa: el globo no salta
        destino.texto.textContent = '';
        destino.globo.classList.add('is-escribiendo');
        animarPico();

        (function paso() {
            if (id !== tipeo.id || !tipeo.activo) return;
            var n = tipeo.i + 1;
            var c = texto.charCodeAt(n - 1);
            if (c >= 0xD800 && c <= 0xDBFF) n++;       // no partir un emoji en dos
            tipeo.i = n;
            destino.texto.textContent = texto.slice(0, n);
            if (n >= texto.length) { finTipeo(); return; }
            var ch = texto.charAt(n - 1);
            var espera = PAUSAS[ch] || 0;
            // La pausa de un punto o una coma solo cuenta si viene un espacio ("3.5" no para)
            if (espera && ch !== '\n' && !/\s/.test(texto.charAt(n))) espera = 0;
            tipeo.pausa = espera > 0;
            if (!tipeo.pausa) animarPico();
            tipeo.timer = setTimeout(paso, espera || VEL);
        })();
    }
    function finTipeo() {
        clearTimeout(tipeo.timer);
        tipeo.timer = 0;
        tipeo.activo = false;
        tipeo.pausa = false;
        var d = tipeo.destino;
        if (d) {
            d.texto.textContent = tipeo.texto;
            d.globo.classList.remove('is-escribiendo');
        }
    }
    function completar() { if (tipeo.activo) finTipeo(); }
    function pararTipeo() {
        clearTimeout(tipeo.timer);
        tipeo.timer = 0;
        tipeo.activo = false;
        tipeo.pausa = false;
        tipeo.id++;
        if (tipeo.destino) tipeo.destino.globo.classList.remove('is-escribiendo');
    }

    /* ── EL PICO: se abre y se cierra mientras escribe o habla ─────── */
    function hablaAlgo() { return (tipeo.activo && !tipeo.pausa) || voz.hablando; }
    function ponerPico(v) { if (esc) esc.svg.style.setProperty('--pico', v.toFixed(2)); }
    function animarPico() {
        if (pico.timer) return;
        (function tic() {
            if (!esc || !hablaAlgo()) { pico.timer = 0; pico.abierto = false; ponerPico(0); return; }
            pico.abierto = !pico.abierto;
            ponerPico(pico.abierto ? azar(.5, 1) : azar(0, .15));
            pico.timer = setTimeout(tic, pico.abierto ? azar(85, 145) : azar(60, 110));
        })();
    }

    /* ── VOZ (SpeechSynthesis) ─────────────────────────────────────── */
    function elegirVoz() {
        if (voz.elegida) return voz.elegida;
        var mejor = null, puntos = 0;
        (synth.getVoices() || []).forEach(function (v) {
            var l = String(v.lang || '').toLowerCase().replace('_', '-');
            if (l.indexOf('es') !== 0) return;
            var p = 1;
            if (l === 'es-co') p += 6;                                   // acento de aquí
            else if (l === 'es-419' || l === 'es-mx' || l === 'es-us') p += 4;
            else if (l !== 'es-es') p += 3;
            if (/natural|online|neural|google/i.test(v.name)) p += 2;     // las voces que suenan mejor
            if (p > puntos) { puntos = p; mejor = v; }
        });
        voz.elegida = mejor;
        return mejor;
    }
    if (hayVoz && synth.addEventListener) {
        synth.addEventListener('voiceschanged', function () { voz.elegida = null; });
    }

    // vigente(): ¿sigue en pantalla lo que se iba a decir? (por defecto, la línea actual del diálogo)
    function hablar(texto, vigente) {
        if (!voz.activa || !hayVoz || !texto) return;
        if (!vigente) {
            var t = turno, linea = indice;
            vigente = function () { return t === turno && linea === indice && estado === 'hablando'; };
        }
        voz.texto = texto;
        voz.vigente = vigente;
        // Sin un clic previo en la página el navegador no deja hablar: se espera
        if (navigator.userActivation && !navigator.userActivation.hasBeenActive) { voz.pendiente = true; return; }
        voz.pendiente = false;
        synth.cancel();
        voz.u = null;
        voz.hablando = false;
        // Chrome a veces se traga lo que se pide justo después de cancel()
        setTimeout(function () {
            if (!vigente() || !voz.activa) return;
            var u = new SpeechSynthesisUtterance(texto.replace(/\s*\n\s*/g, ' '));
            var v = elegirVoz();
            if (v) { u.voice = v; u.lang = v.lang; } else { u.lang = 'es-CO'; }
            u.rate = 1;
            u.pitch = 1.1;
            u.onstart = function () { if (voz.u === u) { voz.hablando = true; animarPico(); } };
            u.onend = function () { if (voz.u === u) { voz.hablando = false; voz.u = null; } };
            u.onerror = function (e) {
                if (voz.u !== u) return;
                voz.hablando = false;
                voz.u = null;
                if (e && e.error === 'not-allowed') voz.pendiente = true;
            };
            voz.u = u;
            synth.speak(u);
        }, 60);
    }
    function callarVoz() {
        var era = !!voz.u;
        voz.u = null;
        voz.hablando = false;
        voz.pendiente = false;
        if (era && hayVoz) synth.cancel();      // solo si hablaba el búho (no cortar otras lecturas)
    }
    function primeraInteraccion() {
        if (voz.pendiente && voz.activa && voz.vigente && voz.vigente()) hablar(voz.texto, voz.vigente);
    }
    function pintarVoz() {
        var etiqueta = voz.activa ? 'Voz del búho: activada' : 'Voz del búho: desactivada';
        [esc && esc.voz, dock && dock.voz].forEach(function (b) {
            if (!b) return;
            b.setAttribute('aria-checked', voz.activa ? 'true' : 'false');
            b.setAttribute('aria-label', etiqueta);
        });
        if (dock) dock.voz.title = voz.activa ? 'Silenciar al búho' : 'Que el búho lea en voz alta';
    }
    function alternarVoz() {
        voz.activa = !voz.activa;
        guardar(CLAVE_VOZ, voz.activa ? '1' : '0');
        pintarVoz();
        if (voz.activa) {
            if (estado === 'hablando' && guion) hablar(guion.lineas[indice]);
            else if (nota.visible) hablar(nota.texto, notaVigente(nota.id));
        }
        else callarVoz();
    }

    /* ── GESTOS ────────────────────────────────────────────────────── */
    // veces = Infinity para volar; grados = cuánto suben las alas
    function aletear(veces, ms, grados) {
        if (!puedeAnimar() || !esc) return null;
        grados = grados || 80;
        var op = { duration: ms || 240, iterations: veces || 1, easing: 'ease-in-out' };
        var ai = esc.alaI.animate([{ transform: 'rotate(0deg)' }, { transform: 'rotate(' + grados + 'deg)' }, { transform: 'rotate(0deg)' }], op);
        var ad = esc.alaD.animate([{ transform: 'rotate(0deg)' }, { transform: 'rotate(' + (-grados) + 'deg)' }, { transform: 'rotate(0deg)' }], op);
        return { anims: [ai, ad], cancelar: function () { ai.cancel(); ad.cancel(); } };
    }
    // Termina el aleteo al final del batido en curso (sin saltos)
    function frenarAleteo(alas) {
        if (!alas) return;
        alas.anims.forEach(function (a) {
            try {
                var vuelta = a.effect.getComputedTiming().currentIteration || 0;
                a.effect.updateTiming({ iterations: vuelta + 1 });
            } catch (e) { a.cancel(); }
        });
    }
    // Saluda con el ala derecha
    function saludar() {
        ladear(-10, 1300);
        if (!puedeAnimar()) return;
        esc.alaD.animate([
            { transform: 'rotate(0deg)' },
            { transform: 'rotate(-118deg)', offset: .22 },
            { transform: 'rotate(-96deg)', offset: .38 },
            { transform: 'rotate(-122deg)', offset: .54 },
            { transform: 'rotate(-96deg)', offset: .7 },
            { transform: 'rotate(-114deg)', offset: .82 },
            { transform: 'rotate(0deg)' }
        ], { duration: 1500, easing: 'ease-in-out' });
    }
    function ladear(grados, ms) {
        if (!esc || reduce()) return;
        esc.cabeza.style.transform = grados ? 'rotate(' + grados + 'deg)' : '';
        clearTimeout(vida.enderezar);
        if (grados && ms) vida.enderezar = setTimeout(function () { ladear(0); }, ms);
    }
    function parpadear() {
        if (!esc || !hayWAAPI) return;
        esc.parpados.forEach(function (p) {
            p.animate([
                { transform: 'scaleY(0)' },
                { transform: 'scaleY(1)', offset: .45 },
                { transform: 'scaleY(1)', offset: .6 },
                { transform: 'scaleY(0)' }
            ], { duration: 200, easing: 'ease-in-out' });
        });
    }

    /* ── LA MIRADA ─────────────────────────────────────────────────── */
    // Dónde están los ojos. Se mide una vez y se guarda un momento: el búho está fijo
    // en la pantalla y solo se mueve al volar, minimizarse o cambiar la ventana de
    // tamaño (entonces se olvida). Medirlo en cada movimiento del puntero obligaba al
    // navegador a recalcular la página en cada cuadro (y se notaba al arrastrar un estante).
    var ojos = null;
    function olvidarOjos() { ojos = null; }
    function centroOjos() {
        var ahora = Date.now();
        if (ojos && ahora - ojos.t < 1500) return ojos;
        var r = esc.svg.getBoundingClientRect();
        ojos = { x: r.left + r.width * .5, y: r.top + r.height * (78 / 250), escala: r.width / 200, t: ahora };
        return ojos;
    }
    window.addEventListener('resize', olvidarOjos);
    function mirarA(x, y) {
        var c = centroOjos();
        var dx = x - c.x, dy = y - c.y, d = Math.sqrt(dx * dx + dy * dy) || 1;
        var k = Math.min(3.6, d / (40 * c.escala)) / d;
        var tr = 'translate(' + (dx * k).toFixed(2) + 'px,' + (dy * k).toFixed(2) + 'px)';
        esc.pupilas.forEach(function (p) { p.style.transform = tr; });
    }
    // Mira su globo: el del diálogo, o el del rincón; en el rincón sin globo, al frente
    function mirarAlGlobo() {
        if (!esc || vida.puntero) return;
        var g = estado !== 'cerrado' ? esc.globo : (nota.visible && dock ? dock.globo : null);
        var r = g ? g.getBoundingClientRect() : null;
        if (r && r.width) mirarA(r.left + r.width * .5, r.top + r.height * .45);
        else esc.pupilas.forEach(function (p) { p.style.transform = ''; });
    }
    function alMoverPuntero(e) {
        if (estado !== 'hablando') return;
        seguirPuntero(e);
    }
    // En el rincón también sigue al puntero (por toda la página)
    function alMoverPunteroRincon(e) {
        if (!enRincon() || estado !== 'cerrado' || minimizado) return;
        seguirPuntero(e);
    }
    function seguirPuntero(e) {
        if (e.pointerType === 'touch') return;
        vida.px = e.clientX;
        vida.py = e.clientY;
        vida.puntero = true;
        clearTimeout(vida.miradaLibre);
        // Si el puntero se queda quieto un rato, vuelve a mirar su globo
        vida.miradaLibre = setTimeout(function () { vida.puntero = false; mirarAlGlobo(); }, 2200);
        if (!vida.raf) vida.raf = requestAnimationFrame(function () { vida.raf = 0; if (esc) mirarA(vida.px, vida.py); });
    }

    /* ── VIDA: parpadeo y ladeos sueltos ───────────────────────────── */
    function iniciarVida() {
        pararVida();
        (function programarParpadeo() {
            vida.parpadeo = setTimeout(function () {
                parpadear();
                if (Math.random() < .22) setTimeout(parpadear, 280);     // a veces, doble
                programarParpadeo();
            }, azar(2200, 5600));
        })();
        (function programarLadeo() {
            vida.ladeo = setTimeout(function () {
                if (!tipeo.activo) ladear((Math.random() < .5 ? -1 : 1) * azar(5, 10), 1200);
                programarLadeo();
            }, azar(5500, 9500));
        })();
    }
    function pararVida() {
        clearTimeout(vida.parpadeo);
        clearTimeout(vida.ladeo);
        clearTimeout(vida.enderezar);
        clearTimeout(vida.miradaLibre);
        if (vida.raf) cancelAnimationFrame(vida.raf);
        vida.raf = 0;
        vida.puntero = false;
        clearTimeout(pico.timer);
        pico.timer = 0;
    }

    /* ── TECLADO ───────────────────────────────────────────────────── */
    function alTecla(e) {
        if (estado === 'cerrado') return;
        if (e.key === 'Escape') {
            e.preventDefault();
            e.stopPropagation();
            cerrar('saltar');
        } else if (e.key === 'ArrowRight' && !e.altKey && !e.ctrlKey && !e.metaKey) {
            e.preventDefault();
            e.stopPropagation();
            siguiente();
        } else if (e.key === 'Tab') {
            // El foco no sale del globo mientras el búho habla
            var botones = [esc.voz, esc.saltar, esc.siguiente].filter(function (b) { return !b.hidden; });
            var i = botones.indexOf(doc.activeElement);
            var j = i < 0 ? 0 : (i + (e.shiftKey ? -1 : 1) + botones.length) % botones.length;
            e.preventDefault();
            botones[j].focus();
        } else {
            primeraInteraccion();
        }
    }

    /* ── EL RINCÓN (Etapa 5) ───────────────────────────────────────── */
    var ICONO_MIN = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 9l6 6 6-6" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';

    function enRincon() { return !!(dock && esc && esc.figura.parentNode === dock.percha); }

    function crearRincon() {
        var d = doc.createElement('div');
        d.className = 'buho-dock';
        d.id = 'plecBuhoDock';
        d.hidden = true;
        d.innerHTML =
            '<div class="buho-dock-globo" hidden>' +
                '<div class="buho-dock-cuerpo">' +
                    '<p class="buho-linea buho-dock-linea">' +
                        '<span class="buho-linea-fantasma" aria-hidden="true"></span>' +
                        '<span class="buho-linea-texto" aria-hidden="true"></span>' +
                    '</p>' +
                    '<div class="buho-dock-acciones" hidden></div>' +
                '</div>' +
                '<div class="buho-dock-controles">' +
                    '<button type="button" class="buho-dock-icono buho-dock-x" aria-label="Cerrar el mensaje del búho" title="Cerrar">' +
                        '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg></button>' +
                    '<button type="button" class="buho-dock-icono buho-dock-voz" role="switch" aria-checked="false">' + ICONO_VOZ + '</button>' +
                '</div>' +
            '</div>' +
            '<p class="buho-sr buho-dock-sr" aria-live="polite"></p>' +
            '<div class="buho-dock-percha"></div>' +
            '<button type="button" class="buho-dock-tocar" aria-label="Pregúntale al búho"></button>' +
            '<button type="button" class="buho-dock-min" aria-label="Minimizar al búho" title="Minimizar">' + ICONO_MIN + '</button>';
        doc.body.appendChild(d);

        function $(s) { return d.querySelector(s); }
        dock = {
            raiz: d, globo: $('.buho-dock-globo'), linea: $('.buho-dock-linea'),
            texto: $('.buho-dock-linea .buho-linea-texto'), fantasma: $('.buho-dock-linea .buho-linea-fantasma'),
            acciones: $('.buho-dock-acciones'), x: $('.buho-dock-x'), voz: $('.buho-dock-voz'),
            sr: $('.buho-dock-sr'), percha: $('.buho-dock-percha'), tocar: $('.buho-dock-tocar'), min: $('.buho-dock-min')
        };
        if (!hayVoz) dock.voz.hidden = true;
        pintarVoz();
        pintarMin();

        dock.tocar.addEventListener('click', function () {
            if (minimizado) { minimizar(false); return; }
            if (vueloDock) return;
            aletear(2, 230);
            ladear(Math.random() < .5 ? -12 : 12, 900);
            parpadear();
            emitir('buho:tocar', {});
        });
        dock.min.addEventListener('click', function () {
            minimizar(true);
            try { dock.tocar.focus({ preventScroll: true }); } catch (e) { /* nada */ }
        });
        dock.x.addEventListener('click', function () {
            cerrarNota();
            try { dock.tocar.focus({ preventScroll: true }); } catch (e) { /* nada */ }
        });
        dock.voz.addEventListener('click', alternarVoz);
        dock.linea.addEventListener('click', function () { if (tipeo.activo && tipeo.destino && tipeo.destino.globo === dock.globo) completar(); });
        // Mientras el lector está encima del globo (o tiene el foco), no se cierra solo
        dock.globo.addEventListener('pointerenter', function () { clearTimeout(nota.timer); });
        dock.globo.addEventListener('pointerleave', function () { if (nota.visible) programarFinNota(3000); });
        dock.globo.addEventListener('focusin', function () { clearTimeout(nota.timer); });
        dock.globo.addEventListener('focusout', function (e) {
            if (nota.visible && !dock.globo.contains(e.relatedTarget)) programarFinNota(3000);
        });
        d.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && nota.visible) {
                e.preventDefault();
                e.stopPropagation();
                cerrarNota();
                try { dock.tocar.focus({ preventScroll: true }); } catch (err) { /* nada */ }
            }
        });
        doc.addEventListener('pointermove', alMoverPunteroRincon, { passive: true });
        // La primera interacción con la página permite hablar (si la voz quedó activa de antes)
        doc.addEventListener('pointerdown', function () { if (estado === 'cerrado') primeraInteraccion(); }, true);
        doc.addEventListener('keydown', function () { if (estado === 'cerrado') primeraInteraccion(); }, true);
    }

    function pintarMin() {
        if (!dock) return;
        dock.raiz.classList.toggle('is-min', minimizado);
        dock.tocar.setAttribute('aria-label', minimizado ? 'Mostrar al búho' : 'Pregúntale al búho');
        dock.tocar.title = minimizado ? 'Mostrar al búho' : 'Pregúntale al búho';
        dock.min.hidden = minimizado;
    }

    // Lo lleva a su rincón. Si hay un diálogo abierto, irá al cerrarlo.
    function anclar() {
        if (!esc) crearEscena();
        if (!dock) crearRincon();
        anclado = true;
        html.classList.add('buho-anclado');
        if (estado !== 'cerrado' || enRincon()) return true;
        llegarAlRincon(null);
        return true;
    }

    // Pone la figura en el rincón; si puede, llega volando desde "desde" (o subiendo desde abajo)
    function llegarAlRincon(desde) {
        if (!dock) crearRincon();
        var t = ++dockTurno;
        if (vueloDock) { vueloDock.anim.cancel(); if (vueloDock.alas) vueloDock.alas.cancelar(); vueloDock = null; }
        dock.raiz.hidden = false;
        dock.percha.appendChild(esc.figura);
        esc.pupilas.forEach(function (p) { p.style.transform = ''; });
        esc.cabeza.style.transform = '';
        pintarMin();
        void dock.raiz.offsetWidth;
        dock.raiz.classList.add('is-visible');

        var fin = function () {
            if (t !== dockTurno) return;
            vueloDock = null;
            dock.raiz.classList.remove('is-llegando');
            dock.raiz.classList.add('is-posado');
            olvidarOjos();
            iniciarVida();
            mirarAlGlobo();
            emitir('buho:anclado', { minimizado: minimizado });
            if (nota.pendiente) { var p = nota.pendiente; nota.pendiente = null; p(); }
        };
        if (!puedeAnimar()) { fin(); return; }

        // Mientras vuela pasa por encima del diálogo que se desvanece
        dock.raiz.classList.add('is-llegando');
        dock.raiz.classList.remove('is-posado');
        var frames = framesDesde(desde) || [
            { transform: 'translate(-24px, 150%) rotate(-12deg) scale(.7)', opacity: 0 },
            { opacity: 1, offset: .25 },
            { transform: 'translate(0, -12px) rotate(4deg) scale(1)', opacity: 1, offset: .8 },
            { transform: 'none', opacity: 1 }
        ];
        var alas = aletear(Infinity, 200, 95);
        var a = esc.vuelo.animate(frames, { duration: desde ? 1100 : 900, easing: 'cubic-bezier(.35, .7, .4, 1)' });
        vueloDock = { anim: a, alas: alas };
        terminado(a).then(function () {
            if (t !== dockTurno) return;
            frenarAleteo(alas);
            if (puedeAnimar()) {
                esc.svg.animate([
                    { transform: 'scale(1.1, .86)' },
                    { transform: 'scale(.96, 1.05)', offset: .45 },
                    { transform: 'none' }
                ], { duration: 380, easing: 'ease-out' });
            }
            fin();
        });
    }

    // Sale del rincón para ir a un diálogo grande
    function salirDelRincon() {
        dockTurno++;
        if (vueloDock) { vueloDock.anim.cancel(); if (vueloDock.alas) vueloDock.alas.cancelar(); vueloDock = null; }
        cerrarNota(true);
        pararVida();
        esc.dialogo.appendChild(esc.figura);
        olvidarOjos();
        dock.raiz.classList.remove('is-posado', 'is-visible', 'is-llegando');
        dock.raiz.hidden = true;
    }

    function minimizar(si) {
        si = !!si;
        if (si === minimizado) return minimizado;
        minimizado = si;
        olvidarOjos();
        guardar(CLAVE_DOCK, si ? 'min' : '');
        if (dock) {
            if (si) { cerrarNota(true); nota.pendiente = null; vida.puntero = false; mirarAlGlobo(); }
            pintarMin();
            if (!si && enRincon() && estado === 'cerrado') {
                aletear(2, 220);
                ladear(-10, 900);
                parpadear();
            }
        }
        emitir('buho:minimizar', { minimizado: si });
        return minimizado;
    }

    /* El globo del rincón */
    function notaVigente(id) {
        return function () { return nota.id === id && nota.visible && estado === 'cerrado'; };
    }
    function duracionNota(texto, op) {
        var ms = 4500 + texto.length * 60;
        if (op.acciones && op.acciones.length) ms += 5000;
        return Math.min(ms, 22000);
    }
    function programarFinNota(ms) {
        clearTimeout(nota.timer);
        var id = nota.id;
        nota.timer = setTimeout(function revisar() {
            if (id !== nota.id || !nota.visible) return;
            // No se corta mientras escribe o lee en voz alta
            if (tipeo.activo || voz.hablando) { nota.timer = setTimeout(revisar, 900); return; }
            cerrarNota();
        }, ms);
    }

    // Dice algo corto desde el rincón. Devuelve false si no puede (minimizado, en un diálogo…)
    function comentar(texto, op) {
        op = op || {};
        texto = String(texto == null ? '' : texto).trim();
        if (!texto || !anclado || estado !== 'cerrado') return false;
        if (!esc) crearEscena();
        if (!dock) crearRincon();
        if (minimizado && !op.aunMinimizado) return false;
        if (minimizado) minimizar(false);
        if (vueloDock || !enRincon()) {           // todavía está llegando: lo dice al posarse
            nota.pendiente = function () { comentar(texto, op); };
            return true;
        }
        var id = ++nota.id;
        clearTimeout(nota.timer);
        nota.texto = texto;
        nota.visible = true;
        nota.op = op;

        dock.acciones.textContent = '';
        (op.acciones || []).forEach(function (a) {
            if (!a || !a.texto) return;
            var b = doc.createElement('button');
            b.type = 'button';
            b.className = 'buho-dock-accion';
            b.textContent = a.texto;
            b.addEventListener('click', function () {
                var seguir = typeof a.alElegir === 'function' ? a.alElegir() : undefined;
                if (seguir !== false && nota.id === id) cerrarNota();
            });
            dock.acciones.appendChild(b);
        });
        dock.acciones.hidden = !dock.acciones.children.length;
        dock.sr.textContent = '';
        dock.sr.textContent = texto;
        dock.globo.hidden = false;
        void dock.globo.offsetWidth;
        dock.raiz.classList.add('is-globo');

        escribir(texto, { texto: dock.texto, fantasma: dock.fantasma, globo: dock.globo });
        hablar(texto, notaVigente(id));
        aletear(1, 240);
        ladear(Math.random() < .5 ? -8 : 8, 900);
        vida.puntero = false;
        mirarAlGlobo();
        programarFinNota(op.duracion || duracionNota(texto, op));
        emitir('buho:comentar', { texto: texto, tipo: op.tipo || '' });
        return true;
    }

    function cerrarNota(rapido) {
        clearTimeout(nota.timer);
        if (!nota.visible || !dock) return;
        nota.visible = false;
        nota.id++;
        if (tipeo.destino && tipeo.destino.globo === dock.globo) { pararTipeo(); ponerPico(0); }
        if (voz.u) callarVoz();
        dock.raiz.classList.remove('is-globo');
        var g = dock.globo;
        if (g.contains(doc.activeElement)) {
            try { dock.tocar.focus({ preventScroll: true }); } catch (e) { /* nada */ }
        }
        if (rapido || reduce()) g.hidden = true;
        else setTimeout(function () { if (!nota.visible) g.hidden = true; }, 260);
        mirarAlGlobo();
    }

    /* ── API ───────────────────────────────────────────────────────── */
    function bienvenida(op) {
        var el = doc.getElementById('buhoBienvenida');
        if (!el) return false;
        var d;
        try { d = JSON.parse(el.textContent); } catch (e) { return false; }
        if (!d || !d.lineas) return false;
        return decir(d.lineas, {
            final: d.final, fecha: d.fecha, marca: d.marca, lema: d.lema,
            alCerrar: op && op.alCerrar, anclar: !!(op && op.anclar)
        });
    }

    window.PlecBuho = {
        bienvenida: bienvenida,
        decir: decir,
        siguiente: siguiente,
        cerrar: function () { cerrar('saltar'); },
        abierto: function () { return estado === 'entrando' || estado === 'hablando'; },
        voz: function (si) {
            if (typeof si === 'boolean' && si !== voz.activa) alternarVoz();
            return voz.activa;
        },
        estado: function () {
            return {
                estado: estado, linea: indice, total: guion ? guion.lineas.length : 0,
                escribiendo: tipeo.activo, voz: voz.activa, hablando: voz.hablando,
                vozElegida: hayVoz && voz.activa && elegirVoz() ? elegirVoz().name : null,
                movimiento: puedeAnimar(),
                anclado: anclado, enRincon: enRincon(), minimizado: minimizado, volando: !!vueloDock,
                globo: nota.visible ? nota.texto : null
            };
        },
        anclar: anclar,
        comentar: comentar,
        callar: function () { cerrarNota(); },
        minimizar: minimizar,
        anclado: function () { return anclado; },
        minimizado: function () { return minimizado; }
    };
})();
