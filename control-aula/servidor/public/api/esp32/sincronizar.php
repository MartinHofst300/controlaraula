<?php
/**
 * GET o POST /api/esp32/sincronizar
 * Endpoint consultado periódicamente por el ESP32 (polling cada 1.5s).
 * En POST unificado: el ESP32 reporta telemetría (luces, ip, rssi, uptime, fw, confirmaciones)
 * y recibe el estado deseado de luces y el próximo comando IR en la misma respuesta HTTP.
 * Autenticación: Cabecera X-API-Key con hash_equals.
 * Respuesta: Texto plano liviano (clave=valor por línea) con claves avanzadas:
 * ir_id, ir_proto, ir_code, ir_bits, ir_state, ir_raw, ir_rep, ir_pausa, ir_khz (solo las que tengan valor).
 */

require_once __DIR__ . '/../../../app/config.php';
require_once __DIR__ . '/../../../app/db.php';
require_once __DIR__ . '/../../../app/auth.php';
require_once __DIR__ . '/../../../app/funciones.php';

habilitarCORS();

// Validar método
$metodo = $_SERVER['REQUEST_METHOD'];
if ($metodo !== 'GET' && $metodo !== 'POST') {
    http_response_code(405);
    header('Content-Type: text/plain; charset=utf-8');
    echo "ERROR: Metodo no permitido\n";
    exit;
}

// Autenticar la solicitud del ESP32
autenticarESP32();

$pdo = obtenerConexionDB();

// 1. Procesar telemetría y confirmaciones si vino vía POST
if ($metodo === 'POST') {
    $datos = obtenerDatosEntrada();

    $lucesReales = isset($datos['luces']) ? (int) $datos['luces'] : null;
    $ip = isset($datos['ip']) ? substr(trim($datos['ip']), 0, 45) : obtenerIPCliente();
    $rssi = isset($datos['rssi']) ? (int) $datos['rssi'] : null;
    $uptime = isset($datos['uptime']) ? (int) $datos['uptime'] : 0;
    $firmware = isset($datos['firmware']) ? substr(trim($datos['firmware']), 0, 20) : '1.0.0';
    $irEjecutado = isset($datos['ir_ejecutado']) ? (int) $datos['ir_ejecutado'] : 0;
    $cambioLocal = isset($datos['cambio_local']) && ((int)$datos['cambio_local'] === 1);

    if ($lucesReales !== null) {
        if ($cambioLocal) {
            // El usuario accionó las luces localmente en el aula:
            // Sincronizar tanto luces_reales como luces_deseadas para que el servidor no las revierta
            $stmtUpdate = $pdo->prepare(
                'UPDATE estado_actual 
                 SET luces_reales = :luces,
                     luces_deseadas = :luces,
                     ip = :ip,
                     rssi = :rssi,
                     uptime = :uptime,
                     version_fw = :firmware,
                     ultimo_contacto = NOW()
                 WHERE id = 1'
            );
            $stmtUpdate->execute([
                ':luces'    => $lucesReales,
                ':ip'       => $ip,
                ':rssi'     => $rssi,
                ':uptime'   => $uptime,
                ':firmware' => $firmware
            ]);

            $textoEstado = $lucesReales === 1 ? 'ENCENDIDAS' : 'APAGADAS';
            registrarEvento(
                $pdo,
                'esp32',
                'LUCES_CAMBIO_LOCAL',
                "Luces modificadas localmente en el aula ({$textoEstado}). Servidor actualizado para no revertir."
            );
        } else {
            // Reporte habitual de telemetría
            $stmtUpdate = $pdo->prepare(
                'UPDATE estado_actual 
                 SET luces_reales = :luces,
                     ip = :ip,
                     rssi = :rssi,
                     uptime = :uptime,
                     version_fw = :firmware,
                     ultimo_contacto = NOW()
                 WHERE id = 1'
            );
            $stmtUpdate->execute([
                ':luces'    => $lucesReales,
                ':ip'       => $ip,
                ':rssi'     => $rssi,
                ':uptime'   => $uptime,
                ':firmware' => $firmware
            ]);
        }
    }

    // Confirmación de comando IR ejecutado por ID
    if ($irEjecutado > 0) {
        $stmtIR = $pdo->prepare("UPDATE comandos_ir 
                                 SET estado = 'ejecutado', ejecutado_en = NOW() 
                                 WHERE id = :id");
        $stmtIR->execute([':id' => $irEjecutado]);

        registrarEvento(
            $pdo,
            'esp32',
            'COMANDO_IR_EJECUTADO',
            "Comando IR ID #{$irEjecutado} confirmado como ejecutado por el ESP32"
        );
    }
}

// 2. Expirar comandos que lleven más de 30 segundos pendientes
expirarComandosViejos($pdo);

// 3. Obtener el estado deseado actual de las luces
$stmtEstado = $pdo->query('SELECT luces_deseadas FROM estado_actual WHERE id = 1 LIMIT 1');
$lucesDeseadas = (int) $stmtEstado->fetchColumn();

// 4. Buscar el próximo comando IR pendiente (el más antiguo)
$stmtComando = $pdo->query("SELECT id, dispositivo, accion, protocolo, codigo, bits, estado_hex, raw, repeticiones, pausa_ms, frecuencia_khz 
                            FROM comandos_ir 
                            WHERE estado = 'pendiente' 
                            ORDER BY id ASC 
                            LIMIT 1");
$comando = $stmtComando->fetch();

$irId = 0;
if ($comando) {
    $irId = (int) $comando['id'];
    // Marcar el comando como entregado
    $stmtUpdate = $pdo->prepare("UPDATE comandos_ir SET estado = 'entregado', entregado_en = NOW() WHERE id = :id");
    $stmtUpdate->execute([':id' => $irId]);
}

// 5. Responder en formato de texto plano simple clave=valor
// Entregar solo las claves que tengan valor definido
header('Content-Type: text/plain; charset=utf-8');
echo "luces={$lucesDeseadas}\n";
echo "ir_id={$irId}\n";

if ($irId > 0 && $comando) {
    if (!empty($comando['protocolo'])) {
        echo "ir_proto={$comando['protocolo']}\n";
    }
    if (!empty($comando['codigo'])) {
        echo "ir_code={$comando['codigo']}\n";
    }
    if (!empty($comando['bits']) && (int)$comando['bits'] > 0) {
        echo "ir_bits={$comando['bits']}\n";
    }
    if (!empty($comando['estado_hex'])) {
        echo "ir_state={$comando['estado_hex']}\n";
    }
    if (!empty($comando['raw'])) {
        echo "ir_raw={$comando['raw']}\n";
    }
    if (!empty($comando['repeticiones']) && (int)$comando['repeticiones'] > 1) {
        echo "ir_rep={$comando['repeticiones']}\n";
    }
    if (!empty($comando['pausa_ms']) && (int)$comando['pausa_ms'] != 100) {
        echo "ir_pausa={$comando['pausa_ms']}\n";
    } elseif (!empty($comando['repeticiones']) && (int)$comando['repeticiones'] > 1) {
        echo "ir_pausa={$comando['pausa_ms']}\n";
    }
    if (!empty($comando['frecuencia_khz']) && (int)$comando['frecuencia_khz'] != 38) {
        echo "ir_khz={$comando['frecuencia_khz']}\n";
    }
}
