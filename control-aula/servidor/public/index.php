<?php
/**
 * Panel de Control Principal - Control de Aula
 * Vistas diferenciadas por rol:
 *  - Profesor: Interfaz limpia y directa con Escenas Rápidas, Termostato de Aire Acondicionado,
 *              Control de Proyector Multimedia, Iluminación y Centro de Configuración Avanzado.
 *  - Admin: Acceso completo + Gestión de Usuarios, Catálogo de Códigos IR, Capturas KY-022, Telemetría y Simulador ESP32.
 */

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/funciones.php';

iniciarSesionSegura();

// Control de acceso: requerir sesión activa
if (empty($_SESSION['autenticado']) || $_SESSION['autenticado'] !== true) {
    header('Location: login.php');
    exit;
}

$csrfToken = obtenerTokenCSRF();
$usuario = $_SESSION['usuario'] ?? 'admin';
$nombreCompleto = $_SESSION['nombre_completo'] ?? $usuario;
$rol = $_SESSION['rol'] ?? 'admin';
$esAdmin = ($rol === 'admin');
$usuarioIdActual = (int) ($_SESSION['usuario_id'] ?? 1);

$pdo = obtenerConexionDB();
$prefsUsuarioDB = obtenerPreferenciasUsuarioDB($pdo, $usuarioIdActual);

// Si es cuenta de aula/espacio, obtener los datos del espacio asignado
$aulaFija = null;
if ($rol === 'aula') {
    $aid = (int)($_SESSION['aula_id'] ?? 1);
    if ($aid <= 0) {
        $stmtUsr = $pdo->prepare('SELECT aula_id FROM usuarios WHERE id = :id LIMIT 1');
        $stmtUsr->execute([':id' => $usuarioIdActual]);
        $aid = (int)$stmtUsr->fetchColumn();
    }
    $aulaFija = obtenerEstadoAula($pdo, $aid > 0 ? $aid : 1);
}
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="light" data-theme="claro" data-accent="azul" data-font="sans" data-density="comoda" data-contrast="normal" data-motion="normal">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0d6efd">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Control Aula">
    <link rel="manifest" href="manifest.json">
    <link rel="icon" type="image/svg+xml" href="assets/icons/icon.svg">
    <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="user-role" content="<?php echo htmlspecialchars($rol, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="user-id" content="<?php echo $usuarioIdActual; ?>">
    <?php if ($aulaFija): ?>
    <meta name="user-aula-id" content="<?php echo (int)$aulaFija['id']; ?>">
    <?php endif; ?>
    <title>Panel de Control | Control de Aula</title>
    <!-- Bootstrap 5.3 CSS & Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script>
        window.PREFS_SERVIDOR_DB = <?php echo json_encode($prefsUsuarioDB, JSON_UNESCAPED_UNICODE); ?>;
        window.AULA_SESION_ID = <?php echo $aulaFija ? (int)$aulaFija['id'] : 1; ?>;
        window.USER_ROL = "<?php echo htmlspecialchars($rol, ENT_QUOTES, 'UTF-8'); ?>";
        (function() {
            try {
                var prefsLocal = JSON.parse(localStorage.getItem('control_aula_prefs_bs5') || '{}');
                var prefs = Object.assign({}, window.PREFS_SERVIDOR_DB || {}, prefsLocal);
                if (window.PREFS_SERVIDOR_DB) {
                    prefs = Object.assign({}, prefsLocal, window.PREFS_SERVIDOR_DB);
                    localStorage.setItem('control_aula_prefs_bs5', JSON.stringify(prefs));
                }
                var root = document.documentElement;
                var tema = prefs.tema || 'claro';
                if (tema === 'auto') {
                    tema = (window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches) ? 'claro' : 'oscuro';
                }
                root.setAttribute('data-theme', tema);
                root.setAttribute('data-bs-theme', tema === 'claro' ? 'light' : 'dark');
                root.setAttribute('data-accent', prefs.acento || 'azul');
                if (prefs.fuente) root.setAttribute('data-font', prefs.fuente);
                if (prefs.densidad) root.setAttribute('data-density', prefs.densidad);
                if (prefs.altoContraste) root.setAttribute('data-contrast', 'alto');
                if (prefs.reducirAnimaciones) root.setAttribute('data-motion', 'reducido');
                if (prefs.escalaPct) {
                    var pct = Math.max(85, Math.min(130, parseInt(prefs.escalaPct, 10) || 100));
                    root.style.setProperty('--ui-scale', (pct / 100).toFixed(2));
                }
                if (prefs.acento === 'custom' && prefs.colorCustom) {
                    root.style.setProperty('--color-primary', prefs.colorCustom);
                    root.style.setProperty('--color-primary-hover', prefs.colorCustom);
                    root.style.setProperty('--text-gold', prefs.colorCustom);
                }
            } catch (e) {}
        })();
    </script>
    <link rel="stylesheet" href="assets/css/estilos.css?v=20261126b">
