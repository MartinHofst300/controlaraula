/**
 * =============================================================================
 * PROYECTO: Control de Aula — MODO 100% LOCALHOST (STANDALONE EN ESP32)
 * ARCHIVO: control_aula.ino
 * Firmware completo todo-en-uno para ESP32 DevKit V1 / NodeMCU-32S
 *
 * Sin servidor externo, sin Docker, sin MySQL y sin dependencia de Internet:
 * - Interfaz web completa (Login, Panel Docente, Panel Admin, Configuracion)
 *   alojada en Flash (PROGMEM comprimido con GZIP, 100% offline sin CDN).
 * - Base de datos persistente local en LittleFS (/db.json): Aulas/Espacios,
 *   Tipos de Espacio, Usuarios/Admins, Horarios Automaticos, Preferencias y Logs.
 * - Red Dual Wi-Fi (AP + STA):
 *     * Crea siempre su propio Wi-Fi: SSID "ControlAula" / Clave "12345678"
 *       -> Entrar a http://192.168.4.1  o  http://controlaula.local
 *     * Tambien se conecta a las redes Wi-Fi configuradas en config.h si estan cerca.
 * - Hardware (pines sin cambios):
 *     * Rele de iluminacion: GPIO 26 (Activo en ALTO / HIGH)
 *     * Emisor Infrarrojo (BC337 + LEDs IR / KY-005): GPIO 18
 *     * Receptor Infrarrojo KY-022: GPIO 15
 *     * LED indicador de estado: GPIO 2
 * =============================================================================
 */

#include "config.h"
#include "storage_db.h"
#include "web_assets.h"

#include <Arduino.h>
#include <ArduinoJson.h>
#include <DNSServer.h>
#include <ESPmDNS.h>
#include <IRrecv.h>
#include <IRremoteESP8266.h>
#include <IRsend.h>
#include <IRutils.h>
#include <WebServer.h>
#include <WiFi.h>

// -----------------------------------------------------------------------------
// INSTANCIAS DE DISPOSITIVOS Y SERVICIOS
// -----------------------------------------------------------------------------
WebServer servidorLocal(PUERTO_LOCAL_HTTP);
DNSServer dnsPortal;
IRsend emisorIR(PIN_IR_SEND);
IRrecv receptorIR(PIN_IR_RECV, 1024, 15, true);
decode_results resultadosIR;

// -----------------------------------------------------------------------------
// REDES WIFI CLIENTE (STA) DE RESPALDO
// -----------------------------------------------------------------------------
struct RedWiFiConfig {
  const char *ssid;
  const char *pass;
};

const RedWiFiConfig REDES_WIFI[] = {
  { WIFI_SSID, WIFI_PASS },
#ifdef WIFI_SSID_2
  { WIFI_SSID_2, WIFI_PASS_2 },
#endif
#ifdef WIFI_SSID_3
  { WIFI_SSID_3, WIFI_PASS_3 },
#endif
#ifdef WIFI_SSID_4
  { WIFI_SSID_4, WIFI_PASS_4 },
#endif
#ifdef WIFI_SSID_5
  { WIFI_SSID_5, WIFI_PASS_5 },
#endif
#ifdef WIFI_SSID_6
  { WIFI_SSID_6, WIFI_PASS_6 },
#endif
};
const int TOTAL_REDES_WIFI = sizeof(REDES_WIFI) / sizeof(REDES_WIFI[0]);

// -----------------------------------------------------------------------------
// VARIABLES DE ESTADO EN TIEMPO REAL
// -----------------------------------------------------------------------------
int estadoLucesReal = 0;
unsigned long ultimoLedToggleMs = 0;
bool estadoLed = false;
unsigned long ultimaReconexionStaMs = 0;
int indiceRedActual = 0;
bool mdnsIniciado = false;
bool ntpIniciado = false;

// Programador no bloqueante para el 2do pulso de apagado del Proyector Epson
bool segundoPulsoEpsonPendiente = false;
unsigned long segundoPulsoEpsonAtMs = 0;

// Verificacion periodica de horarios automaticos
unsigned long ultimoCheckHorariosMs = 0;

// -----------------------------------------------------------------------------
// DECLARACIONES PREVIAS
// -----------------------------------------------------------------------------
void configurarServidorWeb();
void vigilarWiFiSTA();
void actualizarLedEstado();
void verificarCapturaIR();
void verificarComandosSerial();
void verificarSegundoPulsoEpson();
void verificarHorariosAutomaticos();
void emitirRafagaDiagnostico();
void probarLoopbackIR();
void aplicarHardwareLuces(int aulaId, int nuevoEstado);
void emitirIRDispositivo(int aulaId, const String &dispositivo, const String &accion);
int apagarTodoEnAulaLocal(int aulaId, const String &origen, const String &actor);
int apagarServicioGlobalLocal(const String &servicio, const String &origen,
                              const String &actor, const String &motivo = "");

// Callbacks para proteger escrituras en Flash LittleFS de interrupciones IR
void cbAntesEscribirFlash() {
  receptorIR.disableIRIn();
}
void cbDespuesEscribirFlash() {
  receptorIR.enableIRIn();
}

// -----------------------------------------------------------------------------
// HELPERS HTTP, COOKIES, JSON Y AUTENTICACION
// -----------------------------------------------------------------------------
void enviarAssetGz(const uint8_t *data, size_t len, const char *mime, bool cacheable = true) {
  servidorLocal.sendHeader("Content-Encoding", "gzip");
  if (cacheable) {
    servidorLocal.sendHeader("Cache-Control", "public, max-age=86400");
  } else {
    servidorLocal.sendHeader("Cache-Control", "no-store, no-cache, must-revalidate");
  }
  servidorLocal.send_P(200, mime, (const char *)data, len);
}

void enviarAssetRaw(const uint8_t *data, size_t len, const char *mime) {
  servidorLocal.sendHeader("Cache-Control", "public, max-age=86400");
  servidorLocal.send_P(200, mime, (const char *)data, len);
}

void responderJSON(int codigo, const String &json) {
  servidorLocal.sendHeader("Cache-Control", "no-store");
  servidorLocal.sendHeader("Access-Control-Allow-Origin", "*");
  servidorLocal.sendHeader("Access-Control-Allow-Methods", "GET, POST, OPTIONS");
  servidorLocal.sendHeader("Access-Control-Allow-Headers", "Content-Type, X-CSRF-Token, Authorization");
  servidorLocal.send(codigo, "application/json; charset=utf-8", json);
}

void responderErrorJSON(int codigo, const String &msg) {
  CA_JSON_DOC(doc, 256);
  doc["ok"] = false;
  doc["error"] = msg;
  String out;
  serializeJson(doc, out);
  responderJSON(codigo, out);
}

void responderOkMsg(const String &msg) {
  CA_JSON_DOC(doc, 384);
  doc["ok"] = true;
  doc["mensaje"] = msg;
  String out;
  serializeJson(doc, out);
  responderJSON(200, out);
}

void redirigirA(const String &destino) {
  servidorLocal.sendHeader("Location", destino, true);
  servidorLocal.sendHeader("Cache-Control", "no-store");
  servidorLocal.send(302, "text/plain", "");
}

String obtenerCookie(const String &nombre) {
  if (!servidorLocal.hasHeader("Cookie")) return "";
  String cookies = servidorLocal.header("Cookie");
  String buscar = nombre + "=";
  int start = 0;
  while (start < (int)cookies.length()) {
    int semi = cookies.indexOf(';', start);
    if (semi < 0) semi = cookies.length();
    String par = cookies.substring(start, semi);
    par.trim();
    if (par.startsWith(buscar)) {
      return par.substring(buscar.length());
    }
    start = semi + 1;
  }
  return "";
}

String obtenerIPActivaStr() {
  if (WiFi.status() == WL_CONNECTED) {
    return WiFi.localIP().toString();
  }
  return WiFi.softAPIP().toString();
}

int obtenerRSSIActivo() {
  if (WiFi.status() == WL_CONNECTED) {
    return WiFi.RSSI();
  }
  return -35; // Senal local directa por Punto de Acceso ESP32
}

// Verifica sesion activa por cookie CA_SID
int obtenerUsuarioDeSesion(SesionRec *&sesOut) {
  sesOut = nullptr;
  String sid = obtenerCookie("CA_SID");
  if (sid.length() == 0) return -1;
  SesionRec *s = buscarSesionPorSid(sid);
  if (!s) return -1;
  int idxUsr = buscarIdxUsuario(s->usuarioId);
  if (idxUsr < 0 || !dbUsuarios[idxUsr].activo) {
    destruirSesion(sid);
    return -1;
  }
  sesOut = s;
  return idxUsr;
}

bool requerirAuthAPI(SesionRec *&sesOut, int &idxUsrOut, bool soloAdmin = false) {
  idxUsrOut = obtenerUsuarioDeSesion(sesOut);
  if (idxUsrOut < 0) {
    responderErrorJSON(401, "Acceso no autorizado");
    return false;
  }
  if (soloAdmin && dbUsuarios[idxUsrOut].rol != "admin") {
    responderErrorJSON(403, "Acceso denegado: esta operacion requiere permisos de administrador");
    return false;
  }
  return true;
}

bool validarCSRF(SesionRec *ses, JsonObjectConst bodyObj) {
  if (!ses) return false;
  String tok = servidorLocal.header("X-CSRF-Token");
  tok.trim();
  if (tok.length() == 0 && !bodyObj.isNull()) {
    tok = bodyObj["csrf_token"] | "";
    tok.trim();
  }
  if (tok.length() == 0 && servidorLocal.hasArg("csrf_token")) {
    tok = servidorLocal.arg("csrf_token");
    tok.trim();
  }
  if (tok != ses->csrf) {
    responderErrorJSON(403, "Token CSRF invalido o expirado");
    return false;
  }
  return true;
}

int resolverAulaIdPermitida(int idxUsr, int aulaIdSolicitada) {
  UsuarioRec &u = dbUsuarios[idxUsr];
  if (u.rol != "admin") {
    return (u.aulaId > 0) ? u.aulaId : AULA_ID;
  }
  if (aulaIdSolicitada > 0 && buscarIdxAula(aulaIdSolicitada) >= 0) {
    return aulaIdSolicitada;
  }
  return (dbAulasCount > 0) ? dbAulas[0].id : AULA_ID;
}

bool parsearCuerpoJSON(JsonDocument &doc) {
  String raw = servidorLocal.arg("plain");
  raw.trim();
  if (raw.length() == 0) return true;
  DeserializationError err = deserializeJson(doc, raw);
  return !err;
}

// -----------------------------------------------------------------------------
// CONTROL REAL DE HARDWARE (RELE GPIO 26 + EMISOR IR GPIO 18)
// -----------------------------------------------------------------------------
void aplicarHardwareLuces(int aulaId, int nuevoEstado) {
  if (aulaId != AULA_ID) return;
  estadoLucesReal = (nuevoEstado == 1) ? 1 : 0;
  digitalWrite(PIN_RELE_LUCES, estadoLucesReal == 1 ? HIGH : LOW);
  Serial.printf("[RELE GPIO %d] Luces -> %s\n", PIN_RELE_LUCES,
                estadoLucesReal == 1 ? "ENCENDIDAS (HIGH)" : "APAGADAS (LOW)");
}

