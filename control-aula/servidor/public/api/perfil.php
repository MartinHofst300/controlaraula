<?php
/**
 * GET  /api/perfil -> Obtiene los datos de perfil del usuario autenticado (profesor o admin)
 * POST /api/perfil -> Permite al usuario editar su Nombre Completo o cambiar su propia contraseña
 */

require_once __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../app/db.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/funciones.php';

habilitarCORS();

$auth = autenticarPanelOApp();
$pdo = obtenerConexionDB();

$usuarioId = (int) ($auth['usuario_id'] ?? 0);
$usuarioLogin = $auth['usuario'] ?? 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare('SELECT id, usuario, nombre_completo, rol, creado_en FROM usuarios WHERE id = :id OR usuario = :usr LIMIT 1');
    $stmt->execute([':id' => $usuarioId, ':usr' => $usuarioLogin]);
    $perfil = $stmt->fetch();

    if (!$perfil) {
        $perfil = [
            'id' => $usuarioId,
            'usuario' => $usuarioLogin,
            'nombre_completo' => $auth['nombre_completo'] ?? $usuarioLogin,
            'rol' => $auth['rol'] ?? 'profesor'
        ];
    }

    responderJSON([
        'ok' => true,
        'perfil' => $perfil
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarTokenCSRFSiEsPanel($auth);
    $datos = obtenerDatosEntrada();
    $operacion = strtolower(trim($datos['operacion'] ?? 'actualizar_perfil'));

    $stmt = $pdo->prepare('SELECT id, usuario, password_hash, nombre_completo, rol FROM usuarios WHERE id = :id OR usuario = :usr LIMIT 1');
    $stmt->execute([':id' => $usuarioId, ':usr' => $usuarioLogin]);
    $fila = $stmt->fetch();

    if (!$fila) {
        responderJSON(['ok' => false, 'error' => 'No se encontro el registro del usuario en la base de datos'], 404);
    }

    $idReal = (int) $fila['id'];

    // 1. Actualizar Nombre Completo
    if ($operacion === 'actualizar_perfil') {
        $nuevoNombre = trim($datos['nombre_completo'] ?? '');
        if (empty($nuevoNombre) || strlen($nuevoNombre) < 2) {
            responderJSON(['ok' => false, 'error' => 'El nombre completo debe tener al menos 2 caracteres'], 422);
        }

        $nuevoNombre = substr($nuevoNombre, 0, 100);
        $stmtUpd = $pdo->prepare('UPDATE usuarios SET nombre_completo = :nombre WHERE id = :id');
        $stmtUpd->execute([':nombre' => $nuevoNombre, ':id' => $idReal]);

        iniciarSesionSegura();
        $_SESSION['nombre_completo'] = $nuevoNombre;

        registrarEvento(
            $pdo,
            $auth['origen'],
            'ACTUALIZAR_PERFIL',
            "Usuario '{$fila['usuario']}' actualizo su nombre visible a '{$nuevoNombre}'"
        );

        responderJSON([
            'ok' => true,
            'mensaje' => 'Nombre completo actualizado correctamente',
            'nombre_completo' => $nuevoNombre
        ]);
    }

    // 2. Cambiar contraseña propia
    if ($operacion === 'cambiar_password') {
        $passwordActual = $datos['password_actual'] ?? '';
        $passwordNueva = $datos['password_nueva'] ?? '';

        if (empty($passwordActual) || empty($passwordNueva)) {
            responderJSON(['ok' => false, 'error' => 'Debe completar su contraseña actual y la nueva contraseña'], 400);
        }

        if (!password_verify($passwordActual, $fila['password_hash'])) {
            responderJSON(['ok' => false, 'error' => 'La contraseña actual ingresada es incorrecta'], 422);
        }

        if (strlen($passwordNueva) < 4) {
            responderJSON(['ok' => false, 'error' => 'La nueva contraseña debe tener al menos 4 caracteres'], 422);
        }

        $nuevoHash = password_hash($passwordNueva, PASSWORD_BCRYPT);
        $stmtUpd = $pdo->prepare('UPDATE usuarios SET password_hash = :hash WHERE id = :id');
        $stmtUpd->execute([':hash' => $nuevoHash, ':id' => $idReal]);

        registrarEvento(
            $pdo,
            $auth['origen'],
            'CAMBIO_PASSWORD_PROPIO',
            "El usuario '{$fila['usuario']}' actualizo su contraseña desde Mi Perfil"
        );

        responderJSON([
            'ok' => true,
            'mensaje' => 'Contraseña actualizada exitosamente'
        ]);
    }

    responderJSON(['ok' => false, 'error' => 'Operacion no reconocida'], 400);
}

responderJSON(['ok' => false, 'error' => 'Metodo no permitido'], 405);

