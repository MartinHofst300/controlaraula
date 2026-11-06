/**
 * =============================================================================
 * PROYECTO: Control de Aula
 * ARCHIVO: control_aula.ino
 * Firmware para ESP32 DevKit V1 (Core ESP32 en Arduino IDE)
 *
 * Hardware:
 * - Rele de iluminacion: GPIO 26 (Activo en ALTO / HIGH)
 * - Emisor Infrarrojo KY-005: GPIO 18
 * - Receptor Infrarrojo KY-022: GPIO 15 (Buffer 1024 para tramas de A/C)
 * - LED indicador de estado: GPIO 2
 *
 * Protocolos y Capacidades:
 * - Soporte multiproposito: Iluminacion, Aire Acondicionado y Proyector Epson.
 * - Soporte IR avanzado: codigos cortos (NEC, COOLIX, GREE), tramas de estado y
 * RAW.
 * - Doble pulso con pausa de 2 segundos para confirmacion de apagado de
 * Proyector Epson.
 * - Polling unificado y envio de telemetria (POST a sincronizar.php,
 * reportar.php y captura.php).
 * - Autenticacion robusta: API_KEY por cabeceras (X-API-Key, Authorization:
 * Bearer), POST body y query param.
 * - Servidor local en puerto 80 con interfaz web de respaldo en PROGMEM.
 * - Compatible con librerias IRremoteESP8266 y ArduinoJson (v6 y v7).
 * =============================================================================
 */

#include "config.h"
#include <Arduino.h>
#include <ArduinoJson.h>
#include <HTTPClient.h>
#include <IRrecv.h>
#include <IRremoteESP8266.h>
#include <IRsend.h>
#include <IRutils.h>
#include <WebServer.h>
#include <WiFi.h>
#include <WiFiClientSecure.h>

// -----------------------------------------------------------------------------
// PAGINA HTML DE RESPALDO LOCAL EN "/" (PROGMEM)
// Incluye control de Luces, Aire acondicionado y Proyector sin emojis Unicode
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
      <span>Luces (Rele GPIO 26)</span>
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
      <span id="st-aire" style="color:var(--gold);font-weight:700;font-size:11px;">STAR AC (COOLIX)</span>
    </div>
    <div class="grid">
      <button type="button" onclick="aire('prender')">Aire Prender</button>
      <button type="button" class="btn-off" onclick="aire('apagar')">Aire Apagar</button>
    </div>
  </div>

  <div class="card">
    <div class="status-bar">
      <span>Proyector (IR GPIO 18)</span>
      <span id="st-proyector" style="color:var(--gold);font-weight:700;font-size:11px;">EPSON (NEC)</span>
    </div>
    <div class="grid">
      <button type="button" onclick="proyector('prender')">Proyector Prender</button>
      <button type="button" class="btn-off" onclick="proyector('apagar')">Proyector Apagar</button>
    </div>
  </div>

  <div class="card">
    <div class="tele-row"><span>IP Local:</span><span id="tel-ip" class="tele-val">--</span></div>
    <div class="tele-row"><span>Senial WiFi:</span><span id="tel-rssi" class="tele-val">--</span></div>
    <div class="tele-row"><span>Uptime:</span><span id="tel-up" class="tele-val">--</span></div>
    <div class="tele-row"><span>Servidor VPS:</span><span id="tel-vps" class="tele-val">--</span></div>
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
    document.getElementById('tel-vps').textContent=d.servidor_vps_ok?'CONECTADO':'SIN CONEXION';
  }).catch(console.error);
}
actualizar();
setInterval(actualizar,3000);
</script>
</body>
</html>)rawliteral";

// -----------------------------------------------------------------------------
// INSTANCIAS DE DISPOSITIVOS Y SERVICIOS
// -----------------------------------------------------------------------------
WebServer servidorLocal(PUERTO_LOCAL_HTTP);
IRsend emisorIR(PIN_IR_SEND);
IRrecv receptorIR(PIN_IR_RECV, 1024, 15, true);
decode_results resultadosIR;

// Clientes de red (HTTPS con TLS y HTTP directo)
WiFiClient clienteHTTP;
WiFiClientSecure clienteSSL;
HTTPClient http;

// -----------------------------------------------------------------------------
// VARIABLES DE ESTADO Y TIEMPOS
// -----------------------------------------------------------------------------
int estadoLucesReal = 0;           // 0 = Apagadas, 1 = Encendidas
bool cambioLocalLuces = false;     // Bandera de cambio local prioritario
int ultimoIrEjecutadoId = 0;       // ID de comando IR para confirmacion
bool ultimoSyncOk = false;         // Bandera de comunicacion con servidor VPS
unsigned long ultimoPollingMs = 0; // Temporizador para polling
unsigned long intervaloPolling = INTERVALO_POLLING_MS;
unsigned long ultimoLedToggleMs = 0; // Temporizador para parpadeo de LED
bool estadoLed = false;
unsigned long ultimaReconexionMs = 0; // Temporizador para reconexion WiFi
bool intentandoRedSecundaria = false; // Alternancia entre WIFI_SSID y WIFI_SSID_2

// -----------------------------------------------------------------------------
// DECLARACION PREVIA DE FUNCIONES
// -----------------------------------------------------------------------------
void configurarServidorLocal();
void vigilarWiFi();
void actualizarLedEstado();
void emitirRafagaDiagnostico();
void transmitirComandoIR(const String &protoStr, const String &codeHex,
                         uint16_t bits, const String &stateHex,
                         const String &rawData, uint16_t repeticiones,
                         uint16_t pausaMs, uint16_t khz,
                         const String &dispositivo = "",
                         const String &accion = "",
                         const String &acStateJson = "");