void enviarTramaEpsonNEC(uint32_t codigo) {
  receptorIR.disableIRIn();
  delay(10);
  if ((codigo & 0xFFFF0000UL) == 0xC1AA0000UL) {
    emisorIR.sendNEC(CODIGO_PROYECTOR_PREAMBULO_DEF, 32);
    delay(28);
  }
  emisorIR.sendNEC(codigo, 32);
  delay(15);
  receptorIR.enableIRIn();
}

void emitirIRDispositivo(int aulaId, const String &dispositivo, const String &accion) {
  if (aulaId != AULA_ID) return; // Solo emitir IR fisico para el aula de este ESP32

  String disp = toLowerTrim(dispositivo);
  String acc = toLowerTrim(accion);

  if (disp == "aire") {
    uint32_t cod = (acc == "apagar") ? CODIGO_STAR_AIRE_OFF_DEF : CODIGO_STAR_AIRE_ON_DEF;
    Serial.printf("[IR GPIO %d] Aire BGH COOLIX %s -> 0x%06lX\n",
                  PIN_IR_SEND, acc.c_str(), (unsigned long)cod);
    receptorIR.disableIRIn();
    delay(10);
    emisorIR.sendCOOLIX(cod, 24, 2);
    delay(15);
    receptorIR.enableIRIn();
    return;
  }

  if (disp == "proyector") {
    uint32_t cod = 0;
    uint32_t codAlt = 0;
    bool esApagar = false;

    if (acc == "prender" || acc == "encender") {
      cod = CODIGO_PROYECTOR_PRENDER_DEF;
    } else if (acc == "apagar") {
      cod = CODIGO_PROYECTOR_APAGAR_DEF;
      esApagar = true;
    } else if (acc == "hdmi1") {
      cod = CODIGO_PROYECTOR_HDMI1_DEF;
    } else if (acc == "hdmi2") {
      cod = CODIGO_PROYECTOR_HDMI2_DEF;
    } else if (acc == "vga") {
      cod = CODIGO_PROYECTOR_VGA_DEF;
    } else if (acc == "freeze") {
      cod = CODIGO_PROYECTOR_FREEZE_DEF;
    } else if (acc == "av_mute" || acc == "vol_mute" || acc == "mute") {
      cod = CODIGO_PROYECTOR_MUTE_DEF;
    } else if (acc == "vol_mas" || acc == "subir") {
      cod = CODIGO_PROYECTOR_VOL_MAS_DEF;
      codAlt = CODIGO_PROYECTOR_VOL_MAS_ALT;
    } else if (acc == "vol_menos" || acc == "bajar") {
      cod = CODIGO_PROYECTOR_VOL_MENOS_DEF;
      codAlt = CODIGO_PROYECTOR_VOL_MENOS_ALT;
    } else if (acc == "enter") {
      cod = CODIGO_PROYECTOR_ENTER_DEF;
    }

    if (cod != 0) {
      Serial.printf("[IR GPIO %d] Proyector Epson NEC %s -> 0x%08lX\n",
                    PIN_IR_SEND, acc.c_str(), (unsigned long)cod);
      enviarTramaEpsonNEC(cod);
      if (codAlt != 0) {
        delay(55);
        enviarTramaEpsonNEC(codAlt);
      }
      if (esApagar) {
        // Programar 2do pulso de confirmacion Epson a los 2000 ms sin bloquear HTTP
        segundoPulsoEpsonPendiente = true;
        segundoPulsoEpsonAtMs = millis() + PAUSA_PROYECTOR_APAGAR_MS;
        Serial.println(F("[IR EPSON] 2do pulso de apagado programado en 2000 ms"));
      }
    }
  }
}

void verificarSegundoPulsoEpson() {
  if (!segundoPulsoEpsonPendiente) return;
  if ((long)(millis() - segundoPulsoEpsonAtMs) >= 0) {
    segundoPulsoEpsonPendiente = false;
    Serial.println(F("[IR EPSON] Enviando 2do pulso de confirmacion de apagado..."));
    enviarTramaEpsonNEC(CODIGO_PROYECTOR_APAGAR_DEF);
  }
}

int apagarTodoEnAulaLocal(int aulaId, const String &origen, const String &actor) {
  int idx = buscarIdxAula(aulaId);
  if (idx < 0) return 0;
  AulaRec &a = dbAulas[idx];
  a.luces = 0;
  a.aireEnc = 0;
  a.proyEnc = 0;
  a.proyFreeze = 0;
  a.proyMute = 0;

  aplicarHardwareLuces(aulaId, 0);
  emitirIRDispositivo(aulaId, "aire", "apagar");
  delay(80);
  emitirIRDispositivo(aulaId, "proyector", "apagar");

  registrarEventoLocal(origen, "APAGAR_TODO_AULA",
                       "Apagado total en '" + a.nombre + "' por " + actor, aulaId);
  return 2;
}

int apagarServicioGlobalLocal(const String &servicio, const String &origen,
                              const String &actor, const String &motivo) {
  int afectadas = 0;
  for (int i = 0; i < dbAulasCount; i++) {
    AulaRec &a = dbAulas[i];
    if (!a.activa) continue;
    afectadas++;
    if (servicio == "luces" || servicio == "todo") {
      a.luces = 0;
      aplicarHardwareLuces(a.id, 0);
    }
    if (servicio == "aire" || servicio == "todo") {
      a.aireEnc = 0;
      emitirIRDispositivo(a.id, "aire", "apagar");
    }
    if (servicio == "proyector" || servicio == "todo") {
      a.proyEnc = 0;
      a.proyFreeze = 0;
      a.proyMute = 0;
      emitirIRDispositivo(a.id, "proyector", "apagar");
    }
  }
  String det = motivo.length() > 0
                   ? (motivo + " (servicio: " + servicio + ", " + String(afectadas) + " espacios)")
                   : ("Apagado global (" + servicio + ") en " + String(afectadas) + " espacios por " + actor);
  registrarEventoLocal(origen, "APAGADO_GLOBAL", det, 0);
  return afectadas;
}

// -----------------------------------------------------------------------------
// SETUP PRINCIPAL
// -----------------------------------------------------------------------------
void setup() {
  Serial.begin(115200);
  delay(200);
  Serial.println();
  Serial.println(F("============================================================"));
  Serial.println(F("  CONTROL DE AULA — MODO 100% LOCALHOST (ESP32 STANDALONE)"));
  Serial.print(F("  Firmware: "));
  Serial.println(F(FIRMWARE_VERSION));
  Serial.println(F("============================================================"));

  // 1. Hardware: Rele y LED de estado
  pinMode(PIN_RELE_LUCES, OUTPUT);
  digitalWrite(PIN_RELE_LUCES, LOW);
  estadoLucesReal = 0;

  pinMode(PIN_LED_ESTADO, OUTPUT);
  digitalWrite(PIN_LED_ESTADO, LOW);

  // 2. Infrarrojos
  emisorIR.begin();
  receptorIR.setUnknownThreshold(30);
  receptorIR.enableIRIn();

  // 3. Base de datos local en LittleFS
  dbBeforeFlashWriteCb = cbAntesEscribirFlash;
  dbAfterFlashWriteCb = cbDespuesEscribirFlash;
  configurarZonaHorariaAR();
  iniciarBaseDeDatos();

  // 4. Wi-Fi Modo Dual: Punto de Acceso propio SIEMPRE activo + intento STA opcional
  WiFi.mode(WIFI_AP_STA);
  WiFi.softAP(AP_SSID, AP_PASS);
  delay(150);
  IPAddress apIP = WiFi.softAPIP();

  // Portal cautivo DNS en el AP (resuelve controlaula.local y abre el panel directo)
  dnsPortal.start(53, "*", apIP);

  Serial.println(F("------------------------------------------------------------"));
  Serial.print(F("[AP PROPIO] Red Wi-Fi: "));
  Serial.print(F(AP_SSID));
  Serial.print(F(" | Clave: "));
  Serial.println(F(AP_PASS));
  Serial.print(F("[AP PROPIO] Abrir en navegador: http://"));
  Serial.println(apIP);
  Serial.println(F("------------------------------------------------------------"));

  // Intento rapido de conexion a las redes Wi-Fi configuradas (sin bloquear mucho el arranque)
  for (int i = 0; i < TOTAL_REDES_WIFI; i++) {
    if (strlen(REDES_WIFI[i].ssid) == 0) continue;
    indiceRedActual = i;
    Serial.print(F("[WIFI STA] Probando red ("));
    Serial.print(i + 1);
    Serial.print(F("/"));
    Serial.print(TOTAL_REDES_WIFI);
    Serial.print(F("): "));
    Serial.println(REDES_WIFI[i].ssid);

    WiFi.disconnect(false);
    delay(50);
    WiFi.begin(REDES_WIFI[i].ssid, REDES_WIFI[i].pass);
    unsigned long t0 = millis();
    while (WiFi.status() != WL_CONNECTED && (millis() - t0 < 3000)) {
      digitalWrite(PIN_LED_ESTADO, !digitalRead(PIN_LED_ESTADO));
      delay(180);
      Serial.print('.');
    }
    Serial.println();
    if (WiFi.status() == WL_CONNECTED) break;
  }

  if (WiFi.status() == WL_CONNECTED) {
    Serial.print(F("[WIFI STA] Conectado a "));
    Serial.print(WiFi.SSID());
    Serial.print(F(" | IP: http://"));
    Serial.println(WiFi.localIP());
    configTime(GMT_OFFSET_SEC, DAYLIGHT_OFFSET, "pool.ntp.org", "time.nist.gov");
    ntpIniciado = true;
  } else {
    WiFi.disconnect(false);
    Serial.println(F("[WIFI STA] Sin red externa por ahora (el AP ControlAula ya esta 100% operativo)."));
  }

  // 5. mDNS -> http://controlaula.local
  if (MDNS.begin(MDNS_HOST)) {
    MDNS.addService("http", "tcp", PUERTO_LOCAL_HTTP);
    mdnsIniciado = true;
    Serial.print(F("[mDNS] Disponible tambien en: http://"));
    Serial.print(F(MDNS_HOST));
    Serial.println(F(".local"));
  }

  // 6. Servidor Web Localhost completo (UI + API REST + Sesiones)
  const char *headerKeys[] = {"Cookie", "X-CSRF-Token", "Authorization"};
  servidorLocal.collectHeaders(headerKeys, 3);
  configurarServidorWeb();
  servidorLocal.begin();

  Serial.print(F("[MEMORIA] Heap libre: "));
  Serial.print(ESP.getFreeHeap());
  Serial.println(F(" bytes"));
  Serial.println(F("[SISTEMA] Servidor Localhost ESP32 LISTO en puerto 80."));
  Serial.println(F("  Usuarios default -> admin / admin123 | aula101 / aula123"));
  Serial.println(F("  Comandos Serial  -> ip | test | rafaga | resetdb"));
}

// -----------------------------------------------------------------------------
// LOOP PRINCIPAL (NO BLOQUEANTE)
// -----------------------------------------------------------------------------
void loop() {
  dnsPortal.processNextRequest();
  servidorLocal.handleClient();
  verificarSegundoPulsoEpson();
  verificarCapturaIR();
  verificarComandosSerial();
  verificarHorariosAutomaticos();
  vigilarWiFiSTA();
  actualizarLedEstado();
}

