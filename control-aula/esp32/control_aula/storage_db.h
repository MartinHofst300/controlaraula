#ifndef STORAGE_DB_H
#define STORAGE_DB_H

#include <Arduino.h>
#include <ArduinoJson.h>
#include <LittleFS.h>
#include <limits.h>
#include <mbedtls/md.h>
#include <sys/time.h>
#include <time.h>
#include "config.h"

// Compatibilidad transparente ArduinoJson v6 y v7
#if ARDUINOJSON_VERSION_MAJOR >= 7
  #define CA_JSON_DOC(name, cap) JsonDocument name
  #define CA_ADD_OBJ(arr) ((arr).add<JsonObject>())
  #define CA_SET_OBJ(obj, k) ((obj)[k].to<JsonObject>())
  #define CA_SET_ARR(obj, k) ((obj)[k].to<JsonArray>())
#else
  #define CA_JSON_DOC(name, cap) DynamicJsonDocument name(cap)
  #define CA_ADD_OBJ(arr) ((arr).createNestedObject())
  #define CA_SET_OBJ(obj, k) ((obj).createNestedObject(k))
  #define CA_SET_ARR(obj, k) ((obj).createNestedArray(k))
#endif

// Limites seguros de memoria RAM/Flash para ESP32
#define MAX_AULAS       12
#define MAX_TIPOS       16
#define MAX_HORARIOS    16
#define MAX_USUARIOS    20
#define MAX_LOGS        60
#define MAX_LOGS_FLASH  30
#define MAX_CAPTURAS    12
#define MAX_SESIONES    12

#define DB_FILE_PATH    "/db.json"

struct AulaRec {
  int id = 0;
  String nombre;
  String tipo = "Aula";
  String ubicacion = "";
  bool activa = true;
  String marcaAire = "Star (OEM)";
  String protoAire = "COOLIX";
  String marcaProy = "Epson";
  String protoProy = "EPSON_NEC";
  int luces = 0;
  int aireEnc = 0;
  int aireTemp = 24;
  String aireModo = "frio";
  String aireFan = "auto";
  int aireSwing = 0;
  int proyEnc = 0;
  String proyFuente = "hdmi1";
  int proyFreeze = 0;
  int proyMute = 0;
  int proyVol = 50;
};

struct TipoRec {
  int id = 0;
  String nombre;
  String icono = "bi-door-open";
};

struct HorarioRec {
  int id = 0;
  String nombre;
  String hora = "18:30";
  String dias = "1,2,3,4,5";
  String accion = "todo";
  bool activo = true;
  String ultimaEjec = ""; // YYYY-MM-DD
};

struct UsuarioRec {
  int id = 0;
  int aulaId = 0; // 0 = sin aula fija (admin)
  String usuario;
  String passHash;
  String nombreCompleto;
  String rol = "aula"; // "admin" | "aula"
  bool activo = true;
  String prefsJson = "";
};

struct LogRec {
  int id = 0;
  int aulaId = 0;
  String origen;
  String evento;
  String detalle;
  String creadoEn;
};

struct CapturaRec {
  int id = 0;
  int aulaId = AULA_ID;
  String protocolo;
  String valor;
  int bits = 0;
  String creadoEn;
};

struct SesionRec {
  bool activa = false;
  String sid;
  String csrf;
  int usuarioId = 0;
  unsigned long ultimoUsoMs = 0;
};

// Estado global en RAM
static AulaRec     dbAulas[MAX_AULAS];
static int         dbAulasCount = 0;
static int         dbNextAulaId = 4;

static TipoRec     dbTipos[MAX_TIPOS];
static int         dbTiposCount = 0;
static int         dbNextTipoId = 7;

static HorarioRec  dbHorarios[MAX_HORARIOS];
static int         dbHorariosCount = 0;
static int         dbNextHorarioId = 1;

static UsuarioRec  dbUsuarios[MAX_USUARIOS];
static int         dbUsuariosCount = 0;
static int         dbNextUsuarioId = 5;

