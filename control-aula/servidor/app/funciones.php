<?php
/**
 * Funciones auxiliares de respuesta, CORS, expiración y auditoría de eventos
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/**
 * Habilita cabeceras CORS para permitir consumo desde apps móviles o frontends externos
 */
function habilitarCORS() {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key, X-CSRF-Token');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

/**
 * Responde con formato JSON y código HTTP especificado
 */
function responderJSON($datos, $codigo = 200) {
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Lee y decodifica el cuerpo de la petición (JSON o form-urlencoded)
 */
function obtenerDatosEntrada() {
    $raw = file_get_contents('php://input');
    if (!empty($raw)) {
        $json = json_decode($raw, true);
        if (is_array($json)) {
            return $json;
        }
    }
    return is_array($_POST) ? $_POST : [];
}

/**
 * Registra un evento en la tabla log_eventos
 */
function registrarEvento($pdo, $origen, $evento, $detalle = null) {
    try {
        $stmt = $pdo->prepare('INSERT INTO log_eventos (origen, evento, detalle, creado_en) VALUES (:origen, :evento, :detalle, NOW())');
        $stmt->execute([
            ':origen' => substr(trim($origen), 0, 30),
            ':evento' => substr(trim($evento), 0, 100),
            ':detalle' => is_string($detalle) ? $detalle : (is_null($detalle) ? null : json_encode($detalle, JSON_UNESCAPED_UNICODE))
        ]);
    } catch (Exception $e) {
        error_log('Error al registrar evento: ' . $e->getMessage());
    }
}

/**
 * Expira los comandos IR que lleven más de 30 segundos pendientes sin ser tomados por el ESP32
 */
function expirarComandosViejos($pdo) {
    try {
        $segundos = (int) EXPIRACION_COMANDO_SEGUNDOS;
        $sql = "UPDATE comandos_ir 
                SET estado = 'expirado' 
                WHERE estado = 'pendiente' 
                  AND creado_en < (NOW() - INTERVAL {$segundos} SECOND)";
        $pdo->exec($sql);
    } catch (Exception $e) {
        error_log('Error al expirar comandos IR viejos: ' . $e->getMessage());
    }
}
