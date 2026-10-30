/**
 * LÓGICA DEL PANEL WEB (VANILLA JAVASCRIPT)
 * - Termostato Interactivo de Aire Acondicionado (16°C-30°C, Modos, Fan, Swing, Presets)
 * - Control Avanzado de Proyector (Fuentes HDMI/VGA, Freeze, AV Mute, Volumen)
 * - Escenas Rápidas de Aula de 1 Clic (Modo Proyección, Iniciar Clase, Cerrar Aula)
 * - Centro de Configuración Avanzado en 3 Pestañas (Apariencia, Preferencias de Aula, Mi Perfil)
 * - Panel de Administración para rol Admin (Usuarios, Códigos IR, Capturas KY-022 y Auditoría)
 */

(function () {
    'use strict';

    // Metadatos de sesión inyectados en el DOM
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const userRole = document.querySelector('meta[name="user-role"]')?.getAttribute('content') || 'profesor';
    const currentUserId = parseInt(document.querySelector('meta[name="user-id"]')?.getAttribute('content') || '0', 10);
    const esAdmin = (userRole === 'admin');

    // Multi-Aula: Selector de Cabecera y Tablero Administrativo
    const selectAulaHeader = document.getElementById('selectAulaHeader');
    const tableroAulasGrid = document.getElementById('tableroAulasGrid');
    const tableroAulasFeedback = document.getElementById('tableroAulasFeedback');
    const btnApagarTodasAulas = document.getElementById('btnApagarTodasAulas');

    const tablaAulasBody = document.getElementById('tablaAulasBody');
    const formAula = document.getElementById('formAula');
    const aulaFormFeedback = document.getElementById('aulaFormFeedback');
    const btnLimpiarFormAula = document.getElementById('btnLimpiarFormAula');
    const tituloFormAula = document.getElementById('tituloFormAula');
    const textoBtnGuardarAula = document.getElementById('textoBtnGuardarAula');
    const aulaIdInput = document.getElementById('aulaIdInput');
    const aulaNombre = document.getElementById('aulaNombre');
    const aulaTipo = document.getElementById('aulaTipo');
    const aulaUbicacion = document.getElementById('aulaUbicacion');
    const aulaUsuario = document.getElementById('aulaUsuario');
    const aulaPassword = document.getElementById('aulaPassword');
    const labelAulaPassword = document.getElementById('labelAulaPassword');
    const aulaClonarCodigos = document.getElementById('aulaClonarCodigos');
    const contenedorClonarCodigos = document.getElementById('contenedorClonarCodigos');

    const checkAulasUsuarioContainer = document.getElementById('checkAulasUsuarioContainer');
    const filtroCodigosAula = document.getElementById('filtroCodigosAula');
    const codAulaId = document.getElementById('codAulaId');

    let aulaActualId = parseInt(localStorage.getItem('control_aula_activa_id') || '1', 10);
    if (window.AULA_SESION_ID && (userRole === 'aula' || !esAdmin)) {
        aulaActualId = parseInt(window.AULA_SESION_ID, 10);
    }
    let listaAulas = [];

    // Cabecera y Estado de Conexión
    const headerNombreUsuario = document.getElementById('headerNombreUsuario');
    const badgeConexion = document.getElementById('badgeConexion');
    const textoConexion = document.getElementById('textoConexion');

    // Escenas Rápidas
    const escenasFeedback = document.getElementById('escenasFeedback');
    const descIniciarClase = document.getElementById('descIniciarClase');

    // Controles de Luces
    const pastillaLuces = document.getElementById('pastillaLuces');
    const iconoLuces = document.getElementById('iconoLuces');
    const btnLucesOn = document.getElementById('btnLucesOn');
    const btnLucesOff = document.getElementById('btnLucesOff');

    // Controles del Termostato de Aire Acondicionado
    const pastillaAire = document.getElementById('pastillaAire');
    const iconoAire = document.getElementById('iconoAire');
    const colaAireInfo = document.getElementById('colaAireInfo');
    const termostatoDisplay = document.getElementById('termostatoDisplay');
    const aireTempValor = document.getElementById('aireTempValor');
    const aireEstadoSub = document.getElementById('aireEstadoSub');
    const sliderAireTemp = document.getElementById('sliderAireTemp');
    const btnTempMenos = document.getElementById('btnTempMenos');
    const btnTempMas = document.getElementById('btnTempMas');
    const chipAireModo = document.getElementById('chipAireModo');
    const chipAireFan = document.getElementById('chipAireFan');
    const chipAireSwing = document.getElementById('chipAireSwing');
    const labelModoActivo = document.getElementById('labelModoActivo');
    const labelFanActivo = document.getElementById('labelFanActivo');
    const btnAireOn = document.getElementById('btnAireOn');
    const btnAireOff = document.getElementById('btnAireOff');
    const btnAireSwing = document.getElementById('btnAireSwing');
    const btnPresetEco = document.getElementById('btnPresetEco');
    const btnPresetMaxFrio = document.getElementById('btnPresetMaxFrio');
    const btnPresetCalor = document.getElementById('btnPresetCalor');

    // Controles del Proyector Multimedia
    const pastillaProyector = document.getElementById('pastillaProyector');
    const iconoProyector = document.getElementById('iconoProyector');
    const colaProyectorInfo = document.getElementById('colaProyectorInfo');
    const btnProyectorOn = document.getElementById('btnProyectorOn');
    const btnProyectorOff = document.getElementById('btnProyectorOff');
    const labelProyFuente = document.getElementById('labelProyFuente');
    const btnProyFreeze = document.getElementById('btnProyFreeze');
    const btnProyMute = document.getElementById('btnProyMute');
    const proyVolTexto = document.getElementById('proyVolTexto');
    const btnProyVolMenos = document.getElementById('btnProyVolMenos');
    const btnProyVolMute = document.getElementById('btnProyVolMute');
    const btnProyVolMas = document.getElementById('btnProyVolMas');
    const sliderProyVol = document.getElementById('sliderProyVol');
    let volumenPrevioProyector = 50;
    let debounceVolTimer = null;

    // Telemetría y Panel Admin (presente solo en vista Admin)
    const telIp = document.getElementById('telIp');
    const telRssi = document.getElementById('telRssi');
    const telUptime = document.getElementById('telUptime');
    const telFw = document.getElementById('telFw');
    const telContacto = document.getElementById('telContacto');

    const btnToggleAdminPanel = document.getElementById('btnToggleAdminPanel');
    const contenedorAdminTecnico = document.getElementById('contenedorAdminTecnico');
    const textoToggleAdmin = document.getElementById('textoToggleAdmin');
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabContenidos = document.querySelectorAll('.tab-contenido');

    // Tablas y Formularios Administrativos
    const tablaUsuariosBody = document.getElementById('tablaUsuariosBody');
    const formUsuario = document.getElementById('formUsuario');
    const usuarioFormFeedback = document.getElementById('usuarioFormFeedback');
    const btnLimpiarFormUsuario = document.getElementById('btnLimpiarFormUsuario');
    const tituloFormUsuario = document.getElementById('tituloFormUsuario');
    const textoBtnGuardarUsuario = document.getElementById('textoBtnGuardarUsuario');
    const labelUsrPassword = document.getElementById('labelUsrPassword');

    const tablaCodigosBody = document.getElementById('tablaCodigosBody');
    const tablaCapturasBody = document.getElementById('tablaCapturasBody');
    const tablaLogBody = document.getElementById('tablaLogBody');
    const formCodigo = document.getElementById('formCodigo');
    const formFeedback = document.getElementById('formFeedback');
    const btnLimpiarFormCodigo = document.getElementById('btnLimpiarFormCodigo');
    const capturaAsignarFeedback = document.getElementById('capturaAsignarFeedback');

    // Modal del Centro de Configuración
    const modalConfigApariencia = document.getElementById('modalConfigApariencia');
    const btnAbrirConfig = document.getElementById('btnAbrirConfig');
    const btnCerrarConfig = document.getElementById('btnCerrarConfig');
    const btnCerrarConfigFooter = document.getElementById('btnCerrarConfigFooter');
    const btnResetApariencia = document.getElementById('btnResetApariencia');
    const inputColorCustom = document.getElementById('inputColorCustom');
    const btnAplicarColorCustom = document.getElementById('btnAplicarColorCustom');
    const sliderEscalaTexto = document.getElementById('sliderEscalaTexto');
    const labelEscalaPorcentaje = document.getElementById('labelEscalaPorcentaje');
    const chkAltoContraste = document.getElementById('chkAltoContraste');
    const chkReducirAnim = document.getElementById('chkReducirAnim');
    const prefAireTemp = document.getElementById('prefAireTemp');
    const prefAireModo = document.getElementById('prefAireModo');
    const chkConfirmarApagado = document.getElementById('chkConfirmarApagado');
    const chkSonidoBeep = document.getElementById('chkSonidoBeep');
    const prefIntervaloSync = document.getElementById('prefIntervaloSync');

    // Formularios de Mi Perfil
    const formPerfilNombre = document.getElementById('formPerfilNombre');
    const inputPerfilNombre = document.getElementById('inputPerfilNombre');
    const perfilNombreFeedback = document.getElementById('perfilNombreFeedback');
    const formPerfilPassword = document.getElementById('formPerfilPassword');
    const perfilPasswordFeedback = document.getElementById('perfilPasswordFeedback');

    // Estado local sincronizado de equipos
    let estadoAire = {
        encendido: false,
        temp: 24,
        modo: 'frio',
        fan: 'auto',
        swing: true
    };

    let estadoProyector = {
        encendido: false,
        fuente: 'hdmi1',
        freeze: false,
        mute: false,
        volumen: 50
    };

    let actualizacionEnCurso = false;
    let timerPolling = null;
    let audioCtx = null;

    // -------------------------------------------------------------------------
    // 1. GENERADOR DE BEEP SUAVE (WEB AUDIO API SIN ARCHIVOS EXTERNOS)
    // -------------------------------------------------------------------------
    function emitirBeep(frecuencia = 780, duracionMs = 55) {
        if (!prefsActuales || !prefsActuales.sonidoBeep) return;
        try {
            const AudioContextClass = window.AudioContext || window.webkitAudioContext;
            if (!AudioContextClass) return;
            if (!audioCtx) {
                audioCtx = new AudioContextClass();
            }
            if (audioCtx.state === 'suspended') {
                audioCtx.resume();
            }
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(frecuencia, audioCtx.currentTime);
            gain.gain.setValueAtTime(0.06, audioCtx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + duracionMs / 1000);
            osc.connect(gain);
            gain.connect(audioCtx.destination);
            osc.start();
            osc.stop(audioCtx.currentTime + duracionMs / 1000);
        } catch (e) {
            // Ignorar si el navegador bloquea audio antes de interacción
        }
    }

    // -------------------------------------------------------------------------
    // 2. CENTRO DE CONFIGURACIÓN Y PREFERENCIAS (LOCALSTORAGE)
    // -------------------------------------------------------------------------
    const PREFS_KEY = 'control_aula_prefs';
    const PREFS_DEFAULT = {
        tema: 'oscuro',           // 'oscuro' | 'claro' | 'auto'
        acento: 'dorado',         // 'dorado' | 'azul' | 'esmeralda' | 'violeta' | 'coral' | 'cian' | 'custom'
        colorCustom: '#897c37',
        fuente: 'sans',           // 'sans' | 'serif' | 'mono'
        escalaPct: 100,           // 85 a 130
        densidad: 'comoda',       // 'comoda' | 'compacta'
        altoContraste: false,
        reducirAnimaciones: false,
        prefAireTemp: 24,
        prefAireModo: 'frio',
        confirmarApagado: true,
        sonidoBeep: true,
        intervaloSync: 3000       // 3000 | 5000 | 10000
    };

    function obtenerPreferencias() {
        try {
            const local = JSON.parse(localStorage.getItem(PREFS_KEY) || '{}');
            const servidor = (typeof window.PREFS_SERVIDOR_DB === 'object' && window.PREFS_SERVIDOR_DB !== null)
                ? window.PREFS_SERVIDOR_DB : {};
            const guardado = Object.assign({}, local, servidor);

            let modoPref = guardado.prefAireModo || PREFS_DEFAULT.prefAireModo;
            if (modoPref === 'deshumidificador') modoPref = 'seco';

            return {
                tema: ['oscuro', 'claro', 'auto'].includes(guardado.tema) ? guardado.tema : PREFS_DEFAULT.tema,
                acento: ['dorado', 'azul', 'esmeralda', 'violeta', 'coral', 'cian', 'custom'].includes(guardado.acento)
                    ? guardado.acento : PREFS_DEFAULT.acento,
                colorCustom: /^#[0-9a-fA-F]{6}$/.test(guardado.colorCustom || '')
                    ? guardado.colorCustom : PREFS_DEFAULT.colorCustom,
                fuente: ['sans', 'serif', 'mono'].includes(guardado.fuente) ? guardado.fuente : PREFS_DEFAULT.fuente,
                escalaPct: Math.max(85, Math.min(130, parseInt(guardado.escalaPct, 10) || PREFS_DEFAULT.escalaPct)),
                densidad: ['comoda', 'compacta'].includes(guardado.densidad) ? guardado.densidad : PREFS_DEFAULT.densidad,
                altoContraste: Boolean(guardado.altoContraste),
                reducirAnimaciones: Boolean(guardado.reducirAnimaciones),
                prefAireTemp: Math.max(16, Math.min(30, parseInt(guardado.prefAireTemp, 10) || PREFS_DEFAULT.prefAireTemp)),
                prefAireModo: ['frio', 'calor', 'auto', 'ventilacion', 'seco'].includes(modoPref)
                    ? modoPref : PREFS_DEFAULT.prefAireModo,
                confirmarApagado: guardado.confirmarApagado !== undefined ? Boolean(guardado.confirmarApagado) : PREFS_DEFAULT.confirmarApagado,
                sonidoBeep: guardado.sonidoBeep !== undefined ? Boolean(guardado.sonidoBeep) : PREFS_DEFAULT.sonidoBeep,
                intervaloSync: [3000, 5000, 10000].includes(parseInt(guardado.intervaloSync, 10))
                    ? parseInt(guardado.intervaloSync, 10) : PREFS_DEFAULT.intervaloSync
            };
        } catch (e) {
            return { ...PREFS_DEFAULT };
        }
    }

    async function guardarPreferenciasEnServidor(prefs, esReset = false) {
        try {
            await fetch('api/preferencias.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({
                    reset: esReset ? 1 : 0,
                    preferencias: prefs,
                    csrf_token: csrfToken
                })
            });
        } catch (err) {
            console.error('Error al sincronizar preferencias en MySQL:', err);
        }
    }

    function hexToRgba(hex, alpha) {
        const limpio = hex.replace('#', '');
        const r = parseInt(limpio.substring(0, 2), 16) || 137;
        const g = parseInt(limpio.substring(2, 4), 16) || 124;
        const b = parseInt(limpio.substring(4, 6), 16) || 55;
        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    }

    function aplicarPreferencias(prefs, reiniciarTimer = false, guardarEnDB = true, esReset = false) {
        const root = document.documentElement;

        // 1. Resolver tema (oscuro, claro o automático del sistema)
        let temaEfectivo = prefs.tema;
        if (temaEfectivo === 'auto') {
            temaEfectivo = (window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches)
                ? 'claro' : 'oscuro';
        }
        root.setAttribute('data-theme', temaEfectivo);

        // 2. Paleta de acento o color personalizado
        root.setAttribute('data-accent', prefs.acento);
        if (prefs.acento === 'custom' && prefs.colorCustom) {
            root.style.setProperty('--color-primary', prefs.colorCustom);
            root.style.setProperty('--color-primary-hover', prefs.colorCustom);
            root.style.setProperty('--color-primary-glow', hexToRgba(prefs.colorCustom, 0.28));
            root.style.setProperty('--color-primary-bg', hexToRgba(prefs.colorCustom, 0.15));
            root.style.setProperty('--border-highlight', hexToRgba(prefs.colorCustom, 0.45));
            root.style.setProperty('--text-gold', prefs.colorCustom);
        } else {
            root.style.removeProperty('--color-primary');
            root.style.removeProperty('--color-primary-hover');
            root.style.removeProperty('--color-primary-glow');
            root.style.removeProperty('--color-primary-bg');
            root.style.removeProperty('--border-highlight');
            root.style.removeProperty('--text-gold');
        }

        // 3. Fuente, escala, densidad y accesibilidad
        root.setAttribute('data-font', prefs.fuente);
        root.style.setProperty('--ui-scale', (prefs.escalaPct / 100).toFixed(2));
        root.setAttribute('data-density', prefs.densidad);
        root.setAttribute('data-contrast', prefs.altoContraste ? 'alto' : 'normal');
        root.setAttribute('data-motion', prefs.reducirAnimaciones ? 'reducido' : 'normal');

        // Persistir en localStorage y en MySQL (api/preferencias.php)
        try {
            localStorage.setItem(PREFS_KEY, JSON.stringify(prefs));
        } catch (e) {}

        if (guardarEnDB) {
            guardarPreferenciasEnServidor(prefs, esReset);
        }

        // Sincronizar controles visuales del modal
        document.querySelectorAll('[data-pref-tema]').forEach(btn => {
            btn.classList.toggle('activo', btn.getAttribute('data-pref-tema') === prefs.tema);
        });
        document.querySelectorAll('[data-pref-acento]').forEach(btn => {
            btn.classList.toggle('activo', btn.getAttribute('data-pref-acento') === prefs.acento);
        });
        document.querySelectorAll('[data-pref-fuente]').forEach(btn => {
            btn.classList.toggle('activo', btn.getAttribute('data-pref-fuente') === prefs.fuente);
        });
        document.querySelectorAll('[data-pref-densidad]').forEach(btn => {
            btn.classList.toggle('activo', btn.getAttribute('data-pref-densidad') === prefs.densidad);
        });

        if (inputColorCustom) inputColorCustom.value = prefs.colorCustom;
        if (sliderEscalaTexto) sliderEscalaTexto.value = String(prefs.escalaPct);
        if (labelEscalaPorcentaje) labelEscalaPorcentaje.textContent = `${prefs.escalaPct}%`;
        if (chkAltoContraste) chkAltoContraste.checked = prefs.altoContraste;
        if (chkReducirAnim) chkReducirAnim.checked = prefs.reducirAnimaciones;
        if (prefAireTemp) prefAireTemp.value = String(prefs.prefAireTemp);
        if (prefAireModo) prefAireModo.value = prefs.prefAireModo === 'deshumidificador' ? 'seco' : prefs.prefAireModo;
        if (chkConfirmarApagado) chkConfirmarApagado.checked = prefs.confirmarApagado;
        if (chkSonidoBeep) chkSonidoBeep.checked = prefs.sonidoBeep;
        if (prefIntervaloSync) prefIntervaloSync.value = String(prefs.intervaloSync);

        // Actualizar texto descriptivo de la escena "Iniciar Clase"
        if (descIniciarClase) {
            const mapaModos = {
                frio: 'Frío',
                calor: 'Calor',
                auto: 'Auto',
                ventilacion: 'Ventilación',
                seco: 'Seco / Dry',
                deshumidificador: 'Seco / Dry'
            };
            descIniciarClase.textContent = `Enciende luces y aire en ${prefs.prefAireTemp}°C (${mapaModos[prefs.prefAireModo] || 'Frío'})`;
        }

        if (reiniciarTimer) {
            iniciarTimerPolling();
        }
    }

    let prefsActuales = obtenerPreferencias();
    aplicarPreferencias(prefsActuales, false, false);

    // Escuchar cambios del tema del sistema si está en "auto"
    if (window.matchMedia) {
        const mq = window.matchMedia('(prefers-color-scheme: light)');
        if (mq.addEventListener) {
            mq.addEventListener('change', () => {
                if (prefsActuales.tema === 'auto') {
                    aplicarPreferencias(prefsActuales, false);
                }
            });
        }
    }

    // Abrir / Cerrar Modal de Configuración
    if (btnAbrirConfig && modalConfigApariencia) {
        btnAbrirConfig.addEventListener('click', () => {
            emitirBeep(820, 45);
            modalConfigApariencia.classList.add('activo');
        });
    }

    function cerrarModalConfig() {
        if (modalConfigApariencia) {
            modalConfigApariencia.classList.remove('activo');
        }
    }

    if (btnCerrarConfig) btnCerrarConfig.addEventListener('click', cerrarModalConfig);
    if (btnCerrarConfigFooter) btnCerrarConfigFooter.addEventListener('click', cerrarModalConfig);
    if (modalConfigApariencia) {
        modalConfigApariencia.addEventListener('click', (e) => {
            if (e.target === modalConfigApariencia) cerrarModalConfig();
        });
    }

    // Navegación entre las 3 pestañas del modal de configuración
    document.querySelectorAll('[data-cfg-tab]').forEach(btn => {
        btn.addEventListener('click', () => {
            const targetId = btn.getAttribute('data-cfg-tab');
            document.querySelectorAll('[data-cfg-tab]').forEach(b => {
                b.classList.toggle('activo', b === btn);
            });
            document.querySelectorAll('.cfg-tab-pane').forEach(pane => {
                pane.classList.toggle('activo', pane.id === targetId);
            });
        });
    });

    // Eventos de Pestaña 1: Apariencia y Accesibilidad
    document.querySelectorAll('[data-pref-tema]').forEach(btn => {
        btn.addEventListener('click', () => {
            prefsActuales.tema = btn.getAttribute('data-pref-tema');
            aplicarPreferencias(prefsActuales);
        });
    });

    document.querySelectorAll('[data-pref-acento]').forEach(btn => {
        btn.addEventListener('click', () => {
            prefsActuales.acento = btn.getAttribute('data-pref-acento');
            aplicarPreferencias(prefsActuales);
        });
    });

    if (inputColorCustom) {
        inputColorCustom.addEventListener('input', () => {
            prefsActuales.colorCustom = inputColorCustom.value;
            prefsActuales.acento = 'custom';
            aplicarPreferencias(prefsActuales);
        });
    }

    if (btnAplicarColorCustom && inputColorCustom) {
        btnAplicarColorCustom.addEventListener('click', () => {
            prefsActuales.colorCustom = inputColorCustom.value;
            prefsActuales.acento = 'custom';
            aplicarPreferencias(prefsActuales);
        });
    }

    document.querySelectorAll('[data-pref-fuente]').forEach(btn => {
        btn.addEventListener('click', () => {
            prefsActuales.fuente = btn.getAttribute('data-pref-fuente');
            aplicarPreferencias(prefsActuales);
        });
    });

    if (sliderEscalaTexto) {
        sliderEscalaTexto.addEventListener('input', () => {
            prefsActuales.escalaPct = parseInt(sliderEscalaTexto.value, 10) || 100;
            aplicarPreferencias(prefsActuales);
        });
    }

    document.querySelectorAll('[data-pref-densidad]').forEach(btn => {
        btn.addEventListener('click', () => {
            prefsActuales.densidad = btn.getAttribute('data-pref-densidad');
            aplicarPreferencias(prefsActuales);
        });
    });

    if (chkAltoContraste) {
        chkAltoContraste.addEventListener('change', () => {
            prefsActuales.altoContraste = chkAltoContraste.checked;
            aplicarPreferencias(prefsActuales);
        });
    }

    if (chkReducirAnim) {
        chkReducirAnim.addEventListener('change', () => {
            prefsActuales.reducirAnimaciones = chkReducirAnim.checked;
            aplicarPreferencias(prefsActuales);
        });
    }

    // Eventos de Pestaña 2: Preferencias de Aula
    if (prefAireTemp) {
        prefAireTemp.addEventListener('change', () => {
            prefsActuales.prefAireTemp = parseInt(prefAireTemp.value, 10) || 24;
            aplicarPreferencias(prefsActuales);
        });
    }

    if (prefAireModo) {
        prefAireModo.addEventListener('change', () => {
            prefsActuales.prefAireModo = prefAireModo.value;
            aplicarPreferencias(prefsActuales);
        });
    }

    if (chkConfirmarApagado) {
        chkConfirmarApagado.addEventListener('change', () => {
            prefsActuales.confirmarApagado = chkConfirmarApagado.checked;
            aplicarPreferencias(prefsActuales);
        });
    }

    if (chkSonidoBeep) {
        chkSonidoBeep.addEventListener('change', () => {
            prefsActuales.sonidoBeep = chkSonidoBeep.checked;
            aplicarPreferencias(prefsActuales);
            if (prefsActuales.sonidoBeep) emitirBeep(880, 60);
        });
    }

    if (prefIntervaloSync) {
        prefIntervaloSync.addEventListener('change', () => {
            prefsActuales.intervaloSync = parseInt(prefIntervaloSync.value, 10) || 3000;
            aplicarPreferencias(prefsActuales, true);
        });
    }

    if (btnResetApariencia) {
        btnResetApariencia.addEventListener('click', () => {
            prefsActuales = { ...PREFS_DEFAULT };
            aplicarPreferencias(prefsActuales, true, true, true);
        });
    }

    // Eventos de Pestaña 3: Mi Perfil y Seguridad (api/perfil.php)
    if (formPerfilNombre) {
        formPerfilNombre.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (perfilNombreFeedback) {
                perfilNombreFeedback.textContent = '';
                perfilNombreFeedback.className = '';
            }
            const nuevoNombre = (inputPerfilNombre?.value || '').trim();
            if (!nuevoNombre) return;

            try {
                const res = await fetch('api/perfil.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify({
                        operacion: 'actualizar_perfil',
                        nombre_completo: nuevoNombre,
                        csrf_token: csrfToken
                    })
                });
                const data = await res.json();
                if (data.ok) {
                    if (headerNombreUsuario) {
                        headerNombreUsuario.textContent = data.nombre_completo || nuevoNombre;
                    }
                    if (perfilNombreFeedback) {
                        perfilNombreFeedback.className = 'alerta alerta-exito';
                        perfilNombreFeedback.textContent = data.mensaje || 'Nombre actualizado correctamente';
                    }
                    if (esAdmin) {
                        cargarUsuarios();
                        cargarLog();
                    }
                } else if (perfilNombreFeedback) {
                    perfilNombreFeedback.className = 'alerta alerta-error';
                    perfilNombreFeedback.textContent = data.error || 'No se pudo actualizar el nombre';
                }
            } catch (err) {
                if (perfilNombreFeedback) {
                    perfilNombreFeedback.className = 'alerta alerta-error';
                    perfilNombreFeedback.textContent = 'Error de conexión con el servidor';
                }
            }
        });
    }

    if (formPerfilPassword) {
        formPerfilPassword.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (perfilPasswordFeedback) {
                perfilPasswordFeedback.textContent = '';
                perfilPasswordFeedback.className = '';
            }

            const passwordActual = document.getElementById('inputPassActual')?.value || '';
            const passwordNueva = document.getElementById('inputPassNueva')?.value || '';
            const passwordConfirmar = document.getElementById('inputPassConfirmar')?.value || '';

            try {
                const res = await fetch('api/perfil.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify({
                        operacion: 'cambiar_password',
                        password_actual: passwordActual,
                        password_nueva: passwordNueva,
                        password_confirmar: passwordConfirmar,
                        csrf_token: csrfToken
                    })
                });
                const data = await res.json();
                if (data.ok) {
                    formPerfilPassword.reset();
                    if (perfilPasswordFeedback) {
                        perfilPasswordFeedback.className = 'alerta alerta-exito';
                        perfilPasswordFeedback.textContent = data.mensaje || 'Contraseña actualizada correctamente';
                    }
                    if (esAdmin) cargarLog();
                } else if (perfilPasswordFeedback) {
                    perfilPasswordFeedback.className = 'alerta alerta-error';
                    perfilPasswordFeedback.textContent = data.error || 'No se pudo actualizar la contraseña';
                }
            } catch (err) {
                if (perfilPasswordFeedback) {
                    perfilPasswordFeedback.className = 'alerta alerta-error';
                    perfilPasswordFeedback.textContent = 'Error de conexión con el servidor';
                }
            }
        });
    }

    // -------------------------------------------------------------------------
    // 3. SECCIÓN COLAPSABLE DE ADMINISTRACIÓN
    // -------------------------------------------------------------------------
    if (btnToggleAdminPanel && contenedorAdminTecnico) {
        btnToggleAdminPanel.addEventListener('click', () => {
            const colapsado = contenedorAdminTecnico.classList.toggle('colapsado');
            btnToggleAdminPanel.setAttribute('aria-expanded', colapsado ? 'false' : 'true');
            if (textoToggleAdmin) {
                textoToggleAdmin.textContent = colapsado ? 'Mostrar herramientas' : 'Ocultar herramientas';
            }
        });
    }

    // -------------------------------------------------------------------------
    // 4. FORMATEADORES DE DATOS
    // -------------------------------------------------------------------------
    function formatearUptime(segundos) {
        if (!segundos || segundos < 0) return '0s';
        const d = Math.floor(segundos / 86400);
        const h = Math.floor((segundos % 86400) / 3600);
        const m = Math.floor((segundos % 3600) / 60);
        const s = segundos % 60;
        if (d > 0) return `${d}d ${h}h ${m}m`;
        if (h > 0) return `${h}h ${m}m ${s}s`;
        if (m > 0) return `${m}m ${s}s`;
        return `${s}s`;
    }

    function formatearRssi(rssi) {
        if (rssi === null || rssi === undefined) return '--';
        return `${rssi} dBm`;
    }

    function formatearFecha(cadenaFecha) {
        if (!cadenaFecha) return '--';
        const f = new Date(cadenaFecha.replace(' ', 'T'));
        return f.toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    }

    function escapeHtml(texto) {
        if (!texto) return '';
        const div = document.createElement('div');
        div.textContent = texto;
        return div.innerHTML;
    }

    // -------------------------------------------------------------------------
    // 5. RENDERIZADO VISUAL DEL TERMOSTATO Y PROYECTOR
    // -------------------------------------------------------------------------
    const INFO_MODOS_AIRE = {
        frio: { texto: 'Frío (Cool)', chip: 'Frío (Cool)', clase: 'modo-frio' },
        calor: { texto: 'Calor (Heat)', chip: 'Calor (Heat)', clase: 'modo-calor' },
        ventilacion: { texto: 'Ventilación (Fan)', chip: 'Ventilación', clase: 'modo-ventilacion' },
        seco: { texto: 'Deshumidificador (Seco)', chip: 'Seco (Dry)', clase: 'modo-deshumidificador' },
        deshumidificador: { texto: 'Deshumidificador (Seco)', chip: 'Seco (Dry)', clase: 'modo-deshumidificador' },
        auto: { texto: 'Automático (Auto)', chip: 'Automático', clase: 'modo-auto' }
    };

    const INFO_FAN_AIRE = {
        auto: { texto: 'Automático', chip: 'Fan: Auto' },
        bajo: { texto: 'Velocidad Baja', chip: 'Fan: Bajo' },
        medio: { texto: 'Velocidad Media', chip: 'Fan: Medio' },
        alto: { texto: 'Alto / Turbo', chip: 'Fan: Turbo' }
    };

    function actualizarVistaTermostato() {
        const infoModo = INFO_MODOS_AIRE[estadoAire.modo] || INFO_MODOS_AIRE.frio;
        const infoFan = INFO_FAN_AIRE[estadoAire.fan] || INFO_FAN_AIRE.auto;

        if (pastillaAire) {
            pastillaAire.className = `estado-pastilla ${estadoAire.encendido ? 'on' : 'off'}`;
            pastillaAire.textContent = estadoAire.encendido ? `ENCENDIDO · ${estadoAire.temp}°C` : 'APAGADO';
        }

        if (iconoAire) {
            iconoAire.classList.toggle('aire-on', estadoAire.encendido);
        }

        if (termostatoDisplay) {
            termostatoDisplay.className = `termostato-display ${infoModo.clase} ${estadoAire.encendido ? '' : 'aire-apagado'}`.trim();
        }

        if (aireTempValor) {
            aireTempValor.textContent = `${estadoAire.temp}°C`;
        }

        if (aireEstadoSub) {
            aireEstadoSub.textContent = estadoAire.encendido
                ? `CLIMATIZANDO EN ${infoModo.texto.toUpperCase()}`
                : 'CLIMATIZADOR EN ESPERA (APAGADO)';
        }

        if (sliderAireTemp && document.activeElement !== sliderAireTemp) {
            sliderAireTemp.value = String(estadoAire.temp);
        }

        if (chipAireModo) chipAireModo.textContent = infoModo.chip;
        if (chipAireFan) chipAireFan.textContent = infoFan.chip;
        if (chipAireSwing) chipAireSwing.textContent = estadoAire.swing ? 'Swing: Activo' : 'Swing: Fijo';

        if (labelModoActivo) labelModoActivo.textContent = infoModo.texto;
        if (labelFanActivo) labelFanActivo.textContent = infoFan.texto;

        const modoNorm = estadoAire.modo === 'deshumidificador' ? 'seco' : estadoAire.modo;
        document.querySelectorAll('[data-aire-modo]').forEach(btn => {
            btn.classList.toggle('activo', btn.getAttribute('data-aire-modo') === modoNorm);
        });

        document.querySelectorAll('[data-aire-fan]').forEach(btn => {
            btn.classList.toggle('activo', btn.getAttribute('data-aire-fan') === estadoAire.fan);
        });

        if (btnAireSwing) {
            btnAireSwing.classList.toggle('activo', estadoAire.swing);
        }
    }

    function actualizarVistaProyector() {
        if (pastillaProyector) {
            pastillaProyector.className = `estado-pastilla ${estadoProyector.encendido ? 'on' : 'off'}`;
            pastillaProyector.textContent = estadoProyector.encendido
                ? `ENCENDIDO · ${estadoProyector.fuente.toUpperCase()}`
                : 'APAGADO';
        }

        if (iconoProyector) {
            iconoProyector.classList.toggle('proyector-on', estadoProyector.encendido);
        }

        if (labelProyFuente) {
            labelProyFuente.textContent = estadoProyector.fuente.toUpperCase();
        }

        document.querySelectorAll('[data-proy-fuente]').forEach(btn => {
            btn.classList.toggle('activo', btn.getAttribute('data-proy-fuente') === estadoProyector.fuente);
        });

        if (btnProyFreeze) {
            btnProyFreeze.classList.toggle('activo', estadoProyector.freeze);
            btnProyFreeze.innerHTML = estadoProyector.freeze
                ? '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg><span>Reanudar Imagen (Freeze ON)</span>'
                : '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="4" width="4" height="16"></rect><rect x="14" y="4" width="4" height="16"></rect></svg><span>Congelar Imagen</span>';
        }

        if (btnProyMute) {
            btnProyMute.classList.toggle('activo', estadoProyector.mute);
            btnProyMute.innerHTML = estadoProyector.mute
                ? '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"></circle></svg><span>Mostrar Imagen (AV Mute ON)</span>'
                : '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"></rect></svg><span>Pantalla en Negro</span>';
        }

        const esMuted = (estadoProyector.volumen === 0);
        if (proyVolTexto) {
            proyVolTexto.textContent = esMuted ? 'MUTE' : `${estadoProyector.volumen}%`;
            proyVolTexto.classList.toggle('muted', esMuted);
        }

        if (sliderProyVol && document.activeElement !== sliderProyVol) {
            sliderProyVol.value = String(estadoProyector.volumen);
            sliderProyVol.classList.toggle('is-muted', esMuted);
        }

        if (btnProyVolMute) {
            btnProyVolMute.classList.toggle('activo', esMuted);
            btnProyVolMute.title = esMuted ? 'Restaurar audio (Activo: Silenciado)' : 'Silenciar audio (Mute)';
        }

        document.querySelectorAll('.btn-vol-pill').forEach(pill => {
            const val = parseInt(pill.dataset.vol, 10);
            pill.classList.toggle('activo', !esMuted && estadoProyector.volumen === val);
        });
    }

    // -------------------------------------------------------------------------
    // 6. SINCRONIZACIÓN DE ESTADO DEL AULA (POLLING CONFIGURABLE)
    // -------------------------------------------------------------------------
    async function consultarEstado() {
        if (actualizacionEnCurso) return;
        actualizacionEnCurso = true;

        try {
            const respuesta = await fetch(`api/estado.php?aula_id=${aulaActualId}`, {
                headers: { 'Accept': 'application/json' }
            });

            if (!respuesta.ok) {
                if (respuesta.status === 401) {
                    window.location.href = 'login.php';
                    return;
                }
                throw new Error(`HTTP ${respuesta.status}`);
            }

            const data = await respuesta.json();
            if (data && data.ok) {
                renderizarEstado(data);
            }
        } catch (error) {
            console.error('Error al actualizar estado:', error);
            actualizarIndicadorConexion(false, null);
        } finally {
            actualizacionEnCurso = false;
        }
    }

    function iniciarTimerPolling() {
        if (timerPolling) clearInterval(timerPolling);
        const intervalo = prefsActuales?.intervaloSync || 3000;
        timerPolling = setInterval(consultarEstado, intervalo);
    }

    function actualizarIndicadorConexion(online, segundos, nombreAula = '') {
        if (!badgeConexion || !textoConexion) return;
        const prefijo = nombreAula ? `${nombreAula.toUpperCase()} · ` : '';
        if (online) {
            badgeConexion.className = 'badge-conexion online';
            textoConexion.textContent = `${prefijo}ONLINE`;
            if (telContacto) telContacto.textContent = segundos !== null ? `hace ${segundos}s` : 'activo';
        } else {
            badgeConexion.className = 'badge-conexion offline';
            textoConexion.textContent = `${prefijo}OFFLINE`;
            if (telContacto) telContacto.textContent = segundos !== null ? `hace ${segundos}s` : 'sin señal';
        }
    }

    function renderizarEstado(data) {
        if (data.aula_id) {
            aulaActualId = parseInt(data.aula_id, 10);
            if (selectAulaHeader && String(selectAulaHeader.value) !== String(aulaActualId)) {
                selectAulaHeader.value = aulaActualId;
            }
        }

        actualizarIndicadorConexion(data.esp32_online, data.segundos_desde_contacto, data.aula_nombre || '');

        // Telemetría (solo si existe en el DOM para Admin)
        if (telIp) telIp.textContent = data.ip || '--';
        if (telRssi) telRssi.textContent = formatearRssi(data.rssi);
        if (telUptime) telUptime.textContent = formatearUptime(data.uptime);
        if (telFw) telFw.textContent = data.version_fw ? `v${data.version_fw}` : '--';

        // Badges de hardware real (Star AC y Epson)
        if (data.hardware) {
            const chipAireTexto = document.getElementById('hwChipAireTexto');
            if (chipAireTexto) {
                chipAireTexto.textContent = `${data.hardware.marca_aire || 'Star Series'} · ${data.hardware.protocolo_aire || 'Coolix OEM'}`;
            }
            const chipProyTexto = document.getElementById('hwChipProyectorTexto');
            if (chipProyTexto) {
                const protoTxt = (data.hardware.protocolo_proyector === 'EPSON_NEC') ? 'Protocolo NEC' : (data.hardware.protocolo_proyector || 'NEC');
                chipProyTexto.textContent = `${data.hardware.marca_proyector || 'Epson'} · ${protoTxt}`;
            }
        }

        // 1. Estado de Luces
        const lucesEncendidas = (data.luces === 1 || data.luces_reales === 1 || data.luces_deseadas === 1);
        if (pastillaLuces) {
            pastillaLuces.className = `estado-pastilla ${lucesEncendidas ? 'on' : 'off'}`;
            pastillaLuces.textContent = lucesEncendidas ? 'ENCENDIDAS' : 'APAGADAS';
        }
        if (iconoLuces) {
            iconoLuces.classList.toggle('luces-on', lucesEncendidas);
        }

        // 2. Estado Completo del Aire Acondicionado
        estadoAire.encendido = Boolean(data.aire_encendido);
        if (data.aire_temp) estadoAire.temp = parseInt(data.aire_temp, 10);
        if (data.aire_modo) estadoAire.modo = data.aire_modo;
        if (data.aire_fan) estadoAire.fan = data.aire_fan;
        if (data.aire_swing !== undefined) estadoAire.swing = Boolean(data.aire_swing);
        actualizarVistaTermostato();

        // 3. Estado Completo del Proyector
        estadoProyector.encendido = Boolean(data.proyector_encendido);
        if (data.proyector_fuente) estadoProyector.fuente = data.proyector_fuente;
        if (data.proyector_freeze !== undefined) estadoProyector.freeze = Boolean(data.proyector_freeze);
        if (data.proyector_mute !== undefined) estadoProyector.mute = Boolean(data.proyector_mute);
        if (data.proyector_volumen !== undefined) estadoProyector.volumen = parseInt(data.proyector_volumen, 10);
        actualizarVistaProyector();

        // Información de comandos pendientes en cola para el ESP32
        if (colaAireInfo && colaProyectorInfo) {
            if (data.comandos_ir_pendientes > 0) {
                colaAireInfo.style.display = 'block';
                colaAireInfo.textContent = `(${data.comandos_ir_pendientes} comando IR en cola para esta aula...)`;
                colaProyectorInfo.style.display = 'block';
                colaProyectorInfo.textContent = `(${data.comandos_ir_pendientes} comando IR en cola para esta aula...)`;
            } else {
                colaAireInfo.style.display = 'none';
                colaProyectorInfo.style.display = 'none';
            }
        }
    }

    // -------------------------------------------------------------------------
    // 7. ACCIONES DE CONTROL DEL AULA (LUCES, TERMOSTATO, PROYECTOR, ESCENAS)
    // -------------------------------------------------------------------------
    async function cambiarLuces(estadoDeseado) {
        emitirBeep(estadoDeseado === 1 ? 880 : 620, 55);
        if (btnLucesOn) btnLucesOn.disabled = true;
        if (btnLucesOff) btnLucesOff.disabled = true;

        try {
            const res = await fetch('api/luces.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({
                    aula_id: aulaActualId,
                    estado: estadoDeseado,
                    csrf_token: csrfToken
                })
            });

            const data = await res.json();
            if (data.ok) {
                await consultarEstado();
                if (esAdmin) {
                    cargarLog();
                    cargarAulas();
                }
            } else {
                alert(data.error || 'No se pudo cambiar el estado de las luces');
            }
        } catch (err) {
            console.error('Error al cambiar luces:', err);
            alert('Error de conexión al servidor');
        } finally {
            if (btnLucesOn) btnLucesOn.disabled = false;
            if (btnLucesOff) btnLucesOff.disabled = false;
        }
    }

    async function enviarControlAire(opciones) {
        emitirBeep(opciones.accion === 'apagar' ? 580 : 840, 50);

        const payload = {
            aula_id: aulaActualId,
            accion: opciones.accion || 'ajustar',
            temp: opciones.temp !== undefined ? opciones.temp : estadoAire.temp,
            modo: opciones.modo !== undefined ? opciones.modo : estadoAire.modo,
            fan: opciones.fan !== undefined ? opciones.fan : estadoAire.fan,
            swing: opciones.swing !== undefined ? (opciones.swing ? 1 : 0) : (estadoAire.swing ? 1 : 0),
            csrf_token: csrfToken
        };

        // Actualización optimista inmediata en la interfaz
        estadoAire.encendido = (payload.accion !== 'apagar');
        estadoAire.temp = payload.temp;
        estadoAire.modo = payload.modo;
        estadoAire.fan = payload.fan;
        estadoAire.swing = Boolean(payload.swing);
        actualizarVistaTermostato();

        try {
            const res = await fetch('api/aire.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (data.ok) {
                estadoAire.encendido = Boolean(data.aire_encendido);
                estadoAire.temp = parseInt(data.aire_temp, 10);
                estadoAire.modo = data.aire_modo;
                estadoAire.fan = data.aire_fan;
                estadoAire.swing = Boolean(data.aire_swing);
                actualizarVistaTermostato();
                await consultarEstado();
                if (esAdmin) {
                    cargarLog();
                    cargarAulas();
                }
            } else {
                alert(data.error || 'Error al controlar el aire acondicionado');
            }
        } catch (err) {
            console.error('Error en api/aire.php:', err);
            alert('Error de conexión al servidor');
        }
    }

    async function enviarControlProyector(accion, extra = {}) {
        if (accion === 'apagar' && prefsActuales.confirmarApagado) {
            if (!confirm('¿Confirmás que querés apagar el proyector del aula?')) {
                return;
            }
        }

        if (accion === 'apagar' && colaProyectorInfo) {
            colaProyectorInfo.style.display = 'block';
            colaProyectorInfo.textContent = 'Enviando doble pulso de apagado Epson (2 seg)...';
            setTimeout(() => {
                if (colaProyectorInfo && colaProyectorInfo.textContent === 'Enviando doble pulso de apagado Epson (2 seg)...') {
                    colaProyectorInfo.style.display = 'none';
                    colaProyectorInfo.textContent = '';
                }
            }, 4000);
        }

        emitirBeep(accion === 'apagar' ? 560 : 800, 55);

        try {
            const res = await fetch('api/proyector.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({
                    aula_id: aulaActualId,
                    accion: accion,
                    ...extra,
                    csrf_token: csrfToken
                })
            });
            const data = await res.json();
            if (data.ok) {
                estadoProyector.encendido = Boolean(data.proyector_encendido);
                estadoProyector.fuente = data.proyector_fuente || estadoProyector.fuente;
                estadoProyector.freeze = Boolean(data.proyector_freeze);
                estadoProyector.mute = Boolean(data.proyector_mute);
                estadoProyector.volumen = parseInt(data.proyector_volumen, 10);
                actualizarVistaProyector();
                await consultarEstado();
                if (esAdmin) {
                    cargarLog();
                    cargarAulas();
                }
            } else {
                alert(data.error || 'Error al controlar el proyector');
            }
        } catch (err) {
            console.error('Error en api/proyector.php:', err);
            alert('Error de conexión al servidor');
        }
    }

    async function ejecutarEscenaAula(escena) {
        if (escena === 'cerrar_aula' && prefsActuales.confirmarApagado) {
            if (!confirm('¿Querés ejecutar "Cerrar Aula"? Se apagarán las luces, el aire acondicionado y el proyector.')) {
                return;
            }
        }

        emitirBeep(920, 75);

        try {
            const res = await fetch('api/escenas.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({
                    aula_id: aulaActualId,
                    escena: escena,
                    aire_temp: prefsActuales.prefAireTemp,
                    aire_modo: prefsActuales.prefAireModo,
                    pref_aire_temp: prefsActuales.prefAireTemp,
                    pref_aire_modo: prefsActuales.prefAireModo,
                    csrf_token: csrfToken
                })
            });
            const data = await res.json();
            if (data.ok) {
                if (escenasFeedback) {
                    escenasFeedback.textContent = data.mensaje;
                    setTimeout(() => {
                        if (escenasFeedback) escenasFeedback.textContent = '';
                    }, 4000);
                }
                await consultarEstado();
                if (esAdmin) {
                    cargarLog();
                    cargarAulas();
                }
            } else {
                alert(data.error || 'No se pudo ejecutar la escena');
            }
        } catch (err) {
            console.error('Error en api/escenas.php:', err);
            alert('Error de conexión al servidor');
        }
    }

    // Enviar comando IR genérico desde la tabla de prueba del Admin
    async function enviarComandoDispositivo(dispositivo, accion) {
        emitirBeep(820, 50);
        try {
            const res = await fetch('api/ir.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({
                    aula_id: aulaActualId,
                    dispositivo: dispositivo,
                    accion: accion,
                    csrf_token: csrfToken
                })
            });

            const data = await res.json();
            if (data.ok) {
                await consultarEstado();
                if (esAdmin) cargarLog();
            } else {
                alert(data.error || `No se pudo enviar el comando a ${dispositivo}`);
            }
        } catch (err) {
            console.error(`Error al enviar comando a ${dispositivo}:`, err);
            alert('Error de conexión al servidor');
        }
    }

    // -------------------------------------------------------------------------
    // 8. GESTIÓN MULTI-AULA Y TABLERO EN TIEMPO REAL
    // -------------------------------------------------------------------------
    async function cargarAulas() {
        try {
            const res = await fetch('api/aulas.php');
            if (!res.ok) {
                if (res.status === 401) { window.location.href = 'login.php'; return; }
                return;
            }
            const data = await res.json();
            if (data.ok && Array.isArray(data.aulas)) {
                listaAulas = data.aulas;

                // Actualizar Selector de Cabecera
                if (selectAulaHeader) {
                    const idPrevio = selectAulaHeader.value ? parseInt(selectAulaHeader.value, 10) : aulaActualId;
                    selectAulaHeader.innerHTML = '';
                    listaAulas.forEach(a => {
                        const opt = document.createElement('option');
                        opt.value = a.id;
                        opt.textContent = `[${a.tipo || 'Aula'}] ${a.nombre} ${a.ubicacion ? '(' + a.ubicacion + ')' : ''}`;
                        selectAulaHeader.appendChild(opt);
                    });

                    // Seleccionar el aula activa guardada o la primera disponible
                    const existe = listaAulas.some(a => a.id === idPrevio);
                    aulaActualId = existe ? idPrevio : (listaAulas[0] ? listaAulas[0].id : 1);
                    selectAulaHeader.value = aulaActualId;
                    localStorage.setItem('control_aula_activa_id', aulaActualId);
                } else {
                    if (window.AULA_SESION_ID) {
                        aulaActualId = parseInt(window.AULA_SESION_ID, 10);
                    } else if (listaAulas[0]) {
                        aulaActualId = listaAulas[0].id;
                    }
                }

                // Actualizar selector clonar en formAula
                if (aulaClonarCodigos) {
                    const selClonPrev = aulaClonarCodigos.value;
                    aulaClonarCodigos.innerHTML = '<option value="0" selected>Clonar desde Plantilla Global</option><option value="-1">No clonar (comenzar en blanco)</option>';
                    listaAulas.forEach(a => {
                        const opt = document.createElement('option');
                        opt.value = a.id;
                        opt.textContent = `Clonar desde ${a.nombre} (${a.tipo || 'Aula'})`;
                        aulaClonarCodigos.appendChild(opt);
                    });
                    if (selClonPrev) aulaClonarCodigos.value = selClonPrev;
                }

                // Actualizar selector en formulario de códigos IR
                if (codAulaId) {
                    const selCodPrev = codAulaId.value;
                    codAulaId.innerHTML = '<option value="0">Plantilla Global (Todas las aulas)</option>';
                    listaAulas.forEach(a => {
                        const opt = document.createElement('option');
                        opt.value = a.id;
                        opt.textContent = `Aula: ${a.nombre}`;
                        codAulaId.appendChild(opt);
                    });
                    if (selCodPrev) codAulaId.value = selCodPrev;
                }

                // Actualizar filtro de códigos IR
                if (filtroCodigosAula) {
                    const selFiltroPrev = filtroCodigosAula.value;
                    filtroCodigosAula.innerHTML = '<option value="">Todas las Aulas y Globales</option><option value="0">Solo Plantilla Global</option>';
                    listaAulas.forEach(a => {
                        const opt = document.createElement('option');
                        opt.value = a.id;
                        opt.textContent = `Solo ${a.nombre}`;
                        filtroCodigosAula.appendChild(opt);
                    });
                    if (selFiltroPrev) filtroCodigosAula.value = selFiltroPrev;
                }

                // Actualizar checkboxes de asignación de aulas en gestión de usuarios
                if (checkAulasUsuarioContainer) {
                    renderizarCheckboxesAulasUsuario();
                }

                // Si es admin, renderizar el Tablero Multi-Aula y la tabla CRUD de aulas
                if (esAdmin) {
                    renderizarTableroAulas(listaAulas);
                    renderizarTablaAulas(listaAulas);
                }
            }
        } catch (e) {
            console.error('Error al cargar aulas:', e);
        }
    }

    function renderizarCheckboxesAulasUsuario(aulasSeleccionadas = 'todas') {
        if (!checkAulasUsuarioContainer) return;
        checkAulasUsuarioContainer.innerHTML = '';

        const esTodas = (aulasSeleccionadas === 'todas' || !aulasSeleccionadas);
        let idsPermitidos = [];
        if (!esTodas) {
            try {
                idsPermitidos = typeof aulasSeleccionadas === 'string' ? JSON.parse(aulasSeleccionadas) : aulasSeleccionadas;
            } catch (err) {
                idsPermitidos = [];
            }
        }

        const lblTodas = document.createElement('label');
        lblTodas.style.cssText = 'display:inline-flex; align-items:center; gap:6px; font-weight:700; cursor:pointer; margin-right:12px; color:var(--text-gold);';
        lblTodas.innerHTML = `<input type="checkbox" id="chkAulaTodas" ${esTodas ? 'checked' : ''}> Todas las aulas`;
        checkAulasUsuarioContainer.appendChild(lblTodas);

        listaAulas.forEach(a => {
            const chk = document.createElement('label');
            chk.style.cssText = 'display:inline-flex; align-items:center; gap:6px; cursor:pointer; margin-right:12px;';
            const marcado = esTodas || (Array.isArray(idsPermitidos) && idsPermitidos.includes(a.id));
            chk.innerHTML = `<input type="checkbox" class="chk-aula-item" value="${a.id}" ${marcado ? 'checked' : ''} ${esTodas ? 'disabled' : ''}> ${escapeHtml(a.nombre)}`;
            checkAulasUsuarioContainer.appendChild(chk);
        });

        const chkTodasElem = document.getElementById('chkAulaTodas');
        if (chkTodasElem) {
            chkTodasElem.addEventListener('change', () => {
                const checked = chkTodasElem.checked;
                document.querySelectorAll('.chk-aula-item').forEach(item => {
                    item.disabled = checked;
                    if (checked) item.checked = true;
                });
            });
        }
    }

    function renderizarTableroAulas(aulas) {
        if (!tableroAulasGrid) return;
        tableroAulasGrid.innerHTML = '';

        if (!aulas || aulas.length === 0) {
            tableroAulasGrid.innerHTML = '<div style="grid-column:1/-1; text-align:center; padding:20px; color:var(--text-secondary);">No hay aulas registradas en el sistema</div>';
            return;
        }

        aulas.forEach(a => {
            const card = document.createElement('div');
            const esAulaActiva = (a.id === aulaActualId);
            const onlineClase = a.online ? 'color:var(--color-success);' : 'color:var(--color-danger);';
            const onlineTexto = a.online ? 'ONLINE' : 'OFFLINE';

            card.style.cssText = `
                background: var(--bg-surface);
                border: 2px solid ${esAulaActiva ? 'var(--color-primary)' : 'var(--border-subtle)'};
                border-radius: var(--radius-md);
                padding: 16px;
                display: flex;
                flex-direction: column;
                gap: 12px;
                box-shadow: var(--shadow-card);
                position: relative;
            `;

            const est = a.estado || {};
            const lucesBadge = est.luces === 1 
                ? '<span style="background:rgba(234,179,8,0.2); color:#eab308; padding:3px 8px; border-radius:4px; font-size:11px; font-weight:700;">Luces ON</span>'
                : '<span style="background:rgba(255,255,255,0.06); color:var(--text-muted); padding:3px 8px; border-radius:4px; font-size:11px;">Luces OFF</span>';

            const aireBadge = est.aire_encendido === 1
                ? `<span style="background:rgba(59,130,246,0.2); color:#60a5fa; padding:3px 8px; border-radius:4px; font-size:11px; font-weight:700;">Aire ON · ${est.aire_temp}°C (${(est.aire_modo || 'frio').toUpperCase()})</span>`
                : '<span style="background:rgba(255,255,255,0.06); color:var(--text-muted); padding:3px 8px; border-radius:4px; font-size:11px;">Aire OFF</span>';

            const proyBadge = est.proyector_encendido === 1
                ? `<span style="background:rgba(168,85,247,0.2); color:#c084fc; padding:3px 8px; border-radius:4px; font-size:11px; font-weight:700;">Proyector ON · ${(est.proyector_fuente || 'hdmi1').toUpperCase()}</span>`
                : '<span style="background:rgba(255,255,255,0.06); color:var(--text-muted); padding:3px 8px; border-radius:4px; font-size:11px;">Proyector OFF</span>';

            const tipoTag = `<span class="rol-tag" style="font-size:10px; margin-left:6px;">${escapeHtml(a.tipo || 'Aula')}</span>`;

            card.innerHTML = `
                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                    <div>
                        <h4 style="margin:0; font-size:15px; font-weight:700; color:var(--text-primary); display:flex; align-items:center; gap:4px; flex-wrap:wrap;">
                            ${escapeHtml(a.nombre)}
                            ${tipoTag}
                            ${esAulaActiva ? '<span style="font-size:10px; background:var(--color-primary-bg); color:var(--text-gold); border:1px solid var(--border-highlight); padding:2px 6px; border-radius:4px;">ACTUAL</span>' : ''}
                        </h4>
                        <span style="font-size:12px; color:var(--text-secondary);">${escapeHtml(a.ubicacion || 'Sin ubicación')}</span>
                    </div>
                    <span style="font-size:11px; font-weight:700; ${onlineClase}">${onlineTexto}</span>
                </div>

                <div style="display:flex; flex-wrap:wrap; gap:6px; margin:4px 0;">
                    ${lucesBadge}
                    ${aireBadge}
                    ${proyBadge}
                </div>

                <div style="display:flex; gap:8px; margin-top:auto; pt:6px;">
                    <button type="button" class="btn btn-secundario btn-pequeno btn-tablero-operar" data-id="${a.id}" style="flex:1;">
                        <span>Operar Aula</span>
                    </button>
                    <button type="button" class="btn btn-peligro btn-pequeno btn-tablero-apagar" data-id="${a.id}" data-nombre="${escapeHtml(a.nombre)}" title="Apagar todo en esta aula">
                        <span>Apagar Todo</span>
                    </button>
                </div>
            `;

            tableroAulasGrid.appendChild(card);
        });

        tableroAulasGrid.querySelectorAll('.btn-tablero-operar').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = parseInt(btn.dataset.id, 10);
                if (selectAulaHeader) selectAulaHeader.value = id;
                aulaActualId = id;
                localStorage.setItem('control_aula_activa_id', id);
                consultarEstado();
                cargarAulas();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        });

        tableroAulasGrid.querySelectorAll('.btn-tablero-apagar').forEach(btn => {
            btn.addEventListener('click', async () => {
                const id = parseInt(btn.dataset.id, 10);
                const nombre = btn.dataset.nombre;
                if (!confirm(`¿Confirmás el apagado total de equipos (luces, aire y proyector) en '${nombre}'?`)) {
                    return;
                }
                btn.disabled = true;
                await apagarTodoEnAula(id);
                btn.disabled = false;
            });
        });
    }

    function renderizarTablaAulas(aulas) {
        if (!tablaAulasBody) return;
        tablaAulasBody.innerHTML = '';

        if (!aulas || aulas.length === 0) {
            tablaAulasBody.innerHTML = '<tr><td colspan="9" style="text-align:center; color:var(--text-muted);">No hay espacios registrados</td></tr>';
            return;
        }

        aulas.forEach(a => {
            const tr = document.createElement('tr');
            const activaTexto = a.activa ? 'Activa' : 'Inactiva';
            const activaClase = a.activa ? 'color:var(--color-success); font-weight:700;' : 'color:var(--text-muted);';

            tr.innerHTML = `
                <td><strong>#${a.id}</strong></td>
                <td><strong>${escapeHtml(a.nombre)}</strong></td>
                <td><span class="rol-tag">${escapeHtml(a.tipo || 'Aula')}</span></td>
                <td>${escapeHtml(a.ubicacion || '--')}</td>
                <td><code style="font-size:12px; font-weight:700; color:var(--text-gold); background:var(--bg-input); padding:2px 8px; border-radius:4px;">${escapeHtml(a.usuario_acceso || '--')}</code></td>
                <td>
                    <div style="display:flex; align-items:center; gap:6px;">
                        <code style="font-size:11px; background:var(--bg-input); padding:2px 6px; border-radius:4px; max-width:110px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="${escapeHtml(a.api_key_esp32 || '')}">${escapeHtml(a.api_key_esp32 || '--')}</code>
                        <button type="button" class="btn btn-secundario btn-pequeno btn-copiar-key" data-key="${escapeHtml(a.api_key_esp32 || '')}" title="Copiar API Key">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                        </button>
                        <button type="button" class="btn btn-secundario btn-pequeno btn-regen-key" data-id="${a.id}" data-nombre="${escapeHtml(a.nombre)}" title="Regenerar API Key">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>
                        </button>
                    </div>
                </td>
                <td>
                    <button type="button" class="btn btn-secundario btn-pequeno btn-toggle-activa-aula" data-id="${a.id}" style="${activaClase}">
                        ${activaTexto}
                    </button>
                </td>
                <td><span class="codigo-tag">${a.codigos_ir_count || 0} códigos</span></td>
                <td style="white-space:nowrap;">
                    <button type="button" class="btn btn-secundario btn-pequeno btn-editar-aula" 
                        data-id="${a.id}" 
                        data-nombre="${escapeHtml(a.nombre)}" 
                        data-tipo="${escapeHtml(a.tipo || 'Aula')}" 
                        data-ubicacion="${escapeHtml(a.ubicacion || '')}" 
                        data-usuario="${escapeHtml(a.usuario_acceso || '')}"
                        data-marca-aire="${escapeHtml(a.hardware?.marca_aire || 'Star (OEM)')}"
                        data-proto-aire="${escapeHtml(a.hardware?.protocolo_aire || 'COOLIX')}"
                        data-marca-proy="${escapeHtml(a.hardware?.marca_proyector || 'Epson')}"
                        data-proto-proy="${escapeHtml(a.hardware?.protocolo_proyector || 'EPSON_NEC')}">
                        Editar
                    </button>
                    <button type="button" class="btn btn-secundario btn-pequeno btn-clonar-aula" data-id="${a.id}" data-nombre="${escapeHtml(a.nombre)}" title="Clonar plantilla de códigos IR">
                        Clonar IR
                    </button>
                    ${a.id > 1 ? `<button type="button" class="btn btn-peligro btn-pequeno btn-eliminar-aula" data-id="${a.id}" data-nombre="${escapeHtml(a.nombre)}" title="Eliminar espacio">Eliminar</button>` : ''}
                </td>
            `;

            tablaAulasBody.appendChild(tr);
        });

        tablaAulasBody.querySelectorAll('.btn-copiar-key').forEach(btn => {
            btn.addEventListener('click', () => {
                const key = btn.dataset.key;
                if (!key) return;
                navigator.clipboard.writeText(key).then(() => {
                    alert(`API Key copiada al portapapeles:\n\n${key}\n\nConfigurala en el ESP32 de esta aula.`);
                }).catch(() => {
                    prompt('Copia esta API Key:', key);
                });
            });
        });

        tablaAulasBody.querySelectorAll('.btn-regen-key').forEach(btn => {
            btn.addEventListener('click', async () => {
                const id = parseInt(btn.dataset.id, 10);
                const nombre = btn.dataset.nombre;
                if (!confirm(`¿Regenerar la API Key para '${nombre}'? El ESP32 actual perderá conexión hasta que se actualice su clave.`)) {
                    return;
                }
                btn.disabled = true;
                try {
                    const res = await fetch('api/aulas.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                        body: JSON.stringify({ operacion: 'regenerar_key', id: id, csrf_token: csrfToken })
                    });
                    const d = await res.json();
                    if (d.ok) {
                        alert(`Nueva API Key generada:\n\n${d.api_key_esp32}`);
                        cargarAulas();
                    } else {
                        alert(d.error || 'Error al regenerar API Key');
                    }
                } catch (e) {
                    alert('Error de conexión');
                } finally {
                    btn.disabled = false;
                }
            });
        });

        tablaAulasBody.querySelectorAll('.btn-toggle-activa-aula').forEach(btn => {
            btn.addEventListener('click', async () => {
                const id = parseInt(btn.dataset.id, 10);
                btn.disabled = true;
                try {
                    const res = await fetch('api/aulas.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                        body: JSON.stringify({ operacion: 'toggle_activa', id: id, csrf_token: csrfToken })
                    });
                    const d = await res.json();
                    if (d.ok) {
                        cargarAulas();
                    } else {
                        alert(d.error || 'No se pudo cambiar el estado del aula');
                    }
                } catch (e) {
                    alert('Error de conexión');
                } finally {
                    btn.disabled = false;
                }
            });
        });

        tablaAulasBody.querySelectorAll('.btn-editar-aula').forEach(btn => {
            btn.addEventListener('click', () => {
                aulaIdInput.value = btn.dataset.id;
                aulaNombre.value = btn.dataset.nombre;
                if (aulaTipo) aulaTipo.value = btn.dataset.tipo || 'Aula';
                aulaUbicacion.value = btn.dataset.ubicacion || '';
                const elMarcaAire = document.getElementById('aulaMarcaAire');
                if (elMarcaAire) elMarcaAire.value = btn.dataset.marcaAire || 'Star (OEM)';
                const elProtoAire = document.getElementById('aulaProtocoloAire');
                if (elProtoAire) elProtoAire.value = btn.dataset.protoAire || 'COOLIX';
                const elMarcaProy = document.getElementById('aulaMarcaProyector');
                if (elMarcaProy) elMarcaProy.value = btn.dataset.marcaProy || 'Epson';
                const elProtoProy = document.getElementById('aulaProtocoloProyector');
                if (elProtoProy) elProtoProy.value = btn.dataset.protoProy || 'EPSON_NEC';
                if (aulaUsuario) aulaUsuario.value = btn.dataset.usuario || '';
                if (aulaPassword) {
                    aulaPassword.value = '';
                    aulaPassword.placeholder = 'Dejar vacío para no modificarla';
                }
                if (labelAulaPassword) labelAulaPassword.textContent = 'Nueva Contraseña (opcional)';
                if (contenedorClonarCodigos) contenedorClonarCodigos.style.display = 'none';
                tituloFormAula.textContent = `Editar espacio #${btn.dataset.id} - ${btn.dataset.nombre}`;
                textoBtnGuardarAula.textContent = 'Actualizar Espacio';
                formAula.scrollIntoView({ behavior: 'smooth' });
            });
        });

        tablaAulasBody.querySelectorAll('.btn-clonar-aula').forEach(btn => {
            btn.addEventListener('click', async () => {
                const destinoId = parseInt(btn.dataset.id, 10);
                const destinoNombre = btn.dataset.nombre;
                const origen = prompt(
                    `Clonar plantilla de códigos IR hacia '${destinoNombre}' (#${destinoId}):\n\n` +
                    `Ingresá '0' para clonar la Plantilla Global por defecto, o el ID del aula existente origen:`,
                    '0'
                );
                if (origen === null) return;
                const origenId = parseInt(origen, 10);
                if (isNaN(origenId) || origenId < 0) {
                    alert('Origen inválido');
                    return;
                }
                btn.disabled = true;
                try {
                    const res = await fetch('api/aulas.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                        body: JSON.stringify({
                            operacion: 'clonar_codigos',
                            origen_aula_id: origenId,
                            destino_aula_id: destinoId,
                            csrf_token: csrfToken
                        })
                    });
                    const d = await res.json();
                    if (d.ok) {
                        alert(d.mensaje);
                        cargarAulas();
                    } else {
                        alert(d.error || 'Error al clonar códigos');
                    }
                } catch (e) {
                    alert('Error de conexión');
                } finally {
                    btn.disabled = false;
                }
            });
        });

        tablaAulasBody.querySelectorAll('.btn-eliminar-aula').forEach(btn => {
            btn.addEventListener('click', async () => {
                const id = parseInt(btn.dataset.id, 10);
                const nombre = btn.dataset.nombre;
                if (!confirm(`¿Eliminar definitivamente el aula '${nombre}' (#${id})? Se borrarán también sus códigos IR asociados.`)) {
                    return;
                }
                btn.disabled = true;
                try {
                    const res = await fetch('api/aulas.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                        body: JSON.stringify({ operacion: 'eliminar', id: id, csrf_token: csrfToken })
                    });
                    const d = await res.json();
                    if (d.ok) {
                        alert(d.mensaje);
                        if (aulaActualId === id) {
                            aulaActualId = 1;
                            localStorage.setItem('control_aula_activa_id', 1);
                        }
                        cargarAulas();
                        consultarEstado();
                    } else {
                        alert(d.error || 'Error al eliminar aula');
                    }
                } catch (e) {
                    alert('Error de conexión');
                } finally {
                    btn.disabled = false;
                }
            });
        });
    }

    async function apagarTodoEnAula(aulaId) {
        emitirBeep(520, 90);
        try {
            const res = await fetch('api/aulas.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({ operacion: 'apagar_todo', aula_id: aulaId, csrf_token: csrfToken })
            });
            const d = await res.json();
            if (d.ok) {
                if (tableroAulasFeedback) {
                    tableroAulasFeedback.className = 'alerta alerta-exito';
                    tableroAulasFeedback.textContent = d.mensaje;
                    setTimeout(() => { tableroAulasFeedback.textContent = ''; tableroAulasFeedback.className = ''; }, 4000);
                }
                await cargarAulas();
                await consultarEstado();
                if (esAdmin) cargarLog();
            } else {
                alert(d.error || 'Error al apagar el aula');
            }
        } catch (e) {
            alert('Error de conexión');
        }
    }

    function resetFormularioAula() {
        if (!formAula) return;
        formAula.reset();
        if (aulaIdInput) aulaIdInput.value = '0';
        if (aulaTipo) aulaTipo.value = 'Aula';
        const elMarcaAire = document.getElementById('aulaMarcaAire');
        if (elMarcaAire) elMarcaAire.value = 'Star (OEM)';
        const elProtoAire = document.getElementById('aulaProtocoloAire');
        if (elProtoAire) elProtoAire.value = 'COOLIX';
        const elMarcaProy = document.getElementById('aulaMarcaProyector');
        if (elMarcaProy) elMarcaProy.value = 'Epson';
        const elProtoProy = document.getElementById('aulaProtocoloProyector');
        if (elProtoProy) elProtoProy.value = 'EPSON_NEC';
        if (aulaUsuario) aulaUsuario.value = '';
        if (aulaPassword) {
            aulaPassword.value = '';
            aulaPassword.placeholder = 'Dejar en blanco para mantener o default aula123';
        }
        if (labelAulaPassword) labelAulaPassword.textContent = 'Contraseña de Acceso';
        if (contenedorClonarCodigos) contenedorClonarCodigos.style.display = 'block';
        if (tituloFormAula) tituloFormAula.textContent = 'Dar de alta nuevo espacio físico';
        if (textoBtnGuardarAula) textoBtnGuardarAula.textContent = 'Guardar Espacio';
        if (aulaFormFeedback) {
            aulaFormFeedback.textContent = '';
            aulaFormFeedback.className = '';
        }
    }

    // -------------------------------------------------------------------------
    // 9. GESTIÓN DE USUARIOS (EXCLUSIVO ADMIN)
    // -------------------------------------------------------------------------
    function resetFormularioUsuario() {
        if (!formUsuario) return;
        formUsuario.reset();
        document.getElementById('usrId').value = '0';
        if (tituloFormUsuario) {
            tituloFormUsuario.textContent = 'Dar de alta nuevo usuario (Profesor o Administrador)';
        }
        if (textoBtnGuardarUsuario) {
            textoBtnGuardarUsuario.textContent = 'Crear Usuario';
        }
        if (labelUsrPassword) {
            labelUsrPassword.textContent = 'Contraseña (obligatoria)';
        }
        const inputPass = document.getElementById('usrPassword');
        if (inputPass) {
            inputPass.required = true;
            inputPass.placeholder = 'Mínimo 4 caracteres';
        }
        renderizarCheckboxesAulasUsuario('todas');
    }

    async function cargarUsuarios() {
        if (!esAdmin || !tablaUsuariosBody) return;

        try {
            const res = await fetch('api/usuarios.php');
            const data = await res.json();

            if (data.ok && Array.isArray(data.usuarios)) {
                tablaUsuariosBody.innerHTML = '';
                data.usuarios.forEach(u => {
                    const tr = document.createElement('tr');
                    const esMiUsuario = (u.id === currentUserId);
                    const rolBadge = u.rol === 'admin'
                        ? '<span class="rol-tag">ADMIN</span>'
                        : '<span class="estado-pastilla off">PROFESOR</span>';
                    const estadoBadge = u.activo === 1
                        ? '<span class="estado-pastilla on">ACTIVO</span>'
                        : '<span class="estado-pastilla off">INACTIVO</span>';

                    // Formatear texto de aulas permitidas
                    let aulasTexto = 'Todas';
                    if (u.rol === 'admin' || u.aulas_permitidas === 'todas' || !u.aulas_permitidas) {
                        aulasTexto = '<span class="codigo-tag">Todas las aulas</span>';
                    } else {
                        try {
                            const ids = typeof u.aulas_permitidas === 'string' ? JSON.parse(u.aulas_permitidas) : u.aulas_permitidas;
                            if (Array.isArray(ids) && ids.length > 0) {
                                const nombres = ids.map(id => {
                                    const match = listaAulas.find(a => a.id === id);
                                    return match ? match.nombre : `#${id}`;
                                });
                                aulasTexto = `<span class="codigo-tag" title="${escapeHtml(nombres.join(', '))}">${escapeHtml(nombres.join(', '))}</span>`;
                            } else {
                                aulasTexto = '<span class="codigo-tag">Todas las aulas</span>';
                            }
                        } catch (err) {
                            aulasTexto = '<span class="codigo-tag">Todas</span>';
                        }
                    }

                    let botonesAccion = `
                        <button type="button" class="btn btn-secundario btn-pequeno btn-editar-usuario"
                            data-id="${u.id}"
                            data-usuario="${escapeHtml(u.usuario)}"
                            data-nombre="${escapeHtml(u.nombre_completo)}"
                            data-rol="${escapeHtml(u.rol)}"
                            data-activo="${u.activo}"
                            data-aulas="${escapeHtml(u.aulas_permitidas || 'todas')}">
                            Editar
                        </button>
                    `;

                    if (!esMiUsuario) {
                        botonesAccion += `
                            <button type="button" class="btn btn-secundario btn-pequeno btn-toggle-usuario"
                                data-id="${u.id}"
                                data-usuario="${escapeHtml(u.usuario)}"
                                title="${u.activo === 1 ? 'Desactivar acceso' : 'Reactivar acceso'}">
                                ${u.activo === 1 ? 'Desactivar' : 'Activar'}
                            </button>
                            <button type="button" class="btn btn-peligro btn-pequeno btn-eliminar-usuario"
                                data-id="${u.id}"
                                data-usuario="${escapeHtml(u.usuario)}">
                                Eliminar
                            </button>
                        `;
                    } else {
                        botonesAccion += ` <span style="font-size:11px; color:var(--text-muted); margin-left:6px;">(Sesión actual)</span>`;
                    }

                    tr.innerHTML = `
                        <td><strong>${escapeHtml(u.usuario)}</strong></td>
                        <td>${escapeHtml(u.nombre_completo)}</td>
                        <td>${rolBadge}</td>
                        <td>${estadoBadge}</td>
                        <td>${aulasTexto}</td>
                        <td style="white-space:nowrap;">${botonesAccion}</td>
                    `;
                    tablaUsuariosBody.appendChild(tr);
                });

                document.querySelectorAll('.btn-editar-usuario').forEach(btn => {
                    btn.addEventListener('click', () => {
                        document.getElementById('usrId').value = btn.dataset.id;
                        document.getElementById('usrUsuario').value = btn.dataset.usuario;
                        document.getElementById('usrNombre').value = btn.dataset.nombre;
                        document.getElementById('usrRol').value = btn.dataset.rol;
                        const inputPass = document.getElementById('usrPassword');
                        if (inputPass) {
                            inputPass.value = '';
                            inputPass.required = false;
                            inputPass.placeholder = 'Dejar en blanco para mantener la actual';
                        }
                        if (tituloFormUsuario) {
                            tituloFormUsuario.textContent = `Editando usuario: ${btn.dataset.usuario}`;
                        }
                        if (textoBtnGuardarUsuario) {
                            textoBtnGuardarUsuario.textContent = 'Guardar Cambios';
                        }
                        if (labelUsrPassword) {
                            labelUsrPassword.textContent = 'Nueva Contraseña (opcional)';
                        }
                        renderizarCheckboxesAulasUsuario(btn.dataset.aulas || 'todas');
                        document.getElementById('usrUsuario').scrollIntoView({ behavior: 'smooth' });
                    });
                });

                document.querySelectorAll('.btn-toggle-usuario').forEach(btn => {
                    btn.addEventListener('click', async () => {
                        try {
                            const res = await fetch('api/usuarios.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-Token': csrfToken
                                },
                                body: JSON.stringify({
                                    operacion: 'toggle_activo',
                                    id: parseInt(btn.dataset.id, 10),
                                    csrf_token: csrfToken
                                })
                            });
                            const resp = await res.json();
                            if (resp.ok) {
                                cargarUsuarios();
                                cargarLog();
                            } else {
                                alert(resp.error || 'No se pudo cambiar el estado del usuario');
                            }
                        } catch (err) {
                            alert('Error de conexión con el servidor');
                        }
                    });
                });

                document.querySelectorAll('.btn-eliminar-usuario').forEach(btn => {
                    btn.addEventListener('click', async () => {
                        const nombreUsr = btn.dataset.usuario;
                        if (!confirm(`¿Está seguro de que desea eliminar al usuario "${nombreUsr}"?`)) {
                            return;
                        }
                        try {
                            const res = await fetch('api/usuarios.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-Token': csrfToken
                                },
                                body: JSON.stringify({
                                    operacion: 'eliminar',
                                    id: parseInt(btn.dataset.id, 10),
                                    csrf_token: csrfToken
                                })
                            });
                            const resp = await res.json();
                            if (resp.ok) {
                                if (usuarioFormFeedback) {
                                    usuarioFormFeedback.className = 'alerta alerta-exito';
                                    usuarioFormFeedback.textContent = resp.mensaje;
                                }
                                cargarUsuarios();
                                cargarLog();
                            } else {
                                alert(resp.error || 'No se pudo eliminar el usuario');
                            }
                        } catch (err) {
                            alert('Error de conexión con el servidor');
                        }
                    });
                });
            }
        } catch (e) {
            console.error('Error al cargar usuarios:', e);
        }
    }

    if (btnLimpiarFormUsuario) {
        btnLimpiarFormUsuario.addEventListener('click', () => {
            resetFormularioUsuario();
            if (usuarioFormFeedback) {
                usuarioFormFeedback.textContent = '';
                usuarioFormFeedback.className = '';
            }
        });
    }

    if (formUsuario) {
        formUsuario.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (usuarioFormFeedback) {
                usuarioFormFeedback.textContent = '';
                usuarioFormFeedback.className = '';
            }

            const id = parseInt(document.getElementById('usrId').value || '0', 10);
            const rol = document.getElementById('usrRol').value;

            // Determinar aulas permitidas
            let aulasPermitidas = 'todas';
            const chkTodas = document.getElementById('chkAulaTodas');
            if (rol !== 'admin' && chkTodas && !chkTodas.checked) {
                const seleccionadas = Array.from(document.querySelectorAll('.chk-aula-item:checked'))
                    .map(el => parseInt(el.value, 10))
                    .filter(v => v > 0);
                aulasPermitidas = seleccionadas.length > 0 ? seleccionadas : 'todas';
            }

            const payload = {
                operacion: id > 0 ? 'editar' : 'crear',
                id: id,
                usuario: document.getElementById('usrUsuario').value.trim(),
                nombre_completo: document.getElementById('usrNombre').value.trim(),
                rol: rol,
                password: document.getElementById('usrPassword').value,
                activo: 1,
                aulas_permitidas: aulasPermitidas,
                csrf_token: csrfToken
            };

            try {
                const res = await fetch('api/usuarios.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (data.ok) {
                    if (usuarioFormFeedback) {
                        usuarioFormFeedback.className = 'alerta alerta-exito';
                        usuarioFormFeedback.textContent = data.mensaje || 'Usuario guardado exitosamente';
                    }
                    if (id === currentUserId && headerNombreUsuario) {
                        headerNombreUsuario.textContent = payload.nombre_completo;
                    }
                    resetFormularioUsuario();
                    cargarUsuarios();
                    cargarLog();
                } else {
                    if (usuarioFormFeedback) {
                        usuarioFormFeedback.className = 'alerta alerta-error';
                        usuarioFormFeedback.textContent = data.error || 'Error al guardar el usuario';
                    }
                }
            } catch (err) {
                if (usuarioFormFeedback) {
                    usuarioFormFeedback.className = 'alerta alerta-error';
                    usuarioFormFeedback.textContent = 'Error de conexión con el servidor';
                }
            }
        });
    }

    // -------------------------------------------------------------------------
    // 10. GESTIÓN DE CÓDIGOS IR (EXCLUSIVO ADMIN)
    // -------------------------------------------------------------------------
    async function cargarCodigos() {
        if (!esAdmin || !tablaCodigosBody) return;

        try {
            const url = (filtroCodigosAula && filtroCodigosAula.value !== '')
                ? `api/codigos.php?aula_id=${filtroCodigosAula.value}`
                : 'api/codigos.php';
            const res = await fetch(url);
            const data = await res.json();

            if (data.ok && Array.isArray(data.codigos)) {
                tablaCodigosBody.innerHTML = '';
                if (data.codigos.length === 0) {
                    tablaCodigosBody.innerHTML = '<tr><td colspan="7" style="text-align:center; color:var(--text-muted);">No hay códigos registrados</td></tr>';
                    return;
                }

                data.codigos.forEach(item => {
                    const tr = document.createElement('tr');

                    let transmisionHTML = '';
                    if (item.codigo) {
                        transmisionHTML += `<span class="codigo-tag">${escapeHtml(item.codigo)}</span> `;
                    }
                    if (item.estado_hex) {
                        transmisionHTML += `<span class="codigo-tag" title="${escapeHtml(item.estado_hex)}">[Estado]</span> `;
                    }
                    if (item.raw) {
                        transmisionHTML += `<span class="codigo-tag" title="Trama RAW">[RAW]</span> `;
                    }

                    const params = `${item.bits}b | ${item.repeticiones}x | ${item.pausa_ms}ms | ${item.frecuencia_khz}kHz`;
                    const ambitoAula = item.aula_nombre 
                        ? `<span class="codigo-tag" style="background:var(--color-primary-bg); color:var(--text-gold); font-weight:700;">${escapeHtml(item.aula_nombre)}</span>`
                        : (item.aula_id ? `<span class="codigo-tag">Aula #${item.aula_id}</span>` : `<span class="codigo-tag" style="background:rgba(255,255,255,0.06);">Plantilla Global</span>`);

                    tr.innerHTML = `
                        <td>${ambitoAula}</td>
                        <td><strong>${escapeHtml(item.dispositivo.toUpperCase())}</strong></td>
                        <td>${escapeHtml(item.accion)}</td>
                        <td>${escapeHtml(item.protocolo)}</td>
                        <td>${transmisionHTML}</td>
                        <td style="font-size:11px; color:var(--text-secondary);">${params}</td>
                        <td style="white-space:nowrap;">
                            <button type="button" class="btn btn-primario btn-pequeno btn-probar-codigo"
                                data-dispositivo="${escapeHtml(item.dispositivo)}"
                                data-accion="${escapeHtml(item.accion)}" title="Encolar y probar ahora en el aula activa">
                                Probar
                            </button>
                            <button type="button" class="btn btn-secundario btn-pequeno btn-editar-codigo" 
                                data-id="${item.id}"
                                data-aula-id="${item.aula_id || 0}"
                                data-dispositivo="${escapeHtml(item.dispositivo)}"
                                data-accion="${escapeHtml(item.accion)}"
                                data-protocolo="${escapeHtml(item.protocolo)}"
                                data-codigo="${escapeHtml(item.codigo || '')}"
                                data-bits="${item.bits}"
                                data-repeticiones="${item.repeticiones}"
                                data-pausa="${item.pausa_ms}"
                                data-khz="${item.frecuencia_khz}"
                                data-estado="${escapeHtml(item.estado_hex || '')}"
                                data-raw="${escapeHtml(item.raw || '')}"
                                data-descripcion="${escapeHtml(item.descripcion || '')}">
                                Editar
                            </button>
                        </td>
                    `;
                    tablaCodigosBody.appendChild(tr);
                });

                document.querySelectorAll('.btn-probar-codigo').forEach(btn => {
                    btn.addEventListener('click', async () => {
                        btn.disabled = true;
                        await enviarComandoDispositivo(btn.dataset.dispositivo, btn.dataset.accion);
                        btn.disabled = false;
                    });
                });

                document.querySelectorAll('.btn-editar-codigo').forEach(btn => {
                    btn.addEventListener('click', () => {
                        if (codAulaId) codAulaId.value = btn.dataset.aulaId || '0';
                        document.getElementById('codDispositivo').value = btn.dataset.dispositivo;
                        document.getElementById('codAccion').value = btn.dataset.accion;
                        document.getElementById('codProtocolo').value = btn.dataset.protocolo;
                        document.getElementById('codCodigo').value = btn.dataset.codigo;
                        document.getElementById('codBits').value = btn.dataset.bits;
                        document.getElementById('codRepeticiones').value = btn.dataset.repeticiones;
                        document.getElementById('codPausaMs').value = btn.dataset.pausa;
                        document.getElementById('codFrecuenciaKhz').value = btn.dataset.khz;
                        document.getElementById('codEstadoHex').value = btn.dataset.estado;
                        document.getElementById('codRaw').value = btn.dataset.raw;
                        document.getElementById('codDescripcion').value = btn.dataset.descripcion;
                        document.getElementById('codDispositivo').scrollIntoView({ behavior: 'smooth' });
                    });
                });
            }
        } catch (e) {
            console.error('Error al cargar codigos IR:', e);
        }
    }

    if (btnLimpiarFormCodigo) {
        btnLimpiarFormCodigo.addEventListener('click', () => {
            formCodigo.reset();
            formFeedback.textContent = '';
            formFeedback.className = '';
        });
    }

    if (formCodigo) {
        formCodigo.addEventListener('submit', async function (e) {
            e.preventDefault();
            formFeedback.textContent = '';
            formFeedback.className = '';

            const payload = {
                aula_id: parseInt(document.getElementById('codAulaId')?.value || '0', 10),
                dispositivo: document.getElementById('codDispositivo').value.trim(),
                accion: document.getElementById('codAccion').value.trim(),
                protocolo: document.getElementById('codProtocolo').value.trim(),
                codigo: document.getElementById('codCodigo').value.trim(),
                bits: parseInt(document.getElementById('codBits').value.trim(), 10) || 32,
                repeticiones: parseInt(document.getElementById('codRepeticiones').value.trim(), 10) || 1,
                pausa_ms: parseInt(document.getElementById('codPausaMs').value.trim(), 10) || 100,
                frecuencia_khz: parseInt(document.getElementById('codFrecuenciaKhz').value.trim(), 10) || 38,
                estado_hex: document.getElementById('codEstadoHex').value.trim(),
                raw: document.getElementById('codRaw').value.trim(),
                descripcion: document.getElementById('codDescripcion').value.trim(),
                csrf_token: csrfToken
            };

            try {
                const res = await fetch('api/codigos.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify(payload)
                });

                const data = await res.json();
                if (data.ok) {
                    formFeedback.className = 'alerta alerta-exito';
                    formFeedback.textContent = data.mensaje || 'Código guardado con éxito';
                    cargarCodigos();
                    cargarLog();
                } else {
                    formFeedback.className = 'alerta alerta-error';
                    formFeedback.textContent = data.error || 'Error al guardar código';
                }
            } catch (err) {
                formFeedback.className = 'alerta alerta-error';
                formFeedback.textContent = 'Error de conexión con el servidor';
            }
        });
    }

    // -------------------------------------------------------------------------
    // 11. CAPTURAS IR (KY-022) Y ASIGNACIÓN DIRECTA (EXCLUSIVO ADMIN)
    // -------------------------------------------------------------------------
    async function cargarCapturas() {
        if (!esAdmin || !tablaCapturasBody) return;

        try {
            const res = await fetch('api/capturas.php');
            const data = await res.json();

            if (data.ok && Array.isArray(data.capturas)) {
                tablaCapturasBody.innerHTML = '';
                if (data.capturas.length === 0) {
                    tablaCapturasBody.innerHTML = '<tr><td colspan="6" style="text-align:center; color:var(--text-muted);">Sin capturas recientes</td></tr>';
                    return;
                }

                data.capturas.forEach(item => {
                    const tr = document.createElement('tr');

                    let detalleDatos = '';
                    if (item.valor) detalleDatos += `<span class="codigo-tag">${escapeHtml(item.valor)}</span> `;
                    if (item.estado_hex) detalleDatos += `<span class="codigo-tag" title="${escapeHtml(item.estado_hex)}">[Estado]</span> `;
                    if (item.raw) detalleDatos += `<span class="codigo-tag" title="Trama RAW">[RAW]</span> `;

                    const aulaTxt = item.aula_nombre 
                        ? escapeHtml(item.aula_nombre) 
                        : (item.aula_id ? `Aula #${item.aula_id}` : 'General');

                    tr.innerHTML = `
                        <td>${formatearFecha(item.creado_en)}</td>
                        <td><span class="codigo-tag">${aulaTxt}</span></td>
                        <td>${escapeHtml(item.protocolo)}</td>
                        <td>${detalleDatos}</td>
                        <td>${item.bits} bits</td>
                        <td>
                            <button type="button" class="btn btn-primario btn-pequeno btn-asignar-captura"
                                data-id="${item.id}"
                                data-aula-id="${item.aula_id || 0}"
                                data-proto="${escapeHtml(item.protocolo)}"
                                data-valor="${escapeHtml(item.valor || '')}"
                                data-estado="${escapeHtml(item.estado_hex || '')}"
                                data-raw="${escapeHtml(item.raw || '')}"
                                data-bits="${item.bits}">
                                Asignar a...
                            </button>
                        </td>
                    `;
                    tablaCapturasBody.appendChild(tr);
                });

                document.querySelectorAll('.btn-asignar-captura').forEach(btn => {
                    btn.addEventListener('click', async () => {
                        const id = btn.dataset.id;
                        const aulaIdCap = parseInt(btn.dataset.aulaId || '0', 10);
                        const dispPrompt = prompt('Ingrese el dispositivo a asignar ("aire" o "proyector"):', 'aire');
                        if (!dispPrompt) return;
                        const accPrompt = prompt('Ingrese la acción ("prender", "apagar", "frio_24", etc.):', 'prender');
                        if (!accPrompt) return;

                        try {
                            const res = await fetch('api/capturas/asignar.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-Token': csrfToken
                                },
                                body: JSON.stringify({
                                    captura_id: parseInt(id, 10),
                                    aula_id: aulaIdCap,
                                    dispositivo: dispPrompt.toLowerCase().trim(),
                                    accion: accPrompt.toLowerCase().trim(),
                                    csrf_token: csrfToken
                                })
                            });

                            const respData = await res.json();
                            if (respData.ok) {
                                if (capturaAsignarFeedback) {
                                    capturaAsignarFeedback.className = 'alerta alerta-exito';
                                    capturaAsignarFeedback.textContent = respData.mensaje;
                                }
                                cargarCodigos();
                                cargarLog();
                            } else {
                                alert(respData.error || 'No se pudo asignar la captura');
                            }
                        } catch (err) {
                            alert('Error de conexión al asignar captura');
                        }
                    });
                });
            }
        } catch (e) {
            console.error('Error al cargar capturas:', e);
        }
    }

    // -------------------------------------------------------------------------
    // 12. REGISTRO DE EVENTOS (LOG - EXCLUSIVO ADMIN)
    // -------------------------------------------------------------------------
    async function cargarLog() {
        if (!esAdmin || !tablaLogBody) return;

        try {
            const res = await fetch('api/log.php');
            const data = await res.json();

            if (data.ok && Array.isArray(data.eventos)) {
                tablaLogBody.innerHTML = '';
                if (data.eventos.length === 0) {
                    tablaLogBody.innerHTML = '<tr><td colspan="5" style="text-align:center; color:var(--text-muted);">Sin eventos registrados</td></tr>';
                    return;
                }

                data.eventos.forEach(item => {
                    const tr = document.createElement('tr');
                    const aulaTxt = item.aula_nombre 
                        ? escapeHtml(item.aula_nombre) 
                        : (item.aula_id ? `Aula #${item.aula_id}` : 'General');

                    tr.innerHTML = `
                        <td style="white-space:nowrap;">${formatearFecha(item.creado_en)}</td>
                        <td><span class="codigo-tag">${aulaTxt}</span></td>
                        <td><span class="estado-pastilla off">${escapeHtml(item.origen)}</span></td>
                        <td><strong>${escapeHtml(item.evento)}</strong></td>
                        <td style="color:var(--text-secondary);">${escapeHtml(item.detalle || '--')}</td>
                    `;
                    tablaLogBody.appendChild(tr);
                });
            }
        } catch (e) {
            console.error('Error al cargar log:', e);
        }
    }

    // -------------------------------------------------------------------------
    // 13. NAVEGACIÓN POR PESTAÑAS (PANEL ADMIN)
    // -------------------------------------------------------------------------
    function activarTab(tabId) {
        tabBtns.forEach(btn => {
            btn.classList.toggle('activo', btn.dataset.target === tabId);
        });
        tabContenidos.forEach(seccion => {
            seccion.classList.toggle('activo', seccion.id === tabId);
        });
    }

    tabBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const destino = btn.dataset.target;
            activarTab(destino);

            if (destino === 'tab-aulas') cargarAulas();
            if (destino === 'tab-usuarios') cargarUsuarios();
            if (destino === 'tab-codigos') cargarCodigos();
            if (destino === 'tab-capturas') cargarCapturas();
            if (destino === 'tab-log') cargarLog();
        });
    });

    // -------------------------------------------------------------------------
    // 14. ASIGNACIÓN DE EVENTOS A CONTROLES DE AULA E INICIALIZACIÓN
    // -------------------------------------------------------------------------
    // Escenas Rápidas de 1 Clic
    document.querySelectorAll('[data-escena]').forEach(btn => {
        btn.addEventListener('click', () => {
            ejecutarEscenaAula(btn.getAttribute('data-escena'));
        });
    });

    // Luces
    if (btnLucesOn) btnLucesOn.addEventListener('click', () => cambiarLuces(1));
    if (btnLucesOff) btnLucesOff.addEventListener('click', () => cambiarLuces(0));

    // Termostato de Aire Acondicionado
    if (btnAireOn) {
        btnAireOn.addEventListener('click', () => {
            enviarControlAire({
                accion: 'prender',
                temp: prefsActuales.prefAireTemp || estadoAire.temp,
                modo: prefsActuales.prefAireModo || estadoAire.modo,
                fan: estadoAire.fan,
                swing: estadoAire.swing
            });
        });
    }

    if (btnAireOff) {
        btnAireOff.addEventListener('click', () => {
            enviarControlAire({ accion: 'apagar' });
        });
    }

    if (btnTempMenos) {
        btnTempMenos.addEventListener('click', () => {
            const nuevaTemp = Math.max(16, (estadoAire.temp || 24) - 1);
            enviarControlAire({ accion: 'ajustar', temp: nuevaTemp });
        });
    }

    if (btnTempMas) {
        btnTempMas.addEventListener('click', () => {
            const nuevaTemp = Math.min(30, (estadoAire.temp || 24) + 1);
            enviarControlAire({ accion: 'ajustar', temp: nuevaTemp });
        });
    }

    if (sliderAireTemp) {
        sliderAireTemp.addEventListener('input', () => {
            if (aireTempValor) {
                aireTempValor.textContent = `${sliderAireTemp.value}°C`;
            }
        });
        sliderAireTemp.addEventListener('change', () => {
            const nuevaTemp = Math.max(16, Math.min(30, parseInt(sliderAireTemp.value, 10) || 24));
            enviarControlAire({ accion: 'ajustar', temp: nuevaTemp });
        });
    }

    document.querySelectorAll('[data-aire-modo]').forEach(btn => {
        btn.addEventListener('click', () => {
            const nuevoModo = btn.getAttribute('data-aire-modo');
            enviarControlAire({ accion: 'ajustar', modo: nuevoModo });
        });
    });

    document.querySelectorAll('[data-aire-fan]').forEach(btn => {
        btn.addEventListener('click', () => {
            const nuevoFan = btn.getAttribute('data-aire-fan');
            enviarControlAire({ accion: 'ajustar', fan: nuevoFan });
        });
    });

    if (btnAireSwing) {
        btnAireSwing.addEventListener('click', () => {
            enviarControlAire({ accion: 'ajustar', swing: !estadoAire.swing });
        });
    }

    if (btnPresetEco) {
        btnPresetEco.addEventListener('click', () => {
            enviarControlAire({ accion: 'preset_eco', temp: 24, modo: 'frio', fan: 'auto', swing: true });
        });
    }

    if (btnPresetMaxFrio) {
        btnPresetMaxFrio.addEventListener('click', () => {
            enviarControlAire({ accion: 'preset_max_frio', temp: 18, modo: 'frio', fan: 'alto', swing: true });
        });
    }

    if (btnPresetCalor) {
        btnPresetCalor.addEventListener('click', () => {
            enviarControlAire({ accion: 'preset_calor', temp: 26, modo: 'calor', fan: 'medio', swing: true });
        });
    }

    // Proyector Multimedia
    if (btnProyectorOn) btnProyectorOn.addEventListener('click', () => enviarControlProyector('prender'));
    if (btnProyectorOff) btnProyectorOff.addEventListener('click', () => enviarControlProyector('apagar'));

    document.querySelectorAll('[data-proy-fuente]').forEach(btn => {
        btn.addEventListener('click', () => {
            const fuente = btn.getAttribute('data-proy-fuente');
            enviarControlProyector(fuente, { fuente: fuente });
        });
    });

    if (btnProyFreeze) btnProyFreeze.addEventListener('click', () => enviarControlProyector('freeze'));
    if (btnProyMute) btnProyMute.addEventListener('click', () => enviarControlProyector('av_mute'));

    // Control Ergonómico de Volumen del Proyector
    function programarEnvioVolumen(vol) {
        if (debounceVolTimer) clearTimeout(debounceVolTimer);
        debounceVolTimer = setTimeout(() => {
            enviarControlProyector('set_volumen', { valor: vol });
        }, 250);
    }

    if (sliderProyVol) {
        sliderProyVol.addEventListener('input', () => {
            const vol = parseInt(sliderProyVol.value, 10);
            estadoProyector.volumen = vol;
            if (vol > 0) volumenPrevioProyector = vol;
            if (proyVolTexto) {
                proyVolTexto.textContent = vol === 0 ? 'MUTE' : `${vol}%`;
                proyVolTexto.classList.toggle('muted', vol === 0);
            }
            if (btnProyVolMute) {
                btnProyVolMute.classList.toggle('activo', vol === 0);
            }
            document.querySelectorAll('.btn-vol-pill').forEach(pill => {
                pill.classList.toggle('activo', vol > 0 && parseInt(pill.dataset.vol, 10) === vol);
            });
            programarEnvioVolumen(vol);
        });

        sliderProyVol.addEventListener('change', () => {
            if (debounceVolTimer) clearTimeout(debounceVolTimer);
            const vol = parseInt(sliderProyVol.value, 10);
            enviarControlProyector('set_volumen', { valor: vol });
        });
    }

    if (btnProyVolMute) {
        btnProyVolMute.addEventListener('click', () => {
            if (estadoProyector.volumen > 0) {
                volumenPrevioProyector = estadoProyector.volumen;
                estadoProyector.volumen = 0;
                actualizarVistaProyector();
                enviarControlProyector('set_volumen', { valor: 0 });
            } else {
                const volRestaurado = (volumenPrevioProyector > 0) ? volumenPrevioProyector : 50;
                estadoProyector.volumen = volRestaurado;
                actualizarVistaProyector();
                enviarControlProyector('set_volumen', { valor: volRestaurado });
            }
        });
    }

    if (btnProyVolMenos) {
        btnProyVolMenos.addEventListener('click', () => {
            const nuevo = Math.max(0, estadoProyector.volumen - 5);
            estadoProyector.volumen = nuevo;
            if (nuevo > 0) volumenPrevioProyector = nuevo;
            actualizarVistaProyector();
            enviarControlProyector('set_volumen', { valor: nuevo });
        });
    }

    if (btnProyVolMas) {
        btnProyVolMas.addEventListener('click', () => {
            const base = (estadoProyector.volumen === 0 && volumenPrevioProyector > 0) ? volumenPrevioProyector : estadoProyector.volumen;
            const nuevo = Math.min(100, (base === 0 ? 5 : base + 5));
            estadoProyector.volumen = nuevo;
            volumenPrevioProyector = nuevo;
            actualizarVistaProyector();
            enviarControlProyector('set_volumen', { valor: nuevo });
        });
    }

    document.querySelectorAll('.btn-vol-pill').forEach(pill => {
        pill.addEventListener('click', () => {
            const vol = parseInt(pill.dataset.vol, 10);
            estadoProyector.volumen = vol;
            volumenPrevioProyector = vol;
            actualizarVistaProyector();
            enviarControlProyector('set_volumen', { valor: vol });
        });
    });

    // Selector de Aula en Cabecera
    if (selectAulaHeader) {
        selectAulaHeader.addEventListener('change', () => {
            aulaActualId = parseInt(selectAulaHeader.value, 10);
            localStorage.setItem('control_aula_activa_id', aulaActualId);
            consultarEstado();
            if (esAdmin) cargarAulas();
        });
    }

    // Filtro de Códigos IR por Aula
    if (filtroCodigosAula) {
        filtroCodigosAula.addEventListener('change', () => {
            cargarCodigos();
        });
    }

    // Formulario de Alta y Edición de Aulas (Admin)
    if (formAula) {
        formAula.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (aulaFormFeedback) {
                aulaFormFeedback.textContent = '';
                aulaFormFeedback.className = '';
            }

            const id = parseInt(aulaIdInput ? aulaIdInput.value : '0', 10);
            const elMarcaAire = document.getElementById('aulaMarcaAire');
            const elProtoAire = document.getElementById('aulaProtocoloAire');
            const elMarcaProy = document.getElementById('aulaMarcaProyector');
            const elProtoProy = document.getElementById('aulaProtocoloProyector');

            const payload = {
                operacion: id > 0 ? 'editar' : 'crear',
                id: id,
                aula_id: id,
                nombre: aulaNombre ? aulaNombre.value.trim() : '',
                tipo: aulaTipo ? aulaTipo.value : 'Aula',
                ubicacion: aulaUbicacion ? aulaUbicacion.value.trim() : '',
                marca_aire: elMarcaAire ? elMarcaAire.value : 'Star (OEM)',
                protocolo_aire: elProtoAire ? elProtoAire.value : 'COOLIX',
                marca_proyector: elMarcaProy ? elMarcaProy.value : 'Epson',
                protocolo_proyector: elProtoProy ? elProtoProy.value : 'EPSON_NEC',
                usuario: aulaUsuario ? aulaUsuario.value.trim() : '',
                password: aulaPassword ? aulaPassword.value : '',
                clonar_origen_id: aulaClonarCodigos ? aulaClonarCodigos.value : 0,
                csrf_token: csrfToken
            };

            try {
                const res = await fetch('api/aulas.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                    body: JSON.stringify(payload)
                });
                const d = await res.json();
                if (d.ok) {
                    if (aulaFormFeedback) {
                        aulaFormFeedback.className = 'alerta alerta-exito';
                        aulaFormFeedback.textContent = d.mensaje;
                        setTimeout(() => { if (aulaFormFeedback) { aulaFormFeedback.textContent = ''; aulaFormFeedback.className = ''; } }, 4000);
                    }
                    resetFormularioAula();
                    cargarAulas();
                    if (esAdmin) cargarLog();
                } else {
                    if (aulaFormFeedback) {
                        aulaFormFeedback.className = 'alerta alerta-error';
                        aulaFormFeedback.textContent = d.error || 'Error al guardar aula';
                    }
                }
            } catch (err) {
                if (aulaFormFeedback) {
                    aulaFormFeedback.className = 'alerta alerta-error';
                    aulaFormFeedback.textContent = 'Error de conexión con el servidor';
                }
            }
        });
    }

    if (btnLimpiarFormAula) {
        btnLimpiarFormAula.addEventListener('click', resetFormularioAula);
    }

    if (btnApagarTodasAulas) {
        btnApagarTodasAulas.addEventListener('click', async () => {
            if (!confirm('ATENCIÓN: ¿Confirmás que deseás enviar comandos de apagado a TODAS las aulas activas de la institución?')) {
                return;
            }
            btnApagarTodasAulas.disabled = true;
            await apagarTodoEnAula('todas');
            btnApagarTodasAulas.disabled = false;
        });
    }

    // Iniciar carga de aulas y polling del estado del aula activa
    cargarAulas().then(() => {
        consultarEstado();
        iniciarTimerPolling();
    });

    // Si es Admin, inicializar módulos administrativos
    if (esAdmin) {
        resetFormularioUsuario();
        resetFormularioAula();
        cargarUsuarios();
        cargarCodigos();
        cargarCapturas();
        cargarLog();

        setInterval(() => {
            const tabActiva = document.querySelector('.tab-contenido.activo');
            if (tabActiva && tabActiva.id === 'tab-aulas') cargarAulas();
            if (tabActiva && tabActiva.id === 'tab-capturas') cargarCapturas();
            if (tabActiva && tabActiva.id === 'tab-log') cargarLog();
        }, 6000);
    }

})();