static LogRec      dbLogs[MAX_LOGS];
static int         dbLogsCount = 0;
static int         dbNextLogId = 1;

static CapturaRec  dbCapturas[MAX_CAPTURAS];
static int         dbCapturasCount = 0;
static int         dbNextCapturaId = 1;

static SesionRec   dbSesiones[MAX_SESIONES];

static bool        dbLittleFsOk = false;
static bool        dbHoraSincronizada = false;

// Callback opcional para pausar el receptor IR durante escritura en Flash
static void (*dbBeforeFlashWriteCb)() = nullptr;
static void (*dbAfterFlashWriteCb)() = nullptr;

// -----------------------------------------------------------------------------
// UTILIDADES DE SEGURIDAD, TEXTO Y RELOJ
// -----------------------------------------------------------------------------
inline String sha256Salted(const String &plain) {
  String salted = "CA_ESP32_SALT_v3:" + plain;
  uint8_t out[32];
  mbedtls_md_context_t ctx;
  mbedtls_md_init(&ctx);
  mbedtls_md_setup(&ctx, mbedtls_md_info_from_type(MBEDTLS_MD_SHA256), 0);
  mbedtls_md_starts(&ctx);
  mbedtls_md_update(&ctx, (const unsigned char *)salted.c_str(), salted.length());
  mbedtls_md_finish(&ctx, out);
  mbedtls_md_free(&ctx);

  char hex[65];
  for (int i = 0; i < 32; i++) {
    sprintf(hex + i * 2, "%02x", out[i]);
  }
  hex[64] = '\0';
  return String(hex);
}

inline String generarTokenHex(int bytesLen = 16) {
  String out;
  out.reserve(bytesLen * 2);
  char buf[9];
  int generated = 0;
  while (generated < bytesLen) {
    uint32_t r = esp_random();
    sprintf(buf, "%08lx", (unsigned long)r);
    int take = min(8, (bytesLen - generated) * 2);
    for (int i = 0; i < take; i++) out += buf[i];
    generated += take / 2;
  }
  return out;
}

inline String normalizarCompacto(const String &s) {
  String res;
  res.reserve(s.length());
  for (unsigned int i = 0; i < s.length(); i++) {
    char c = s.charAt(i);
    if (c == ' ' || c == '\t' || c == '\r' || c == '\n') continue;
    res += (char)tolower((unsigned char)c);
  }
  return res;
}

inline String toLowerTrim(const String &s) {
  String r = s;
  r.trim();
  r.toLowerCase();
  return r;
}

inline void configurarZonaHorariaAR() {
  setenv("TZ", "ART3", 1);
  tzset();
}

inline void sincronizarHoraDesdeUnix(long epochSec) {
  if (epochSec < 1700000000L) return;
  time_t ahora = time(nullptr);
  // Actualizar si nunca se sincronizo o si hay mas de 60s de diferencia
  if (ahora < 1700000000L || labs((long)ahora - epochSec) > 60L) {
    struct timeval tv;
    tv.tv_sec = (time_t)epochSec;
    tv.tv_usec = 0;
    settimeofday(&tv, nullptr);
    configurarZonaHorariaAR();
    dbHoraSincronizada = true;
  }
}

inline bool obtenerTmLocal(struct tm &tmOut) {
  time_t now = time(nullptr);
  if (now < 1700000000L) return false;
  localtime_r(&now, &tmOut);
  dbHoraSincronizada = true;
  return true;
}

inline String obtenerFechaHoraISO() {
  struct tm t;
  if (obtenerTmLocal(t)) {
    char buf[24];
    snprintf(buf, sizeof(buf), "%04d-%02d-%02d %02d:%02d:%02d",
             t.tm_year + 1900, t.tm_mon + 1, t.tm_mday,
             t.tm_hour, t.tm_min, t.tm_sec);
    return String(buf);
  }
  unsigned long s = millis() / 1000;
  char buf[24];
  snprintf(buf, sizeof(buf), "2026-01-01 %02lu:%02lu:%02lu",
           (s / 3600) % 24, (s / 60) % 60, s % 60);
  return String(buf);
}

