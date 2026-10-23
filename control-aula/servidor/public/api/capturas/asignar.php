<?php
/**
 * POST /api/capturas/asignar
 * Asigna una captura del receptor KY-022 directamente al catálogo de codigos_ir en un solo paso.
 * Parámetros: {"captura_id": 12, "dispositivo": "aire|proyector", "accion": "prender|apagar|..."}
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
validarTokenCSRFSiEsPanel($auth);

$datos = obtenerDatosEntrada();

$capturaId = isset($datos['captura_id']) ? (int) $datos['captura_id'] : 0;
$dispositivo = isset($datos['dispositivo']) ? strtolower(trim($datos['dispositivo'])) : '';
$accion = isset($datos['accion']) ? strtolower(trim($datos['accion'])) : '';

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

// 1. Obtener los datos de la captura
$stmt = $pdo->prepare('SELECT id, protocolo, valor, bits, estado_hex, raw, creado_en FROM capturas_ir WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $capturaId]);
$captura = $stmt->fetch();

if (!$captura) {
    responderJSON(['ok' => false, 'error' => "No se encontro la captura con ID #{$capturaId}"], 404);
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
     (dispositivo, accion, protocolo, codigo, bits, estado_hex, raw, repeticiones, pausa_ms, frecuencia_khz, descripcion, actualizado_en)
     VALUES 
     (:dispositivo, :accion, :protocolo, :codigo, :bits, :estado_hex, :raw, 1, 100, 38, :descripcion, NOW())
     ON DUPLICATE KEY UPDATE
        protocolo = VALUES(protocolo),
        codigo = VALUES(codigo),
        bits = VALUES(bits),
        estado_hex = VALUES(estado_hex),
        raw = VALUES(raw),
        descripcion = VALUES(descripcion),
        actualizado_en = NOW()'
);

$stmtUpsert->execute([
    ':dispositivo' => $dispositivo,
    ':accion'      => $accion,
    ':protocolo'   => $captura['protocolo'],
    ':codigo'      => $captura['valor'],
    ':bits'        => (int) $captura['bits'],
    ':estado_hex'  => $captura['estado_hex'],
    ':raw'         => $captura['raw'],
    ':descripcion' => $descripcion
]);

registrarEvento(
    $pdo,
    $auth['origen'],
    'ASIGNAR_CAPTURA_IR',
    "Captura #{$capturaId} asignada a {$dispositivo}/{$accion} ({$captura['protocolo']}) por {$auth['usuario']}"
);

responderJSON([
    'ok' => true,
    'mensaje' => "Captura asignada exitosamente al comando '{$dispositivo}' -> '{$accion}'",
    'codigo' => [
        'dispositivo' => $dispositivo,
        'accion'      => $accion,
        'protocolo'   => $captura['protocolo'],
        'codigo'      => $captura['valor'],
        'bits'        => (int) $captura['bits'],
        'estado_hex'  => $captura['estado_hex'],
        'tiene_raw'   => !empty($captura['raw'])
    ]
]);
