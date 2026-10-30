<?php
/**
 * POST /api/luces
 * Actualiza el estado de las luces en MySQL para el aula especificada (Multi-Aula).
 * El ESP32 del aula se sincroniza con este estado en su siguiente polling.
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

$valorEntrada = $datos['estado'] ?? ($datos['luces'] ?? null);
if ($valorEntrada === null) {
    responderJSON(['ok' => false, 'error' => 'El parametro "estado" o "luces" es obligatorio (1 o 0)'], 400);
}

$nuevoEstado = (int) $valorEntrada;
if ($nuevoEstado !== 0 && $nuevoEstado !== 1) {
    responderJSON(['ok' => false, 'error' => 'Valor de estado invalido. Debe ser 0 o 1'], 422);
}

$aulaId = determinarAulaIdPeticion($auth, $datos);

$pdo = obtenerConexionDB();
asegurarColumnasExtendidas($pdo);

// Validar permisos sobre el aula
verificarPermisoAula($pdo, (int)($auth['usuario_id'] ?? 0), $auth['rol'] ?? 'profesor', $aulaId);

// Actualizar estado en MySQL
actualizarEstadoAula($pdo, $aulaId, [
    'luces' => $nuevoEstado
]);

$textoAccion = $nuevoEstado === 1 ? 'ENCENDER' : 'APAGAR';
registrarEvento(
    $pdo,
    $auth['origen'],
    'CAMBIO_LUCES',
    "Luces cambiadas a {$textoAccion} ({$nuevoEstado}) por {$auth['usuario']} (Aula #{$aulaId})",
    $aulaId
);

responderJSON([
    'ok' => true,
    'aula_id' => $aulaId,
    'mensaje' => $nuevoEstado === 1 ? 'Luces ENCENDIDAS' : 'Luces APAGADAS',
    'luces' => $nuevoEstado,
    'luces_deseadas' => $nuevoEstado,
    'luces_reales' => $nuevoEstado
]);
