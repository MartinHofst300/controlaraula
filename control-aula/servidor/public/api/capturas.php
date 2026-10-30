<?php
/**
 * GET /api/capturas
 * Retorna las últimas capturas registradas por el receptor KY-022 del ESP32 (Multi-Aula),
 * incluyendo valores hexadecimales, tramas largas de estado, secuencias raw y aula de origen.
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
asegurarColumnasExtendidas($pdo);

$limite = isset($_GET['limite']) ? min(100, max(1, (int)$_GET['limite'])) : 30;
$filtroAula = isset($_GET['aula_id']) && $_GET['aula_id'] !== '' ? (int)$_GET['aula_id'] : null;

$sql = "SELECT c.id, c.protocolo, c.valor, c.bits, c.estado_hex, c.raw, c.aula_id, c.creado_en, a.nombre AS aula_nombre 
        FROM capturas_ir c 
        LEFT JOIN aulas a ON c.aula_id = a.id";
$params = [];

if ($filtroAula !== null) {
    if ($filtroAula === 0) {
        $sql .= " WHERE (c.aula_id IS NULL OR c.aula_id = 0)";
    } else {
        $sql .= " WHERE c.aula_id = :aula_id";
        $params[':aula_id'] = $filtroAula;
    }
}

$sql .= " ORDER BY c.id DESC LIMIT :limite";

$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
$stmt->execute();
$capturas = $stmt->fetchAll();

responderJSON([
    'ok' => true,
    'total' => count($capturas),
    'capturas' => $capturas
]);
