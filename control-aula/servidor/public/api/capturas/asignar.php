<?php
/**
 * POST /api/capturas/asignar
 * Asigna una captura del receptor KY-022 directamente al catálogo de codigos_ir en un solo paso,
 * permitiendo asociarla a una aula específica o a la plantilla global.
 * Parámetros: {"captura_id": 12, "dispositivo": "aire|proyector", "accion": "prender|apagar|...", "aula_id": 1}
 */

require_once __DIR__ . '/../../../app/config.php';
require_once __DIR__ . '/../../../app/db.php';
require_once __DIR__ . '/../../../app/auth.php';
require_once __DIR__ . '/../../../app/funciones.php';

habilitarCORS();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderJSON(['ok' => false, 'error' => 'Metodo no permitido. Se requiere POST'], 405);
}

$auth = autenticarPanelOApp();
requerirRolAdmin($auth);
validarTokenCSRFSiEsPanel($auth);

$datos = obtenerDatosEntrada();

$capturaId = isset($datos['captura_id']) ? (int) $datos['captura_id'] : 0;
$dispositivo = isset($datos['dispositivo']) ? strtolower(trim($datos['dispositivo'])) : '';
$accion = isset($datos['accion']) ? strtolower(trim($datos['accion'])) : '';
$aulaId = isset($datos['aula_id']) && (int)$datos['aula_id'] > 0 ? (int)$datos['aula_id'] : null;

if ($capturaId <= 0) {
    responderJSON(['ok' => false, 'error' => 'El parametro "captura_id" es obligatorio y debe ser mayor a 0'], 400);
}

if (empty($dispositivo) || !preg_match('/^[a-z0-9_-]{2,50}$/', $dispositivo)) {
    responderJSON(['ok' => false, 'error' => 'El parametro "dispositivo" es obligatorio (ej. "aire" o "proyector")'], 400);
}

if (empty($accion) || !preg_match('/^[a-z0-9_-]{2,50}$/', $accion)) {
    responderJSON(['ok' => false, 'error' => 'El parametro "accion" es obligatorio (ej. "prender" o "apagar")'], 400);
}

$pdo = obtenerConexionDB();
asegurarColumnasExtendidas($pdo);

// 1. Obtener los datos de la captura
$stmt = $pdo->prepare('SELECT id, protocolo, valor, bits, estado_hex, raw, aula_id, creado_en FROM capturas_ir WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $capturaId]);
$captura = $stmt->fetch();

if (!$captura) {
    responderJSON(['ok' => false, 'error' => "No se encontro la captura con ID #{$capturaId}"], 404);
}

// Si no se pasó aula_id explícito, heredar el aula_id de la captura si lo tiene
if ($aulaId === null && !empty($captura['aula_id'])) {
    $aulaId = (int) $captura['aula_id'];
}

// 2. Asegurar existencia del dispositivo en dispositivos_ir
$stmtDisp = $pdo->prepare('INSERT INTO dispositivos_ir (nombre, etiqueta) VALUES (:nombre, :etiqueta) ON DUPLICATE KEY UPDATE `id` = `id`');
$stmtDisp->execute([
    ':nombre'   => $dispositivo,
    ':etiqueta' => ucfirst($dispositivo)
]);

// 3. Insertar o actualizar en codigos_ir
$descripcion = "Asignado desde captura KY-022 ID #{$capturaId} (" . date('d/m/Y H:i') . ")";

$stmtUpsert = $pdo->prepare(
    'INSERT INTO codigos_ir 
     (dispositivo, accion, protocolo, codigo, codigo_hex, bits, estado_hex, raw, repeticiones, pausa_ms, frecuencia_khz, descripcion, aula_id, actualizado_en)
     VALUES 
     (:dispositivo, :accion, :protocolo, :codigo, :codigo_hex, :bits, :estado_hex, :raw, 1, 100, 38, :descripcion, :aula_id, NOW())
     ON DUPLICATE KEY UPDATE
        protocolo = VALUES(protocolo),
        codigo = VALUES(codigo),
        codigo_hex = VALUES(codigo_hex),
        bits = VALUES(bits),
        estado_hex = VALUES(estado_hex),
        raw = VALUES(raw),
        descripcion = VALUES(descripcion),
        actualizado_en = NOW()'
);

$stmtUpsert->execute([
    ':dispositivo'  => $dispositivo,
    ':accion'       => $accion,
    ':protocolo'    => $captura['protocolo'],
    ':codigo'       => $captura['valor'],
    ':codigo_hex'   => $captura['valor'],
    ':bits'         => (int) $captura['bits'],
    ':estado_hex'   => $captura['estado_hex'],
    ':raw'          => $captura['raw'],
    ':descripcion'  => $descripcion,
    ':aula_id'      => $aulaId
]);

$alcance = $aulaId ? "Aula #{$aulaId}" : "Plantilla Global";
registrarEvento(
    $pdo,
    $auth['origen'],
    'ASIGNAR_CAPTURA_IR',
    "Captura #{$capturaId} asignada a {$dispositivo}/{$accion} ({$captura['protocolo']}) en {$alcance} por {$auth['usuario']}",
    $aulaId
);

responderJSON([
    'ok' => true,
    'mensaje' => "Captura asignada exitosamente al comando '{$dispositivo}' -> '{$accion}' ({$alcance})",
    'codigo' => [
        'dispositivo' => $dispositivo,
        'accion'      => $accion,
        'protocolo'   => $captura['protocolo'],
        'codigo'      => $captura['valor'],
        'bits'        => (int) $captura['bits'],
        'estado_hex'  => $captura['estado_hex'],
        'tiene_raw'   => !empty($captura['raw']),
        'aula_id'     => $aulaId,
        'es_global'   => empty($aulaId)
    ]
]);