void ejecutarEnvioRaw(const String &rawStr, uint16_t khz);
void ejecutarEnvioEstado(decode_type_t tipo, const String &estadoHex);
void ejecutarEnvioCorto(decode_type_t tipo, uint64_t codigoNum, uint16_t bits);
uint64_t convertirHexAUint64(const String &hexStr);
void verificarCapturaIR();
void verificarComandosSerial();
void probarLoopbackIR();
void sincronizarYReportar();
void enviarReporteEstado(bool cambioLocal, int irEjecutadoId = 0);
String formatearRutaUrl(const String &base, const String &ruta);

// -----------------------------------------------------------------------------
// INICIALIZACION (SETUP)
// -----------------------------------------------------------------------------
void setup() {
  Serial.begin(115200);
  delay(200);
  Serial.println();
  Serial.println(F("=================================================="));
  Serial.println(F("SISTEMA CONTROL DE AULA - FIRMWARE ESP32"));
  Serial.print(F("Version: "));
  Serial.println(F(FIRMWARE_VERSION));
  Serial.print(F("Aula ID: "));
  Serial.println(AULA_ID);
  Serial.print(F("Servidor: "));
  Serial.println(SERVER_BASE_URL);
  Serial.println(F("=================================================="));

  // 1. Configuracion de pines de hardware
  pinMode(PIN_RELE_LUCES, OUTPUT);
  digitalWrite(PIN_RELE_LUCES, LOW); // Iniciar siempre apagado por seguridad
  estadoLucesReal = 0;
  cambioLocalLuces = false;

  pinMode(PIN_LED_ESTADO, OUTPUT);
  digitalWrite(PIN_LED_ESTADO, LOW);

  // 2. Inicializacion de perifericos infrarrojos
  emisorIR.begin();
  receptorIR.setUnknownThreshold(30); // Ignorar ruido corto (<30 pulsos)
  receptorIR.enableIRIn();

// 3. Configuracion de clientes de red
#if VERIFICAR_CERTIFICADO_SSL
  // Para verificacion estricta se debe configurar el certificado CA
#else
  clienteSSL.setInsecure();
#endif
  clienteSSL.setTimeout(HTTP_TIMEOUT_MS / 1000);
  clienteHTTP.setTimeout(HTTP_TIMEOUT_MS / 1000);
  http.setReuse(false); // Evitar colision de sockets (Error HTTP -5)
  http.setTimeout(HTTP_TIMEOUT_MS);

  // 4. Conexion a red WiFi (intenta principal y luego respaldo)
  WiFi.mode(WIFI_STA);
  Serial.print(F("[WIFI] Conectando a red principal: "));
  Serial.println(WIFI_SSID);
  WiFi.begin(WIFI_SSID, WIFI_PASS);

  unsigned long inicioWiFi = millis();
  while (WiFi.status() != WL_CONNECTED && (millis() - inicioWiFi < 6000)) {
    digitalWrite(PIN_LED_ESTADO, !digitalRead(PIN_LED_ESTADO));
    delay(200);
    Serial.print(F("."));
  }
  Serial.println();

#ifdef WIFI_SSID_2
  if (WiFi.status() != WL_CONNECTED && strlen(WIFI_SSID_2) > 0) {
    Serial.print(F("[WIFI] Intentando red secundaria: "));
    Serial.println(WIFI_SSID_2);
    WiFi.disconnect();
    WiFi.begin(WIFI_SSID_2, WIFI_PASS_2);
    intentandoRedSecundaria = true;
    inicioWiFi = millis();
    while (WiFi.status() != WL_CONNECTED && (millis() - inicioWiFi < 6000)) {
      digitalWrite(PIN_LED_ESTADO, !digitalRead(PIN_LED_ESTADO));
      delay(200);
      Serial.print(F("."));
    }
    Serial.println();
  }
#endif

  if (WiFi.status() == WL_CONNECTED) {
    Serial.print(F("[WIFI] Conectado exitosamente a "));
    Serial.print(WiFi.SSID());
    Serial.print(F(" | IP Local: "));
    Serial.println(WiFi.localIP());
    Serial.print(F("[WIFI] Senial RSSI: "));
    Serial.print(WiFi.RSSI());
    Serial.println(F(" dBm"));
  } else {
    Serial.println(F("[WIFI] No se pudo conectar de inmediato. Se continuara "
                     "en segundo plano."));
  }

  // 5. Inicializacion del servidor HTTP local de respaldo
  configurarServidorLocal();
  servidorLocal.begin();
  Serial.println(F("[HTTP LOCAL] Servidor de respaldo activo en puerto 80"));
  Serial.println(
      F("[SISTEMA] Inicializacion finalizada. Bucle principal activo."));
}

// -----------------------------------------------------------------------------
// BUCLE PRINCIPAL (LOOP)
// -----------------------------------------------------------------------------
void loop() {
  // 1. Atender solicitudes del servidor HTTP local
  servidorLocal.handleClient();

  // 2. Supervisar conexion WiFi de forma no bloqueante
  vigilarWiFi();

  // 3. Leer codigos infrarrojos entrantes del KY-022
  verificarCapturaIR();

  // 4. Atender comandos de diagnostico por Monitor Serie
  verificarComandosSerial();

  // 5. Actualizar el estado del LED integrado segun conectividad
  actualizarLedEstado();

  // 6. Polling periodico al servidor VPS si hay WiFi
  if (WiFi.status() == WL_CONNECTED) {
    unsigned long ahora = millis();
    if (ahora - ultimoPollingMs >= intervaloPolling) {
      ultimoPollingMs = ahora;
      sincronizarYReportar();
    }
  }
}

