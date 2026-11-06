<?php
/**
 * GET  /api/aulas -> Lista las aulas disponibles (resumen de estado para profesores, completo para admin)
 * POST /api/aulas -> Operaciones: crear, editar, toggle_activa, regenerar_key, clonar_codigos, apagar_todo, eliminar
 */

require_once __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../app/db.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/funciones.php';

habilitarCORS();

$auth = autenticarPanelOApp();
$pdo = obtenerConexionDB();
asegurarColumnasExtendidas($pdo);

$esAdmin = ($auth['rol'] === 'admin');
$usuarioId = (int) ($auth['usuario_id'] ?? 0);

// ============================================================================
// GET: LISTADO DE AULAS CON ESTADO EN TIEMPO REAL
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($esAdmin) {
        $stmt = $pdo->query(
            "SELECT 
                a.id, a.nombre, a.tipo, a.ubicacion, a.api_key_esp32, a.activa,
                a.marca_aire, a.protocolo_aire, a.marca_proyector, a.protocolo_proyector,
                a.luces, a.aire_encendido, a.aire_temp, a.aire_modo, a.aire_fan, a.aire_swing,
                a.proyector_encendido, a.proyector_fuente, a.proyector_freeze, a.proyector_mute, a.proyector_volumen,
                a.rssi_wifi, a.uptime_seg, a.ultima_conexion,
                u.usuario AS usuario_acceso,
                TIMESTAMPDIFF(SECOND, a.ultima_conexion, NOW()) AS segundos_desde_conexion
             FROM aulas a
             LEFT JOIN usuarios u ON (u.aula_id = a.id AND u.rol = 'aula')
             ORDER BY a.id ASC"
        );
        $aulas = $stmt->fetchAll();
    } else {
        // Profesor o Aula: solo aulas asignadas y activas
        $aulasPermitidas = obtenerAulasPermitidasUsuario($pdo, $usuarioId, $auth['rol']);
        $aulas = [];
        foreach ($aulasPermitidas as $ap) {
            $est = obtenerEstadoAula($pdo, (int)$ap['id']);
            if ($est) {
                // Ocultar la API Key al usuario regular por seguridad
                unset($est['api_key_esp32']);
                $aulas[] = $est;
            }
        }
    }

    // Contar códigos IR por aula
    $codigosPorAula = [];
    try {
        $stmtCount = $pdo->query(
            "SELECT COALESCE(aula_id, 0) as aula_id, COUNT(*) as total 
             FROM codigos_ir 
             GROUP BY aula_id"
        );
        while ($row = $stmtCount->fetch()) {
            $codigosPorAula[(int)$row['aula_id']] = (int)$row['total'];
        }
    } catch (Exception $e) {
        // Silencioso si aún no existe la columna
    }

    $formateadas = [];
    foreach ($aulas as $a) {
        $id = (int) $a['id'];
        $segundos = isset($a['segundos_desde_conexion']) && $a['segundos_desde_conexion'] !== null 
            ? (int) $a['segundos_desde_conexion'] 
            : 999999;
        $online = ($segundos <= 75); // Consideramos online si reportó en los últimos 75 seg

        $formateadas[] = [
            'id' => $id,
            'nombre' => $a['nombre'],
            'tipo' => $a['tipo'] ?? 'Aula',
            'ubicacion' => $a['ubicacion'] ?? '',
            'usuario_acceso' => $a['usuario_acceso'] ?? null,
            'api_key_esp32' => $esAdmin ? ($a['api_key_esp32'] ?? '') : null,
            'activa' => (int) ($a['activa'] ?? 1),
            'online' => $online,
            'segundos_desde_conexion' => $segundos,
            'ultima_conexion' => $a['ultima_conexion'] ?? null,
            'rssi_wifi' => (int) ($a['rssi_wifi'] ?? 0),
            'uptime_seg' => (int) ($a['uptime_seg'] ?? 0),
            'codigos_ir_count' => $codigosPorAula[$id] ?? 0,
            'hardware' => [
                'marca_aire' => $a['marca_aire'] ?? 'Star (OEM)',
                'protocolo_aire' => $a['protocolo_aire'] ?? 'COOLIX',
                'marca_proyector' => $a['marca_proyector'] ?? 'Epson',
                'protocolo_proyector' => $a['protocolo_proyector'] ?? 'EPSON_NEC'
            ],
            'estado' => [
                'luces' => (int) ($a['luces'] ?? 0),
                'aire_encendido' => (int) ($a['aire_encendido'] ?? 0),
                'aire_temp' => (int) ($a['aire_temp'] ?? 24),
                'aire_modo' => $a['aire_modo'] ?? 'frio',
                'aire_fan' => $a['aire_fan'] ?? 'auto',
                'aire_swing' => (int) ($a['aire_swing'] ?? 0),
                'proyector_encendido' => (int) ($a['proyector_encendido'] ?? 0),
                'proyector_fuente' => $a['proyector_fuente'] ?? 'hdmi1',
                'proyector_freeze' => (int) ($a['proyector_freeze'] ?? 0),
                'proyector_mute' => (int) ($a['proyector_mute'] ?? 0),
                'proyector_volumen' => (int) ($a['proyector_volumen'] ?? 50)
            ]
        ];
    }

    responderJSON([
        'ok' => true,
        'total' => count($formateadas),
        'es_admin' => $esAdmin,
        'codigos_globales_count' => $codigosPorAula[0] ?? 0,
        'aulas' => $formateadas
    ]);
}