inline String obtenerHoraHHMM() {
  struct tm t;
  if (obtenerTmLocal(t)) {
    char buf[8];
    snprintf(buf, sizeof(buf), "%02d:%02d", t.tm_hour, t.tm_min);
    return String(buf);
  }
  return "--:--";
}

inline String obtenerFechaYYYYMMDD() {
  struct tm t;
  if (obtenerTmLocal(t)) {
    char buf[12];
    snprintf(buf, sizeof(buf), "%04d-%02d-%02d",
             t.tm_year + 1900, t.tm_mon + 1, t.tm_mday);
    return String(buf);
  }
  return "";
}

// Lunes=1 ... Domingo=7 (igual que PHP date('N'))
inline int obtenerDiaSemanaISO() {
  struct tm t;
  if (!obtenerTmLocal(t)) return 0;
  return (t.tm_wday == 0) ? 7 : t.tm_wday;
}

// -----------------------------------------------------------------------------
// BUSQUEDAS EN MEMORIA
// -----------------------------------------------------------------------------
inline int buscarIdxAula(int id) {
  for (int i = 0; i < dbAulasCount; i++) {
    if (dbAulas[i].id == id) return i;
  }
  return -1;
}

inline int buscarIdxUsuario(int id) {
  for (int i = 0; i < dbUsuariosCount; i++) {
    if (dbUsuarios[i].id == id) return i;
  }
  return -1;
}

inline int buscarIdxUsuarioPorAula(int aulaId) {
  for (int i = 0; i < dbUsuariosCount; i++) {
    if (dbUsuarios[i].aulaId == aulaId && dbUsuarios[i].rol == "aula") return i;
  }
  return -1;
}

inline String obtenerNombreAula(int aulaId) {
  if (aulaId <= 0) return "";
  int idx = buscarIdxAula(aulaId);
  return (idx >= 0) ? dbAulas[idx].nombre : "";
}

// -----------------------------------------------------------------------------
// REGISTRO DE EVENTOS Y CAPTURAS IR
// -----------------------------------------------------------------------------
inline void registrarEventoLocal(const String &origen, const String &evento,
                                 const String &detalle, int aulaId = 0) {
  if (dbLogsCount >= MAX_LOGS) {
    for (int i = 0; i < MAX_LOGS - 1; i++) {
      dbLogs[i] = dbLogs[i + 1];
    }
    dbLogsCount = MAX_LOGS - 1;
  }
  LogRec &r = dbLogs[dbLogsCount++];
  r.id = dbNextLogId++;
  r.aulaId = aulaId;
  r.origen = origen;
  r.evento = evento;
  r.detalle = detalle;
  r.creadoEn = obtenerFechaHoraISO();
}

inline void registrarCapturaIRLocal(const String &proto, const String &valor, int bits) {
  if (dbCapturasCount >= MAX_CAPTURAS) {
    for (int i = 0; i < MAX_CAPTURAS - 1; i++) {
      dbCapturas[i] = dbCapturas[i + 1];
    }
    dbCapturasCount = MAX_CAPTURAS - 1;
  }
  CapturaRec &c = dbCapturas[dbCapturasCount++];
  c.id = dbNextCapturaId++;
  c.aulaId = AULA_ID;
  c.protocolo = proto;
  c.valor = valor;
  c.bits = bits;
  c.creadoEn = obtenerFechaHoraISO();
}

// -----------------------------------------------------------------------------
// SESIONES EN MEMORIA (COOKIES)
// -----------------------------------------------------------------------------
inline SesionRec *buscarSesionPorSid(const String &sid) {
  if (sid.length() < 8) return nullptr;
  for (int i = 0; i < MAX_SESIONES; i++) {
    if (dbSesiones[i].activa && dbSesiones[i].sid == sid) {
      dbSesiones[i].ultimoUsoMs = millis();
      return &dbSesiones[i];
    }
  }
  return nullptr;
}

