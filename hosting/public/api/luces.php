<?php
/**
 * POST /api/luces
 * Actualiza el estado deseado de las luces (1 para encender, 0 para apagar).
 * El ESP32 se sincroniza con este estado en su siguiente polling.
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

if (!isset($datos['estado'])) {
    responderJSON(['ok' => false, 'error' => 'El parametro "estado" es obligatorio (1 o 0)'], 400);
}

$nuevoEstado = (int) $datos['estado'];
if ($nuevoEstado !== 0 && $nuevoEstado !== 1) {
    responderJSON(['ok' => false, 'error' => 'Valor de estado invalido. Debe ser 0 o 1'], 422);
}

$pdo = obtenerConexionDB();

$stmt = $pdo->prepare('UPDATE estado_actual SET luces_deseadas = :estado WHERE id = 1');
$stmt->execute([':estado' => $nuevoEstado]);

$textoAccion = $nuevoEstado === 1 ? 'ENCENDER' : 'APAGAR';
registrarEvento(
    $pdo,
    $auth['origen'],
    'CAMBIO_LUCES_DESEADAS',
    "Solicitud de {$textoAccion} luces iniciada por {$auth['usuario']}"
);

responderJSON([
    'ok' => true,
    'mensaje' => 'Estado deseado de luces actualizado exitosamente',
    'luces_deseadas' => $nuevoEstado
]);
