<?php
/**
 * GET o POST /api/esp32/sincronizar
 * Endpoint consultado periódicamente por el ESP32 o el Simulador Web (Multi-Aula).
 * - Autenticación: vía cabecera X-API-Key individual por aula (o API Key global + aula_id opcional).
 * - En POST unificado: el ESP32 reporta telemetría (luces, ip, rssi, uptime, fw, confirmaciones)
 *   y recibe el estado completo del aula respectiva (luces, aire, proyector) y el próximo comando IR encolado para esa aula.
 * - Soporta respuesta en JSON (si se envía ?formato=json o cabecera Accept: application/json)
 *   y respuesta en texto plano clave=valor para el firmware C++ del ESP32.
 */

require_once __DIR__ . '/../../../app/config.php';
require_once __DIR__ . '/../../../app/db.php';
require_once __DIR__ . '/../../../app/auth.php';
require_once __DIR__ . '/../../../app/funciones.php';

habilitarCORS();

$metodo = $_SERVER['REQUEST_METHOD'];
if ($metodo !== 'GET' && $metodo !== 'POST') {
    http_response_code(405);
    header('Content-Type: text/plain; charset=utf-8');
    echo "ERROR: Metodo no permitido\n";
    exit;
}

$pdo = obtenerConexionDB();
asegurarColumnasExtendidas($pdo);

// Autenticar la solicitud del ESP32 identificando su aula asignada
$aula = autenticarESP32($pdo);
$aulaId = (int) $aula['id'];

