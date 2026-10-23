/**
 * =============================================================================
 * PROYECTO: Control de Aula
 * ARCHIVO: control_aula.ino
 * Firmware para ESP32 DevKit V1 (Core ESP32 2.0.17 en Arduino IDE 1.8.x)
 * 
 * Hardware:
 * - Relé de iluminación: GPIO 26 (Activo en ALTO)
 * - Emisor Infrarrojo KY-005: GPIO 18
 * - Receptor Infrarrojo KY-022: GPIO 15 (Buffer 1024 para tramas de A/C)
 * - LED indicador de estado: GPIO 2
 * 
 * Protocolo y Capacidades:
 * - Soporte multipropósito: Luces, Aire acondicionado y Proyector.
 * - Soporte IR avanzado: valores cortos, tramas largas de estado (A/C) y secuencias RAW.
 * - Polling unificado (POST /api/esp32/sincronizar) con conexión TLS persistente.
 * - Servidor local en puerto 80 con página HTML de respaldo en PROGMEM.
 * - Prioridad local de luces para no revertir si el servidor responde o está caído.
 * =============================================================================
 */

#include <Arduino.h>
#include <WiFi.h>
#include <WebServer.h>
#include <HTTPClient.h>
#include <WiFiClientSecure.h>
#include <IRremoteESP8266.h>
#include <IRsend.h>
#include <IRrecv.h>
#include <IRutils.h>
#include "config.h"

