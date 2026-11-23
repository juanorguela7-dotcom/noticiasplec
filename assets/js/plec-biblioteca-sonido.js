/* ════════════════════════════════════════════════════════════════════
   NOTICIAS PLEC — BIBLIOTECA INTERACTIVA: SONIDO (Etapa 3)
   Todos los sonidos se generan en el momento con la Web Audio API
   (osciladores y ruido filtrado): no se descarga ningún archivo de audio.

   · Deslizar un estante: un roce que crece con la velocidad, un "tic"
     suave cada vez que un libro pasa por el centro y un golpe sordo si
     el estante llega con fuerza al borde.
   · Pasar sobre un libro (mouse, o teclado al enfocarlo): un toque de
     madera; la nota cambia un poco de un libro al siguiente.
   · Abrir un libro: el vuelo, la tapa y cada hoja suenan al compás de la
     animación (marcas de biblio:animacion) y, cuando aparece la noticia,
     una campanita con la nota de la sección. Al cerrar, lo mismo al revés.
     Sin animación (movimiento reducido) suena una versión corta.
   · Cambiar de sección: una nota. Cada sección tiene la suya, en escala
     pentatónica, así cualquier recorrido suena afinado.

   · Nada suena hasta el primer clic del usuario en la página (lo exigen
     los navegadores y así nada sorprende al entrar).
   · Botón flotante para silenciar y control de volumen. Se guardan en
     localStorage (clave "plec-biblio-sonido") y se sincronizan entre
     pestañas.

   Solo escucha los eventos biblio:* de plec-biblioteca.js; si la
   biblioteca no arrancó (portada clásica) no hace nada.
   Consola: PlecBiblioteca.sonido.estado(), .probar('tapa'), …
   ════════════════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    var doc = document, html = doc.documentElement;
    var AC = window.AudioContext || window.webkitAudioContext;
    var B = window.PlecBiblioteca;
    if (!AC || !B || !html.classList.contains('biblio-activa')) return;

    function clamp(v, a, b) { return v < a ? a : (v > b ? b : v); }

    /* ── NOTAS ─────────────────────────────────────────────────────── */
    // Pentatónica mayor de Sol desde G4: G A B D E, y luego una octava arriba
    var PENTA = [0, 2, 4, 7, 9];
    function frecuencia(i) {
        i = Math.max(0, i | 0) % 10;
        return 392 * Math.pow(2, (PENTA[i % 5] + 12 * Math.floor(i / 5)) / 12);
    }

    /* ── MOTOR: LOS SONIDOS ────────────────────────────────────────── */
    // Funciona con cualquier contexto de audio (también OfflineAudioContext, para pruebas)
    function Motor(ctx, destino) {
        this.ctx = ctx;
        this.destino = destino;
        // 2 s de ruido blanco: la base de roces, hojas y golpes
        var largo = Math.floor(ctx.sampleRate * 2);
        var buf = ctx.createBuffer(1, largo, ctx.sampleRate), d = buf.getChannelData(0);
        for (var i = 0; i < largo; i++) d[i] = Math.random() * 2 - 1;
        this.ruido = buf;
    }
    var M = Motor.prototype;

    // Sube en línea recta hasta "pico" y se apaga de forma exponencial
    M.envolvente = function (param, t, pico, ataque, caida) {
        param.setValueAtTime(0.0001, t);
        param.linearRampToValueAtTime(Math.max(pico, 0.0002), t + ataque);
        param.exponentialRampToValueAtTime(0.0001, t + ataque + caida);
    };
    // Un oscilador: { t, f, f2 (deslizar hasta), glide, tipo, pico, ataque, caida, dest }
    M.tono = function (o) {
        var c = this.ctx, t = o.t, ataque = o.ataque || 0.003;
        var osc = c.createOscillator(), g = c.createGain();
        osc.type = o.tipo || 'sine';
        osc.frequency.setValueAtTime(o.f, t);
        if (o.f2) osc.frequency.exponentialRampToValueAtTime(o.f2, t + (o.glide || o.caida));
        this.envolvente(g.gain, t, o.pico, ataque, o.caida);
        osc.connect(g);
        g.connect(o.dest || this.destino);
        osc.start(t);
        osc.stop(t + ataque + o.caida + 0.05);
    };
    // Ruido filtrado: { t, filtro, f, f2 (barrer hasta), barrido, q, pico, ataque, caida, dest }
    M.soplo = function (o) {
        var c = this.ctx, t = o.t;
        var src = c.createBufferSource(), fil = c.createBiquadFilter(), g = c.createGain();
        src.buffer = this.ruido;
        fil.type = o.filtro || 'bandpass';
        fil.frequency.setValueAtTime(o.f, t);
        if (o.f2) fil.frequency.exponentialRampToValueAtTime(o.f2, t + (o.barrido || o.ataque + o.caida));
        fil.Q.value = o.q || 1;
        this.envolvente(g.gain, t, o.pico, o.ataque, o.caida);
        src.connect(fil);
        fil.connect(g);
        g.connect(o.dest || this.destino);
        src.start(t, Math.random() * 1.2);
        src.stop(t + o.ataque + o.caida + 0.05);
    };

    // Toque de madera al pasar sobre un libro (paso: 0-4, la nota de ese libro)
    M.toque = function (t, paso, dest) {
        var f = 640 * Math.pow(2, PENTA[(paso | 0) % 5] / 12);
        this.tono({ t: t, f: f * 1.5, f2: f, glide: 0.018, ataque: 0.002, caida: 0.075, pico: 0.1, dest: dest });
        this.tono({ t: t, f: f * 3.9, ataque: 0.001, caida: 0.022, pico: 0.018, dest: dest });
        this.soplo({ t: t, filtro: 'bandpass', f: 2600, q: 1.2, ataque: 0.001, caida: 0.018, pico: 0.08, dest: dest });
    };
    // "Tic" de un libro que pasa por el centro del estante (fuerza 0-1)
    M.tic = function (t, fuerza, alterno, dest) {
        this.tono({ t: t, f: alterno ? 2350 : 2050, tipo: 'triangle', ataque: 0.001, caida: 0.02, pico: 0.05 * fuerza, dest: dest });
        this.soplo({ t: t, filtro: 'highpass', f: 4500, q: 0.7, ataque: 0.001, caida: 0.012, pico: 0.09 * fuerza, dest: dest });
    };
    // Golpe sordo: el estante llega al borde
    M.golpe = function (t, fuerza, dest) {
        this.tono({ t: t, f: 110, f2: 62, glide: 0.11, ataque: 0.003, caida: 0.16, pico: 0.3 * fuerza, dest: dest });
        this.soplo({ t: t, filtro: 'lowpass', f: 520, q: 0.8, ataque: 0.002, caida: 0.06, pico: 0.3 * fuerza, dest: dest });
    };
    // Una hoja que pasa (dura "dur" s, como en la animación) y se asienta
    M.hoja = function (t, dur, variante, fuerza, dest) {
        var v = 0.9 + 0.2 * ((variante * 0.618) % 1);
        this.soplo({ t: t, filtro: 'bandpass', f: 1300 * v, f2: 3400 * v, barrido: dur * 0.6, q: 0.9,
                     ataque: dur * 0.42, caida: dur * 0.5, pico: 0.17 * fuerza, dest: dest });
        this.soplo({ t: t + dur * 0.84, filtro: 'bandpass', f: 1800 * v, q: 1.1, ataque: 0.002, caida: 0.035, pico: 0.12 * fuerza, dest: dest });
    };
    // La tapa dura al asentarse (cierra: más grave y más fuerte)
    M.asentar = function (t, cierra, dest) {
        this.tono({ t: t, f: cierra ? 135 : 160, f2: cierra ? 66 : 80, glide: 0.12, ataque: 0.003,
                    caida: cierra ? 0.24 : 0.18, pico: cierra ? 0.38 : 0.3, dest: dest });
        this.soplo({ t: t, filtro: 'lowpass', f: cierra ? 650 : 800, q: 0.7, ataque: 0.002, caida: 0.07, pico: cierra ? 0.3 : 0.26, dest: dest });
    };
    // La tapa gira (un soplo que barre) y se asienta cerca del final del giro
    M.tapa = function (t, dur, cierra, dest) {
        this.soplo({ t: t, filtro: 'bandpass', f: cierra ? 1600 : 500, f2: cierra ? 500 : 1600, barrido: dur * 0.8, q: 0.7,
                     ataque: dur * 0.5, caida: dur * 0.42, pico: 0.14, dest: dest });
        this.asentar(t + dur * 0.82, cierra, dest);
    };
    // Soplo del libro que vuela (sube: hacia el centro; si no, de vuelta al estante)
    M.vuelo = function (t, dur, sube, pico, dest) {
        this.soplo({ t: t, filtro: 'bandpass', f: sube ? 320 : 1500, f2: sube ? 1500 : 320, barrido: dur, q: 0.6,
                     ataque: dur * 0.55, caida: dur * 0.6, pico: pico, dest: dest });
    };
    // Nota de una sección (como una kalimba)
    M.nota = function (t, indice, fuerza, dest) {
        var f = frecuencia(indice);
        this.tono({ t: t, f: f, ataque: 0.004, caida: 0.6, pico: 0.115 * fuerza, dest: dest });
        this.tono({ t: t, f: f * 2, tipo: 'triangle', ataque: 0.002, caida: 0.18, pico: 0.035 * fuerza, dest: dest });
        this.tono({ t: t, f: f * 3.01, ataque: 0.001, caida: 0.07, pico: 0.02 * fuerza, dest: dest });
    };
    // Campanita al aparecer la noticia: la nota de la sección y su quinta
    M.campana = function (t, indice, dest) {
        var f = frecuencia(Math.max(0, indice | 0) % 5) * 2;
        this.tono({ t: t, f: f, ataque: 0.003, caida: 1.0, pico: 0.09, dest: dest });
        this.tono({ t: t, f: f * 2.76, ataque: 0.002, caida: 0.35, pico: 0.015, dest: dest });
        this.tono({ t: t + 0.08, f: f * 1.5, ataque: 0.003, caida: 0.9, pico: 0.06, dest: dest });
    };

    // Marcas de biblio:animacion → segundos. Devuelve null si algo no cuadra.
    function tramo(par) {
        return par && typeof par[0] === 'number' && typeof par[1] === 'number' && par[1] > par[0] && par[1] - par[0] < 3000
            ? [par[0] / 1000, (par[1] - par[0]) / 1000] : null;
    }
    // Apertura completa, al compás de la animación
    M.abrirLibro = function (t, m, nota, dest) {
        var vuelo = tramo(m.vuelo), tapa = tramo(m.tapa);
        if (!vuelo || !tapa || !m.hojas || typeof m.revela !== 'number') return false;
        this.vuelo(t + vuelo[0], vuelo[1], true, 0.15, dest);
        this.tapa(t + tapa[0], tapa[1], false, dest);
        for (var k = 0; k < m.hojas.length; k++) {
            var h = tramo(m.hojas[k]);
            if (h) this.hoja(t + h[0], h[1], k, 0.75 - k * 0.1, dest);
        }
        this.campana(t + m.revela / 1000 - 0.03, nota, dest);
        return true;
    };
    // Cierre completo: la página se encoge, las hojas y la tapa vuelven, el libro vuela al estante
    M.cerrarLibro = function (t, m, dest) {
        var encoge = tramo(m.encoge), tapa = tramo(m.tapa), vuelo = tramo(m.vuelo);
        if (!encoge || !tapa || !vuelo || !m.hojas) return false;
        this.vuelo(t + encoge[0], encoge[1], false, 0.06, dest);
        for (var k = 0; k < m.hojas.length; k++) {
            var h = tramo(m.hojas[k]);
            if (h) this.hoja(t + h[0], h[1], m.hojas.length - k, 0.5 + k * 0.1, dest);
        }
        this.tapa(t + tapa[0], tapa[1], true, dest);
        this.vuelo(t + vuelo[0], vuelo[1], false, m.vuelve ? 0.12 : 0.07, dest);
        return true;
    };
    // Versiones cortas (sin animación)
    M.abrirCorto = function (t, nota, dest) {
        this.tapa(t, 0.24, false, dest);
        this.hoja(t + 0.07, 0.2, 0, 0.7, dest);
        this.hoja(t + 0.12, 0.2, 1, 0.6, dest);
        this.hoja(t + 0.17, 0.2, 2, 0.5, dest);
        this.campana(t + 0.34, nota, dest);
    };
    M.cerrarCorto = function (t, dest) {
        this.hoja(t, 0.16, 2, 0.5, dest);
        this.hoja(t + 0.04, 0.16, 1, 0.6, dest);
        this.tapa(t + 0.08, 0.22, true, dest);
    };

    // Roce continuo mientras se mueve un estante; se apaga solo si deja de recibir movimiento
    function Roce(motor) { this.m = motor; this.src = null; this.reposo = 0; this.apagar = 0; }
    Roce.prototype.mover = function (rapidez) {            // rapidez 0-1
        var c = this.m.ctx, ahora = c.currentTime, self = this;
        if (!this.src) {
            this.src = c.createBufferSource();
            this.src.buffer = this.m.ruido;
            this.src.loop = true;
            this.fil = c.createBiquadFilter();
            this.fil.type = 'bandpass';
            this.fil.Q.value = 0.8;
            this.fil.frequency.value = 600;
            this.g = c.createGain();
            this.g.gain.value = 0;
            this.src.connect(this.fil);
            this.fil.connect(this.g);
            this.g.connect(this.m.destino);
            this.src.start(ahora, Math.random() * 1.5);
        }
        clearTimeout(this.reposo);
        clearTimeout(this.apagar);
        this.g.gain.setTargetAtTime(0.13 * Math.pow(rapidez, 0.75), ahora, 0.03);
        this.fil.frequency.setTargetAtTime(450 + 2600 * rapidez, ahora, 0.05);
        this.reposo = setTimeout(function () { self.calmar(); }, 90);
    };
    Roce.prototype.calmar = function () {
        if (!this.src) return;
        var self = this;
        this.g.gain.setTargetAtTime(0, this.m.ctx.currentTime, 0.06);
        this.apagar = setTimeout(function () { self.parar(); }, 450);
    };
    Roce.prototype.parar = function () {
        clearTimeout(this.reposo);
        clearTimeout(this.apagar);
        if (!this.src) return;
        try { this.src.stop(); } catch (e) { /* ya parado */ }
        try { this.g.disconnect(); } catch (e) { /* nada */ }
        this.src = this.fil = this.g = null;
    };

    /* ── PREFERENCIAS (localStorage) ───────────────────────────────── */
    var CLAVE = 'plec-biblio-sonido';
    var VOL_INICIAL = 0.6;
    function leerPref() {
        var p = { volumen: VOL_INICIAL, silencio: false };
        try {
            var o = JSON.parse(window.localStorage.getItem(CLAVE) || 'null');
            if (o && typeof o === 'object') {
                if (typeof o.volumen === 'number' && isFinite(o.volumen)) p.volumen = clamp(o.volumen, 0, 1);
                if (typeof o.silencio === 'boolean') p.silencio = o.silencio;
            }
        } catch (e) { /* modo privado o almacenamiento bloqueado: valores por defecto */ }
        return p;
    }
    function guardarPref() {
        try {
            window.localStorage.setItem(CLAVE, JSON.stringify({ volumen: Math.round(pref.volumen * 100) / 100, silencio: pref.silencio }));
        } catch (e) { /* nada */ }
    }
    var pref = leerPref();

    /* ── CONTEXTO DE AUDIO ─────────────────────────────────────────── */
    var ctx = null, entrada = null, motor = null, roce = null;
    var desbloqueado = false;          // hubo un primer clic
    var dormir = 0;                    // temporizador para suspender el audio al silenciar

    function mudo() { return pref.silencio || pref.volumen <= 0; }
    function nivel() { return mudo() ? 0 : Math.pow(pref.volumen, 1.5); }

    function crearAudio() {
        if (ctx) return true;
        try {
            try { ctx = new AC({ latencyHint: 'interactive' }); } catch (e) { ctx = new AC(); }
            // Volumen → compresor suave (nada satura aunque suenen varias cosas juntas) → altavoces
            var comp = ctx.createDynamicsCompressor();
            comp.threshold.value = -14;
            comp.knee.value = 10;
            comp.ratio.value = 4;
            comp.attack.value = 0.003;
            comp.release.value = 0.2;
            entrada = ctx.createGain();
            entrada.gain.value = nivel();
            entrada.connect(comp);
            comp.connect(ctx.destination);
            motor = new Motor(ctx, entrada);
            roce = new Roce(motor);
        } catch (e) {
            try { console.warn('[Biblioteca PLEC] Sin sonido:', e); } catch (e2) { /* nada */ }
            ctx = null;
            return false;
        }
        return true;
    }
    function despertarAudio() {
        if (!ctx || ctx.state === 'running' || ctx.state === 'closed' || doc.hidden || mudo()) return;
        try { var p = ctx.resume(); if (p && p.catch) p.catch(function () { /* nada */ }); } catch (e) { /* nada */ }
    }
    function suspenderAudio() {
        if (!ctx || ctx.state !== 'running') return;
        try { var p = ctx.suspend(); if (p && p.catch) p.catch(function () { /* nada */ }); } catch (e) { /* nada */ }
    }
    // ¿Se puede sonar ya? (primer clic hecho, sin silenciar y con la pestaña a la vista)
    function listo() {
        if (!desbloqueado || mudo() || doc.hidden) return false;
        if (!ctx && !crearAudio()) return false;
        if (ctx.state === 'closed') return false;
        if (ctx.state !== 'running') despertarAudio();
        return true;
    }
    function ahora() { return ctx.currentTime + 0.005; }

    // Aplica volumen y silencio al audio que ya existe
    function aplicar() {
        clearTimeout(dormir);
        if (!ctx) { if (listo()) entrada.gain.value = nivel(); return; }
        entrada.gain.setTargetAtTime(nivel(), ctx.currentTime, 0.03);
        if (mudo()) {
            roce.parar();
            pararSecuencia();
            dormir = setTimeout(suspenderAudio, 400);     // silenciado no gasta batería
        } else {
            despertarAudio();
        }
    }

    // El primer clic en cualquier parte de la página habilita el audio. Cada clic,
    // además, lo reanuda si el navegador lo pausó (Safari, pestaña en segundo plano…).
    // Va en la fase de captura de window: corre antes que el clic que abre un libro.
    function alClic() {
        if (!desbloqueado) {
            desbloqueado = true;
            if (!mudo()) crearAudio();
            pintarControl();
        }
        despertarAudio();
    }
    window.addEventListener('click', alClic, true);
    doc.addEventListener('visibilitychange', function () {
        if (doc.hidden) { if (roce) roce.parar(); suspenderAudio(); }
        else despertarAudio();
    });

    /* ── SECUENCIAS (abrir y cerrar un libro) ──────────────────────── */
    // Cada secuencia va por su propio canal, así se puede cortar si se adelanta o se cierra a medias
    var secuencia = null;              // { bus, fase, nota }
    function nuevaSecuencia(fase, nota) {
        pararSecuencia();
        var bus = ctx.createGain();
        bus.connect(entrada);
        secuencia = { bus: bus, fase: fase, nota: nota };
        return bus;
    }
    function pararSecuencia() {
        if (!secuencia) return;
        var s = secuencia;
        secuencia = null;
        try { s.bus.gain.setTargetAtTime(0, ctx.currentTime, 0.02); } catch (e) { /* nada */ }
        setTimeout(function () { try { s.bus.disconnect(); } catch (e) { /* nada */ } }, 250);
    }

    /* ── QUÉ SUENA CON CADA EVENTO ─────────────────────────────────── */
    var paneles = (B.datos && B.datos.paneles) || [];
    var abriendo = null;               // { nota, animado } de la última apertura
    var cierreAnimado = false;

    // Sección de una noticia (para la nota de la campanita): la del libro, o la de su categoría
    function seccionDe(d) {
        if (d && d.seccion >= 0) return d.seccion;
        var ci = d && d.noticia ? d.noticia.ci : null;
        for (var i = paneles.length - 1; i > 0; i--) if ((paneles[i].cats || []).indexOf(ci) >= 0) return i;
        return 0;
    }

    // Deslizar
    var ultimoTic = 0, fueraAntes = {};
    doc.addEventListener('biblio:mover', function (e) {
        var d = e.detail || {};
        var estabaFuera = fueraAntes[d.indice];
        fueraAntes[d.indice] = !!d.fuera;
        if (!listo()) return;
        var v = Math.abs(d.velocidad) || 0, rapidez = clamp(v / 2.4, 0, 1), t = ahora();
        roce.mover(rapidez);
        if (d.pasa && t - ultimoTic > 0.03) {
            ultimoTic = t;
            motor.tic(t, 0.45 + 0.55 * rapidez, (d.libro | 0) % 2);
        }
        // Llegó al borde con impulso (no al estirarlo con el dedo)
        if (d.fuera && !estabaFuera && !d.arrastre && v > 0.25) motor.golpe(t, clamp(v / 2, 0.35, 1));
    });

    // Pasar sobre un libro
    var ultimoToque = 0, ultimoId = null;
    doc.addEventListener('biblio:sobre', function (e) {
        var d = e.detail || {}, t = performance.now();
        if (t - ultimoToque < 45 || (d.id === ultimoId && t - ultimoToque < 300)) return;
        if (!listo()) return;
        ultimoToque = t;
        ultimoId = d.id;
        motor.toque(ahora(), Math.max(0, d.posicion | 0) % 5);
    });

    // Abrir: si en este mismo turno llega biblio:animacion, suena al compás; si no, la versión corta
    doc.addEventListener('biblio:abrir', function (e) {
        cierreAnimado = false;
        var esta = abriendo = { nota: seccionDe(e.detail), animado: false };
        if (!listo()) return;
        pararSecuencia();
        Promise.resolve().then(function () {
            if (abriendo !== esta || esta.animado || !listo()) return;
            motor.abrirCorto(ahora(), esta.nota, nuevaSecuencia('abrir', esta.nota));
        });
    });
    doc.addEventListener('biblio:animacion', function (e) {
        var d = e.detail || {}, m = d.marcas || {};
        if (d.fase === 'abrir') {
            var nota = abriendo ? abriendo.nota : 0;
            if (abriendo) abriendo.animado = true;
            if (!listo()) return;
            var bus = nuevaSecuencia('abrir', nota);
            if (!motor.abrirLibro(ahora(), m, nota, bus)) motor.abrirCorto(ahora(), nota, bus);
        } else if (d.fase === 'cerrar') {
            cierreAnimado = true;
            if (!listo()) return;
            var bus2 = nuevaSecuencia('cerrar', 0);
            if (!motor.cerrarLibro(ahora(), m, bus2)) motor.cerrarCorto(ahora(), bus2);
        } else if (d.fase === 'adelantar') {
            // Un clic o una tecla adelanta la animación: se corta y suena solo el final
            var s = secuencia;
            if (!s || !listo()) return;
            var fin = nuevaSecuencia(s.fase + '-fin', s.nota);
            if (s.fase === 'abrir') motor.campana(ahora(), s.nota, fin);
            else if (s.fase === 'cerrar') motor.asentar(ahora(), true, fin);
        }
    });
    doc.addEventListener('biblio:cerrar', function () {
        abriendo = null;
        if (cierreAnimado) { cierreAnimado = false; return; }   // ya sonó al compás de la animación
        if (!listo()) return;
        motor.cerrarCorto(ahora(), nuevaSecuencia('cerrar', 0));  // corta la apertura si iba a medias
    });

    // Cambiar de sección. Al bajar rápido por varias secciones suena como mucho una nota
    // cada 200 ms (la última). Tras un clic en una pestaña suena enseguida, y se callan
    // las secciones que se cruzan en el camino hasta llegar.
    var ESPACIO_NOTAS = 200;
    var ultimaNota = 0, notaPendiente = -1, notaTimer = 0, destino = -1, destinoHasta = 0;
    function tocarSeccion(i, fuerte) {
        // Con una ventana abierta (bloquea el scroll) o mientras un libro se abre o se cierra, nada
        if (!listo() || doc.body.style.overflow === 'hidden' || doc.querySelector('.biblio-lector')) return;
        ultimaNota = performance.now();
        motor.nota(ahora(), i, fuerte ? 1 : 0.8);
    }
    doc.addEventListener('biblio:panel', function (e) {
        var d = e.detail || {}, t = performance.now();
        if (d.origen === 'inicio') return;
        if (d.origen === 'pestana') {
            clearTimeout(notaTimer);
            notaPendiente = -1;
            destino = d.indice;
            destinoHasta = t + 1500;
            tocarSeccion(d.indice, true);
            return;
        }
        if (destino >= 0) {
            if (t < destinoHasta) { if (d.indice === destino) destino = -1; return; }
            destino = -1;
        }
        var espera = ESPACIO_NOTAS - (t - ultimaNota);
        if (espera <= 0) { tocarSeccion(d.indice, false); return; }
        notaPendiente = d.indice;
        clearTimeout(notaTimer);
        notaTimer = setTimeout(function () {
            var i = notaPendiente;
            notaPendiente = -1;
            if (i >= 0) tocarSeccion(i, false);
        }, espera);
    });

    /* ── CONTROL: SILENCIAR Y VOLUMEN ──────────────────────────────── */
    var control, boton, icono, rango;
    function crearControl() {
        control = doc.createElement('div');
        control.className = 'biblio-sonido';
        control.setAttribute('role', 'group');
        control.setAttribute('aria-label', 'Sonidos de la biblioteca');
        control.innerHTML =
            '<button type="button" class="biblio-sonido-boton" aria-pressed="false">' +
                '<i class="fas fa-volume-high" aria-hidden="true"></i>' +
                '<span class="biblio-sonido-sr">Silenciar sonidos</span>' +
            '</button>' +
            '<label class="biblio-sonido-vol">' +
                '<span class="biblio-sonido-sr">Volumen de los sonidos</span>' +
                '<input type="range" min="0" max="100" step="5">' +
            '</label>';
        boton = control.querySelector('button');
        icono = boton.querySelector('i');
        rango = control.querySelector('input');
        doc.body.appendChild(control);

        boton.addEventListener('click', function () {
            if (mudo()) {
                pref.silencio = false;
                if (pref.volumen <= 0) pref.volumen = 0.5;
                guardarPref();
                aplicar();
                pintarControl();
                if (listo()) motor.nota(ahora(), 5, 0.9);   // confirma que vuelve a sonar
            } else {
                pref.silencio = true;
                guardarPref();
                aplicar();
                pintarControl();
            }
        });

        // Mover el volumen también cuenta como "primer clic" (es tocar el control de sonido)
        var ultimaMuestra = 0;
        rango.addEventListener('input', function () {
            if (!desbloqueado) alClic();
            pref.volumen = clamp(rango.value / 100, 0, 1);
            if (pref.volumen > 0) pref.silencio = false;
            aplicar();
            pintarControl();
            var t = performance.now();
            if (t - ultimaMuestra > 90 && listo()) { ultimaMuestra = t; motor.toque(ahora(), 2); }   // así se oye el nivel
        });
        rango.addEventListener('change', guardarPref);

        // Solo se muestra mientras la biblioteca está en pantalla
        var raiz = doc.getElementById('biblioteca');
        if (raiz && 'IntersectionObserver' in window) {
            new IntersectionObserver(function (ents) {
                control.classList.toggle('is-visible', ents[ents.length - 1].isIntersecting);
            }, { rootMargin: '0px 0px -12% 0px' }).observe(raiz);
        } else {
            control.classList.add('is-visible');
        }
        pintarControl();
    }
    function pintarControl() {
        if (!control) return;
        var callado = mudo(), v = Math.round(pref.volumen * 100);
        boton.setAttribute('aria-pressed', callado ? 'true' : 'false');
        icono.className = 'fas ' + (callado ? 'fa-volume-xmark' : (pref.volumen < 0.4 ? 'fa-volume-low' : 'fa-volume-high'));
        boton.title = callado ? 'Activar sonidos' : (desbloqueado ? 'Silenciar sonidos' : 'Silenciar sonidos (suenan desde tu primer clic)');
        if (+rango.value !== v) rango.value = v;
        rango.style.setProperty('--vol', v + '%');
        rango.setAttribute('aria-valuetext', v + ' %' + (pref.silencio ? ', silenciado' : ''));
        control.classList.toggle('is-mudo', callado);
    }

    // Si se cambia en otra pestaña, aquí también
    window.addEventListener('storage', function (e) {
        if (e.key !== CLAVE) return;
        pref = leerPref();
        aplicar();
        pintarControl();
    });

    crearControl();

    /* ── API (diagnóstico desde la consola) ────────────────────────── */
    var PRUEBAS = {
        toque: function (t) { motor.toque(t, 0); },
        tic: function (t) { motor.tic(t, 1, 0); },
        golpe: function (t) { motor.golpe(t, 1); },
        hoja: function (t) { motor.hoja(t, 0.48, 0, 0.75); },
        tapa: function (t) { motor.tapa(t, 0.56, false); },
        vuelo: function (t) { motor.vuelo(t, 0.42, true, 0.15); },
        nota: function (t) { motor.nota(t, 0, 1); },
        campana: function (t) { motor.campana(t, 0); },
        abrir: function (t) { motor.abrirCorto(t, 0); },
        cerrar: function (t) { motor.cerrarCorto(t); }
    };
    B.sonido = {
        estado: function () {
            return { desbloqueado: desbloqueado, silencio: pref.silencio, volumen: pref.volumen, audio: ctx ? ctx.state : 'sin crear' };
        },
        silenciar: function (si) { pref.silencio = si !== false; guardarPref(); aplicar(); pintarControl(); },
        volumen: function (v) { pref.volumen = clamp(+v || 0, 0, 1); guardarPref(); aplicar(); pintarControl(); },
        probar: function (nombre) {
            if (!PRUEBAS[nombre]) return Object.keys(PRUEBAS);
            if (!listo()) return 'Sin sonido: falta el primer clic o está silenciado';
            PRUEBAS[nombre](ahora());
            return true;
        },
        Motor: Motor
    };
})();
