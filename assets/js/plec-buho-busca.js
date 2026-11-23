/* ════════════════════════════════════════════════════════════════════
   NOTICIAS PLEC — PREGÚNTALE AL BÚHO: EL BUSCADOR (Etapa 5, sin dependencias)
   Busca solo en el navegador, sobre las noticias que ya vienen en la
   página: no llama a ningún servidor ni a ningún servicio de pago.

   · Ignora tildes y mayúsculas ("Política" = "politica"; "niño" = "nino").
   · Entiende plurales y género sencillos ("vacunas" encuentra "vacuna" y
     "vacunación"; "niñas" encuentra "niños").
   · Sinónimos básicos: "elecciones" también busca "comicios", "votaciones"…
   · Entiende fechas dichas con palabras: "hoy", "ayer", "esta semana",
     "este mes", "en mayo", "de 2025", "el año pasado"…
   · Una sección dicha con palabras ("vacunas en salud") cuenta como
     palabra Y como sección: pesan más las noticias de esa sección.
   · Si no hay nada, propone alternativas con cuántas noticias tendría
     cada una: sin la fecha, en todas las secciones, "¿quisiste decir…?",
     buscar en todo el archivo…
   · Escribe lo que comenta el búho ("Encontré 5 noticias sobre…").

   API: window.PlecBuscador
     .crear({ noticias, secciones, hoy })  → buscador
        noticias: [{ id, t, r, c?, p?, k, ci, f, h, i, v }]
          t título · r resumen · c contenido HTML · p palabras clave extra ·
          k nombre de la categoría · ci id de la categoría · f 'AAAA-MM-DD'
        secciones: [{ clave, nombre, cats }]   (cats null = todas)
        hoy: 'AAAA-MM-DD' (la fecha del servidor; si falta, la del navegador)
     buscador.buscar({ texto, seccion, fecha, tema }) → resultado
        seccion: clave · fecha: 'hoy'|'semana'|'mes'|'anio'|'antes' · tema: clave
        resultado: { activa, total, noticias, modo, consulta, comentario,
                     resumen, sugerencias, sinonimos }
     buscador.temas()   → [{ clave, nombre, icono, total }] (solo los que tienen noticias)
     PlecBuscador.normalizar(texto)
   ════════════════════════════════════════════════════════════════════ */