// -----------------------------------------------------------------------------
// RECONEXION STA NO BLOQUEANTE (SIN CORTAR EL AP)
// -----------------------------------------------------------------------------
void vigilarWiFiSTA() {
  if (WiFi.status() == WL_CONNECTED) {
    if (!ntpIniciado) {
      configTime(GMT_OFFSET_SEC, DAYLIGHT_OFFSET, "pool.ntp.org", "time.nist.gov");
      ntpIniciado = true;
    }
    return;
  }
  // Solo reintentar STA cada 25 segundos y si no hay clientes activos en el AP o suavemente
  unsigned long ahora = millis();
  if (ahora - ultimaReconexionStaMs >= 25000) {
    ultimaReconexionStaMs = ahora;
    if (WiFi.softAPgetStationNum() == 0) {
      WiFi.disconnect(false);
      delay(50);
      indiceRedActual = (indiceRedActual + 1) % TOTAL_REDES_WIFI;
      WiFi.begin(REDES_WIFI[indiceRedActual].ssid, REDES_WIFI[indiceRedActual].pass);
    }
  }
}

void actualizarLedEstado() {
  // LED fijo si hay clientes en el AP o Wi-Fi STA conectado; parpadeo suave si esta esperando
  if (WiFi.status() == WL_CONNECTED || WiFi.softAPgetStationNum() > 0) {
    digitalWrite(PIN_LED_ESTADO, HIGH);
  } else {
    unsigned long ahora = millis();
    if (ahora - ultimoLedToggleMs >= 900) {
      ultimoLedToggleMs = ahora;
      estadoLed = !estadoLed;
      digitalWrite(PIN_LED_ESTADO, estadoLed ? HIGH : LOW);
    }
  }
}

// -----------------------------------------------------------------------------
// EJECUTOR AUTOMATICO DE HORARIOS DE CIERRE
// -----------------------------------------------------------------------------
void verificarHorariosAutomaticos() {
  unsigned long ahora = millis();
  if (ahora - ultimoCheckHorariosMs < 15000) return;
  ultimoCheckHorariosMs = ahora;

  if (dbHorariosCount == 0) return;
  struct tm tmNow;
  if (!obtenerTmLocal(tmNow)) return;

  String horaActual = obtenerHoraHHMM();
  String fechaHoy = obtenerFechaYYYYMMDD();
  String diaStr = String(obtenerDiaSemanaISO());

  bool huboCambio = false;
  for (int i = 0; i < dbHorariosCount; i++) {
    HorarioRec &h = dbHorarios[i];
    if (!h.activo) continue;
    if (h.hora != horaActual) continue;
    if (h.ultimaEjec == fechaHoy) continue;
    if (h.dias.indexOf(diaStr) < 0) continue;

    h.ultimaEjec = fechaHoy;
    huboCambio = true;
    Serial.printf("[HORARIO AUTO] Ejecutando '%s' (%s -> %s)\n",
                  h.nombre.c_str(), h.hora.c_str(), h.accion.c_str());
    apagarServicioGlobalLocal(h.accion, "horario", "sistema",
                              "Cierre automático '" + h.nombre + "'");
  }
  if (huboCambio) {
    guardarDB();
  }
}

// -----------------------------------------------------------------------------
// RECEPTOR INFRARROJO KY-022 (CAPTURA LOCAL EN ESP32)
// -----------------------------------------------------------------------------
void verificarCapturaIR() {
  if (!receptorIR.decode(&resultadosIR)) return;

  uint16_t rawLen = getCorrectedRawLength(&resultadosIR);
  if (resultadosIR.decode_type == UNKNOWN && rawLen < 30) {
    receptorIR.resume();
    return;
  }

  String protocolo = typeToString(resultadosIR.decode_type);
  String valorHex = resultToHexidecimal(&resultadosIR);
  int bits = resultadosIR.bits;

  if (valorHex.equalsIgnoreCase("0x81C00FF0") && rawLen < 130) {
    receptorIR.resume();
    return;
  }

  receptorIR.resume();

  if (valorHex.equalsIgnoreCase("0x81C00FF0")) return;

  Serial.printf("[KY-022] Capturado -> Proto: %s | HEX: %s | Bits: %d\n",
                protocolo.c_str(), valorHex.c_str(), bits);
  registrarCapturaIRLocal(protocolo, valorHex, bits);
}

// -----------------------------------------------------------------------------
// DIAGNOSTICO POR MONITOR SERIE
// -----------------------------------------------------------------------------
void emitirRafagaDiagnostico() {
  Serial.println(F("[DIAGNOSTICO IR] Rafaga de 3s en GPIO 18..."));
  receptorIR.disableIRIn();
  unsigned long inicio = millis();
  while (millis() - inicio < 3000) {
    emisorIR.sendNEC(CODIGO_IR_PRENDER_DEF, 32);
    delay(80);
    servidorLocal.handleClient();
  }
  receptorIR.enableIRIn();
  Serial.println(F("[DIAGNOSTICO IR] Completada."));
}

void probarLoopbackIR() {
  Serial.println(F("\n[AUTOPRUEBA LOOPBACK IR] GPIO 18 -> KY-022 GPIO 15..."));
  receptorIR.enableIRIn();
  receptorIR.resume();
  delay(80);
  bool ok = false;
  String cod = "";
  for (int i = 1; i <= 3; i++) {
    emisorIR.sendNEC(CODIGO_PROYECTOR_PRENDER_DEF, 32);
    delay(120);
    if (receptorIR.decode(&resultadosIR)) {
      ok = true;
      cod = resultToHexidecimal(&resultadosIR);
      receptorIR.resume();
      break;
    }
  }
  if (ok) {
    Serial.print(F(">>> EXITO: Emisor y receptor IR OK! Capturado: "));
    Serial.println(cod);
  } else {
    Serial.println(F(">>> Sin senal en KY-022. Revisar BC337 (Base con 330 ohm a GPIO 18)."));
  }
}

void verificarComandosSerial() {
  if (!Serial.available()) return;
  String cmd = Serial.readStringUntil('\n');
  cmd.trim();
  if (cmd.equalsIgnoreCase("test") || cmd.equalsIgnoreCase("probar") || cmd.equalsIgnoreCase("loopback")) {
    probarLoopbackIR();
  } else if (cmd.equalsIgnoreCase("rafaga") || cmd.equalsIgnoreCase("test_ir")) {
    emitirRafagaDiagnostico();
  } else if (cmd.equalsIgnoreCase("ip") || cmd.equalsIgnoreCase("info")) {
    Serial.println(F("\n--- ESTADO ESP32 LOCALHOST ---"));
    Serial.print(F("AP Wi-Fi: ")); Serial.print(F(AP_SSID));
    Serial.print(F(" | IP AP: http://")); Serial.println(WiFi.softAPIP());
    Serial.print(F("Clientes en AP: ")); Serial.println(WiFi.softAPgetStationNum());
    if (WiFi.status() == WL_CONNECTED) {
      Serial.print(F("Wi-Fi STA: ")); Serial.print(WiFi.SSID());
      Serial.print(F(" | IP STA: http://")); Serial.println(WiFi.localIP());
    } else {
      Serial.println(F("Wi-Fi STA: No conectado"));
    }
    Serial.print(F("mDNS: http://")); Serial.print(F(MDNS_HOST)); Serial.println(F(".local"));
    Serial.print(F("Hora local: ")); Serial.println(obtenerFechaHoraISO());
    Serial.print(F("Heap libre: ")); Serial.print(ESP.getFreeHeap()); Serial.println(F(" bytes\n"));
  } else if (cmd.equalsIgnoreCase("resetdb")) {
    Serial.println(F("[DB] Restaurando base de datos de fabrica..."));
    sembrarDatosPorDefecto();
    guardarDB();
    Serial.println(F("[DB] Lista (admin/admin123, aula101/aula123)."));
  }
}