// -----------------------------------------------------------------------------
// GESTION DE WIFI NO BLOQUEANTE
// -----------------------------------------------------------------------------
void vigilarWiFi() {
  if (WiFi.status() != WL_CONNECTED) {
    ultimoSyncOk = false;
    unsigned long ahora = millis();
    if (ahora - ultimaReconexionMs >= 10000) {
      ultimaReconexionMs = ahora;
      WiFi.disconnect();
#ifdef WIFI_SSID_2
      if (strlen(WIFI_SSID_2) > 0) {
        intentandoRedSecundaria = !intentandoRedSecundaria;
        const char *ssid = intentandoRedSecundaria ? WIFI_SSID_2 : WIFI_SSID;
        const char *pass = intentandoRedSecundaria ? WIFI_PASS_2 : WIFI_PASS;
        Serial.print(F("[WIFI] Desconectado. Reintentando con red: "));
        Serial.println(ssid);
        WiFi.begin(ssid, pass);
        return;
      }
#endif
      Serial.println(F("[WIFI] Desconectado. Reintentando conexion..."));
      WiFi.begin(WIFI_SSID, WIFI_PASS);
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
// REPORTE EXPLICITO DE TELEMETRIA Y ESTADO A /api/esp32/reportar.php
// -----------------------------------------------------------------------------
void enviarReporteEstado(bool cambioLocal, int irEjecutadoId) {
  if (WiFi.status() != WL_CONNECTED)
    return;

  String urlRep = String(URL_REPORTAR);
  if (urlRep.indexOf('?') == -1) {
    urlRep += "?api_key=" + String(API_KEY) + "&aula_id=" + String(AULA_ID);
  } else {
    urlRep += "&api_key=" + String(API_KEY) + "&aula_id=" + String(AULA_ID);
  }

  HTTPClient httpRep;
  WiFiClient clienteRepHTTP;
  bool conOk = false;
  if (urlRep.startsWith("https://")) {
    conOk = httpRep.begin(clienteSSL, urlRep);
  } else {
    conOk = httpRep.begin(clienteRepHTTP, urlRep);
  }

  if (!conOk) {
    Serial.println(F("[HTTP REP] Error al conectar a URL_REPORTAR"));
    return;
  }

  httpRep.setTimeout(HTTP_TIMEOUT_MS);
  httpRep.addHeader("Content-Type", "application/x-www-form-urlencoded");
  httpRep.addHeader("X-API-Key", API_KEY);
  httpRep.addHeader("Authorization", "Bearer " + String(API_KEY));

  String cuerpo =
      "api_key=" + String(API_KEY) + "&aula_id=" + String(AULA_ID) +
      "&luces=" + String(estadoLucesReal) + "&ip=" + WiFi.localIP().toString() +
      "&rssi=" + String(WiFi.RSSI()) + "&uptime=" + String(millis() / 1000) +
      "&firmware=" + String(FIRMWARE_VERSION) +
      "&ir_ejecutado=" + String(irEjecutadoId) +
      "&cambio_local=" + String(cambioLocal ? 1 : 0);

  int codigo = httpRep.POST(cuerpo);
  if (codigo == 200) {
    Serial.println(F("[HTTP REP] Telemetria reportada exitosamente"));
  } else {
    Serial.print(F("[HTTP REP] Codigo de respuesta: "));
    Serial.println(codigo);
  }
  httpRep.end();
}

// -----------------------------------------------------------------------------
// POLLING UNIFICADO (POST A /api/esp32/sincronizar.php): TELEMETRIA + COMANDOS
// -----------------------------------------------------------------------------
void sincronizarYReportar() {
  String urlSync = String(URL_SINCRONIZAR);
  if (urlSync.indexOf('?') == -1) {
    urlSync += "?api_key=" + String(API_KEY) + "&aula_id=" + String(AULA_ID);
  } else {
    urlSync += "&api_key=" + String(API_KEY) + "&aula_id=" + String(AULA_ID);
  }

  bool conexionOk = false;
  if (urlSync.startsWith("https://")) {
    conexionOk = http.begin(clienteSSL, urlSync);
  } else {
    conexionOk = http.begin(clienteHTTP, urlSync);
  }

  if (!conexionOk) {
    Serial.println(F("[HTTP SYNC] Error al inicializar conexion HTTP/HTTPS"));
    ultimoSyncOk = false;
    intervaloPolling = INTERVALO_BACKOFF_MS;
    return;
  }

  http.addHeader("Content-Type", "application/x-www-form-urlencoded");
  http.addHeader("X-API-Key", API_KEY);
  http.addHeader("Authorization", "Bearer " + String(API_KEY));

  // Telemetria y confirmaciones enviadas en el body
  String cuerpo =
      "api_key=" + String(API_KEY) + "&aula_id=" + String(AULA_ID) +
      "&luces=" + String(estadoLucesReal) + "&ip=" + WiFi.localIP().toString() +
      "&rssi=" + String(WiFi.RSSI()) + "&uptime=" + String(millis() / 1000) +
      "&firmware=" + String(FIRMWARE_VERSION) +
      "&ir_ejecutado=" + String(ultimoIrEjecutadoId) +
      "&cambio_local=" + String(cambioLocalLuces ? 1 : 0);

  int httpCodigo = http.POST(cuerpo);

  int lucesDeseadas = -1;
  int comandoIrId = 0;
  String irDisp = "aire";
  String irAccion = "";
  String irProto = "NEC";
  String irCode = "";
  int irBits = 32;
  String irState = "";
  String irRaw = "";
  int irRep = 1;
  int irPausa = 100;
  int irKhz = 38;
  String irAcState = "";

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
      Serial.println(
          F("[RELE] Cambio local de luces sincronizado con servidor."));
    }

    String respuesta = http.getString();
    respuesta.trim();

    // 1. Deteccion y parseo si la respuesta es JSON (usando ArduinoJson)
    bool parseadoJson = false;
    if (respuesta.startsWith("{") && respuesta.endsWith("}")) {
#if ARDUINOJSON_VERSION_MAJOR >= 7
      JsonDocument doc;
#else
      DynamicJsonDocument doc(2048);
#endif
      DeserializationError jsonErr = deserializeJson(doc, respuesta);
      if (!jsonErr) {
        parseadoJson = true;
        if (doc.containsKey("luces")) {
          lucesDeseadas = doc["luces"].as<int>();
        }
        if (doc.containsKey("ir_id")) {
          comandoIrId = doc["ir_id"].as<int>();
        }
        if (doc.containsKey("comando_ir") && !doc["comando_ir"].isNull()) {
          JsonObject cmd = doc["comando_ir"];
          if (cmd.containsKey("id"))
            comandoIrId = cmd["id"].as<int>();
          if (cmd.containsKey("dispositivo"))
            irDisp = cmd["dispositivo"].as<String>();
          if (cmd.containsKey("accion"))
            irAccion = cmd["accion"].as<String>();
          if (cmd.containsKey("protocolo"))
            irProto = cmd["protocolo"].as<String>();
          if (cmd.containsKey("codigo"))
            irCode = cmd["codigo"].as<String>();
          if (cmd.containsKey("bits"))
            irBits = cmd["bits"].as<int>();
          if (cmd.containsKey("estado_hex") && !cmd["estado_hex"].isNull())
            irState = cmd["estado_hex"].as<String>();
          if (cmd.containsKey("raw") && !cmd["raw"].isNull())
            irRaw = cmd["raw"].as<String>();
          if (cmd.containsKey("repeticiones"))
            irRep = cmd["repeticiones"].as<int>();
          if (cmd.containsKey("pausa_ms"))
            irPausa = cmd["pausa_ms"].as<int>();
          if (cmd.containsKey("frecuencia_khz"))
            irKhz = cmd["frecuencia_khz"].as<int>();
          if (cmd.containsKey("ac_state") && !cmd["ac_state"].isNull())
            irAcState = cmd["ac_state"].as<String>();
        }
      }
    }

    // 2. Si no es JSON o fallo, parsear formato texto plano clave=valor
    if (!parseadoJson) {
      int inicio = 0;
      while (inicio < (int)respuesta.length()) {
        int fin = respuesta.indexOf('\n', inicio);
        if (fin == -1)
          fin = respuesta.length();
        String linea = respuesta.substring(inicio, fin);
        linea.trim();
        inicio = fin + 1;

        if (linea.length() == 0)
          continue;
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
          } else if (clave.equalsIgnoreCase("ir_disp")) {
            irDisp = valor;
          } else if (clave.equalsIgnoreCase("ir_accion")) {
            irAccion = valor;
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
          } else if (clave.equalsIgnoreCase("ir_ac_state")) {
            irAcState = valor;
          }
        }
      }
    }

    // Aplicar estado de luces deseado por el servidor si no hubo cambio local
    // pendiente
    if (!cambioLocalLuces && (lucesDeseadas == 0 || lucesDeseadas == 1)) {
      if (estadoLucesReal != lucesDeseadas) {
        estadoLucesReal = lucesDeseadas;
        digitalWrite(PIN_RELE_LUCES, estadoLucesReal == 1 ? HIGH : LOW);
        Serial.print(F("[RELE] Luces actualizadas segun servidor: "));
        Serial.println(estadoLucesReal == 1 ? F("ENCENDIDAS") : F("APAGADAS"));
      }
    }

    // Ejecutar comando infrarrojo si vino uno encolado
    if (comandoIrId > 0) {
      Serial.print(F("[IR] Ejecutando comando ID #"));
      Serial.print(comandoIrId);
      Serial.print(F(" (Disp: "));
      Serial.print(irDisp);
      Serial.print(F(", Accion: "));
      Serial.print(irAccion);
      Serial.print(F(", Proto: "));
      Serial.print(irProto);
      Serial.print(F(", Code: "));
      Serial.print(irCode);
      Serial.print(F(", Reps: "));
      Serial.print(irRep);
      Serial.print(F(", Pausa: "));
      Serial.print(irPausa);
      Serial.println(F(")"));

      if (irAcState.length() > 0) {
        Serial.print(F("[IR AC STATE] "));
        Serial.println(irAcState);
      }

      transmitirComandoIR(irProto, irCode, irBits, irState, irRaw, irRep,
                          irPausa, irKhz, irDisp, irAccion, irAcState);
      ultimoIrEjecutadoId =
          comandoIrId; // Se confirmara en el siguiente ciclo de polling
    }
    http.end();
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
    uint16_t rawLen = getCorrectedRawLength(&resultadosIR);

    // Ignorar rebotes o fragmentos de ruido cortos (ej. 6 bits / 12 muestras)
    if (resultadosIR.decode_type == UNKNOWN && rawLen < 30) {
      receptorIR.resume();
      return;
    }

    String protocolo = typeToString(resultadosIR.decode_type);
    String valorHex = resultToHexidecimal(&resultadosIR);
    int bits = resultadosIR.bits;
    String estadoHex = "";
    String rawStr = "";

    // Captura de tramas largas de estado (aires acondicionados)
    if (hasACState(resultadosIR.decode_type)) {
      uint16_t nBytes = resultadosIR.bits / 8;
      if (nBytes == 0)
        nBytes = kStateSizeMax;
      for (uint16_t i = 0; i < nBytes; i++) {
        if (resultadosIR.state[i] < 0x10)
          estadoHex += "0";
        estadoHex += String(resultadosIR.state[i], HEX);
      }
      estadoHex.toUpperCase();
    }

    // Si es el preambulo compuesto de Epson (0x81C00FF0) y solo vino la primera trama (67 muestras),
    // ignorarlo y seguir escuchando de inmediato para atrapar el codigo real del boton (0xC1AAxxxx)
    if (valorHex.equalsIgnoreCase("0x81C00FF0") && rawLen < 130) {
      receptorIR.resume();
      return;
    }

    // Captura de secuencia RAW en microsegundos, filtro de ruido y extraccion de 2da trama Epson
    uint16_t pulsosUltraCortos = 0;
    if (rawLen > 0) {
      uint16_t *rawArray = resultToRawArray(&resultadosIR);
      if (rawArray != NULL) {
        // Si vino 0x81C00FF0 pegado con la trama real del boton (>=130 muestras), extraer la 2da trama NEC
        if (valorHex.equalsIgnoreCase("0x81C00FF0") && rawLen >= 130) {
          for (uint16_t i = 60; i + 65 < rawLen; i++) {
            if (rawArray[i] > 7000 && rawArray[i] < 11000 &&
                rawArray[i + 1] > 3500 && rawArray[i + 1] < 5500) {
              uint32_t code2 = 0;
              bool ok2 = true;
              for (uint8_t b = 0; b < 32; b++) {
                uint16_t m = rawArray[i + 2 + b * 2];
                uint16_t s = rawArray[i + 3 + b * 2];
                if (m < 300 || m > 900) {
                  ok2 = false;
                  break;
                }
                code2 = (code2 << 1) | ((s > 1000) ? 1UL : 0UL);
              }
              if (ok2 && code2 != 0x81C00FF0UL && code2 != 0xFFFFFFFFUL) {
                valorHex = "0x" + String(code2, HEX);
                valorHex.toUpperCase();
                protocolo = "NEC";
                bits = 32;
                break;
              }
            }
          }
        }

        rawStr.reserve(rawLen * 6);
        for (uint16_t i = 0; i < rawLen; i++) {
          if (rawArray[i] < 80)
            pulsosUltraCortos++;
          if (i > 0)
            rawStr += ",";
          rawStr += String(rawArray[i]);
        }
        delete[] rawArray; // Liberar memoria dinamica inmediatamente
      }
    }

    // Si aun sigue siendo solo el preambulo repetido 0x81C00FF0, ignorarlo
    if (valorHex.equalsIgnoreCase("0x81C00FF0")) {
      receptorIR.resume();
      return;
    }

    // Si es UNKNOWN y mas del 25% de los pulsos son menores a 80us, es ruido electrico (ej. falso contacto en GND/VCC)
    if (resultadosIR.decode_type == UNKNOWN && pulsosUltraCortos * 4 > rawLen) {
      receptorIR.resume();
      return;
    }

    // Rehabilitar de inmediato el receptor y pausar el polling por 8s sin desbordamiento unsigned
    receptorIR.resume();
    ultimoPollingMs = millis();
    intervaloPolling = 8000;

    Serial.println();
    Serial.println(F(">>> [CAPTURA IR KY-022 RECIBIDA] <<<"));
    Serial.print(F("Protocolo: "));
    Serial.println(protocolo);
    Serial.print(F("Valor HEX: "));
    Serial.println(valorHex);
    Serial.print(F("Bits: "));
    Serial.println(bits);
    if (estadoHex.length() > 0) {
      Serial.print(F("Estado HEX: "));
      Serial.println(estadoHex);
    }
    if (rawStr.length() > 0) {
      Serial.print(F("Muestras RAW: "));
      Serial.println(rawLen);
    }

    // Enviar captura al servidor VPS si hay WiFi disponible (usando socket propio)
    if (WiFi.status() == WL_CONNECTED) {
      String urlCap = String(URL_CAPTURA);
      if (urlCap.indexOf('?') == -1) {
        urlCap += "?api_key=" + String(API_KEY) + "&aula_id=" + String(AULA_ID);
      } else {
        urlCap += "&api_key=" + String(API_KEY) + "&aula_id=" + String(AULA_ID);
      }

      HTTPClient httpCap;
      WiFiClient clienteCapHTTP;
      bool capOk = false;
      if (urlCap.startsWith("https://")) {
        capOk = httpCap.begin(clienteSSL, urlCap);
      } else {
        capOk = httpCap.begin(clienteCapHTTP, urlCap);
      }

      if (capOk) {
        httpCap.setTimeout(3000);
        httpCap.addHeader("Content-Type", "application/x-www-form-urlencoded");
        httpCap.addHeader("X-API-Key", API_KEY);
        httpCap.addHeader("Authorization", "Bearer " + String(API_KEY));

        String postBody = "api_key=" + String(API_KEY) +
                          "&aula_id=" + String(AULA_ID) +
                          "&protocolo=" + protocolo + "&valor=" + valorHex +
                          "&bits=" + String(bits) + "&estado_hex=" + estadoHex +
                          "&raw=" + rawStr;

        int cod = httpCap.POST(postBody);
        if (cod == 200) {
          Serial.println(F("[CAPTURA IR] Enviada y registrada exitosamente en "
                           "el servidor."));
        } else {
          Serial.print(F("[CAPTURA IR] Error en envio: "));
          Serial.println(cod);
        }
        httpCap.end();
      }
    }

    // Asegurar que el receptor quede limpio y listo para el proximo boton
    receptorIR.resume();
    ultimoPollingMs = millis();
    intervaloPolling = 8000;
    Serial.println(F("[LISTO] Puedes presionar el siguiente boton..."));
  }
}

