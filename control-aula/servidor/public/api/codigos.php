<?php
/**
 * GET  /api/codigos -> Lista códigos IR agrupados por dispositivo y acción, más la lista de dispositivos
 * POST /api/codigos -> Registra o actualiza un código IR con todos los campos avanzados (estado_hex, raw, repeticiones, etc.)
 */

require_once __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../app/db.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/funciones.php';

habilitarCORS();

$auth = autenticarPanelOApp();
$pdo = obtenerConexionDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // 1. Obtener catálogo de dispositivos
    $stmtDisp = $pdo->query('SELECT id, nombre, etiqueta FROM dispositivos_ir ORDER BY nombre ASC');
    $dispositivos = $stmtDisp->fetchAll();

    // 2. Obtener códigos IR (con filtro opcional por dispositivo)
    $filtroDispositivo = isset($_GET['dispositivo']) ? strtolower(trim($_GET['dispositivo'])) : '';

    if (!empty($filtroDispositivo)) {
        $stmtCodigos = $pdo->prepare(
            'SELECT id, dispositivo, accion, protocolo, codigo, bits, estado_hex, raw, repeticiones, pausa_ms, frecuencia_khz, descripcion, actualizado_en 
             FROM codigos_ir 
             WHERE dispositivo = :dispositivo 
             ORDER BY accion ASC'
        );
        $stmtCodigos->execute([':dispositivo' => $filtroDispositivo]);
    } else {
        $stmtCodigos = $pdo->query(
            'SELECT id, dispositivo, accion, protocolo, codigo, bits, estado_hex, raw, repeticiones, pausa_ms, frecuencia_khz, descripcion, actualizado_en 
             FROM codigos_ir 
             ORDER BY dispositivo ASC, accion ASC'
        );
    }
    $codigos = $stmtCodigos->fetchAll();

    responderJSON([
        'ok' => true,
        'dispositivos' => $dispositivos,
        'codigos' => $codigos
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarTokenCSRFSiEsPanel($auth);
    $datos = obtenerDatosEntrada();

    $dispositivo = strtolower(trim($datos['dispositivo'] ?? 'aire'));
    $accion = strtolower(trim($datos['accion'] ?? ''));
    $protocolo = strtoupper(trim($datos['protocolo'] ?? 'NEC'));
    $codigo = !empty($datos['codigo']) ? trim($datos['codigo']) : null;
    $bits = isset($datos['bits']) ? max(1, (int) $datos['bits']) : 32;
    $estadoHex = !empty($datos['estado_hex']) ? trim($datos['estado_hex']) : null;
    $raw = !empty($datos['raw']) ? trim($datos['raw']) : null;
    $repeticiones = isset($datos['repeticiones']) ? max(1, min(10, (int) $datos['repeticiones'])) : 1;
    $pausaMs = isset($datos['pausa_ms']) ? max(0, min(5000, (int) $datos['pausa_ms'])) : 100;
    $frecuenciaKhz = isset($datos['frecuencia_khz']) ? max(10, min(60, (int) $datos['frecuencia_khz'])) : 38;
    $descripcion = trim($datos['descripcion'] ?? '');

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

    // Al menos uno de codigo, estado_hex o raw debe estar presente
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
         (dispositivo, accion, protocolo, codigo, bits, estado_hex, raw, repeticiones, pausa_ms, frecuencia_khz, descripcion, actualizado_en)
         VALUES 
         (:dispositivo, :accion, :protocolo, :codigo, :bits, :estado_hex, :raw, :repeticiones, :pausa_ms, :frecuencia_khz, :descripcion, NOW())
         ON DUPLICATE KEY UPDATE
            protocolo = VALUES(protocolo),
            codigo = VALUES(codigo),
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
        ':bits'           => $bits,
        ':estado_hex'     => $estadoHex,
        ':raw'            => $raw,
        ':repeticiones'   => $repeticiones,
        ':pausa_ms'       => $pausaMs,
        ':frecuencia_khz' => $frecuenciaKhz,
        ':descripcion'    => substr($descripcion, 0, 255)
    ]);

    registrarEvento(
        $pdo,
        $auth['origen'],
        'GUARDAR_CODIGO_IR',
        "Codigo guardado para {$dispositivo}/{$accion} ({$protocolo}) por {$auth['usuario']}"
    );

    responderJSON([
        'ok' => true,
        'mensaje' => "Codigo IR para '{$dispositivo}' -> '{$accion}' guardado exitosamente",
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
            'descripcion'    => $descripcion
        ]
    ]);
}

responderJSON(['ok' => false, 'error' => 'Metodo no permitido'], 405);
