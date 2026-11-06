<?php
/**
 * GET  /api/usuarios -> Lista todos los usuarios registrados (exclusivo rol 'admin')
 * POST /api/usuarios -> Crea, edita, activa/desactiva o elimina usuarios (exclusivo rol 'admin')
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
    $stmt = $pdo->query('SELECT id, usuario, nombre_completo, rol, aula_id, activo, aulas_permitidas, creado_en FROM usuarios ORDER BY id ASC');
    $usuarios = $stmt->fetchAll();

    foreach ($usuarios as &$u) {
        $u['id'] = (int) $u['id'];
        $u['aula_id'] = $u['aula_id'] !== null ? (int)$u['aula_id'] : null;
        $u['activo'] = (int) $u['activo'];
        if (empty($u['aulas_permitidas'])) {
            $u['aulas_permitidas'] = 'todas';
        }
    }
    unset($u);

    responderJSON([
        'ok' => true,
        'total' => count($usuarios),
        'usuarios' => $usuarios
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarTokenCSRFSiEsPanel($auth);
    $datos = obtenerDatosEntrada();

    $operacion = strtolower(trim($datos['operacion'] ?? 'guardar'));
    $id = isset($datos['id']) ? (int) $datos['id'] : 0;
    $adminActualId = (int) ($auth['usuario_id'] ?? 0);

    // 1. ELIMINAR USUARIO
    if ($operacion === 'eliminar') {
        if ($id <= 0) {
            responderJSON(['ok' => false, 'error' => 'ID de usuario invalido'], 400);
        }
        if ($id === $adminActualId) {
            responderJSON(['ok' => false, 'error' => 'No puede eliminar su propio usuario con sesion activa'], 422);
        }

        $stmtCheck = $pdo->prepare('SELECT id, usuario, rol FROM usuarios WHERE id = :id LIMIT 1');
        $stmtCheck->execute([':id' => $id]);
        $usuarioObj = $stmtCheck->fetch();

        if (!$usuarioObj) {
            responderJSON(['ok' => false, 'error' => 'El usuario especificado no existe'], 404);
        }

        $stmtDel = $pdo->prepare('DELETE FROM usuarios WHERE id = :id');
        $stmtDel->execute([':id' => $id]);

        registrarEvento(
            $pdo,
            $auth['origen'],
            'ELIMINAR_USUARIO',
            "Usuario '{$usuarioObj['usuario']}' (ID #{$id}, rol {$usuarioObj['rol']}) eliminado por {$auth['usuario']}"
        );

        responderJSON([
            'ok' => true,
            'mensaje' => "Usuario '{$usuarioObj['usuario']}' eliminado correctamente"
        ]);
    }

    // 2. ACTIVAR / DESACTIVAR RÁPIDO
    if ($operacion === 'toggle_activo') {
        if ($id <= 0) {
            responderJSON(['ok' => false, 'error' => 'ID de usuario invalido'], 400);
        }
        if ($id === $adminActualId) {
            responderJSON(['ok' => false, 'error' => 'No puede desactivar su propio usuario con sesion activa'], 422);
        }

        $stmtCheck = $pdo->prepare('SELECT id, usuario, activo FROM usuarios WHERE id = :id LIMIT 1');
        $stmtCheck->execute([':id' => $id]);
        $usuarioObj = $stmtCheck->fetch();

        if (!$usuarioObj) {
            responderJSON(['ok' => false, 'error' => 'El usuario especificado no existe'], 404);
        }

        $nuevoEstado = ((int) $usuarioObj['activo'] === 1) ? 0 : 1;
        $stmtUpd = $pdo->prepare('UPDATE usuarios SET activo = :activo WHERE id = :id');
        $stmtUpd->execute([':activo' => $nuevoEstado, ':id' => $id]);

        $estadoTexto = $nuevoEstado === 1 ? 'activado' : 'desactivado';
        registrarEvento(
            $pdo,
            $auth['origen'],
            'ESTADO_USUARIO',
            "Usuario '{$usuarioObj['usuario']}' {$estadoTexto} por {$auth['usuario']}"
        );

        responderJSON([
            'ok' => true,
            'mensaje' => "Usuario '{$usuarioObj['usuario']}' {$estadoTexto} correctamente",
            'activo' => $nuevoEstado
        ]);
    }

    // 3. CREAR O EDITAR USUARIO
    $usuario = trim($datos['usuario'] ?? '');
    $nombreCompleto = trim($datos['nombre_completo'] ?? '');
    $password = $datos['password'] ?? '';
    $rol = strtolower(trim($datos['rol'] ?? 'profesor'));
    $activo = isset($datos['activo']) ? ((int) $datos['activo'] === 1 ? 1 : 0) : 1;

    // Normalizar aulas permitidas
    $aulasPermitidasRaw = $datos['aulas_permitidas'] ?? 'todas';
    if ($rol === 'admin' || $aulasPermitidasRaw === 'todas') {
        $aulasPermitidas = 'todas';
    } elseif (is_array($aulasPermitidasRaw)) {
        $cleanIds = array_values(array_unique(array_filter(array_map('intval', $aulasPermitidasRaw), function($v) { return $v > 0; })));
        $aulasPermitidas = empty($cleanIds) ? 'todas' : json_encode($cleanIds);
    } elseif (is_string($aulasPermitidasRaw) && (strpos($aulasPermitidasRaw, '[') === 0)) {
        $aulasPermitidas = $aulasPermitidasRaw;
    } else {
        $aulasPermitidas = 'todas';
    }

    if (!in_array($rol, ['admin', 'aula', 'profesor'], true)) {
        responderJSON(['ok' => false, 'error' => 'El rol debe ser "admin", "aula" o "profesor"'], 422);
    }

    if (empty($nombreCompleto) || strlen($nombreCompleto) < 2) {
        responderJSON(['ok' => false, 'error' => 'Ingrese el nombre completo del usuario'], 422);
    }

    if ($id > 0) {
        // EDITAR USUARIO EXISTENTE
        $stmtCheck = $pdo->prepare('SELECT id, usuario, rol, activo FROM usuarios WHERE id = :id LIMIT 1');
        $stmtCheck->execute([':id' => $id]);
        $existente = $stmtCheck->fetch();

        if (!$existente) {
            responderJSON(['ok' => false, 'error' => 'El usuario a editar no existe'], 404);
        }

        if ($id === $adminActualId) {
            if ($rol !== 'admin') {
                responderJSON(['ok' => false, 'error' => 'No puede quitarse a si mismo el rol de administrador'], 422);
            }
            if ($activo !== 1) {
                responderJSON(['ok' => false, 'error' => 'No puede desactivar su propio usuario con sesion activa'], 422);
            }
        }

        if (!empty($usuario) && $usuario !== $existente['usuario']) {
            if (!preg_match('/^[a-zA-Z0-9_.-]{3,50}$/', $usuario)) {
                responderJSON(['ok' => false, 'error' => 'Nombre de usuario invalido (3 a 50 caracteres alfanumericos)'], 422);
            }
            $stmtDup = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE usuario = :usuario AND id != :id');
            $stmtDup->execute([':usuario' => $usuario, ':id' => $id]);
            if ((int) $stmtDup->fetchColumn() > 0) {
                responderJSON(['ok' => false, 'error' => "El nombre de usuario '{$usuario}' ya esta en uso"], 422);
            }
        } else {
            $usuario = $existente['usuario'];
        }

        if (!empty($password)) {
            if (strlen($password) < 4) {
                responderJSON(['ok' => false, 'error' => 'La contraseña debe tener al menos 4 caracteres'], 422);
            }
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmtUpd = $pdo->prepare(
                'UPDATE usuarios 
                 SET usuario = :usuario, nombre_completo = :nombre, password_hash = :hash, rol = :rol, activo = :activo, aulas_permitidas = :aulas 
                 WHERE id = :id'
            );
            $stmtUpd->execute([
                ':usuario' => $usuario,
                ':nombre'  => substr($nombreCompleto, 0, 100),
                ':hash'    => $hash,
                ':rol'     => $rol,
                ':activo'  => $activo,
                ':aulas'   => $aulasPermitidas,
                ':id'      => $id
            ]);
        } else {
            $stmtUpd = $pdo->prepare(
                'UPDATE usuarios 
                 SET usuario = :usuario, nombre_completo = :nombre, rol = :rol, activo = :activo, aulas_permitidas = :aulas 
                 WHERE id = :id'
            );
            $stmtUpd->execute([
                ':usuario' => $usuario,
                ':nombre'  => substr($nombreCompleto, 0, 100),
                ':rol'     => $rol,
                ':activo'  => $activo,
                ':aulas'   => $aulasPermitidas,
                ':id'      => $id
            ]);
        }

        registrarEvento(
            $pdo,
            $auth['origen'],
            'EDITAR_USUARIO',
            "Usuario '{$usuario}' (ID #{$id}, rol {$rol}) actualizado por {$auth['usuario']}"
        );

        responderJSON([
            'ok' => true,
            'mensaje' => "Usuario '{$usuario}' actualizado exitosamente",
            'usuario' => [
                'id' => $id,
                'usuario' => $usuario,
                'nombre_completo' => $nombreCompleto,
                'rol' => $rol,
                'activo' => $activo,
                'aulas_permitidas' => $aulasPermitidas
            ]
        ]);
    } else {
        // CREAR NUEVO USUARIO
        if (empty($usuario) || !preg_match('/^[a-zA-Z0-9_.-]{3,50}$/', $usuario)) {
            responderJSON(['ok' => false, 'error' => 'Nombre de usuario invalido (use entre 3 y 50 caracteres alfanumericos, puntos o guiones)'], 422);
        }

        if (empty($password) || strlen($password) < 4) {
            responderJSON(['ok' => false, 'error' => 'Debe asignar una contraseña de al menos 4 caracteres'], 422);
        }

        $stmtDup = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE usuario = :usuario');
        $stmtDup->execute([':usuario' => $usuario]);
        if ((int) $stmtDup->fetchColumn() > 0) {
            responderJSON(['ok' => false, 'error' => "El nombre de usuario '{$usuario}' ya existe"], 422);
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmtIns = $pdo->prepare(
            'INSERT INTO usuarios (usuario, password_hash, nombre_completo, rol, activo, aulas_permitidas, creado_en) 
             VALUES (:usuario, :hash, :nombre, :rol, :activo, :aulas, NOW())'
        );
        $stmtIns->execute([
            ':usuario' => $usuario,
            ':hash'    => $hash,
            ':nombre'  => substr($nombreCompleto, 0, 100),
            ':rol'     => $rol,
            ':activo'  => $activo,
            ':aulas'   => $aulasPermitidas
        ]);

        $nuevoId = (int) $pdo->lastInsertId();

        registrarEvento(
            $pdo,
            $auth['origen'],
            'CREAR_USUARIO',
            "Nuevo usuario '{$usuario}' (ID #{$nuevoId}, rol {$rol}) creado por {$auth['usuario']}"
        );

        responderJSON([
            'ok' => true,
            'mensaje' => "Usuario '{$usuario}' ({$rol}) creado exitosamente",
            'usuario' => [
                'id' => $nuevoId,
                'usuario' => $usuario,
                'nombre_completo' => $nombreCompleto,
                'rol' => $rol,
                'activo' => $activo,
                'aulas_permitidas' => $aulasPermitidas
            ]
        ]);
    }
}

responderJSON(['ok' => false, 'error' => 'Metodo no permitido'], 405);