// -----------------------------------------------------------------------------
// TRANSMISION INFRARROJA MULTIPROTOCOLO (RAW, ESTADO Y CORTO)
// -----------------------------------------------------------------------------
void ejecutarEnvioRaw(const String &rawStr, uint16_t khz) {
  if (rawStr.length() == 0)
    return;

  // Contar comas para dimensionar arreglo dinamico
  uint16_t count = 1;
  for (unsigned int i = 0; i < rawStr.length(); i++) {
    if (rawStr.charAt(i) == ',')
      count++;
  }

  uint16_t *rawBuf = new uint16_t[count];
  if (!rawBuf)
    return;

  uint16_t idx = 0;
  int start = 0;
  while (start < (int)rawStr.length() && idx < count) {
    int comma = rawStr.indexOf(',', start);
    if (comma == -1)
      comma = rawStr.length();
    String valStr = rawStr.substring(start, comma);
    valStr.trim();
    rawBuf[idx++] = (uint16_t)valStr.toInt();
    start = comma + 1;
  }

  uint16_t hz = (khz > 0 ? khz : 38) * 1000;
  emisorIR.sendRaw(rawBuf, idx, hz);

  delete[] rawBuf; // Liberar memoria dinamica inmediatamente
}

void ejecutarEnvioEstado(decode_type_t tipo, const String &estadoHex) {
  String cleanHex = estadoHex;
  cleanHex.replace(" ", "");
  cleanHex.replace("0x", "");
  cleanHex.replace("0X", "");
  cleanHex.replace(",", "");

  uint16_t numBytes = cleanHex.length() / 2;
  if (numBytes == 0)
    return;

  uint8_t *stateBuf = new uint8_t[numBytes];
  if (!stateBuf)
    return;

  for (uint16_t i = 0; i < numBytes; i++) {
    String byteStr = cleanHex.substring(i * 2, (i * 2) + 2);
    stateBuf[i] = (uint8_t)strtoul(byteStr.c_str(), NULL, 16);
  }

  emisorIR.send(tipo, stateBuf, numBytes);

  delete[] stateBuf; // Liberar memoria dinamica inmediatamente
}

