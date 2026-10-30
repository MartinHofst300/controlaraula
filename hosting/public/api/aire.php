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
$temp = isset($actual['aire_temp']) ? (int) $actual['aire_temp'] : 24;
$modo = $actual['aire_modo'] ?? 'frio';
$fan = $actual['aire_fan'] ?? 'auto';
$swing = isset($actual['aire_swing']) ? (int) $actual['aire_swing'] : 0;

$accionSolicitada = isset($datos['accion']) ? strtolower(trim((string) $datos['accion'])) : '';
$accionEspecificaIR = null;

// 1. Procesar Presets Rápidos si se solicitaron
if ($accionSolicitada === 'preset_eco') {
    $encendido = 1;
    $temp = 24;
    $modo = 'frio';
    $fan = 'auto';
    $swing = 1;
    $accionEspecificaIR = 'frio_24';
} elseif ($accionSolicitada === 'preset_max_frio') {
    $encendido = 1;
    $temp = 18;
    $modo = 'frio';
    $fan = 'alto';
    $swing = 1;
    $accionEspecificaIR = 'frio_18';
} elseif ($accionSolicitada === 'preset_calor') {
    $encendido = 1;
    $temp = 26;
    $modo = 'calor';
    $fan = 'medio';
    $swing = 1;
    $accionEspecificaIR = 'calor_26';
}

// 2. Validar y aplicar temperatura (16 a 30)
if (isset($datos['temp']) || isset($datos['aire_temp']) || isset($datos['temperatura'])) {
    $t = (int) ($datos['temp'] ?? ($datos['aire_temp'] ?? $datos['temperatura']));
    if ($t < 16 || $t > 30) {
        responderJSON(['ok' => false, 'error' => 'La temperatura debe estar entre 16°C y 30°C'], 422);
    }
    $temp = $t;
}

// 3. Validar y aplicar modo ('frio', 'calor', 'ventilacion', 'seco' / 'deshumidificador', 'auto')
$modoRecibido = $datos['modo'] ?? ($datos['aire_modo'] ?? null);
if ($modoRecibido !== null && $modoRecibido !== '') {
    $m = strtolower(trim((string) $modoRecibido));
    if ($m === 'deshumidificador' || $m === 'dry') {
        $m = 'seco';
    }
    $modosValidos = ['frio', 'calor', 'ventilacion', 'seco', 'auto'];
    if (!in_array($m, $modosValidos, true)) {
        responderJSON(['ok' => false, 'error' => 'Modo de aire invalido. Opciones: frio, calor, ventilacion, seco, auto'], 422);
    }
    $modo = $m;
    if (!$accionEspecificaIR) {
        $accionEspecificaIR = "modo_{$modo}";
    }
}

// 4. Validar y aplicar velocidad de ventilador ('auto', 'bajo', 'medio', 'alto')
$fanRecibido = $datos['fan'] ?? ($datos['aire_fan'] ?? null);
if ($fanRecibido !== null && $fanRecibido !== '') {
    $f = strtolower(trim((string) $fanRecibido));
    if ($f === 'turbo') {
        $f = 'alto';
    }
    $fansValidos = ['auto', 'bajo', 'medio', 'alto'];
    if (!in_array($f, $fansValidos, true)) {
        responderJSON(['ok' => false, 'error' => 'Velocidad de ventilador invalida. Opciones: auto, bajo, medio, alto'], 422);
    }
    $fan = $f;
    if (!$accionEspecificaIR) {
        $accionEspecificaIR = "fan_{$fan}";
    }
}

// 5. Validar y aplicar swing (0 o 1)
$swingRecibido = $datos['swing'] ?? ($datos['aire_swing'] ?? null);
if ($swingRecibido !== null) {
    $swing = ((int) $swingRecibido === 1 || $swingRecibido === true || $swingRecibido === 'true') ? 1 : 0;
    if (!$accionEspecificaIR) {
        $accionEspecificaIR = $swing === 1 ? 'swing_on' : 'swing_off';
    }
}

// 6. Determinar estado encendido/apagado
if ($accionSolicitada === 'apagar') {
    $encendido = 0;
} elseif (in_array($accionSolicitada, ['prender', 'encender', 'ajustar', 'preset_eco', 'preset_max_frio', 'preset_calor'], true)) {
    $encendido = 1;
} elseif (isset($datos['encendido']) || isset($datos['aire_encendido'])) {
    $valEnc = $datos['encendido'] ?? $datos['aire_encendido'];
    $encendido = ((int) $valEnc === 1 || $valEnc === true || $valEnc === 'true') ? 1 : 0;
} else {
    $encendido = 1;
}

$ultimaAccion = ($encendido === 1) ? 'prender' : 'apagar';

// 7. Actualizar inmediatamente todas las columnas reales en MySQL para esta aula
actualizarEstadoAula($pdo, $aulaId, [
    'aire_encendido'     => $encendido,
    'ultima_accion_aire' => $ultimaAccion,
    'aire_temp'          => $temp,
    'aire_modo'          => $modo,
    'aire_fan'           => $fan,
    'aire_swing'         => $swing
]);

// 8. Buscar en codigos_ir el comando específico y encolar la orden real en comandos_ir para el ESP32 de esta aula
if ($encendido === 0) {
    $accionPrincipalIR = 'apagar';
    $fallbacksIR = ['apagar'];
} else {
    $combinacionTempModo = "{$modo}_{$temp}";
    $accionPrincipalIR = $combinacionTempModo;
    $fallbacksIR = array_filter([
        $accionEspecificaIR,
        "modo_{$modo}",
        "fan_{$fan}",
        $swing === 1 ? 'swing_on' : 'swing_off',
        'prender'
    ]);
}

$acState = [
    'marca'     => $actual['marca_aire'] ?? 'Star (OEM)',
    'protocolo' => $actual['protocolo_aire'] ?? 'COOLIX',
    'power'     => ($encendido === 1),
    'temp'      => $temp,
    'modo'      => $modo,
    'fan'       => $fan,
    'swing'     => ($swing === 1)
];

$infoIR = encolarComandoIRSiExiste($pdo, 'aire', $accionPrincipalIR, $fallbacksIR, $aulaId, $acState);

$swingTexto = $swing === 1 ? 'ON' : 'OFF';
$resumenEstado = ($encendido === 0)
    ? "Aire acondicionado APAGADO (Aula #{$aulaId})"
    : "Aire ENCENDIDO: {$temp}°C | Modo: " . strtoupper($modo) . " | Fan: " . strtoupper($fan) . " | Swing: {$swingTexto} (Aula #{$aulaId})";

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
    'aire_temp' => $temp,
    'aire_modo' => $modo,
    'aire_fan' => $fan,
    'aire_swing' => $swing,
    'ac_state' => $acState,
    'marca_aire' => $actual['marca_aire'] ?? 'Star (OEM)',
    'protocolo_aire' => $actual['protocolo_aire'] ?? 'COOLIX',
    'protocolo' => $infoIR['protocolo'],
    'codigo' => $infoIR['codigo'],
    'expira_en_segundos' => EXPIRACION_COMANDO_SEGUNDOS
]);
