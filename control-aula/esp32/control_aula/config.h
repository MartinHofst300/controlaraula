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

// URL base del servidor en OVH (sin barra final)
// Ejemplo: "https://tudominio.com" o "https://tudominio.com/control-aula/servidor/public"
#define URL_SERVIDOR      "https://tu-dominio.com"

// Clave API de autenticación con el servidor OVH (debe coincidir con API_KEY_ESP32 en app/config.php)
#define API_KEY           "CAMBIAR_POR_CLAVE_ESP32_ALEATORIA_64BITS"

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

// Códigos IR predeterminados de respaldo para el control local directo si no hay conexión a OVH
#define CODIGO_IR_PRENDER_DEF           0xFF629D    // NEC genérico prender aire
#define CODIGO_IR_APAGAR_DEF            0xFF02FD    // NEC genérico apagar aire
#define CODIGO_PROYECTOR_PRENDER_DEF    0x4CB0F00F  // NEC proyector prender
#define CODIGO_PROYECTOR_APAGAR_DEF     0x4CB0708F  // NEC proyector apagar

#endif // CONFIG_H