inline SesionRec *crearSesion(int usuarioId) {
  int slot = -1;
  unsigned long oldest = ULONG_MAX;
  for (int i = 0; i < MAX_SESIONES; i++) {
    if (!dbSesiones[i].activa) {
      slot = i;
      break;
    }
    if (dbSesiones[i].ultimoUsoMs < oldest) {
      oldest = dbSesiones[i].ultimoUsoMs;
      slot = i;
    }
  }
  if (slot < 0) slot = 0;
  dbSesiones[slot].activa = true;
  dbSesiones[slot].sid = generarTokenHex(16);
  dbSesiones[slot].csrf = generarTokenHex(16);
  dbSesiones[slot].usuarioId = usuarioId;
  dbSesiones[slot].ultimoUsoMs = millis();
  return &dbSesiones[slot];
}

inline void destruirSesion(const String &sid) {
  for (int i = 0; i < MAX_SESIONES; i++) {
    if (dbSesiones[i].activa && dbSesiones[i].sid == sid) {
      dbSesiones[i].activa = false;
      dbSesiones[i].sid = "";
      dbSesiones[i].csrf = "";
    }
  }
}

// -----------------------------------------------------------------------------
// AUTENTICACION DE CREDENCIALES
// -----------------------------------------------------------------------------
inline int autenticarUsuarioLocal(const String &userIn, const String &passIn, String &errorOut) {
  String normIn = normalizarCompacto(userIn);
  String lowerIn = toLowerTrim(userIn);
  if (normIn.length() == 0) {
    errorOut = "Ingrese usuario y contraseña.";
    return -1;
  }

  String hashIn = sha256Salted(passIn);

  for (int i = 0; i < dbUsuariosCount; i++) {
    UsuarioRec &u = dbUsuarios[i];
    if (toLowerTrim(u.usuario) == lowerIn ||
        toLowerTrim(u.nombreCompleto) == lowerIn ||
        normalizarCompacto(u.usuario) == normIn ||
        normalizarCompacto(u.nombreCompleto) == normIn) {
      if (!u.activo) {
        errorOut = "Esta cuenta de usuario se encuentra desactivada. Contacte al administrador.";
        return -1;
      }
      if (u.passHash == hashIn) {
        return i;
      }
      errorOut = "Credenciales incorrectas. Verifique usuario y contraseña.";
      return -1;
    }
  }
  errorOut = "Credenciales incorrectas. Verifique usuario y contraseña.";
  return -1;
}

