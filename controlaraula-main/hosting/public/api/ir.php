<?php
/**
 * POST /api/ir
 * Encola un comando infrarrojo para cualquier dispositivo (aire, proyector u otros) para un aula específica (Multi-Aula).
 * Parámetros: {"dispositivo": "aire|proyector", "accion": "prender|apagar|...", "aula_id": 1}
 * Busca primero códigos específicos del aula y si no existen recurre a la plantilla global.
 * Copia todos los parámetros de transmisión (protocolo, código, bits, estado_hex, raw, repeticiones, pausa, frecuencia).
 * El comando expira automáticamente si el ESP32 de esa aula no lo toma.
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
$aulaId = determinarAulaIdPeticion($auth, $datos);

if (empty($dispositivo)) {
    responderJSON(['ok' => false, 'error' => 'El parametro "dispositivo" es obligatorio (ej. "aire" o "proyector")'], 400);
}

if (empty($accion)) {
    responderJSON(['ok' => false, 'error' => 'El parametro "accion" es obligatorio (ej. "prender" o "apagar")'], 400);
}

$pdo = obtenerConexionDB();
asegurarColumnasExtendidas($pdo);

// Validar permisos
verificarPermisoAula($pdo, (int)($auth['usuario_id'] ?? 0), $auth['rol'] ?? 'profesor', $aulaId);

// Expirar comandos viejos antes de encolar
expirarComandosViejos($pdo);

// Buscar el código en codigos_ir dando prioridad al aula solicitada sobre la plantilla global
$stmt = $pdo->prepare(
    "SELECT * FROM codigos_ir 
     WHERE dispositivo = :dispositivo 
       AND accion = :accion 
       AND (aula_id = :aula_id OR aula_id IS NULL OR aula_id = 0)
     ORDER BY (CASE WHEN aula_id = :aula_id_ord THEN 0 ELSE 1 END) ASC 
     LIMIT 1"
);
$stmt->execute([
    ':dispositivo'   => $dispositivo,
    ':accion'        => $accion,
    ':aula_id'       => $aulaId,
    ':aula_id_ord'   => $aulaId
]);
$codigoInfo = $stmt->fetch();

if (!$codigoInfo) {
    responderJSON([
        'ok' => false,
        'error' => "No se encontro un codigo IR configurado para '{$dispositivo}' y accion '{$accion}' en el aula #{$aulaId} ni en la plantilla global"
    ], 404);
}

$codigoHex = $codigoInfo['codigo'] ?? ($codigoInfo['codigo_hex'] ?? '');

// Encolar comando copiando todos los datos e identificando el aula_id
$stmtInsert = $pdo->prepare(
    'INSERT INTO comandos_ir 
     (aula_id, dispositivo, accion, protocolo, codigo, bits, estado_hex, raw, repeticiones, pausa_ms, frecuencia_khz, estado, creado_en) 
     VALUES 
     (:aula_id, :dispositivo, :accion, :protocolo, :codigo, :bits, :estado_hex, :raw, :repeticiones, :pausa_ms, :frecuencia_khz, "pendiente", NOW())'
);

$stmtInsert->execute([
    ':aula_id'        => $aulaId,
    ':dispositivo'    => $dispositivo,
    ':accion'         => $accion,
    ':protocolo'      => $codigoInfo['protocolo'],
    ':codigo'         => $codigoHex,
    ':bits'           => (int) ($codigoInfo['bits'] ?? 0),
    ':estado_hex'     => $codigoInfo['estado_hex'] ?? null,
    ':raw'            => $codigoInfo['raw'] ?? null,
    ':repeticiones'   => (int) ($codigoInfo['repeticiones'] ?? 1),
    ':pausa_ms'       => (int) ($codigoInfo['pausa_ms'] ?? 0),
    ':frecuencia_khz' => (int) ($codigoInfo['frecuencia_khz'] ?? 38)
]);

$comandoId = (int) $pdo->lastInsertId();

// Actualizar última acción en el aula
if ($dispositivo === 'aire') {
    actualizarEstadoAula($pdo, $aulaId, ['ultima_accion_aire' => $accion]);
} elseif ($dispositivo === 'proyector') {
    actualizarEstadoAula($pdo, $aulaId, ['ultima_accion_proyector' => $accion]);
}

registrarEvento(
    $pdo,
    $auth['origen'],
    'ENCOLAR_COMANDO_IR',
    "Comando '{$accion}' para '{$dispositivo}' encolado (ID: {$comandoId}, Proto: {$codigoInfo['protocolo']}) por {$auth['usuario']} (Aula #{$aulaId})",
    $aulaId
);

responderJSON([
    'ok' => true,
    'aula_id' => $aulaId,
    'mensaje' => "Comando '{$accion}' para '{$dispositivo}' encolado en Aula #{$aulaId}",
    'comando_id' => $comandoId,
    'dispositivo' => $dispositivo,
    'accion' => $accion,
    'protocolo' => $codigoInfo['protocolo'],
    'codigo' => $codigoHex,
    'bits' => (int) ($codigoInfo['bits'] ?? 0),
    'repeticiones' => (int) ($codigoInfo['repeticiones'] ?? 1),
    'pausa_ms' => (int) ($codigoInfo['pausa_ms'] ?? 0),
    'frecuencia_khz' => (int) ($codigoInfo['frecuencia_khz'] ?? 38),
    'es_global' => empty($codigoInfo['aula_id']),
    'expira_en_segundos' => EXPIRACION_COMANDO_SEGUNDOS
]);
