<?php
/**
 * Lógica de autenticación, autorización, tokens CSRF y control de fuerza bruta
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/**
 * Obtiene la dirección IP real del cliente considerando posibles proxies reversos
 */
function obtenerIPCliente() {
    if (defined('TRUSTED_PROXY') && TRUSTED_PROXY === true) {
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return $_SERVER['HTTP_CF_CONNECTING_IP'];
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $partes = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($partes[0]);
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Autentica solicitudes del ESP32 mediante cabecera X-API-Key
 */
function autenticarESP32() {
    $apiKeyRecibida = '';

    if (isset($_SERVER['HTTP_X_API_KEY'])) {
        $apiKeyRecibida = trim($_SERVER['HTTP_X_API_KEY']);
    } else {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        foreach ($headers as $nombre => $valor) {
            if (strcasecmp($nombre, 'X-API-Key') === 0) {
                $apiKeyRecibida = trim($valor);
                break;
            }
        }
    }

    if (empty($apiKeyRecibida) || !hash_equals(API_KEY_ESP32, $apiKeyRecibida)) {
        http_response_code(401);
        header('Content-Type: text/plain; charset=utf-8');
        echo "ERROR: No autorizado\n";
        exit;
    }

    return true;
}

/**
 * Extrae el Bearer token de la cabecera Authorization
 */
function obtenerBearerToken() {
    $authHeader = '';
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = trim($_SERVER['HTTP_AUTHORIZATION']);
    } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $authHeader = trim($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
    } else {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        foreach ($headers as $nombre => $valor) {
            if (strcasecmp($nombre, 'Authorization') === 0) {
                $authHeader = trim($valor);
                break;
            }
        }
    }

    if (!empty($authHeader) && preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
        return trim($matches[1]);
    }

    return null;
}

/**
 * Autentica peticiones para el Panel Web (vía sesión) o para la App Móvil (vía Bearer token)
 */
function autenticarPanelOApp() {
    // 1. Verificar si hay token Bearer de la app móvil
    $bearerToken = obtenerBearerToken();
    if ($bearerToken !== null) {
        if (hash_equals(TOKEN_APP, $bearerToken)) {
            return [
                'autenticado' => true,
                'origen' => 'app',
                'usuario' => 'app_movil'
            ];
        } else {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Token de aplicacion invalido']);
            exit;
        }
    }

    // 2. Verificar si existe una sesión activa del panel
    iniciarSesionSegura();
    if (!empty($_SESSION['autenticado']) && $_SESSION['autenticado'] === true) {
        return [
            'autenticado' => true,
            'origen' => 'panel',
            'usuario' => $_SESSION['usuario'] ?? 'admin'
        ];
    }

    // No autenticado
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Acceso no autorizado']);
    exit;
}

/**
 * Genera o recupera un token CSRF para la sesión activa
 */
function obtenerTokenCSRF() {
    iniciarSesionSegura();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Valida el token CSRF para peticiones POST/PUT/DELETE que provengan de sesión web
 */
function validarTokenCSRFSiEsPanel($authInfo) {
    // Si la llamada fue autenticada por Bearer Token (app móvil), no requiere CSRF
    if (isset($authInfo['origen']) && $authInfo['origen'] === 'app') {
        return true;
    }

    iniciarSesionSegura();
    $tokenRecibido = '';

    if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        $tokenRecibido = trim($_SERVER['HTTP_X_CSRF_TOKEN']);
    } elseif (isset($_POST['csrf_token'])) {
        $tokenRecibido = trim($_POST['csrf_token']);
    } else {
        // Intentar leer de JSON body si existe
        $raw = file_get_contents('php://input');
        if (!empty($raw)) {
            $data = json_decode($raw, true);
            if (is_array($data) && isset($data['csrf_token'])) {
                $tokenRecibido = trim($data['csrf_token']);
            }
        }
    }

    if (empty($_SESSION['csrf_token']) || empty($tokenRecibido) || !hash_equals($_SESSION['csrf_token'], $tokenRecibido)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Token CSRF invalido o expirado']);
        exit;
    }

    return true;
}

/**
 * Control de intentos de login por IP para mitigar ataques de fuerza bruta
 */
function verificarBloqueoIP($pdo, $ip) {
    $stmt = $pdo->prepare('SELECT intentos, ultimo_intento, bloqueado_hasta FROM intentos_login WHERE ip = :ip LIMIT 1');
    $stmt->execute([':ip' => $ip]);
    $fila = $stmt->fetch();

    if ($fila && !empty($fila['bloqueado_hasta'])) {
        $bloqueadoHasta = strtotime($fila['bloqueado_hasta']);
        if ($bloqueadoHasta > time()) {
            $minutosRestantes = ceil(($bloqueadoHasta - time()) / 60);
            return [
                'bloqueado' => true,
                'mensaje' => "Demasiados intentos fallidos. Intente nuevamente en {$minutosRestantes} minutos."
            ];
        }
    }

    return ['bloqueado' => false];
}

function registrarFalloLogin($pdo, $ip) {
    $stmt = $pdo->prepare('SELECT intentos FROM intentos_login WHERE ip = :ip LIMIT 1');
    $stmt->execute([':ip' => $ip]);
    $fila = $stmt->fetch();

    $ahora = date('Y-m-d H:i:s');

    if ($fila) {
        $intentos = $fila['intentos'] + 1;
        $bloqueadoHasta = null;

        if ($intentos >= MAX_INTENTOS_LOGIN) {
            $bloqueadoHasta = date('Y-m-d H:i:s', time() + (MINUTOS_BLOQUEO_LOGIN * 60));
        }

        $update = $pdo->prepare('UPDATE intentos_login SET intentos = :intentos, ultimo_intento = :ahora, bloqueado_hasta = :bloqueado_hasta WHERE ip = :ip');
        $update->execute([
            ':intentos' => $intentos,
            ':ahora' => $ahora,
            ':bloqueado_hasta' => $bloqueadoHasta,
            ':ip' => $ip
        ]);
    } else {
        $insert = $pdo->prepare('INSERT INTO intentos_login (ip, intentos, ultimo_intento, bloqueado_hasta) VALUES (:ip, 1, :ahora, NULL)');
        $insert->execute([
            ':ip' => $ip,
            ':ahora' => $ahora
        ]);
    }
}

function limpiarIntentosLogin($pdo, $ip) {
    $stmt = $pdo->prepare('DELETE FROM intentos_login WHERE ip = :ip');
    $stmt->execute([':ip' => $ip]);
}
