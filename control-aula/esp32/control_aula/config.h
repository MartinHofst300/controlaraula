#ifndef CONFIG_H
#define CONFIG_H

// =============================================================================
// PROYECTO: Control de Aula
// ARCHIVO: config.h
// Configuración de credenciales, pines y parámetros operativos del ESP32
// =============================================================================

// Descomentar la siguiente línea para simulación en Wokwi (usa red Wokwi-GUEST sin password)
// #define SIMULACION_WOKWI

#ifdef SIMULACION_WOKWI
  #define WIFI_SSID       "Wokwi-GUEST"
  #define WIFI_PASSWORD   ""
#else
  // Completar con las credenciales de la red WiFi real (Hotspot del celular o WiFi del colegio)
  #define WIFI_SSID       "TU_WIFI_AQUI"
  #define WIFI_PASSWORD   "TU_PASSWORD_AQUI"
#endif

// URL base del servidor (sin barra final)
// Apuntando al VPS en producción por IP y puerto
#define URL_SERVIDOR      "http://40.160.138.65:8085"

// Clave API de autenticación con el servidor (coincide con Aula 1 en MySQL y API_KEY_ESP32 en app/config.php)
#define API_KEY           "clave_secreta_esp32_aula_101_general_abc12345"

// Versión del firmware
#define FIRMWARE_VERSION  "1.0.0"

// -----------------------------------------------------------------------------
// DEFINICIÓN DE PINES DE HARDWARE (ESP32 DevKit V1 - NO MODIFICAR)
// -----------------------------------------------------------------------------
#define PIN_RELE_LUCES    26    // Relé de luces (Activo en ALTO / HIGH)
#define PIN_IR_SEND       18    // Emisor Infrarrojo KY-005 (Aire acondicionado)
#define PIN_IR_RECV       15    // Receptor Infrarrojo KY-022 (Captura y clonación)
#define PIN_LED_ESTADO    2     // LED integrado (Fijo: conectado; Parpadeo: desconectado)

// -----------------------------------------------------------------------------
// PARÁMETROS DE RED Y TIEMPOS
// -----------------------------------------------------------------------------
#define INTERVALO_POLLING_MS      1500  // Frecuencia normal de consulta al servidor (1.5 segundos)
#define INTERVALO_BACKOFF_MS      5000  // Espera si el servidor falla para evitar saturación y no bloquear loop
#define HTTP_TIMEOUT_MS           5000  // Timeout para solicitudes HTTP (5 segundos)
#define PUERTO_LOCAL_HTTP         80    // Puerto del servidor Web de respaldo local

// Verificación SSL: false usa client.setInsecure() para compatibilidad directa sin certificados intermedios
#define VERIFICAR_CERTIFICADO_SSL false

// Códigos IR predeterminados de respaldo para equipos reales del colegio (Epson + Star AC Coolix)
#define CODIGO_PROYECTOR_PRENDER_DEF    0x8355906F  // Epson NEC 32-bit: Encender proyector
#define CODIGO_PROYECTOR_APAGAR_DEF     0x8355906F  // Epson NEC 32-bit: Apagar proyector (2 pulsos con pausa de 2s)
#define CODIGO_PROYECTOR_HDMI1_DEF      0x8355D926  // Epson: Entrada HDMI 1
#define CODIGO_PROYECTOR_HDMI2_DEF      0x835530CF  // Epson: Entrada HDMI 2
#define CODIGO_PROYECTOR_VGA_DEF        0x8355659A  // Epson: Entrada VGA
#define CODIGO_PROYECTOR_FREEZE_DEF     0x8355926D  // Epson: Congelar imagen (Freeze)
#define CODIGO_PROYECTOR_MUTE_DEF       0x8355936C  // Epson: A/V Mute (Pantalla en negro)
#define CODIGO_PROYECTOR_VOL_MAS_DEF    0x835552AD  // Epson: Subir volumen
#define CODIGO_PROYECTOR_VOL_MENOS_DEF  0x8355728D  // Epson: Bajar volumen

#define REPETICIONES_PROYECTOR_APAGAR   2
#define PAUSA_PROYECTOR_APAGAR_MS       2000

#define CODIGO_STAR_AIRE_ON_DEF         0xB2BF40    // Star AC Coolix 24-bit: ON / 24°C Frío Auto
#define CODIGO_STAR_AIRE_OFF_DEF        0xB27BE0    // Star AC Coolix 24-bit: OFF
#define CODIGO_STAR_AIRE_FRIO18_DEF     0xB2BF00    // Star AC Coolix 24-bit: Frío 18°C Max
#define CODIGO_STAR_AIRE_FRIO20_DEF     0xB2BF10    // Star AC Coolix 24-bit: Frío 20°C
#define CODIGO_STAR_AIRE_FRIO22_DEF     0xB2BF20    // Star AC Coolix 24-bit: Frío 22°C
#define CODIGO_STAR_AIRE_FRIO26_DEF     0xB2BF60    // Star AC Coolix 24-bit: Frío 26°C
#define CODIGO_STAR_AIRE_CALOR21_DEF    0xB2BF95    // Star AC Coolix 24-bit: Calor 21°C
#define CODIGO_STAR_AIRE_CALOR26_DEF    0xB2BF9C    // Star AC Coolix 24-bit: Calor 26°C
#define CODIGO_STAR_AIRE_VENT_DEF       0xB2BFC8    // Star AC Coolix 24-bit: Modo Ventilación
#define CODIGO_STAR_AIRE_SECO_DEF       0xB2BF84    // Star AC Coolix 24-bit: Modo Seco (Deshumidificador)
#define CODIGO_STAR_AIRE_AUTO_DEF       0xB2BF08    // Star AC Coolix 24-bit: Modo Auto
#define CODIGO_STAR_AIRE_SWING_DEF      0xB26BE0    // Star AC Coolix 24-bit: Oscilación Swing

// Retrocompatibilidad directa
#define CODIGO_IR_PRENDER_DEF           CODIGO_STAR_AIRE_ON_DEF
#define CODIGO_IR_APAGAR_DEF            CODIGO_STAR_AIRE_OFF_DEF

#endif // CONFIG_H
