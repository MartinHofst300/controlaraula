<?php
/**
 * Simulador Web de ESP32 para pruebas de extremo a extremo sin hardware (Multi-Aula)
 * Protegido por sesión de usuario del panel.
 * Soporta selección de aula individual, simulación de su API Key específica,
 * colas independientes, y comandos avanzados de termostato y proyector.
 */

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/funciones.php';

iniciarSesionSegura();

if (empty($_SESSION['autenticado']) || $_SESSION['autenticado'] !== true) {
    header('Location: login.php');
    exit;
}

if (($_SESSION['rol'] ?? '') !== 'admin') {
    http_response_code(403);
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Acceso restringido</title><link rel="stylesheet" href="assets/css/estilos.css"></head><body class="login-body"><div class="login-wrapper"><div class="login-card" style="text-align:center;"><h2 style="margin-bottom:10px;">Acceso Restringido</h2><p style="color:var(--text-secondary);margin-bottom:20px;">El Simulador de ESP32 está disponible únicamente para usuarios con rol Administrador.</p><a href="index.php" class="btn btn-primario">Volver al Panel del Aula</a></div></div></body></html>';
    exit;
}

$pdo = obtenerConexionDB();
asegurarColumnasExtendidas($pdo);

$stmtAulas = $pdo->query("SELECT id, nombre, ubicacion, api_key_esp32 FROM aulas WHERE activa = 1 ORDER BY id ASC");
$aulasSimulador = $stmtAulas ? $stmtAulas->fetchAll() : [];

