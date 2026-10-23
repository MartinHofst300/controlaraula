<?php
/**
 * Simulador Web de ESP32 para pruebas de extremo a extremo sin hardware
 * Protegido por sesión de usuario del panel.
 * Soporta dispositivos múltiples (aire y proyector) y tramas avanzadas (hex, estado y raw).
 */

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';

iniciarSesionSegura();

if (empty($_SESSION['autenticado']) || $_SESSION['autenticado'] !== true) {
    header('Location: login.php');
    exit;
}

$apiKeyActual = API_KEY_ESP32;
$csrfToken = obtenerTokenCSRF();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1c1818">
    <title>Simulador ESP32 | Control de Aula</title>
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
                    <h1>Simulador de ESP32</h1>
                    <span>Validación de protocolo (Aire, Proyector y tramas avanzadas)</span>
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
                <!-- Tarjeta 1: Sincronización Unificada -->
                <div class="tarjeta-seccion">
                    <h3 style="font-size:16px; margin-bottom:8px;">1. Polling: Sincronización Unificada</h3>
                    <p style="font-size:12px; color:var(--text-secondary); margin-bottom:14px;">
                        Consulta <code>/api/esp32/sincronizar</code>. En POST envía telemetría y recibe comandos en la misma conexión TLS.
                    </p>
                    <div style="display:flex; gap:10px;">
                        <button type="button" id="btnSimularSyncPost" class="btn btn-primario btn-bloque">
                            <span>POST Sincronizar (Unificado)</span>
                        </button>
                        <button type="button" id="btnSimularSync" class="btn btn-secundario btn-bloque">
                            <span>GET (Solo consulta)</span>
                        </button>
                    </div>
                </div>

                <!-- Tarjeta 2: Encolar comandos de prueba -->
                <div class="tarjeta-seccion">
                    <h3 style="font-size:16px; margin-bottom:8px;">2. Encolar Comandos IR (Panel/App)</h3>
                    <p style="font-size:12px; color:var(--text-secondary); margin-bottom:14px;">
                        Simula una orden enviada desde la app para probar la entrega al ESP32.
                    </p>
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                        <button type="button" id="btnEncolarAire" class="btn btn-secundario btn-pequeno">
                            <span>Encolar Aire (Prender)</span>
                        </button>
                        <button type="button" id="btnEncolarProyector" class="btn btn-secundario btn-pequeno">
                            <span>Encolar Proyector (Prender)</span>
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
                    <h3 style="font-size:16px; margin-bottom:8px;">4. Simular Lectura KY-022 (Hex, Estado o Raw)</h3>
                    <p style="font-size:12px; color:var(--text-secondary); margin-bottom:14px;">
                        Envía <code>POST /api/esp32/captura</code> con tramas cortas, de estado largo o microsegundos raw.
                    </p>
                    <div class="formulario-grilla" style="grid-template-columns: 1fr 1fr 1fr; margin-bottom:14px;">
                        <div class="campo-grupo">
                            <label for="capProto">Protocolo</label>
                            <input type="text" id="capProto" value="NEC">
                        </div>
                        <div class="campo-grupo">
                            <label for="capValor">Código HEX</label>
                            <input type="text" id="capValor" value="0x4CB0F00F">
                        </div>
                        <div class="campo-grupo">
                            <label for="capBits">Bits</label>
                            <input type="number" id="capBits" value="32">
                        </div>
                        <div class="campo-grupo col-span-4" style="grid-column: span 3;">
                            <label for="capEstadoHex">Trama de Estado HEX (opcional para A/C)</label>
                            <input type="text" id="capEstadoHex" placeholder="Ej. 0x23CB2601002003...">
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
                <div id="consolaLog" class="consola-log" style="flex:1; min-height:540px;">[SISTEMA] Simulador listo.
API Key configurada: <?php echo htmlspecialchars($apiKeyActual, ENT_QUOTES, 'UTF-8'); ?>
--------------------------------------------------------------------------------</div>
            </div>
        </div>
    </div>

    <script>
        const apiKey = <?php echo json_encode($apiKeyActual); ?>;
        const csrfToken = <?php echo json_encode($csrfToken); ?>;
        const consola = document.getElementById('consolaLog');

        function logConsola(texto) {
            const hora = new Date().toLocaleTimeString();
            consola.textContent += `\n[${hora}] ${texto}`;
            consola.scrollTop = consola.scrollHeight;
        }

        document.getElementById('btnLimpiarConsola').addEventListener('click', () => {
            consola.textContent = '[SISTEMA] Consola limpia.';
        });

        // 1.a Simular Sincronización GET
        document.getElementById('btnSimularSync').addEventListener('click', async () => {
            logConsola('>>> GET /api/esp32/sincronizar (Header X-API-Key)');
            try {
                const res = await fetch('api/esp32/sincronizar.php', {
                    headers: { 'X-API-Key': apiKey }
                });
                const texto = await res.text();
                logConsola(`<<< HTTP ${res.status}\n${texto.trim()}`);

                procesarRespuestaSync(texto);
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
                luces: luces,
                ip: '192.168.1.150',
                rssi: rssi,
                uptime: uptime,
                firmware: '1.0.0-sim',
                ir_ejecutado: irId,
                cambio_local: '0'
            });

            logConsola(`>>> POST /api/esp32/sincronizar (Telemetría + Comandos)\nCuerpo: ${body.toString()}`);
            try {
                const res = await fetch('api/esp32/sincronizar.php', {
                    method: 'POST',
                    headers: {
                        'X-API-Key': apiKey,
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
        }

        // 2. Encolar comandos de prueba
        document.getElementById('btnEncolarAire').addEventListener('click', async () => {
            logConsola('>>> Encolando comando: aire -> prender...');
            try {
                const res = await fetch('api/ir.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify({ dispositivo: 'aire', accion: 'prender', csrf_token: csrfToken })
                });
                const data = await res.json();
                logConsola(`<<< Encolado: ${JSON.stringify(data)}`);
            } catch (e) {
                logConsola(`[ERROR] ${e.message}`);
            }
        });

        document.getElementById('btnEncolarProyector').addEventListener('click', async () => {
            logConsola('>>> Encolando comando: proyector -> prender...');
            try {
                const res = await fetch('api/ir.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify({ dispositivo: 'proyector', accion: 'prender', csrf_token: csrfToken })
                });
                const data = await res.json();
                logConsola(`<<< Encolado: ${JSON.stringify(data)}`);
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
                luces: luces,
                ip: '192.168.1.150',
                rssi: rssi,
                uptime: uptime,
                firmware: '1.0.0-sim',
                ir_ejecutado: irId
            });

            logConsola(`>>> POST /api/esp32/reportar\nCuerpo: ${body.toString()}`);
            try {
                const res = await fetch('api/esp32/reportar.php', {
                    method: 'POST',
                    headers: {
                        'X-API-Key': apiKey,
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
                protocolo: proto,
                valor: valor,
                bits: bits,
                estado_hex: estadoHex,
                raw: raw
            });

            logConsola(`>>> POST /api/esp32/captura\nCuerpo: ${body.toString()}`);
            try {
                const res = await fetch('api/esp32/captura.php', {
                    method: 'POST',
                    headers: {
                        'X-API-Key': apiKey,
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
