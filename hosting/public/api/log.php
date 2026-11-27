<?php
/**
 * GET /api/log
 * Retorna los últimos eventos registrados en el sistema con filtros opcionales (aula_id, q, tipo)
 * y exportación directa a CSV para Excel (?export=csv).
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

$exportCsv = isset($_GET['export']) && strtolower(trim($_GET['export'])) === 'csv';
$limite = $exportCsv
    ? 500
    : (isset($_GET['limite']) ? min(200, max(1, (int)$_GET['limite'])) : 80);

$filtroAula = isset($_GET['aula_id']) && $_GET['aula_id'] !== '' ? (int)$_GET['aula_id'] : null;
$filtroEvento = isset($_GET['evento']) ? trim((string)$_GET['evento']) : '';
$busqueda = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

$sql = "SELECT l.id, l.origen, l.evento, l.detalle, l.aula_id, l.creado_en, a.nombre AS aula_nombre 
        FROM log_eventos l 
        LEFT JOIN aulas a ON l.aula_id = a.id";
$wheres = [];
$params = [];

if ($filtroAula !== null) {
    if ($filtroAula === 0) {
        $wheres[] = "(l.aula_id IS NULL OR l.aula_id = 0)";
    } else {
        $wheres[] = "l.aula_id = :aula_id";
        $params[':aula_id'] = $filtroAula;
    }
}

if ($filtroEvento !== '') {
    $wheres[] = "l.evento LIKE :evento";
    $params[':evento'] = '%' . $filtroEvento . '%';
}

if ($busqueda !== '') {
    $wheres[] = "(l.detalle LIKE :q1 OR l.evento LIKE :q2 OR l.origen LIKE :q3 OR a.nombre LIKE :q4)";
    $likeQ = '%' . $busqueda . '%';
    $params[':q1'] = $likeQ;
    $params[':q2'] = $likeQ;
    $params[':q3'] = $likeQ;
    $params[':q4'] = $likeQ;
}

if (!empty($wheres)) {
    $sql .= " WHERE " . implode(' AND ', $wheres);
}

$sql .= " ORDER BY l.id DESC LIMIT :limite";

$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
$stmt->execute();
$eventos = $stmt->fetchAll();

if ($exportCsv) {
    $fecha = date('Y-m-d_Hi');
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"historial_control_aula_{$fecha}.csv\"");
    header('Pragma: no-cache');
    header('Expires: 0');

    // BOM UTF-8 para que Excel en español reconozca tildes y ñ
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Fecha y Hora', 'Espacio / Aula', 'Origen', 'Evento', 'Detalle'], ';');
    foreach ($eventos as $ev) {
        fputcsv($out, [
            $ev['creado_en'] ?? '',
            $ev['aula_nombre'] ?: 'General',
            $ev['origen'] ?? '',
            $ev['evento'] ?? '',
            $ev['detalle'] ?? ''
        ], ';');
    }
    fclose($out);
    exit;
}

responderJSON([
    'ok' => true,
    'total' => count($eventos),
    'eventos' => $eventos
]);