// ============================================================================
// POST: OPERACIONES SOBRE AULAS
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarTokenCSRFSiEsPanel($auth);
    $datos = obtenerDatosEntrada();

    $operacion = strtolower(trim($datos['operacion'] ?? ''));
    $aulaId = isset($datos['aula_id']) ? (int) $datos['aula_id'] : (isset($datos['id']) ? (int) $datos['id'] : 0);

    // 1. APAGAR TODO (Disponible para Admin o para Profesor en sus aulas permitidas)
    if ($operacion === 'apagar_todo') {
        if ($aulaId === 0 || $datos['aula_id'] === 'todas') {
            // Apagado global de todas las aulas (sólo admin)
            requerirRolAdmin($auth);
            $stmt = $pdo->query("SELECT id, nombre FROM aulas WHERE activa = 1");
            $aulasActivas = $stmt->fetchAll();
            $afectadas = 0;
            foreach ($aulasActivas as $a) {
                apagarTodoAula($pdo, (int)$a['id'], $auth['usuario']);
                $afectadas++;
            }
            registrarEvento($pdo, $auth['origen'], 'APAGAR_TODO_GLOBAL', "Apagado total de emergencia en {$afectadas} aulas por {$auth['usuario']}");
            responderJSON([
                'ok' => true,
                'mensaje' => "Se enviaron comandos de apagado a {$afectadas} aulas activas",
                'aulas_afectadas' => $afectadas
            ]);
        } else {
            // Apagado de un aula específica
            verificarPermisoAula($pdo, $usuarioId, $auth['rol'], $aulaId);
            $resultado = apagarTodoAula($pdo, $aulaId, $auth['usuario']);
            responderJSON([
                'ok' => true,
                'mensaje' => "Comandos de apagado total encolados para el aula #{$aulaId}",
                'aula_id' => $aulaId,
                'comandos_encolados' => $resultado['comandos_encolados']
            ]);
        }
    }

    // Las siguientes operaciones son EXCLUSIVAS de Administradores
    requerirRolAdmin($auth);

    // 2. CREAR NUEVO ESPACIO FÍSICO / AULA CON SU CUENTA
    if ($operacion === 'crear') {
        $nombre = trim($datos['nombre'] ?? '');
        $tipo = in_array($datos['tipo'] ?? '', ['Aula', 'Laboratorio', 'Taller', 'Otro'], true) ? $datos['tipo'] : 'Aula';
        $ubicacion = trim($datos['ubicacion'] ?? '');
        $marcaAire = trim($datos['marca_aire'] ?? 'Star (OEM)');
        $protoAire = trim($datos['protocolo_aire'] ?? 'COOLIX');
        $marcaProy = trim($datos['marca_proyector'] ?? 'Epson');
        $protoProy = trim($datos['protocolo_proyector'] ?? 'EPSON_NEC');
        $usuarioLogin = trim($datos['usuario'] ?? '');
        $password = trim($datos['password'] ?? '');
        $clonarOrigen = isset($datos['clonar_origen_id']) && $datos['clonar_origen_id'] !== '' ? (int)$datos['clonar_origen_id'] : -1;

        if (empty($nombre)) {
            responderJSON(['ok' => false, 'error' => 'Debe ingresar el nombre del espacio (ej: Aula 102, Lab 2)'], 422);
        }

        // Generar slug para API Key y usuario por defecto
        $slug = preg_replace('/[^a-z0-9]/', '_', strtolower($nombre));
        $slug = trim(preg_replace('/_+/', '_', $slug), '_');
        if (empty($slug)) $slug = 'espacio_' . time();

        if (empty($usuarioLogin)) {
            $usuarioLogin = substr($slug, 0, 20);
        }

        // Validar unicidad del usuario
        $stmtUsrCheck = $pdo->prepare('SELECT id FROM usuarios WHERE usuario = :u LIMIT 1');
        $stmtUsrCheck->execute([':u' => $usuarioLogin]);
        if ($stmtUsrCheck->fetch()) {
            $usuarioLogin .= '_' . rand(10, 99);
        }

        if (empty($password)) {
            $password = 'aula123';
        }

        $apiKey = 'esp32_key_' . substr($slug, 0, 12) . '_' . bin2hex(random_bytes(4));

        try {
            $pdo->beginTransaction();

            $stmtIns = $pdo->prepare(
                "INSERT INTO aulas (nombre, tipo, ubicacion, api_key_esp32, activa, marca_aire, protocolo_aire, marca_proyector, protocolo_proyector, luces, aire_encendido, aire_temp, aire_modo, aire_fan, aire_swing, proyector_encendido, proyector_fuente, proyector_volumen)
                 VALUES (:nombre, :tipo, :ubicacion, :api_key, 1, :marca_aire, :proto_aire, :marca_proy, :proto_proy, 0, 0, 24, 'frio', 'auto', 0, 0, 'hdmi1', 50)"
            );
            $stmtIns->execute([
                ':nombre' => $nombre,
                ':tipo' => $tipo,
                ':ubicacion' => $ubicacion,
                ':api_key' => $apiKey,
                ':marca_aire' => $marcaAire,
                ':proto_aire' => $protoAire,
                ':marca_proy' => $marcaProy,
                ':proto_proy' => $protoProy
            ]);

            $nuevoId = (int) $pdo->lastInsertId();

            // Crear cuenta de acceso vinculada a este espacio
            $stmtInsUsr = $pdo->prepare(
                "INSERT INTO usuarios (usuario, password_hash, nombre_completo, rol, aula_id, activo, aulas_permitidas)
                 VALUES (:usr, :pass, :nom, 'aula', :aid, 1, :perm)"
            );
            $stmtInsUsr->execute([
                ':usr' => $usuarioLogin,
                ':pass' => password_hash($password, PASSWORD_DEFAULT),
                ':nom' => $nombre,
                ':aid' => $nuevoId,
                ':perm' => json_encode([$nuevoId])
            ]);

            // Sincronizar fila en estado_actual
            try {
                $pdo->exec("INSERT INTO estado_actual (id, luces, luces_deseadas, luces_reales, aire_temp, aire_modo, aire_fan, proyector_fuente, proyector_volumen, version_fw) VALUES ({$nuevoId}, 0, 0, 0, 24, 'frio', 'auto', 'hdmi1', 50, '1.0.0') ON DUPLICATE KEY UPDATE version_fw = VALUES(version_fw)");
            } catch (Exception $e) {}

            // Clonar códigos IR opcionalmente
            $totalClonados = 0;
            if ($clonarOrigen >= 0) {
                if ($clonarOrigen === 0) {
                    $stmtCod = $pdo->query("SELECT * FROM codigos_ir WHERE aula_id IS NULL OR aula_id = 0");
                } else {
                    $stmtCod = $pdo->prepare("SELECT * FROM codigos_ir WHERE aula_id = :origen_id");
                    $stmtCod->execute([':origen_id' => $clonarOrigen]);
                }
                $codigosAClonar = $stmtCod ? $stmtCod->fetchAll() : [];
                if (!empty($codigosAClonar)) {
                    $stmtInsCod = $pdo->prepare(
                        "INSERT INTO codigos_ir (dispositivo, accion, protocolo, codigo, codigo_hex, bits, repeticiones, pausa_ms, frecuencia_khz, estado_hex, raw, descripcion, aula_id)
                         VALUES (:disp, :acc, :proto, :cod, :cod_hex, :bits, :rep, :pausa, :freq, :st_hex, :raw, :desc, :aid)"
                    );
                    foreach ($codigosAClonar as $c) {
                        $stmtInsCod->execute([
                            ':disp' => $c['dispositivo'],
                            ':acc' => $c['accion'],
                            ':proto' => $c['protocolo'] ?? 'NEC',
                            ':cod' => $c['codigo'] ?? ($c['codigo_hex'] ?? ''),
                            ':cod_hex' => $c['codigo_hex'] ?? ($c['codigo'] ?? ''),
                            ':bits' => (int)($c['bits'] ?? 32),
                            ':rep' => (int)($c['repeticiones'] ?? 1),
                            ':pausa' => (int)($c['pausa_ms'] ?? 100),
                            ':freq' => (int)($c['frecuencia_khz'] ?? 38),
                            ':st_hex' => $c['estado_hex'] ?? null,
                            ':raw' => $c['raw'] ?? null,
                            ':desc' => $c['descripcion'] ?? '',
                            ':aid' => $nuevoId
                        ]);
                        $totalClonados++;
                    }
                }
            }

            $pdo->commit();

            registrarEvento($pdo, $auth['origen'], 'CREAR_AULA', "Espacio '{$nombre}' ({$tipo}, ID #{$nuevoId}) con cuenta '{$usuarioLogin}' creado por {$auth['usuario']}", $nuevoId);

            responderJSON([
                'ok' => true,
                'mensaje' => "Espacio '{$nombre}' ({$tipo}) creado exitosamente con usuario '{$usuarioLogin}'",
                'aula' => [
                    'id' => $nuevoId,
                    'nombre' => $nombre,
                    'tipo' => $tipo,
                    'ubicacion' => $ubicacion,
                    'usuario_acceso' => $usuarioLogin,
                    'api_key_esp32' => $apiKey,
                    'codigos_clonados' => $totalClonados,
                    'activa' => 1
                ]
            ]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            responderJSON(['ok' => false, 'error' => 'Error al crear el espacio físico: ' . $e->getMessage()], 500);
        }
    }

    // 3. EDITAR ESPACIO FÍSICO / AULA
    if ($operacion === 'editar') {
        if ($aulaId <= 0) {
            responderJSON(['ok' => false, 'error' => 'ID de aula inválido'], 400);
        }

        $nombre = trim($datos['nombre'] ?? '');
        $tipo = in_array($datos['tipo'] ?? '', ['Aula', 'Laboratorio', 'Taller', 'Otro'], true) ? $datos['tipo'] : 'Aula';
        $ubicacion = trim($datos['ubicacion'] ?? '');
        $marcaAire = trim($datos['marca_aire'] ?? 'Star (OEM)');
        $protoAire = trim($datos['protocolo_aire'] ?? 'COOLIX');
        $marcaProy = trim($datos['marca_proyector'] ?? 'Epson');
        $protoProy = trim($datos['protocolo_proyector'] ?? 'EPSON_NEC');
        $usuarioLogin = trim($datos['usuario'] ?? '');
        $password = trim($datos['password'] ?? '');

        if (empty($nombre)) {
            responderJSON(['ok' => false, 'error' => 'Debe ingresar el nombre del espacio'], 422);
        }

        try {
            $pdo->beginTransaction();

            $stmtUpd = $pdo->prepare(
                "UPDATE aulas 
                 SET nombre = :nombre, tipo = :tipo, ubicacion = :ubicacion, 
                     marca_aire = :marca_aire, protocolo_aire = :proto_aire, 
                     marca_proyector = :marca_proy, protocolo_proyector = :proto_proy 
                 WHERE id = :id"
            );
            $stmtUpd->execute([
                ':nombre' => $nombre,
                ':tipo' => $tipo,
                ':ubicacion' => $ubicacion,
                ':marca_aire' => $marcaAire,
                ':proto_aire' => $protoAire,
                ':marca_proy' => $marcaProy,
                ':proto_proy' => $protoProy,
                ':id' => $aulaId
            ]);

            // Actualizar cuenta de usuario vinculada
            if (!empty($usuarioLogin) || !empty($password)) {
                $stmtCheckUsr = $pdo->prepare("SELECT id FROM usuarios WHERE aula_id = :id AND rol = 'aula' LIMIT 1");
                $stmtCheckUsr->execute([':id' => $aulaId]);
                $usrExistenteId = $stmtCheckUsr->fetchColumn();

                if ($usrExistenteId) {
                    $sets = [];
                    $params = [':id' => (int)$usrExistenteId];
                    if (!empty($usuarioLogin)) {
                        $sets[] = "`usuario` = :usuario";
                        $params[':usuario'] = $usuarioLogin;
                    }
                    if (!empty($password)) {
                        $sets[] = "`password_hash` = :pass";
                        $params[':pass'] = password_hash($password, PASSWORD_DEFAULT);
                    }
                    $sets[] = "`nombre_completo` = :nom";
                    $params[':nom'] = $nombre;

                    $stmtUsrUpd = $pdo->prepare("UPDATE usuarios SET " . implode(', ', $sets) . " WHERE id = :id");
                    $stmtUsrUpd->execute($params);
                } else if (!empty($usuarioLogin)) {
                    // Crear cuenta si no existía previamente
                    $passHash = password_hash(!empty($password) ? $password : 'aula123', PASSWORD_DEFAULT);
                    $stmtInsUsr = $pdo->prepare(
                        "INSERT INTO usuarios (usuario, password_hash, nombre_completo, rol, aula_id, activo, aulas_permitidas)
                         VALUES (:usr, :pass, :nom, 'aula', :aid, 1, :perm)"
                    );
                    $stmtInsUsr->execute([
                        ':usr' => $usuarioLogin,
                        ':pass' => $passHash,
                        ':nom' => $nombre,
                        ':aid' => $aulaId,
                        ':perm' => json_encode([$aulaId])
                    ]);
                }
            }

            $pdo->commit();

            registrarEvento($pdo, $auth['origen'], 'EDITAR_AULA', "Espacio #{$aulaId} actualizado a '{$nombre}' ({$tipo}) por {$auth['usuario']}", $aulaId);

            responderJSON([
                'ok' => true,
                'mensaje' => "Espacio '{$nombre}' ({$tipo}) actualizado exitosamente",
                'aula' => [
                    'id' => $aulaId,
                    'nombre' => $nombre,
                    'tipo' => $tipo,
                    'ubicacion' => $ubicacion,
                    'usuario_acceso' => $usuarioLogin
                ]
            ]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            responderJSON(['ok' => false, 'error' => 'Error al actualizar el espacio: ' . $e->getMessage()], 500);
        }
    }

    // 4. ACTIVAR / DESACTIVAR AULA
    if ($operacion === 'toggle_activa') {
        if ($aulaId <= 0) {
            responderJSON(['ok' => false, 'error' => 'ID de aula inválido'], 400);
        }

        $stmtCheck = $pdo->prepare("SELECT id, nombre, activa FROM aulas WHERE id = :id LIMIT 1");
        $stmtCheck->execute([':id' => $aulaId]);
        $aula = $stmtCheck->fetch();

        if (!$aula) {
            responderJSON(['ok' => false, 'error' => 'Aula no encontrada'], 404);
        }

        $nuevaActiva = ((int) $aula['activa'] === 1) ? 0 : 1;
        $stmtUpd = $pdo->prepare("UPDATE aulas SET activa = :activa WHERE id = :id");
        $stmtUpd->execute([':activa' => $nuevaActiva, ':id' => $aulaId]);

        $texto = $nuevaActiva === 1 ? 'activada' : 'desactivada';
        registrarEvento($pdo, $auth['origen'], 'ESTADO_AULA', "Aula '{$aula['nombre']}' #{$aulaId} {$texto} por {$auth['usuario']}", $aulaId);

        responderJSON([
            'ok' => true,
            'mensaje' => "Aula '{$aula['nombre']}' {$texto} correctamente",
            'activa' => $nuevaActiva
        ]);
    }

    // 5. REGENERAR API KEY ESP32
    if ($operacion === 'regenerar_key') {
        if ($aulaId <= 0) {
            responderJSON(['ok' => false, 'error' => 'ID de aula inválido'], 400);
        }

        $stmtCheck = $pdo->prepare("SELECT id, nombre FROM aulas WHERE id = :id LIMIT 1");
        $stmtCheck->execute([':id' => $aulaId]);
        $aula = $stmtCheck->fetch();

        if (!$aula) {
            responderJSON(['ok' => false, 'error' => 'Aula no encontrada'], 404);
        }

        $slug = preg_replace('/[^a-z0-9]/', '_', strtolower($aula['nombre']));
        $nuevaKey = 'esp32_key_' . substr($slug, 0, 10) . '_' . bin2hex(random_bytes(6));

        $stmtUpd = $pdo->prepare("UPDATE aulas SET api_key_esp32 = :key WHERE id = :id");
        $stmtUpd->execute([':key' => $nuevaKey, ':id' => $aulaId]);

        registrarEvento($pdo, $auth['origen'], 'REGENERAR_KEY_AULA', "API Key regenerada para aula '{$aula['nombre']}' #{$aulaId} por {$auth['usuario']}", $aulaId);

        responderJSON([
            'ok' => true,
            'mensaje' => "Nueva API Key generada para '{$aula['nombre']}'",
            'api_key_esp32' => $nuevaKey
        ]);
    }

    // 6. CLONAR CÓDIGOS IR DESDE OTRA AULA O GLOBALES
    if ($operacion === 'clonar_codigos') {
        $origenId = isset($datos['origen_aula_id']) ? (int) $datos['origen_aula_id'] : 0;
        $destinoId = isset($datos['destino_aula_id']) ? (int) $datos['destino_aula_id'] : $aulaId;

        if ($destinoId <= 0) {
            responderJSON(['ok' => false, 'error' => 'Debe indicar el aula de destino'], 400);
        }

        if ($origenId === $destinoId) {
            responderJSON(['ok' => false, 'error' => 'El aula de origen y destino no pueden ser la misma'], 422);
        }

        // Buscar códigos en el origen
        if ($origenId === 0) {
            $stmtCod = $pdo->query("SELECT * FROM codigos_ir WHERE aula_id IS NULL OR aula_id = 0");
            $origenNombre = 'Plantilla Global';
        } else {
            $stmtCod = $pdo->prepare("SELECT * FROM codigos_ir WHERE aula_id = :origen_id");
            $stmtCod->execute([':origen_id' => $origenId]);
            $origenNombre = "Aula #{$origenId}";
        }

        $codigos = $stmtCod->fetchAll();
        if (empty($codigos)) {
            responderJSON(['ok' => false, 'error' => "No se encontraron códigos IR en {$origenNombre} para clonar"], 404);
        }

        $stmtIns = $pdo->prepare(
            "INSERT INTO codigos_ir (dispositivo, accion, protocolo, codigo_hex, bits, aula_id)
             VALUES (:dispositivo, :accion, :protocolo, :codigo_hex, :bits, :aula_id)
             ON DUPLICATE KEY UPDATE protocolo = VALUES(protocolo), codigo_hex = VALUES(codigo_hex), bits = VALUES(bits)"
        );

        $clonados = 0;
        foreach ($codigos as $c) {
            $stmtIns->execute([
                ':dispositivo' => $c['dispositivo'],
                ':accion' => $c['accion'],
                ':protocolo' => $c['protocolo'],
                ':codigo_hex' => $c['codigo_hex'],
                ':bits' => $c['bits'],
                ':aula_id' => $destinoId
            ]);
            $clonados++;
        }

        registrarEvento(
            $pdo,
            $auth['origen'],
            'CLONAR_CODIGOS_IR',
            "{$clonados} códigos IR clonados desde {$origenNombre} hacia Aula #{$destinoId} por {$auth['usuario']}",
            $destinoId
        );

        responderJSON([
            'ok' => true,
            'mensaje' => "Se clonaron {$clonados} códigos IR exitosamente hacia el aula destino",
            'total_clonados' => $clonados
        ]);
    }

    // 7. ELIMINAR AULA
    if ($operacion === 'eliminar') {
        if ($aulaId <= 1) {
            responderJSON(['ok' => false, 'error' => 'No es posible eliminar el Aula principal por defecto (#1)'], 422);
        }

        $stmtCheck = $pdo->prepare("SELECT id, nombre FROM aulas WHERE id = :id LIMIT 1");
        $stmtCheck->execute([':id' => $aulaId]);
        $aula = $stmtCheck->fetch();

        if (!$aula) {
            responderJSON(['ok' => false, 'error' => 'Aula no encontrada'], 404);
        }

        // Limpiar comandos, códigos, estado, usuario y aula
        $pdo->prepare("DELETE FROM comandos_ir WHERE aula_id = :id")->execute([':id' => $aulaId]);
        $pdo->prepare("DELETE FROM codigos_ir WHERE aula_id = :id")->execute([':id' => $aulaId]);
        $pdo->prepare("DELETE FROM capturas_ir WHERE aula_id = :id")->execute([':id' => $aulaId]);
        $pdo->prepare("DELETE FROM estado_actual WHERE id = :id")->execute([':id' => $aulaId]);
        $pdo->prepare("DELETE FROM usuarios WHERE aula_id = :id AND rol = 'aula'")->execute([':id' => $aulaId]);
        $pdo->prepare("DELETE FROM aulas WHERE id = :id")->execute([':id' => $aulaId]);

        registrarEvento($pdo, $auth['origen'], 'ELIMINAR_AULA', "Aula '{$aula['nombre']}' #{$aulaId} eliminada por {$auth['usuario']}");

        responderJSON([
            'ok' => true,
            'mensaje' => "Aula '{$aula['nombre']}' y sus configuraciones fueron eliminadas correctamente"
        ]);
    }

    responderJSON(['ok' => false, 'error' => 'Operación no válida en /api/aulas'], 400);
}

