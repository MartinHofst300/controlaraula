/**
 * LÓGICA DEL PANEL WEB (VANILLA JAVASCRIPT)
 * Sincronización en tiempo real cada 2 segundos sin dependencias externas.
 * Soporte multidispositivo: Luces, Aire acondicionado y Proyector.
 */

(function () {
    'use strict';

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

    // Telemetría
    const telIp = document.getElementById('telIp');
    const telRssi = document.getElementById('telRssi');
    const telUptime = document.getElementById('telUptime');
    const telFw = document.getElementById('telFw');
    const telContacto = document.getElementById('telContacto');

    // Pestañas
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabContenidos = document.querySelectorAll('.tab-contenido');

    // Tablas y Formularios
    const tablaCodigosBody = document.getElementById('tablaCodigosBody');
    const tablaCapturasBody = document.getElementById('tablaCapturasBody');
    const tablaLogBody = document.getElementById('tablaLogBody');
    const formCodigo = document.getElementById('formCodigo');
    const formFeedback = document.getElementById('formFeedback');
    const btnLimpiarFormCodigo = document.getElementById('btnLimpiarFormCodigo');
    const capturaAsignarFeedback = document.getElementById('capturaAsignarFeedback');

    // Token CSRF inyectado en el DOM
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    let actualizacionEnCurso = false;

    // -------------------------------------------------------------------------
    // FORMATEADORES DE DATOS
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

    // -------------------------------------------------------------------------
    // ACTUALIZACIÓN DE ESTADO (POLLING CADA 2 SEGUNDOS)
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
        if (online) {
            badgeConexion.className = 'badge-conexion online';
            textoConexion.textContent = 'ESP32 ONLINE';
            telContacto.textContent = segundos !== null ? `hace ${segundos}s` : 'activo';
        } else {
            badgeConexion.className = 'badge-conexion offline';
            textoConexion.textContent = 'ESP32 OFFLINE';
            telContacto.textContent = segundos !== null ? `hace ${segundos}s` : 'sin señal';
        }
    }

    function renderizarEstado(data) {
        actualizarIndicadorConexion(data.esp32_online, data.segundos_desde_contacto);

        // Telemetría
        telIp.textContent = data.ip || '--';
        telRssi.textContent = formatearRssi(data.rssi);
        telUptime.textContent = formatearUptime(data.uptime);
        telFw.textContent = data.version_fw ? `v${data.version_fw}` : '--';

        // 1. Estado de Luces
        const lucesEncendidas = data.luces_reales === 1;
        pastillaLuces.className = `estado-pastilla ${lucesEncendidas ? 'on' : 'off'}`;
        pastillaLuces.textContent = lucesEncendidas ? 'ENCENDIDAS' : 'APAGADAS';

        if (lucesEncendidas) {
            iconoLuces.classList.add('luces-on');
        } else {
            iconoLuces.classList.remove('luces-on');
        }

        // 2. Estado del Aire Acondicionado
        const accionAire = (data.ultima_accion_aire || 'ninguna').toLowerCase();
        const aireActivo = accionAire === 'prender';
        pastillaAire.className = `estado-pastilla ${aireActivo ? 'on' : 'off'}`;
        pastillaAire.textContent = accionAire.toUpperCase();

        if (aireActivo) {
            iconoAire.classList.add('aire-on');
        } else {
            iconoAire.classList.remove('aire-on');
        }

        // 3. Estado del Proyector
        const accionProyector = (data.ultima_accion_proyector || 'ninguna').toLowerCase();
        const proyectorActivo = accionProyector === 'prender';
        pastillaProyector.className = `estado-pastilla ${proyectorActivo ? 'on' : 'off'}`;
        pastillaProyector.textContent = accionProyector.toUpperCase();

        if (proyectorActivo) {
            iconoProyector.classList.add('proyector-on');
        } else {
            iconoProyector.classList.remove('proyector-on');
        }

        // Información de comandos pendientes
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

    // -------------------------------------------------------------------------
    // ACCIONES DE CONTROL
    // -------------------------------------------------------------------------
    async function cambiarLuces(estadoDeseado) {
        btnLucesOn.disabled = true;
        btnLucesOff.disabled = true;

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
                cargarLog();
            } else {
                alert(data.error || 'No se pudo cambiar el estado de las luces');
            }
        } catch (err) {
            console.error('Error al cambiar luces:', err);
            alert('Error de conexión al servidor');
        } finally {
            btnLucesOn.disabled = false;
            btnLucesOff.disabled = false;
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
                cargarLog();
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
    // GESTIÓN DE CÓDIGOS IR
    // -------------------------------------------------------------------------
    async function cargarCodigos() {
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
                    
                    // Resumen de datos de transmisión
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

                    // Parámetros
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

                // Listeners de Probar código
                document.querySelectorAll('.btn-probar-codigo').forEach(btn => {
                    btn.addEventListener('click', async () => {
                        btn.disabled = true;
                        await enviarComandoDispositivo(btn.dataset.dispositivo, btn.dataset.accion);
                        btn.disabled = false;
                    });
                });

                // Listeners de Editar código
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
    // CAPTURAS IR (KY-022) Y ASIGNACIÓN DIRECTA
    // -------------------------------------------------------------------------
    async function cargarCapturas() {
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
    // REGISTRO DE EVENTOS (LOG)
    // -------------------------------------------------------------------------
    async function cargarLog() {
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
    // NAVEGACIÓN POR PESTAÑAS
    // -------------------------------------------------------------------------
    function activarTab(tabId) {
        tabBtns.forEach(btn => {
            if (btn.dataset.target === tabId) {
                btn.classList.add('activo');
            } else {
                btn.classList.remove('activo');
            }
        });

        tabContenidos.forEach(seccion => {
            if (seccion.id === tabId) {
                seccion.classList.add('activo');
            } else {
                seccion.classList.remove('activo');
            }
        });
    }

    tabBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const destino = btn.dataset.target;
            activarTab(destino);

            if (destino === 'tab-codigos') cargarCodigos();
            if (destino === 'tab-capturas') cargarCapturas();
            if (destino === 'tab-log') cargarLog();
        });
    });

    function escapeHtml(texto) {
        if (!texto) return '';
        const div = document.createElement('div');
        div.textContent = texto;
        return div.innerHTML;
    }

    // -------------------------------------------------------------------------
    // ASIGNACIÓN DE EVENTOS A BOTONES PRINCIPALES
    // -------------------------------------------------------------------------
    if (btnLucesOn) btnLucesOn.addEventListener('click', () => cambiarLuces(1));
    if (btnLucesOff) btnLucesOff.addEventListener('click', () => cambiarLuces(0));

    if (btnAireOn) btnAireOn.addEventListener('click', () => enviarComandoDispositivo('aire', 'prender'));
    if (btnAireOff) btnAireOff.addEventListener('click', () => enviarComandoDispositivo('aire', 'apagar'));

    if (btnProyectorOn) btnProyectorOn.addEventListener('click', () => enviarComandoDispositivo('proyector', 'prender'));
    if (btnProyectorOff) btnProyectorOff.addEventListener('click', () => enviarComandoDispositivo('proyector', 'apagar'));

    // Iniciar polling y cargas iniciales
    consultarEstado();
    cargarCodigos();
    cargarCapturas();
    cargarLog();

    setInterval(consultarEstado, 2000);
    setInterval(() => {
        const tabActiva = document.querySelector('.tab-contenido.activo');
        if (tabActiva && tabActiva.id === 'tab-capturas') cargarCapturas();
        if (tabActiva && tabActiva.id === 'tab-log') cargarLog();
    }, 6000);

})();
