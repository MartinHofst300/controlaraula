<?php
/**
 * POST /api/esp32/reportar
 * Endpoint donde el ESP32 informa su estado real (luces, IP, RSSI, uptime, firmware)
 * y confirma la ejecución de comandos IR por ID para su aula asignada (Multi-Aula).
 * Autenticación: Cabecera X-API-Key con hash_equals.
 * Respuesta: Texto plano "OK".
 */

require_once __DIR__ . '/../../../app/config.php';
require_once __DIR__ . '/../../../app/db.php';
require_once __DIR__ . '/../../../app/auth.php';
require_once __DIR__ . '/../../../app/funciones.php';

habilitarCORS();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: text/plain; charset=utf-8');
    echo "ERROR: Metodo no permitido\n";
    exit;
}

$pdo = obtenerConexionDB();
asegurarColumnasExtendidas($pdo);

$aula = autenticarESP32($pdo);
$aulaId = (int) $aula['id'];

$datos = obtenerDatosEntrada();

$lucesReales = isset($datos['luces']) ? (int) $datos['luces'] : 0;
$ip = isset($datos['ip']) ? substr(trim($datos['ip']), 0, 45) : obtenerIPCliente();
$rssi = isset($datos['rssi']) ? (int) $datos['rssi'] : null;
$uptime = isset($datos['uptime']) ? (int) $datos['uptime'] : 0;
$firmware = isset($datos['firmware']) ? substr(trim($datos['firmware']), 0, 20) : '1.0.0';
$irEjecutado = isset($datos['ir_ejecutado']) ? (int) $datos['ir_ejecutado'] : 0;
$cambioLocal = isset($datos['cambio_local']) && ((int)$datos['cambio_local'] === 1);

// Actualizar la fila en `aulas`
if ($cambioLocal) {
    $stmt = $pdo->prepare(
        'UPDATE aulas 
         SET luces = :luces,
             rssi_wifi = :rssi,
             uptime_seg = :uptime,
             ultima_conexion = NOW()
         WHERE id = :id'
    );
    $stmt->execute([
        ':luces'  => $lucesReales,
        ':rssi'   => $rssi,
        ':uptime' => $uptime,
        ':id'     => $aulaId
    ]);

    if ($aulaId === 1) {
        $stmtCompat = $pdo->prepare(
            'UPDATE estado_actual 
             SET luces_reales = :luces, luces_deseadas = :luces, luces = :luces, ip = :ip, rssi = :rssi, uptime = :uptime, version_fw = :fw, ultimo_contacto = NOW() 
             WHERE id = 1'
        );
        $stmtCompat->execute([':luces' => $lucesReales, ':ip' => $ip, ':rssi' => $rssi, ':uptime' => $uptime, ':fw' => $firmware]);
    }

    registrarEvento(
        $pdo,
        'esp32',
        'LUCES_CAMBIO_LOCAL',
        "Luces modificadas localmente en Aula #{$aulaId} (" . ($lucesReales === 1 ? 'ENCENDIDAS' : 'APAGADAS') . "). Servidor actualizado.",
        $aulaId
    );
} else {
    $stmt = $pdo->prepare(
        'UPDATE aulas 
         SET rssi_wifi = :rssi,
             uptime_seg = :uptime,
             ultima_conexion = NOW()
         WHERE id = :id'
    );
    $stmt->execute([
        ':rssi'   => $rssi,
        ':uptime' => $uptime,
        ':id'     => $aulaId
    ]);

    if ($aulaId === 1) {
        $stmtCompat = $pdo->prepare(
            'UPDATE estado_actual 
             SET luces_reales = :luces, ip = :ip, rssi = :rssi, uptime = :uptime, version_fw = :fw, ultimo_contacto = NOW() 
             WHERE id = 1'
        );
        $stmtCompat->execute([':luces' => $lucesReales, ':ip' => $ip, ':rssi' => $rssi, ':uptime' => $uptime, ':fw' => $firmware]);
    }
}

// Si se informó un comando IR ejecutado, marcarlo en la tabla comandos_ir
if ($irEjecutado > 0) {
    $stmtIR = $pdo->prepare(
        "UPDATE comandos_ir 
         SET estado = 'ejecutado', ejecutado_en = NOW() 
         WHERE id = :id AND (aula_id = :aula_id OR aula_id IS NULL OR aula_id = 0)"
    );
    $stmtIR->execute([':id' => $irEjecutado, ':aula_id' => $aulaId]);

    registrarEvento(
        $pdo,
        'esp32',
        'COMANDO_IR_EJECUTADO',
        "Comando IR ID #{$irEjecutado} confirmado como ejecutado por el ESP32 en Aula #{$aulaId}",
        $aulaId
    );
}

header('Content-Type: text/plain; charset=utf-8');
echo "OK\n";
