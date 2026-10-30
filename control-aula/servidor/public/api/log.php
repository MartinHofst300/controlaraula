<?php
/**
 * GET /api/log
 * Retorna los últimos eventos registrados en el sistema (Multi-Aula).
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

$limite = isset($_GET['limite']) ? min(100, max(1, (int)$_GET['limite'])) : 50;
$filtroAula = isset($_GET['aula_id']) && $_GET['aula_id'] !== '' ? (int)$_GET['aula_id'] : null;

$sql = "SELECT l.id, l.origen, l.evento, l.detalle, l.aula_id, l.creado_en, a.nombre AS aula_nombre 
        FROM log_eventos l 
        LEFT JOIN aulas a ON l.aula_id = a.id";
$params = [];

if ($filtroAula !== null) {
    if ($filtroAula === 0) {
        $sql .= " WHERE (l.aula_id IS NULL OR l.aula_id = 0)";
    } else {
        $sql .= " WHERE l.aula_id = :aula_id";
        $params[':aula_id'] = $filtroAula;
    }
}

$sql .= " ORDER BY l.id DESC LIMIT :limite";

$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
$stmt->execute();
$eventos = $stmt->fetchAll();

responderJSON([
    'ok' => true,
    'total' => count($eventos),
    'eventos' => $eventos
]);