void ejecutarEnvioCorto(decode_type_t tipo, uint64_t codigoNum, uint16_t bits) {
  uint16_t nbits = (bits > 0) ? bits : 32;
  if (tipo == COOLIX) {
    // BGH envia 2 a 3 tramas seguidas (199 a 299 muestras RAW)
    emisorIR.sendCOOLIX(codigoNum, (bits > 0) ? bits : 24, 2);
  } else if (tipo == COOLIX48) {
    emisorIR.sendCoolix48(codigoNum, (bits > 0) ? bits : 48, 1);
  } else if (tipo == GREE) {
    emisorIR.sendGree(codigoNum, (bits > 0) ? bits : 64);
  } else if (tipo == NEC) {
    // Si es un codigo Epson (0xC1AAxxxx), enviar preambulo 0x81C00FF0 + codigo real
    if ((codigoNum & 0xFFFF0000ULL) == 0xC1AA0000ULL) {
      emisorIR.sendNEC(CODIGO_PROYECTOR_PREAMBULO_DEF, 32);
      delay(28);
    }
    emisorIR.sendNEC(codigoNum, nbits);
  } else if (tipo != UNKNOWN && emisorIR.send(tipo, codigoNum, nbits)) {
    // Envio reconocido y emitido
  } else {
    // Fallback predeterminado a protocolo NEC
    emisorIR.sendNEC(codigoNum, nbits);
  }
}

