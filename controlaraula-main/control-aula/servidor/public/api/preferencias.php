<?php
/**
 * GET / POST /api/preferencias
 * Persiste y recupera en MySQL (`usuarios.preferencias_json`) todas las opciones
 * del Centro de Configuración del usuario logueado (Profesor o Administrador):
 *  - Apariencia: tema ('oscuro'|'claro'|'auto'), acento, colorCustom, fuente, escalaPct, densidad, altoContraste, reducirAnimaciones
 *  - Aula: prefAireTemp, prefAireModo, confirmarApagado, sonidoBeep, intervaloSync
 */

require_once __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../app/db.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/funciones.php';

habilitarCORS();

$auth = autenticarPanelOApp();
$pdo = obtenerConexionDB();
asegurarColumnasExtendidas($pdo);

$usuarioId = (int) ($auth['usuario_id'] ?? 1);
if ($usuarioId <= 0) {
    $usuarioId = 1;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $prefs = obtenerPreferenciasUsuarioDB($pdo, $usuarioId);
    responderJSON([
        'ok' => true,
        'usuario_id' => $usuarioId,
        'usuario' => $auth['usuario'],
        'preferencias' => $prefs
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarTokenCSRFSiEsPanel($auth);
    $datos = obtenerDatosEntrada();

    // Permitir enviar { preferencias: {...} } o directamente las claves en la raíz del JSON
    $entradaPrefs = isset($datos['preferencias']) && is_array($datos['preferencias'])
        ? $datos['preferencias']
        : $datos;

    // Si es un restablecimiento a valores por defecto
    if (!empty($datos['reset'])) {
        $entradaPrefs = obtenerPreferenciasPorDefecto();
    } else {
        // Combinar con las preferencias actuales en BD para permitir actualizaciones parciales o completas
        $actualesDB = obtenerPreferenciasUsuarioDB($pdo, $usuarioId);
        $entradaPrefs = array_merge($actualesDB, $entradaPrefs);
    }

    $guardadas = guardarPreferenciasUsuarioDB($pdo, $usuarioId, $entradaPrefs);

    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['preferencias_json'] = json_encode($guardadas, JSON_UNESCAPED_UNICODE);
    }

    registrarEvento(
        $pdo,
        $auth['origen'],
        'PREFERENCIAS_GUARDADAS',
        "Preferencias de configuración guardadas en MySQL por '{$auth['usuario']}' (tema={$guardadas['tema']}, acento={$guardadas['acento']}, preset={$guardadas['prefAireTemp']}°C {$guardadas['prefAireModo']})"
    );

    responderJSON([
        'ok' => true,
        'mensaje' => 'Preferencias guardadas en la base de datos',
        'usuario_id' => $usuarioId,
        'preferencias' => $guardadas
    ]);
}

responderJSON(['ok' => false, 'error' => 'Metodo no permitido'], 405);

