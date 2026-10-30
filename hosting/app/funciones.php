<?php
/**
 * Funciones auxiliares de respuesta, CORS, expiración, esquema extendido de MySQL,
 * persistencia de preferencias de usuario, arquitectura Multi-Aula y cola de comandos IR para el ESP32.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/**
 * Habilita cabeceras CORS para permitir consumo desde apps móviles o frontends externos
 */
function habilitarCORS() {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key, X-CSRF-Token, X-Aula-ID');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

/**
 * Responde con formato JSON y código HTTP especificado
 */
function responderJSON($datos, $codigo = 200) {
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Lee y decodifica el cuerpo de la petición (JSON o form-urlencoded)
 */
function obtenerDatosEntrada() {
    $raw = file_get_contents('php://input');
    if (!empty($raw)) {
        $json = json_decode($raw, true);
        if (is_array($json)) {
            return $json;
        }
    }
    return is_array($_POST) ? $_POST : [];
}

/**
 * Registra un evento en la tabla log_eventos con aula_id
 */
function registrarEvento($pdo, $origen, $evento, $detalle = null, $aulaId = 1) {
    try {
        asegurarColumnasExtendidas($pdo);
        $aulaIdVal = ($aulaId !== null && (int)$aulaId > 0) ? (int)$aulaId : 1;
        $stmt = $pdo->prepare('INSERT INTO log_eventos (aula_id, origen, evento, detalle, creado_en) VALUES (:aula_id, :origen, :evento, :detalle, NOW())');
        $stmt->execute([
            ':aula_id' => $aulaIdVal,
            ':origen'  => substr(trim($origen), 0, 30),
            ':evento'  => substr(trim($evento), 0, 100),
            ':detalle' => is_string($detalle) ? $detalle : (is_null($detalle) ? null : json_encode($detalle, JSON_UNESCAPED_UNICODE))
        ]);
    } catch (Exception $e) {
        error_log('Error al registrar evento: ' . $e->getMessage());
    }
}

/**
 * Expira los comandos IR que lleven más de 30 segundos pendientes sin ser tomados por el ESP32
 */
function expirarComandosViejos($pdo) {
    try {
        $segundos = (int) EXPIRACION_COMANDO_SEGUNDOS;
        $sql = "UPDATE comandos_ir 
                SET estado = 'expirado' 
                WHERE estado = 'pendiente' 
                  AND creado_en < (NOW() - INTERVAL {$segundos} SECOND)";
        $pdo->exec($sql);
    } catch (Exception $e) {
        error_log('Error al expirar comandos IR viejos: ' . $e->getMessage());
    }
}

/**
 * Devuelve la estructura base de preferencias por defecto para un usuario
 */
function obtenerPreferenciasPorDefecto() {
    return [
        'tema' => 'oscuro',               // 'oscuro' | 'claro' | 'auto'
        'acento' => 'dorado',             // 'dorado' | 'azul' | 'esmeralda' | 'violeta' | 'coral' | 'cian' | 'custom'
        'colorCustom' => '#897c37',
        'fuente' => 'sans',               // 'sans' | 'serif' | 'mono'
        'escalaPct' => 100,               // 85 a 130
        'densidad' => 'comoda',           // 'comoda' | 'compacta'
        'altoContraste' => false,
        'reducirAnimaciones' => false,
        'prefAireTemp' => 24,             // 16 a 30
        'prefAireModo' => 'frio',         // 'frio' | 'calor' | 'ventilacion' | 'seco' | 'auto'
        'confirmarApagado' => true,
        'sonidoBeep' => true,
        'intervaloSync' => 3000           // 3000 | 5000 | 10000
    ];
}

/**
 * Normaliza y valida un arreglo de preferencias recibido del cliente o de la BD
 */
function sanitizarPreferenciasUsuario($entrada) {
    $def = obtenerPreferenciasPorDefecto();
    if (!is_array($entrada)) {
        return $def;
    }

    $tema = isset($entrada['tema']) && in_array($entrada['tema'], ['oscuro', 'claro', 'auto'], true)
        ? $entrada['tema'] : $def['tema'];

    $acento = isset($entrada['acento']) && in_array($entrada['acento'], ['dorado', 'azul', 'esmeralda', 'violeta', 'coral', 'cian', 'custom'], true)
        ? $entrada['acento'] : $def['acento'];

    $colorCustom = isset($entrada['colorCustom']) && preg_match('/^#[0-9a-fA-F]{6}$/', (string) $entrada['colorCustom'])
        ? strtolower((string) $entrada['colorCustom']) : $def['colorCustom'];

    $fuente = isset($entrada['fuente']) && in_array($entrada['fuente'], ['sans', 'serif', 'mono'], true)
        ? $entrada['fuente'] : $def['fuente'];

    $escalaPct = isset($entrada['escalaPct']) ? max(85, min(130, (int) $entrada['escalaPct'])) : $def['escalaPct'];

    $densidad = isset($entrada['densidad']) && in_array($entrada['densidad'], ['comoda', 'compacta'], true)
        ? $entrada['densidad'] : $def['densidad'];

    $altoContraste = isset($entrada['altoContraste']) ? (bool) $entrada['altoContraste'] : $def['altoContraste'];
    $reducirAnimaciones = isset($entrada['reducirAnimaciones']) ? (bool) $entrada['reducirAnimaciones'] : $def['reducirAnimaciones'];

    $prefAireTemp = isset($entrada['prefAireTemp']) ? max(16, min(30, (int) $entrada['prefAireTemp'])) : $def['prefAireTemp'];

    $modoRaw = isset($entrada['prefAireModo']) ? strtolower(trim((string) $entrada['prefAireModo'])) : $def['prefAireModo'];
    if ($modoRaw === 'deshumidificador') {
        $modoRaw = 'seco';
    }
    $prefAireModo = in_array($modoRaw, ['frio', 'calor', 'ventilacion', 'seco', 'auto'], true)
        ? $modoRaw : $def['prefAireModo'];

    $confirmarApagado = array_key_exists('confirmarApagado', $entrada) ? (bool) $entrada['confirmarApagado'] : $def['confirmarApagado'];
    $sonidoBeep = array_key_exists('sonidoBeep', $entrada) ? (bool) $entrada['sonidoBeep'] : $def['sonidoBeep'];

    $intervaloSync = isset($entrada['intervaloSync']) && in_array((int) $entrada['intervaloSync'], [3000, 5000, 10000], true)
        ? (int) $entrada['intervaloSync'] : $def['intervaloSync'];

    return [
        'tema' => $tema,
        'acento' => $acento,
        'colorCustom' => $colorCustom,
        'fuente' => $fuente,
        'escalaPct' => $escalaPct,
        'densidad' => $densidad,
        'altoContraste' => $altoContraste,
        'reducirAnimaciones' => $reducirAnimaciones,
        'prefAireTemp' => $prefAireTemp,
        'prefAireModo' => $prefAireModo,
        'confirmarApagado' => $confirmarApagado,
        'sonidoBeep' => $sonidoBeep,
        'intervaloSync' => $intervaloSync
    ];
}

/**
 * Obtiene las preferencias guardadas en MySQL para el usuario indicado
 */
function obtenerPreferenciasUsuarioDB($pdo, $usuarioId) {
    asegurarColumnasExtendidas($pdo);
    $def = obtenerPreferenciasPorDefecto();
    if ((int) $usuarioId <= 0) {
        return $def;
    }

    try {
        $stmt = $pdo->prepare('SELECT preferencias_json FROM usuarios WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => (int) $usuarioId]);
        $jsonStr = $stmt->fetchColumn();
        if (!empty($jsonStr)) {
            $decodificado = json_decode($jsonStr, true);
            if (is_array($decodificado)) {
                return sanitizarPreferenciasUsuario($decodificado);
            }
        }
    } catch (Exception $e) {
        error_log('Error al leer preferencias_json: ' . $e->getMessage());
    }

    return $def;
}

/**
 * Guarda las preferencias del usuario en MySQL (usuarios.preferencias_json)
 */
function guardarPreferenciasUsuarioDB($pdo, $usuarioId, $preferencias) {
    asegurarColumnasExtendidas($pdo);
    $limpias = sanitizarPreferenciasUsuario($preferencias);
    $json = json_encode($limpias, JSON_UNESCAPED_UNICODE);

    $stmt = $pdo->prepare('UPDATE usuarios SET preferencias_json = :prefs WHERE id = :id');
    $stmt->execute([
        ':prefs' => $json,
        ':id' => (int) $usuarioId
    ]);

    return $limpias;
}

/**
 * Determina de forma segura el aula_id para la petición actual:
 * - Si el usuario tiene rol 'aula', FUERZA su aula_id fija de sesión/DB.
 * - Si el usuario es 'admin', toma el aula_id del request (o 1 por defecto).
 */
function determinarAulaIdPeticion($auth, $datos = []) {
    if (($auth['rol'] ?? '') === 'aula') {
        $aulaId = (int)($auth['aula_id'] ?? ($_SESSION['aula_id'] ?? 1));
        return max(1, $aulaId);
    }

    $aulaId = 0;
    if (isset($datos['aula_id']) && (int)$datos['aula_id'] > 0) {
        $aulaId = (int)$datos['aula_id'];
    } elseif (isset($_GET['aula_id']) && (int)$_GET['aula_id'] > 0) {
        $aulaId = (int)$_GET['aula_id'];
    } elseif (isset($_POST['aula_id']) && (int)$_POST['aula_id'] > 0) {
        $aulaId = (int)$_POST['aula_id'];
    }

    return $aulaId > 0 ? $aulaId : 1;
}

/**
 * Retorna la lista de aulas activas permitidas para el usuario según su rol y permisos
 */
function obtenerAulasPermitidasUsuario($pdo, $usuarioId, $rol) {
    asegurarColumnasExtendidas($pdo);

    if ($rol === 'admin') {
        $stmt = $pdo->query('SELECT * FROM aulas WHERE activa = 1 ORDER BY id ASC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    if ($rol === 'aula') {
        $stmtUsr = $pdo->prepare('SELECT aula_id FROM usuarios WHERE id = :id LIMIT 1');
        $stmtUsr->execute([':id' => (int)$usuarioId]);
        $aid = (int)$stmtUsr->fetchColumn();
        if ($aid <= 0 && isset($_SESSION['aula_id'])) {
            $aid = (int)$_SESSION['aula_id'];
        }
        if ($aid > 0) {
            $stmt = $pdo->prepare('SELECT * FROM aulas WHERE id = :id AND activa = 1 LIMIT 1');
            $stmt->execute([':id' => $aid]);
            $fila = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($fila) {
                return [$fila];
            }
        }
        // Fallback a aula 1 si no se hallara
        $stmt1 = $pdo->query('SELECT * FROM aulas WHERE id = 1 LIMIT 1');
        $f1 = $stmt1->fetch(PDO::FETCH_ASSOC);
        return $f1 ? [$f1] : [];
    }

    // Para compatibilidad con 'profesor'
    $stmtUsr = $pdo->prepare('SELECT aulas_permitidas FROM usuarios WHERE id = :id LIMIT 1');
    $stmtUsr->execute([':id' => (int)$usuarioId]);
    $rawPermisos = $stmtUsr->fetchColumn();

    if (empty($rawPermisos) || $rawPermisos === 'todas') {
        $stmt = $pdo->query('SELECT * FROM aulas WHERE activa = 1 ORDER BY id ASC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $ids = [];
    if (is_string($rawPermisos)) {
        $dec = json_decode($rawPermisos, true);
        if (is_array($dec)) {
            $ids = array_map('intval', $dec);
        } else {
            $partes = explode(',', $rawPermisos);
            foreach ($partes as $p) {
                if ((int)trim($p) > 0) $ids[] = (int)trim($p);
            }
        }
    }

    if (empty($ids)) {
        $ids = [1];
    }

    $inQuery = implode(',', array_map('intval', $ids));
    $stmt = $pdo->query("SELECT * FROM aulas WHERE activa = 1 AND id IN ($inQuery) ORDER BY id ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Verifica si un usuario tiene acceso a un aula específica
 */
function verificarPermisoAula($pdo, $usuarioId, $rol, $aulaId, $bloquear = true) {
    if ($rol === 'admin') {
        return true;
    }

    if ($rol === 'aula') {
        $stmtUsr = $pdo->prepare('SELECT aula_id FROM usuarios WHERE id = :id LIMIT 1');
        $stmtUsr->execute([':id' => (int)$usuarioId]);
        $miAulaId = (int)$stmtUsr->fetchColumn();
        if ($miAulaId <= 0 && isset($_SESSION['aula_id'])) {
            $miAulaId = (int)$_SESSION['aula_id'];
        }
        if ($miAulaId > 0 && $miAulaId === (int)$aulaId) {
            return true;
        }

        if (!$bloquear) return false;

        if (php_sapi_name() === 'cli') {
            throw new Exception("Acceso denegado: cuenta de espacio (#{$miAulaId}) no tiene permisos para aula #{$aulaId}");
        }
        responderJSON(['ok' => false, 'error' => "Acceso denegado: esta cuenta solo puede controlar su propio salón"], 403);
        return false;
    }

    $aulasPermitidas = obtenerAulasPermitidasUsuario($pdo, $usuarioId, $rol);
    foreach ($aulasPermitidas as $a) {
        if ((int)$a['id'] === (int)$aulaId) {
            return true;
        }
    }

    if (!$bloquear) {
        return false;
    }

    if (php_sapi_name() === 'cli') {
        throw new Exception("Acceso denegado: el usuario no tiene permisos para el aula #{$aulaId}");
    }

    responderJSON(['ok' => false, 'error' => "No tiene permisos para acceder o controlar el aula #{$aulaId}"], 403);
    return false;
}

/**
 * Obtiene el registro de estado completo de un aula por ID
 */
function obtenerEstadoAula($pdo, $aulaId) {
    asegurarColumnasExtendidas($pdo);
    $stmt = $pdo->prepare('SELECT * FROM aulas WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => (int)$aulaId]);
    $aula = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$aula) {
        // Fallback a Aula 1 si no se encuentra
        $stmt1 = $pdo->query('SELECT * FROM aulas WHERE id = 1 LIMIT 1');
        $aula = $stmt1->fetch(PDO::FETCH_ASSOC);
    }

    return $aula;
}

/**
 * Actualiza los campos de estado de un aula en la tabla `aulas` y sincroniza `estado_actual`
 */
function actualizarEstadoAula($pdo, $aulaId, array $campos) {
    asegurarColumnasExtendidas($pdo);
    $aulaId = (int)$aulaId;

    if (empty($campos)) {
        return;
    }

    $sets = [];
    $params = [':id' => $aulaId];
    foreach ($campos as $col => $val) {
        $colLimpia = preg_replace('/[^a-zA-Z0-9_]/', '', $col);
        $sets[] = "`{$colLimpia}` = :{$colLimpia}";
        $params[":{$colLimpia}"] = $val;
    }

    $sql = "UPDATE aulas SET " . implode(', ', $sets) . " WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // Sincronizar en estado_actual para compatibilidad con vistas legacy
    try {
        $sqlEstado = "UPDATE estado_actual SET " . implode(', ', $sets) . " WHERE id = :id";
        $stmtEst = $pdo->prepare($sqlEstado);
        $stmtEst->execute($params);
    } catch (Exception $e) {
        // Ignorar si estado_actual no tiene fila con ese id
    }
}

/**
 * Apaga luces, aire acondicionado y proyector de un aula y encola comandos de apagado para el ESP32
 */
function apagarTodoAula($pdo, $aulaId, $usuario = 'sistema') {
    $aulaId = (int)$aulaId;
    actualizarEstadoAula($pdo, $aulaId, [
        'luces'                   => 0,
        'luces_deseadas'          => 0,
        'luces_reales'            => 0,
        'aire_encendido'          => 0,
        'ultima_accion_aire'      => 'apagar',
        'proyector_encendido'     => 0,
        'ultima_accion_proyector' => 'apagar',
        'proyector_freeze'        => 0,
        'proyector_mute'          => 0
    ]);

    $irAire = encolarComandoIRSiExiste($pdo, 'aire', 'apagar', null, $aulaId);
    $irProy = encolarComandoIRSiExiste($pdo, 'proyector', 'apagar', null, $aulaId);

    registrarEvento(
        $pdo,
        'panel',
        'AULA_APAGAR_TODO',
        "Apagado total ejecutado en Aula #{$aulaId} por {$usuario}",
        $aulaId
    );

    return [
        'ir_aire_id' => $irAire['comando_id'],
        'ir_proyector_id' => $irProy['comando_id']
    ];
}

/**
 * Asegura de forma idempotente que la base de datos local tenga todas las tablas
 * y columnas requeridas para Multi-Aula: `aulas`, `estado_actual`, `comandos_ir`, `codigos_ir`, `capturas_ir`, `log_eventos`.
 */
function asegurarColumnasExtendidas($pdo) {
    static $verificado = false;
    if ($verificado) {
        return;
    }
    $verificado = true;

    try {
        // 1. Crear tabla aulas si no existe
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `aulas` (
              `id` INT AUTO_INCREMENT NOT NULL,
              `nombre` VARCHAR(100) NOT NULL,
              `tipo` ENUM('Aula','Laboratorio','Taller','Otro') NOT NULL DEFAULT 'Aula',
              `ubicacion` VARCHAR(100) NOT NULL DEFAULT '',
              `api_key_esp32` VARCHAR(64) NOT NULL,
              `activa` TINYINT(1) NOT NULL DEFAULT 1,
              `luces` TINYINT(1) NOT NULL DEFAULT 0,
              `luces_deseadas` TINYINT(1) NOT NULL DEFAULT 0,
              `luces_reales` TINYINT(1) NOT NULL DEFAULT 0,
              `aire_encendido` TINYINT(1) NOT NULL DEFAULT 0,
              `ultima_accion_aire` VARCHAR(50) NOT NULL DEFAULT 'ninguna',
              `aire_temp` INT NOT NULL DEFAULT 24,
              `aire_modo` VARCHAR(25) NOT NULL DEFAULT 'frio',
              `aire_fan` VARCHAR(20) NOT NULL DEFAULT 'auto',
              `aire_swing` TINYINT(1) NOT NULL DEFAULT 0,
              `proyector_encendido` TINYINT(1) NOT NULL DEFAULT 0,
              `ultima_accion_proyector` VARCHAR(50) NOT NULL DEFAULT 'ninguna',
              `proyector_fuente` VARCHAR(20) NOT NULL DEFAULT 'hdmi1',
              `proyector_freeze` TINYINT(1) NOT NULL DEFAULT 0,
              `proyector_mute` TINYINT(1) NOT NULL DEFAULT 0,
              `proyector_volumen` INT NOT NULL DEFAULT 50,
              `ip` VARCHAR(45) DEFAULT NULL,
              `rssi_wifi` INT DEFAULT NULL,
              `uptime_seg` INT UNSIGNED DEFAULT 0,
              `version_fw` VARCHAR(20) NOT NULL DEFAULT '1.0.0',
              `ultima_conexion` DATETIME DEFAULT NULL,
              `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uk_aula_api_key` (`api_key_esp32`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // Asegurar columna `tipo` y configuración de hardware en tabla `aulas`
        $colsAulas = $pdo->query("SHOW COLUMNS FROM `aulas`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('tipo', $colsAulas, true)) {
            $pdo->exec("ALTER TABLE `aulas` ADD COLUMN `tipo` ENUM('Aula','Laboratorio','Taller','Otro') NOT NULL DEFAULT 'Aula' AFTER `nombre`");
        }
        if (!in_array('marca_aire', $colsAulas, true)) {
            $pdo->exec("ALTER TABLE `aulas` ADD COLUMN `marca_aire` VARCHAR(50) NOT NULL DEFAULT 'Star (OEM)' AFTER `activa`");
        }
        if (!in_array('protocolo_aire', $colsAulas, true)) {
            $pdo->exec("ALTER TABLE `aulas` ADD COLUMN `protocolo_aire` VARCHAR(30) NOT NULL DEFAULT 'COOLIX' AFTER `marca_aire`");
        }
        if (!in_array('marca_proyector', $colsAulas, true)) {
            $pdo->exec("ALTER TABLE `aulas` ADD COLUMN `marca_proyector` VARCHAR(50) NOT NULL DEFAULT 'Epson' AFTER `protocolo_aire`");
        }
        if (!in_array('protocolo_proyector', $colsAulas, true)) {
            $pdo->exec("ALTER TABLE `aulas` ADD COLUMN `protocolo_proyector` VARCHAR(30) NOT NULL DEFAULT 'EPSON_NEC' AFTER `marca_proyector`");
        }

        // 2. Insertar o actualizar las 3 aulas predeterminadas
        $keyDefault1 = defined('API_KEY_ESP32') ? API_KEY_ESP32 : 'secreto_esp32_aula_1234';
        $stmtInsAulas = $pdo->prepare("
            INSERT INTO `aulas` (`id`, `nombre`, `tipo`, `ubicacion`, `api_key_esp32`, `activa`, `aire_temp`, `aire_modo`, `aire_fan`, `proyector_fuente`, `proyector_volumen`) VALUES
            (1, 'Aula 101 - General', 'Aula', 'Planta Baja', :key1, 1, 24, 'frio', 'auto', 'hdmi1', 50),
            (2, 'Laboratorio 1 - Informática', 'Laboratorio', 'Piso 1 - Ala Norte', 'esp32_key_lab1_inform_5678', 1, 22, 'frio', 'auto', 'hdmi1', 60),
            (3, 'Taller de Electrónica', 'Taller', 'Piso 2 - Talleres', 'esp32_key_taller_elect_9012', 1, 24, 'frio', 'auto', 'vga', 40)
            ON DUPLICATE KEY UPDATE
              `tipo` = VALUES(`tipo`),
              `ubicacion` = VALUES(`ubicacion`);
        ");
        $stmtInsAulas->execute([':key1' => $keyDefault1]);

        // 3. Verificar columnas de estado_actual
        $colsEstado = $pdo->query("SHOW COLUMNS FROM `estado_actual`")->fetchAll(PDO::FETCH_COLUMN);

        $alteracionesEstado = [];
        if (!in_array('luces', $colsEstado, true)) {
            $alteracionesEstado[] = "ADD COLUMN `luces` TINYINT(1) NOT NULL DEFAULT 0 AFTER `id`";
        }
        if (!in_array('aire_encendido', $colsEstado, true)) {
            $alteracionesEstado[] = "ADD COLUMN `aire_encendido` TINYINT(1) NOT NULL DEFAULT 0 AFTER `luces_reales`";
        }
        if (!in_array('aire_temp', $colsEstado, true)) {
            $alteracionesEstado[] = "ADD COLUMN `aire_temp` INT NOT NULL DEFAULT 24 AFTER `ultima_accion_aire`";
        }
        if (!in_array('aire_modo', $colsEstado, true)) {
            $alteracionesEstado[] = "ADD COLUMN `aire_modo` VARCHAR(25) NOT NULL DEFAULT 'frio' AFTER `aire_temp`";
        }
        if (!in_array('aire_fan', $colsEstado, true)) {
            $alteracionesEstado[] = "ADD COLUMN `aire_fan` VARCHAR(20) NOT NULL DEFAULT 'auto' AFTER `aire_modo`";
        }
        if (!in_array('aire_swing', $colsEstado, true)) {
            $alteracionesEstado[] = "ADD COLUMN `aire_swing` TINYINT(1) NOT NULL DEFAULT 0 AFTER `aire_fan`";
        }
        if (!in_array('proyector_encendido', $colsEstado, true)) {
            $alteracionesEstado[] = "ADD COLUMN `proyector_encendido` TINYINT(1) NOT NULL DEFAULT 0 AFTER `aire_swing`";
        }
        if (!in_array('proyector_fuente', $colsEstado, true)) {
            $alteracionesEstado[] = "ADD COLUMN `proyector_fuente` VARCHAR(20) NOT NULL DEFAULT 'hdmi1' AFTER `ultima_accion_proyector`";
        }
        if (!in_array('proyector_freeze', $colsEstado, true)) {
            $alteracionesEstado[] = "ADD COLUMN `proyector_freeze` TINYINT(1) NOT NULL DEFAULT 0 AFTER `proyector_fuente`";
        }
        if (!in_array('proyector_mute', $colsEstado, true)) {
            $alteracionesEstado[] = "ADD COLUMN `proyector_mute` TINYINT(1) NOT NULL DEFAULT 0 AFTER `proyector_freeze`";
        }
        if (!in_array('proyector_volumen', $colsEstado, true)) {
            $alteracionesEstado[] = "ADD COLUMN `proyector_volumen` INT NOT NULL DEFAULT 50 AFTER `proyector_mute`";
        }

        if (!empty($alteracionesEstado)) {
            $sqlAlter = "ALTER TABLE `estado_actual` " . implode(", ", $alteracionesEstado);
            $pdo->exec($sqlAlter);
        }

        // Asegurar que estado_actual tenga filas para cada aula (id = aula_id)
        $aulasRows = $pdo->query("SELECT id FROM `aulas`")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($aulasRows as $aid) {
            $stmtCheckEst = $pdo->prepare("SELECT id FROM `estado_actual` WHERE id = :id");
            $stmtCheckEst->execute([':id' => $aid]);
            if (!$stmtCheckEst->fetch()) {
                $pdo->exec("INSERT INTO `estado_actual` (`id`, `luces`, `luces_deseadas`, `luces_reales`, `aire_temp`, `aire_modo`, `aire_fan`, `proyector_fuente`, `proyector_volumen`, `version_fw`) VALUES ({$aid}, 0, 0, 0, 24, 'frio', 'auto', 'hdmi1', 50, '1.0.0')");
            }
        }

        // 4. Verificar columnas en usuarios
        $colsUsuarios = $pdo->query("SHOW COLUMNS FROM `usuarios`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('aula_id', $colsUsuarios, true)) {
            $pdo->exec("ALTER TABLE `usuarios` ADD COLUMN `aula_id` INT NULL DEFAULT NULL AFTER `id`, ADD KEY `idx_usuario_aula` (`aula_id`)");
        }
        if (!in_array('preferencias_json', $colsUsuarios, true)) {
            $pdo->exec("ALTER TABLE `usuarios` ADD COLUMN `preferencias_json` TEXT DEFAULT NULL AFTER `activo`");
        }
        if (!in_array('aulas_permitidas', $colsUsuarios, true)) {
            $pdo->exec("ALTER TABLE `usuarios` ADD COLUMN `aulas_permitidas` VARCHAR(255) NOT NULL DEFAULT 'todas' AFTER `preferencias_json`");
        }

        // Asegurar que el ENUM de rol soporte 'aula'
        try {
            $pdo->exec("ALTER TABLE `usuarios` MODIFY COLUMN `rol` ENUM('admin','aula','profesor') NOT NULL DEFAULT 'aula'");
        } catch (Exception $e) {
            // Ignorar si el tipo ya es compatible
        }

        // Cuentas por espacio y admin por defecto
        $cuentasDefecto = [
            ['usuario' => 'admin',   'password' => 'admin123',  'nombre' => 'Administrador General',       'rol' => 'admin', 'aula_id' => null, 'perm' => 'todas'],
            ['usuario' => 'aula101', 'password' => 'aula123',   'nombre' => 'Aula 101 - General',          'rol' => 'aula',  'aula_id' => 1,    'perm' => '[1]'],
            ['usuario' => 'lab1',    'password' => 'lab123',    'nombre' => 'Laboratorio 1 - Informática', 'rol' => 'aula',  'aula_id' => 2,    'perm' => '[2]'],
            ['usuario' => 'taller1', 'password' => 'taller123', 'nombre' => 'Taller de Electrónica',       'rol' => 'aula',  'aula_id' => 3,    'perm' => '[3]']
        ];

        $stmtInsOrUpd = $pdo->prepare("
            INSERT INTO `usuarios` (`usuario`, `password_hash`, `nombre_completo`, `rol`, `aula_id`, `activo`, `aulas_permitidas`)
            VALUES (:usr, :pass, :nom, :rol, :aid, 1, :perm)
            ON DUPLICATE KEY UPDATE
              `password_hash` = VALUES(`password_hash`),
              `nombre_completo` = VALUES(`nombre_completo`),
              `rol` = VALUES(`rol`),
              `aula_id` = VALUES(`aula_id`)
        ");

        foreach ($cuentasDefecto as $c) {
            $stmtInsOrUpd->execute([
                ':usr'  => $c['usuario'],
                ':pass' => password_hash($c['password'], PASSWORD_DEFAULT),
                ':nom'  => $c['nombre'],
                ':rol'  => $c['rol'],
                ':aid'  => $c['aula_id'],
                ':perm' => $c['perm']
            ]);
        }

        // 5. Agregar aula_id en comandos_ir, codigos_ir, capturas_ir y log_eventos, y ac_state en comandos_ir
        $colsCmd = $pdo->query("SHOW COLUMNS FROM `comandos_ir`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('aula_id', $colsCmd, true)) {
            $pdo->exec("ALTER TABLE `comandos_ir` ADD COLUMN `aula_id` INT NOT NULL DEFAULT 1 AFTER `id`, ADD KEY `idx_cmd_aula` (`aula_id`)");
        }
        if (!in_array('ac_state', $colsCmd, true)) {
            $pdo->exec("ALTER TABLE `comandos_ir` ADD COLUMN `ac_state` TEXT DEFAULT NULL AFTER `frecuencia_khz`");
        }

        $colsCod = $pdo->query("SHOW COLUMNS FROM `codigos_ir`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('aula_id', $colsCod, true)) {
            $pdo->exec("ALTER TABLE `codigos_ir` ADD COLUMN `aula_id` INT NULL DEFAULT NULL AFTER `id`, ADD KEY `idx_cod_aula` (`aula_id`)");
        }

        $colsCap = $pdo->query("SHOW COLUMNS FROM `capturas_ir`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('aula_id', $colsCap, true)) {
            $pdo->exec("ALTER TABLE `capturas_ir` ADD COLUMN `aula_id` INT NOT NULL DEFAULT 1 AFTER `id`, ADD KEY `idx_cap_aula` (`aula_id`)");
        }

        $colsLog = $pdo->query("SHOW COLUMNS FROM `log_eventos`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('aula_id', $colsLog, true)) {
            $pdo->exec("ALTER TABLE `log_eventos` ADD COLUMN `aula_id` INT NULL DEFAULT 1 AFTER `id`, ADD KEY `idx_log_aula` (`aula_id`)");
        }

        // 6. Asegurar códigos IR reales de hardware (Epson y Star AC Coolix)
        $codigosRealesHardware = [
            // Códigos reales Star AC (Coolix OEM 24-bit)
            ['aire', 'prender',          'COOLIX', '0xB2BF40', 24, 1, 100, 38, 'Star Aire Encender (24°C Frío Auto)'],
            ['aire', 'apagar',           'COOLIX', '0xB27BE0', 24, 1, 100, 38, 'Star Aire Apagar'],
            ['aire', 'frio_18',          'COOLIX', '0xB2BF00', 24, 1, 100, 38, 'Star Aire 18°C Frío Max'],
            ['aire', 'frio_20',          'COOLIX', '0xB2BF10', 24, 1, 100, 38, 'Star Aire 20°C Frío'],
            ['aire', 'frio_22',          'COOLIX', '0xB2BF20', 24, 1, 100, 38, 'Star Aire 22°C Frío'],
            ['aire', 'frio_24',          'COOLIX', '0xB2BF40', 24, 1, 100, 38, 'Star Aire 24°C Frío Eco'],
            ['aire', 'frio_26',          'COOLIX', '0xB2BF60', 24, 1, 100, 38, 'Star Aire 26°C Frío'],
            ['aire', 'calor_21',         'COOLIX', '0xB2BF95', 24, 1, 100, 38, 'Star Aire 21°C Calor'],
            ['aire', 'calor_26',         'COOLIX', '0xB2BF9C', 24, 1, 100, 38, 'Star Aire 26°C Calor Confort'],
            ['aire', 'modo_frio',        'COOLIX', '0xB2BF40', 24, 1, 100, 38, 'Star Aire Modo Frío'],
            ['aire', 'modo_calor',       'COOLIX', '0xB2BF9C', 24, 1, 100, 38, 'Star Aire Modo Calor'],
            ['aire', 'modo_ventilacion', 'COOLIX', '0xB2BFC8', 24, 1, 100, 38, 'Star Aire Modo Ventilación'],
            ['aire', 'modo_seco',        'COOLIX', '0xB2BF84', 24, 1, 100, 38, 'Star Aire Modo Seco (Deshumidificador)'],
            ['aire', 'modo_auto',        'COOLIX', '0xB2BF08', 24, 1, 100, 38, 'Star Aire Modo Automático'],
            ['aire', 'fan_auto',         'COOLIX', '0xB2BF40', 24, 1, 100, 38, 'Star Aire Ventilador Auto'],
            ['aire', 'fan_bajo',         'COOLIX', '0xB2BF42', 24, 1, 100, 38, 'Star Aire Ventilador Bajo'],
            ['aire', 'fan_medio',        'COOLIX', '0xB2BF44', 24, 1, 100, 38, 'Star Aire Ventilador Medio'],
            ['aire', 'fan_alto',         'COOLIX', '0xB2BF46', 24, 1, 100, 38, 'Star Aire Ventilador Alto'],
            ['aire', 'swing',            'COOLIX', '0xB26BE0', 24, 1, 100, 38, 'Star Aire Oscilación Swing'],
            ['aire', 'swing_on',         'COOLIX', '0xB26BE0', 24, 1, 100, 38, 'Star Aire Oscilación Swing ON'],
            ['aire', 'swing_off',        'COOLIX', '0xB26BE0', 24, 1, 100, 38, 'Star Aire Oscilación Swing OFF'],
            // Códigos reales Epson Proyector (Protocolo NEC 32-bit / 38 kHz)
            ['proyector', 'prender',     'NEC', '0x8355906F', 32, 1, 100, 38, 'Epson Proyector Encender'],
            ['proyector', 'apagar',      'NEC', '0x8355906F', 32, 2, 2000, 38, 'Epson Proyector Apagar (doble confirmación 2 seg)'],
            ['proyector', 'hdmi1',       'NEC', '0x8355D926', 32, 1, 100, 38, 'Epson Proyector Entrada HDMI 1'],
            ['proyector', 'hdmi2',       'NEC', '0x835530CF', 32, 1, 100, 38, 'Epson Proyector Entrada HDMI 2'],
            ['proyector', 'vga',         'NEC', '0x8355659A', 32, 1, 100, 38, 'Epson Proyector Entrada VGA (Computer)'],
            ['proyector', 'freeze',      'NEC', '0x8355926D', 32, 1, 100, 38, 'Epson Proyector Congelar Imagen (Freeze)'],
            ['proyector', 'av_mute',     'NEC', '0x8355936C', 32, 1, 100, 38, 'Epson Proyector Pantalla en Negro (AV Mute)'],
            ['proyector', 'vol_mas',     'NEC', '0x835552AD', 32, 1, 100, 38, 'Epson Proyector Subir Volumen'],
            ['proyector', 'vol_menos',   'NEC', '0x8355728D', 32, 1, 100, 38, 'Epson Proyector Bajar Volumen'],
            ['proyector', 'vol_mute',    'NEC', '0x8355936C', 32, 1, 100, 38, 'Epson Proyector Silenciar Audio (Mute)']
        ];

        $stmtFindCod = $pdo->prepare("SELECT id FROM `codigos_ir` WHERE `dispositivo` = :disp AND `accion` = :acc AND (`aula_id` IS NULL OR `aula_id` = 0) LIMIT 1");
        $stmtUpdCod = $pdo->prepare("UPDATE `codigos_ir` SET `protocolo` = :proto, `codigo` = :cod, `codigo_hex` = :cod_hex, `bits` = :bits, `repeticiones` = :rep, `pausa_ms` = :pausa, `frecuencia_khz` = :khz, `descripcion` = :desc WHERE `id` = :id");
        $stmtInsCod = $pdo->prepare("INSERT INTO `codigos_ir` (`aula_id`, `dispositivo`, `accion`, `protocolo`, `codigo`, `codigo_hex`, `bits`, `repeticiones`, `pausa_ms`, `frecuencia_khz`, `descripcion`) VALUES (NULL, :disp, :acc, :proto, :cod, :cod_hex, :bits, :rep, :pausa, :khz, :desc)");

        foreach ($codigosRealesHardware as $cr) {
            $stmtFindCod->execute([':disp' => $cr[0], ':acc' => $cr[1]]);
            $existenteId = $stmtFindCod->fetchColumn();
            if ($existenteId) {
                $stmtUpdCod->execute([
                    ':proto'   => $cr[2],
                    ':cod'     => $cr[3],
                    ':cod_hex' => $cr[3],
                    ':bits'    => $cr[4],
                    ':rep'     => $cr[5],
                    ':pausa'   => $cr[6],
                    ':khz'     => $cr[7],
                    ':desc'    => $cr[8],
                    ':id'      => $existenteId
                ]);
            } else {
                $stmtInsCod->execute([
                    ':disp'    => $cr[0],
                    ':acc'     => $cr[1],
                    ':proto'   => $cr[2],
                    ':cod'     => $cr[3],
                    ':cod_hex' => $cr[3],
                    ':bits'    => $cr[4],
                    ':rep'     => $cr[5],
                    ':pausa'   => $cr[6],
                    ':khz'     => $cr[7],
                    ':desc'    => $cr[8]
                ]);
            }
        }
    } catch (Exception $e) {
        error_log('Aviso en asegurarColumnasExtendidas: ' . $e->getMessage());
    }
}

/**
 * Busca un código IR en codigos_ir considerando el aula específica o fallback global (aula_id IS NULL)
 * y encola SIEMPRE la orden real en la tabla `comandos_ir` para el ESP32 del aula indicada.
 * Soporta ac_state sintetizado y temporizaciones especiales de apagado Epson.
 */
function encolarComandoIRSiExiste($pdo, $dispositivo, $accionPrincipal, $accionFallback = null, $aulaId = 1, $acState = null) {
    asegurarColumnasExtendidas($pdo);
    $aulaId = ($aulaId !== null && (int)$aulaId > 0) ? (int)$aulaId : 1;

    $candidatos = [$accionPrincipal];
    if (is_array($accionFallback)) {
        foreach ($accionFallback as $fb) {
            if ($fb && !in_array($fb, $candidatos, true)) {
                $candidatos[] = $fb;
            }
        }
    } elseif (is_string($accionFallback) && $accionFallback !== '' && $accionFallback !== $accionPrincipal) {
        $candidatos[] = $accionFallback;
    }

    if (!in_array('prender', $candidatos, true) && $accionPrincipal !== 'apagar') {
        $candidatos[] = 'prender';
    }

    $stmt = $pdo->prepare('
        SELECT * FROM codigos_ir 
        WHERE dispositivo = :disp AND accion = :acc 
          AND (aula_id = :aid1 OR aula_id IS NULL OR aula_id = 0)
        ORDER BY (aula_id = :aid2) DESC, id DESC 
        LIMIT 1
    ');
    $codigoInfo = null;

    foreach ($candidatos as $accBusqueda) {
        $stmt->execute([':disp' => $dispositivo, ':acc' => $accBusqueda, ':aid1' => $aulaId, ':aid2' => $aulaId]);
        $fila = $stmt->fetch();
        if ($fila) {
            $codigoInfo = $fila;
            break;
        }
    }

    // Si aún no hubiera fila en codigos_ir, sintetizar trama determinística
    if (!$codigoInfo) {
        if ($dispositivo === 'aire') {
            $codigoInfo = [
                'protocolo' => 'COOLIX',
                'codigo' => '0xB2BF40',
                'bits' => 24,
                'estado_hex' => null,
                'raw' => null,
                'repeticiones' => 1,
                'pausa_ms' => 100,
                'frecuencia_khz' => 38
            ];
        } else {
            $hashHex = strtoupper(substr(md5($dispositivo . '_' . $accionPrincipal), 0, 8));
            $codigoInfo = [
                'protocolo' => 'NEC',
                'codigo' => '0x' . $hashHex,
                'bits' => 32,
                'estado_hex' => null,
                'raw' => null,
                'repeticiones' => 1,
                'pausa_ms' => 100,
                'frecuencia_khz' => 38
            ];
        }
    }

    // Regla de hardware Epson: el apagado requiere 2 repeticiones con 2000 ms de pausa
    if ($dispositivo === 'proyector' && $accionPrincipal === 'apagar') {
        $codigoInfo['repeticiones'] = 2;
        $codigoInfo['pausa_ms'] = 2000;
        if (empty($codigoInfo['codigo']) || $codigoInfo['codigo'] === '0x4CB0708F') {
            $codigoInfo['codigo'] = '0x8355906F';
        }
    }

    $acStateJson = null;
    if (is_array($acState)) {
        $acStateJson = json_encode($acState, JSON_UNESCAPED_UNICODE);
    } elseif (is_string($acState) && !empty($acState)) {
        $acStateJson = $acState;
    }

    $stmtInsert = $pdo->prepare(
        'INSERT INTO comandos_ir 
         (aula_id, dispositivo, accion, protocolo, codigo, bits, estado_hex, raw, repeticiones, pausa_ms, frecuencia_khz, ac_state, estado, creado_en) 
         VALUES 
         (:aula_id, :disp, :accion, :protocolo, :codigo, :bits, :estado_hex, :raw, :repeticiones, :pausa_ms, :frecuencia_khz, :ac_state, "pendiente", NOW())'
    );

    $stmtInsert->execute([
        ':aula_id'        => $aulaId,
        ':disp'           => $dispositivo,
        ':accion'         => $accionPrincipal,
        ':protocolo'      => $codigoInfo['protocolo'],
        ':codigo'         => $codigoInfo['codigo'],
        ':bits'           => (int) $codigoInfo['bits'],
        ':estado_hex'     => $codigoInfo['estado_hex'],
        ':raw'            => $codigoInfo['raw'],
        ':repeticiones'   => (int) $codigoInfo['repeticiones'],
        ':pausa_ms'       => (int) $codigoInfo['pausa_ms'],
        ':frecuencia_khz' => (int) $codigoInfo['frecuencia_khz'],
        ':ac_state'       => $acStateJson
    ]);

    return [
        'encolado'       => true,
        'comando_id'     => (int) $pdo->lastInsertId(),
        'aula_id'        => $aulaId,
        'protocolo'      => $codigoInfo['protocolo'],
        'codigo'         => $codigoInfo['codigo'],
        'bits'           => (int) $codigoInfo['bits'],
        'repeticiones'   => (int) $codigoInfo['repeticiones'],
        'pausa_ms'       => (int) $codigoInfo['pausa_ms'],
        'frecuencia_khz' => (int) $codigoInfo['frecuencia_khz'],
        'ac_state'       => $acStateJson ? json_decode($acStateJson, true) : null
    ];
}