// -----------------------------------------------------------------------------
// PÁGINA HTML DE RESPALDO LOCAL EN "/" (PROGMEM)
// Incluye control de Luces, Aire acondicionado y Proyector
// -----------------------------------------------------------------------------
const char PAGINA_LOCAL_HTML[] PROGMEM = R"rawliteral(<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#1c1818">
<title>Control de Aula | Respaldo Local</title>
<style>
:root{--bg:#1c1818;--card:#262020;--text:#f4f0e6;--muted:#b8afa0;--accent:#897c37;--accent-hover:#9d8f40;--gold:#cdbf6a;--on:#10b981;--off:#ef4444;}
*{box-sizing:border-box;margin:0;padding:0;}
body{background:var(--bg);color:var(--text);font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;padding:16px;min-height:100vh;display:flex;flex-direction:column;align-items:center;}
.wrap{width:100%;max-width:440px;}
h1{font-size:22px;margin-bottom:4px;color:var(--text);font-weight:700;}
p.sub{font-size:12px;color:var(--muted);margin-bottom:16px;}
.card{background:var(--card);border:1px solid rgba(244,240,230,0.08);border-radius:12px;padding:16px;margin-bottom:14px;box-shadow:0 6px 18px rgba(0,0,0,0.5);}
.status-bar{display:flex;justify-content:space-between;align-items:center;font-size:13px;margin-bottom:10px;}
.badge{padding:3px 10px;border-radius:99px;font-weight:700;font-size:11px;letter-spacing:0.5px;}
.badge-on{background:rgba(16,185,129,0.15);color:var(--on);border:1px solid rgba(16,185,129,0.3);}
.badge-off{background:rgba(244,240,230,0.06);color:var(--muted);border:1px solid rgba(244,240,230,0.1);}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:6px;}
button{background:var(--accent);color:var(--text);border:none;border-radius:8px;padding:14px 10px;font-size:14px;font-weight:700;cursor:pointer;width:100%;transition:background .2s;}
button:hover{background:var(--accent-hover);}
button.btn-off{background:#3a3232;}
button.btn-off:hover{background:#4a4040;}
.tele-row{display:flex;justify-content:space-between;font-size:11px;color:var(--muted);padding:3px 0;}
.tele-val{color:var(--gold);font-family:monospace;font-weight:600;}
</style>
</head>
<body>
<div class="wrap">
  <h1>Control de Aula</h1>
  <p class="sub">Modo Respaldo Local (ESP32 - Puerto 80)</p>

  <div class="card">
    <div class="status-bar">
      <span>Luces (Relé GPIO 26)</span>
      <span id="st-luces" class="badge badge-off">APAGADAS</span>
    </div>
    <div class="grid">
      <button type="button" onclick="luces(1)">Luces Prender</button>
      <button type="button" class="btn-off" onclick="luces(0)">Luces Apagar</button>
    </div>
  </div>

  <div class="card">
    <div class="status-bar">
      <span>Aire Acondicionado (IR GPIO 18)</span>
      <span id="st-aire" style="color:var(--gold);font-weight:700;font-size:11px;">INFRARROJO</span>
    </div>
    <div class="grid">
      <button type="button" onclick="aire('prender')">Aire Prender</button>
      <button type="button" class="btn-off" onclick="aire('apagar')">Aire Apagar</button>
    </div>
  </div>

  <div class="card">
    <div class="status-bar">
      <span>Proyector (IR GPIO 18)</span>
      <span id="st-proyector" style="color:var(--gold);font-weight:700;font-size:11px;">INFRARROJO</span>
    </div>
    <div class="grid">
      <button type="button" onclick="proyector('prender')">Proyector Prender</button>
      <button type="button" class="btn-off" onclick="proyector('apagar')">Proyector Apagar</button>
    </div>
  </div>

  <div class="card">
    <div class="tele-row"><span>IP Local:</span><span id="tel-ip" class="tele-val">--</span></div>
    <div class="tele-row"><span>Señal WiFi:</span><span id="tel-rssi" class="tele-val">--</span></div>
    <div class="tele-row"><span>Uptime:</span><span id="tel-up" class="tele-val">--</span></div>
    <div class="tele-row"><span>Servidor OVH:</span><span id="tel-ovh" class="tele-val">--</span></div>
  </div>
</div>
<script>
function luces(e){fetch('/luces?estado='+e).then(r=>r.json()).then(()=>actualizar()).catch(console.error);}
function aire(a){fetch('/aire?accion='+a).then(r=>r.json()).then(()=>actualizar()).catch(console.error);}
function proyector(a){fetch('/proyector?accion='+a).then(r=>r.json()).then(()=>actualizar()).catch(console.error);}
function actualizar(){
  fetch('/estado').then(r=>r.json()).then(d=>{
    const l=document.getElementById('st-luces');
    if(d.luces===1){l.className='badge badge-on';l.textContent='ENCENDIDAS';}
    else{l.className='badge badge-off';l.textContent='APAGADAS';}
    document.getElementById('tel-ip').textContent=d.ip||'--';
    document.getElementById('tel-rssi').textContent=(d.rssi!==undefined?d.rssi+' dBm':'--');
    document.getElementById('tel-up').textContent=(d.uptime||0)+' s';
    document.getElementById('tel-ovh').textContent=d.servidor_ovh_ok?'CONECTADO':'SIN CONEXIÓN';
  }).catch(console.error);
}
actualizar();
setInterval(actualizar,3000);
</script>
</body>
</html>)rawliteral";

// -----------------------------------------------------------------------------
// INSTANCIAS DE DISPOSITIVOS Y SERVICIOS
// Receptor configurado con buffer de 1024 para soportar tramas largas de aire acondicionado
// -----------------------------------------------------------------------------
WebServer servidorLocal(PUERTO_LOCAL_HTTP);
IRsend emisorIR(PIN_IR_SEND);
IRrecv receptorIR(PIN_IR_RECV, 1024, 50, true);
decode_results resultadosIR;

// Cliente HTTPS global para reutilizar sockets TLS persistentes
WiFiClientSecure clienteSSL;
HTTPClient http;

// -----------------------------------------------------------------------------
// VARIABLES DE ESTADO Y TIEMPOS
// -----------------------------------------------------------------------------
int estadoLucesReal = 0;              // 0 = Apagadas, 1 = Encendidas
bool cambioLocalLuces = false;        // Bandera de cambio local prioritario
int ultimoIrEjecutadoId = 0;          // ID de comando IR para confirmación
bool ultimoSyncOk = false;            // Bandera de comunicación con servidor OVH
unsigned long ultimoPollingMs = 0;    // Temporizador para polling
unsigned long intervaloPolling = INTERVALO_POLLING_MS;
unsigned long ultimoLedToggleMs = 0;  // Temporizador para parpadeo de LED
bool estadoLed = false;
unsigned long ultimaReconexionMs = 0; // Temporizador para reconexión WiFi

// -----------------------------------------------------------------------------
// DECLARACIÓN PREVIA DE FUNCIONES
// -----------------------------------------------------------------------------
void configurarServidorLocal();
void vigilarWiFi();
void actualizarLedEstado();
void emitirRafagaDiagnostico();
void transmitirComandoIR(const String& protoStr, const String& codeHex, uint16_t bits,
                         const String& stateHex, const String& rawData,
                         uint16_t repeticiones, uint16_t pausaMs, uint16_t khz);
void ejecutarEnvioRaw(const String& rawStr, uint16_t khz);
void ejecutarEnvioEstado(decode_type_t tipo, const String& estadoHex);
void ejecutarEnvioCorto(decode_type_t tipo, uint64_t codigoNum, uint16_t bits);
uint64_t convertirHexAUint64(const String& hexStr);
void verificarCapturaIR();
void verificarComandosSerial();
void sincronizarYReportar();
String formatearRutaUrl(const String& base, const String& ruta);

// -----------------------------------------------------------------------------
// INICIALIZACIÓN (SETUP)
// -----------------------------------------------------------------------------
void setup() {
    Serial.begin(115200);
    delay(200);
    Serial.println();
    Serial.println(F("=================================================="));
    Serial.println(F("SISTEMA CONTROL DE AULA - ESP32 FIRMWARE"));
    Serial.print(F("Version: "));
    Serial.println(F(FIRMWARE_VERSION));
    Serial.println(F("=================================================="));

    // 1. Configuración de pines de hardware
    pinMode(PIN_RELE_LUCES, OUTPUT);
    digitalWrite(PIN_RELE_LUCES, LOW); // Iniciar siempre apagado
    estadoLucesReal = 0;
    cambioLocalLuces = false;

    pinMode(PIN_LED_ESTADO, OUTPUT);
    digitalWrite(PIN_LED_ESTADO, LOW);

    // 2. Inicialización de periféricos infrarrojos
    emisorIR.begin();
    receptorIR.setUnknownThreshold(12);
    receptorIR.enableIRIn();

    // 3. Configuración del cliente SSL global persistente
    #if VERIFICAR_CERTIFICADO_SSL
        // Para verificación estricta se debe configurar el certificado CA
    #else
        clienteSSL.setInsecure();
    #endif
    clienteSSL.setTimeout(HTTP_TIMEOUT_MS / 1000);
    http.setReuse(true); // Reutilizar socket TLS para minimizar latencia en el loop
    http.setTimeout(HTTP_TIMEOUT_MS);

    // 4. Conexión a red WiFi
    Serial.print(F("[WIFI] Conectando a red: "));
    Serial.println(WIFI_SSID);
    WiFi.mode(WIFI_STA);
    WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

    unsigned long inicioWiFi = millis();
    while (WiFi.status() != WL_CONNECTED && (millis() - inicioWiFi < 8000)) {
        digitalWrite(PIN_LED_ESTADO, !digitalRead(PIN_LED_ESTADO));
        delay(200);
        Serial.print(F("."));
    }
    Serial.println();

    if (WiFi.status() == WL_CONNECTED) {
        Serial.print(F("[WIFI] Conectado exitosamente. IP Local: "));
        Serial.println(WiFi.localIP());
        Serial.print(F("[WIFI] Senial RSSI: "));
        Serial.print(WiFi.RSSI());
        Serial.println(F(" dBm"));
    } else {
        Serial.println(F("[WIFI] No se pudo conectar de inmediato. Se continuara en segundo plano."));
    }

    // 5. Inicialización del servidor HTTP local de respaldo
    configurarServidorLocal();
    servidorLocal.begin();
    Serial.println(F("[HTTP LOCAL] Servidor de respaldo activo en puerto 80"));
    Serial.println(F("[SISTEMA] Inicializacion finalizada. Bucle principal activo."));
}

// -----------------------------------------------------------------------------
// BUCLE PRINCIPAL (LOOP)
// -----------------------------------------------------------------------------
void loop() {
    // 1. Atender solicitudes del servidor HTTP local
    servidorLocal.handleClient();

    // 2. Supervisar conexión WiFi de forma no bloqueante
    vigilarWiFi();

    // 3. Leer códigos infrarrojos entrantes del KY-022
    verificarCapturaIR();

    // 4. Atender comandos de diagnóstico por Monitor Serie
    verificarComandosSerial();

    // 5. Actualizar el estado del LED integrado según la conectividad
    actualizarLedEstado();

    // 6. Polling periódico al servidor OVH si hay WiFi
    if (WiFi.status() == WL_CONNECTED) {
        unsigned long ahora = millis();
        if (ahora - ultimoPollingMs >= intervaloPolling) {
            ultimoPollingMs = ahora;
            sincronizarYReportar();
        }
    }
}

// -----------------------------------------------------------------------------
// GESTIÓN DE WIFI NO BLOQUEANTE
// -----------------------------------------------------------------------------
void vigilarWiFi() {
    if (WiFi.status() != WL_CONNECTED) {
        ultimoSyncOk = false;
        unsigned long ahora = millis();
        if (ahora - ultimaReconexionMs >= 10000) {
            ultimaReconexionMs = ahora;
            Serial.println(F("[WIFI] Desconectado. Reintentando conexion..."));
            WiFi.disconnect();
            WiFi.reconnect();
        }
    }
}

// -----------------------------------------------------------------------------
// CONTROL DEL LED DE ESTADO (GPIO 2)
// -----------------------------------------------------------------------------
void actualizarLedEstado() {
    if (WiFi.status() == WL_CONNECTED && ultimoSyncOk) {
        digitalWrite(PIN_LED_ESTADO, HIGH);
    } else {
        unsigned long ahora = millis();
        if (ahora - ultimoLedToggleMs >= 500) {
            ultimoLedToggleMs = ahora;
            estadoLed = !estadoLed;
            digitalWrite(PIN_LED_ESTADO, estadoLed ? HIGH : LOW);
        }
    }
}

// -----------------------------------------------------------------------------
// POLLING UNIFICADO (POST /api/esp32/sincronizar): TELEMETRÍA + COMANDOS
// -----------------------------------------------------------------------------
void sincronizarYReportar() {
    String urlSync = formatearRutaUrl(URL_SERVIDOR, "/api/esp32/sincronizar");

    if (!http.begin(clienteSSL, urlSync)) {
        Serial.println(F("[HTTP SYNC] Error al inicializar conexion SSL"));
        ultimoSyncOk = false;
        intervaloPolling = INTERVALO_BACKOFF_MS;
        return;
    }

    http.addHeader("Content-Type", "application/x-www-form-urlencoded");
    http.addHeader("X-API-Key", API_KEY);

    // Preparar telemetría y confirmaciones
    String cuerpo = "luces=" + String(estadoLucesReal) +
                    "&ip=" + WiFi.localIP().toString() +
                    "&rssi=" + String(WiFi.RSSI()) +
                    "&uptime=" + String(millis() / 1000) +
                    "&firmware=" + String(FIRMWARE_VERSION) +
                    "&ir_ejecutado=" + String(ultimoIrEjecutadoId) +
                    "&cambio_local=" + String(cambioLocalLuces ? 1 : 0);

    int httpCodigo = http.POST(cuerpo);

    int lucesDeseadas = -1;
    int comandoIrId = 0;
    String irProto = "NEC";
    String irCode = "";
    int irBits = 32;
    String irState = "";
    String irRaw = "";
    int irRep = 1;
    int irPausa = 100;
    int irKhz = 38;

    if (httpCodigo == 200) {
        ultimoSyncOk = true;
        intervaloPolling = INTERVALO_POLLING_MS;

        if (ultimoIrEjecutadoId > 0) {
            Serial.print(F("[IR] Confirmado comando ID #"));
            Serial.println(ultimoIrEjecutadoId);
            ultimoIrEjecutadoId = 0;
        }

        if (cambioLocalLuces) {
            cambioLocalLuces = false;
            Serial.println(F("[RELE] Cambio local de luces sincronizado con servidor."));
        }

        String respuesta = http.getString();

        // Parsear líneas clave=valor sin ArduinoJson
        int inicio = 0;
        while (inicio < (int)respuesta.length()) {
            int fin = respuesta.indexOf('\n', inicio);
            if (fin == -1) fin = respuesta.length();
            String linea = respuesta.substring(inicio, fin);
            linea.trim();
            inicio = fin + 1;

            if (linea.length() == 0) continue;
            int sep = linea.indexOf('=');
            if (sep > 0) {
                String clave = linea.substring(0, sep);
                String valor = linea.substring(sep + 1);
                clave.trim();
                valor.trim();

                if (clave.equalsIgnoreCase("luces")) {
                    lucesDeseadas = valor.toInt();
                } else if (clave.equalsIgnoreCase("ir_id")) {
                    comandoIrId = valor.toInt();
                } else if (clave.equalsIgnoreCase("ir_proto")) {
                    irProto = valor;
                } else if (clave.equalsIgnoreCase("ir_code")) {
                    irCode = valor;
                } else if (clave.equalsIgnoreCase("ir_bits")) {
                    irBits = valor.toInt();
                } else if (clave.equalsIgnoreCase("ir_state")) {
                    irState = valor;
                } else if (clave.equalsIgnoreCase("ir_raw")) {
                    irRaw = valor;
                } else if (clave.equalsIgnoreCase("ir_rep")) {
                    irRep = valor.toInt();
                } else if (clave.equalsIgnoreCase("ir_pausa")) {
                    irPausa = valor.toInt();
                } else if (clave.equalsIgnoreCase("ir_khz")) {
                    irKhz = valor.toInt();
                }
            }
        }

        // Aplicar luces del servidor solo si no hay una acción local inmediata
        if (!cambioLocalLuces && (lucesDeseadas == 0 || lucesDeseadas == 1)) {
            if (estadoLucesReal != lucesDeseadas) {
                estadoLucesReal = lucesDeseadas;
                digitalWrite(PIN_RELE_LUCES, estadoLucesReal == 1 ? HIGH : LOW);
                Serial.print(F("[RELE] Luces actualizadas segun servidor: "));
                Serial.println(estadoLucesReal == 1 ? F("ENCENDIDAS") : F("APAGADAS"));
            }
        }

        // Ejecutar comando IR si hay uno pendiente
        if (comandoIrId > 0) {
            Serial.print(F("[IR] Ejecutando comando ID #"));
            Serial.print(comandoIrId);
            Serial.print(F(" (Proto: "));
            Serial.print(irProto);
            Serial.print(F(", Reps: "));
            Serial.print(irRep);
            Serial.println(F(")"));

            transmitirComandoIR(irProto, irCode, irBits, irState, irRaw, irRep, irPausa, irKhz);
            ultimoIrEjecutadoId = comandoIrId; // Se confirmará en el próximo polling
        }
    } else {
        Serial.print(F("[HTTP SYNC] Error en consulta unificada. Codigo HTTP: "));
        Serial.println(httpCodigo);
        ultimoSyncOk = false;
        intervaloPolling = INTERVALO_BACKOFF_MS;
        http.end();
    }
}

// -----------------------------------------------------------------------------
// RECEPTOR INFRARROJO KY-022: CAPTURA AVANZADA (HEX, ESTADO Y RAW)
// -----------------------------------------------------------------------------
void verificarCapturaIR() {
    if (receptorIR.decode(&resultadosIR)) {
        String protocolo = typeToString(resultadosIR.decode_type);
        String valorHex = resultToHexidecimal(&resultadosIR);
        int bits = resultadosIR.bits;
        String estadoHex = "";
        String rawStr = "";

        // Captura de tramas largas de estado (aires acondicionados)
        if (hasState(resultadosIR.decode_type)) {
            uint16_t nBytes = resultadosIR.bits / 8;
            if (nBytes == 0) nBytes = kStateSizeMax;
            for (uint16_t i = 0; i < nBytes; i++) {
                if (resultadosIR.state[i] < 0x10) estadoHex += "0";
                estadoHex += String(resultadosIR.state[i], HEX);
            }
            estadoHex.toUpperCase();
        }

        // Captura de secuencia RAW en microsegundos
        uint16_t rawLen = getCorrectedRawLength(&resultadosIR);
        if (rawLen > 0) {
            uint16_t *rawArray = resultToRawArray(&resultadosIR);
            if (rawArray != NULL) {
                rawStr.reserve(rawLen * 6);
                for (uint16_t i = 0; i < rawLen; i++) {
                    if (i > 0) rawStr += ",";
                    rawStr += String(rawArray[i]);
                }
                delete[] rawArray; // Liberar memoria dinámica inmediatamente
            }
        }

        Serial.println();
        Serial.println(F(">>> [CAPTURA IR KY-022 RECIBIDA] <<<"));
        Serial.print(F("Protocolo: ")); Serial.println(protocolo);
        Serial.print(F("Valor HEX: ")); Serial.println(valorHex);
        Serial.print(F("Bits: ")); Serial.println(bits);
        if (estadoHex.length() > 0) {
            Serial.print(F("Estado HEX: ")); Serial.println(estadoHex);
        }
        if (rawStr.length() > 0) {
            Serial.print(F("Muestras RAW: ")); Serial.println(rawLen);
        }

        // Enviar captura al servidor OVH si hay WiFi
        if (WiFi.status() == WL_CONNECTED) {
            HTTPClient httpCap;
            String urlCap = formatearRutaUrl(URL_SERVIDOR, "/api/esp32/captura");
            if (httpCap.begin(clienteSSL, urlCap)) {
                httpCap.setTimeout(4000);
                httpCap.addHeader("Content-Type", "application/x-www-form-urlencoded");
                httpCap.addHeader("X-API-Key", API_KEY);
                String postBody = "protocolo=" + protocolo +
                                  "&valor=" + valorHex +
                                  "&bits=" + String(bits) +
                                  "&estado_hex=" + estadoHex +
                                  "&raw=" + rawStr;
                int cod = httpCap.POST(postBody);
                if (cod == 200) {
                    Serial.println(F("[CAPTURA IR] Enviada y registrada exitosamente en OVH."));
                } else {
                    Serial.print(F("[CAPTURA IR] Error en envio: ")); Serial.println(cod);
                }
                httpCap.end();
            }
        }

        receptorIR.resume(); // Habilitar recepción del siguiente valor
    }
}

// -----------------------------------------------------------------------------
// TRANSMISIÓN INFRARROJA MULTIPROTOCOLO (RAW, ESTADO Y CORTO)
// -----------------------------------------------------------------------------
void ejecutarEnvioRaw(const String& rawStr, uint16_t khz) {
    if (rawStr.length() == 0) return;

    // Contar comas para dimensionar arreglo dinámico
    uint16_t count = 1;
    for (unsigned int i = 0; i < rawStr.length(); i++) {
        if (rawStr.charAt(i) == ',') count++;
    }

    uint16_t* rawBuf = new uint16_t[count];
    if (!rawBuf) return;

    uint16_t idx = 0;
    int start = 0;
    while (start < (int)rawStr.length() && idx < count) {
        int comma = rawStr.indexOf(',', start);
        if (comma == -1) comma = rawStr.length();
        String valStr = rawStr.substring(start, comma);
        valStr.trim();
        rawBuf[idx++] = (uint16_t) valStr.toInt();
        start = comma + 1;
    }

    uint16_t hz = (khz > 0 ? khz : 38) * 1000;
    emisorIR.sendRaw(rawBuf, idx, hz);

    delete[] rawBuf; // Liberar memoria dinámica inmediatamente
}

void ejecutarEnvioEstado(decode_type_t tipo, const String& estadoHex) {
    String cleanHex = estadoHex;
    cleanHex.replace(" ", "");
    cleanHex.replace("0x", "");
    cleanHex.replace("0X", "");
    cleanHex.replace(",", "");

    uint16_t numBytes = cleanHex.length() / 2;
    if (numBytes == 0) return;

    uint8_t* stateBuf = new uint8_t[numBytes];
    if (!stateBuf) return;

    for (uint16_t i = 0; i < numBytes; i++) {
        String byteStr = cleanHex.substring(i * 2, (i * 2) + 2);
        stateBuf[i] = (uint8_t) strtoul(byteStr.c_str(), NULL, 16);
    }

    emisorIR.send(tipo, stateBuf, numBytes);

    delete[] stateBuf; // Liberar memoria dinámica inmediatamente
}

void ejecutarEnvioCorto(decode_type_t tipo, uint64_t codigoNum, uint16_t bits) {
    uint16_t nbits = bits > 0 ? bits : 32;
    if (tipo != UNKNOWN && emisorIR.send(tipo, codigoNum, nbits)) {
        // Envió con éxito usando el protocolo reconocido
    } else {
        // Fallback a NEC
        emisorIR.sendNEC(codigoNum, nbits);
    }
}

void transmitirComandoIR(const String& protoStr, const String& codeHex, uint16_t bits,
                         const String& stateHex, const String& rawData,
                         uint16_t repeticiones, uint16_t pausaMs, uint16_t khz) {
    decode_type_t tipo = strToDecodeType(protoStr.c_str());
    uint16_t reps = (repeticiones > 0) ? repeticiones : 1;
    uint16_t pausa = (pausaMs > 0) ? pausaMs : 100;
    uint64_t codigoNum = convertirHexAUint64(codeHex);

    receptorIR.disableIRIn();
    delay(15);

    for (uint16_t r = 0; r < reps; r++) {
        if (r > 0) {
            delay(pausa);
        }

        if (rawData.length() > 0) {
            ejecutarEnvioRaw(rawData, khz);
        } else if (stateHex.length() > 0) {
            ejecutarEnvioEstado(tipo, stateHex);
        } else if (codeHex.length() > 0) {
            ejecutarEnvioCorto(tipo, codigoNum, bits);
        }
    }

    delay(20);
    receptorIR.enableIRIn();
}

/**
 * Diagnóstico de emisor: emite una ráfaga continua durante 3 segundos
 * para comprobar con la cámara del celular si el LED infrarrojo enciende.
 */
void emitirRafagaDiagnostico() {
    Serial.println(F("[DIAGNOSTICO IR] Iniciando rafaga de 3 segundos en GPIO 18..."));
    receptorIR.disableIRIn();
    unsigned long inicio = millis();
    while (millis() - inicio < 3000) {
        emisorIR.sendNEC(CODIGO_IR_PRENDER_DEF, 32);
        delay(80);
        servidorLocal.handleClient();
    }
    receptorIR.enableIRIn();
    Serial.println(F("[DIAGNOSTICO IR] Rafaga completada."));
}

void verificarComandosSerial() {
    if (Serial.available()) {
        String cmd = Serial.readStringUntil('\n');
        cmd.trim();
        if (cmd.equalsIgnoreCase("test") || cmd.equalsIgnoreCase("test_ir")) {
            emitirRafagaDiagnostico();
        }
    }
}

// -----------------------------------------------------------------------------
// SERVIDOR WEB LOCAL DE RESPALDO (PUERTO 80)
// -----------------------------------------------------------------------------
void responderJSONLocal(int codigo, const String& json) {
    servidorLocal.sendHeader("Access-Control-Allow-Origin", "*");
    servidorLocal.sendHeader("Access-Control-Allow-Methods", "GET, POST, OPTIONS");
    servidorLocal.sendHeader("Access-Control-Allow-Headers", "*");
    servidorLocal.send(codigo, "application/json", json);
}

void configurarServidorLocal() {
    // Ruta principal: página de respaldo autocontenida en PROGMEM
    servidorLocal.on("/", HTTP_GET, []() {
        servidorLocal.send_P(200, "text/html", PAGINA_LOCAL_HTML);
    });

    // Ruta /estado: JSON con CORS
    servidorLocal.on("/estado", HTTP_GET, []() {
        String json = "{\"ok\":true,\"luces\":" + String(estadoLucesReal) +
                      ",\"ip\":\"" + WiFi.localIP().toString() + "\"" +
                      ",\"rssi\":" + String(WiFi.RSSI()) +
                      ",\"uptime\":" + String(millis() / 1000) +
                      ",\"servidor_ovh_ok\":" + String(ultimoSyncOk ? "true" : "false") +
                      ",\"version\":\"" + String(FIRMWARE_VERSION) + "\"}";
        responderJSONLocal(200, json);
    });

    // Ruta /luces?estado=1|0
    servidorLocal.on("/luces", HTTP_GET, []() {
        if (servidorLocal.hasArg("estado")) {
            int nuevo = servidorLocal.arg("estado").toInt();
            if (nuevo == 0 || nuevo == 1) {
                estadoLucesReal = nuevo;
                cambioLocalLuces = true; // Indicar cambio local prioritario
                digitalWrite(PIN_RELE_LUCES, estadoLucesReal == 1 ? HIGH : LOW);
                Serial.print(F("[HTTP LOCAL] Cambio manual prioritario de luces a: "));
                Serial.println(estadoLucesReal);
                responderJSONLocal(200, "{\"ok\":true,\"mensaje\":\"Luces actualizadas localmente\",\"luces\":" + String(estadoLucesReal) + "}");
                return;
            }
        }
        responderJSONLocal(400, "{\"ok\":false,\"error\":\"Parametro estado invalido (use 1 o 0)\"}");
    });

    // Ruta /aire?accion=prender|apagar
    servidorLocal.on("/aire", HTTP_GET, []() {
        if (servidorLocal.hasArg("accion")) {
            String acc = servidorLocal.arg("accion");
            acc.toLowerCase();
            if (acc == "prender") {
                transmitirComandoIR("NEC", String(CODIGO_IR_PRENDER_DEF, HEX), 32, "", "", 1, 100, 38);
                Serial.println(F("[HTTP LOCAL] Comando IR Prender Aire enviado"));
                responderJSONLocal(200, "{\"ok\":true,\"mensaje\":\"Comando IR Prender Aire enviado\"}");
                return;
            } else if (acc == "apagar") {
                transmitirComandoIR("NEC", String(CODIGO_IR_APAGAR_DEF, HEX), 32, "", "", 1, 100, 38);
                Serial.println(F("[HTTP LOCAL] Comando IR Apagar Aire enviado"));
                responderJSONLocal(200, "{\"ok\":true,\"mensaje\":\"Comando IR Apagar Aire enviado\"}");
                return;
            }
        }
        responderJSONLocal(400, "{\"ok\":false,\"error\":\"Parametro accion invalido (prender o apagar)\"}");
    });

    // Ruta /proyector?accion=prender|apagar
    servidorLocal.on("/proyector", HTTP_GET, []() {
        if (servidorLocal.hasArg("accion")) {
            String acc = servidorLocal.arg("accion");
            acc.toLowerCase();
            if (acc == "prender") {
                transmitirComandoIR("NEC", String(CODIGO_PROYECTOR_PRENDER_DEF, HEX), 32, "", "", 1, 100, 38);
                Serial.println(F("[HTTP LOCAL] Comando IR Prender Proyector enviado"));
                responderJSONLocal(200, "{\"ok\":true,\"mensaje\":\"Comando IR Prender Proyector enviado\"}");
                return;
            } else if (acc == "apagar") {
                // El proyector usualmente requiere 2 repeticiones con pausa
                transmitirComandoIR("NEC", String(CODIGO_PROYECTOR_APAGAR_DEF, HEX), 32, "", "", 2, 250, 38);
                Serial.println(F("[HTTP LOCAL] Comando IR Apagar Proyector enviado (2x)"));
                responderJSONLocal(200, "{\"ok\":true,\"mensaje\":\"Comando IR Apagar Proyector enviado\"}");
                return;
            }
        }
        responderJSONLocal(400, "{\"ok\":false,\"error\":\"Parametro accion invalido (prender o apagar)\"}");
    });

    // Ruta /test_ir: Diagnóstico de ráfaga
    servidorLocal.on("/test_ir", HTTP_GET, []() {
        emitirRafagaDiagnostico();
        responderJSONLocal(200, "{\"ok\":true,\"mensaje\":\"Rafaga de diagnostico IR completada (3 segundos)\"}");
    });

    // Manejo de pre-flight CORS y errores 404
    servidorLocal.onNotFound([]() {
        if (servidorLocal.method() == HTTP_OPTIONS) {
            responderJSONLocal(200, "{}");
        } else {
            responderJSONLocal(404, "{\"ok\":false,\"error\":\"Ruta no encontrada\"}");
        }
    });
}

// -----------------------------------------------------------------------------
// UTILIDADES
// -----------------------------------------------------------------------------
uint64_t convertirHexAUint64(const String& hexStr) {
    if (hexStr.length() == 0) return 0;
    const char* str = hexStr.c_str();
    if (hexStr.startsWith("0x") || hexStr.startsWith("0X")) {
        str += 2;
    }
    return (uint64_t) strtoull(str, NULL, 16);
}

String formatearRutaUrl(const String& base, const String& ruta) {
    if (base.endsWith("/") && ruta.startsWith("/")) {
        return base + ruta.substring(1);
    } else if (!base.endsWith("/") && !ruta.startsWith("/")) {
        return base + "/" + ruta;
    }
    return base + ruta;
}