(function (global) {
    'use strict';

    /* ── TEXTO ─────────────────────────────────────────────────────── */
    var RE_PALABRA = /[a-z0-9À-ɏ]+/gi;
    var TIENE_NORMALIZE = typeof ''.normalize === 'function';

    function sinTildes(s) {
        s = String(s == null ? '' : s).toLowerCase();
        return TIENE_NORMALIZE ? s.normalize('NFD').replace(/[̀-ͯ]/g, '') : s;
    }

    var ENTIDADES = {
        nbsp: ' ', amp: '&', quot: '"', apos: "'", lt: '<', gt: '>', laquo: '«', raquo: '»',
        ldquo: '"', rdquo: '"', lsquo: "'", rsquo: "'", hellip: '…', mdash: '—', ndash: '–',
        iquest: '¿', iexcl: '¡', aacute: 'á', eacute: 'é', iacute: 'í', oacute: 'ó', uacute: 'ú',
        Aacute: 'Á', Eacute: 'É', Iacute: 'Í', Oacute: 'Ó', Uacute: 'Ú', ntilde: 'ñ', Ntilde: 'Ñ',
        uuml: 'ü', Uuml: 'Ü', ordm: 'º', ordf: 'ª', deg: '°'
    };
    function decodificar(s) {
        return s.replace(/&(#x[0-9a-f]+|#\d+|[a-z]+);/gi, function (m, e) {
            if (e.charAt(0) === '#') {
                var c = e.charAt(1).toLowerCase() === 'x' ? parseInt(e.slice(2), 16) : parseInt(e.slice(1), 10);
                try { return String.fromCodePoint(c); } catch (err) { return ' '; }
            }
            return Object.prototype.hasOwnProperty.call(ENTIDADES, e) ? ENTIDADES[e] : ' ';
        });
    }
    function textoPlano(html) {
        if (!html) return '';
        return decodificar(String(html).replace(/<(script|style)[\s\S]*?<\/\1>/gi, ' ').replace(/<[^>]*>/g, ' '));
    }

    // Palabras que se unen en una sola (en el texto y en la pregunta): [normalizada, código, cómo se muestra]
    var FRASES = [
        ['estados unidos', 'eeuu', 'Estados Unidos'],
        ['inteligencia artificial', 'ia', 'inteligencia artificial'],
        ['medio ambiente', 'medioambiente', 'medio ambiente'],
        ['cambio climatico', 'cambioclimatico', 'cambio climático'],
        ['calentamiento global', 'cambioclimatico', 'calentamiento global'],
        ['adulto mayor', 'adultomayor', 'adulto mayor'],
        ['adultos mayores', 'adultomayor', 'adultos mayores'],
        ['tercera edad', 'adultomayor', 'tercera edad'],
        ['fuerza publica', 'fuerzapublica', 'fuerza pública'],
        ['capital musical', 'ibague', 'capital musical'],
        ['salud mental', 'saludmental', 'salud mental'],
        ['redes sociales', 'redessociales', 'redes sociales']
    ].map(function (f) { return { partes: f[0].split(' '), n: f[1], s: f[2] }; });

    // Palabras sin contenido propio (normalizadas): no se buscan
    var VACIAS = {};
    ('a al algo algun alguna algunas alguno algunos ante antes aqui asi aun cada como con contra cual cuales cualquier ' +
     'cuando de del desde donde dos e el ella ellas ello ellos en entre era eran es esa esas ese eso esos esta estas este ' +
     'esto estos fue fueron ha han hasta hay la las le les lo los mas me mi mis muy ni no nos o os otra otras otro otros ' +
     'para pero poco por porque pues que quien quienes se sea ser si sido sin sobre son su sus tal tambien tan tanto te ' +
     'tiene tienen todo todos toda todas tras tu tus u un una uno unos unas usted y ya yo ' +
     'quiero quisiera busco buscar busca buscame encuentra encuentrame encontrar dame dime muestrame mostrar muestra ' +
     'ver leer lee leeme saber sabes hablen habla hablan hablar acerca respecto relacionado relacionadas relacionados ' +
     'noticia noticias articulo articulos informacion info nota notas cosa cosas tema temas seccion secciones plec ' +
     'favor porfa hola buho gracias alguna hubo paso pasa pasado pasando ocurrio sucedio hace hacer hecho')
        .split(' ').forEach(function (w) { VACIAS[w] = 1; });
    // "pasado" es palabra vacía, pero "la semana pasada" / "el mes pasado" se leen antes como fecha

    // Palabras de un texto: s = como está escrita (mayúsculas y tildes), n = normalizada
    function palabras(texto) {
        var out = [], t = String(texto == null ? '' : texto), m;
        RE_PALABRA.lastIndex = 0;
        while ((m = RE_PALABRA.exec(t))) out.push({ s: m[0], n: sinTildes(m[0]) });
        // Frases de varias palabras → un solo código
        if (out.length > 1) {
            var r = [];
            for (var i = 0; i < out.length; i++) {
                var hecho = false;
                for (var j = 0; j < FRASES.length && !hecho; j++) {
                    var f = FRASES[j], ok = true;
                    for (var k = 0; k < f.partes.length; k++) {
                        if (!out[i + k] || out[i + k].n !== f.partes[k]) { ok = false; break; }
                    }
                    if (ok) {
                        r.push({ s: out.slice(i, i + f.partes.length).map(function (p) { return p.s; }).join(' '), n: f.n });
                        i += f.partes.length - 1;
                        hecho = true;
                    }
                }
                if (!hecho) r.push(out[i]);
            }
            out = r;
        }
        return out;
    }

    // Raíz sencilla: quita el plural y la vocal final ("vacunas", "vacuna" → "vacun")
    function raiz(w) {
        if (w.length <= 4 || /^\d+$/.test(w)) return w;
        if (/yes$/.test(w)) return w.slice(0, -2);                   // leyes → ley
        if (/ces$/.test(w)) return w.slice(0, -3) + 'z';             // jueces → juez
        if (w.charAt(w.length - 1) === 's') w = w.slice(0, -1);
        if (w.length > 4 && /[aeo]$/.test(w)) w = w.slice(0, -1);
        return w;
    }

    /* ── SINÓNIMOS BÁSICOS (grupos de palabras que valen lo mismo) ── */
    var SINONIMOS = [
        'elecciones electoral comicios votaciones votación voto votos sufragio urnas',
        'candidato candidata candidatos candidatura aspirante',
        'presidente presidenta mandatario mandataria presidencia',
        'gobierno ejecutivo administración',
        'congreso parlamento legislativo senado',
        'congresista parlamentario senador senadora',
        'alcalde alcaldesa alcaldía burgomaestre',
        'gobernador gobernadora gobernación',
        'policía policías policial uniformados patrullero patrulleros',
        'ejército militares militar soldados fuerzapublica',
        'asesinato homicidio asesinado asesinada asesinan sicariato sicario',
        'robo hurto atraco asalto ladrones',
        'captura capturado capturados detenido detenidos arresto aprehendido',
        'accidente choque siniestro volcamiento colisión',
        'lluvia lluvias aguacero aguaceros precipitaciones invierno temporal tormenta',
        'inundación inundaciones desbordamiento creciente anegado',
        'incendio incendios fuego llamas conflagración',
        'sismo temblor terremoto',
        'derrumbe deslizamiento avalancha alud',
        'médico médicos doctor doctora galeno',
        'hospital hospitales clínica clínicas',
        'enfermedad enfermedades afección padecimiento',
        'vacuna vacunas vacunación inmunización',
        'colegio colegios escuela escuelas plantel institución',
        'estudiante estudiantes alumno alumnos escolares',
        'profesor profesores docente docentes maestro maestros educador',
        'universidad universidades universitario universitarios',
        'educación educativo educativa enseñanza',
        'trabajo empleo empleos laboral trabajadores',
        'desempleo desocupación cesantes',
        'paro huelga protesta protestas manifestación marcha movilización bloqueo',
        'precio precios costo costos tarifa tarifas alza carestía',
        'dinero plata recursos fondos presupuesto',
        'impuesto impuestos tributaria tributario iva',
        'economía económico económica finanzas',
        'fútbol futbol balompié futbolista',
        'deporte deportes deportivo deportiva deportista',
        'música musical canción canciones cantante concierto',
        'cine película películas cinematográfico',
        'farándula famosos celebridades espectáculo entretenimiento',
        'cultura cultural arte artístico',
        'niño niños niña niñas infancia niñez menores infantil',
        'joven jóvenes juventud adolescentes juvenil',
        'mujer mujeres femenina femenino',
        'adultomayor anciano ancianos abuelos',
        'discapacidad discapacitado discapacitados accesibilidad',
        'inclusión incluyente diversidad',
        'indígena indígenas resguardo etnia',
        'campesino campesinos agricultor agricultores agro rural',
        'agua acueducto hídrico',
        'medioambiente ambiente ambiental ecología ecológico',
        'cambioclimatico climático',
        'carretera carreteras vía vías autopista vial',
        'tránsito movilidad tráfico trancón',
        'internet conectividad digital',
        'celular celulares teléfono móvil',
        'tecnología tecnológico tecnológica',
        'ia chatgpt',
        'ibagué ibaguereño ibaguereños ibaguereña',
        'tolima tolimense tolimenses',
        'colombia colombiano colombianos colombiana colombianas',
        'eeuu estadounidense norteamericano',
        'venezuela venezolano venezolanos venezolana',
        'corrupción corrupto corruptos soborno desfalco peculado',
        'paz posconflicto reconciliación',
        'guerrilla guerrilleros eln farc disidencias',
        'justicia judicial tribunal juzgado',
        'fiscalía fiscal',
        'cárcel cárceles prisión penitenciaría inpec reclusos presos',
        'opinión columna columnas columnista columnistas editorial'
    ];

    // Secciones que se pueden nombrar en la pregunta ("vacunas en salud")
    var ALIAS_SECCION = {
        colombia: 'colombia nacional nacionales',
        politica: 'política políticas político políticos',
        salud: 'salud',
        internacional: 'internacional internacionales mundo exterior extranjero',
        educacion: 'educación educativo educativa',
        farandula: 'farándula entretenimiento espectáculo espectáculos famosos',
        inclusion: 'inclusión',
        opinion: 'opinión columna columnas columnista columnistas editorial'
    };

    // Temas (más amplios que los sinónimos): los botones de la barra. cats = categorías que ya son de ese tema.
    var TEMAS = [
        { clave: 'elecciones', nombre: 'Elecciones', icono: 'fa-check-to-slot', cats: [],
          p: 'elecciones electoral comicios votaciones votación votantes sufragio urnas candidato candidata candidatos candidatura campaña registraduría escrutinio jurados' },
        { clave: 'gobierno', nombre: 'Gobierno', icono: 'fa-landmark', cats: [7],
          p: 'gobierno presidente presidenta ministro ministra ministerio gobernador gobernación alcalde alcaldía concejo concejal congreso senado congresista senador decreto reforma' },
        { clave: 'seguridad', nombre: 'Seguridad', icono: 'fa-shield-halved', cats: [],
          p: 'seguridad inseguridad policía homicidio asesinato crimen delito delincuencia robo hurto captura capturado sicario extorsión atentado violencia' },
        { clave: 'economia', nombre: 'Economía', icono: 'fa-coins', cats: [8],
          p: 'economía económico empleo desempleo salario precios inflación impuestos dólar comercio empresa empresas negocio presupuesto inversión pobreza' },
        { clave: 'salud', nombre: 'Salud', icono: 'fa-heart-pulse', cats: [4],
          p: 'salud hospital clínica médico médicos enfermedad pacientes eps vacuna vacunación epidemia virus dengue medicina' },
        { clave: 'educacion', nombre: 'Educación', icono: 'fa-graduation-cap', cats: [3],
          p: 'educación educativo colegio escuela universidad estudiantes docentes profesores maestros icfes matrícula becas' },
        { clave: 'clima', nombre: 'Clima y emergencias', icono: 'fa-cloud-rain', cats: [],
          p: 'clima lluvia lluvias invierno inundación aguacero tormenta temporal desbordamiento deslizamiento derrumbe emergencia sequía incendio sismo temblor bomberos' },
        { clave: 'ambiente', nombre: 'Medio ambiente', icono: 'fa-leaf', cats: [],
          p: 'medioambiente ambiental naturaleza ecología contaminación deforestación reciclaje bosque fauna cambioclimatico' },
        { clave: 'deportes', nombre: 'Deportes', icono: 'fa-futbol', cats: [2],
          p: 'deporte deportes deportivo fútbol futbol partido selección gol liga torneo atleta deportista ciclismo campeón' },
        { clave: 'cultura', nombre: 'Cultura y espectáculo', icono: 'fa-music', cats: [5, 10, 14],
          p: 'cultura cultural música musical concierto cantante artista festival farándula espectáculo cine película teatro danza folclor famosos actor actriz' },
        { clave: 'inclusion', nombre: 'Inclusión y derechos', icono: 'fa-universal-access', cats: [16],
          p: 'inclusión discapacidad accesibilidad diversidad derechos igualdad género mujeres lgbti indígena afro migrantes' },
        { clave: 'paz', nombre: 'Paz y conflicto', icono: 'fa-dove', cats: [],
          p: 'paz conflicto guerrilla eln farc disidencias armado armados acuerdo desmovilización reincorporación víctimas' },
        { clave: 'justicia', nombre: 'Justicia', icono: 'fa-scale-balanced', cats: [],
          p: 'justicia judicial juez tribunal corte fiscalía fiscal procuraduría contraloría condena sentencia demanda juicio cárcel corrupción' },
        { clave: 'tecnologia', nombre: 'Tecnología', icono: 'fa-microchip', cats: [1],
          p: 'tecnología tecnológico internet digital celular aplicación ia ciberseguridad innovación computador' },
        { clave: 'tolima', nombre: 'Tolima e Ibagué', icono: 'fa-location-dot', cats: [],
          p: 'tolima tolimense ibagué ibaguereño espinal honda melgar mariquita líbano chaparral natagaima guamo flandes lérida' }
    ];

    var MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    // Prepara los grupos: raíz → grupos donde aparece, y la forma con tildes de cada palabra
    var GRUPOS = SINONIMOS.map(function (g) {
        return g.split(' ').map(function (w) { return { s: w, r: raiz(sinTildes(w)) }; });
    });
    var GRUPOS_DE = {};
    GRUPOS.forEach(function (g, i) {
        g.forEach(function (w) { (GRUPOS_DE[w.r] = GRUPOS_DE[w.r] || []).push(i); });
    });
    var SECCION_DE = {};
    Object.keys(ALIAS_SECCION).forEach(function (clave) {
        ALIAS_SECCION[clave].split(' ').forEach(function (w) { SECCION_DE[raiz(sinTildes(w))] = clave; });
    });
    TEMAS.forEach(function (t) {
        t.raices = [];
        t.p.split(' ').forEach(function (w) { var r = raiz(sinTildes(w)); if (t.raices.indexOf(r) < 0) t.raices.push(r); });
    });

    /* ── FECHAS ────────────────────────────────────────────────────── */
    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function aTexto(d) { return d.getUTCFullYear() + '-' + pad(d.getUTCMonth() + 1) + '-' + pad(d.getUTCDate()); }
    function aFecha(s) { var p = String(s).split('-'); return new Date(Date.UTC(+p[0], (+p[1] || 1) - 1, +p[2] || 1)); }
    function sumarDias(s, n) { var d = aFecha(s); d.setUTCDate(d.getUTCDate() + n); return aTexto(d); }
    function finDeMes(anio, mes) { return aTexto(new Date(Date.UTC(anio, mes + 1, 0))); }   // mes: 0-11
    function diasEntre(a, b) { return Math.round((aFecha(b) - aFecha(a)) / 86400000); }
    function fechaBonita(s) {
        var p = String(s || '').split('-');
        return p.length === 3 ? (+p[2]) + ' de ' + MESES[(+p[1]) - 1] + (p[0] !== HOY_ANIO ? ' de ' + p[0] : '') : '';
    }
    var HOY_ANIO = '';

    // Rangos del menú "Fecha"
    function rangoMenu(clave, hoy) {
        var anio = +hoy.slice(0, 4);
        switch (clave) {
            case 'hoy':    return { desde: hoy, hasta: hoy, nombre: 'de hoy' };
            case 'semana': return { desde: sumarDias(hoy, -6), hasta: hoy, nombre: 'de los últimos 7 días' };
            case 'mes':    return { desde: sumarDias(hoy, -29), hasta: hoy, nombre: 'de los últimos 30 días' };
            case 'anio':   return { desde: anio + '-01-01', hasta: hoy, nombre: 'de este año' };
            case 'antes':  return { desde: '0000-01-01', hasta: sumarDias(hoy, -366), nombre: 'de hace más de un año' };
        }
        return null;
    }

    // Busca una fecha dicha con palabras en la lista de palabras; marca las usadas
    function leerFecha(ps, hoy) {
        var n = ps.map(function (p) { return p.n; });
        var anio = +hoy.slice(0, 4), mesHoy = +hoy.slice(5, 7) - 1;
        var usar = function (desde, cuantas) { for (var k = 0; k < cuantas; k++) ps[desde + k].fecha = true; };
        // Un artículo o preposición justo antes de la fecha también se la lleva ("de esta semana")
        var antes = function (i) {
            var k = i - 1;
            while (k >= 0 && /^(de|del|en|el|la|los|las|desde|durante)$/.test(n[k])) { ps[k].fecha = true; k--; }
        };
        for (var i = 0; i < n.length; i++) {
            var w = n[i], w2 = n[i + 1], w3 = n[i + 2];
            if (w === 'hoy') { usar(i, 1); antes(i); return { desde: hoy, hasta: hoy, nombre: 'de hoy' }; }
            if (w === 'ayer') { usar(i, 1); antes(i); var ay = sumarDias(hoy, -1); return { desde: ay, hasta: ay, nombre: 'de ayer' }; }
            if (w === 'anteayer' || w === 'antier') { usar(i, 1); antes(i); var aa = sumarDias(hoy, -2); return { desde: aa, hasta: aa, nombre: 'de anteayer' }; }
            if ((w === 'semana' && (w2 === 'pasada' || w2 === 'anterior')) || (w === 'pasada' && w2 === 'semana')) {
                usar(i, 2); antes(i);
                return { desde: sumarDias(hoy, -13), hasta: sumarDias(hoy, -7), nombre: 'de la semana pasada' };
            }
            if ((w === 'esta' || w === 'ultima' || w === 'esa') && w2 === 'semana') {
                usar(i, 2); antes(i);
                return { desde: sumarDias(hoy, -6), hasta: hoy, nombre: w === 'ultima' ? 'de la última semana' : 'de esta semana' };
            }
            if (w === 'semana') { usar(i, 1); antes(i); return { desde: sumarDias(hoy, -6), hasta: hoy, nombre: 'de esta semana' }; }
            if ((w === 'mes' && (w2 === 'pasado' || w2 === 'anterior')) || (w === 'pasado' && w2 === 'mes')) {
                usar(i, 2); antes(i);
                var mp = mesHoy === 0 ? 11 : mesHoy - 1, ap = mesHoy === 0 ? anio - 1 : anio;
                return { desde: ap + '-' + pad(mp + 1) + '-01', hasta: finDeMes(ap, mp), nombre: 'del mes pasado' };
            }
            if ((w === 'este' || w === 'ese') && w2 === 'mes') {
                usar(i, 2); antes(i);
                return { desde: hoy.slice(0, 8) + '01', hasta: hoy, nombre: 'de este mes' };
            }
            if (w === 'ultimo' && w2 === 'mes') { usar(i, 2); antes(i); return rangoMenu('mes', hoy); }
            if (w === 'ultimos' && /^\d+$/.test(w2 || '') && (w3 === 'dias' || w3 === 'dia')) {
                usar(i, 3); antes(i);
                var nd = Math.max(1, Math.min(3650, +w2));
                return { desde: sumarDias(hoy, -(nd - 1)), hasta: hoy, nombre: 'de los últimos ' + nd + ' días' };
            }
            if ((w === 'ano' || w === 'anio') && (w2 === 'pasado' || w2 === 'anterior')) {
                usar(i, 2); antes(i);
                return { desde: (anio - 1) + '-01-01', hasta: (anio - 1) + '-12-31', nombre: 'del año pasado' };
            }
            if ((w === 'este' || w === 'ese') && (w2 === 'ano' || w2 === 'anio')) { usar(i, 2); antes(i); return rangoMenu('anio', hoy); }
            var m = MESES.indexOf(w === 'setiembre' ? 'septiembre' : w);
            if (m >= 0) {
                var a = m > mesHoy ? anio - 1 : anio, cuantas = 1;
                // "mayo 2025" o "mayo de 2025"
                if (/^(19|20)\d\d$/.test(w2 || '')) { a = +w2; cuantas = 2; }
                else if (w2 === 'de' && /^(19|20)\d\d$/.test(w3 || '')) { a = +w3; cuantas = 3; }
                usar(i, cuantas); antes(i);
                return { desde: a + '-' + pad(m + 1) + '-01', hasta: finDeMes(a, m),
                         nombre: 'de ' + MESES[m] + (cuantas > 1 || a !== anio ? ' de ' + a : '') };
            }
            if (/^(19|20)\d\d$/.test(w)) {
                usar(i, 1); antes(i);
                return { desde: w + '-01-01', hasta: w + '-12-31', nombre: 'de ' + w };
            }
        }
        return null;
    }
    function leerOrden(ps) {
        for (var i = 0; i < ps.length; i++) {
            var w = ps[i].n;
            if (/^(recientes?|ultimas?|ultimos?|nuevas?|nuevos?|actuales?|frescas?)$/.test(w)) {
                ps[i].fecha = true;
                if (i > 0 && /^(lo|las|los|mas)$/.test(ps[i - 1].n)) ps[i - 1].fecha = true;
                return 'reciente';
            }
        }
        return '';
    }
    function cruzar(a, b) {
        if (!a) return b;
        if (!b) return a;
        return { desde: a.desde > b.desde ? a.desde : b.desde, hasta: a.hasta < b.hasta ? a.hasta : b.hasta, nombre: b.nombre };
    }

    /* ── DISTANCIA ENTRE PALABRAS (para "¿quisiste decir…?") ───────── */
    function distancia(a, b, max) {
        if (Math.abs(a.length - b.length) > max) return max + 1;
        var d = [], i, j;
        for (i = 0; i <= a.length; i++) d[i] = [i];
        for (j = 0; j <= b.length; j++) d[0][j] = j;
        for (i = 1; i <= a.length; i++) {
            var menor = Infinity;
            for (j = 1; j <= b.length; j++) {
                var c = a.charAt(i - 1) === b.charAt(j - 1) ? 0 : 1;
                var v = Math.min(d[i - 1][j] + 1, d[i][j - 1] + 1, d[i - 1][j - 1] + c);
                // Dos letras cambiadas de lugar ("vacuans") cuentan como un solo error
                if (i > 1 && j > 1 && a.charAt(i - 1) === b.charAt(j - 2) && a.charAt(i - 2) === b.charAt(j - 1)) {
                    v = Math.min(v, d[i - 2][j - 2] + 1);
                }
                d[i][j] = v;
                if (v < menor) menor = v;
            }
            if (menor > max) return max + 1;
        }
        return d[a.length][b.length];
    }

    /* ── EL BUSCADOR ───────────────────────────────────────────────── */
    var PESO = { titulo: 6, categoria: 4, resumen: 3, cuerpo: 1 };
    var MIN_PREFIJO = 3;

    function crear(op) {
        op = op || {};
        var hoy = /^\d{4}-\d\d-\d\d$/.test(op.hoy || '') ? op.hoy : aTexto(new Date(Date.UTC(new Date().getFullYear(), new Date().getMonth(), new Date().getDate())));
        HOY_ANIO = hoy.slice(0, 4);
        var secciones = (op.secciones || []).filter(function (s) { return s && s.clave; });
        var seccionDeCat = {}, nombreSeccion = {};
        secciones.forEach(function (s) {
            nombreSeccion[s.clave] = s.nombre;
            (s.cats || []).forEach(function (c) { if (!(c in seccionDeCat)) seccionDeCat[c] = s.clave; });
        });
        var vocab = {};          // palabra normalizada → { s: forma con tildes más usada, c: veces }

        function campo(texto, peso, contar) {
            var set = {}, lista = [];
            palabras(texto).forEach(function (p) {
                if (VACIAS[p.n] || (p.n.length < 2 && !/\d/.test(p.n))) return;
                var r = raiz(p.n);
                if (!set[r]) { set[r] = 1; lista.push(r); }
                if (contar && p.n.length >= 4) {
                    var s = p.s.toLowerCase();
                    var v = vocab[p.n] || (vocab[p.n] = { s: s, c: 0, formas: {} });
                    v.c++;
                    v.formas[s] = (v.formas[s] || 0) + 1;
                    if (v.formas[s] > (v.formas[v.s] || 0)) v.s = s;
                }
            });
            return { w: peso, set: set, lista: lista };
        }

        var docs = [];
        var vistos = {};
        (op.noticias || []).forEach(function (n) {
            if (!n || vistos[n.id]) return;
            vistos[n.id] = 1;
            var cuerpo = n.c != null ? textoPlano(n.c) : (n.p || '');
            var d = {
                n: n, id: n.id, ci: n.ci, f: n.f || '', seccion: seccionDeCat[n.ci] || null,
                campos: [
                    campo(n.t, PESO.titulo, true),
                    campo(n.k, PESO.categoria, false),
                    campo(n.r, PESO.resumen, true),
                    campo(cuerpo, PESO.cuerpo, true)
                ],
                temas: {}
            };
            docs.push(d);
        });

        // ¿El documento tiene esta raíz? 1 exacta · .7 si empieza igual · 0 nada
        function tiene(campoDoc, r, conPrefijo) {
            if (campoDoc.set[r]) return 1;
            if (conPrefijo && r.length >= MIN_PREFIJO) {
                for (var i = 0; i < campoDoc.lista.length; i++) {
                    if (campoDoc.lista[i].length > r.length && campoDoc.lista[i].lastIndexOf(r, 0) === 0) return 0.7;
                }
            }
            return 0;
        }

        // Las palabras de los sinónimos y los temas también sirven para "¿quisiste decir…?"
        // (con 0 usos: a igual parecido gana una palabra que sí está en las noticias)
        GRUPOS.forEach(function (g) {
            g.forEach(function (w) { var k = sinTildes(w.s); if (k.length >= 4 && !vocab[k]) vocab[k] = { s: w.s, c: 0, formas: {} }; });
        });
        TEMAS.forEach(function (t) {
            t.p.split(' ').forEach(function (w) { var k = sinTildes(w); if (k.length >= 4 && !vocab[k]) vocab[k] = { s: w, c: 0, formas: {} }; });
        });

        // Temas de cada noticia: su categoría, o varias palabras del tema (el título pesa más)
        docs.forEach(function (d) {
            TEMAS.forEach(function (t) {
                if (t.cats.indexOf(d.ci) >= 0) { d.temas[t.clave] = 1; return; }
                var pts = 0;
                d.campos.forEach(function (c) {
                    if (c.w === PESO.categoria) return;
                    t.raices.forEach(function (r) { if (tiene(c, r, r.length >= 5) >= 0.7) pts += c.w; });
                });
                if (pts >= 3) d.temas[t.clave] = 1;
            });
        });

        var temasCon = TEMAS.map(function (t) {
            var total = 0;
            docs.forEach(function (d) { if (d.temas[t.clave]) total++; });
            return { clave: t.clave, nombre: t.nombre, icono: t.icono, total: total };
        }).filter(function (t) { return t.total > 0; }).sort(function (a, b) { return b.total - a.total; });
        var temaPorClave = {};
        TEMAS.forEach(function (t) { temaPorClave[t.clave] = t; });

        /* La pregunta → términos */
        function interpretar(texto) {
            var ps = palabras(texto);
            var fecha = leerFecha(ps, hoy);
            var orden = leerOrden(ps);
            var terminos = [];
            ps.forEach(function (p) {
                if (p.fecha || VACIAS[p.n]) return;
                if (p.n.length < 2 && !/\d/.test(p.n)) return;
                var r = raiz(p.n);
                if (terminos.some(function (t) { return t.r === r; })) return;
                var alt = [], altS = [];
                (GRUPOS_DE[r] || []).forEach(function (gi) {
                    GRUPOS[gi].forEach(function (w) {
                        if (w.r !== r && alt.indexOf(w.r) < 0) { alt.push(w.r); altS.push(w.s); }
                    });
                });
                terminos.push({ s: p.s, n: p.n, r: r, alt: alt, altS: altS, seccion: SECCION_DE[r] || null });
            });
            return {
                terminos: terminos, fecha: fecha, orden: orden,
                textoSinFecha: ps.filter(function (p) { return !p.fecha; }).map(function (p) { return p.s; }).join(' ')
            };
        }

        // Puntos de un término en un documento (y si solo coincidió por un sinónimo)
        function puntuar(d, t) {
            var pts = 0, directo = false, porSinonimo = false;
            for (var i = 0; i < d.campos.length; i++) {
                var c = d.campos[i], q = tiene(c, t.r, true);
                if (q) { directo = true; pts += q * c.w; continue; }
                for (var k = 0; k < t.alt.length; k++) {
                    if (tiene(c, t.alt[k], t.alt[k].length >= 5)) { pts += 0.6 * c.w; porSinonimo = true; break; }
                }
            }
            if (t.seccion && d.seccion === t.seccion) { pts += 8; directo = true; }
            return { pts: pts, sinonimo: porSinonimo && !directo };
        }

        function filtrar(lista, q, fecha) {
            var cats = null;
            if (q.seccion) {
                var s = secciones.filter(function (x) { return x.clave === q.seccion; })[0];
                if (s && s.cats) cats = s.cats;
            }
            return lista.filter(function (d) {
                if (cats && cats.indexOf(d.ci) < 0) return false;
                if (q.tema && !d.temas[q.tema]) return false;
                if (fecha && (d.f < fecha.desde || d.f > fecha.hasta)) return false;
                return true;
            });
        }

        function ordenar(lista, reciente) {
            return lista.sort(function (a, b) {
                if (reciente) { if (a.d.f !== b.d.f) return a.d.f < b.d.f ? 1 : -1; }
                if (b.pts !== a.pts) return b.pts - a.pts;
                if (a.d.f !== b.d.f) return a.d.f < b.d.f ? 1 : -1;
                return b.d.id - a.d.id;
            });
        }

        function recencia(d) {
            if (!d.f) return 0;
            var dias = Math.max(0, diasEntre(d.f, hoy));
            return 1.5 / (1 + dias / 30);
        }

        // La búsqueda en sí, sin comentarios ni alternativas
        function correr(q, inter) {
            var fecha = cruzar(rangoMenu(q.fecha, hoy), inter.fecha);
            var base = filtrar(docs, q, fecha);
            var ts = inter.terminos, res = [], modo = 'todas', sinonimos = {};
            if (ts.length) {
                var parcial = [];
                base.forEach(function (d) {
                    var total = 0, cuantos = 0, sin = [];
                    ts.forEach(function (t) {
                        var p = puntuar(d, t);
                        if (p.pts > 0) { total += p.pts; cuantos++; if (p.sinonimo) sin.push(t); }
                    });
                    if (!cuantos) return;
                    var item = { d: d, pts: total + recencia(d), cuantos: cuantos, sin: sin };
                    if (cuantos === ts.length) res.push(item); else parcial.push(item);
                });
                if (!res.length && parcial.length && ts.length > 1) {
                    modo = 'parcial';
                    res = parcial.map(function (x) { x.pts += x.cuantos * 20; return x; });
                }
                res.forEach(function (x) {
                    x.sin.forEach(function (t) {
                        // Qué sinónimo apareció de verdad (para contarlo con su tilde)
                        for (var k = 0; k < t.alt.length; k++) {
                            var hay = x.d.campos.some(function (c) { return tiene(c, t.alt[k], t.alt[k].length >= 5); });
                            if (hay) { sinonimos[t.altS[k]] = 1; break; }
                        }
                    });
                });
            } else if (q.seccion || q.tema || fecha || inter.orden) {
                res = base.map(function (d) { return { d: d, pts: 0, cuantos: 0, sin: [] }; });
            } else {
                return null;          // nada que buscar
            }
            ordenar(res, inter.orden === 'reciente' || !ts.length);
            return { lista: res, modo: modo, fecha: fecha, sinonimos: Object.keys(sinonimos) };
        }

        /* Lo que comenta el búho */
        function comillas(t) { return '«' + t + '»'; }
        function plural(n, uno, varios) { return n === 1 ? uno : varios; }
        function cortar(t, n) { t = String(t || ''); return t.length > n ? t.slice(0, n - 1).replace(/\s+\S*$/, '') + '…' : t; }

        function describir(q, inter, r) {
            var ts = inter.terminos;
            var deSeccion = ts.filter(function (t) { return t.seccion; });
            var libres = ts.filter(function (t) { return !t.seccion; });
            var n = r ? r.lista.length : 0;
            var tema = q.tema && temaPorClave[q.tema] ? temaPorClave[q.tema].nombre : '';
            var fechaN = r && r.fecha ? r.fecha.nombre : (inter.fecha ? inter.fecha.nombre : '');

            // ¿Todas las encontradas son de la sección nombrada? Entonces se dice "en Salud";
            // si no, la sección se cuenta como una palabra más ("mixta")
            var enSeccion = '', dichas = libres.map(function (t) { return t.s; }), mixta = null;
            deSeccion.forEach(function (t) {
                var todas = n && r.lista.every(function (x) { return x.d.seccion === t.seccion; });
                if (todas && !enSeccion) enSeccion = nombreSeccion[t.seccion] || t.s;
                else { dichas.push(t.s); if (!mixta) mixta = t; }
            });
            if (!enSeccion && q.seccion) enSeccion = nombreSeccion[q.seccion] || '';

            var sobre = dichas.length ? ' sobre ' + comillas(dichas.join(' ')) : '';
            var donde = enSeccion ? ' en ' + enSeccion : '';
            var delTema = tema ? (sobre ? ', del tema ' : ' del tema ') + tema : '';
            var cuando = fechaN ? ' ' + fechaN : '';
            var detalle = sobre ? sobre + donde + delTema : delTema + donde;
            var resumen;

            if (!n) {
                resumen = 'No encontré noticias' + ((detalle + cuando) || ' para esa búsqueda') + '.';
                return { resumen: resumen, comentario: resumen };
            }

            var cuantas = n === 1 ? 'una noticia' : n + ' noticias';
            var propias = mixta ? r.lista.filter(function (x) { return x.d.seccion === mixta.seccion; }).length : 0;
            if (r.modo === 'parcial') {
                resumen = 'No tengo noticias con todas esas palabras juntas, pero encontré ' + cuantas + ' que se acercan' + donde + cuando + '.';
            } else if (!ts.length && !tema && !enSeccion && !cuando) {
                resumen = 'Aquí tienes lo más reciente: ' + cuantas + '.';
            } else if (!libres.length && !mixta && !tema && enSeccion && !cuando) {
                resumen = 'Estas son ' + (n === 1 ? 'la única noticia' : 'las ' + n + ' noticias') + ' de ' + enSeccion + ' que tengo en mis estantes.';
            } else if (!libres.length && mixta && deSeccion.length === 1 && !q.seccion && !tema && propias > 0) {
                // Solo se nombró una sección, y también hablan de eso noticias de otras secciones
                resumen = 'Encontré ' + cuantas + ' sobre ' + comillas(mixta.s) + ': ' + propias + ' de la sección ' +
                    (nombreSeccion[mixta.seccion] || mixta.s) + ' y ' + (n - propias) + ' de otras secciones' + cuando + '.';
            } else {
                resumen = 'Encontré ' + cuantas + detalle + cuando + '.';
            }

            var extra = '';
            if (n === 1) extra = ' Se titula ' + comillas(cortar(r.lista[0].d.n.t, 80)) + '.';
            else if (n <= 3 && ts.length) extra = ' La que más se acerca: ' + comillas(cortar(r.lista[0].d.n.t, 70)) + '.';
            if (r.sinonimos.length && r.modo !== 'parcial') {
                extra += ' También busqué palabras parecidas, como ' + comillas(r.sinonimos[0]) + '.';
            }
            return { resumen: resumen, comentario: resumen + extra };
        }

        /* Alternativas cuando no hay nada (con cuántas noticias daría cada una) */
        // Palabras parecidas a un término que no aparece en ninguna noticia (las 3 mejores)
        function existe(t) {
            return docs.some(function (d) { return puntuar(d, t).pts > 0; });
        }
        function corregir(t) {
            if (t.n.length < 4 || /\d/.test(t.n) || existe(t)) return [];
            var max = t.n.length >= 7 ? 2 : 1, cands = [];
            Object.keys(vocab).forEach(function (w) {
                if (raiz(w) === t.r) return;
                var dd = distancia(t.n, w, max);
                if (dd <= max) cands.push({ s: vocab[w].s, d: dd, c: vocab[w].c });
            });
            cands.sort(function (a, b) { return a.d - b.d || b.c - a.c; });
            return cands.slice(0, 3).map(function (x) { return x.s; });
        }
        function contar(q) {
            var r = correr(q, interpretar(q.texto || ''));
            return r ? r.lista.length : 0;
        }
        function alternativas(q, inter) {
            var sug = [], texto = q.texto || '';
            var copia = function (cambio) {
                var c = { texto: texto, seccion: q.seccion || '', fecha: q.fecha || '', tema: q.tema || '' };
                for (var k in cambio) c[k] = cambio[k];
                return c;
            };
            var poner = function (etiqueta, cambio) {
                var c = copia(cambio), total = contar(c);
                if (total > 0) sug.push({ texto: etiqueta, cuantas: total, consulta: c });
                return total;
            };
            var hayFecha = !!(q.fecha || inter.fecha);
            if (hayFecha) poner('Sin la fecha', { fecha: '', texto: inter.textoSinFecha });
            if (q.seccion) poner('En todas las secciones', { seccion: '' });
            if (q.tema) poner('Sin el tema', { tema: '' });
            var filtros = (hayFecha ? 1 : 0) + (q.seccion ? 1 : 0) + (q.tema ? 1 : 0);
            if (!sug.length && filtros > 1) poner('Sin filtros', { fecha: '', seccion: '', tema: '', texto: inter.textoSinFecha });

            // ¿Quisiste decir…? (en todas las secciones y fechas si así sí hay algo)
            var corregido = inter.terminos.map(function (t) { return { t: t, c: corregir(t) }; });
            if (corregido.some(function (x) { return x.c.length; })) {
                // Prueba la mejor corrección de cada palabra; si no da nada, la segunda, y la tercera
                for (var intento = 0; intento < 3; intento++) {
                    var nuevo = inter.textoSinFecha, dichas = [];
                    corregido.forEach(function (x) {
                        if (!x.c.length) return;
                        var c = x.c[Math.min(intento, x.c.length - 1)];
                        dichas.push(c);
                        nuevo = nuevo.replace(new RegExp('(^|\\s)' + x.t.s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '(?=\\s|$)'), '$1' + c);
                    });
                    var dicho = dichas.join(' '), etiqueta = '¿Quisiste decir ' + comillas(dicho) + '?';
                    var conFecha = hayFecha && !q.fecha ? nuevo + ' ' + inter.fecha.nombre : nuevo;
                    var antes = sug.length;
                    if (!poner(etiqueta, { texto: conFecha })) poner(etiqueta, { texto: nuevo, fecha: '', seccion: '', tema: '' });
                    if (sug.length > antes) { sug[sug.length - 1].correccion = dicho; break; }
                }
            }

            if (inter.terminos.length && texto.trim()) {
                sug.push({ texto: 'Buscar ' + comillas(cortar(inter.textoSinFecha || texto, 40)) + ' en todo el archivo',
                           enlace: 'index.php?s=' + encodeURIComponent((inter.textoSinFecha || texto).slice(0, 100)) +
                                   (q.seccion ? '&cat=' + ((secciones.filter(function (s) { return s.clave === q.seccion; })[0] || {}).cats || [0])[0] : '') });
            }
            if (sug.length < 2) {
                temasCon.slice(0, 3).forEach(function (t) {
                    if (t.clave !== q.tema) sug.push({ texto: t.nombre, cuantas: t.total, consulta: { texto: '', seccion: '', fecha: '', tema: t.clave }, tema: true });
                });
                sug.push({ texto: 'Ver lo más reciente', consulta: { texto: 'lo más reciente', seccion: '', fecha: '', tema: '' } });
            }
            return sug;
        }

        function buscar(q) {
            q = q || {};
            q = { texto: String(q.texto || '').slice(0, 120), seccion: q.seccion || '', fecha: q.fecha || '', tema: q.tema || '' };
            var inter = interpretar(q.texto);
            var r = correr(q, inter);
            if (!r) return { activa: false, total: 0, noticias: [], consulta: q, sugerencias: [] };
            var dicho = describir(q, inter, r);
            var res = {
                activa: true,
                total: r.lista.length,
                noticias: r.lista.map(function (x) { return x.d.n; }),
                modo: r.modo,
                consulta: q,
                interpretacion: {
                    palabras: inter.terminos.map(function (t) { return t.s; }),
                    sinonimos: inter.terminos.reduce(function (a, t) { return a.concat(t.altS); }, []),
                    fecha: r.fecha ? r.fecha.nombre : '',
                    reciente: inter.orden === 'reciente'
                },
                sinonimos: r.sinonimos,
                resumen: dicho.resumen,
                comentario: dicho.comentario,
                sugerencias: []
            };
            if (!res.total) {
                res.sugerencias = alternativas(q, inter);
                var q1 = res.sugerencias.filter(function (s) { return s.correccion; })[0];
                var otra = res.sugerencias.filter(function (s) { return s.consulta && !s.correccion && !s.tema && s.cuantas; })[0];
                var ultima = masReciente();
                if (q1) res.comentario += ' ¿Quisiste decir ' + comillas(q1.correccion) + '?';
                else if (otra) res.comentario += ' ' + otra.texto + ' hay ' + (otra.cuantas === 1 ? 'una. ¿Te la muestro?' : otra.cuantas + '. ¿Te las muestro?');
                else if ((q.fecha || inter.fecha) && !inter.terminos.length && ultima) {
                    res.comentario += ' Lo más reciente que tengo es del ' + ultima.fecha + '.';
                    // "Ver lo más reciente" primero
                    res.sugerencias.sort(function (a, b) { return (b.texto === 'Ver lo más reciente') - (a.texto === 'Ver lo más reciente'); });
                }
                else res.comentario += ' Prueba con otras palabras o mira estas ideas.';
            }
            return res;
        }

        function masReciente() {
            var m = null;
            docs.forEach(function (d) { if (d.f && (!m || d.f > m.f)) m = d; });
            return m ? { noticia: m.n, fecha: fechaBonita(m.f) } : null;
        }

        return {
            buscar: buscar,
            temas: function () { return temasCon.slice(); },
            // Secciones que tienen al menos una noticia en el índice (para el menú "Sección")
            secciones: function () {
                return secciones.filter(function (s) { return s.cats && s.cats.length; }).map(function (s) {
                    var total = 0;
                    docs.forEach(function (d) { if (s.cats.indexOf(d.ci) >= 0) total++; });
                    return { clave: s.clave, nombre: s.nombre, total: total };
                }).filter(function (s) { return s.total > 0; });
            },
            total: function () { return docs.length; },
            hoy: hoy,
            masReciente: masReciente
        };
    }

    var API = { crear: crear, normalizar: sinTildes, raiz: raiz, palabras: palabras, fechaBonita: fechaBonita };
    if (typeof module !== 'undefined' && module.exports) module.exports = API;
    else global.PlecBuscador = API;
})(typeof window !== 'undefined' ? window : this);