// -----------------------------------------------------------------------------
// CONFIGURACION DE TODAS LAS RUTAS WEB Y API REST LOCALHOST
// -----------------------------------------------------------------------------
void configurarServidorWeb() {
  // =========================================================================
  // 1. PAGINAS HTML Y ASSETS ESTATICOS (GZIP EN PROGMEM, 100% OFFLINE)
  // =========================================================================
  auto servirIndex = []() {
    SesionRec *ses = nullptr;
    if (obtenerUsuarioDeSesion(ses) < 0) {
      redirigirA("/login.php");
      return;
    }
    enviarAssetGz(ASSET_INDEX_HTML_GZ, ASSET_INDEX_HTML_GZ_LEN, "text/html; charset=utf-8", false);
  };
  servidorLocal.on("/", HTTP_GET, servirIndex);
  servidorLocal.on("/index.php", HTTP_GET, servirIndex);
  servidorLocal.on("/index.html", HTTP_GET, servirIndex);

  servidorLocal.on("/login.php", HTTP_GET, []() {
    SesionRec *ses = nullptr;
    if (obtenerUsuarioDeSesion(ses) >= 0) {
      redirigirA("/index.php");
      return;
    }
    enviarAssetGz(ASSET_LOGIN_HTML_GZ, ASSET_LOGIN_HTML_GZ_LEN, "text/html; charset=utf-8", false);
  });

  servidorLocal.on("/login.php", HTTP_POST, []() {
    String usuario = "";
    String password = "";
    long clientTs = 0;
    bool esJson = false;

    String raw = servidorLocal.arg("plain");
    raw.trim();
    if (raw.startsWith("{")) {
      esJson = true;
      CA_JSON_DOC(doc, 512);
      if (!deserializeJson(doc, raw)) {
        usuario  = doc["usuario"] | "";
        password = doc["password"] | "";
        clientTs = doc["client_ts"] | 0L;
      }
    } else {
      usuario  = servidorLocal.arg("usuario");
      password = servidorLocal.arg("password");
    }

    if (clientTs > 1700000000L) sincronizarHoraDesdeUnix(clientTs);

    String errMsg = "";
    int idxUsr = autenticarUsuarioLocal(usuario, password, errMsg);
    if (idxUsr >= 0) {
      UsuarioRec &u = dbUsuarios[idxUsr];
      SesionRec *ses = crearSesion(u.id);
      registrarEventoLocal("panel", "INICIO_SESION",
                           "Inicio de sesion exitoso de '" + u.usuario + "' (rol: " + u.rol + ")",
                           u.aulaId);

      servidorLocal.sendHeader("Set-Cookie", "CA_SID=" + ses->sid + "; Path=/; HttpOnly; SameSite=Lax");
      if (esJson) {
        CA_JSON_DOC(res, 256);
        res["ok"] = true;
        res["usuario"] = u.usuario;
        res["rol"] = u.rol;
        String out;
        serializeJson(res, out);
        responderJSON(200, out);
      } else {
        redirigirA("/index.php");
      }
    } else {
      registrarEventoLocal("seguridad", "FALLO_LOGIN",
                           "Intento fallido de login con usuario '" + usuario + "'", 0);
      if (esJson) {
        responderErrorJSON(401, errMsg);
      } else {
        redirigirA("/login.php?err=Credenciales+incorrectas");
      }
    }
  });

  servidorLocal.on("/logout.php", HTTP_GET, []() {
    SesionRec *ses = nullptr;
    int idxUsr = obtenerUsuarioDeSesion(ses);
    if (idxUsr >= 0) {
      registrarEventoLocal("panel", "CIERRE_SESION",
                           "Cierre de sesion de '" + dbUsuarios[idxUsr].usuario + "'",
                           dbUsuarios[idxUsr].aulaId);
      destruirSesion(ses->sid);
    }
    servidorLocal.sendHeader("Set-Cookie", "CA_SID=; Path=/; Max-Age=0; HttpOnly; SameSite=Lax");
    redirigirA("/login.php");
  });

  servidorLocal.on("/assets/css/estilos.css", HTTP_GET, []() {
    enviarAssetGz(ASSET_ESTILOS_CSS_GZ, ASSET_ESTILOS_CSS_GZ_LEN, "text/css; charset=utf-8", true);
  });

  servidorLocal.on("/assets/js/panel.js", HTTP_GET, []() {
    enviarAssetGz(ASSET_PANEL_JS_GZ, ASSET_PANEL_JS_GZ_LEN, "application/javascript; charset=utf-8", true);
  });

  servidorLocal.on("/assets/icons/icon.svg", HTTP_GET, []() {
    enviarAssetGz(ASSET_ICON_SVG_GZ, ASSET_ICON_SVG_GZ_LEN, "image/svg+xml", true);
  });

  servidorLocal.on("/assets/icons/icon-192.png", HTTP_GET, []() {
    enviarAssetRaw(ASSET_ICON_192_PNG, ASSET_ICON_192_PNG_LEN, "image/png");
  });

  servidorLocal.on("/assets/icons/apple-touch-icon.png", HTTP_GET, []() {
    enviarAssetRaw(ASSET_ICON_192_PNG, ASSET_ICON_192_PNG_LEN, "image/png");
  });

  servidorLocal.on("/assets/icons/icon-512.png", HTTP_GET, []() {
    enviarAssetRaw(ASSET_ICON_512_PNG, ASSET_ICON_512_PNG_LEN, "image/png");
  });

  servidorLocal.on("/manifest.json", HTTP_GET, []() {
    enviarAssetGz(ASSET_MANIFEST_JSON_GZ, ASSET_MANIFEST_JSON_GZ_LEN, "application/manifest+json; charset=utf-8", true);
  });

  servidorLocal.on("/sw.js", HTTP_GET, []() {
    servidorLocal.sendHeader("Service-Worker-Allowed", "/");
    enviarAssetGz(ASSET_SW_JS_GZ, ASSET_SW_JS_GZ_LEN, "application/javascript; charset=utf-8", false);
  });

  servidorLocal.on("/ControlAula.apk", HTTP_GET, []() {
    servidorLocal.sendHeader("Content-Disposition", "attachment; filename=\"ControlAula.apk\"");
    enviarAssetRaw(ASSET_APP_APK, ASSET_APP_APK_LEN, "application/vnd.android.package-archive");
  });

  // =========================================================================
  // 2. API SESION Y SINCRONIZACION DE HORA LOCAL
  // =========================================================================
  servidorLocal.on("/api/sesion.php", HTTP_GET, []() {
    if (servidorLocal.hasArg("client_ts")) {
      sincronizarHoraDesdeUnix(servidorLocal.arg("client_ts").toInt());
    }
    SesionRec *ses = nullptr;
    int idxUsr = -1;
    if (!requerirAuthAPI(ses, idxUsr, false)) return;

    UsuarioRec &u = dbUsuarios[idxUsr];
    int aid = (u.aulaId > 0) ? u.aulaId : AULA_ID;
    int idxAula = buscarIdxAula(aid);

    CA_JSON_DOC(doc, 1536);
    doc["ok"] = true;
    doc["usuario_id"] = u.id;
    doc["usuario"] = u.usuario;
    doc["nombre_completo"] = u.nombreCompleto;
    doc["rol"] = u.rol;
    doc["aula_id"] = aid;
    doc["aula_nombre"] = (idxAula >= 0) ? dbAulas[idxAula].nombre : "Aula 101";
    doc["aula_tipo"] = (idxAula >= 0) ? dbAulas[idxAula].tipo : "Aula";
    doc["csrf_token"] = ses->csrf;
    if (u.prefsJson.length() > 2) {
      CA_JSON_DOC(pDoc, 768);
      if (!deserializeJson(pDoc, u.prefsJson)) {
        doc["preferencias"].set(pDoc.as<JsonVariantConst>());
      }
    }

    String out;
    serializeJson(doc, out);
    responderJSON(200, out);
  });

  // =========================================================================
  // 3. API ESTADO DEL AULA (GET /api/estado.php)
  // =========================================================================
  servidorLocal.on("/api/estado.php", HTTP_GET, []() {
    SesionRec *ses = nullptr;
    int idxUsr = -1;
    if (!requerirAuthAPI(ses, idxUsr, false)) return;

    int aulaSol = servidorLocal.hasArg("aula_id") ? servidorLocal.arg("aula_id").toInt() : 0;
    int aulaId = resolverAulaIdPermitida(idxUsr, aulaSol);
    int idxA = buscarIdxAula(aulaId);
    if (idxA < 0) {
      responderErrorJSON(404, "Aula no encontrada");
      return;
    }

    AulaRec &a = dbAulas[idxA];
    bool esEstaPlaca = (a.id == AULA_ID);

    CA_JSON_DOC(doc, 1536);
    doc["ok"] = true;
    doc["aula_id"] = a.id;
    doc["aula_nombre"] = a.nombre;
    doc["aula_ubicacion"] = a.ubicacion;

    JsonObject hw = CA_SET_OBJ(doc, "hardware");
    hw["marca_aire"] = a.marcaAire;
    hw["protocolo_aire"] = a.protoAire;
    hw["marca_proyector"] = a.marcaProy;
    hw["protocolo_proyector"] = a.protoProy;

    doc["esp32_online"] = esEstaPlaca;
    doc["luces"] = a.luces;
    doc["luces_deseadas"] = a.luces;
    doc["luces_reales"] = esEstaPlaca ? estadoLucesReal : a.luces;
    doc["aire_encendido"] = a.aireEnc;
    doc["aire_temp"] = a.aireTemp;
    doc["aire_modo"] = a.aireModo;
    doc["aire_fan"] = a.aireFan;
    doc["aire_swing"] = a.aireSwing;
    doc["proyector_encendido"] = a.proyEnc;
    doc["proyector_fuente"] = a.proyFuente;
    doc["proyector_freeze"] = a.proyFreeze;
    doc["proyector_mute"] = a.proyMute;
    doc["proyector_volumen"] = a.proyVol;
    doc["ip"] = esEstaPlaca ? obtenerIPActivaStr() : "";
    doc["rssi"] = esEstaPlaca ? obtenerRSSIActivo() : 0;
    doc["uptime"] = esEstaPlaca ? (unsigned long)(millis() / 1000) : 0UL;
    doc["version_fw"] = FIRMWARE_VERSION;
    doc["segundos_desde_contacto"] = esEstaPlaca ? 0 : 9999;
    doc["comandos_ir_pendientes"] = segundoPulsoEpsonPendiente ? 1 : 0;
    doc["hora_servidor"] = obtenerFechaHoraISO();

    String out;
    serializeJson(doc, out);
    responderJSON(200, out);
  });

  // =========================================================================
  // 4. API LUCES (POST /api/luces.php)
  // =========================================================================
  servidorLocal.on("/api/luces.php", HTTP_POST, []() {
    SesionRec *ses = nullptr;
    int idxUsr = -1;
    if (!requerirAuthAPI(ses, idxUsr, false)) return;

    CA_JSON_DOC(body, 512);
    if (!parsearCuerpoJSON(body)) { responderErrorJSON(400, "JSON invalido"); return; }
    if (!validarCSRF(ses, body.as<JsonObjectConst>())) return;

    int estado = body["estado"] | (body["luces"] | -1);
    if (estado != 0 && estado != 1) {
      responderErrorJSON(422, "Valor de estado invalido (use 0 o 1)");
      return;
    }

    int aulaId = resolverAulaIdPermitida(idxUsr, body["aula_id"] | 0);
    int idxA = buscarIdxAula(aulaId);
    if (idxA < 0) { responderErrorJSON(404, "Aula no encontrada"); return; }

    dbAulas[idxA].luces = estado;
    aplicarHardwareLuces(aulaId, estado);

    registrarEventoLocal("panel", "CAMBIO_LUCES",
                         "Luces cambiadas a " + String(estado == 1 ? "ENCENDER" : "APAGAR") +
                             " por " + dbUsuarios[idxUsr].usuario + " (" + dbAulas[idxA].nombre + ")",
                         aulaId);

    CA_JSON_DOC(res, 256);
    res["ok"] = true;
    res["aula_id"] = aulaId;
    res["mensaje"] = (estado == 1) ? "Luces ENCENDIDAS" : "Luces APAGADAS";
    res["luces"] = estado;
    res["luces_deseadas"] = estado;
    res["luces_reales"] = estado;
    String out;
    serializeJson(res, out);
    responderJSON(200, out);
  });

  // =========================================================================
  // 5. API AIRE ACONDICIONADO (POST /api/aire.php)
  // =========================================================================
  servidorLocal.on("/api/aire.php", HTTP_POST, []() {
    SesionRec *ses = nullptr;
    int idxUsr = -1;
    if (!requerirAuthAPI(ses, idxUsr, false)) return;

    CA_JSON_DOC(body, 512);
    if (!parsearCuerpoJSON(body)) { responderErrorJSON(400, "JSON invalido"); return; }
    if (!validarCSRF(ses, body.as<JsonObjectConst>())) return;

    int aulaId = resolverAulaIdPermitida(idxUsr, body["aula_id"] | 0);
    int idxA = buscarIdxAula(aulaId);
    if (idxA < 0) { responderErrorJSON(404, "Aula no encontrada"); return; }

    AulaRec &a = dbAulas[idxA];
    String accion = toLowerTrim(body["accion"] | "");
    if (accion == "apagar") {
      a.aireEnc = 0;
    } else if (accion == "prender" || accion == "encender") {
      a.aireEnc = 1;
    } else if (!body["encendido"].isNull()) {
      a.aireEnc = (body["encendido"].as<int>() == 1) ? 1 : 0;
    } else {
      a.aireEnc = 1;
    }

    if (!body["temp"].isNull()) a.aireTemp = constrain(body["temp"].as<int>(), 16, 30);
    if (!body["modo"].isNull()) a.aireModo = body["modo"].as<String>();
    if (!body["fan"].isNull())  a.aireFan  = body["fan"].as<String>();
    if (!body["swing"].isNull()) a.aireSwing = body["swing"].as<int>() ? 1 : 0;

    String accIR = (a.aireEnc == 1) ? "prender" : "apagar";
    emitirIRDispositivo(aulaId, "aire", accIR);

    String resumen = (a.aireEnc == 0)
                         ? ("Aire acondicionado APAGADO (" + a.nombre + ")")
                         : ("Aire acondicionado ENCENDIDO (" + a.nombre + ")");
    registrarEventoLocal("panel", "CONTROL_AIRE",
                         resumen + " por " + dbUsuarios[idxUsr].usuario, aulaId);

    CA_JSON_DOC(res, 512);
    res["ok"] = true;
    res["aula_id"] = aulaId;
    res["mensaje"] = resumen;
    res["dispositivo"] = "aire";
    res["accion"] = accIR;
    res["aire_encendido"] = a.aireEnc;
    res["aire_temp"] = a.aireTemp;
    res["aire_modo"] = a.aireModo;
    res["aire_fan"] = a.aireFan;
    res["aire_swing"] = a.aireSwing;
    res["marca_aire"] = "BGH";
    res["protocolo_aire"] = "COOLIX";
    String out;
    serializeJson(res, out);
    responderJSON(200, out);
  });

  // =========================================================================
  // 6. API PROYECTOR MULTIMEDIA (POST /api/proyector.php)
  // =========================================================================
  servidorLocal.on("/api/proyector.php", HTTP_POST, []() {
    SesionRec *ses = nullptr;
    int idxUsr = -1;
    if (!requerirAuthAPI(ses, idxUsr, false)) return;

    CA_JSON_DOC(body, 512);
    if (!parsearCuerpoJSON(body)) { responderErrorJSON(400, "JSON invalido"); return; }
    if (!validarCSRF(ses, body.as<JsonObjectConst>())) return;

    int aulaId = resolverAulaIdPermitida(idxUsr, body["aula_id"] | 0);
    int idxA = buscarIdxAula(aulaId);
    if (idxA < 0) { responderErrorJSON(404, "Aula no encontrada"); return; }

    AulaRec &a = dbAulas[idxA];
    String accion = toLowerTrim(body["accion"] | "");
    if (accion.length() == 0) {
      if (!body["valor"].isNull()) accion = "set_volumen";
      else if (!body["fuente"].isNull()) accion = "fuente";
      else accion = "prender";
    }

    String accIR = accion;
    String det = "";

    if (accion == "prender" || accion == "encender") {
      a.proyEnc = 1; a.proyFreeze = 0; a.proyMute = 0; accIR = "prender";
      det = "Proyector ENCENDIDO (" + a.proyFuente + ")";
    } else if (accion == "apagar") {
      a.proyEnc = 0; a.proyFreeze = 0; a.proyMute = 0; accIR = "apagar";
      det = "Proyector APAGADO (doble pulso Epson)";
    } else if (accion == "hdmi1" || accion == "hdmi2" || accion == "vga") {
      a.proyFuente = accion; a.proyEnc = 1; accIR = accion;
      det = "Fuente cambiada a " + accion;
    } else if (accion == "fuente") {
      String f = toLowerTrim(body["fuente"] | "hdmi1");
      if (f != "hdmi1" && f != "hdmi2" && f != "vga") { responderErrorJSON(422, "Fuente invalida"); return; }
      a.proyFuente = f; a.proyEnc = 1; accIR = f;
      det = "Fuente cambiada a " + f;
    } else if (accion == "freeze") {
      a.proyFreeze = a.proyFreeze ? 0 : 1; a.proyEnc = 1; accIR = "freeze";
      det = a.proyFreeze ? "Imagen CONGELADA (Freeze ON)" : "Imagen REANUDADA (Freeze OFF)";
    } else if (accion == "av_mute") {
      a.proyMute = a.proyMute ? 0 : 1; a.proyEnc = 1; accIR = "av_mute";
      det = a.proyMute ? "Pantalla en Negro ACTIVADA (AV Mute)" : "Pantalla en Negro DESACTIVADA";
    } else if (accion == "vol_mas" || accion == "subir") {
      a.proyVol = min(100, a.proyVol + 5); accIR = "vol_mas";
      det = "Volumen subido a " + String(a.proyVol) + "%";
    } else if (accion == "vol_menos" || accion == "bajar") {
      a.proyVol = max(0, a.proyVol - 5); accIR = "vol_menos";
      det = "Volumen bajado a " + String(a.proyVol) + "%";
    } else if (accion == "vol_mute" || accion == "mute") {
      if (a.proyVol == 0) { a.proyVol = 50; accIR = "vol_mas"; det = "Audio restaurado (50%)"; }
      else { a.proyVol = 0; accIR = "vol_mute"; det = "Audio silenciado (MUTE)"; }
    } else if (accion == "set_volumen" || accion == "volumen") {
      int nuevoVol = constrain(body["valor"] | (body["volumen"] | a.proyVol), 0, 100);
      if (nuevoVol == 0) accIR = "vol_mute";
      else if (nuevoVol < a.proyVol) accIR = "vol_menos";
      else accIR = "vol_mas";
      a.proyVol = nuevoVol;
      det = (nuevoVol == 0) ? "Audio silenciado (MUTE)" : ("Volumen ajustado a " + String(nuevoVol) + "%");
    } else {
      responderErrorJSON(400, "Accion de proyector no reconocida");
      return;
    }

    emitirIRDispositivo(aulaId, "proyector", accIR);
    registrarEventoLocal("panel", "CONTROL_PROYECTOR",
                         det + " por " + dbUsuarios[idxUsr].usuario + " (" + a.nombre + ")", aulaId);

    CA_JSON_DOC(res, 512);
    res["ok"] = true;
    res["aula_id"] = aulaId;
    res["mensaje"] = det;
    res["dispositivo"] = "proyector";
    res["accion"] = accIR;
    res["proyector_encendido"] = a.proyEnc;
    res["proyector_fuente"] = a.proyFuente;
    res["proyector_freeze"] = a.proyFreeze;
    res["proyector_mute"] = a.proyMute;
    res["proyector_volumen"] = a.proyVol;
    String out;
    serializeJson(res, out);
    responderJSON(200, out);
  });

  // =========================================================================
  // 7. API ESCENAS RAPIDAS (POST /api/escenas.php)
  // =========================================================================
  servidorLocal.on("/api/escenas.php", HTTP_POST, []() {
    SesionRec *ses = nullptr;
    int idxUsr = -1;
    if (!requerirAuthAPI(ses, idxUsr, false)) return;

    CA_JSON_DOC(body, 512);
    if (!parsearCuerpoJSON(body)) { responderErrorJSON(400, "JSON invalido"); return; }
    if (!validarCSRF(ses, body.as<JsonObjectConst>())) return;

    String escena = toLowerTrim(body["escena"] | "");
    if (escena == "proyeccion") escena = "modo_proyeccion";

    int aulaId = resolverAulaIdPermitida(idxUsr, body["aula_id"] | 0);
    int idxA = buscarIdxAula(aulaId);
    if (idxA < 0) { responderErrorJSON(404, "Aula no encontrada"); return; }
    AulaRec &a = dbAulas[idxA];

    CA_JSON_DOC(res, 512);
    res["ok"] = true;
    res["aula_id"] = aulaId;
    res["escena"] = escena;

    if (escena == "modo_proyeccion") {
      a.luces = 0; a.proyEnc = 1; a.proyFreeze = 0; a.proyMute = 0;
      aplicarHardwareLuces(aulaId, 0);
      emitirIRDispositivo(aulaId, "proyector", "prender");
      String msg = "Escena 'Modo Proyección' activada en " + a.nombre + ": luces apagadas y proyector encendido";
      registrarEventoLocal("panel", "ESCENA_MODO_PROYECCION", msg + " por " + dbUsuarios[idxUsr].usuario, aulaId);
      res["mensaje"] = msg;
      res["luces"] = 0;
      res["proyector_encendido"] = 1;
    } else if (escena == "iniciar_clase") {
      int t = constrain(body["aire_temp"] | (body["pref_aire_temp"] | 24), 16, 30);
      String m = body["aire_modo"] | (body["pref_aire_modo"] | "frio");
      a.luces = 1; a.aireEnc = 1; a.aireTemp = t; a.aireModo = m; a.aireFan = "auto"; a.aireSwing = 1;
      aplicarHardwareLuces(aulaId, 1);
      emitirIRDispositivo(aulaId, "aire", "prender");
      String msg = "Escena 'Iniciar Clase' activada en " + a.nombre + ": luces y aire encendidos";
      registrarEventoLocal("panel", "ESCENA_INICIAR_CLASE", msg + " por " + dbUsuarios[idxUsr].usuario, aulaId);
      res["mensaje"] = msg;
      res["luces"] = 1;
      res["aire_encendido"] = 1;
      res["aire_temp"] = t;
      res["aire_modo"] = m;
    } else if (escena == "cerrar_aula") {
      apagarTodoEnAulaLocal(aulaId, "panel", dbUsuarios[idxUsr].usuario);
      String msg = "Escena 'Cerrar Aula' ejecutada en " + a.nombre + ": luces, aire y proyector apagados";
      registrarEventoLocal("panel", "ESCENA_CERRAR_AULA", msg + " por " + dbUsuarios[idxUsr].usuario, aulaId);
      res["mensaje"] = msg;
      res["luces"] = 0;
      res["aire_encendido"] = 0;
      res["proyector_encendido"] = 0;
    } else {
      responderErrorJSON(400, "Escena invalida");
      return;
    }

    String out;
    serializeJson(res, out);
    responderJSON(200, out);
  });

  // =========================================================================
  // 8. API IR GENERICO (POST /api/ir.php)
  // =========================================================================
  servidorLocal.on("/api/ir.php", HTTP_POST, []() {
    SesionRec *ses = nullptr;
    int idxUsr = -1;
    if (!requerirAuthAPI(ses, idxUsr, false)) return;

    CA_JSON_DOC(body, 512);
    if (!parsearCuerpoJSON(body)) { responderErrorJSON(400, "JSON invalido"); return; }
    if (!validarCSRF(ses, body.as<JsonObjectConst>())) return;

    String disp = toLowerTrim(body["dispositivo"] | "");
    String acc = toLowerTrim(body["accion"] | "");
    int aulaId = resolverAulaIdPermitida(idxUsr, body["aula_id"] | 0);
    if (disp.length() == 0 || acc.length() == 0) {
      responderErrorJSON(400, "Dispositivo y accion son obligatorios");
      return;
    }
    emitirIRDispositivo(aulaId, disp, acc);
    registrarEventoLocal("panel", "COMANDO_IR",
                         "IR " + disp + " -> " + acc + " por " + dbUsuarios[idxUsr].usuario, aulaId);
    responderJSON(200, "{\"ok\":true,\"mensaje\":\"Comando IR ejecutado localmente\"}");
  });

  // =========================================================================
  // 9. API AULAS / ESPACIOS (GET y POST /api/aulas.php)
  // =========================================================================
  servidorLocal.on("/api/aulas.php", HTTP_GET, []() {
    SesionRec *ses = nullptr;
    int idxUsr = -1;
    if (!requerirAuthAPI(ses, idxUsr, false)) return;

    UsuarioRec &u = dbUsuarios[idxUsr];
    bool esAdmin = (u.rol == "admin");

    CA_JSON_DOC(doc, 8192);
    doc["ok"] = true;
    doc["es_admin"] = esAdmin;
    JsonArray arr = CA_SET_ARR(doc, "aulas");

    int total = 0;
    for (int i = 0; i < dbAulasCount; i++) {
      AulaRec &a = dbAulas[i];
      if (!esAdmin && a.id != u.aulaId) continue;

      total++;
      JsonObject o = CA_ADD_OBJ(arr);
      o["id"] = a.id;
      o["nombre"] = a.nombre;
      o["tipo"] = a.tipo;
      o["ubicacion"] = a.ubicacion;
      int idxUA = buscarIdxUsuarioPorAula(a.id);
      o["usuario_acceso"] = (idxUA >= 0) ? dbUsuarios[idxUA].usuario : a.nombre;
      o["activa"] = a.activa ? 1 : 0;
      o["online"] = (a.id == AULA_ID);
      o["rssi_wifi"] = (a.id == AULA_ID) ? obtenerRSSIActivo() : 0;
      o["uptime_seg"] = (a.id == AULA_ID) ? (unsigned long)(millis() / 1000) : 0UL;

      JsonObject hw = CA_SET_OBJ(o, "hardware");
      hw["marca_aire"] = a.marcaAire;
      hw["protocolo_aire"] = a.protoAire;
      hw["marca_proyector"] = a.marcaProy;
      hw["protocolo_proyector"] = a.protoProy;

      JsonObject est = CA_SET_OBJ(o, "estado");
      est["luces"] = a.luces;
      est["aire_encendido"] = a.aireEnc;
      est["aire_temp"] = a.aireTemp;
      est["aire_modo"] = a.aireModo;
      est["aire_fan"] = a.aireFan;
      est["aire_swing"] = a.aireSwing;
      est["proyector_encendido"] = a.proyEnc;
      est["proyector_fuente"] = a.proyFuente;
      est["proyector_freeze"] = a.proyFreeze;
      est["proyector_mute"] = a.proyMute;
      est["proyector_volumen"] = a.proyVol;
    }
    doc["total"] = total;

    String out;
    serializeJson(doc, out);
    responderJSON(200, out);
  });

  servidorLocal.on("/api/aulas.php", HTTP_POST, []() {
    SesionRec *ses = nullptr;
    int idxUsr = -1;
    if (!requerirAuthAPI(ses, idxUsr, false)) return;

    CA_JSON_DOC(body, 1024);
    if (!parsearCuerpoJSON(body)) { responderErrorJSON(400, "JSON invalido"); return; }
    if (!validarCSRF(ses, body.as<JsonObjectConst>())) return;

    String op = toLowerTrim(body["operacion"] | "");
    int aulaId = body["aula_id"] | (body["id"] | 0);
    String aulaIdStr = body["aula_id"] | "";
    bool esAdmin = (dbUsuarios[idxUsr].rol == "admin");

    if (op == "apagar_todo") {
      if (aulaId == 0 || aulaIdStr.equalsIgnoreCase("todas")) {
        if (!esAdmin) { responderErrorJSON(403, "Solo administradores"); return; }
        int af = apagarServicioGlobalLocal("todo", "panel", dbUsuarios[idxUsr].usuario);
        CA_JSON_DOC(res, 256);
        res["ok"] = true;
        res["mensaje"] = "Se apagaron todos los equipos en " + String(af) + " espacios activos";
        res["aulas_afectadas"] = af;
        String out; serializeJson(res, out);
        responderJSON(200, out);
        return;
      }
      int targetId = resolverAulaIdPermitida(idxUsr, aulaId);
      apagarTodoEnAulaLocal(targetId, "panel", dbUsuarios[idxUsr].usuario);
      CA_JSON_DOC(res, 256);
      res["ok"] = true;
      res["mensaje"] = "Apagado total ejecutado en el aula #" + String(targetId);
      res["aula_id"] = targetId;
      String out; serializeJson(res, out);
      responderJSON(200, out);
      return;
    }

    if (!esAdmin) { responderErrorJSON(403, "Requiere rol administrador"); return; }

    if (op == "apagar_servicio") {
      String srv = toLowerTrim(body["servicio"] | "");
      if (srv != "luces" && srv != "aire" && srv != "proyector" && srv != "todo") {
        responderErrorJSON(422, "Servicio invalido");
        return;
      }
      int af = apagarServicioGlobalLocal(srv, "panel", dbUsuarios[idxUsr].usuario);
      CA_JSON_DOC(res, 256);
      res["ok"] = true;
      res["mensaje"] = "Apagado selectivo (" + srv + ") ejecutado en " + String(af) + " espacios activos";
      res["aulas_afectadas"] = af;
      String out; serializeJson(res, out);
      responderJSON(200, out);
      return;
    }

    if (op == "crear") {
      if (dbAulasCount >= MAX_AULAS) { responderErrorJSON(422, "Limite maximo de espacios en ESP32 alcanzado"); return; }
      String nombre = body["nombre"] | ""; nombre.trim();
      String tipo = body["tipo"] | "Aula"; tipo.trim();
      String pass = body["password"] | ""; pass.trim();
      if (nombre.length() < 2) { responderErrorJSON(422, "Ingrese el nombre del espacio"); return; }
      if (pass.length() == 0) pass = "aula123";

      String normN = normalizarCompacto(nombre);
      for (int i = 0; i < dbUsuariosCount; i++) {
        if (normalizarCompacto(dbUsuarios[i].usuario) == normN) {
          responderErrorJSON(422, "Ya existe un espacio o cuenta con ese nombre");
          return;
        }
      }

      AulaRec &a = dbAulas[dbAulasCount++];
      a = AulaRec();
      a.id = dbNextAulaId++;
      a.nombre = nombre;
      a.tipo = (tipo.length() >= 2) ? tipo : "Aula";
      a.activa = true;

      if (dbUsuariosCount < MAX_USUARIOS) {
        UsuarioRec &u = dbUsuarios[dbUsuariosCount++];
        u = UsuarioRec();
        u.id = dbNextUsuarioId++;
        u.aulaId = a.id;
        u.usuario = nombre;
        u.passHash = sha256Salted(pass);
        u.nombreCompleto = nombre;
        u.rol = "aula";
        u.activo = true;
      }

      registrarEventoLocal("panel", "CREAR_AULA",
                           "Espacio '" + nombre + "' (" + a.tipo + ") creado por " + dbUsuarios[idxUsr].usuario, a.id);
      guardarDB();

      CA_JSON_DOC(res, 384);
      res["ok"] = true;
      res["mensaje"] = "Espacio '" + nombre + "' (" + a.tipo + ") creado con usuario '" + nombre + "'";
      String out; serializeJson(res, out);
      responderJSON(200, out);
      return;
    }

    if (op == "editar") {
      int idxA = buscarIdxAula(aulaId);
      if (idxA < 0) { responderErrorJSON(404, "Aula no encontrada"); return; }
      String nombre = body["nombre"] | ""; nombre.trim();
      String tipo = body["tipo"] | "Aula"; tipo.trim();
      String pass = body["password"] | ""; pass.trim();
      if (nombre.length() < 2) { responderErrorJSON(422, "Nombre invalido"); return; }

      dbAulas[idxA].nombre = nombre;
      dbAulas[idxA].tipo = (tipo.length() >= 2) ? tipo : "Aula";

      int idxUA = buscarIdxUsuarioPorAula(aulaId);
      if (idxUA >= 0) {
        dbUsuarios[idxUA].usuario = nombre;
        dbUsuarios[idxUA].nombreCompleto = nombre;
        if (pass.length() >= 4) dbUsuarios[idxUA].passHash = sha256Salted(pass);
      }

      registrarEventoLocal("panel", "EDITAR_AULA",
                           "Espacio #" + String(aulaId) + " actualizado a '" + nombre + "' por " + dbUsuarios[idxUsr].usuario, aulaId);
      guardarDB();
      responderJSON(200, "{\"ok\":true,\"mensaje\":\"Espacio actualizado correctamente\"}");
      return;
    }

    if (op == "toggle_activa") {
      int idxA = buscarIdxAula(aulaId);
      if (idxA < 0) { responderErrorJSON(404, "Aula no encontrada"); return; }
      dbAulas[idxA].activa = !dbAulas[idxA].activa;
      int idxUA = buscarIdxUsuarioPorAula(aulaId);
      if (idxUA >= 0) dbUsuarios[idxUA].activo = dbAulas[idxA].activa;

      String txt = dbAulas[idxA].activa ? "activada" : "desactivada";
      registrarEventoLocal("panel", "ESTADO_AULA",
                           "Espacio '" + dbAulas[idxA].nombre + "' " + txt + " por " + dbUsuarios[idxUsr].usuario, aulaId);
      guardarDB();
      responderJSON(200, "{\"ok\":true,\"mensaje\":\"Espacio " + txt + "\",\"activa\":" + String(dbAulas[idxA].activa ? 1 : 0) + "}");
      return;
    }

    if (op == "eliminar") {
      if (aulaId <= 1) { responderErrorJSON(422, "No se puede eliminar el Aula principal (#1)"); return; }
      int idxA = buscarIdxAula(aulaId);
      if (idxA < 0) { responderErrorJSON(404, "Aula no encontrada"); return; }
      String nom = dbAulas[idxA].nombre;
      for (int i = idxA; i < dbAulasCount - 1; i++) dbAulas[i] = dbAulas[i + 1];
      dbAulasCount--;

      int idxUA = buscarIdxUsuarioPorAula(aulaId);
      if (idxUA >= 0) {
        for (int i = idxUA; i < dbUsuariosCount - 1; i++) dbUsuarios[i] = dbUsuarios[i + 1];
        dbUsuariosCount--;
      }

      registrarEventoLocal("panel", "ELIMINAR_AULA",
                           "Espacio '" + nom + "' eliminado por " + dbUsuarios[idxUsr].usuario, 0);
      guardarDB();
      responderOkMsg("Espacio '" + nom + "' eliminado correctamente");
      return;
    }

    responderErrorJSON(400, "Operacion no valida en /api/aulas");
  });

  // =========================================================================
  // 10. API TIPOS DE ESPACIO (GET y POST /api/tipos_espacio.php)
  // =========================================================================
  servidorLocal.on("/api/tipos_espacio.php", HTTP_GET, []() {
    SesionRec *ses = nullptr;
    int idxUsr = -1;
    if (!requerirAuthAPI(ses, idxUsr, false)) return;

    CA_JSON_DOC(doc, 2048);
    doc["ok"] = true;
    JsonArray arr = CA_SET_ARR(doc, "tipos");
    for (int i = 0; i < dbTiposCount; i++) {
      JsonObject o = CA_ADD_OBJ(arr);
      o["id"] = dbTipos[i].id;
      o["nombre"] = dbTipos[i].nombre;
      o["icono"] = dbTipos[i].icono;
    }
    String out; serializeJson(doc, out);
    responderJSON(200, out);
  });

  servidorLocal.on("/api/tipos_espacio.php", HTTP_POST, []() {
    SesionRec *ses = nullptr;
    int idxUsr = -1;
    if (!requerirAuthAPI(ses, idxUsr, true)) return;

    CA_JSON_DOC(body, 512);
    if (!parsearCuerpoJSON(body)) { responderErrorJSON(400, "JSON invalido"); return; }
    if (!validarCSRF(ses, body.as<JsonObjectConst>())) return;

    String op = toLowerTrim(body["operacion"] | "crear");
    if (op == "crear") {
      if (dbTiposCount >= MAX_TIPOS) { responderErrorJSON(422, "Limite maximo de tipos alcanzado"); return; }
      String nom = body["nombre"] | ""; nom.trim();
      String ico = body["icono"] | "bi-building"; ico.trim();
      if (nom.length() < 2) { responderErrorJSON(422, "Nombre muy corto"); return; }
      for (int i = 0; i < dbTiposCount; i++) {
        if (toLowerTrim(dbTipos[i].nombre) == toLowerTrim(nom)) {
          responderErrorJSON(422, "Ya existe el tipo '" + nom + "'");
          return;
        }
      }
      TipoRec &t = dbTipos[dbTiposCount++];
      t.id = dbNextTipoId++;
      t.nombre = nom;
      t.icono = ico;
      registrarEventoLocal("panel", "CREAR_TIPO_ESPACIO", "Tipo '" + nom + "' creado por " + dbUsuarios[idxUsr].usuario, 0);
      guardarDB();
      responderOkMsg("Tipo de espacio '" + nom + "' agregado");
      return;
    }

    if (op == "eliminar") {
      int id = body["id"] | 0;
      int idxT = -1;
      for (int i = 0; i < dbTiposCount; i++) if (dbTipos[i].id == id) { idxT = i; break; }
      if (idxT < 0) { responderErrorJSON(404, "Tipo no encontrado"); return; }
      if (toLowerTrim(dbTipos[idxT].nombre) == "aula") { responderErrorJSON(422, "No se puede eliminar 'Aula'"); return; }
      for (int i = 0; i < dbAulasCount; i++) {
        if (dbAulas[i].tipo == dbTipos[idxT].nombre) {
          responderErrorJSON(422, "Hay espacios asignados a ese tipo");
          return;
        }
      }
      String nom = dbTipos[idxT].nombre;
      for (int i = idxT; i < dbTiposCount - 1; i++) dbTipos[i] = dbTipos[i + 1];
      dbTiposCount--;
      registrarEventoLocal("panel", "ELIMINAR_TIPO_ESPACIO", "Tipo '" + nom + "' eliminado por " + dbUsuarios[idxUsr].usuario, 0);
      guardarDB();
      responderOkMsg("Tipo '" + nom + "' eliminado");
      return;
    }

    responderErrorJSON(400, "Operacion no soportada");
  });

  // =========================================================================
  // 11. API HORARIOS AUTOMATICOS (GET y POST /api/horarios.php)
  // =========================================================================
  servidorLocal.on("/api/horarios.php", HTTP_GET, []() {
    SesionRec *ses = nullptr;
    int idxUsr = -1;
    if (!requerirAuthAPI(ses, idxUsr, true)) return;

    CA_JSON_DOC(doc, 3072);
    doc["ok"] = true;
    doc["hora_servidor"] = obtenerHoraHHMM();
    JsonArray arr = CA_SET_ARR(doc, "horarios");
    for (int i = 0; i < dbHorariosCount; i++) {
      JsonObject o = CA_ADD_OBJ(arr);
      o["id"] = dbHorarios[i].id;
      o["nombre"] = dbHorarios[i].nombre;
      o["hora"] = dbHorarios[i].hora;
      o["dias"] = dbHorarios[i].dias;
      o["accion"] = dbHorarios[i].accion;
      o["activo"] = dbHorarios[i].activo ? 1 : 0;
      o["ultima_ejecucion"] = dbHorarios[i].ultimaEjec;
    }
    String out; serializeJson(doc, out);
    responderJSON(200, out);
  });

  servidorLocal.on("/api/horarios.php", HTTP_POST, []() {
    SesionRec *ses = nullptr;
    int idxUsr = -1;
    if (!requerirAuthAPI(ses, idxUsr, true)) return;

    CA_JSON_DOC(body, 512);
    if (!parsearCuerpoJSON(body)) { responderErrorJSON(400, "JSON invalido"); return; }
    if (!validarCSRF(ses, body.as<JsonObjectConst>())) return;

    String op = toLowerTrim(body["operacion"] | "crear");
    if (op == "crear") {
      if (dbHorariosCount >= MAX_HORARIOS) { responderErrorJSON(422, "Limite maximo de horarios alcanzado"); return; }
      String nom = body["nombre"] | ""; nom.trim();
      String hora = body["hora"] | "18:30"; hora.trim();
      String dias = body["dias"] | "1,2,3,4,5"; dias.trim();
      String acc = toLowerTrim(body["accion"] | "todo");
      if (nom.length() < 2) { responderErrorJSON(422, "Ingrese un nombre para el horario"); return; }
      if (hora.length() != 5 || hora.charAt(2) != ':') { responderErrorJSON(422, "Formato HH:MM invalido"); return; }
      if (acc != "todo" && acc != "luces" && acc != "aire" && acc != "proyector") acc = "todo";

      HorarioRec &h = dbHorarios[dbHorariosCount++];
      h.id = dbNextHorarioId++;
      h.nombre = nom;
      h.hora = hora;
      h.dias = dias;
      h.accion = acc;
      h.activo = true;
      h.ultimaEjec = "";

      registrarEventoLocal("panel", "CREAR_HORARIO",
                           "Horario '" + nom + "' (" + hora + ", " + acc + ") creado por " + dbUsuarios[idxUsr].usuario, 0);
      guardarDB();
      responderOkMsg("Horario '" + nom + "' (" + hora + ") guardado correctamente");
      return;
    }

    int id = body["id"] | 0;
    int idxH = -1;
    for (int i = 0; i < dbHorariosCount; i++) if (dbHorarios[i].id == id) { idxH = i; break; }
    if (idxH < 0) { responderErrorJSON(404, "Horario no encontrado"); return; }

    if (op == "toggle") {
      dbHorarios[idxH].activo = !dbHorarios[idxH].activo;
      guardarDB();
      responderJSON(200, "{\"ok\":true,\"activo\":" + String(dbHorarios[idxH].activo ? 1 : 0) + "}");
      return;
    }
    if (op == "eliminar") {
      String nom = dbHorarios[idxH].nombre;
      for (int i = idxH; i < dbHorariosCount - 1; i++) dbHorarios[i] = dbHorarios[i + 1];
      dbHorariosCount--;
      registrarEventoLocal("panel", "ELIMINAR_HORARIO", "Horario '" + nom + "' eliminado", 0);
      guardarDB();
      responderOkMsg("Horario '" + nom + "' eliminado");
      return;
    }
    if (op == "ejecutar_ahora") {
      int af = apagarServicioGlobalLocal(dbHorarios[idxH].accion, "panel", dbUsuarios[idxUsr].usuario,
                                         "Ejecución manual de horario '" + dbHorarios[idxH].nombre + "'");
      responderOkMsg("Regla '" + dbHorarios[idxH].nombre + "' ejecutada en " + String(af) + " espacios");
      return;
    }
    responderErrorJSON(400, "Operacion no soportada");
  });

  // =========================================================================
  // 12. API ADMINISTRADORES / USUARIOS (GET y POST /api/usuarios.php)
  // =========================================================================
  servidorLocal.on("/api/usuarios.php", HTTP_GET, []() {
    SesionRec *ses = nullptr;
    int idxUsr = -1;
    if (!requerirAuthAPI(ses, idxUsr, true)) return;

    CA_JSON_DOC(doc, 4096);
    doc["ok"] = true;
    doc["total"] = dbUsuariosCount;
    JsonArray arr = CA_SET_ARR(doc, "usuarios");
    for (int i = 0; i < dbUsuariosCount; i++) {
      JsonObject o = CA_ADD_OBJ(arr);
      o["id"] = dbUsuarios[i].id;
      o["usuario"] = dbUsuarios[i].usuario;
      o["nombre_completo"] = dbUsuarios[i].nombreCompleto;
      o["rol"] = dbUsuarios[i].rol;
      o["aula_id"] = dbUsuarios[i].aulaId;
      o["activo"] = dbUsuarios[i].activo ? 1 : 0;
      o["aulas_permitidas"] = (dbUsuarios[i].rol == "admin") ? "todas" : ("[" + String(dbUsuarios[i].aulaId) + "]");
    }
    String out; serializeJson(doc, out);
    responderJSON(200, out);
  });

  servidorLocal.on("/api/usuarios.php", HTTP_POST, []() {
    SesionRec *ses = nullptr;
    int idxUsr = -1;
    if (!requerirAuthAPI(ses, idxUsr, true)) return;

    CA_JSON_DOC(body, 768);
    if (!parsearCuerpoJSON(body)) { responderErrorJSON(400, "JSON invalido"); return; }
    if (!validarCSRF(ses, body.as<JsonObjectConst>())) return;

    String op = toLowerTrim(body["operacion"] | "crear");
    int id = body["id"] | 0;
    int miId = dbUsuarios[idxUsr].id;

    if (op == "eliminar") {
      if (id <= 0 || id == miId) { responderErrorJSON(422, "No puede eliminar su propio usuario activo"); return; }
      int idxT = buscarIdxUsuario(id);
      if (idxT < 0) { responderErrorJSON(404, "Usuario no encontrado"); return; }
      String nom = dbUsuarios[idxT].usuario;
      for (int i = idxT; i < dbUsuariosCount - 1; i++) dbUsuarios[i] = dbUsuarios[i + 1];
      dbUsuariosCount--;
      registrarEventoLocal("panel", "ELIMINAR_USUARIO", "Usuario '" + nom + "' eliminado por " + dbUsuarios[idxUsr].usuario, 0);
      guardarDB();
      responderOkMsg("Usuario '" + nom + "' eliminado");
      return;
    }

    if (op == "toggle_activo") {
      if (id <= 0 || id == miId) { responderErrorJSON(422, "No puede desactivar su propio usuario activo"); return; }
      int idxT = buscarIdxUsuario(id);
      if (idxT < 0) { responderErrorJSON(404, "Usuario no encontrado"); return; }
      dbUsuarios[idxT].activo = !dbUsuarios[idxT].activo;
      guardarDB();
      responderJSON(200, "{\"ok\":true,\"activo\":" + String(dbUsuarios[idxT].activo ? 1 : 0) + "}");
      return;
    }

    String usr = body["usuario"] | ""; usr.trim();
    String nom = body["nombre_completo"] | ""; nom.trim();
    String pass = body["password"] | "";
    String rol = toLowerTrim(body["rol"] | "admin");
    if (nom.length() < 2) { responderErrorJSON(422, "Ingrese el nombre completo"); return; }

    if (id > 0) {
      int idxT = buscarIdxUsuario(id);
      if (idxT < 0) { responderErrorJSON(404, "Usuario no encontrado"); return; }
      if (usr.length() >= 3) dbUsuarios[idxT].usuario = usr;
      dbUsuarios[idxT].nombreCompleto = nom;
      if (pass.length() > 0) {
        if (pass.length() < 4) { responderErrorJSON(422, "La contraseña debe tener al menos 4 caracteres"); return; }
        dbUsuarios[idxT].passHash = sha256Salted(pass);
      }
      registrarEventoLocal("panel", "EDITAR_USUARIO", "Usuario '" + dbUsuarios[idxT].usuario + "' editado", 0);
      guardarDB();
      responderOkMsg("Usuario actualizado exitosamente");
      return;
    } else {
      if (dbUsuariosCount >= MAX_USUARIOS) { responderErrorJSON(422, "Limite maximo de usuarios alcanzado"); return; }
      if (usr.length() < 3) { responderErrorJSON(422, "Usuario invalido (minimo 3 caracteres)"); return; }
      if (pass.length() < 4) { responderErrorJSON(422, "Contraseña minima de 4 caracteres"); return; }
      for (int i = 0; i < dbUsuariosCount; i++) {
        if (toLowerTrim(dbUsuarios[i].usuario) == toLowerTrim(usr)) {
          responderErrorJSON(422, "El usuario '" + usr + "' ya existe");
          return;
        }
      }
      UsuarioRec &u = dbUsuarios[dbUsuariosCount++];
      u = UsuarioRec();
      u.id = dbNextUsuarioId++;
      u.aulaId = 0;
      u.usuario = usr;
      u.passHash = sha256Salted(pass);
      u.nombreCompleto = nom;
      u.rol = (rol == "admin" || rol == "aula") ? rol : "admin";
      u.activo = true;
      registrarEventoLocal("panel", "CREAR_USUARIO", "Administrador '" + usr + "' creado por " + dbUsuarios[idxUsr].usuario, 0);
      guardarDB();
      responderOkMsg("Usuario '" + usr + "' creado exitosamente");
      return;
    }
  });

  // =========================================================================
  // 13. API REGISTRO DE EVENTOS + EXPORTAR EXCEL CSV (GET /api/log.php)
  // =========================================================================
  servidorLocal.on("/api/log.php", HTTP_GET, []() {
    SesionRec *ses = nullptr;
    int idxUsr = -1;
    if (!requerirAuthAPI(ses, idxUsr, true)) return;

    bool exportCsv = servidorLocal.hasArg("export") && servidorLocal.arg("export").equalsIgnoreCase("csv");
    bool filtrarAula = servidorLocal.hasArg("aula_id") && servidorLocal.arg("aula_id").length() > 0;
    int aulaF = filtrarAula ? servidorLocal.arg("aula_id").toInt() : -1;
    String evF = servidorLocal.hasArg("evento") ? toLowerTrim(servidorLocal.arg("evento")) : "";
    String qF = servidorLocal.hasArg("q") ? toLowerTrim(servidorLocal.arg("q")) : "";

    if (exportCsv) {
      String csv = "\xEF\xBB\xBF";
      csv += "Fecha y Hora;Espacio / Aula;Origen;Evento;Detalle\r\n";
      for (int i = dbLogsCount - 1; i >= 0; i--) {
        LogRec &l = dbLogs[i];
        String aNom = (l.aulaId > 0) ? obtenerNombreAula(l.aulaId) : "General";
        if (filtrarAula && l.aulaId != aulaF) continue;
        if (evF.length() > 0 && toLowerTrim(l.evento).indexOf(evF) < 0) continue;
        if (qF.length() > 0 &&
            toLowerTrim(l.detalle).indexOf(qF) < 0 &&
            toLowerTrim(l.evento).indexOf(qF) < 0 &&
            toLowerTrim(aNom).indexOf(qF) < 0) continue;

        String det = l.detalle; det.replace("\"", "'");
        csv += "\"" + l.creadoEn + "\";\"" + aNom + "\";\"" + l.origen + "\";\"" + l.evento + "\";\"" + det + "\"\r\n";
      }
      servidorLocal.sendHeader("Content-Disposition", "attachment; filename=\"historial_control_aula.csv\"");
      servidorLocal.sendHeader("Cache-Control", "no-store");
      servidorLocal.send(200, "text/csv; charset=utf-8", csv);
      return;
    }

    CA_JSON_DOC(doc, 10240);
    doc["ok"] = true;
    JsonArray arr = CA_SET_ARR(doc, "eventos");
    int count = 0;
    for (int i = dbLogsCount - 1; i >= 0; i--) {
      LogRec &l = dbLogs[i];
      String aNom = (l.aulaId > 0) ? obtenerNombreAula(l.aulaId) : "";
      if (filtrarAula && l.aulaId != aulaF) continue;
      if (evF.length() > 0 && toLowerTrim(l.evento).indexOf(evF) < 0) continue;
      if (qF.length() > 0 &&
          toLowerTrim(l.detalle).indexOf(qF) < 0 &&
          toLowerTrim(l.evento).indexOf(qF) < 0 &&
          toLowerTrim(aNom).indexOf(qF) < 0) continue;

      JsonObject o = CA_ADD_OBJ(arr);
      o["id"] = l.id;
      o["aula_id"] = l.aulaId;
      o["aula_nombre"] = aNom;
      o["origen"] = l.origen;
      o["evento"] = l.evento;
      o["detalle"] = l.detalle;
      o["creado_en"] = l.creadoEn;
      count++;
    }
    doc["total"] = count;
    String out; serializeJson(doc, out);
    responderJSON(200, out);
  });

  // =========================================================================
  // 14. API MI PERFIL (GET y POST /api/perfil.php)
  // =========================================================================
  servidorLocal.on("/api/perfil.php", HTTP_POST, []() {
    SesionRec *ses = nullptr;
    int idxUsr = -1;
    if (!requerirAuthAPI(ses, idxUsr, false)) return;

    CA_JSON_DOC(body, 512);
    if (!parsearCuerpoJSON(body)) { responderErrorJSON(400, "JSON invalido"); return; }
    if (!validarCSRF(ses, body.as<JsonObjectConst>())) return;

    String op = toLowerTrim(body["operacion"] | "actualizar_perfil");
    UsuarioRec &u = dbUsuarios[idxUsr];

    if (op == "actualizar_perfil") {
      String nom = body["nombre_completo"] | ""; nom.trim();
      if (nom.length() < 2) { responderErrorJSON(422, "El nombre debe tener al menos 2 caracteres"); return; }
      u.nombreCompleto = nom;
      registrarEventoLocal("panel", "ACTUALIZAR_PERFIL", "Usuario '" + u.usuario + "' cambio su nombre a '" + nom + "'", u.aulaId);
      guardarDB();
      CA_JSON_DOC(res, 256);
      res["ok"] = true;
      res["mensaje"] = "Nombre actualizado correctamente";
      res["nombre_completo"] = nom;
      String out; serializeJson(res, out);
      responderJSON(200, out);
      return;
    }

    if (op == "cambiar_password") {
      String actual = body["password_actual"] | "";
      String nueva = body["password_nueva"] | "";
      String conf = body["password_confirmar"] | "";
      if (sha256Salted(actual) != u.passHash) {
        responderErrorJSON(422, "La contraseña actual es incorrecta");
        return;
      }
      if (nueva.length() < 4) {
        responderErrorJSON(422, "La nueva contraseña debe tener al menos 4 caracteres");
        return;
      }
      if (conf.length() > 0 && nueva != conf) {
        responderErrorJSON(422, "Las contraseñas nuevas no coinciden");
        return;
      }
      u.passHash = sha256Salted(nueva);
      registrarEventoLocal("panel", "CAMBIO_PASSWORD_PROPIO", "Usuario '" + u.usuario + "' cambio su contraseña", u.aulaId);
      guardarDB();
      responderJSON(200, "{\"ok\":true,\"mensaje\":\"Contraseña actualizada exitosamente\"}");
      return;
    }

    responderErrorJSON(400, "Operacion no reconocida");
  });

  // =========================================================================
  // 15. API PREFERENCIAS DE APARIENCIA (GET y POST /api/preferencias.php)
  // =========================================================================
  servidorLocal.on("/api/preferencias.php", HTTP_POST, []() {
    SesionRec *ses = nullptr;
    int idxUsr = -1;
    if (!requerirAuthAPI(ses, idxUsr, false)) return;

    CA_JSON_DOC(body, 1024);
    if (!parsearCuerpoJSON(body)) { responderErrorJSON(400, "JSON invalido"); return; }
    if (!validarCSRF(ses, body.as<JsonObjectConst>())) return;

    UsuarioRec &u = dbUsuarios[idxUsr];
    if ((body["reset"] | 0) == 1) {
      u.prefsJson = "";
    } else if (!body["preferencias"].isNull()) {
      String pStr;
      serializeJson(body["preferencias"], pStr);
      u.prefsJson = pStr;
    }
    guardarDB();
    responderJSON(200, "{\"ok\":true,\"mensaje\":\"Preferencias guardadas en el ESP32\"}");
  });

  // =========================================================================
  // 16. API CAPTURAS IR LOCALES (GET /api/capturas.php)
  // =========================================================================
  servidorLocal.on("/api/capturas.php", HTTP_GET, []() {
    SesionRec *ses = nullptr;
    int idxUsr = -1;
    if (!requerirAuthAPI(ses, idxUsr, true)) return;

    CA_JSON_DOC(doc, 2048);
    doc["ok"] = true;
    JsonArray arr = CA_SET_ARR(doc, "capturas");
    for (int i = dbCapturasCount - 1; i >= 0; i--) {
      JsonObject o = CA_ADD_OBJ(arr);
      o["id"] = dbCapturas[i].id;
      o["aula_id"] = dbCapturas[i].aulaId;
      o["aula_nombre"] = obtenerNombreAula(dbCapturas[i].aulaId);
      o["protocolo"] = dbCapturas[i].protocolo;
      o["valor"] = dbCapturas[i].valor;
      o["bits"] = dbCapturas[i].bits;
      o["creado_en"] = dbCapturas[i].creadoEn;
    }
    String out; serializeJson(doc, out);
    responderJSON(200, out);
  });

  // =========================================================================
  // 17. ENDPOINTS RAPIDOS DE DIAGNOSTICO / RETROCOMPATIBILIDAD
  // =========================================================================
  servidorLocal.on("/estado", HTTP_GET, []() {
    String json = "{\"ok\":true,\"modo\":\"localhost_esp32\",\"luces\":" + String(estadoLucesReal) +
                  ",\"ip\":\"" + obtenerIPActivaStr() + "\",\"ap_ip\":\"" + WiFi.softAPIP().toString() +
                  "\",\"rssi\":" + String(obtenerRSSIActivo()) + ",\"uptime\":" + String(millis() / 1000) +
                  ",\"heap\":" + String(ESP.getFreeHeap()) + ",\"version\":\"" + String(FIRMWARE_VERSION) + "\"}";
    responderJSON(200, json);
  });

  servidorLocal.on("/test_ir", HTTP_GET, []() {
    emitirRafagaDiagnostico();
    responderJSON(200, "{\"ok\":true,\"mensaje\":\"Rafaga IR completada (3 segundos)\"}");
  });

  // =========================================================================
  // 18. PORTAL CAUTIVO (ANDROID / iOS / WINDOWS) Y PRE-FLIGHT CORS
  // =========================================================================
  auto portalRedirect = []() {
    redirigirA("http://" + WiFi.softAPIP().toString() + "/login.php");
  };
  servidorLocal.on("/generate_204", HTTP_GET, portalRedirect);
  servidorLocal.on("/gen_204", HTTP_GET, portalRedirect);
  servidorLocal.on("/hotspot-detect.html", HTTP_GET, portalRedirect);
  servidorLocal.on("/connecttest.txt", HTTP_GET, portalRedirect);
  servidorLocal.on("/ncsi.txt", HTTP_GET, portalRedirect);
  servidorLocal.on("/fwlink", HTTP_GET, portalRedirect);

  servidorLocal.onNotFound([]() {
    if (servidorLocal.method() == HTTP_OPTIONS) {
      responderJSON(200, "{}");
      return;
    }
    String uri = servidorLocal.uri();
    if (uri.startsWith("/api/")) {
      responderErrorJSON(404, "Endpoint no encontrado: " + uri);
      return;
    }
    // Redirigir cualquier ruta desconocida (ideal para portal cautivo en el AP)
    redirigirA("/login.php");
  });
}
