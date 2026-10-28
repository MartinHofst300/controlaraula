<?php
/**
 * GET /api/capturas
 * Retorna las últimas capturas registradas por el receptor KY-022 del ESP32,
 * incluyendo valores hexadecimales, tramas largas de estado y secuencias raw.
 */

require_once __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../app/db.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/funciones.php';

habilitarCORS();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderJSON(['ok' => false, 'error' => 'Metodo no permitido. Se requiere GET'], 405);
}

$auth = autenticarPanelOApp();
requerirRolAdmin($auth);
$pdo = obtenerConexionDB();

$limite = isset($_GET['limite']) ? min(100, max(1, (int)$_GET['limite'])) : 30;

$stmt = $pdo->prepare('SELECT id, protocolo, valor, bits, estado_hex, raw, creado_en FROM capturas_ir ORDER BY id DESC LIMIT :limite');
$stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
$stmt->execute();
$capturas = $stmt->fetchAll();

responderJSON([
    'ok' => true,
    'total' => count($capturas),
    'capturas' => $capturas
]);