// 1. Procesar telemetría y confirmaciones si vino vía POST
if ($metodo === 'POST') {
    $datos = obtenerDatosEntrada();

    $lucesReales = isset($datos['luces']) ? (int) $datos['luces'] : null;
    $ip = isset($datos['ip']) ? substr(trim($datos['ip']), 0, 45) : obtenerIPCliente();
    $rssi = isset($datos['rssi']) ? (int) $datos['rssi'] : null;
    $uptime = isset($datos['uptime']) ? (int) $datos['uptime'] : 0;
    $firmware = isset($datos['firmware']) ? substr(trim($datos['firmware']), 0, 20) : '1.0.0';
    $irEjecutado = isset($datos['ir_ejecutado']) ? (int) $datos['ir_ejecutado'] : 0;
    $cambioLocal = isset($datos['cambio_local']) && ((int) $datos['cambio_local'] === 1);

    if ($lucesReales !== null) {
        if ($cambioLocal) {
            $stmtUpdAula = $pdo->prepare(
                'UPDATE aulas 
                 SET luces = :luces,
                     rssi_wifi = :rssi,
                     uptime_seg = :uptime,
                     ultima_conexion = NOW()
                 WHERE id = :id'
            );
            $stmtUpdAula->execute([
                ':luces'  => $lucesReales,
                ':rssi'   => $rssi,
                ':uptime' => $uptime,
                ':id'     => $aulaId
            ]);

            if ($aulaId === 1) {
                $stmtCompat = $pdo->prepare(
                    'UPDATE estado_actual 
                     SET luces = :luces, luces_reales = :luces, luces_deseadas = :luces, ip = :ip, rssi = :rssi, uptime = :uptime, version_fw = :fw, ultimo_contacto = NOW() 
                     WHERE id = 1'
                );
                $stmtCompat->execute([':luces' => $lucesReales, ':ip' => $ip, ':rssi' => $rssi, ':uptime' => $uptime, ':fw' => $firmware]);
            }

            $textoEstado = $lucesReales === 1 ? 'ENCENDIDAS' : 'APAGADAS';
            registrarEvento(
                $pdo,
                'esp32',
                'LUCES_CAMBIO_LOCAL',
                "Luces modificadas localmente en Aula #{$aulaId} ({$textoEstado}). Servidor actualizado.",
                $aulaId
            );
        } else {
            $stmtUpdAula = $pdo->prepare(
                'UPDATE aulas 
                 SET rssi_wifi = :rssi,
                     uptime_seg = :uptime,
                     ultima_conexion = NOW()
                 WHERE id = :id'
            );
            $stmtUpdAula->execute([
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
    } else {
        // Actualizar último contacto en aulas
        $pdo->prepare('UPDATE aulas SET ultima_conexion = NOW() WHERE id = :id')->execute([':id' => $aulaId]);
        if ($aulaId === 1) {
            $pdo->prepare('UPDATE estado_actual SET ultimo_contacto = NOW() WHERE id = 1')->execute();
        }
    }

    // Confirmación de comando IR ejecutado por ID
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
} else {
    // Si fue GET, actualizar marca de última conexión
    $pdo->prepare('UPDATE aulas SET ultima_conexion = NOW() WHERE id = :id')->execute([':id' => $aulaId]);
    if ($aulaId === 1) {
        $pdo->prepare('UPDATE estado_actual SET ultimo_contacto = NOW() WHERE id = 1')->execute();
    }
}

// 2. Expirar comandos que lleven más de 30 segundos pendientes
expirarComandosViejos($pdo);

// 3. Obtener el estado completo de ESTA aula desde MySQL
$estado = obtenerEstadoAula($pdo, $aulaId);
if (!$estado) {
    http_response_code(404);
    echo "ERROR: Aula no encontrada\n";
    exit;
}

$lucesDeseadas = (int) ($estado['luces'] ?? 0);
$aireEncendido = (int) ($estado['aire_encendido'] ?? 0);
$aireTemp = (int) ($estado['aire_temp'] ?? 24);
$aireModo = $estado['aire_modo'] ?? 'frio';
$aireFan = $estado['aire_fan'] ?? 'auto';
$aireSwing = (int) ($estado['aire_swing'] ?? 0);

$proyectorEncendido = (int) ($estado['proyector_encendido'] ?? 0);
$proyectorFuente = $estado['proyector_fuente'] ?? 'hdmi1';
$proyectorFreeze = (int) ($estado['proyector_freeze'] ?? 0);
$proyectorMute = (int) ($estado['proyector_mute'] ?? 0);
$proyectorVolumen = (int) ($estado['proyector_volumen'] ?? 50);

// 4. Buscar el próximo comando IR pendiente exclusivo para ESTA AULA
$stmtComando = $pdo->prepare(
    "SELECT id, dispositivo, accion, protocolo, COALESCE(codigo, codigo_hex) AS codigo, bits, estado_hex, raw, repeticiones, pausa_ms, frecuencia_khz, ac_state 
     FROM comandos_ir 
     WHERE estado = 'pendiente' AND (aula_id = :aula_id OR aula_id IS NULL OR aula_id = 0)
     ORDER BY id ASC 
     LIMIT 1"
);
$stmtComando->execute([':aula_id' => $aulaId]);
$comando = $stmtComando->fetch();

$irId = 0;
if ($comando) {
    $irId = (int) $comando['id'];
    $stmtUpdate = $pdo->prepare("UPDATE comandos_ir SET estado = 'entregado', entregado_en = NOW() WHERE id = :id");
    $stmtUpdate->execute([':id' => $irId]);
}

// Contar cuántos comandos más quedan pendientes en la cola para esta aula
$stmtRestantes = $pdo->prepare("SELECT COUNT(*) FROM comandos_ir WHERE estado = 'pendiente' AND (aula_id = :aula_id OR aula_id IS NULL OR aula_id = 0)");
$stmtRestantes->execute([':aula_id' => $aulaId]);
$pendientesRestantes = (int) $stmtRestantes->fetchColumn();

// Verificar si el cliente solicitó respuesta en formato JSON (Simulador o cliente HTTP JSON)
$quiereJson = (isset($_GET['formato']) && strtolower($_GET['formato']) === 'json')
    || (isset($_POST['formato']) && strtolower($_POST['formato']) === 'json')
    || (isset($_SERVER['HTTP_ACCEPT']) && stripos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

if ($quiereJson) {
    $comandoSalida = $comando ?: null;
    if ($comandoSalida && !empty($comandoSalida['ac_state'])) {
        $comandoSalida['ac_state_obj'] = json_decode($comandoSalida['ac_state'], true);
    }

    responderJSON([
        'ok' => true,
        'aula_id' => $aulaId,
        'aula_nombre' => $aula['nombre'],
        'hardware' => [
            'marca_aire' => $estado['marca_aire'] ?? 'Star (OEM)',
            'protocolo_aire' => $estado['protocolo_aire'] ?? 'COOLIX',
            'marca_proyector' => $estado['marca_proyector'] ?? 'Epson',
            'protocolo_proyector' => $estado['protocolo_proyector'] ?? 'EPSON_NEC'
        ],
        'luces' => $lucesDeseadas,
        'aire' => [
            'encendido' => $aireEncendido,
            'temp' => $aireTemp,
            'modo' => $aireModo,
            'fan' => $aireFan,
            'swing' => $aireSwing
        ],
        'proyector' => [
            'encendido' => $proyectorEncendido,
            'fuente' => $proyectorFuente,
            'freeze' => $proyectorFreeze,
            'mute' => $proyectorMute,
            'volumen' => $proyectorVolumen
        ],
        'ir_id' => $irId,
        'comando_ir' => $comandoSalida,
        'cola_pendientes_restantes' => $pendientesRestantes
    ]);
}

// 5. Respuesta en texto plano clave=valor (compatible con el parser de firmware del ESP32)
header('Content-Type: text/plain; charset=utf-8');
echo "aula_id={$aulaId}\n";
echo "marca_aire=" . ($estado['marca_aire'] ?? 'Star (OEM)') . "\n";
echo "protocolo_aire=" . ($estado['protocolo_aire'] ?? 'COOLIX') . "\n";
echo "marca_proyector=" . ($estado['marca_proyector'] ?? 'Epson') . "\n";
echo "protocolo_proyector=" . ($estado['protocolo_proyector'] ?? 'EPSON_NEC') . "\n";
echo "luces={$lucesDeseadas}\n";
echo "aire_encendido={$aireEncendido}\n";
echo "aire_temp={$aireTemp}\n";
echo "aire_modo={$aireModo}\n";
echo "aire_fan={$aireFan}\n";
echo "aire_swing={$aireSwing}\n";
echo "proyector_encendido={$proyectorEncendido}\n";
echo "proyector_fuente={$proyectorFuente}\n";
echo "proyector_freeze={$proyectorFreeze}\n";
echo "proyector_mute={$proyectorMute}\n";
echo "proyector_volumen={$proyectorVolumen}\n";
echo "ir_id={$irId}\n";

if ($irId > 0 && $comando) {
    echo "ir_disp={$comando['dispositivo']}\n";
    echo "ir_accion={$comando['accion']}\n";
    if (!empty($comando['protocolo'])) {
        echo "ir_proto={$comando['protocolo']}\n";
    }
    if (!empty($comando['codigo'])) {
        echo "ir_code={$comando['codigo']}\n";
    }
    if (!empty($comando['bits']) && (int) $comando['bits'] > 0) {
        echo "ir_bits={$comando['bits']}\n";
    }
    if (!empty($comando['estado_hex'])) {
        echo "ir_state={$comando['estado_hex']}\n";
    }
    if (!empty($comando['raw'])) {
        echo "ir_raw={$comando['raw']}\n";
    }
    if (!empty($comando['repeticiones']) && (int) $comando['repeticiones'] > 1) {
        echo "ir_rep={$comando['repeticiones']}\n";
    }
    if (!empty($comando['pausa_ms']) && (int) $comando['pausa_ms'] != 100) {
        echo "ir_pausa={$comando['pausa_ms']}\n";
    } elseif (!empty($comando['repeticiones']) && (int) $comando['repeticiones'] > 1) {
        echo "ir_pausa={$comando['pausa_ms']}\n";
    }
    if (!empty($comando['frecuencia_khz']) && (int) $comando['frecuencia_khz'] != 38) {
        echo "ir_khz={$comando['frecuencia_khz']}\n";
    }
    if (!empty($comando['ac_state'])) {
        echo "ir_ac_state={$comando['ac_state']}\n";
    }
}
