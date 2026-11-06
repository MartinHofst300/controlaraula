#ifndef CONFIG_H
#define CONFIG_H

// =============================================================================
// PROYECTO: Control de Aula
// ARCHIVO: config.h
// Configuracion de credenciales, pines y parametros operativos del ESP32
// =============================================================================

// -----------------------------------------------------------------------------
// 1. CREDENCIALES DE RED WIFI
// Ingrese aqui el nombre (SSID) y clave de su red WiFi (o hotspot del celular)
// -----------------------------------------------------------------------------
// Red Principal (Hotspot / WiFi actual)
#define WIFI_SSID       "Galaxydetincho"
#define WIFI_PASS       "12345678"
#define WIFI_PASSWORD   WIFI_PASS // Alias de compatibilidad

// Red Secundaria de Respaldo
#define WIFI_SSID_2     "Clarowifi"
#define WIFI_PASS_2     "Hofstetter07"

// -----------------------------------------------------------------------------
// 2. ENDPOINTS Y SERVIDOR EN PRODUCCION (VPS DOCKER)
// Servidor VPS sin dominio por IP y puerto 8085 (DocumentRoot apunta directo a public)
// -----------------------------------------------------------------------------
#define SERVER_BASE_URL   "http://40.160.138.65:8085"
#define URL_SINCRONIZAR   "http://40.160.138.65:8085/api/esp32/sincronizar.php"
#define URL_REPORTAR      "http://40.160.138.65:8085/api/esp32/reportar.php"
#define URL_CAPTURA       "http://40.160.138.65:8085/api/esp32/captura.php"
#define URL_SERVIDOR      SERVER_BASE_URL // Alias de compatibilidad

// -----------------------------------------------------------------------------
// 3. AUTENTICACION (API KEY AULA #1 - AULA 101 GENERAL)
// Debe coincidir con api_key_esp32 del Aula 1 en la base de datos MySQL
// -----------------------------------------------------------------------------
#define API_KEY           "clave_secreta_esp32_aula_101_general_abc12345"
#define AULA_ID           1

// Version del firmware
#define FIRMWARE_VERSION  "2.0.0-BGH"

// -----------------------------------------------------------------------------
// 4. DEFINICION DE PINES DE HARDWARE (ESP32 DevKit V1)
// -----------------------------------------------------------------------------
#define PIN_RELE_LUCES    26    // Rele de luces (GPIO 26 - Activo en ALTO / HIGH)
#define PIN_IR_SEND       18    // Emisor Infrarrojo KY-005 (GPIO 18)
#define PIN_IR_RECV       15    // Receptor Infrarrojo KY-022 (GPIO 15)
#define PIN_LED_ESTADO    2     // LED integrado indicador de estado (GPIO 2)

// -----------------------------------------------------------------------------
// 5. PARAMETROS DE RED Y TIEMPOS
// -----------------------------------------------------------------------------
#define INTERVALO_POLLING_MS      1500  // Frecuencia normal de consulta al servidor (1.5 segundos)
#define INTERVALO_BACKOFF_MS      5000  // Espera si el servidor falla para evitar saturacion y no bloquear loop
#define HTTP_TIMEOUT_MS           5000  // Timeout para solicitudes HTTP (5 segundos)
#define PUERTO_LOCAL_HTTP         80    // Puerto del servidor Web de respaldo local

// Verificacion SSL: false para conexiones directas HTTP o HTTPS sin certificados intermedios
#define VERIFICAR_CERTIFICADO_SSL false

// -----------------------------------------------------------------------------
// 6. CODIGOS IR DE RESPALDO (Epson Proyector + BGH / Star AC Coolix)
// -----------------------------------------------------------------------------
// Codigos Epson (Protocolo NEC 32-bit / 38 kHz - Prefijo 0xC1AA MSB-first)
#define CODIGO_PROYECTOR_PREAMBULO_DEF  0x81C00FF0  // Epson NEC: Preambulo del control
#define CODIGO_PROYECTOR_PRENDER_DEF    0xC1AA09F6  // Epson NEC: Encender (0x8355906F inv)
#define CODIGO_PROYECTOR_APAGAR_DEF     0xC1AA09F6  // Epson NEC: Apagar (2 pulsos con pausa de 2s)
#define CODIGO_PROYECTOR_HDMI1_DEF      0xC1AA9B64  // Epson: Entrada HDMI 1 (0x8355D926 inv)
#define CODIGO_PROYECTOR_HDMI2_DEF      0xC1AA0CF3  // Epson: Entrada HDMI 2 / Source (0x835530CF inv)
#define CODIGO_PROYECTOR_VGA_DEF        0xC1AAA659  // Epson: Entrada Computer / VGA (0x8355659A inv)
#define CODIGO_PROYECTOR_FREEZE_DEF     0xC1AA49B6  // Epson: Congelar imagen Freeze (0x8355926D inv)
#define CODIGO_PROYECTOR_MUTE_DEF       0xC1AAC936  // Epson: A/V Mute Pantalla en negro (0x8355936C inv)
#define CODIGO_PROYECTOR_VOL_MAS_DEF    0xC1AA19E6  // Epson: Subir volumen (codigo oficial Epson 0xC1AA19E6)
#define CODIGO_PROYECTOR_VOL_MENOS_DEF  0xC1AA9966  // Epson: Bajar volumen (codigo oficial Epson 0xC1AA9966)
#define CODIGO_PROYECTOR_VOL_MAS_ALT    0xC1AA4AB5  // Epson alternativo: Subir volumen
#define CODIGO_PROYECTOR_VOL_MENOS_ALT  0xC1AA4EB1  // Epson alternativo: Bajar volumen
#define CODIGO_PROYECTOR_ENTER_DEF      0xC1AA0DF2  // Epson: Enter / Auto (capturado directo)

#define REPETICIONES_PROYECTOR_APAGAR   2
#define PAUSA_PROYECTOR_APAGAR_MS       2000

// Codigos Aire BGH (Protocolo COOLIX 24-bit / 38 kHz - Solo Encender y Apagar)
#define CODIGO_STAR_AIRE_ON_DEF         0xB23F40    // BGH Coolix: Encender (capturado directo)
#define CODIGO_STAR_AIRE_OFF_DEF        0xB27BE0    // BGH Coolix: Apagar (capturado directo)

// Retrocompatibilidad
#define CODIGO_IR_PRENDER_DEF           CODIGO_STAR_AIRE_ON_DEF
#define CODIGO_IR_APAGAR_DEF            CODIGO_STAR_AIRE_OFF_DEF

#endif // CONFIG_H
