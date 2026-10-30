<?php
/**
 * POST /api/proyector
 * Control integral del Proyector Multimedia en Backend (MySQL + Cola IR hacia ESP32 + Auditoría) para Multi-Aula:
 *  - Encendido / Apagado (`proyector_encendido`: 0/1, `accion`: "prender", "encender", "apagar")
 *  - Fuente de entrada (`proyector_fuente`: "hdmi1", "hdmi2", "vga")
 *  - Funciones de clase (`proyector_freeze`: 0/1, `proyector_mute`: 0/1)
 *  - Volumen (`proyector_volumen`: 0 a 100, acciones "subir"/"vol_mas", "bajar"/"vol_menos", "mute"/"vol_mute")
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

$actual = obtenerEstadoAula($pdo, $aulaId);
if (!$actual) {
    responderJSON(['ok' => false, 'error' => 'Aula no encontrada'], 404);
}

$encendido = isset($actual['proyector_encendido']) ? (int) $actual['proyector_encendido'] : 0;
$ultimaAccion = $actual['ultima_accion_proyector'] ?? 'ninguna';
$fuente = $actual['proyector_fuente'] ?? 'hdmi1';
$freeze = isset($actual['proyector_freeze']) ? (int) $actual['proyector_freeze'] : 0;
$mute = isset($actual['proyector_mute']) ? (int) $actual['proyector_mute'] : 0;
$volumen = isset($actual['proyector_volumen']) ? (int) $actual['proyector_volumen'] : 50;

$accion = isset($datos['accion']) ? strtolower(trim((string) $datos['accion'])) : '';

// Soportar parámetros directos (fuente, freeze, av_mute, volumen, set_volumen, encendido)
if (empty($accion)) {
    if (isset($datos['valor']) && is_numeric($datos['valor'])) {
        $accion = 'set_volumen';
    } elseif (!empty($datos['fuente']) || !empty($datos['proyector_fuente'])) {
        $accion = 'fuente';
    } elseif (isset($datos['freeze']) || isset($datos['proyector_freeze'])) {
        $accion = 'freeze';
    } elseif (isset($datos['av_mute']) || isset($datos['proyector_mute'])) {
        $accion = 'av_mute';
    } elseif (isset($datos['volumen']) || isset($datos['proyector_volumen'])) {
        $accion = 'set_volumen';
    } elseif (isset($datos['encendido']) || isset($datos['proyector_encendido'])) {
        $valEnc = $datos['encendido'] ?? $datos['proyector_encendido'];
        $accion = ((int) $valEnc === 1 || $valEnc === true) ? 'prender' : 'apagar';
    } else {
        $accion = 'prender';
    }
}

$accionIR = $accion;
$detalleLog = '';

switch ($accion) {
    case 'prender':
    case 'encender':
        $encendido = 1;
        $ultimaAccion = 'prender';
        $freeze = 0;
        $mute = 0;
        $accionIR = 'prender';
        $detalleLog = "Proyector ENCENDIDO (Fuente: " . strtoupper($fuente) . ")";
        break;

    case 'apagar':
        $encendido = 0;
        $ultimaAccion = 'apagar';
        $freeze = 0;
        $mute = 0;
        $accionIR = 'apagar';
        $detalleLog = "Proyector APAGADO";
        break;

    case 'hdmi1':
    case 'hdmi2':
    case 'vga':
        $fuente = $accion;
        $encendido = 1;
        $ultimaAccion = 'prender';
        $accionIR = $accion;
        $detalleLog = "Fuente de proyector cambiada a " . strtoupper($fuente);
        break;

    case 'fuente':
        $f = strtolower(trim((string) ($datos['fuente'] ?? ($datos['proyector_fuente'] ?? 'hdmi1'))));
        if (!in_array($f, ['hdmi1', 'hdmi2', 'vga'], true)) {
            responderJSON(['ok' => false, 'error' => 'Fuente de proyector invalida. Opciones: hdmi1, hdmi2, vga'], 422);
        }
        $fuente = $f;
        $encendido = 1;
        $ultimaAccion = 'prender';
        $accionIR = $fuente;
        $detalleLog = "Fuente de proyector cambiada a " . strtoupper($fuente);
        break;

    case 'freeze':
        if (isset($datos['freeze']) || isset($datos['proyector_freeze'])) {
            $valF = $datos['freeze'] ?? $datos['proyector_freeze'];
            $freeze = ((int) $valF === 1 || $valF === true) ? 1 : 0;
        } else {
            $freeze = ($freeze === 1) ? 0 : 1;
        }
        $encendido = 1;
        $ultimaAccion = 'prender';
        $accionIR = 'freeze';
        $detalleLog = $freeze === 1
            ? "Imagen de proyector CONGELADA (Freeze ON)"
            : "Imagen de proyector REANUDADA (Freeze OFF)";
        break;

    case 'av_mute':
        if (isset($datos['av_mute']) || isset($datos['proyector_mute'])) {
            $valM = $datos['av_mute'] ?? $datos['proyector_mute'];
            $mute = ((int) $valM === 1 || $valM === true) ? 1 : 0;
        } else {
            $mute = ($mute === 1) ? 0 : 1;
        }
        $encendido = 1;
        $ultimaAccion = 'prender';
        $accionIR = 'av_mute';
        $detalleLog = $mute === 1
            ? "Pantalla en Negro ACTIVADA (AV Mute ON)"
            : "Pantalla en Negro DESACTIVADA (AV Mute OFF)";
        break;

    case 'vol_mas':
    case 'subir':
        $volumen = min(100, $volumen + 5);
        $accionIR = 'vol_mas';
        $detalleLog = "Volumen de proyector subido a {$volumen}%";
        break;

    case 'vol_menos':
    case 'bajar':
        $volumen = max(0, $volumen - 5);
        $accionIR = 'vol_menos';
        $detalleLog = "Volumen de proyector bajado a {$volumen}%";
        break;

    case 'vol_mute':
    case 'mute':
        if ($volumen === 0) {
            $restaurar = isset($datos['valor']) && is_numeric($datos['valor']) && (int)$datos['valor'] > 0
                ? (int)$datos['valor'] : 50;
            $volumen = min(100, max(5, $restaurar));
            $accionIR = 'vol_mas';
            $detalleLog = "Audio de proyector RESTAURADO ({$volumen}%)";
        } else {
            $volumen = 0;
            $accionIR = 'vol_mute';
            $detalleLog = "Audio de proyector SILENCIADO (MUTE)";
        }
        break;

    case 'set_volumen':
    case 'volumen':
        $valVol = $datos['valor'] ?? ($datos['volumen'] ?? ($datos['proyector_volumen'] ?? null));
        if ($valVol === 'subir' || $valVol === 'mas') {
            $volumen = min(100, $volumen + 5);
            $accionIR = 'vol_mas';
            $detalleLog = "Volumen de proyector subido a {$volumen}%";
        } elseif ($valVol === 'bajar' || $valVol === 'menos') {
            $volumen = max(0, $volumen - 5);
            $accionIR = 'vol_menos';
            $detalleLog = "Volumen de proyector bajado a {$volumen}%";
        } elseif ($valVol === 'mute' || $valVol === 'silenciar') {
            if ($volumen === 0) {
                $volumen = 50;
                $accionIR = 'vol_mas';
                $detalleLog = "Audio de proyector RESTAURADO (50%)";
            } else {
                $volumen = 0;
                $accionIR = 'vol_mute';
                $detalleLog = "Audio de proyector SILENCIADO (MUTE)";
            }
        } elseif ($valVol !== null && is_numeric($valVol)) {
            $volumen = max(0, min(100, (int) $valVol));
            $accionIR = ($volumen === 0) ? 'vol_mute' : 'vol_mas';
            $detalleLog = ($volumen === 0)
                ? "Audio de proyector SILENCIADO (MUTE)"
                : "Volumen de proyector ajustado directamente a {$volumen}%";
        } else {
            responderJSON(['ok' => false, 'error' => 'Valor de volumen invalido. Se requiere un numero entre 0 y 100'], 422);
        }
        break;

    default:
        responderJSON(['ok' => false, 'error' => 'Accion de proyector no reconocida'], 400);
}

// Actualizar columnas en MySQL para esta aula
actualizarEstadoAula($pdo, $aulaId, [
    'proyector_encendido'     => $encendido,
    'ultima_accion_proyector' => $ultimaAccion,
    'proyector_fuente'        => $fuente,
    'proyector_freeze'        => $freeze,
    'proyector_mute'          => $mute,
    'proyector_volumen'       => $volumen
]);

$fallbackIR = ($ultimaAccion === 'apagar') ? 'apagar' : 'prender';
$infoIR = encolarComandoIRSiExiste($pdo, 'proyector', $accionIR, $fallbackIR, $aulaId);

registrarEvento(
    $pdo,
    $auth['origen'],
    'CONTROL_PROYECTOR',
    "{$detalleLog} (Comando IR #{$infoIR['comando_id']} [{$accionIR}]) ejecutado por {$auth['usuario']} (Aula #{$aulaId})",
    $aulaId
);

responderJSON([
    'ok' => true,
    'aula_id' => $aulaId,
    'mensaje' => "{$detalleLog} (Aula #{$aulaId})",
    'comando_id' => $infoIR['comando_id'],
    'ir_encolado' => $infoIR['encolado'],
    'dispositivo' => 'proyector',
    'accion' => $ultimaAccion,
    'proyector_encendido' => $encendido,
    'proyector_fuente' => $fuente,
    'proyector_freeze' => $freeze,
    'proyector_mute' => $mute,
    'proyector_volumen' => $volumen,
    'marca_proyector' => $actual['marca_proyector'] ?? 'Epson',
    'protocolo_proyector' => $actual['protocolo_proyector'] ?? 'EPSON_NEC',
    'protocolo' => $infoIR['protocolo'],
    'codigo' => $infoIR['codigo'],
    'repeticiones' => $infoIR['repeticiones'],
    'pausa_ms' => $infoIR['pausa_ms'],
    'expira_en_segundos' => EXPIRACION_COMANDO_SEGUNDOS
]);
