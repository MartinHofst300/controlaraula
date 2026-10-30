<?php
/**
 * GET /api/estado
 * Retorna el estado completo en MySQL del aula solicitada (Multi-Aula) y telemetría del ESP32,
 * incluyendo iluminación (`luces`), termostato de Aire Acondicionado (`aire_encendido`,
 * `aire_temp`, `aire_modo`, `aire_fan`, `aire_swing`), Proyector Multimedia
 * (`proyector_encendido`, `proyector_fuente`, `proyector_freeze`, `proyector_mute`, `proyector_volumen`),
 * cantidad de comandos en cola para esa aula y los últimos logs registrados.
 */

require_once __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../app/db.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/funciones.php';

habilitarCORS();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderJSON(['ok' => false, 'error' => 'Metodo no permitido. Se requiere GET'], 405);
}

$auth = autenticarPanelOApp();
$pdo = obtenerConexionDB();

asegurarColumnasExtendidas($pdo);
expirarComandosViejos($pdo);

$usuarioId = (int) ($auth['usuario_id'] ?? 0);
$rol = $auth['rol'] ?? 'profesor';

// Determinar aula solicitada con aislamiento estricto para cuentas de aula
$aulaId = determinarAulaIdPeticion($auth, $_GET);

// Validar permisos
verificarPermisoAula($pdo, $usuarioId, $rol, $aulaId);

// Obtener estado del aula desde la tabla `aulas`
$aula = obtenerEstadoAula($pdo, $aulaId);
if (!$aula) {
    responderJSON(['ok' => false, 'error' => 'Aula no encontrada'], 404);
}

// Determinar estado de conexión del ESP32 asignado al aula
$online = false;
$segundosDesdeContacto = null;

if (!empty($aula['ultima_conexion'])) {
    $tiempoContacto = strtotime($aula['ultima_conexion']);
    $segundosDesdeContacto = time() - $tiempoContacto;
    if ($segundosDesdeContacto >= 0 && $segundosDesdeContacto <= TIMEOUT_ONLINE_SEGUNDOS) {
        $online = true;
    }
}

// Si es el aula 1 y no tiene última conexión en `aulas`, chequear tabla `estado_actual`
if (!$online && $aulaId === 1) {
    $stmtCompat = $pdo->query('SELECT ultimo_contacto FROM estado_actual WHERE id = 1 LIMIT 1');
    $compat = $stmtCompat ? $stmtCompat->fetch() : null;
    if ($compat && !empty($compat['ultimo_contacto'])) {
        $segundos = time() - strtotime($compat['ultimo_contacto']);
        if ($segundos >= 0 && $segundos <= TIMEOUT_ONLINE_SEGUNDOS) {
            $online = true;
            $segundosDesdeContacto = $segundos;
        }
    }
}

// Contar comandos IR en cola pendientes PARA ESTA AULA
$stmtPendientes = $pdo->prepare("SELECT COUNT(*) AS total FROM comandos_ir WHERE estado = 'pendiente' AND aula_id = :aula_id");
$stmtPendientes->execute([':aula_id' => $aulaId]);
$pendientes = (int) $stmtPendientes->fetchColumn();

// Obtener los últimos eventos de log para esta aula o generales
$stmtLogs = $pdo->prepare(
    "SELECT id, origen, evento, detalle, creado_en 
     FROM log_eventos 
     WHERE aula_id = :aula_id OR aula_id IS NULL OR aula_id = 0 
     ORDER BY id DESC LIMIT 10"
);
$stmtLogs->execute([':aula_id' => $aulaId]);
$ultimosLogs = $stmtLogs ? $stmtLogs->fetchAll() : [];

$luces = (int) ($aula['luces'] ?? 0);
$aireEncendido = (int) ($aula['aire_encendido'] ?? 0);
$aireTemp = (int) ($aula['aire_temp'] ?? 24);
$aireModo = $aula['aire_modo'] ?? 'frio';
$aireFan = $aula['aire_fan'] ?? 'auto';
$aireSwing = (int) ($aula['aire_swing'] ?? 0);

$proyectorEncendido = (int) ($aula['proyector_encendido'] ?? 0);
$proyectorFuente = $aula['proyector_fuente'] ?? 'hdmi1';
$proyectorFreeze = (int) ($aula['proyector_freeze'] ?? 0);
$proyectorMute = (int) ($aula['proyector_mute'] ?? 0);
$proyectorVolumen = (int) ($aula['proyector_volumen'] ?? 50);

responderJSON([
    'ok' => true,
    'aula_id' => $aulaId,
    'aula_nombre' => $aula['nombre'],
    'aula_ubicacion' => $aula['ubicacion'] ?? '',
    'hardware' => [
        'marca_aire' => $aula['marca_aire'] ?? 'Star (OEM)',
        'protocolo_aire' => $aula['protocolo_aire'] ?? 'COOLIX',
        'marca_proyector' => $aula['marca_proyector'] ?? 'Epson',
        'protocolo_proyector' => $aula['protocolo_proyector'] ?? 'EPSON_NEC'
    ],
    'aulas_permitidas' => array_map(function($a) {
        return ['id' => (int)$a['id'], 'nombre' => $a['nombre'], 'ubicacion' => $a['ubicacion'] ?? ''];
    }, $aulasPermitidas),
    'esp32_online' => $online,
    'luces' => $luces,
    'luces_deseadas' => $luces,
    'luces_reales' => $luces,
    'aire_encendido' => $aireEncendido,
    'aire_temp' => $aireTemp,
    'aire_modo' => $aireModo,
    'aire_fan' => $aireFan,
    'aire_swing' => $aireSwing,
    'proyector_encendido' => $proyectorEncendido,
    'proyector_fuente' => $proyectorFuente,
    'proyector_freeze' => $proyectorFreeze,
    'proyector_mute' => $proyectorMute,
    'proyector_volumen' => $proyectorVolumen,
    'rssi' => $aula['rssi_wifi'] !== null ? (int) $aula['rssi_wifi'] : null,
    'uptime' => (int) ($aula['uptime_seg'] ?? 0),
    'ultimo_contacto' => $aula['ultima_conexion'] ?? null,
    'segundos_desde_contacto' => $segundosDesdeContacto,
    'comandos_ir_pendientes' => $pendientes,
    'ultimos_logs' => $ultimosLogs,
    'hora_servidor' => date('Y-m-d H:i:s')
]);
