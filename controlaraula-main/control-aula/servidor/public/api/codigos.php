<?php
/**
 * GET  /api/codigos -> Lista códigos IR agrupados por dispositivo y acción, más la lista de dispositivos y aulas
 * POST /api/codigos -> Registra o actualiza un código IR con todos los campos avanzados (estado_hex, raw, repeticiones, etc.) y aula_id
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
    // 1. Obtener catálogo de dispositivos
    $stmtDisp = $pdo->query('SELECT id, nombre, etiqueta FROM dispositivos_ir ORDER BY nombre ASC');
    $dispositivos = $stmtDisp ? $stmtDisp->fetchAll() : [];

    // 2. Filtro opcional por aula y dispositivo
    $filtroAula = isset($_GET['aula_id']) && $_GET['aula_id'] !== '' ? (int)$_GET['aula_id'] : null;
    $filtroDispositivo = isset($_GET['dispositivo']) ? strtolower(trim($_GET['dispositivo'])) : '';

    $where = [];
    $params = [];

    if ($filtroAula !== null) {
        if ($filtroAula === 0) {
            $where[] = "(c.aula_id IS NULL OR c.aula_id = 0)";
        } else {
            $where[] = "c.aula_id = :aula_id";
            $params[':aula_id'] = $filtroAula;
        }
    }

    if (!empty($filtroDispositivo)) {
        $where[] = "c.dispositivo = :dispositivo";
        $params[':dispositivo'] = $filtroDispositivo;
    }

    $sql = "SELECT c.id, c.dispositivo, c.accion, c.protocolo, 
                   COALESCE(c.codigo, c.codigo_hex) AS codigo, c.bits, 
                   c.estado_hex, c.raw, c.repeticiones, c.pausa_ms, c.frecuencia_khz, 
                   c.descripcion, c.aula_id, c.actualizado_en,
                   a.nombre AS aula_nombre
            FROM codigos_ir c
            LEFT JOIN aulas a ON c.aula_id = a.id";

    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }
    $sql .= " ORDER BY c.dispositivo ASC, c.accion ASC, c.aula_id ASC";

    $stmtCodigos = $pdo->prepare($sql);
    $stmtCodigos->execute($params);
    $codigos = $stmtCodigos->fetchAll();

    foreach ($codigos as &$c) {
        $c['id'] = (int) $c['id'];
        $c['bits'] = (int) $c['bits'];
        $c['aula_id'] = ($c['aula_id'] !== null && (int)$c['aula_id'] > 0) ? (int)$c['aula_id'] : null;
        $c['es_global'] = empty($c['aula_id']);
    }
    unset($c);

    responderJSON([
        'ok' => true,
        'dispositivos' => $dispositivos,
        'codigos' => $codigos
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarTokenCSRFSiEsPanel($auth);
    $datos = obtenerDatosEntrada();

    $operacion = strtolower(trim($datos['operacion'] ?? 'guardar'));

    // Operación ELIMINAR CÓDIGO
    if ($operacion === 'eliminar') {
        $id = isset($datos['id']) ? (int) $datos['id'] : 0;
        if ($id <= 0) {
            responderJSON(['ok' => false, 'error' => 'ID de código inválido'], 400);
        }
        $stmtDel = $pdo->prepare("DELETE FROM codigos_ir WHERE id = :id");
        $stmtDel->execute([':id' => $id]);
        registrarEvento($pdo, $auth['origen'], 'ELIMINAR_CODIGO_IR', "Código IR #{$id} eliminado por {$auth['usuario']}");
        responderJSON(['ok' => true, 'mensaje' => 'Código eliminado exitosamente']);
    }

    $dispositivo = strtolower(trim($datos['dispositivo'] ?? 'aire'));
    $accion = strtolower(trim($datos['accion'] ?? ''));
    $protocolo = strtoupper(trim($datos['protocolo'] ?? 'NEC'));
    $codigo = !empty($datos['codigo']) ? trim($datos['codigo']) : (!empty($datos['codigo_hex']) ? trim($datos['codigo_hex']) : null);
    $bits = isset($datos['bits']) ? max(1, (int) $datos['bits']) : 32;
    $estadoHex = !empty($datos['estado_hex']) ? trim($datos['estado_hex']) : null;
    $raw = !empty($datos['raw']) ? trim($datos['raw']) : null;
    $repeticiones = isset($datos['repeticiones']) ? max(1, min(10, (int) $datos['repeticiones'])) : 1;
    $pausaMs = isset($datos['pausa_ms']) ? max(0, min(5000, (int) $datos['pausa_ms'])) : 100;
    $frecuenciaKhz = isset($datos['frecuencia_khz']) ? max(10, min(60, (int) $datos['frecuencia_khz'])) : 38;
    $descripcion = trim($datos['descripcion'] ?? '');
    
    // Aula ID: null / 0 = Plantilla Global
    $aulaId = isset($datos['aula_id']) && (int)$datos['aula_id'] > 0 ? (int)$datos['aula_id'] : null;

    // Validaciones
    if (empty($dispositivo) || !preg_match('/^[a-z0-9_-]{2,50}$/', $dispositivo)) {
        responderJSON(['ok' => false, 'error' => 'Dispositivo invalido (ej. "aire" o "proyector")'], 422);
    }

    if (empty($accion) || !preg_match('/^[a-z0-9_-]{2,50}$/', $accion)) {
        responderJSON(['ok' => false, 'error' => 'Accion invalida (use entre 2 y 50 caracteres alfanumericos o guiones)'], 422);
    }

    if (empty($protocolo) || !preg_match('/^[A-Z0-9_]{2,30}$/', $protocolo)) {
        responderJSON(['ok' => false, 'error' => 'Protocolo invalido (ej. NEC, SONY, RC5, SAMSUNG, RAW)'], 422);
    }

    if ($codigo !== null && !preg_match('/^0x[0-9a-fA-F]+$/i', $codigo)) {
        responderJSON(['ok' => false, 'error' => 'Formato de codigo hexadecimal invalido (debe comenzar con 0x)'], 422);
    }

    if (empty($codigo) && empty($estadoHex) && empty($raw)) {
        responderJSON(['ok' => false, 'error' => 'Debe ingresar al menos un valor de transmision: codigo HEX, estado_hex o datos raw'], 422);
    }

    // Asegurar que el dispositivo exista en dispositivos_ir
    $stmtCheckDisp = $pdo->prepare('INSERT INTO dispositivos_ir (nombre, etiqueta) VALUES (:nombre, :etiqueta) ON DUPLICATE KEY UPDATE `id` = `id`');
    $stmtCheckDisp->execute([
        ':nombre' => $dispositivo,
        ':etiqueta' => ucfirst($dispositivo)
    ]);

    // Insertar o actualizar en codigos_ir
    $stmtUpsert = $pdo->prepare(
        'INSERT INTO codigos_ir 
         (dispositivo, accion, protocolo, codigo, codigo_hex, bits, estado_hex, raw, repeticiones, pausa_ms, frecuencia_khz, descripcion, aula_id, actualizado_en)
         VALUES 
         (:dispositivo, :accion, :protocolo, :codigo, :codigo_hex, :bits, :estado_hex, :raw, :repeticiones, :pausa_ms, :frecuencia_khz, :descripcion, :aula_id, NOW())
         ON DUPLICATE KEY UPDATE
            protocolo = VALUES(protocolo),
            codigo = VALUES(codigo),
            codigo_hex = VALUES(codigo_hex),
            bits = VALUES(bits),
            estado_hex = VALUES(estado_hex),
            raw = VALUES(raw),
            repeticiones = VALUES(repeticiones),
            pausa_ms = VALUES(pausa_ms),
            frecuencia_khz = VALUES(frecuencia_khz),
            descripcion = VALUES(descripcion),
            actualizado_en = NOW()'
    );

    $stmtUpsert->execute([
        ':dispositivo'    => $dispositivo,
        ':accion'         => $accion,
        ':protocolo'      => $protocolo,
        ':codigo'         => $codigo,
        ':codigo_hex'     => $codigo,
        ':bits'           => $bits,
        ':estado_hex'     => $estadoHex,
        ':raw'            => $raw,
        ':repeticiones'   => $repeticiones,
        ':pausa_ms'       => $pausaMs,
        ':frecuencia_khz' => $frecuenciaKhz,
        ':descripcion'    => substr($descripcion, 0, 255),
        ':aula_id'        => $aulaId
    ]);

    $alcance = $aulaId ? "Aula #{$aulaId}" : "Plantilla Global";
    registrarEvento(
        $pdo,
        $auth['origen'],
        'GUARDAR_CODIGO_IR',
        "Codigo guardado para {$dispositivo}/{$accion} ({$protocolo}) en {$alcance} por {$auth['usuario']}",
        $aulaId
    );

    responderJSON([
        'ok' => true,
        'mensaje' => "Codigo IR para '{$dispositivo}' -> '{$accion}' guardado exitosamente ({$alcance})",
        'codigo' => [
            'dispositivo'    => $dispositivo,
            'accion'         => $accion,
            'protocolo'      => $protocolo,
            'codigo'         => $codigo,
            'bits'           => $bits,
            'estado_hex'     => $estadoHex,
            'tiene_raw'      => !empty($raw),
            'repeticiones'   => $repeticiones,
            'pausa_ms'       => $pausaMs,
            'frecuencia_khz' => $frecuenciaKhz,
            'descripcion'    => $descripcion,
            'aula_id'        => $aulaId,
            'es_global'      => empty($aulaId)
        ]
    ]);
}

responderJSON(['ok' => false, 'error' => 'Metodo no permitido'], 405);