$primeraKey = !empty($aulasSimulador[0]['api_key_esp32']) ? $aulasSimulador[0]['api_key_esp32'] : API_KEY_ESP32;
$csrfToken = obtenerTokenCSRF();
?>
<!DOCTYPE html>
<html lang="es" data-theme="oscuro" data-accent="dorado" data-font="sans" data-density="comoda" data-contrast="normal" data-motion="normal">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1c1818">
    <title>Simulador ESP32 Multi-Aula | Control de Aula</title>
    <script>
        (function() {
            try {
                var prefs = JSON.parse(localStorage.getItem('control_aula_prefs') || '{}');
                var root = document.documentElement;
                var tema = prefs.tema || 'oscuro';
                if (tema === 'auto') {
                    tema = (window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches) ? 'claro' : 'oscuro';
                }
                root.setAttribute('data-theme', tema);
                if (prefs.acento) root.setAttribute('data-accent', prefs.acento);
                if (prefs.fuente) root.setAttribute('data-font', prefs.fuente);
                if (prefs.densidad) root.setAttribute('data-density', prefs.densidad);
                if (prefs.altoContraste) root.setAttribute('data-contrast', 'alto');
                if (prefs.reducirAnimaciones) root.setAttribute('data-motion', 'reducido');
                if (prefs.escalaPct) {
                    var pct = Math.max(85, Math.min(130, parseInt(prefs.escalaPct, 10) || 100));
                    root.style.setProperty('--ui-scale', (pct / 100).toFixed(2));
                }
                if (prefs.acento === 'custom' && prefs.colorCustom) {
                    root.style.setProperty('--color-primary', prefs.colorCustom);
                    root.style.setProperty('--color-primary-hover', prefs.colorCustom);
                    root.style.setProperty('--text-gold', prefs.colorCustom);
                }
            } catch (e) {}
        })();
    </script>
    <link rel="stylesheet" href="assets/css/estilos.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="panel-contenedor">
        <header class="cabecera-principal">
            <div class="marca-titulo">
                <div class="marca-icono" style="background: rgba(16, 185, 129, 0.12); color: var(--color-success); border-color: rgba(16, 185, 129, 0.35);">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="5 3 19 12 5 21 5 3"></polygon>
                    </svg>
                </div>
                <div class="marca-info">
                    <h1>Simulador de ESP32 (Multi-Aula)</h1>
                    <span>Validación de protocolo, colas aisladas por aula y telemetría</span>
                </div>
            </div>

            <div class="cabecera-acciones">
                <a href="index.php" class="btn btn-secundario btn-pequeno">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="19" y1="12" x2="5" y2="12"></line>
                        <polyline points="12 19 5 12 12 5"></polyline>
                    </svg>
                    <span>Volver al Panel</span>
                </a>
            </div>
        </header>

        <div class="simulador-grid">
            <!-- Columna Izquierda: Acciones de Simulación -->
            <div style="display:flex; flex-direction:column; gap:20px;">
                <!-- Tarjeta 0: Selector de Aula a Simular -->
                <div class="tarjeta-seccion" style="background:var(--bg-card); border:2px solid var(--border-highlight); padding:16px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                        <div>
                            <h3 style="font-size:15px; margin:0; color:var(--text-gold);">Aula a Simular</h3>
                            <p style="font-size:12px; color:var(--text-secondary); margin:2px 0 0 0;">Cada aula posee su propia cola de órdenes IR y su API Key única.</p>
                        </div>
                        <select id="simuladorAulaSelect" style="background:var(--bg-input, #222); color:var(--text-primary, #fff); border:1px solid var(--border-subtle, #444); border-radius:6px; padding:6px 12px; font-weight:700; font-size:13px; cursor:pointer;">
                            <?php foreach ($aulasSimulador as $as): ?>
                                <option value="<?php echo $as['id']; ?>" data-key="<?php echo htmlspecialchars($as['api_key_esp32']); ?>">
                                    #<?php echo $as['id']; ?> - <?php echo htmlspecialchars($as['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="margin-top:10px; font-size:12px; display:flex; align-items:center; gap:8px;">
                        <span style="color:var(--text-secondary);">API Key simulada:</span>
                        <code id="labelApiKeySimulada" style="background:rgba(0,0,0,0.35); padding:3px 8px; border-radius:4px; font-weight:700; color:var(--text-gold); font-size:11px;">
                            <?php echo htmlspecialchars($primeraKey); ?>
                        </code>
                    </div>
                </div>

                <!-- Tarjeta 1: Sincronización Unificada -->
                <div class="tarjeta-seccion">
                    <h3 style="font-size:16px; margin-bottom:8px;">1. Polling: Sincronización Unificada</h3>
                    <p style="font-size:12px; color:var(--text-secondary); margin-bottom:14px;">
                        Consulta <code>/api/esp32/sincronizar</code> con la API Key del aula seleccionada.
                    </p>
                    <div style="display:flex; gap:10px; flex-wrap:wrap;">
                        <button type="button" id="btnSimularSyncPost" class="btn btn-primario btn-bloque">
                            <span>POST Sincronizar (Telemetría + Comandos)</span>
                        </button>
                        <button type="button" id="btnSimularSync" class="btn btn-secundario btn-bloque">
                            <span>GET (Texto Plano Hardware)</span>
                        </button>
                        <button type="button" id="btnSimularSyncJson" class="btn btn-secundario btn-bloque">
                            <span>GET JSON Completo (?formato=json)</span>
                        </button>
                    </div>
                </div>

                <!-- Tarjeta 2: Encolar comandos de prueba -->
                <div class="tarjeta-seccion">
                    <h3 style="font-size:16px; margin-bottom:8px;">2. Encolar Comandos IR para esta Aula</h3>
                    <p style="font-size:12px; color:var(--text-secondary); margin-bottom:14px;">
                        Encola órdenes reales en la tabla <code>comandos_ir</code> hacia el hardware real del salón (Star AC + Epson).
                    </p>
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                        <button type="button" id="btnEncolarAireEco" class="btn btn-secundario btn-pequeno">
                            <span>Star AC: 24°C Frío Eco (0xB2BF40)</span>
                        </button>
                        <button type="button" id="btnEncolarAireOff" class="btn btn-secundario btn-pequeno">
                            <span>Star AC: Apagar (0xB27BE0)</span>
                        </button>
                        <button type="button" id="btnEncolarProyectorOn" class="btn btn-secundario btn-pequeno">
                            <span>Epson: Encender (0x8355906F)</span>
                        </button>
                        <button type="button" id="btnEncolarProyectorOff" class="btn btn-secundario btn-pequeno">
                            <span>Epson: Apagar (2x / 2000ms)</span>
                        </button>
                    </div>
                </div>

                <!-- Tarjeta 3: Telemetría manual -->
                <div class="tarjeta-seccion">
                    <h3 style="font-size:16px; margin-bottom:8px;">3. Telemetría y Confirmación de Comandos</h3>
                    <div class="formulario-grilla" style="grid-template-columns: 1fr 1fr; margin-bottom:14px;">
                        <div class="campo-grupo">
                            <label for="simLuces">Estado real de Luces</label>
                            <select id="simLuces">
                                <option value="0">0 (Apagadas)</option>
                                <option value="1">1 (Encendidas)</option>
                            </select>
                        </div>
                        <div class="campo-grupo">
                            <label for="simIrId">ID de Comando IR a confirmar</label>
                            <input type="number" id="simIrId" value="0" min="0">
                        </div>
                        <div class="campo-grupo">
                            <label for="simRssi">RSSI WiFi (dBm)</label>
                            <input type="number" id="simRssi" value="-58">
                        </div>
                        <div class="campo-grupo">
                            <label for="simUptime">Uptime (segundos)</label>
                            <input type="number" id="simUptime" value="3600">
                        </div>
                    </div>
                    <button type="button" id="btnSimularReporte" class="btn btn-exito btn-bloque">
                        <span>Enviar Reporte Tradicional (POST /reportar)</span>
                    </button>
                </div>

                <!-- Tarjeta 4: Captura Infrarroja Avanzada -->
                <div class="tarjeta-seccion">
                    <h3 style="font-size:16px; margin-bottom:8px;">4. Simular Lectura KY-022</h3>
                    <p style="font-size:12px; color:var(--text-secondary); margin-bottom:14px;">
                        Envía <code>POST /api/esp32/captura</code> con tramas de Star AC o proyector Epson.
                    </p>
                    <div class="formulario-grilla" style="grid-template-columns: 1fr 1fr 1fr; margin-bottom:14px;">
                        <div class="campo-grupo">
                            <label for="capProto">Protocolo</label>
                            <input type="text" id="capProto" value="COOLIX">
                        </div>
                        <div class="campo-grupo">
                            <label for="capValor">Código HEX</label>
                            <input type="text" id="capValor" value="0xB2BF40">
                        </div>
                        <div class="campo-grupo">
                            <label for="capBits">Bits</label>
                            <input type="number" id="capBits" value="24">
                        </div>
                        <div class="campo-grupo col-span-4" style="grid-column: span 3;">
                            <label for="capEstadoHex">Trama de Estado HEX (opcional para A/C)</label>
                            <input type="text" id="capEstadoHex" placeholder="Ej. B2BF40 (Coolix 24 bits)">
                        </div>
                        <div class="campo-grupo col-span-4" style="grid-column: span 3;">
                            <label for="capRaw">Datos RAW en microsegundos (opcional)</label>
                            <input type="text" id="capRaw" placeholder="Ej. 8950,4420,580,520,580,1620...">
                        </div>
                    </div>
                    <button type="button" id="btnSimularCaptura" class="btn btn-secundario btn-bloque">
                        <span>Enviar Captura Infrarroja al Servidor</span>
                    </button>
                </div>
            </div>

            <!-- Columna Derecha: Consola de Monitoreo -->
            <div class="tarjeta-seccion" style="display:flex; flex-direction:column;">
                <div class="seccion-encabezado">
                    <h3>Terminal de Comunicaciones</h3>
                    <button type="button" id="btnLimpiarConsola" class="btn btn-secundario btn-pequeno">Limpiar</button>
                </div>
                <div id="consola" class="simulador-consola" style="flex:1; min-height:450px;">[SISTEMA] Simulador ESP32 Multi-Aula inicializado. Listo para probar.</div>
            </div>
        </div>
    </div>

    <script>
        const consola = document.getElementById('consola');
        const csrfToken = '<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>';
        const simuladorAulaSelect = document.getElementById('simuladorAulaSelect');
        const labelApiKeySimulada = document.getElementById('labelApiKeySimulada');

        let currentAulaId = simuladorAulaSelect ? parseInt(simuladorAulaSelect.value, 10) : 1;
        let currentApiKey = simuladorAulaSelect ? (simuladorAulaSelect.options[simuladorAulaSelect.selectedIndex]?.dataset.key || '<?php echo htmlspecialchars($primeraKey); ?>') : '<?php echo htmlspecialchars($primeraKey); ?>';

        if (simuladorAulaSelect) {
            simuladorAulaSelect.addEventListener('change', () => {
                currentAulaId = parseInt(simuladorAulaSelect.value, 10);
                const opt = simuladorAulaSelect.options[simuladorAulaSelect.selectedIndex];
                currentApiKey = opt?.dataset.key || '';
                if (labelApiKeySimulada) labelApiKeySimulada.textContent = currentApiKey;
                logConsola(`[SIMULADOR] Aula cambiada a #${currentAulaId} (${opt.text}). Clave activa: ${currentApiKey}`);
            });
        }

        function logConsola(texto) {
            const hora = new Date().toLocaleTimeString();
            consola.textContent += `\n[${hora}] ${texto}`;
            consola.scrollTop = consola.scrollHeight;
        }

        document.getElementById('btnLimpiarConsola').addEventListener('click', () => {
            consola.textContent = '[SISTEMA] Consola limpia.';
        });

        // 1.a Simular Sincronización GET (Texto plano)
        document.getElementById('btnSimularSync').addEventListener('click', async () => {
            logConsola(`>>> GET /api/esp32/sincronizar (Aula #${currentAulaId}, Key: ${currentApiKey.substring(0, 14)}...)`);
            try {
                const res = await fetch(`api/esp32/sincronizar.php?aula_id=${currentAulaId}`, {
                    headers: { 'X-API-Key': currentApiKey }
                });
                const texto = await res.text();
                logConsola(`<<< HTTP ${res.status}\n${texto.trim()}`);
                procesarRespuestaSync(texto);
            } catch (err) {
                logConsola(`[ERROR] ${err.message}`);
            }
        });

        // 1.a-bis Simular Sincronización GET JSON Completo
        document.getElementById('btnSimularSyncJson').addEventListener('click', async () => {
            logConsola(`>>> GET /api/esp32/sincronizar?formato=json (Aula #${currentAulaId})`);
            try {
                const res = await fetch(`api/esp32/sincronizar.php?formato=json&aula_id=${currentAulaId}`, {
                    headers: {
                        'X-API-Key': currentApiKey,
                        'Accept': 'application/json'
                    }
                });
                const data = await res.json();
                logConsola(`<<< HTTP ${res.status} (JSON)\n${JSON.stringify(data, null, 2)}`);
                if (data.ir_id && data.ir_id > 0) {
                    document.getElementById('simIrId').value = data.ir_id;
                    let extra = '';
                    if (data.comando_ir?.dispositivo === 'proyector' && data.comando_ir?.accion === 'apagar') {
                        extra = ` [Epson Apagar: ${data.comando_ir?.repeticiones} reps, pausa ${data.comando_ir?.pausa_ms}ms (doble pulso)]`;
                    } else if (data.comando_ir?.ac_state) {
                        extra = ` [Star AC State: ${data.comando_ir.ac_state}]`;
                    }
                    logConsola(`[AVISO] Se recibió comando IR ID #${data.ir_id} (${data.comando_ir?.dispositivo} -> ${data.comando_ir?.accion}${extra}) para Aula #${currentAulaId}.`);
                }
            } catch (err) {
                logConsola(`[ERROR] ${err.message}`);
            }
        });

        // 1.b Simular Sincronización Unificada (POST)
        document.getElementById('btnSimularSyncPost').addEventListener('click', async () => {
            const luces = document.getElementById('simLuces').value;
            const irId = document.getElementById('simIrId').value;
            const rssi = document.getElementById('simRssi').value;
            const uptime = document.getElementById('simUptime').value;

            const body = new URLSearchParams({
                aula_id: currentAulaId,
                luces: luces,
                ip: '192.168.1.150',
                rssi: rssi,
                uptime: uptime,
                firmware: '1.0.0-sim',
                ir_ejecutado: irId,
                cambio_local: '0'
            });

            logConsola(`>>> POST /api/esp32/sincronizar (Aula #${currentAulaId})\nCuerpo: ${body.toString()}`);
            try {
                const res = await fetch('api/esp32/sincronizar.php', {
                    method: 'POST',
                    headers: {
                        'X-API-Key': currentApiKey,
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: body
                });
                const texto = await res.text();
                logConsola(`<<< HTTP ${res.status}\n${texto.trim()}`);
                procesarRespuestaSync(texto);
            } catch (err) {
                logConsola(`[ERROR] ${err.message}`);
            }
        });

        function procesarRespuestaSync(texto) {
            const matchId = texto.match(/ir_id=(\d+)/);
            if (matchId && parseInt(matchId[1], 10) > 0) {
                document.getElementById('simIrId').value = matchId[1];
                logConsola(`[AVISO] Se recibió comando IR ID #${matchId[1]}. Campo de confirmación actualizado.`);
            }
            const matchLuces = texto.match(/luces=(\d+)/);
            if (matchLuces) {
                document.getElementById('simLuces').value = matchLuces[1];
            }
            const matchCode = texto.match(/ir_code=([^\n\r]+)/);
            const matchProto = texto.match(/ir_proto=([^\n\r]+)/);
            const matchRep = texto.match(/ir_rep=(\d+)/);
            const matchPausa = texto.match(/ir_pausa=(\d+)/);
            const matchAcState = texto.match(/ir_ac_state=([^\n\r]+)/);

            if (matchCode) {
                let detalle = `[HARDWARE IR RECIBIDO] Código: ${matchCode[1]} | Proto: ${matchProto ? matchProto[1] : 'NEC'}`;
                if (matchRep && parseInt(matchRep[1], 10) > 1) {
                    detalle += ` | Repeticiones: ${matchRep[1]} (Pausa: ${matchPausa ? matchPausa[1] : 100}ms)`;
                    if (parseInt(matchRep[1], 10) === 2 && matchPausa && parseInt(matchPausa[1], 10) === 2000) {
                        detalle += `\n-> [EPSON POWER OFF: Doble pulso de apagado con 2s de intervalo para confirmación]`;
                    }
                }
                if (matchAcState) {
                    detalle += `\n-> [STAR AC STATE]: ${matchAcState[1]}`;
                }
                logConsola(detalle);
            }
        }

        // 2. Encolar comandos de prueba para hardware real
        document.getElementById('btnEncolarAireEco').addEventListener('click', async () => {
            logConsola(`>>> POST api/aire.php (Star AC 24°C Frío Eco en Aula #${currentAulaId})...`);
            try {
                const res = await fetch('api/aire.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                    body: JSON.stringify({
                        aula_id: currentAulaId,
                        accion: 'preset_eco',
                        temp: 24,
                        modo: 'frio',
                        fan: 'auto',
                        swing: 1,
                        csrf_token: csrfToken
                    })
                });
                const data = await res.json();
                logConsola(`<<< Respuesta api/aire.php:\n${JSON.stringify(data, null, 2)}`);
            } catch (e) {
                logConsola(`[ERROR] ${e.message}`);
            }
        });

        document.getElementById('btnEncolarAireOff').addEventListener('click', async () => {
            logConsola(`>>> POST api/aire.php (Star AC Apagar en Aula #${currentAulaId})...`);
            try {
                const res = await fetch('api/aire.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                    body: JSON.stringify({
                        aula_id: currentAulaId,
                        accion: 'apagar',
                        csrf_token: csrfToken
                    })
                });
                const data = await res.json();
                logConsola(`<<< Respuesta api/aire.php:\n${JSON.stringify(data, null, 2)}`);
            } catch (e) {
                logConsola(`[ERROR] ${e.message}`);
            }
        });

        document.getElementById('btnEncolarProyectorOn').addEventListener('click', async () => {
            logConsola(`>>> POST api/proyector.php (Epson Encender en Aula #${currentAulaId})...`);
            try {
                const res = await fetch('api/proyector.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                    body: JSON.stringify({
                        aula_id: currentAulaId,
                        accion: 'prender',
                        csrf_token: csrfToken
                    })
                });
                const data = await res.json();
                logConsola(`<<< Respuesta api/proyector.php:\n${JSON.stringify(data, null, 2)}`);
            } catch (e) {
                logConsola(`[ERROR] ${e.message}`);
            }
        });

        document.getElementById('btnEncolarProyectorOff').addEventListener('click', async () => {
            logConsola(`>>> POST api/proyector.php (Epson Apagar con Doble Pulso 2s en Aula #${currentAulaId})...`);
            try {
                const res = await fetch('api/proyector.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                    body: JSON.stringify({
                        aula_id: currentAulaId,
                        accion: 'apagar',
                        csrf_token: csrfToken
                    })
                });
                const data = await res.json();
                logConsola(`<<< Respuesta api/proyector.php:\n${JSON.stringify(data, null, 2)}`);
            } catch (e) {
                logConsola(`[ERROR] ${e.message}`);
            }
        });

        // 3. Simular Reporte Tradicional
        document.getElementById('btnSimularReporte').addEventListener('click', async () => {
            const luces = document.getElementById('simLuces').value;
            const irId = document.getElementById('simIrId').value;
            const rssi = document.getElementById('simRssi').value;
            const uptime = document.getElementById('simUptime').value;

            const body = new URLSearchParams({
                aula_id: currentAulaId,
                luces: luces,
                ip: '192.168.1.150',
                rssi: rssi,
                uptime: uptime,
                firmware: '1.0.0-sim',
                ir_ejecutado: irId
            });

            logConsola(`>>> POST /api/esp32/reportar (Aula #${currentAulaId})\nCuerpo: ${body.toString()}`);
            try {
                const res = await fetch('api/esp32/reportar.php', {
                    method: 'POST',
                    headers: {
                        'X-API-Key': currentApiKey,
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: body
                });
                const texto = await res.text();
                logConsola(`<<< HTTP ${res.status}: ${texto.trim()}`);
                if (parseInt(irId, 10) > 0) {
                    document.getElementById('simIrId').value = '0';
                }
            } catch (err) {
                logConsola(`[ERROR] ${err.message}`);
            }
        });

        // 4. Simular Captura Avanzada
        document.getElementById('btnSimularCaptura').addEventListener('click', async () => {
            const proto = document.getElementById('capProto').value;
            const valor = document.getElementById('capValor').value;
            const bits = document.getElementById('capBits').value;
            const estadoHex = document.getElementById('capEstadoHex').value;
            const raw = document.getElementById('capRaw').value;

            const body = new URLSearchParams({
                aula_id: currentAulaId,
                protocolo: proto,
                valor: valor,
                bits: bits,
                estado_hex: estadoHex,
                raw: raw
            });

            logConsola(`>>> POST /api/esp32/captura (Aula #${currentAulaId})\nCuerpo: ${body.toString()}`);
            try {
                const res = await fetch('api/esp32/captura.php', {
                    method: 'POST',
                    headers: {
                        'X-API-Key': currentApiKey,
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: body
                });
                const texto = await res.text();
                logConsola(`<<< HTTP ${res.status}: ${texto.trim()}`);
            } catch (err) {
                logConsola(`[ERROR] ${err.message}`);
            }
        });
    </script>
</body>
</html>