// -----------------------------------------------------------------------------
// SEMILLA DE DATOS POR DEFECTO (IGUAL A schema.sql)
// -----------------------------------------------------------------------------
inline void sembrarDatosPorDefecto() {
  dbAulasCount = 0;
  dbTiposCount = 0;
  dbHorariosCount = 0;
  dbUsuariosCount = 0;
  dbLogsCount = 0;

  // 3 Espacios iniciales
  {
    AulaRec &a1 = dbAulas[dbAulasCount++];
    a1.id = 1; a1.nombre = "Aula 101"; a1.tipo = "Aula"; a1.activa = true;
    a1.aireTemp = 24; a1.aireModo = "frio"; a1.aireFan = "auto"; a1.proyFuente = "hdmi1"; a1.proyVol = 50;

    AulaRec &a2 = dbAulas[dbAulasCount++];
    a2.id = 2; a2.nombre = "Laboratorio 1 - Informática"; a2.tipo = "Laboratorio"; a2.activa = true;
    a2.aireTemp = 22; a2.aireModo = "frio"; a2.aireFan = "medio"; a2.proyFuente = "hdmi1"; a2.proyVol = 60;

    AulaRec &a3 = dbAulas[dbAulasCount++];
    a3.id = 3; a3.nombre = "Taller de Electrónica"; a3.tipo = "Taller"; a3.activa = true;
    a3.aireTemp = 25; a3.aireModo = "frio"; a3.aireFan = "auto"; a3.proyFuente = "hdmi2"; a3.proyVol = 40;
    dbNextAulaId = 4;
  }

  // 6 Tipos de espacio iniciales
  const char *tNom[] = {"Aula", "Laboratorio", "Taller", "SUM", "Biblioteca", "Otro"};
  const char *tIco[] = {"bi-door-open", "bi-cpu", "bi-tools", "bi-people", "bi-book", "bi-building"};
  for (int i = 0; i < 6; i++) {
    TipoRec &t = dbTipos[dbTiposCount++];
    t.id = i + 1;
    t.nombre = tNom[i];
    t.icono = tIco[i];
  }
  dbNextTipoId = 7;

  // 4 Usuarios iniciales (mismas credenciales del proyecto)
  {
    UsuarioRec &u1 = dbUsuarios[dbUsuariosCount++];
    u1.id = 1; u1.aulaId = 0; u1.usuario = "admin"; u1.passHash = sha256Salted("admin123");
    u1.nombreCompleto = "Administrador General"; u1.rol = "admin"; u1.activo = true;

    UsuarioRec &u2 = dbUsuarios[dbUsuariosCount++];
    u2.id = 2; u2.aulaId = 1; u2.usuario = "aula101"; u2.passHash = sha256Salted("aula123");
    u2.nombreCompleto = "Aula 101"; u2.rol = "aula"; u2.activo = true;

    UsuarioRec &u3 = dbUsuarios[dbUsuariosCount++];
    u3.id = 3; u3.aulaId = 2; u3.usuario = "lab1"; u3.passHash = sha256Salted("lab123");
    u3.nombreCompleto = "Laboratorio 1 - Informática"; u3.rol = "aula"; u3.activo = true;

    UsuarioRec &u4 = dbUsuarios[dbUsuariosCount++];
    u4.id = 4; u4.aulaId = 3; u4.usuario = "taller1"; u4.passHash = sha256Salted("taller123");
    u4.nombreCompleto = "Taller de Electrónica"; u4.rol = "aula"; u4.activo = true;
    dbNextUsuarioId = 5;
  }

  dbNextHorarioId = 1;
  dbNextLogId = 1;
  registrarEventoLocal("sistema", "INICIO_ESP32", "Sistema Control de Aula inicializado en modo Localhost ESP32", AULA_ID);
}

