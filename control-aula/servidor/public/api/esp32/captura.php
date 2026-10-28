<?php
/**
 * POST /api/esp32/captura
 * Endpoint donde el ESP32 envía los códigos o tramas infrarrojas capturadas por el KY-022.
 * Soporta valores simples (hex), estados de trama larga (estado_hex) y secuencias en microsegundos (raw).
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

autenticarESP32();
$datos = obtenerDatosEntrada();

$protocolo = isset($datos['protocolo']) ? strtoupper(substr(trim($datos['protocolo']), 0, 30)) : 'DESCONOCIDO';
$valor = !empty($datos['valor']) ? substr(trim($datos['valor']), 0, 50) : null;
$bits = isset($datos['bits']) ? (int) $datos['bits'] : 0;
$estadoHex = !empty($datos['estado_hex']) ? trim($datos['estado_hex']) : null;
$raw = !empty($datos['raw']) ? trim($datos['raw']) : null;

// Debe venir al menos una representación válida del código
if (empty($valor) && empty($estadoHex) && empty($raw)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo "ERROR: Captura vacia (sin valor, estado ni raw)\n";
    exit;
}

$pdo = obtenerConexionDB();

$stmt = $pdo->prepare(
    'INSERT INTO capturas_ir (protocolo, valor, bits, estado_hex, raw, creado_en) 
     VALUES (:protocolo, :valor, :bits, :estado_hex, :raw, NOW())'
);
$stmt->execute([
    ':protocolo'  => $protocolo,
    ':valor'      => $valor,
    ':bits'       => $bits,
    ':estado_hex' => $estadoHex,
    ':raw'        => $raw
]);

$capturaId = (int) $pdo->lastInsertId();

$detalleLog = "Captura #{$capturaId}: {$protocolo}";
if (!empty($valor)) $detalleLog .= " {$valor} ({$bits} bits)";
if (!empty($estadoHex)) $detalleLog .= " [Estado: " . substr($estadoHex, 0, 16) . "...]";
if (!empty($raw)) $detalleLog .= " [Raw activo]";

registrarEvento(
    $pdo,
    'esp32',
    'CAPTURA_IR_RECIBIDA',
    $detalleLog
);

header('Content-Type: text/plain; charset=utf-8');
echo "OK\n";