</head>
<body class="bg-body-tertiary">
    <div class="container py-3 panel-contenedor">
        <!-- Cabecera Principal -->
        <header class="bg-body border rounded-3 shadow-sm px-3 py-2 mb-3 cabecera-principal">
            <div class="marca-titulo d-flex align-items-center gap-2">
                <div class="marca-icono bg-primary text-white rounded-2 d-flex align-items-center justify-content-center">
                    <i class="bi bi-hdd-network fs-5"></i>
                </div>
                <div class="marca-info">
                    <h1 class="h5 fw-bold mb-0">Control de Aula</h1>
                    <span class="text-body-secondary small">Climatización, Iluminación y Proyección</span>
                </div>
            </div>

            <div class="cabecera-acciones d-flex align-items-center flex-wrap gap-2">
                <?php if ($esAdmin): ?>
                <!-- Selector Dinámico de Espacio (Solo visible para Administradores) -->
                <div class="selector-aula-contenedor input-group input-group-sm" style="width: auto;">
                    <label class="input-group-text fw-semibold" for="selectAulaHeader">
                        <i class="bi bi-door-open me-1 text-primary"></i>Espacio:
                    </label>
                    <select id="selectAulaHeader" class="form-select form-select-sm select-aula">
                        <option value="1">Cargando espacios...</option>
                    </select>
                </div>
                <?php else: ?>
                <!-- Indicador Fijo del Salón Físico (Cuenta de Espacio) -->
                <?php
                $tipoEsp = $aulaFija['tipo'] ?? 'Aula';
                $nomEspRaw = $aulaFija['nombre'] ?? 'Aula 101';
                $nomEsp = trim(str_replace('- General', '', $nomEspRaw));
                ?>
                <div class="espacio-fijo-badge badge text-bg-light border px-3 py-2 d-inline-flex align-items-center gap-2" title="Espacio físico asignado a esta terminal">
                    <?php if ($tipoEsp === 'Laboratorio'): ?>
                    <i class="bi bi-cpu text-primary"></i>
                    <?php elseif ($tipoEsp === 'Taller'): ?>
                    <i class="bi bi-tools text-primary"></i>
                    <?php else: ?>
                    <i class="bi bi-house-door text-primary"></i>
                    <?php endif; ?>
                    <span class="fw-semibold"><?php echo htmlspecialchars($nomEsp, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <?php endif; ?>

                <div id="badgeConexion" class="badge-conexion offline">
                    <span class="pulso-punto"></span>
                    <span id="textoConexion">OFFLINE</span>
                </div>

                <button type="button" id="btnAbrirConfig" class="btn btn-outline-primary btn-sm px-3 fw-medium btn-config-header" title="Centro de Configuración y Perfil">
                    <i class="bi bi-gear"></i>
                    <span>Configuración</span>
                </button>

                <a href="logout.php" class="btn btn-outline-danger btn-sm px-3 fw-medium" title="Cerrar sesión">
                    <i class="bi bi-box-arrow-right"></i>
                    <span>Salir</span>
                </a>
            </div>
        </header>

        <!-- Barra Superior de Escenas Rápidas del Aula -->
        <section class="escenas-seccion mb-3" aria-label="Escenas rápidas del aula">
            <span id="escenasFeedback" class="escenas-feedback badge text-bg-success mb-2" style="display:none;"></span>
            <div class="barra-escenas">
                <button type="button" class="btn-escena card shadow-sm" data-escena="modo_proyeccion">
                    <div class="escena-icono text-primary">
                        <i class="bi bi-film fs-5"></i>
                    </div>
                    <div class="escena-info">
                        <strong>Modo Proyección</strong>
                        <span>Apaga las luces del aula y enciende el proyector</span>
                    </div>
                </button>

                <button type="button" class="btn-escena card shadow-sm" data-escena="iniciar_clase">
                    <div class="escena-icono text-success">
                        <i class="bi bi-easel2 fs-5"></i>
                    </div>
                    <div class="escena-info">
                        <strong>Iniciar Clase</strong>
                        <span id="descIniciarClase">Enciende luces y aire en 24°C (Frío)</span>
                    </div>
                </button>

                <button type="button" class="btn-escena btn-escena-peligro card shadow-sm" data-escena="cerrar_aula">
                    <div class="escena-icono text-danger">
                        <i class="bi bi-power fs-5"></i>
                    </div>
                    <div class="escena-info">
                        <strong>Cerrar Aula (Apagar Todo)</strong>
                        <span>Apaga luces, aire acondicionado y proyector</span>
                    </div>
                </button>
            </div>
        </section>

        <!-- Grilla de Controles Principales del Aula -->
        <main class="grilla-controles">
            <!-- ===============================================================
                 TARJETA 1: AIRE ACONDICIONADO
                 =============================================================== -->
            <article class="tarjeta-control card shadow-sm">
                <div class="control-header">
                    <div class="control-icono-titulo">
                        <div class="icono-dispositivo" id="iconoAire">
                            <i class="bi bi-wind fs-4"></i>
                        </div>
                        <div class="control-textos">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <h2 class="h6 fw-bold mb-0">Aire Acondicionado</h2>
                                <span class="hw-chip hw-chip-star badge rounded-pill" id="hwChipAire" title="Equipo compatible Star Series (Coolix OEM 24-bit)">
                                    <i class="bi bi-snow2"></i>
                                    <span id="hwChipAireTexto">Star Series · Coolix OEM</span>
                                </span>
                            </div>
                            <p class="mb-0 text-body-secondary small">Control remoto inteligente de climatización</p>
                            <span id="colaAireInfo" class="text-primary fw-semibold" style="display:none; font-size:11px; margin-top:2px;"></span>
                        </div>
                    </div>
                    <span id="pastillaAire" class="estado-pastilla off">APAGADO</span>
                </div>

                <!-- Botones Principales de Encendido / Apagado -->
                <div class="control-botones">
                    <button type="button" id="btnAireOn" class="btn btn-primary btn-grande btn-primario">
                        <i class="bi bi-power"></i>
                        <span>Encender Aire</span>
                    </button>
                    <button type="button" id="btnAireOff" class="btn btn-danger btn-grande btn-peligro">
                        <i class="bi bi-stop-circle"></i>
                        <span>Apagar Aire</span>
                    </button>
                </div>
            </article>

            <!-- ===============================================================
                 TARJETA 2: ILUMINACIÓN DEL AULA
                 =============================================================== -->
            <article class="tarjeta-control card shadow-sm">
                <div class="control-header">
                    <div class="control-icono-titulo">
                        <div class="icono-dispositivo" id="iconoLuces">
                            <i class="bi bi-lightbulb fs-4"></i>
                        </div>
                        <div class="control-textos">
                            <h2 class="h6 fw-bold mb-0">Iluminación</h2>
                            <p class="mb-0 text-body-secondary small">Circuito general de luminarias del aula</p>
                        </div>
                    </div>
                    <span id="pastillaLuces" class="estado-pastilla off">APAGADAS</span>
                </div>

                <div class="control-botones">
                    <button type="button" id="btnLucesOn" class="btn btn-success btn-grande btn-exito">
                        <i class="bi bi-lightbulb-fill"></i>
                        <span>Encender Luces</span>
                    </button>
                    <button type="button" id="btnLucesOff" class="btn btn-danger btn-grande btn-peligro">
                        <i class="bi bi-lightbulb-off"></i>
                        <span>Apagar Luces</span>
                    </button>
                </div>
            </article>

            <!-- ===============================================================
                 TARJETA 3: PROYECTOR MULTIMEDIA AVANZADO
                 =============================================================== -->
            <article class="tarjeta-control tarjeta-proyector-destacada card shadow-sm">
                <div class="control-header">
                    <div class="control-icono-titulo">
                        <div class="icono-dispositivo" id="iconoProyector">
                            <i class="bi bi-projector fs-4"></i>
                        </div>
                        <div class="control-textos">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <h2 class="h6 fw-bold mb-0">Proyector Multimedia</h2>
                            </div>
                            <p class="mb-0 text-body-secondary small">Alimentación, fuente de video y funciones de clase</p>
                            <span id="colaProyectorInfo" class="text-primary fw-semibold" style="display:none; font-size:11px; margin-top:2px;"></span>
                        </div>
                    </div>
                    <span id="pastillaProyector" class="estado-pastilla off">APAGADO</span>
                </div>

                <!-- Encendido y Apagado -->
                <div class="control-botones">
                    <button type="button" id="btnProyectorOn" class="btn btn-primary btn-grande btn-primario">
                        <i class="bi bi-power"></i>
                        <span>Encender</span>
                    </button>
                    <button type="button" id="btnProyectorOff" class="btn btn-danger btn-grande btn-peligro">
                        <i class="bi bi-stop-circle"></i>
                        <span>Apagar</span>
                    </button>
                </div>

                <!-- Selector de Fuente de Entrada -->
                <div class="subbloque-control">
                    <div class="subbloque-label">
                        <span>Fuente de Entrada (Source)</span>
                        <span id="labelProyFuente" class="badge text-bg-secondary">HDMI 1</span>
                    </div>
                    <div class="grilla-botones-opciones cols-3">
                        <button type="button" class="btn-opcion-control horizontal activo" data-proy-fuente="hdmi1">
                            <i class="bi bi-hdmi"></i>
                            <span>HDMI 1</span>
                        </button>
                        <button type="button" class="btn-opcion-control horizontal" data-proy-fuente="hdmi2">
                            <i class="bi bi-hdmi"></i>
                            <span>HDMI 2</span>
                        </button>
                        <button type="button" class="btn-opcion-control horizontal" data-proy-fuente="vga">
                            <i class="bi bi-pc-display-horizontal"></i>
                            <span>VGA</span>
                        </button>
                    </div>
                </div>

                <!-- Funciones de Clase: Freeze y AV Mute -->
                <div class="subbloque-control">
                    <div class="subbloque-label">
                        <span>Funciones de Clase</span>
                    </div>
                    <div class="control-botones">
                        <button type="button" id="btnProyFreeze" class="btn-opcion-control horizontal" title="Congelar imagen proyectada mientras usas tu PC">
                            <i class="bi bi-pause-circle"></i>
                            <span>Congelar Imagen</span>
                        </button>
                        <button type="button" id="btnProyMute" class="btn-opcion-control horizontal" title="Poner pantalla en negro (AV Mute) sin apagar la lámpara">
                            <i class="bi bi-eye-slash"></i>
                            <span>Pantalla en Negro</span>
                        </button>
                    </div>
                </div>

                <!-- Control de Volumen Ergonómico del Proyector -->
                <div class="subbloque-control">
                    <div class="subbloque-label">
                        <span>Audio del Proyector</span>
                        <span id="proyVolTexto" class="volumen-indicador badge text-bg-primary">50%</span>
                    </div>

                    <!-- Fila integrada de control de volumen -->
                    <div class="proyector-volumen-fila">
                        <button type="button" id="btnProyVolMute" class="btn-vol-mute" title="Silenciar audio (Mute)" aria-label="Silenciar audio">
                            <i class="bi bi-volume-up-fill icono-parlante"></i>
                            <i class="bi bi-volume-mute-fill icono-parlante-mute" style="display:none;"></i>
                        </button>

                        <button type="button" id="btnProyVolMenos" class="btn-vol-step" title="Bajar volumen (-5%)" aria-label="Bajar volumen">&minus;</button>

                        <div class="slider-volumen-contenedor">
                            <input type="range" id="sliderProyVol" class="form-range slider-rango slider-volumen" min="0" max="100" step="5" value="50" aria-label="Barra de volumen del proyector">
                        </div>

                        <button type="button" id="btnProyVolMas" class="btn-vol-step" title="Subir volumen (+5%)" aria-label="Subir volumen">&#43;</button>
                    </div>

                    <!-- Botones rápidos (pills) para niveles directos -->
                    <div class="proyector-volumen-pills">
                        <button type="button" class="btn-vol-pill" data-vol="25">25%</button>
                        <button type="button" class="btn-vol-pill" data-vol="50">50%</button>
                        <button type="button" class="btn-vol-pill" data-vol="100">100%</button>
                    </div>
                </div>
            </article>
        </main>

        <?php if ($esAdmin): ?>
        <!-- ===================================================================
             MÓDULO DE ADMINISTRACIÓN Y HERRAMIENTAS TÉCNICAS (SOLO ROL ADMIN)
             =================================================================== -->
        <div class="admin-panel-wrapper" id="adminPanelWrapper">
            <div class="admin-toggle-header card shadow-sm" id="btnToggleAdminPanel" role="button" tabindex="0" aria-expanded="true">
                <div class="admin-toggle-titulo">
                    <i class="bi bi-shield-check text-primary fs-5"></i>
                    <span>Panel de Administración</span>
                </div>
                <span class="btn btn-outline-secondary btn-sm btn-secundario btn-pequeno" id="textoToggleAdmin">Ocultar herramientas</span>
            </div>

            <section id="contenedorAdminTecnico" class="seccion-tabs">
                <nav class="tabs-navegacion nav nav-tabs mb-3" aria-label="Pestañas de administración">
                    <button type="button" class="tab-btn nav-link activo" data-target="tab-aulas">
                        <i class="bi bi-building"></i>
                        <span>Gestión de Aulas</span>
                    </button>
                    <button type="button" class="tab-btn nav-link" data-target="tab-horarios">
                        <i class="bi bi-clock-history"></i>
                        <span>Horarios Automáticos</span>
                    </button>
                    <button type="button" class="tab-btn nav-link" data-target="tab-usuarios">
                        <i class="bi bi-person-badge"></i>
                        <span>Administradores</span>
                    </button>
                    <button type="button" class="tab-btn nav-link" data-target="tab-log">
                        <i class="bi bi-journal-text"></i>
                        <span>Registro de Eventos</span>
                    </button>
                </nav>

                <!-- Pestaña 0: Tablero, KPIs, Tipos de Espacio y Catálogo de Aulas -->
                <div id="tab-aulas" class="tab-contenido activo">
                    <!-- 1. Resumen Rápido del Colegio (KPIs) -->
                    <div class="kpi-grid mb-3" id="kpiResumenColegio">
                        <div class="kpi-card card shadow-sm" data-kpi-filtro="online" role="button" title="Filtrar espacios online">
                            <div class="kpi-icono kpi-verde"><i class="bi bi-wifi"></i></div>
                            <div class="kpi-info">
                                <span class="kpi-label">Espacios Online</span>
                                <strong class="kpi-valor" id="kpiOnline">0 / 0</strong>
                            </div>
                        </div>
                        <div class="kpi-card card shadow-sm" data-kpi-filtro="encendidos" role="button" title="Filtrar espacios con luces encendidas">
                            <div class="kpi-icono kpi-amarillo"><i class="bi bi-lightbulb-fill"></i></div>
                            <div class="kpi-info">
                                <span class="kpi-label">Luces Encendidas</span>
                                <strong class="kpi-valor" id="kpiLuces">0</strong>
                            </div>
                        </div>
                        <div class="kpi-card card shadow-sm" data-kpi-filtro="encendidos" role="button" title="Filtrar espacios con aire encendido">
                            <div class="kpi-icono kpi-celeste"><i class="bi bi-wind"></i></div>
                            <div class="kpi-info">
                                <span class="kpi-label">Aires Encendidos</span>
                                <strong class="kpi-valor" id="kpiAires">0</strong>
                            </div>
                        </div>
                        <div class="kpi-card card shadow-sm" data-kpi-filtro="encendidos" role="button" title="Filtrar espacios con proyector encendido">
                            <div class="kpi-icono kpi-azul"><i class="bi bi-projector-fill"></i></div>
                            <div class="kpi-info">
                                <span class="kpi-label">Proyectores Activos</span>
                                <strong class="kpi-valor" id="kpiProyectores">0</strong>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Tablero Multi-Aula en Vivo + Apagado Selectivo + Filtros -->
                    <div class="tarjeta-seccion card shadow-sm">
                        <div class="seccion-encabezado d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <h3 class="h6 fw-bold mb-1">Tablero Central Multi-Aula en Tiempo Real</h3>
                                <p class="text-body-secondary small mb-0">Monitoreo y control simultáneo de todos los espacios de la institución.</p>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <button type="button" class="btn btn-outline-warning btn-sm fw-medium btn-apagar-servicio" data-servicio="luces" title="Apagar luces en todas las aulas activas">
                                    <i class="bi bi-lightbulb-off"></i>
                                    <span>Apagar Luces</span>
                                </button>
                                <button type="button" class="btn btn-outline-info btn-sm fw-medium btn-apagar-servicio" data-servicio="aire" title="Apagar aires acondicionados en todas las aulas activas">
                                    <i class="bi bi-wind"></i>
                                    <span>Apagar Aires</span>
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm fw-medium btn-apagar-servicio" data-servicio="proyector" title="Apagar proyectores en todas las aulas activas">
                                    <i class="bi bi-projector"></i>
                                    <span>Apagar Proyectores</span>
                                </button>
                                <button type="button" id="btnApagarTodasAulas" class="btn btn-danger btn-sm btn-peligro fw-semibold" title="Apagado general de emergencia en todas las aulas">
                                    <i class="bi bi-power"></i>
                                    <span>Apagar Todo</span>
                                </button>
                            </div>
                        </div>

                        <!-- Barra de Filtros Rápidos del Tablero -->
                        <div class="filtros-barra d-flex flex-wrap align-items-center gap-2 mt-2 mb-2">
                            <div class="input-group input-group-sm" style="max-width: 240px;">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text" id="filtroTableroTexto" class="form-control" placeholder="Buscar espacio...">
                            </div>
                            <select id="filtroTableroTipo" class="form-select form-select-sm" style="width: auto; min-width: 165px;">
                                <option value="">Todos los tipos</option>
                            </select>
                            <div class="btn-group btn-group-sm" role="group" aria-label="Filtro por estado">
                                <button type="button" class="btn btn-outline-secondary active" data-filtro-estado="todos">Todos</button>
                                <button type="button" class="btn btn-outline-secondary" data-filtro-estado="encendidos">Con equipos ON</button>
                                <button type="button" class="btn btn-outline-secondary" data-filtro-estado="online">Solo Online</button>
                            </div>
                        </div>

                        <div id="tableroAulasFeedback" class="mb-2"></div>
                        <div id="tableroAulasGrid" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:16px; margin-top:12px;">
                            <div class="text-center py-3 text-body-secondary" style="grid-column:1/-1;">Cargando estado de las aulas...</div>
                        </div>
                    </div>

                    <!-- 3. Gestión de Tipos de Espacio Personalizados -->
                    <div class="tarjeta-seccion card shadow-sm mt-3">
                        <div class="seccion-encabezado mb-2">
                            <div>
                                <h3 class="h6 fw-bold mb-1">Tipos de Espacio</h3>
                                <p class="text-body-secondary small mb-0">Creá nuevas categorías (ej. SUM, Biblioteca, Dirección, Preceptoría) para clasificar los salones.</p>
                            </div>
                        </div>
                        <div id="tiposEspacioFeedback"></div>
                        <div id="listaTiposEspacio" class="d-flex flex-wrap gap-2 mb-3">
                            <span class="text-body-secondary small">Cargando tipos...</span>
                        </div>
                        <form id="formTipoEspacio" class="d-flex flex-wrap align-items-end gap-2 pt-2 border-top">
                            <div class="campo-grupo flex-grow-1" style="min-width: 200px;">
                                <label for="nuevoTipoNombre" class="form-label">Nuevo Tipo de Espacio</label>
                                <input type="text" class="form-control form-control-sm" id="nuevoTipoNombre" placeholder="ej. Dirección, Sala de Profesores, SUM..." required maxlength="60">
                            </div>
                            <div class="campo-grupo" style="min-width: 170px;">
                                <label for="nuevoTipoIcono" class="form-label">Ícono</label>
                                <select id="nuevoTipoIcono" class="form-select form-select-sm">
                                    <option value="bi-door-open">Puerta / Aula</option>
                                    <option value="bi-cpu">Tecnología / Laboratorio</option>
                                    <option value="bi-tools">Herramientas / Taller</option>
                                    <option value="bi-people">Personas / SUM</option>
                                    <option value="bi-book">Libros / Biblioteca</option>
                                    <option value="bi-building">Edificio / Sector</option>
                                    <option value="bi-briefcase">Oficina / Dirección</option>
                                    <option value="bi-pc-display">Informática / Sala PC</option>
                                    <option value="bi-easel2">Salón de Actos</option>
                                    <option value="bi-mortarboard">Académico</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm px-3 fw-medium">
                                <i class="bi bi-plus-circle"></i>
                                <span>Agregar Tipo</span>
                            </button>
                        </form>
                    </div>

                    <!-- 4. Tabla de Catálogo y Administración de Espacios Físicos -->
                    <div class="tarjeta-seccion card shadow-sm mt-3">
                        <div class="seccion-encabezado">
                            <div>
                                <h3 class="h6 fw-bold mb-1">Catálogo de Espacios Físicos y Cuentas</h3>
                                <p class="text-body-secondary small mb-0">Configure aulas, laboratorios, talleres y sus cuentas de acceso.</p>
                            </div>
                        </div>
                        <div id="aulaFormFeedback"></div>
                        <div class="tabla-contenedor table-responsive border rounded-3">
                            <table class="table table-hover align-middle mb-0 tabla-espacios">
                                <thead class="tabla-encabezado">
                                    <tr>
                                        <th>Nombre</th>
                                        <th>Tipo</th>
                                        <th>Cuenta de Acceso</th>
                                        <th>Estado</th>
                                        <th class="text-end">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tablaAulasBody">
                                    <tr><td colspan="5" class="text-center py-4 text-body-secondary">Cargando listado de espacios...</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Formulario de Alta y Edición de Espacios -->
                        <div class="mt-4 pt-3 border-top">
                            <h4 id="tituloFormAula" class="h6 fw-bold text-secondary mb-3">Dar de alta nuevo espacio físico</h4>
                            <form id="formAula">
                                <input type="hidden" id="aulaIdInput" value="0">
                                <div class="formulario-grilla">
                                    <div class="campo-grupo col-span-2">
                                        <label for="aulaNombre" class="form-label">Nombre del Espacio (y Usuario de Acceso)</label>
                                        <input type="text" class="form-control" id="aulaNombre" placeholder="ej. Aula 102, Laboratorio 2, Taller..." required>
                                    </div>
                                    <div class="campo-grupo">
                                        <label for="aulaTipo" class="form-label">Tipo de Espacio</label>
                                        <select id="aulaTipo" class="form-select" required>
                                            <option value="Aula">Aula</option>
                                            <option value="Laboratorio">Laboratorio</option>
                                            <option value="Taller">Taller</option>
                                        </select>
                                    </div>
                                    <div class="campo-grupo">
                                        <label for="aulaPassword" id="labelAulaPassword" class="form-label">Contraseña de Acceso</label>
                                        <input type="password" class="form-control" id="aulaPassword" placeholder="Dejar en blanco para mantener o default aula123">
                                    </div>
                                </div>
                                <div class="d-flex gap-2 mt-2">
                                    <button type="submit" class="btn btn-primary btn-primario">
                                        <i class="bi bi-check2-circle"></i>
                                        <span id="textoBtnGuardarAula">Guardar Espacio</span>
                                    </button>
                                    <button type="button" id="btnLimpiarFormAula" class="btn btn-outline-secondary btn-secundario">Cancelar / Limpiar</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Pestaña 1: Horarios de Cierre Automático (Ahorro Energético) -->
                <div id="tab-horarios" class="tab-contenido">
                    <div class="tarjeta-seccion card shadow-sm">
                        <div class="seccion-encabezado d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <h3 class="h6 fw-bold mb-1">Horarios de Cierre Automático (Ahorro Energético)</h3>
                                <p class="text-body-secondary small mb-0">Programá el apagado automático al finalizar cada turno para evitar consumos innecesarios.</p>
                            </div>
                            <span class="badge text-bg-secondary px-3 py-2" id="horaServidorBadge">
                                <i class="bi bi-clock me-1"></i>Hora servidor: --:--
                            </span>
                        </div>
                        <div id="horarioFormFeedback"></div>
                        <div class="tabla-contenedor table-responsive border rounded-3">
                            <table class="table table-hover align-middle mb-0 tabla-espacios">
                                <thead class="tabla-encabezado">
                                    <tr>
                                        <th>Regla</th>
                                        <th>Hora</th>
                                        <th>Días</th>
                                        <th>Acción</th>
                                        <th>Estado</th>
                                        <th class="text-end">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tablaHorariosBody">
                                    <tr><td colspan="6" class="text-center py-4 text-body-secondary">Cargando horarios automáticos...</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4 pt-3 border-top">
                            <h4 class="h6 fw-bold text-secondary mb-3">Programar nuevo horario de apagado</h4>
                            <form id="formHorario">
                                <div class="formulario-grilla">
                                    <div class="campo-grupo">
                                        <label for="horarioNombre" class="form-label">Nombre de la Regla</label>
                                        <input type="text" class="form-control" id="horarioNombre" placeholder="ej. Cierre Turno Tarde" required maxlength="100">
                                    </div>
                                    <div class="campo-grupo">
                                        <label for="horarioHora" class="form-label">Hora de Ejecución</label>
                                        <input type="time" class="form-control" id="horarioHora" value="18:30" required>
                                    </div>
                                    <div class="campo-grupo">
                                        <label for="horarioDias" class="form-label">Días de la Semana</label>
                                        <select id="horarioDias" class="form-select">
                                            <option value="1,2,3,4,5" selected>Lunes a Viernes</option>
                                            <option value="1,2,3,4,5,6">Lunes a Sábado</option>
                                            <option value="1,2,3,4,5,6,7">Todos los días</option>
                                        </select>
                                    </div>
                                    <div class="campo-grupo">
                                        <label for="horarioAccion" class="form-label">Qué Apagar</label>
                                        <select id="horarioAccion" class="form-select">
                                            <option value="todo" selected>Todo (Luces, Aire y Proyector)</option>
                                            <option value="luces">Solo Luces</option>
                                            <option value="aire">Solo Aires Acondicionados</option>
                                            <option value="proyector">Solo Proyectores</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="d-flex gap-2 mt-2">
                                    <button type="submit" class="btn btn-primary btn-primario">
                                        <i class="bi bi-clock-fill"></i>
                                        <span>Guardar Horario</span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Pestaña 2: Gestión de Administradores / Directivos -->
                <div id="tab-usuarios" class="tab-contenido">
                    <div class="tarjeta-seccion card shadow-sm">
                        <div class="seccion-encabezado">
                            <div>
                                <h3 class="h6 fw-bold mb-1">Administradores y Directivos</h3>
                                <p class="text-body-secondary small mb-0">Creá cuentas con acceso administrativo completo para directivos, regentes o preceptores.</p>
                            </div>
                        </div>

                        <div class="tabla-contenedor table-responsive border rounded-3">
                            <table class="table table-hover align-middle mb-0 tabla-espacios">
                                <thead class="tabla-encabezado">
                                    <tr>
                                        <th>Usuario</th>
                                        <th>Nombre Completo</th>
                                        <th>Estado</th>
                                        <th class="text-end">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tablaUsuariosBody">
                                    <tr><td colspan="4" class="text-center py-4 text-body-secondary">Cargando administradores...</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Formulario de Alta y Edición de Administradores -->
                        <div class="mt-4 pt-3 border-top">
                            <h4 id="tituloFormUsuario" class="h6 fw-bold text-secondary mb-3">Dar de alta nuevo administrador</h4>
                            <div id="usuarioFormFeedback"></div>
                            <form id="formUsuario">
                                <input type="hidden" id="usrId" value="0">
                                <input type="hidden" id="usrRol" value="admin">
                                <div class="formulario-grilla">
                                    <div class="campo-grupo">
                                        <label for="usrUsuario" class="form-label">Usuario (login)</label>
                                        <input type="text" class="form-control" id="usrUsuario" placeholder="ej. direccion, regente" required pattern="[a-zA-Z0-9_.\-]{3,50}">
                                    </div>
                                    <div class="campo-grupo col-span-2">
                                        <label for="usrNombre" class="form-label">Nombre Completo / Cargo</label>
                                        <input type="text" class="form-control" id="usrNombre" placeholder="ej. Dirección - Prof. Gómez" required>
                                    </div>
                                    <div class="campo-grupo">
                                        <label for="usrPassword" id="labelUsrPassword" class="form-label">Contraseña</label>
                                        <input type="password" class="form-control" id="usrPassword" placeholder="Mínimo 4 caracteres">
                                    </div>
                                </div>
                                <div class="d-flex gap-2 mt-2">
                                    <button type="submit" class="btn btn-primary btn-primario">
                                        <i class="bi bi-person-check"></i>
                                        <span id="textoBtnGuardarUsuario">Crear Administrador</span>
                                    </button>
                                    <button type="button" id="btnLimpiarFormUsuario" class="btn btn-outline-secondary btn-secundario">Cancelar / Limpiar</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Pestaña 3: Registro de Eventos con Filtros y Exportar CSV -->
                <div id="tab-log" class="tab-contenido">
                    <div class="tarjeta-seccion card shadow-sm">
                        <div class="seccion-encabezado d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <h3 class="h6 fw-bold mb-1">Registro de Eventos del Sistema</h3>
                                <p class="text-body-secondary small mb-0">Historial completo de encendidos, apagados, inicios de sesión y cierres automáticos.</p>
                            </div>
                            <button type="button" id="btnExportarLogCsv" class="btn btn-outline-success btn-sm px-3 fw-medium">
                                <i class="bi bi-file-earmark-spreadsheet"></i>
                                <span>Exportar Excel (CSV)</span>
                            </button>
                        </div>

                        <!-- Barra de Filtros del Registro de Eventos -->
                        <div class="filtros-barra d-flex flex-wrap align-items-center gap-2 mb-3">
                            <select id="filtroLogAula" class="form-select form-select-sm" style="width: auto; min-width: 170px;">
                                <option value="">Todos los espacios</option>
                            </select>
                            <select id="filtroLogEvento" class="form-select form-select-sm" style="width: auto; min-width: 170px;">
                                <option value="">Todos los eventos</option>
                                <option value="LUCES">Luces</option>
                                <option value="AIRE">Aire Acondicionado</option>
                                <option value="PROYECTOR">Proyector</option>
                                <option value="ESCENA">Escenas Rápidas</option>
                                <option value="APAGADO_GLOBAL">Apagado Global / Horarios</option>
                                <option value="SESION">Inicios / Cierres de Sesión</option>
                            </select>
                            <div class="input-group input-group-sm" style="max-width: 240px;">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text" id="filtroLogTexto" class="form-control" placeholder="Buscar en detalle...">
                            </div>
                        </div>

                        <div class="tabla-contenedor table-responsive border rounded-3">
                            <table class="table table-hover align-middle mb-0 tabla-espacios">
                                <thead class="tabla-encabezado">
                                    <tr>
                                        <th>Hora</th>
                                        <th>Espacio</th>
                                        <th>Origen</th>
                                        <th>Evento</th>
                                        <th>Detalle</th>
                                    </tr>
                                </thead>
                                <tbody id="tablaLogBody">
                                    <tr><td colspan="5" class="text-center py-4 text-body-secondary">Cargando eventos...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>
        </div>
        <?php endif; ?>
    </div>

    <!-- =======================================================================
         MODAL: CENTRO DE CONFIGURACIÓN AVANZADO (3 PESTAÑAS - AMBOS ROLES)
         ======================================================================= -->
    <div id="modalConfigApariencia" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="tituloModalApariencia">
        <div class="modal-panel card shadow">
            <div class="modal-header">
                <h3 id="tituloModalApariencia" class="h6 fw-bold mb-0">
                    <i class="bi bi-sliders text-primary"></i>
                    <span>Centro de Configuración</span>
                </h3>
                <button type="button" id="btnCerrarConfig" class="btn-close modal-cerrar" aria-label="Cerrar"></button>
            </div>

            <!-- Navegación de las 2 Pestañas del Modal -->
            <div class="modal-tabs-nav" role="tablist">
                <button type="button" class="modal-tab-btn activo" data-cfg-tab="cfg-tab-apariencia">
                    <i class="bi bi-palette me-1"></i>
                    <span>Apariencia y Accesibilidad</span>
                </button>
                <button type="button" class="modal-tab-btn" data-cfg-tab="cfg-tab-perfil">
                    <i class="bi bi-person-gear me-1"></i>
                    <span>Mi Perfil y Seguridad</span>
                </button>
            </div>

            <div class="modal-body-scroll">
                <!-- ===========================================================
                     PESTAÑA 1: APARIENCIA Y ACCESIBILIDAD
                     =========================================================== -->
                <div id="cfg-tab-apariencia" class="cfg-tab-pane activo">
                    <!-- 1. Tema de Fondo -->
                    <div class="pref-grupo">
                        <div class="pref-grupo-titulo">
                            <span>Tema de Fondo</span>
                        </div>
                        <div class="pref-opciones-grid cols-3">
                            <button type="button" class="pref-btn" data-pref-tema="claro">
                                <i class="bi bi-sun"></i>
                                <span>Claro</span>
                            </button>
                            <button type="button" class="pref-btn" data-pref-tema="oscuro">
                                <i class="bi bi-moon-stars"></i>
                                <span>Oscuro</span>
                            </button>
                            <button type="button" class="pref-btn" data-pref-tema="auto">
                                <i class="bi bi-circle-half"></i>
                                <span>Automático</span>
                            </button>
                        </div>
                    </div>

                    <!-- 2. Paleta de Colores (6 Predefinidos + Selector Personalizado) -->
                    <div class="pref-grupo">
                        <div class="pref-grupo-titulo">
                            <span>Color Primario</span>
                        </div>
                        <div class="pref-opciones-grid cols-3">
                            <button type="button" class="pref-btn" data-pref-acento="azul">
                                <span class="color-circulo" style="background:#0d6efd;"></span>
                                <span>Azul</span>
                            </button>
                            <button type="button" class="pref-btn" data-pref-acento="esmeralda">
                                <span class="color-circulo" style="background:#198754;"></span>
                                <span>Verde Success</span>
                            </button>
                            <button type="button" class="pref-btn" data-pref-acento="cian">
                                <span class="color-circulo" style="background:#0dcaf0;"></span>
                                <span>Cian Info</span>
                            </button>
                            <button type="button" class="pref-btn" data-pref-acento="violeta">
                                <span class="color-circulo" style="background:#6610f2;"></span>
                                <span>Índigo</span>
                            </button>
                            <button type="button" class="pref-btn" data-pref-acento="coral">
                                <span class="color-circulo" style="background:#dc3545;"></span>
                                <span>Rojo Danger</span>
                            </button>
                            <button type="button" class="pref-btn" data-pref-acento="dorado">
                                <span class="color-circulo" style="background:#6c757d;"></span>
                                <span>Gris Neutro</span>
                            </button>
                        </div>
                        <div class="color-custom-fila">
                            <span>Selector de Color Personalizado:</span>
                            <div class="d-flex align-items-center gap-2">
                                <input type="color" id="inputColorCustom" class="form-control form-control-color input-color-picker" value="#0d6efd" title="Elegir color personalizado">
                                <button type="button" id="btnAplicarColorCustom" class="btn btn-outline-secondary btn-sm btn-secundario btn-pequeno">Aplicar color</button>
                            </div>
                        </div>
                    </div>

                    <!-- 3. Tipografía del Panel -->
                    <div class="pref-grupo">
                        <div class="pref-grupo-titulo">
                            <span>Tipografía del Panel</span>
                        </div>
                        <div class="pref-opciones-grid cols-3">
                            <button type="button" class="pref-btn" data-pref-fuente="sans">
                                <span>Sans</span>
                            </button>
                            <button type="button" class="pref-btn" data-pref-fuente="serif">
                                <span>Clásica / Serif</span>
                            </button>
                            <button type="button" class="pref-btn" data-pref-fuente="mono">
                                <span>Monoespaciada</span>
                            </button>
                        </div>
                    </div>

                    <!-- 4. Unidad de Temperatura (°C / °F) -->
                    <div class="pref-grupo">
                        <div class="pref-grupo-titulo">
                            <span>Unidad de Temperatura</span>
                        </div>
                        <div class="pref-opciones-grid">
                            <button type="button" class="pref-btn" data-pref-unidad-temp="C">
                                <i class="bi bi-thermometer-half"></i>
                                <span>Grados Celsius (°C)</span>
                            </button>
                            <button type="button" class="pref-btn" data-pref-unidad-temp="F">
                                <i class="bi bi-thermometer-sun"></i>
                                <span>Grados Fahrenheit (°F)</span>
                            </button>
                        </div>
                    </div>

                    <!-- 5. Escala de Texto y Botones (Slider 85% a 130%) -->
                    <div class="pref-grupo">
                        <div class="pref-grupo-titulo">
                            <span>Escala de Texto y Botones</span>
                            <strong id="labelEscalaPorcentaje" class="text-primary">100%</strong>
                        </div>
                        <div class="termostato-slider-wrap">
                            <span>85%</span>
                            <input type="range" id="sliderEscalaTexto" class="form-range slider-rango" min="85" max="130" step="5" value="100">
                            <span>130%</span>
                        </div>
                    </div>

                    <!-- 6. Densidad de las Tarjetas -->
                    <div class="pref-grupo">
                        <div class="pref-grupo-titulo">
                            <span>Densidad de las Tarjetas</span>
                        </div>
                        <div class="pref-opciones-grid">
                            <button type="button" class="pref-btn" data-pref-densidad="comoda">
                                <span>Cómoda (Expandida)</span>
                            </button>
                            <button type="button" class="pref-btn" data-pref-densidad="compacta">
                                <span>Compacta</span>
                            </button>
                        </div>
                    </div>

                    <!-- 7. Accesibilidad -->
                    <div class="pref-grupo mb-0">
                        <div class="pref-grupo-titulo">
                            <span>Accesibilidad Visual</span>
                        </div>
                        <label class="switch-fila" for="chkAltoContraste">
                            <div>
                                <strong>Modo Alto Contraste</strong>
                                <small>Bordes y textos más definidos para ver mejor con el reflejo del proyector</small>
                            </div>
                            <input type="checkbox" id="chkAltoContraste" class="form-check-input switch-check">
                        </label>
                        <label class="switch-fila" for="chkReducirAnim">
                            <div>
                                <strong>Reducir Animaciones</strong>
                                <small>Desactiva efectos de transición y pulsos visuales</small>
                            </div>
                            <input type="checkbox" id="chkReducirAnim" class="form-check-input switch-check">
                        </label>
                    </div>
                </div>

                <!-- ===========================================================
                     PESTAÑA 2: MI PERFIL Y SEGURIDAD
                     =========================================================== -->
                <div id="cfg-tab-perfil" class="cfg-tab-pane">
                    <div class="pref-grupo">
                        <div class="pref-grupo-titulo">
                            <span>Datos de la Cuenta Actual</span>
                        </div>
                        <div class="switch-fila">
                            <div>
                                <strong>Usuario: <?php echo htmlspecialchars($usuario, ENT_QUOTES, 'UTF-8'); ?></strong>
                                <small>Rol asignado: <?php echo $esAdmin ? 'Administrador del Sistema' : 'Cuenta de Aula / Espacio'; ?></small>
                            </div>
                            <span class="rol-tag badge text-bg-primary"><?php echo $esAdmin ? 'Admin' : 'Espacio'; ?></span>
                        </div>
                    </div>

                    <!-- Formulario 1: Editar Nombre Completo -->
                    <div class="pref-grupo">
                        <div class="pref-grupo-titulo">
                            <span>Editar mi Nombre Visible</span>
                        </div>
                        <div id="perfilNombreFeedback"></div>
                        <form id="formPerfilNombre">
                            <div class="campo-grupo">
                                <label for="inputPerfilNombre" class="form-label">Nombre Completo (visible en la barra superior)</label>
                                <input type="text" class="form-control" id="inputPerfilNombre" value="<?php echo htmlspecialchars($nombreCompleto, ENT_QUOTES, 'UTF-8'); ?>" required maxlength="100">
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm btn-primario btn-pequeno">
                                <i class="bi bi-check2"></i>
                                <span>Guardar Nombre</span>
                            </button>
                        </form>
                    </div>

                    <!-- Formulario 2: Cambiar mi Contraseña -->
                    <div class="pref-grupo mb-0 border-top pt-3">
                        <div class="pref-grupo-titulo">
                            <span>Cambiar mi Contraseña</span>
                        </div>
                        <div id="perfilPasswordFeedback"></div>
                        <form id="formPerfilPassword">
                            <div class="campo-grupo">
                                <label for="inputPassActual" class="form-label">Contraseña Actual</label>
                                <input type="password" class="form-control" id="inputPassActual" required autocomplete="current-password" placeholder="Ingrese su contraseña actual">
                            </div>
                            <div class="pref-opciones-grid">
                                <div class="campo-grupo">
                                    <label for="inputPassNueva" class="form-label">Nueva Contraseña</label>
                                    <input type="password" class="form-control" id="inputPassNueva" required minlength="4" autocomplete="new-password" placeholder="Mínimo 4 caracteres">
                                </div>
                                <div class="campo-grupo">
                                    <label for="inputPassConfirmar" class="form-label">Confirmar Nueva Contraseña</label>
                                    <input type="password" class="form-control" id="inputPassConfirmar" required minlength="4" autocomplete="new-password" placeholder="Repita la contraseña">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm btn-primario btn-pequeno">
                                <i class="bi bi-key"></i>
                                <span>Actualizar Contraseña</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" id="btnResetApariencia" class="btn btn-outline-secondary btn-sm btn-secundario btn-pequeno">
                    <span>Restablecer valores por defecto</span>
                </button>
                <button type="button" id="btnCerrarConfigFooter" class="btn btn-primary btn-sm btn-primario btn-pequeno">
                    <span>Listo</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5.3 Bundle JS & Script Principal del Panel -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="assets/js/panel.js?v=20261126b"></script>
    <script src="assets/js/pwa.js?v=20261126b"></script>
</body>
</html>
