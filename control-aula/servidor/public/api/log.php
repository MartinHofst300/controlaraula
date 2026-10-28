<?php
/**
 * GET /api/log
 * Retorna los últimos eventos registrados en el sistema.
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

$limite = isset($_GET['limite']) ? min(100, max(1, (int)$_GET['limite'])) : 40;

$stmt = $pdo->prepare('SELECT id, origen, evento, detalle, creado_en FROM log_eventos ORDER BY id DESC LIMIT :limite');
$stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
$stmt->execute();
$eventos = $stmt->fetchAll();

responderJSON([
    'ok' => true,
    'total' => count($eventos),
    'eventos' => $eventos
]);