// -----------------------------------------------------------------------------
// PERSISTENCIA EN LITTLEFS (/db.json)
// -----------------------------------------------------------------------------
inline bool guardarDB() {
  if (!dbLittleFsOk) return false;
  if (dbBeforeFlashWriteCb) dbBeforeFlashWriteCb();

  CA_JSON_DOC(doc, 24576);
  doc["na"] = dbNextAulaId;
  doc["nt"] = dbNextTipoId;
  doc["nh"] = dbNextHorarioId;
  doc["nu"] = dbNextUsuarioId;
  doc["nl"] = dbNextLogId;

  JsonArray arrA = CA_SET_ARR(doc, "aulas");
  for (int i = 0; i < dbAulasCount; i++) {
    JsonObject o = CA_ADD_OBJ(arrA);
    o["id"] = dbAulas[i].id;
    o["n"]  = dbAulas[i].nombre;
    o["t"]  = dbAulas[i].tipo;
    o["ac"] = dbAulas[i].activa ? 1 : 0;
    o["at"] = dbAulas[i].aireTemp;
    o["am"] = dbAulas[i].aireModo;
    o["af"] = dbAulas[i].aireFan;
    o["pf"] = dbAulas[i].proyFuente;
    o["pv"] = dbAulas[i].proyVol;
  }

  JsonArray arrT = CA_SET_ARR(doc, "tipos");
  for (int i = 0; i < dbTiposCount; i++) {
    JsonObject o = CA_ADD_OBJ(arrT);
    o["id"] = dbTipos[i].id;
    o["n"]  = dbTipos[i].nombre;
    o["ic"] = dbTipos[i].icono;
  }

  JsonArray arrH = CA_SET_ARR(doc, "horarios");
  for (int i = 0; i < dbHorariosCount; i++) {
    JsonObject o = CA_ADD_OBJ(arrH);
    o["id"] = dbHorarios[i].id;
    o["n"]  = dbHorarios[i].nombre;
    o["h"]  = dbHorarios[i].hora;
    o["d"]  = dbHorarios[i].dias;
    o["a"]  = dbHorarios[i].accion;
    o["ac"] = dbHorarios[i].activo ? 1 : 0;
    o["ue"] = dbHorarios[i].ultimaEjec;
  }

  JsonArray arrU = CA_SET_ARR(doc, "usuarios");
  for (int i = 0; i < dbUsuariosCount; i++) {
    JsonObject o = CA_ADD_OBJ(arrU);
    o["id"] = dbUsuarios[i].id;
    o["ai"] = dbUsuarios[i].aulaId;
    o["u"]  = dbUsuarios[i].usuario;
    o["p"]  = dbUsuarios[i].passHash;
    o["nc"] = dbUsuarios[i].nombreCompleto;
    o["r"]  = dbUsuarios[i].rol;
    o["ac"] = dbUsuarios[i].activo ? 1 : 0;
    if (dbUsuarios[i].prefsJson.length() > 0) {
      o["pr"] = dbUsuarios[i].prefsJson;
    }
  }

  JsonArray arrL = CA_SET_ARR(doc, "logs");
  int startLog = max(0, dbLogsCount - MAX_LOGS_FLASH);
  for (int i = startLog; i < dbLogsCount; i++) {
    JsonObject o = CA_ADD_OBJ(arrL);
    o["id"] = dbLogs[i].id;
    o["ai"] = dbLogs[i].aulaId;
    o["o"]  = dbLogs[i].origen;
    o["e"]  = dbLogs[i].evento;
    o["d"]  = dbLogs[i].detalle;
    o["c"]  = dbLogs[i].creadoEn;
  }

  File f = LittleFS.open(DB_FILE_PATH, "w");
  bool ok = false;
  if (f) {
    serializeJson(doc, f);
    f.close();
    ok = true;
  }

  if (dbAfterFlashWriteCb) dbAfterFlashWriteCb();
  return ok;
}

