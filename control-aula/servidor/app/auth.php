<?php
/**
 * Lógica de autenticación, autorización por roles (admin / profesor),
 * tokens CSRF y control de fuerza bruta
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/funciones.php';

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
 * Autentica credenciales de usuario contra la tabla `usuarios` (con fallback a config.php)
 */
function autenticarCredencialesUsuario($pdo, $usuario, $password) {
    try {
        asegurarColumnasExtendidas($pdo);
        $stmt = $pdo->prepare('SELECT id, usuario, password_hash, nombre_completo, rol, aula_id, activo FROM usuarios WHERE usuario = :usuario LIMIT 1');
        $stmt->execute([':usuario' => $usuario]);
        $fila = $stmt->fetch();

        if ($fila) {
            if ((int) $fila['activo'] !== 1) {
                return [
                    'exito' => false,
                    'error' => 'Esta cuenta de usuario se encuentra desactivada. Contacte al administrador.'
                ];
            }
            if (password_verify($password, $fila['password_hash'])) {
                return [
                    'exito' => true,
                    'id' => (int) $fila['id'],
                    'usuario' => $fila['usuario'],
                    'nombre_completo' => $fila['nombre_completo'],
                    'rol' => $fila['rol'],
                    'aula_id' => $fila['aula_id'] !== null ? (int)$fila['aula_id'] : null
                ];
            }
            return [
                'exito' => false,
                'error' => 'Credenciales incorrectas. Verifique usuario y contraseña.'
            ];
        }
    } catch (Exception $e) {
        error_log('Aviso al consultar tabla usuarios: ' . $e->getMessage());
    }

    // Fallback de compatibilidad con constantes de config.php
    if ($usuario === PANEL_USUARIO && password_verify($password, PANEL_PASSWORD_HASH)) {
        return [
            'exito' => true,
            'id' => 1,
            'usuario' => PANEL_USUARIO,
            'nombre_completo' => 'Administrador del Sistema',
            'rol' => 'admin',
            'aula_id' => null
        ];
    }

    return [
        'exito' => false,
        'error' => 'Credenciales incorrectas. Verifique usuario y contraseña.'
    ];
}

/**
 * Autentica solicitudes del ESP32 mediante cabecera X-API-Key o parámetro api_key.
 * Soporta autenticación Multi-Aula:
 * 1. Clave específica de un aula registrada en la tabla `aulas` (columna `api_key_esp32`).
 * 2. Clave global API_KEY_ESP32 con parámetro opcional `aula_id` (por defecto aula 1).
 * Devuelve el registro de la aula autenticada.
 */
function autenticarESP32($pdo = null) {
    if (!$pdo) {
        $pdo = obtenerConexionDB();
    }
    asegurarColumnasExtendidas($pdo);

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

    if (empty($apiKeyRecibida)) {
        if (!empty($_GET['api_key'])) {
            $apiKeyRecibida = trim($_GET['api_key']);
        } elseif (!empty($_POST['api_key'])) {
            $apiKeyRecibida = trim($_POST['api_key']);
        }
    }

    if (empty($apiKeyRecibida)) {
        http_response_code(401);
        header('Content-Type: text/plain; charset=utf-8');
        echo "ERROR: Clave API requerida (X-API-Key)\n";
        exit;
    }

    // 1. Buscar si la clave corresponde a un aula específica activa
    try {
        $stmtAula = $pdo->prepare('SELECT * FROM aulas WHERE api_key_esp32 = :key AND activa = 1 LIMIT 1');
        $stmtAula->execute([':key' => $apiKeyRecibida]);
        $aula = $stmtAula->fetch(PDO::FETCH_ASSOC);
        if ($aula) {
            return $aula;
        }
    } catch (Exception $e) {
        error_log('Aviso al buscar aula por api_key: ' . $e->getMessage());
    }

    // 2. Comprobar contra la clave global por compatibilidad
    if (hash_equals(API_KEY_ESP32, $apiKeyRecibida)) {
        $aulaIdSol = 1;
        if (!empty($_GET['aula_id'])) {
            $aulaIdSol = (int) $_GET['aula_id'];
        } elseif (!empty($_POST['aula_id'])) {
            $aulaIdSol = (int) $_POST['aula_id'];
        } elseif (!empty($_SERVER['HTTP_X_AULA_ID'])) {
            $aulaIdSol = (int) $_SERVER['HTTP_X_AULA_ID'];
        }

        try {
            $stmtAula = $pdo->prepare('SELECT * FROM aulas WHERE id = :id AND activa = 1 LIMIT 1');
            $stmtAula->execute([':id' => $aulaIdSol]);
            $aula = $stmtAula->fetch(PDO::FETCH_ASSOC);
            if ($aula) {
                return $aula;
            }
            // Si no existe, devolver aula 1 por defecto
            $stmt1 = $pdo->query('SELECT * FROM aulas WHERE id = 1 LIMIT 1');
            $aula1 = $stmt1->fetch(PDO::FETCH_ASSOC);
            if ($aula1) {
                return $aula1;
            }
        } catch (Exception $e) {
            error_log('Aviso al buscar aula global: ' . $e->getMessage());
        }

        return [
            'id' => 1,
            'nombre' => 'Aula 101 - General',
            'api_key_esp32' => API_KEY_ESP32,
            'activa' => 1
        ];
    }

    http_response_code(401);
    header('Content-Type: text/plain; charset=utf-8');
    echo "ERROR: No autorizado (API Key invalida)\n";
    exit;
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
                'usuario_id' => 0,
                'usuario' => 'app_movil',
                'nombre_completo' => 'App Móvil',
                'rol' => 'admin'
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
            'usuario_id' => (int) ($_SESSION['usuario_id'] ?? 1),
            'usuario' => $_SESSION['usuario'] ?? 'admin',
            'nombre_completo' => $_SESSION['nombre_completo'] ?? ($_SESSION['usuario'] ?? 'Usuario'),
            'rol' => $_SESSION['rol'] ?? 'admin',
            'aula_id' => isset($_SESSION['aula_id']) && $_SESSION['aula_id'] !== null ? (int)$_SESSION['aula_id'] : null
        ];
    }

    // No autenticado
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Acceso no autorizado']);
    exit;
}

/**
 * Verifica que el usuario autenticado posea rol 'admin'.
 * Bloquea con HTTP 403 en caso contrario.
 */
function requerirRolAdmin($authInfo) {
    if (($authInfo['rol'] ?? '') !== 'admin') {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => false,
            'error' => 'Acceso denegado: esta operacion requiere permisos de administrador'
        ]);
        exit;
    }
    return true;
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
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        foreach ($headers as $nombre => $valor) {
            if (strcasecmp($nombre, 'X-CSRF-Token') === 0) {
                $tokenRecibido = trim($valor);
                break;
            }
        }
        if (empty($tokenRecibido)) {
            // Intentar leer de JSON body si existe
            $raw = file_get_contents('php://input');
            if (!empty($raw)) {
                $data = json_decode($raw, true);
                if (is_array($data) && isset($data['csrf_token'])) {
                    $tokenRecibido = trim($data['csrf_token']);
                }
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
