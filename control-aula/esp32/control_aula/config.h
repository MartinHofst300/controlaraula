#ifndef CONFIG_H
#define CONFIG_H

// =============================================================================
// PROYECTO: Control de Aula — MODO 100% LOCALHOST (STANDALONE EN ESP32)
// ARCHIVO: config.h
// Sin dependencia de servidor VPS, Docker ni Internet:
//   - Toda la interfaz web (login, panel docente y panel admin) vive en Flash
//   - Toda la base de datos (aulas, usuarios, horarios, tipos, prefs) vive en LittleFS
//   - Funciona tanto en tu red WiFi como creando su propia red WiFi (AP)
// =============================================================================

// -----------------------------------------------------------------------------
// 1. PUNTO DE ACCESO PROPIO DEL ESP32 (SIEMPRE DISPONIBLE PARA LA EXPOSICION)
// Conectate desde cualquier celular/notebook a esta red WiFi y abri:
//   http://192.168.4.1   o   http://controlaula.local
// -----------------------------------------------------------------------------
#define AP_SSID           "ControlAula"
#define AP_PASS           "12345678"
#define MDNS_HOST         "controlaula"
#define PUERTO_LOCAL_HTTP 80

// -----------------------------------------------------------------------------
// 2. CREDENCIALES DE RED WIFI CLIENTE (OPCIONAL - MODO DUAL AP + STA)
// Si alguna de estas redes esta cerca, el ESP32 tambien se conecta y te da
// una IP en esa red (y sincroniza hora NTP automaticamente si hay Internet).
// -----------------------------------------------------------------------------
#define WIFI_SSID         "Galaxydetincho"
#define WIFI_PASS         "12345678"
#define WIFI_PASSWORD     WIFI_PASS

#define WIFI_SSID_2       "Clarowifi"
#define WIFI_PASS_2       "Hofstetter07"

#define WIFI_SSID_3       "LSEET1"
#define WIFI_PASS_3       "EdujhSqul9eh"

#define WIFI_SSID_4       "Maestros"
#define WIFI_PASS_4       "Docentes_2025"

#define WIFI_SSID_5       "Estudiantes"
#define WIFI_PASS_5       "Escuelas_2025"

#define WIFI_SSID_6       "Vllamada"
#define WIFI_PASS_6       "Video_2025"

// -----------------------------------------------------------------------------
// 3. IDENTIDAD DEL AULA FISICA CONECTADA A ESTE ESP32
// -----------------------------------------------------------------------------
#define AULA_ID           1
#define FIRMWARE_VERSION  "3.0.0-LOCALHOST"

// Zona horaria Argentina (UTC-3 = -10800 segundos)
#define GMT_OFFSET_SEC    (-3 * 3600)
#define DAYLIGHT_OFFSET   0

// -----------------------------------------------------------------------------
// 4. DEFINICION DE PINES DE HARDWARE (ESP32 DevKit V1 / NodeMCU-32S)
// -----------------------------------------------------------------------------
#define PIN_RELE_LUCES    26    // Rele de luces (GPIO 26 - Activo en ALTO / HIGH)
#define PIN_IR_SEND       18    // Emisor Infrarrojo (Transistor BC337 + LEDs IR en GPIO 18)
#define PIN_IR_RECV       15    // Receptor Infrarrojo KY-022 (GPIO 15)
#define PIN_LED_ESTADO    2     // LED integrado indicador de estado (GPIO 2)

// -----------------------------------------------------------------------------
// 5. CODIGOS IR INTEGRADOS (Epson Proyector + BGH / Star AC Coolix)
// -----------------------------------------------------------------------------
// Codigos Epson (Protocolo NEC 32-bit / 38 kHz - Prefijo 0xC1AA MSB-first)
#define CODIGO_PROYECTOR_PREAMBULO_DEF  0x81C00FF0  // Epson NEC: Preambulo del control
#define CODIGO_PROYECTOR_PRENDER_DEF    0xC1AA09F6  // Epson NEC: Encender
#define CODIGO_PROYECTOR_APAGAR_DEF     0xC1AA09F6  // Epson NEC: Apagar (2 pulsos con pausa de 2s)
#define CODIGO_PROYECTOR_HDMI1_DEF      0xC1AA9B64  // Epson: Entrada HDMI 1
#define CODIGO_PROYECTOR_HDMI2_DEF      0xC1AA0CF3  // Epson: Entrada HDMI 2 / Source
#define CODIGO_PROYECTOR_VGA_DEF        0xC1AAA659  // Epson: Entrada Computer / VGA
#define CODIGO_PROYECTOR_FREEZE_DEF     0xC1AA49B6  // Epson: Congelar imagen Freeze
#define CODIGO_PROYECTOR_MUTE_DEF       0xC1AAC936  // Epson: A/V Mute Pantalla en negro
#define CODIGO_PROYECTOR_VOL_MAS_DEF    0xC1AA19E6  // Epson: Subir volumen
#define CODIGO_PROYECTOR_VOL_MENOS_DEF  0xC1AA9966  // Epson: Bajar volumen
#define CODIGO_PROYECTOR_VOL_MAS_ALT    0xC1AA4AB5  // Epson alternativo: Subir volumen
#define CODIGO_PROYECTOR_VOL_MENOS_ALT  0xC1AA4EB1  // Epson alternativo: Bajar volumen
#define CODIGO_PROYECTOR_ENTER_DEF      0xC1AA0DF2  // Epson: Enter / Auto

#define REPETICIONES_PROYECTOR_APAGAR   2
#define PAUSA_PROYECTOR_APAGAR_MS       2000

// Codigos Aire BGH / Star (Protocolo COOLIX 24-bit / 38 kHz)
#define CODIGO_STAR_AIRE_ON_DEF         0xB23F40    // BGH Coolix: Encender
#define CODIGO_STAR_AIRE_OFF_DEF        0xB27BE0    // BGH Coolix: Apagar

// Retrocompatibilidad
#define CODIGO_IR_PRENDER_DEF           CODIGO_STAR_AIRE_ON_DEF
#define CODIGO_IR_APAGAR_DEF            CODIGO_STAR_AIRE_OFF_DEF

#endif // CONFIG_H
