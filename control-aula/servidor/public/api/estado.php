<?php
/**
 * GET /api/estado
 * Retorna el estado completo del sistema y telemetría del ESP32,
 * incluyendo la última acción por cada dispositivo (luces, aire, proyector).
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

// Limpiar comandos IR que hayan superado el tiempo de expiración
expirarComandosViejos($pdo);

// Consultar el estado actual
$stmt = $pdo->query('SELECT * FROM estado_actual WHERE id = 1 LIMIT 1');
$estado = $stmt->fetch();

if (!$estado) {
    responderJSON(['ok' => false, 'error' => 'Registro de estado no encontrado'], 500);
}

// Determinar si el ESP32 está online (contacto en los últimos N segundos)
$online = false;
$segundosDesdeContacto = null;

if (!empty($estado['ultimo_contacto'])) {
    $tiempoContacto = strtotime($estado['ultimo_contacto']);
    $segundosDesdeContacto = time() - $tiempoContacto;
    if ($segundosDesdeContacto >= 0 && $segundosDesdeContacto <= TIMEOUT_ONLINE_SEGUNDOS) {
        $online = true;
    }
}

// Contar comandos IR en cola pendientes
$stmtPendientes = $pdo->query("SELECT COUNT(*) AS total FROM comandos_ir WHERE estado = 'pendiente'");
$pendientes = (int) $stmtPendientes->fetchColumn();

$ultimaAccionAire = $estado['ultima_accion_aire'] ?? 'ninguna';
$ultimaAccionProyector = $estado['ultima_accion_proyector'] ?? 'ninguna';

responderJSON([
    'ok' => true,
    'esp32_online' => $online,
    'luces_deseadas' => (int) $estado['luces_deseadas'],
    'luces_reales' => (int) $estado['luces_reales'],
    'ultima_accion_aire' => $ultimaAccionAire,
    'ultima_accion_proyector' => $ultimaAccionProyector,
    'acciones_dispositivos' => [
        'aire' => $ultimaAccionAire,
        'proyector' => $ultimaAccionProyector
    ],
    'ip' => $estado['ip'],
    'rssi' => $estado['rssi'] !== null ? (int) $estado['rssi'] : null,
    'uptime' => (int) $estado['uptime'],
    'version_fw' => $estado['version_fw'],
    'ultimo_contacto' => $estado['ultimo_contacto'],
    'segundos_desde_contacto' => $segundosDesdeContacto,
    'comandos_ir_pendientes' => $pendientes,
    'hora_servidor' => date('Y-m-d H:i:s')
]);
