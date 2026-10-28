<?php
/**
 * POST /api/ir
 * Encola un comando infrarrojo para cualquier dispositivo (aire, proyector u otros).
 * Parámetros: {"dispositivo": "aire|proyector", "accion": "prender|apagar|..."}
 * Copia todos los parámetros de transmisión (protocolo, código, bits, estado_hex, raw, repeticiones, pausa, frecuencia).
 * El comando expira automáticamente a los 30 segundos si el ESP32 no lo toma.
 */

require_once __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../app/db.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/funciones.php';

habilitarCORS();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderJSON(['ok' => false, 'error' => 'Metodo no permitido. Se requiere POST'], 405);
}

$auth = autenticarPanelOApp();
validarTokenCSRFSiEsPanel($auth);

$datos = obtenerDatosEntrada();

$dispositivo = isset($datos['dispositivo']) ? strtolower(trim($datos['dispositivo'])) : '';
$accion = isset($datos['accion']) ? strtolower(trim($datos['accion'])) : '';

if (empty($dispositivo)) {
    responderJSON(['ok' => false, 'error' => 'El parametro "dispositivo" es obligatorio (ej. "aire" o "proyector")'], 400);
}

if (empty($accion)) {
    responderJSON(['ok' => false, 'error' => 'El parametro "accion" es obligatorio (ej. "prender" o "apagar")'], 400);
}

$pdo = obtenerConexionDB();

// Expirar comandos viejos antes de encolar
expirarComandosViejos($pdo);

// Buscar el código en codigos_ir
$stmt = $pdo->prepare('SELECT * FROM codigos_ir WHERE dispositivo = :dispositivo AND accion = :accion LIMIT 1');
$stmt->execute([
    ':dispositivo' => $dispositivo,
    ':accion'      => $accion
]);
$codigoInfo = $stmt->fetch();

if (!$codigoInfo) {
    responderJSON([
        'ok' => false,
        'error' => "No se encontro un codigo IR configurado para el dispositivo '{$dispositivo}' y accion '{$accion}'"
    ], 404);
}

// Encolar comando copiando todos los datos
$stmtInsert = $pdo->prepare(
    'INSERT INTO comandos_ir 
     (dispositivo, accion, protocolo, codigo, bits, estado_hex, raw, repeticiones, pausa_ms, frecuencia_khz, estado, creado_en) 
     VALUES 
     (:dispositivo, :accion, :protocolo, :codigo, :bits, :estado_hex, :raw, :repeticiones, :pausa_ms, :frecuencia_khz, "pendiente", NOW())'
);

$stmtInsert->execute([
    ':dispositivo'    => $dispositivo,
    ':accion'         => $accion,
    ':protocolo'      => $codigoInfo['protocolo'],
    ':codigo'         => $codigoInfo['codigo'],
    ':bits'           => (int) $codigoInfo['bits'],
    ':estado_hex'     => $codigoInfo['estado_hex'],
    ':raw'            => $codigoInfo['raw'],
    ':repeticiones'   => (int) $codigoInfo['repeticiones'],
    ':pausa_ms'       => (int) $codigoInfo['pausa_ms'],
    ':frecuencia_khz' => (int) $codigoInfo['frecuencia_khz']
]);

$comandoId = (int) $pdo->lastInsertId();

// Actualizar última acción en estado_actual si corresponde
if ($dispositivo === 'aire') {
    $stmtEstado = $pdo->prepare('UPDATE estado_actual SET ultima_accion_aire = :accion WHERE id = 1');
    $stmtEstado->execute([':accion' => $accion]);
} elseif ($dispositivo === 'proyector') {
    $stmtEstado = $pdo->prepare('UPDATE estado_actual SET ultima_accion_proyector = :accion WHERE id = 1');
    $stmtEstado->execute([':accion' => $accion]);
}

registrarEvento(
    $pdo,
    $auth['origen'],
    'ENCOLAR_COMANDO_IR',
    "Comando '{$accion}' para '{$dispositivo}' encolado (ID: {$comandoId}, Proto: {$codigoInfo['protocolo']}) por {$auth['usuario']}"
);

responderJSON([
    'ok' => true,
    'mensaje' => "Comando '{$accion}' para '{$dispositivo}' encolado correctamente",
    'comando_id' => $comandoId,
    'dispositivo' => $dispositivo,
    'accion' => $accion,
    'protocolo' => $codigoInfo['protocolo'],
    'codigo' => $codigoInfo['codigo'],
    'bits' => (int) $codigoInfo['bits'],
    'repeticiones' => (int) $codigoInfo['repeticiones'],
    'pausa_ms' => (int) $codigoInfo['pausa_ms'],
    'frecuencia_khz' => (int) $codigoInfo['frecuencia_khz'],
    'tiene_estado' => !empty($codigoInfo['estado_hex']),
    'tiene_raw' => !empty($codigoInfo['raw']),
    'expira_en_segundos' => EXPIRACION_COMANDO_SEGUNDOS
]);
