<?php
/**
 * POST /api/escenas
 * Ejecuta dentro de una transacción de base de datos las 3 Escenas Rápidas para el aula seleccionada (Multi-Aula):
 *  1. "modo_proyeccion" (o "proyeccion"): Apaga luces en BD, enciende proyector en BD y encola comandos para el ESP32 del aula.
 *  2. "iniciar_clase": Lee de la BD (`usuarios.preferencias_json`) las preferencias del profesor logueado,
 *                      enciende luces y enciende el aire acondicionado en su temperatura y modo preferidos,
 *                      actualizando BD y encolando órdenes al ESP32 del aula.
 *  3. "cerrar_aula": Apaga luces, aire y proyector en BD y encola las órdenes de apagado general para el ESP32 del aula.
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
$escena = isset($datos['escena']) ? strtolower(trim((string) $datos['escena'])) : '';

if ($escena === 'proyeccion') {
    $escena = 'modo_proyeccion';
}

if (!in_array($escena, ['modo_proyeccion', 'iniciar_clase', 'cerrar_aula'], true)) {
    responderJSON(['ok' => false, 'error' => 'Escena invalida. Opciones: "modo_proyeccion", "iniciar_clase", "cerrar_aula"'], 400);
}

$aulaId = determinarAulaIdPeticion($auth, $datos);

$pdo = obtenerConexionDB();
asegurarColumnasExtendidas($pdo);
expirarComandosViejos($pdo);

// Validar permisos sobre el aula
verificarPermisoAula($pdo, (int)($auth['usuario_id'] ?? 0), $auth['rol'] ?? 'profesor', $aulaId);

try {
    $pdo->beginTransaction();

    if ($escena === 'modo_proyeccion') {
        actualizarEstadoAula($pdo, $aulaId, [
            'luces'                   => 0,
            'proyector_encendido'     => 1,
            'ultima_accion_proyector' => 'prender',
            'proyector_mute'          => 0,
            'proyector_freeze'        => 0
        ]);

        $irProy = encolarComandoIRSiExiste($pdo, 'proyector', 'prender', [], $aulaId);
        $mensaje = "Escena 'Modo Proyección' activada en Aula #{$aulaId}: luces apagadas y proyector encendido";

        registrarEvento(
            $pdo,
            $auth['origen'],
            'ESCENA_MODO_PROYECCION',
            "{$mensaje} (IR Proyector #{$irProy['comando_id']}) ejecutada por {$auth['usuario']}",
            $aulaId
        );

        $pdo->commit();

        responderJSON([
            'ok' => true,
            'aula_id' => $aulaId,
            'escena' => 'modo_proyeccion',
            'mensaje' => $mensaje,
            'luces' => 0,
            'proyector_encendido' => 1,
            'comando_proyector_id' => $irProy['comando_id']
        ]);
    }

    if ($escena === 'iniciar_clase') {
        $prefsDB = obtenerPreferenciasUsuarioDB($pdo, $auth['usuario_id'] ?? 1);
        $prefTemp = (int) ($prefsDB['prefAireTemp'] ?? 24);
        $prefModo = (string) ($prefsDB['prefAireModo'] ?? 'frio');

        if (isset($datos['aire_temp']) || isset($datos['pref_aire_temp'])) {
            $rawT = (int) ($datos['aire_temp'] ?? $datos['pref_aire_temp']);
            $prefTemp = max(16, min(30, $rawT));
        }
        if (!empty($datos['aire_modo']) || !empty($datos['pref_aire_modo'])) {
            $rawM = strtolower(trim((string) ($datos['aire_modo'] ?? $datos['pref_aire_modo'])));
            if ($rawM === 'deshumidificador') {
                $rawM = 'seco';
            }
            if (in_array($rawM, ['frio', 'calor', 'ventilacion', 'seco', 'auto'], true)) {
                $prefModo = $rawM;
            }
        }

        actualizarEstadoAula($pdo, $aulaId, [
            'luces'              => 1,
            'aire_encendido'     => 1,
            'ultima_accion_aire' => 'prender',
            'aire_temp'          => $prefTemp,
            'aire_modo'          => $prefModo,
            'aire_fan'           => 'auto',
            'aire_swing'         => 1
        ]);

        $irAire = encolarComandoIRSiExiste($pdo, 'aire', "{$prefModo}_{$prefTemp}", ["modo_{$prefModo}", 'prender'], $aulaId);
        $mensaje = "Escena 'Iniciar Clase' activada en Aula #{$aulaId}: luces encendidas y aire acondicionado en {$prefTemp}°C (" . strtoupper($prefModo) . ")";

        registrarEvento(
            $pdo,
            $auth['origen'],
            'ESCENA_INICIAR_CLASE',
            "{$mensaje} (IR Aire #{$irAire['comando_id']}) ejecutada por {$auth['usuario']}",
            $aulaId
        );

        $pdo->commit();

        responderJSON([
            'ok' => true,
            'aula_id' => $aulaId,
            'escena' => 'iniciar_clase',
            'mensaje' => $mensaje,
            'luces' => 1,
            'aire_encendido' => 1,
            'aire_temp' => $prefTemp,
            'aire_modo' => $prefModo,
            'comando_aire_id' => $irAire['comando_id']
        ]);
    }

    if ($escena === 'cerrar_aula') {
        $resultado = apagarTodoAula($pdo, $aulaId, $auth['usuario']);
        $mensaje = "Escena 'Cerrar Aula' ejecutada en Aula #{$aulaId}: luces, aire acondicionado y proyector apagados";

        registrarEvento(
            $pdo,
            $auth['origen'],
            'ESCENA_CERRAR_AULA',
            "{$mensaje} ejecutada por {$auth['usuario']}",
            $aulaId
        );

        $pdo->commit();

        responderJSON([
            'ok' => true,
            'aula_id' => $aulaId,
            'escena' => 'cerrar_aula',
            'mensaje' => $mensaje,
            'luces' => 0,
            'aire_encendido' => 0,
            'proyector_encendido' => 0,
            'comandos_encolados' => $resultado['comandos_encolados']
        ]);
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    responderJSON(['ok' => false, 'error' => 'Error al ejecutar escena en base de datos: ' . $e->getMessage()], 500);
}
