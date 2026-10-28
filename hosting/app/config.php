<?php
/**
 * Configuración general del sistema de Control de Aula
 * Compatible con PHP 7.4 y PHP 8.x
 */

// Ocultar errores al público para evitar filtraciones de información
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// Zona horaria obligatoria
date_default_timezone_set('America/Argentina/Buenos_Aires');

// -----------------------------------------------------------------------------
// 1. Conexión a la base de datos MySQL / MariaDB (completar con datos de hosting)
// -----------------------------------------------------------------------------
define('DB_HOST', 'localhost');
define('DB_NAME', 'control_aula');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_PORT', 3306);
define('DB_CHARSET', 'utf8mb4');

// -----------------------------------------------------------------------------
// 2. Claves de autenticación y tokens de seguridad
// -----------------------------------------------------------------------------
// Clave que envía el ESP32 en la cabecera X-API-Key
define('API_KEY_ESP32', 'CAMBIAR_POR_CLAVE_ESP32_ALEATORIA_64BITS');

// Token Bearer que usa la app móvil de los compañeros (Authorization: Bearer <token>)
define('TOKEN_APP', 'CAMBIAR_POR_TOKEN_APP_MOVIL_SECRETO');

// -----------------------------------------------------------------------------
// 3. Credenciales de acceso al panel web
// -----------------------------------------------------------------------------
// Usuario del panel
define('PANEL_USUARIO', 'admin');

// Hash de la contraseña del panel (generado con password_hash()).
// Para generar un hash nuevo, ejecutar en PHP: echo password_hash('TuContraseña', PASSWORD_DEFAULT);
// El hash predeterminado a continuación corresponde a la contraseña provisional: admin123
define('PANEL_PASSWORD_HASH', '$2y$10$2jKPHsivzyJCV8ckLvSi3eymLTnwp9SlsPVGPaHELdJjNVmCYLxTS');

// -----------------------------------------------------------------------------
// 4. Parámetros de comportamiento y tiempos
// -----------------------------------------------------------------------------
// Tiempo máximo sin contacto para considerar al ESP32 como online (en segundos)
define('TIMEOUT_ONLINE_SEGUNDOS', 10);

// Tiempo de vida de un comando IR en cola antes de expirar (en segundos)
define('EXPIRACION_COMANDO_SEGUNDOS', 30);

// Límite de intentos de login fallidos antes de bloquear la IP temporalmente
define('MAX_INTENTOS_LOGIN', 5);
define('MINUTOS_BLOQUEO_LOGIN', 15);

// Confianza en proxies inversos (Cloudflare, balanceadores, etc.).
// Mantener en false por seguridad salvo que el servidor esté configurado detrás de un proxy confiable.
define('TRUSTED_PROXY', false);

// -----------------------------------------------------------------------------
// 5. Inicialización de sesión segura
// -----------------------------------------------------------------------------
function iniciarSesionSegura() {
    if (session_status() === PHP_SESSION_NONE) {
        $cookieParams = [
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'httponly' => true,
            'samesite' => 'Lax'
        ];
        if (PHP_VERSION_ID >= 70300) {
            session_set_cookie_params($cookieParams);
        } else {
            session_set_cookie_params(
                $cookieParams['lifetime'],
                $cookieParams['path'] . '; samesite=' . $cookieParams['samesite'],
                $cookieParams['domain'],
                $cookieParams['secure'],
                $cookieParams['httponly']
            );
        }
        session_name('CONTROL_AULA_SESSID');
        session_start();
    }
}
