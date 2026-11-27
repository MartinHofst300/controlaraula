<?php
/**
 * GET  /api/horarios -> Lista las reglas de cierre automático
 * POST /api/horarios -> Operaciones: crear, toggle, eliminar, ejecutar_ahora (solo rol admin)
 */

require_once __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../app/db.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/funciones.php';

habilitarCORS();

$auth = autenticarPanelOApp();
requerirRolAdmin($auth);
$pdo = obtenerConexionDB();
asegurarColumnasExtendidas($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->query('SELECT id, nombre, hora, dias, accion, activo, ultima_ejecucion FROM horarios_cierre ORDER BY hora ASC, id ASC');
    $horarios = $stmt ? $stmt->fetchAll() : [];
    foreach ($horarios as &$h) {
        $h['id'] = (int) $h['id'];
        $h['activo'] = (int) $h['activo'];
    }
    unset($h);

    responderJSON([
        'ok' => true,
        'hora_servidor' => date('H:i'),
        'horarios' => $horarios
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarTokenCSRFSiEsPanel($auth);
    $datos = obtenerDatosEntrada();
    $operacion = strtolower(trim($datos['operacion'] ?? 'crear'));

    if ($operacion === 'crear') {
        $nombre = trim($datos['nombre'] ?? '');
        $hora = trim($datos['hora'] ?? '');
        $dias = trim($datos['dias'] ?? '1,2,3,4,5');
        $accion = strtolower(trim($datos['accion'] ?? 'todo'));

        if (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 100) {
            responderJSON(['ok' => false, 'error' => 'Ingresá un nombre para el horario (ej: Cierre Turno Tarde)'], 422);
        }
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $hora)) {
            responderJSON(['ok' => false, 'error' => 'Formato de hora inválido (usar HH:MM)'], 422);
        }
        if (!in_array($accion, ['todo', 'luces', 'aire', 'proyector'], true)) {
            $accion = 'todo';
        }
        $diasLimpios = array_values(array_unique(array_filter(array_map('intval', explode(',', $dias)), function ($d) {
            return $d >= 1 && $d <= 7;
        })));
        if (empty($diasLimpios)) {
            $diasLimpios = [1, 2, 3, 4, 5];
        }
        sort($diasLimpios);
        $diasStr = implode(',', $diasLimpios);

        $stmtIns = $pdo->prepare('INSERT INTO horarios_cierre (nombre, hora, dias, accion, activo) VALUES (:n, :h, :d, :a, 1)');
        $stmtIns->execute([
            ':n' => $nombre,
            ':h' => $hora,
            ':d' => $diasStr,
            ':a' => $accion
        ]);

        registrarEvento($pdo, $auth['origen'], 'CREAR_HORARIO', "Horario automático '{$nombre}' ({$hora}, acción: {$accion}) creado por {$auth['usuario']}");

        responderJSON([
            'ok' => true,
            'mensaje' => "Horario automático '{$nombre}' ({$hora}) guardado correctamente"
        ]);
    }

    if ($operacion === 'toggle') {
        $id = isset($datos['id']) ? (int) $datos['id'] : 0;
        $stmt = $pdo->prepare('SELECT id, nombre, activo FROM horarios_cierre WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            responderJSON(['ok' => false, 'error' => 'Horario no encontrado'], 404);
        }
        $nuevo = ((int)$row['activo'] === 1) ? 0 : 1;
        $pdo->prepare('UPDATE horarios_cierre SET activo = :a WHERE id = :id')->execute([':a' => $nuevo, ':id' => $id]);

        responderJSON([
            'ok' => true,
            'mensaje' => "Horario '{$row['nombre']}' " . ($nuevo ? 'activado' : 'pausado'),
            'activo' => $nuevo
        ]);
    }

    if ($operacion === 'eliminar') {
        $id = isset($datos['id']) ? (int) $datos['id'] : 0;
        $stmt = $pdo->prepare('SELECT id, nombre FROM horarios_cierre WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            responderJSON(['ok' => false, 'error' => 'Horario no encontrado'], 404);
        }
        $pdo->prepare('DELETE FROM horarios_cierre WHERE id = :id')->execute([':id' => $id]);
        registrarEvento($pdo, $auth['origen'], 'ELIMINAR_HORARIO', "Horario automático '{$row['nombre']}' eliminado por {$auth['usuario']}");

        responderJSON([
            'ok' => true,
            'mensaje' => "Horario '{$row['nombre']}' eliminado"
        ]);
    }

    if ($operacion === 'ejecutar_ahora') {
        $id = isset($datos['id']) ? (int) $datos['id'] : 0;
        $stmt = $pdo->prepare('SELECT * FROM horarios_cierre WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            responderJSON(['ok' => false, 'error' => 'Horario no encontrado'], 404);
        }
        $afectadas = ejecutarAccionApagadoGlobal($pdo, $row['accion'], $auth['usuario'], "Ejecución manual de horario '{$row['nombre']}'");
        responderJSON([
            'ok' => true,
            'mensaje' => "Regla '{$row['nombre']}' ejecutada sobre {$afectadas} espacios activos"
        ]);
    }

    responderJSON(['ok' => false, 'error' => 'Operación no soportada'], 400);
}

responderJSON(['ok' => false, 'error' => 'Método no permitido'], 405);