inline void iniciarBaseDeDatos() {
  dbLittleFsOk = LittleFS.begin(true); // Formatea automaticamente la primera vez
  if (!dbLittleFsOk) {
    Serial.println(F("[DB] Advertencia: LittleFS no disponible, usando solo RAM."));
    sembrarDatosPorDefecto();
    return;
  }

  if (!LittleFS.exists(DB_FILE_PATH)) {
    Serial.println(F("[DB] Primera ejecucion: creando base de datos inicial en LittleFS..."));
    sembrarDatosPorDefecto();
    guardarDB();
    return;
  }

  File f = LittleFS.open(DB_FILE_PATH, "r");
  if (!f || f.size() == 0) {
    if (f) f.close();
    sembrarDatosPorDefecto();
    guardarDB();
    return;
  }

  CA_JSON_DOC(doc, 24576);
  DeserializationError err = deserializeJson(doc, f);
  f.close();
  if (err) {
    Serial.print(F("[DB] Error leyendo db.json, restaurando valores por defecto: "));
    Serial.println(err.c_str());
    sembrarDatosPorDefecto();
    guardarDB();
    return;
  }

  dbNextAulaId    = doc["na"] | 4;
  dbNextTipoId    = doc["nt"] | 7;
  dbNextHorarioId = doc["nh"] | 1;
  dbNextUsuarioId = doc["nu"] | 5;
  dbNextLogId     = doc["nl"] | 1;

  dbAulasCount = 0;
  for (JsonObject o : doc["aulas"].as<JsonArray>()) {
    if (dbAulasCount >= MAX_AULAS) break;
    AulaRec &a = dbAulas[dbAulasCount++];
    a.id         = o["id"] | (dbAulasCount);
    a.nombre     = o["n"]  | "Aula";
    a.tipo       = o["t"]  | "Aula";
    a.activa     = (o["ac"] | 1) == 1;
    a.aireTemp   = o["at"] | 24;
    a.aireModo   = o["am"] | "frio";
    a.aireFan    = o["af"] | "auto";
    a.proyFuente = o["pf"] | "hdmi1";
    a.proyVol    = o["pv"] | 50;
    // Por seguridad al reiniciar el ESP32, luces y equipos arrancan apagados
    a.luces = 0; a.aireEnc = 0; a.proyEnc = 0; a.proyFreeze = 0; a.proyMute = 0;
  }

  dbTiposCount = 0;
  for (JsonObject o : doc["tipos"].as<JsonArray>()) {
    if (dbTiposCount >= MAX_TIPOS) break;
    TipoRec &t = dbTipos[dbTiposCount++];
    t.id     = o["id"] | dbTiposCount;
    t.nombre = o["n"]  | "Aula";
    t.icono  = o["ic"] | "bi-door-open";
  }

  dbHorariosCount = 0;
  for (JsonObject o : doc["horarios"].as<JsonArray>()) {
    if (dbHorariosCount >= MAX_HORARIOS) break;
    HorarioRec &h = dbHorarios[dbHorariosCount++];
    h.id         = o["id"] | dbHorariosCount;
    h.nombre     = o["n"]  | "Horario";
    h.hora       = o["h"]  | "18:30";
    h.dias       = o["d"]  | "1,2,3,4,5";
    h.accion     = o["a"]  | "todo";
    h.activo     = (o["ac"] | 1) == 1;
    h.ultimaEjec = o["ue"] | "";
  }

  dbUsuariosCount = 0;
  for (JsonObject o : doc["usuarios"].as<JsonArray>()) {
    if (dbUsuariosCount >= MAX_USUARIOS) break;
    UsuarioRec &u = dbUsuarios[dbUsuariosCount++];
    u.id             = o["id"] | dbUsuariosCount;
    u.aulaId         = o["ai"] | 0;
    u.usuario        = o["u"]  | "";
    u.passHash       = o["p"]  | "";
    u.nombreCompleto = o["nc"] | "";
    u.rol            = o["r"]  | "aula";
    u.activo         = (o["ac"] | 1) == 1;
    u.prefsJson      = o["pr"] | "";
  }

  dbLogsCount = 0;
  for (JsonObject o : doc["logs"].as<JsonArray>()) {
    if (dbLogsCount >= MAX_LOGS) break;
    LogRec &l = dbLogs[dbLogsCount++];
    l.id       = o["id"] | dbLogsCount;
    l.aulaId   = o["ai"] | 0;
    l.origen   = o["o"]  | "sistema";
    l.evento   = o["e"]  | "";
    l.detalle  = o["d"]  | "";
    l.creadoEn = o["c"]  | "";
  }

  // Asegurar integridad minima (al menos 1 aula y 1 admin)
  if (dbAulasCount == 0 || dbUsuariosCount == 0) {
    sembrarDatosPorDefecto();
    guardarDB();
  } else {
    registrarEventoLocal("sistema", "REINICIO_ESP32", "ESP32 encendido y base local cargada desde LittleFS", AULA_ID);
    Serial.printf("[DB] Cargada desde LittleFS: %d aulas, %d usuarios, %d horarios, %d logs\n",
                  dbAulasCount, dbUsuariosCount, dbHorariosCount, dbLogsCount);
  }
}

#endif // STORAGE_DB_H
