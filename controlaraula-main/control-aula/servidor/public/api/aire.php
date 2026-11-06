<?php
/**
 * POST /api/aire
 * Control integral de Aire Acondicionado en Backend (MySQL + Cola IR hacia ESP32 + Auditoría) para Multi-Aula:
 *  - Encendido / Apagado (`aire_encendido`: 0/1, `accion`: "prender", "encender", "apagar", "ajustar")
 *  - Temperatura (`aire_temp`: 16°C a 30°C)
 *  - Modo (`aire_modo`: "frio", "calor", "ventilacion", "seco" / "deshumidificador", "auto")
 *  - Velocidad de ventilador (`aire_fan`: "auto", "bajo", "medio", "alto")
 *  - Oscilación / Deflector (`aire_swing`: 0 o 1)
 *  - Presets rápidos ("preset_eco", "preset_max_frio", "preset_calor")
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
$pdo = obtenerConexionDB();

asegurarColumnasExtendidas($pdo);
expirarComandosViejos($pdo);

$aulaId = determinarAulaIdPeticion($auth, $datos);

// Validar permisos sobre el aula
verificarPermisoAula($pdo, (int)($auth['usuario_id'] ?? 0), $auth['rol'] ?? 'profesor', $aulaId);

// Obtener estado actual en MySQL del aula solicitada
$actual = obtenerEstadoAula($pdo, $aulaId);
if (!$actual) {
    responderJSON(['ok' => false, 'error' => 'Aula no encontrada'], 404);
}

$encendido = isset($actual['aire_encendido']) ? (int) $actual['aire_encendido'] : 0;
$accionSolicitada = isset($datos['accion']) ? strtolower(trim((string) $datos['accion'])) : '';

// Determinar estado encendido/apagado (solo se permite Encender o Apagar)
if ($accionSolicitada === 'apagar') {
    $encendido = 0;
} elseif (in_array($accionSolicitada, ['prender', 'encender'], true)) {
    $encendido = 1;
} elseif (isset($datos['encendido']) || isset($datos['aire_encendido'])) {
    $valEnc = $datos['encendido'] ?? $datos['aire_encendido'];
    $encendido = ((int) $valEnc === 1 || $valEnc === true || $valEnc === 'true') ? 1 : 0;
} else {
    $encendido = 1;
}

$ultimaAccion = ($encendido === 1) ? 'prender' : 'apagar';

// Actualizar inmediatamente en MySQL para esta aula
actualizarEstadoAula($pdo, $aulaId, [
    'aire_encendido'     => $encendido,
    'ultima_accion_aire' => $ultimaAccion
]);

// Encolar comando IR exclusivo: prender o apagar
$accionPrincipalIR = $ultimaAccion;
$fallbacksIR = [$ultimaAccion];

$infoIR = encolarComandoIRSiExiste($pdo, 'aire', $accionPrincipalIR, $fallbacksIR, $aulaId, null);

$resumenEstado = ($encendido === 0)
    ? "Aire acondicionado APAGADO (Aula #{$aulaId})"
    : "Aire acondicionado ENCENDIDO (Aula #{$aulaId})";

registrarEvento(
    $pdo,
    $auth['origen'],
    'CONTROL_AIRE',
    "{$resumenEstado} (Comando IR #{$infoIR['comando_id']} [{$accionPrincipalIR}]) ejecutado por {$auth['usuario']}",
    $aulaId
);

responderJSON([
    'ok' => true,
    'aula_id' => $aulaId,
    'mensaje' => $resumenEstado,
    'comando_id' => $infoIR['comando_id'],
    'ir_encolado' => $infoIR['encolado'],
    'dispositivo' => 'aire',
    'accion' => $ultimaAccion,
    'aire_encendido' => $encendido,
    'marca_aire' => 'BGH',
    'protocolo_aire' => 'COOLIX',
    'protocolo' => $infoIR['protocolo'],
    'codigo' => $infoIR['codigo'],
    'expira_en_segundos' => EXPIRACION_COMANDO_SEGUNDOS
]);
