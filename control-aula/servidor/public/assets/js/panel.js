/**
 * LÓGICA DEL PANEL WEB (VANILLA JAVASCRIPT)
 * Sincronización en tiempo real cada 2 segundos sin dependencias externas.
 * Soporte de roles (Admin y Profesor), Gestión de Usuarios y Personalización Visual.
 */

(function () {
    'use strict';

    // Metadatos de sesión inyectados en el DOM
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const userRole = document.querySelector('meta[name="user-role"]')?.getAttribute('content') || 'profesor';
    const currentUserId = parseInt(document.querySelector('meta[name="user-id"]')?.getAttribute('content') || '0', 10);
    const esAdmin = (userRole === 'admin');

    // Elementos del DOM - Conexión
    const badgeConexion = document.getElementById('badgeConexion');
    const textoConexion = document.getElementById('textoConexion');

    // Controles de Luces
    const pastillaLuces = document.getElementById('pastillaLuces');
    const iconoLuces = document.getElementById('iconoLuces');
    const btnLucesOn = document.getElementById('btnLucesOn');
    const btnLucesOff = document.getElementById('btnLucesOff');

    // Controles de Aire Acondicionado
    const pastillaAire = document.getElementById('pastillaAire');
    const iconoAire = document.getElementById('iconoAire');
    const btnAireOn = document.getElementById('btnAireOn');
    const btnAireOff = document.getElementById('btnAireOff');
    const colaAireInfo = document.getElementById('colaAireInfo');

    // Controles de Proyector
    const pastillaProyector = document.getElementById('pastillaProyector');
    const iconoProyector = document.getElementById('iconoProyector');
    const btnProyectorOn = document.getElementById('btnProyectorOn');
    const btnProyectorOff = document.getElementById('btnProyectorOff');
    const colaProyectorInfo = document.getElementById('colaProyectorInfo');

    // Telemetría (presente solo en vista Admin)
    const telIp = document.getElementById('telIp');
    const telRssi = document.getElementById('telRssi');
    const telUptime = document.getElementById('telUptime');
    const telFw = document.getElementById('telFw');
    const telContacto = document.getElementById('telContacto');

    // Panel Admin Colapsable y Pestañas
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

    // Modal de Configuración de Apariencia y Accesibilidad
    const modalConfigApariencia = document.getElementById('modalConfigApariencia');
    const btnAbrirConfig = document.getElementById('btnAbrirConfig');
    const btnCerrarConfig = document.getElementById('btnCerrarConfig');
    const btnCerrarConfigFooter = document.getElementById('btnCerrarConfigFooter');
    const btnResetApariencia = document.getElementById('btnResetApariencia');

    let actualizacionEnCurso = false;

    // -------------------------------------------------------------------------
    // 1. PREFERENCIAS DE APARIENCIA Y ACCESIBILIDAD (LOCALSTORAGE)
    // -------------------------------------------------------------------------
    const PREFS_KEY = 'control_aula_prefs';
    const PREFS_DEFAULT = {
        tema: 'oscuro',
        acento: 'dorado',
        escala: 'normal'
    };

    function obtenerPreferencias() {
        try {
            const guardado = JSON.parse(localStorage.getItem(PREFS_KEY) || '{}');
            return {
                tema: ['oscuro', 'claro'].includes(guardado.tema) ? guardado.tema : PREFS_DEFAULT.tema,
                acento: ['dorado', 'azul', 'esmeralda', 'violeta'].includes(guardado.acento) ? guardado.acento : PREFS_DEFAULT.acento,
                escala: ['pequeno', 'normal', 'grande', 'extragrande'].includes(guardado.escala) ? guardado.escala : PREFS_DEFAULT.escala
            };
        } catch (e) {
            return { ...PREFS_DEFAULT };
        }
    }

    function aplicarPreferencias(prefs) {
        const root = document.documentElement;
        root.setAttribute('data-theme', prefs.tema);
        root.setAttribute('data-accent', prefs.acento);
        root.setAttribute('data-scale', prefs.escala);

        try {
            localStorage.setItem(PREFS_KEY, JSON.stringify(prefs));
        } catch (e) {}

        // Actualizar estado visual de los botones del modal
        document.querySelectorAll('[data-pref-tema]').forEach(btn => {
            btn.classList.toggle('activo', btn.getAttribute('data-pref-tema') === prefs.tema);
        });
        document.querySelectorAll('[data-pref-acento]').forEach(btn => {
            btn.classList.toggle('activo', btn.getAttribute('data-pref-acento') === prefs.acento);
        });
        document.querySelectorAll('[data-pref-escala]').forEach(btn => {
            btn.classList.toggle('activo', btn.getAttribute('data-pref-escala') === prefs.escala);
        });
    }

    // Inicializar preferencias al cargar
    let prefsActuales = obtenerPreferencias();
    aplicarPreferencias(prefsActuales);

    if (btnAbrirConfig && modalConfigApariencia) {
        btnAbrirConfig.addEventListener('click', () => {
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

    document.querySelectorAll('[data-pref-escala]').forEach(btn => {
        btn.addEventListener('click', () => {
            prefsActuales.escala = btn.getAttribute('data-pref-escala');
            aplicarPreferencias(prefsActuales);
        });
    });

    if (btnResetApariencia) {
        btnResetApariencia.addEventListener('click', () => {
            prefsActuales = { ...PREFS_DEFAULT };
            aplicarPreferencias(prefsActuales);
        });
    }

    // -------------------------------------------------------------------------
    // 2. SECCIÓN COLAPSABLE DE ADMINISTRACIÓN
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
    // 3. FORMATEADORES DE DATOS
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
    // 4. ACTUALIZACIÓN DE ESTADO DEL AULA (POLLING CADA 2 SEGUNDOS)
    // -------------------------------------------------------------------------
    async function consultarEstado() {
        if (actualizacionEnCurso) return;
        actualizacionEnCurso = true;

        try {
            const respuesta = await fetch('api/estado.php', {
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

    function actualizarIndicadorConexion(online, segundos) {
        if (!badgeConexion || !textoConexion) return;
        if (online) {
            badgeConexion.className = 'badge-conexion online';
            textoConexion.textContent = 'AULA ONLINE';
            if (telContacto) telContacto.textContent = segundos !== null ? `hace ${segundos}s` : 'activo';
        } else {
            badgeConexion.className = 'badge-conexion offline';
            textoConexion.textContent = 'AULA OFFLINE';
            if (telContacto) telContacto.textContent = segundos !== null ? `hace ${segundos}s` : 'sin señal';
        }
    }

    function renderizarEstado(data) {
        actualizarIndicadorConexion(data.esp32_online, data.segundos_desde_contacto);

        // Telemetría (solo si existe en el DOM para Admin)
        if (telIp) telIp.textContent = data.ip || '--';
        if (telRssi) telRssi.textContent = formatearRssi(data.rssi);
        if (telUptime) telUptime.textContent = formatearUptime(data.uptime);
        if (telFw) telFw.textContent = data.version_fw ? `v${data.version_fw}` : '--';

        // 1. Estado de Luces
        const lucesEncendidas = data.luces_reales === 1;
        if (pastillaLuces) {
            pastillaLuces.className = `estado-pastilla ${lucesEncendidas ? 'on' : 'off'}`;
            pastillaLuces.textContent = lucesEncendidas ? 'ENCENDIDAS' : 'APAGADAS';
        }
        if (iconoLuces) {
            iconoLuces.classList.toggle('luces-on', lucesEncendidas);
        }

        // 2. Estado del Aire Acondicionado
        const accionAire = (data.ultima_accion_aire || 'ninguna').toLowerCase();
        const aireActivo = accionAire === 'prender';
        if (pastillaAire) {
            pastillaAire.className = `estado-pastilla ${aireActivo ? 'on' : 'off'}`;
            pastillaAire.textContent = accionAire.toUpperCase();
        }
        if (iconoAire) {
            iconoAire.classList.toggle('aire-on', aireActivo);
        }

        // 3. Estado del Proyector
        const accionProyector = (data.ultima_accion_proyector || 'ninguna').toLowerCase();
        const proyectorActivo = accionProyector === 'prender';
        if (pastillaProyector) {
            pastillaProyector.className = `estado-pastilla ${proyectorActivo ? 'on' : 'off'}`;
            pastillaProyector.textContent = accionProyector.toUpperCase();
        }
        if (iconoProyector) {
            iconoProyector.classList.toggle('proyector-on', proyectorActivo);
        }

        // Información de comandos pendientes
        if (colaAireInfo && colaProyectorInfo) {
            if (data.comandos_ir_pendientes > 0) {
                colaAireInfo.style.display = 'block';
                colaAireInfo.textContent = `(${data.comandos_ir_pendientes} en cola...)`;
                colaProyectorInfo.style.display = 'block';
                colaProyectorInfo.textContent = `(${data.comandos_ir_pendientes} en cola...)`;
            } else {
                colaAireInfo.style.display = 'none';
                colaProyectorInfo.style.display = 'none';
            }
        }
    }

    // -------------------------------------------------------------------------
    // 5. ACCIONES DE CONTROL DEL AULA (LUCES, AIRE, PROYECTOR)
    // -------------------------------------------------------------------------
    async function cambiarLuces(estadoDeseado) {
        if (btnLucesOn) btnLucesOn.disabled = true;
        if (btnLucesOff) btnLucesOff.disabled = true;

        try {
            const res = await fetch('api/luces.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({ estado: estadoDeseado, csrf_token: csrfToken })
            });

            const data = await res.json();
            if (data.ok) {
                consultarEstado();
                if (esAdmin) cargarLog();
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

    async function enviarComandoDispositivo(dispositivo, accion) {
        const btnOn = dispositivo === 'aire' ? btnAireOn : btnProyectorOn;
        const btnOff = dispositivo === 'aire' ? btnAireOff : btnProyectorOff;

        if (btnOn) btnOn.disabled = true;
        if (btnOff) btnOff.disabled = true;

        try {
            const res = await fetch('api/ir.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({ dispositivo: dispositivo, accion: accion, csrf_token: csrfToken })
            });

            const data = await res.json();
            if (data.ok) {
                consultarEstado();
                if (esAdmin) cargarLog();
            } else {
                alert(data.error || `No se pudo enviar el comando a ${dispositivo}`);
            }
        } catch (err) {
            console.error(`Error al enviar comando a ${dispositivo}:`, err);
            alert('Error de conexión al servidor');
        } finally {
            if (btnOn) btnOn.disabled = false;
            if (btnOff) btnOff.disabled = false;
        }
    }

    // -------------------------------------------------------------------------
    // 6. GESTIÓN DE USUARIOS (EXCLUSIVO ADMIN)
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

                    let botonesAccion = `
                        <button type="button" class="btn btn-secundario btn-pequeno btn-editar-usuario"
                            data-id="${u.id}"
                            data-usuario="${escapeHtml(u.usuario)}"
                            data-nombre="${escapeHtml(u.nombre_completo)}"
                            data-rol="${escapeHtml(u.rol)}"
                            data-activo="${u.activo}">
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
                        <td style="white-space:nowrap;">${botonesAccion}</td>
                    `;
                    tablaUsuariosBody.appendChild(tr);
                });

                // Listeners de Editar usuario
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
                        document.getElementById('usrUsuario').scrollIntoView({ behavior: 'smooth' });
                    });
                });

                // Listeners de Activar / Desactivar
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

                // Listeners de Eliminar usuario
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
            const payload = {
                operacion: id > 0 ? 'editar' : 'crear',
                id: id,
                usuario: document.getElementById('usrUsuario').value.trim(),
                nombre_completo: document.getElementById('usrNombre').value.trim(),
                rol: document.getElementById('usrRol').value,
                password: document.getElementById('usrPassword').value,
                activo: 1,
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
    // 7. GESTIÓN DE CÓDIGOS IR (EXCLUSIVO ADMIN)
    // -------------------------------------------------------------------------
    async function cargarCodigos() {
        if (!esAdmin || !tablaCodigosBody) return;

        try {
            const res = await fetch('api/codigos.php');
            const data = await res.json();

            if (data.ok && Array.isArray(data.codigos)) {
                tablaCodigosBody.innerHTML = '';
                if (data.codigos.length === 0) {
                    tablaCodigosBody.innerHTML = '<tr><td colspan="6" style="text-align:center; color:var(--text-muted);">No hay códigos registrados</td></tr>';
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

                    tr.innerHTML = `
                        <td><strong>${escapeHtml(item.dispositivo.toUpperCase())}</strong></td>
                        <td>${escapeHtml(item.accion)}</td>
                        <td>${escapeHtml(item.protocolo)}</td>
                        <td>${transmisionHTML}</td>
                        <td style="font-size:11px; color:var(--text-secondary);">${params}</td>
                        <td style="white-space:nowrap;">
                            <button type="button" class="btn btn-primario btn-pequeno btn-probar-codigo"
                                data-dispositivo="${escapeHtml(item.dispositivo)}"
                                data-accion="${escapeHtml(item.accion)}" title="Encolar y probar ahora">
                                Probar
                            </button>
                            <button type="button" class="btn btn-secundario btn-pequeno btn-editar-codigo" 
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
    // 8. CAPTURAS IR (KY-022) Y ASIGNACIÓN DIRECTA (EXCLUSIVO ADMIN)
    // -------------------------------------------------------------------------
    async function cargarCapturas() {
        if (!esAdmin || !tablaCapturasBody) return;

        try {
            const res = await fetch('api/capturas.php');
            const data = await res.json();

            if (data.ok && Array.isArray(data.capturas)) {
                tablaCapturasBody.innerHTML = '';
                if (data.capturas.length === 0) {
                    tablaCapturasBody.innerHTML = '<tr><td colspan="5" style="text-align:center; color:var(--text-muted);">Sin capturas recientes</td></tr>';
                    return;
                }

                data.capturas.forEach(item => {
                    const tr = document.createElement('tr');

                    let detalleDatos = '';
                    if (item.valor) detalleDatos += `<span class="codigo-tag">${escapeHtml(item.valor)}</span> `;
                    if (item.estado_hex) detalleDatos += `<span class="codigo-tag" title="${escapeHtml(item.estado_hex)}">[Estado]</span> `;
                    if (item.raw) detalleDatos += `<span class="codigo-tag" title="Trama RAW">[RAW]</span> `;

                    tr.innerHTML = `
                        <td>${formatearFecha(item.creado_en)}</td>
                        <td>${escapeHtml(item.protocolo)}</td>
                        <td>${detalleDatos}</td>
                        <td>${item.bits} bits</td>
                        <td>
                            <button type="button" class="btn btn-primario btn-pequeno btn-asignar-captura"
                                data-id="${item.id}"
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
                        const dispPrompt = prompt('Ingrese el dispositivo a asignar ("aire" o "proyector"):', 'aire');
                        if (!dispPrompt) return;
                        const accPrompt = prompt('Ingrese la acción ("prender", "apagar", u otra):', 'prender');
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
    // 9. REGISTRO DE EVENTOS (LOG - EXCLUSIVO ADMIN)
    // -------------------------------------------------------------------------
    async function cargarLog() {
        if (!esAdmin || !tablaLogBody) return;

        try {
            const res = await fetch('api/log.php');
            const data = await res.json();

            if (data.ok && Array.isArray(data.eventos)) {
                tablaLogBody.innerHTML = '';
                if (data.eventos.length === 0) {
                    tablaLogBody.innerHTML = '<tr><td colspan="4" style="text-align:center; color:var(--text-muted);">Sin eventos registrados</td></tr>';
                    return;
                }

                data.eventos.forEach(item => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td style="white-space:nowrap;">${formatearFecha(item.creado_en)}</td>
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
    // 10. NAVEGACIÓN POR PESTAÑAS (PANEL ADMIN)
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

            if (destino === 'tab-usuarios') cargarUsuarios();
            if (destino === 'tab-codigos') cargarCodigos();
            if (destino === 'tab-capturas') cargarCapturas();
            if (destino === 'tab-log') cargarLog();
        });
    });

    // -------------------------------------------------------------------------
    // 11. ASIGNACIÓN DE EVENTOS A BOTONES PRINCIPALES E INICIALIZACIÓN
    // -------------------------------------------------------------------------
    if (btnLucesOn) btnLucesOn.addEventListener('click', () => cambiarLuces(1));
    if (btnLucesOff) btnLucesOff.addEventListener('click', () => cambiarLuces(0));

    if (btnAireOn) btnAireOn.addEventListener('click', () => enviarComandoDispositivo('aire', 'prender'));
    if (btnAireOff) btnAireOff.addEventListener('click', () => enviarComandoDispositivo('aire', 'apagar'));

    if (btnProyectorOn) btnProyectorOn.addEventListener('click', () => enviarComandoDispositivo('proyector', 'prender'));
    if (btnProyectorOff) btnProyectorOff.addEventListener('click', () => enviarComandoDispositivo('proyector', 'apagar'));

    // Iniciar polling del estado del aula (disponible para Profesor y Admin)
    consultarEstado();
    setInterval(consultarEstado, 2000);

    // Si es Admin, inicializar módulos administrativos
    if (esAdmin) {
        resetFormularioUsuario();
        cargarUsuarios();
        cargarCodigos();
        cargarCapturas();
        cargarLog();

        setInterval(() => {
            const tabActiva = document.querySelector('.tab-contenido.activo');
            if (tabActiva && tabActiva.id === 'tab-capturas') cargarCapturas();
            if (tabActiva && tabActiva.id === 'tab-log') cargarLog();
        }, 6000);
    }

})();
