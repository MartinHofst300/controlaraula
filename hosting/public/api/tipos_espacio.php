<?php
/**
 * GET  /api/tipos_espacio -> Lista todos los tipos de espacio disponibles
 * POST /api/tipos_espacio -> Crea o elimina un tipo de espacio (solo rol admin)
 */

require_once __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../app/db.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/funciones.php';

habilitarCORS();

$auth = autenticarPanelOApp();
$pdo = obtenerConexionDB();
asegurarColumnasExtendidas($pdo);

$iconosPermitidos = [
    'bi-door-open', 'bi-cpu', 'bi-tools', 'bi-people', 'bi-book',
    'bi-building', 'bi-easel2', 'bi-pc-display', 'bi-mortarboard',
    'bi-briefcase', 'bi-music-note-beamed', 'bi-trophy', 'bi-lightbulb'
];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->query('SELECT id, nombre, icono FROM tipos_espacio ORDER BY id ASC');
    $tipos = $stmt ? $stmt->fetchAll() : [];
    foreach ($tipos as &$t) {
        $t['id'] = (int) $t['id'];
    }
    unset($t);

    responderJSON([
        'ok' => true,
        'tipos' => $tipos
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requerirRolAdmin($auth);
    validarTokenCSRFSiEsPanel($auth);
    $datos = obtenerDatosEntrada();
    $operacion = strtolower(trim($datos['operacion'] ?? 'crear'));

    if ($operacion === 'crear') {
        $nombre = trim($datos['nombre'] ?? '');
        $icono = trim($datos['icono'] ?? 'bi-building');

        if (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 60) {
            responderJSON(['ok' => false, 'error' => 'El nombre del tipo debe tener entre 2 y 60 caracteres'], 422);
        }
        if (!in_array($icono, $iconosPermitidos, true)) {
            $icono = 'bi-building';
        }

        $stmtCheck = $pdo->prepare('SELECT id FROM tipos_espacio WHERE LOWER(nombre) = LOWER(:n) LIMIT 1');
        $stmtCheck->execute([':n' => $nombre]);
        if ($stmtCheck->fetch()) {
            responderJSON(['ok' => false, 'error' => "Ya existe el tipo de espacio '{$nombre}'"], 422);
        }

        $stmtIns = $pdo->prepare('INSERT INTO tipos_espacio (nombre, icono) VALUES (:n, :i)');
        $stmtIns->execute([':n' => $nombre, ':i' => $icono]);
        $nuevoId = (int) $pdo->lastInsertId();

        registrarEvento($pdo, $auth['origen'], 'CREAR_TIPO_ESPACIO', "Tipo de espacio '{$nombre}' creado por {$auth['usuario']}");

        responderJSON([
            'ok' => true,
            'mensaje' => "Tipo de espacio '{$nombre}' agregado correctamente",
            'tipo' => ['id' => $nuevoId, 'nombre' => $nombre, 'icono' => $icono]
        ]);
    }

    if ($operacion === 'eliminar') {
        $id = isset($datos['id']) ? (int) $datos['id'] : 0;
        if ($id <= 0) {
            responderJSON(['ok' => false, 'error' => 'ID de tipo inválido'], 400);
        }

        $stmtTipo = $pdo->prepare('SELECT id, nombre FROM tipos_espacio WHERE id = :id LIMIT 1');
        $stmtTipo->execute([':id' => $id]);
        $tipoObj = $stmtTipo->fetch();
        if (!$tipoObj) {
            responderJSON(['ok' => false, 'error' => 'Tipo de espacio no encontrado'], 404);
        }

        if (strtolower($tipoObj['nombre']) === 'aula') {
            responderJSON(['ok' => false, 'error' => 'No se puede eliminar el tipo base "Aula"'], 422);
        }

        $stmtUso = $pdo->prepare('SELECT COUNT(*) FROM aulas WHERE tipo = :t');
        $stmtUso->execute([':t' => $tipoObj['nombre']]);
        if ((int) $stmtUso->fetchColumn() > 0) {
            responderJSON(['ok' => false, 'error' => "No se puede eliminar '{$tipoObj['nombre']}' porque hay espacios asignados a ese tipo"], 422);
        }

        $stmtDel = $pdo->prepare('DELETE FROM tipos_espacio WHERE id = :id');
        $stmtDel->execute([':id' => $id]);

        registrarEvento($pdo, $auth['origen'], 'ELIMINAR_TIPO_ESPACIO', "Tipo de espacio '{$tipoObj['nombre']}' eliminado por {$auth['usuario']}");

        responderJSON([
            'ok' => true,
            'mensaje' => "Tipo '{$tipoObj['nombre']}' eliminado correctamente"
        ]);
    }

    responderJSON(['ok' => false, 'error' => 'Operación no soportada'], 400);
}

responderJSON(['ok' => false, 'error' => 'Método no permitido'], 405);