void transmitirComandoIR(const String &protoStr, const String &codeHex,
                         uint16_t bits, const String &stateHex,
                         const String &rawData, uint16_t repeticiones,
                         uint16_t pausaMs, uint16_t khz,
                         const String &dispositivo, const String &accion,
                         const String &acStateJson) {
  decode_type_t tipo = strToDecodeType(protoStr.c_str());
  if (protoStr.equalsIgnoreCase("COOLIX48")) {
    tipo = COOLIX48;
    if (bits <= 0)
      bits = 48;
  } else if (protoStr.equalsIgnoreCase("COOLIX")) {
    tipo = COOLIX;
    if (bits <= 0)
      bits = 24;
  } else if (protoStr.equalsIgnoreCase("GREE")) {
    tipo = GREE;
    if (bits <= 0)
      bits = 64;
  } else if (protoStr.equalsIgnoreCase("EPSON_NEC") ||
             protoStr.equalsIgnoreCase("EPSON") ||
             protoStr.equalsIgnoreCase("NEC")) {
    tipo = NEC;
    if (bits <= 0)
      bits = 32;
  }

  uint16_t reps = (repeticiones > 0) ? repeticiones : 1;
  uint16_t pausa = (pausaMs > 0) ? pausaMs : 100;
  uint64_t codigoNum = convertirHexAUint64(codeHex);

  // Si es aire: solo Encender (0xB23F40) y Apagar (0xB27BE0) como el control de la escuela
  if (dispositivo.equalsIgnoreCase("aire")) {
    tipo = COOLIX;
    bits = 24;
    if (accion.equalsIgnoreCase("apagar")) {
      codigoNum = CODIGO_STAR_AIRE_OFF_DEF; // 0xB27BE0 (Apagar)
      Serial.println(F("[IR AIRE BGH] Comando: APAGAR (0xB27BE0)"));
    } else {
      codigoNum = CODIGO_STAR_AIRE_ON_DEF;  // 0xB23F40 (Encender)
      Serial.println(F("[IR AIRE BGH] Comando: ENCENDER (0xB23F40)"));
    }
  }

  // Si es proyector Epson, usar siempre los codigos Epson reales (0xC1AA...)
  uint64_t codigoExtraProyector = 0;
  if (dispositivo.equalsIgnoreCase("proyector")) {
    tipo = NEC;
    bits = 32;
    if (accion.equalsIgnoreCase("prender") || accion.equalsIgnoreCase("encender")) {
      codigoNum = CODIGO_PROYECTOR_PRENDER_DEF;
      reps = 1;
    } else if (accion.equalsIgnoreCase("apagar")) {
      codigoNum = CODIGO_PROYECTOR_APAGAR_DEF;
      reps = REPETICIONES_PROYECTOR_APAGAR;
      pausa = PAUSA_PROYECTOR_APAGAR_MS;
    } else if (accion.equalsIgnoreCase("hdmi1")) {
      codigoNum = CODIGO_PROYECTOR_HDMI1_DEF;
    } else if (accion.equalsIgnoreCase("hdmi2")) {
      codigoNum = CODIGO_PROYECTOR_HDMI2_DEF;
    } else if (accion.equalsIgnoreCase("vga")) {
      codigoNum = CODIGO_PROYECTOR_VGA_DEF;
    } else if (accion.equalsIgnoreCase("freeze")) {
      codigoNum = CODIGO_PROYECTOR_FREEZE_DEF;
    } else if (accion.equalsIgnoreCase("av_mute") || accion.equalsIgnoreCase("vol_mute") || accion.equalsIgnoreCase("mute")) {
      codigoNum = CODIGO_PROYECTOR_MUTE_DEF;
    } else if (accion.equalsIgnoreCase("vol_mas") || accion.equalsIgnoreCase("subir")) {
      codigoNum = CODIGO_PROYECTOR_VOL_MAS_DEF;       // 0xC1AA19E6 (Epson Vol+)
      codigoExtraProyector = CODIGO_PROYECTOR_VOL_MAS_ALT; // 0xC1AA4AB5 (Respaldo)
    } else if (accion.equalsIgnoreCase("vol_menos") || accion.equalsIgnoreCase("bajar")) {
      codigoNum = CODIGO_PROYECTOR_VOL_MENOS_DEF;       // 0xC1AA9966 (Epson Vol-)
      codigoExtraProyector = CODIGO_PROYECTOR_VOL_MENOS_ALT; // 0xC1AA4EB1 (Respaldo)
    }
  }

  // Verificacion especial para apagado de proyector Epson (requiere doble pulso
  // con 2s de pausa) Nota: no se compara por codigo porque Epson usa el mismo
  // codigo para prender y apagar
  bool esApagadoEpson = (tipo == NEC) &&
                        dispositivo.equalsIgnoreCase("proyector") &&
                        accion.equalsIgnoreCase("apagar");

  if (esApagadoEpson && reps < 2) {
    reps = REPETICIONES_PROYECTOR_APAGAR;
    pausa = PAUSA_PROYECTOR_APAGAR_MS;
    Serial.println(F("[IR EPSON] Ajustado automaticamente a doble pulso con "
                     "pausa de 2000 ms"));
  }

  Serial.print(F("[IR EMITIENDO] Codigo HEX final: 0x"));
  Serial.println((unsigned long)codigoNum, HEX);

  receptorIR.disableIRIn();
  delay(15);

  for (uint16_t r = 0; r < reps; r++) {
    if (r > 0) {
      Serial.print(F("[IR] Pausa entre repeticiones: "));
      Serial.print(pausa);
      Serial.println(F(" ms"));
      delay(pausa);
    }

    if (codigoNum > 0 && tipo != UNKNOWN) {
      ejecutarEnvioCorto(tipo, codigoNum, bits);
      if (codigoExtraProyector > 0) {
        delay(60);
        ejecutarEnvioCorto(tipo, codigoExtraProyector, bits);
      }
    } else if (rawData.length() > 0) {
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
 * Diagnostico de emisor: emite una rafaga continua durante 3 segundos
 * para comprobar con la camara del celular si el LED infrarrojo enciende.
 */
void emitirRafagaDiagnostico() {
  Serial.println(
      F("[DIAGNOSTICO IR] Iniciando rafaga de 3 segundos en GPIO 18..."));
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

void probarLoopbackIR() {
  Serial.println(F("\n=================================================="));
  Serial.println(F("[AUTOPRUEBA LOOPBACK IR] Iniciando test directo..."));
  Serial.println(F("Apuntando emisor KY-005 hacia el receptor KY-022..."));

  // Asegurar que el receptor este escuchando
  receptorIR.enableIRIn();
  receptorIR.resume();
  delay(100);

  bool detectado = false;
  String codigoDetectado = "";

  for (int intento = 1; intento <= 3; intento++) {
    Serial.print(F("[TEST] Emitiendo pulso "));
    Serial.print(intento);
    Serial.println(F("/3 desde GPIO 18..."));

    emisorIR.sendNEC(CODIGO_PROYECTOR_PRENDER_DEF, 32);
    delay(120);

    if (receptorIR.decode(&resultadosIR)) {
      detectado = true;
      codigoDetectado = resultToHexidecimal(&resultadosIR);
      receptorIR.resume();
      break;
    }
  }

  Serial.println(F("--------------------------------------------------"));
  if (detectado) {
    Serial.println(F(">>> EXITO TOTAL: EL EMISOR IR FUNCIONA PERFECTAMENTE! <<<"));
    Serial.print(F("El receptor KY-022 capturo el pulso emitido: "));
    Serial.println(codigoDetectado);
    Serial.println(F("El circuito del transistor BC337 y KY-005 esta 100% OPERATIVO."));
  } else {
    Serial.println(F(">>> NO SE DETECTO SENIAL DEL EMISOR <<<"));
    Serial.println(F("El receptor KY-022 no escucho el pulso."));
    Serial.println(F("Revisar conexion: Colector (pata izq) al pin (-) del KY-005"));
    Serial.println(F("y Base (pata medio con 330 ohm) a GPIO 18."));
  }
  Serial.println(F("==================================================\n"));
}

void verificarComandosSerial() {
  if (Serial.available()) {
    String cmd = Serial.readStringUntil('\n');
    cmd.trim();
    if (cmd.equalsIgnoreCase("test") || cmd.equalsIgnoreCase("probar") || cmd.equalsIgnoreCase("loopback")) {
      probarLoopbackIR();
    } else if (cmd.equalsIgnoreCase("rafaga") || cmd.equalsIgnoreCase("test_ir")) {
      emitirRafagaDiagnostico();
    }
  }
}

// -----------------------------------------------------------------------------
// SERVIDOR WEB LOCAL DE RESPALDO (PUERTO 80)
// -----------------------------------------------------------------------------
void responderJSONLocal(int codigo, const String &json) {
  servidorLocal.sendHeader("Access-Control-Allow-Origin", "*");
  servidorLocal.sendHeader("Access-Control-Allow-Methods",
                           "GET, POST, OPTIONS");
  servidorLocal.sendHeader("Access-Control-Allow-Headers", "*");
  servidorLocal.send(codigo, "application/json", json);
}

void configurarServidorLocal() {
  // Ruta principal: pagina de respaldo autocontenida en PROGMEM
  servidorLocal.on("/", HTTP_GET, []() {
    servidorLocal.send_P(200, "text/html", PAGINA_LOCAL_HTML);
  });

  // Ruta /estado: JSON con CORS
  servidorLocal.on("/estado", HTTP_GET, []() {
    String json =
        "{\"ok\":true,\"luces\":" + String(estadoLucesReal) + ",\"ip\":\"" +
        WiFi.localIP().toString() + "\"" + ",\"rssi\":" + String(WiFi.RSSI()) +
        ",\"uptime\":" + String(millis() / 1000) +
        ",\"servidor_vps_ok\":" + String(ultimoSyncOk ? "true" : "false") +
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
        enviarReporteEstado(true, 0); // Notificar de inmediato al VPS
        responderJSONLocal(200, "{\"ok\":true,\"mensaje\":\"Luces actualizadas "
                                "localmente\",\"luces\":" +
                                    String(estadoLucesReal) + "}");
        return;
      }
    }
    responderJSONLocal(
        400,
        "{\"ok\":false,\"error\":\"Parametro estado invalido (use 1 o 0)\"}");
  });

  // Ruta /aire?accion=prender|apagar
  servidorLocal.on("/aire", HTTP_GET, []() {
    if (servidorLocal.hasArg("accion")) {
      String acc = servidorLocal.arg("accion");
      acc.toLowerCase();
      if (acc == "prender") {
        transmitirComandoIR("COOLIX", String(CODIGO_STAR_AIRE_ON_DEF, HEX), 24,
                            "", "", 1, 100, 38, "aire", "prender");
        Serial.println(F("[HTTP LOCAL] Comando IR Prender Aire Star (Coolix 24 "
                         "C Frio) enviado"));
        responderJSONLocal(200, "{\"ok\":true,\"mensaje\":\"Comando IR Prender "
                                "Aire Star enviado\"}");
        return;
      } else if (acc == "apagar") {
        transmitirComandoIR("COOLIX", String(CODIGO_STAR_AIRE_OFF_DEF, HEX), 24,
                            "", "", 1, 100, 38, "aire", "apagar");
        Serial.println(
            F("[HTTP LOCAL] Comando IR Apagar Aire Star (Coolix) enviado"));
        responderJSONLocal(200, "{\"ok\":true,\"mensaje\":\"Comando IR Apagar "
                                "Aire Star enviado\"}");
        return;
      }
    }
    responderJSONLocal(400, "{\"ok\":false,\"error\":\"Parametro accion "
                            "invalido (prender o apagar)\"}");
  });

  // Ruta /proyector?accion=prender|apagar
  servidorLocal.on("/proyector", HTTP_GET, []() {
    if (servidorLocal.hasArg("accion")) {
      String acc = servidorLocal.arg("accion");
      acc.toLowerCase();
      if (acc == "prender") {
        transmitirComandoIR("NEC", String(CODIGO_PROYECTOR_PRENDER_DEF, HEX),
                            32, "", "", 1, 100, 38, "proyector", "prender");
        Serial.println(
            F("[HTTP LOCAL] Comando IR Prender Proyector Epson enviado"));
        responderJSONLocal(200, "{\"ok\":true,\"mensaje\":\"Comando IR Prender "
                                "Proyector Epson enviado\"}");
        return;
      } else if (acc == "apagar") {
        // Epson requiere 2 pulsos con pausa de 2 segundos (2000 ms) para
        // confirmar
        transmitirComandoIR("NEC", String(CODIGO_PROYECTOR_APAGAR_DEF, HEX), 32,
                            "", "", REPETICIONES_PROYECTOR_APAGAR,
                            PAUSA_PROYECTOR_APAGAR_MS, 38, "proyector",
                            "apagar");
        Serial.println(F("[HTTP LOCAL] Comando IR Apagar Proyector Epson "
                         "enviado (2 pulsos con pausa de 2s)"));
        responderJSONLocal(200, "{\"ok\":true,\"mensaje\":\"Comando IR Apagar "
                                "Proyector Epson enviado (2x confirmacion)\"}");
        return;
      }
    }
    responderJSONLocal(400, "{\"ok\":false,\"error\":\"Parametro accion "
                            "invalido (prender o apagar)\"}");
  });

  // Ruta /test_ir: Diagnostico de rafaga
  servidorLocal.on("/test_ir", HTTP_GET, []() {
    emitirRafagaDiagnostico();
    responderJSONLocal(200, "{\"ok\":true,\"mensaje\":\"Rafaga de diagnostico "
                            "IR completada (3 segundos)\"}");
  });

  // Manejo de pre-flight CORS y errores 404
  servidorLocal.onNotFound([]() {
    if (servidorLocal.method() == HTTP_OPTIONS) {
      responderJSONLocal(200, "{}");
    } else {
      responderJSONLocal(404,
                         "{\"ok\":false,\"error\":\"Ruta no encontrada\"}");
    }
  });
}

// -----------------------------------------------------------------------------
// UTILIDADES
// -----------------------------------------------------------------------------
uint64_t convertirHexAUint64(const String &hexStr) {
  if (hexStr.length() == 0)
    return 0;
  const char *str = hexStr.c_str();
  if (hexStr.startsWith("0x") || hexStr.startsWith("0X")) {
    str += 2;
  }
  return (uint64_t)strtoull(str, NULL, 16);
}

String formatearRutaUrl(const String &base, const String &ruta) {
  if (base.endsWith("/") && ruta.startsWith("/")) {
    return base + ruta.substring(1);
  } else if (!base.endsWith("/") && !ruta.startsWith("/")) {
    return base + "/" + ruta;
  }
  return base + ruta;
}
