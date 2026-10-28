<?php
/**
 * POST /api/aire
 * Endpoint de compatibilidad para el aire acondicionado.
 * Delega internamente en el nuevo mecanismo multidispositivo (dispositivo: "aire").
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

if (empty($datos['accion'])) {
    responderJSON(['ok' => false, 'error' => 'El parametro "accion" es obligatorio ("prender" o "apagar")'], 400);
}

$accion = strtolower(trim($datos['accion']));
$pdo = obtenerConexionDB();

// Expirar comandos viejos antes de encolar
expirarComandosViejos($pdo);

// Buscar el código en codigos_ir para dispositivo 'aire'
$stmt = $pdo->prepare('SELECT * FROM codigos_ir WHERE dispositivo = "aire" AND accion = :accion LIMIT 1');
$stmt->execute([':accion' => $accion]);
$codigoInfo = $stmt->fetch();

if (!$codigoInfo) {
    responderJSON([
        'ok' => false,
        'error' => "No se encontro un codigo IR configurado para el aire acondicionado y accion '{$accion}'"
    ], 404);
}

// Insertar comando copiando todos los datos
$stmtInsert = $pdo->prepare(
    'INSERT INTO comandos_ir 
     (dispositivo, accion, protocolo, codigo, bits, estado_hex, raw, repeticiones, pausa_ms, frecuencia_khz, estado, creado_en) 
     VALUES 
     ("aire", :accion, :protocolo, :codigo, :bits, :estado_hex, :raw, :repeticiones, :pausa_ms, :frecuencia_khz, "pendiente", NOW())'
);

$stmtInsert->execute([
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

// Actualizar última acción en estado_actual
$stmtEstado = $pdo->prepare('UPDATE estado_actual SET ultima_accion_aire = :accion WHERE id = 1');
$stmtEstado->execute([':accion' => $accion]);

registrarEvento(
    $pdo,
    $auth['origen'],
    'ENCOLAR_COMANDO_AIRE',
    "Comando {$accion} encolado para aire (ID: {$comandoId}, Proto: {$codigoInfo['protocolo']}) por {$auth['usuario']}"
);

responderJSON([
    'ok' => true,
    'mensaje' => "Comando '{$accion}' de aire acondicionado encolado correctamente",
    'comando_id' => $comandoId,
    'dispositivo' => 'aire',
    'accion' => $accion,
    'protocolo' => $codigoInfo['protocolo'],
    'codigo' => $codigoInfo['codigo'],
    'bits' => (int) $codigoInfo['bits'],
    'repeticiones' => (int) $codigoInfo['repeticiones'],
    'pausa_ms' => (int) $codigoInfo['pausa_ms'],
    'frecuencia_khz' => (int) $codigoInfo['frecuencia_khz'],
    'expira_en_segundos' => EXPIRACION_COMANDO_SEGUNDOS
]);
