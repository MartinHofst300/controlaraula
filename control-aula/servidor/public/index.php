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
<html lang="es" data-theme="oscuro" data-accent="dorado" data-font="sans" data-density="comoda" data-contrast="normal" data-motion="normal">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1c1818">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="user-role" content="<?php echo htmlspecialchars($rol, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="user-id" content="<?php echo $usuarioIdActual; ?>">
    <?php if ($aulaFija): ?>
    <meta name="user-aula-id" content="<?php echo (int)$aulaFija['id']; ?>">
    <?php endif; ?>
    <title>Panel de Aula | Control de Aula</title>
    <script>
        window.PREFS_SERVIDOR_DB = <?php echo json_encode($prefsUsuarioDB, JSON_UNESCAPED_UNICODE); ?>;
        window.AULA_SESION_ID = <?php echo $aulaFija ? (int)$aulaFija['id'] : 1; ?>;
        window.USER_ROL = "<?php echo htmlspecialchars($rol, ENT_QUOTES, 'UTF-8'); ?>";
        (function() {
            try {
                var prefsLocal = JSON.parse(localStorage.getItem('control_aula_prefs') || '{}');
                var prefs = Object.assign({}, window.PREFS_SERVIDOR_DB || {}, prefsLocal);
                if (window.PREFS_SERVIDOR_DB) {
                    prefs = Object.assign({}, prefsLocal, window.PREFS_SERVIDOR_DB);
                    localStorage.setItem('control_aula_prefs', JSON.stringify(prefs));
                }
                var root = document.documentElement;
                var tema = prefs.tema || 'oscuro';
                if (tema === 'auto') {
                    tema = (window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches) ? 'claro' : 'oscuro';
                }
                root.setAttribute('data-theme', tema);
                if (prefs.acento) root.setAttribute('data-accent', prefs.acento);
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
    <link rel="stylesheet" href="assets/css/estilos.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <div class="panel-contenedor">
        <!-- Cabecera Principal -->
        <header class="cabecera-principal">
            <div class="marca-titulo">
                <div class="marca-icono">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="2" width="20" height="8" rx="2" ry="2"></rect>
                        <rect x="2" y="14" width="20" height="8" rx="2" ry="2"></rect>
                        <line x1="6" y1="6" x2="6.01" y2="6"></line>
                        <line x1="6" y1="18" x2="6.01" y2="18"></line>
                    </svg>
                </div>
                <div class="marca-info">
                    <h1>Control de Aula</h1>
                    <span>Gestión Inteligente de Climatización, Iluminación y Proyección</span>
                </div>
            </div>

            <div class="cabecera-acciones">
                <?php if ($esAdmin): ?>
                <!-- Selector Dinámico de Espacio (Solo visible para Administradores) -->
                <div class="selector-aula-contenedor">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--text-gold); flex-shrink:0;">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                        <polyline points="9 22 9 12 15 12 15 22"></polyline>
                    </svg>
                    <label for="selectAulaHeader">Espacio:</label>
                    <select id="selectAulaHeader" class="select-aula">
                        <option value="1">Cargando espacios...</option>
                    </select>
                </div>
                <?php else: ?>
                <!-- Indicador Fijo del Salón Físico (Cuenta de Espacio) -->
                <?php
                $tipoEsp = $aulaFija['tipo'] ?? 'Aula';
                $nomEsp = $aulaFija['nombre'] ?? 'Aula 101';
                $ubicEsp = $aulaFija['ubicacion'] ?? '';
                ?>
                <div class="espacio-fijo-badge" title="Espacio físico asignado a esta terminal">
                    <?php if ($tipoEsp === 'Laboratorio'): ?>
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--text-gold); flex-shrink:0;">
                        <path d="M10 2v7.527a2 2 0 0 1-.211.896L4.72 20.55a1 1 0 0 0 .9 1.45h12.76a1 1 0 0 0 .9-1.45l-5.069-10.127A2 2 0 0 1 14 9.527V2"></path>
                        <path d="M8.5 2h7"></path>
                        <path d="M7 16h10"></path>
                    </svg>
                    <?php elseif ($tipoEsp === 'Taller'): ?>
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--text-gold); flex-shrink:0;">
                        <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path>
                    </svg>
                    <?php else: ?>
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--text-gold); flex-shrink:0;">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                        <polyline points="9 22 9 12 15 12 15 22"></polyline>
                    </svg>
                    <?php endif; ?>
                    <span class="tipo-tag"><?php echo htmlspecialchars($tipoEsp, ENT_QUOTES, 'UTF-8'); ?></span>
                    <span><?php echo htmlspecialchars($nomEsp, ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php if (!empty($ubicEsp)): ?>
                    <span class="ubicacion-texto">(<?php echo htmlspecialchars($ubicEsp, ENT_QUOTES, 'UTF-8'); ?>)</span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <div class="usuario-badge" title="Usuario con sesión iniciada">
                    <span id="headerNombreUsuario"><?php echo htmlspecialchars($nombreCompleto, ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="rol-tag"><?php echo $esAdmin ? 'Admin' : ($rol === 'aula' ? 'Espacio' : 'Profesor'); ?></span>
                </div>

                <div id="badgeConexion" class="badge-conexion offline">
                    <span class="pulso-punto"></span>
                    <span id="textoConexion">AULA OFFLINE</span>
                </div>

                <?php if ($esAdmin): ?>
                <a href="simulador.php" class="btn btn-secundario btn-pequeno" title="Simular ESP32 sin hardware">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="5 3 19 12 5 21 5 3"></polygon>
                    </svg>
                    <span>Simulador</span>
                </a>
                <?php endif; ?>

                <button type="button" id="btnAbrirConfig" class="btn btn-secundario btn-pequeno" title="Centro de Configuración, Preferencias y Perfil">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="3"></circle>
                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                    </svg>
                    <span>Configuración</span>
                </button>

                <a href="logout.php" class="btn btn-secundario btn-pequeno" title="Cerrar sesión">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        <polyline points="16 17 21 12 16 7"></polyline>
                        <line x1="21" y1="12" x2="9" y2="12"></line>
                    </svg>
                    <span>Salir</span>
                </a>
            </div>
        </header>

        <!-- Barra Superior de Escenas Rápidas del Aula (Acciones Combinadas de 1 Clic) -->
        <section class="escenas-seccion" aria-label="Escenas rápidas del aula">
            <div class="escenas-header">
                <div class="escenas-titulo">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                    </svg>
                    <span>Escenas Rápidas de Aula (1 Clic)</span>
                </div>
                <span id="escenasFeedback" class="escenas-feedback"></span>
            </div>
            <div class="barra-escenas">
                <button type="button" class="btn-escena" data-escena="modo_proyeccion">
                    <div class="escena-icono">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="2" width="20" height="20" rx="2.18" ry="2.18"></rect>
                            <line x1="7" y1="2" x2="7" y2="22"></line>
                            <line x1="17" y1="2" x2="17" y2="22"></line>
                            <line x1="2" y1="12" x2="22" y2="12"></line>
                            <line x1="2" y1="7" x2="7" y2="7"></line>
                            <line x1="2" y1="17" x2="7" y2="17"></line>
                            <line x1="17" y1="17" x2="22" y2="17"></line>
                            <line x1="17" y1="7" x2="22" y2="7"></line>
                        </svg>
                    </div>
                    <div class="escena-info">
                        <strong>Modo Proyección</strong>
                        <span>Apaga las luces del aula y enciende el proyector</span>
                    </div>
                </button>

                <button type="button" class="btn-escena" data-escena="iniciar_clase">
                    <div class="escena-icono">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
                            <path d="M6 12v5c3 3 9 3 12 0v-5"></path>
                        </svg>
                    </div>
                    <div class="escena-info">
                        <strong>Iniciar Clase</strong>
                        <span id="descIniciarClase">Enciende luces y climatización en tu preset preferido</span>
                    </div>
                </button>

                <button type="button" class="btn-escena btn-escena-peligro" data-escena="cerrar_aula">
                    <div class="escena-icono">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
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
                 TARJETA DESTACADA: AIRE ACONDICIONADO (TERMOSTATO INTERACTIVO)
                 =============================================================== -->
            <article class="tarjeta-control tarjeta-aire-destacada">
                <div class="control-header">
                    <div class="control-icono-titulo">
                        <div class="icono-dispositivo" id="iconoAire">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M9.59 4.59A2 2 0 1 1 11 8H2"></path>
                                <path d="M12.59 19.41A2 2 0 1 0 14 16H2"></path>
                                <path d="M15.73 9.73A2.5 2.5 0 1 1 17.5 14H2"></path>
                            </svg>
                        </div>
                        <div class="control-textos">
                            <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                                <h2>Aire Acondicionado</h2>
                                <span class="hw-chip hw-chip-star" id="hwChipAire" title="Equipo compatible Star Series (Coolix OEM 24-bit)">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                                    </svg>
                                    <span id="hwChipAireTexto">Star Series · Coolix OEM</span>
                                </span>
                            </div>
                            <p>Control remoto inteligente de climatización</p>
                            <span id="colaAireInfo" style="display:none; font-size:11px; color:var(--text-gold); margin-top:2px;"></span>
                        </div>
                    </div>
                    <span id="pastillaAire" class="estado-pastilla off">APAGADO</span>
                </div>

                <!-- Display Digital Central del Termostato -->
                <div id="termostatoDisplay" class="termostato-display modo-frio aire-apagado">
                    <div class="termostato-fila-central">
                        <button type="button" id="btnTempMenos" class="btn-temp-ajuste" title="Disminuir temperatura (-1°C)" aria-label="Disminuir temperatura">&minus;</button>
                        <div class="termostato-lectura">
                            <div id="aireTempValor" class="termostato-temp-valor">24°C</div>
                            <div id="aireEstadoSub" class="termostato-estado-sub">EQUIPO APAGADO</div>
                        </div>
                        <button type="button" id="btnTempMas" class="btn-temp-ajuste" title="Aumentar temperatura (+1°C)" aria-label="Aumentar temperatura">&#43;</button>
                    </div>

                    <div class="termostato-slider-wrap">
                        <span>16°C</span>
                        <input type="range" id="sliderAireTemp" class="slider-rango" min="16" max="30" step="1" value="24" aria-label="Selector deslizable de temperatura">
                        <span>30°C</span>
                    </div>

                    <div class="termostato-badges-activos">
                        <span id="chipAireModo" class="termostato-chip">Frío (Cool)</span>
                        <span id="chipAireFan" class="termostato-chip">Fan: Auto</span>
                        <span id="chipAireSwing" class="termostato-chip">Swing: Activo</span>
                    </div>
                </div>

                <!-- Botones Principales de Encendido / Apagado -->
                <div class="control-botones">
                    <button type="button" id="btnAireOn" class="btn btn-grande btn-primario">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 2v10"></path>
                            <path d="M18.4 6.6a9 9 0 1 1-12.77.01"></path>
                        </svg>
                        <span>Encender Aire</span>
                    </button>
                    <button type="button" id="btnAireOff" class="btn btn-grande btn-peligro">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                        </svg>
                        <span>Apagar Aire</span>
                    </button>
                </div>

                <!-- Selector de Modos de Operación -->
                <div class="subbloque-control">
                    <div class="subbloque-label">
                        <span>Modo de Operación</span>
                        <span id="labelModoActivo">Frío (Cool)</span>
                    </div>
                    <div class="grilla-botones-opciones">
                        <button type="button" class="btn-opcion-control activo" data-aire-modo="frio" title="Modo Frío (Cool)">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="12" y1="2" x2="12" y2="22"></line>
                                <line x1="2" y1="12" x2="22" y2="12"></line>
                                <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>
                                <line x1="19.07" y1="4.93" x2="4.93" y2="19.07"></line>
                            </svg>
                            <span>Frío</span>
                        </button>
                        <button type="button" class="btn-opcion-control" data-aire-modo="calor" title="Modo Calor (Heat)">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="5"></circle>
                                <line x1="12" y1="1" x2="12" y2="3"></line>
                                <line x1="12" y1="21" x2="12" y2="23"></line>
                                <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                                <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                                <line x1="1" y1="12" x2="3" y2="12"></line>
                                <line x1="21" y1="12" x2="23" y2="12"></line>
                                <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                                <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
                            </svg>
                            <span>Calor</span>
                        </button>
                        <button type="button" class="btn-opcion-control" data-aire-modo="ventilacion" title="Modo Ventilación (Fan)">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M9.59 4.59A2 2 0 1 1 11 8H2"></path>
                                <path d="M12.59 19.41A2 2 0 1 0 14 16H2"></path>
                                <path d="M15.73 9.73A2.5 2.5 0 1 1 17.5 14H2"></path>
                            </svg>
                            <span>Ventil.</span>
                        </button>
                        <button type="button" class="btn-opcion-control" data-aire-modo="seco" title="Modo Deshumidificador / Seco (Dry)">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"></path>
                            </svg>
                            <span>Seco</span>
                        </button>
                        <button type="button" class="btn-opcion-control" data-aire-modo="auto" title="Modo Automático (Auto)">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="23 4 23 10 17 10"></polyline>
                                <polyline points="1 20 1 14 7 14"></polyline>
                                <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                            </svg>
                            <span>Auto</span>
                        </button>
                    </div>
                </div>

                <!-- Velocidad de Ventilador (Fan Speed) -->
                <div class="subbloque-control">
                    <div class="subbloque-label">
                        <span>Velocidad de Ventilador (Fan)</span>
                        <span id="labelFanActivo">Automático</span>
                    </div>
                    <div class="grilla-botones-opciones cols-4">
                        <button type="button" class="btn-opcion-control activo" data-aire-fan="auto">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="23 4 23 10 17 10"></polyline>
                                <polyline points="1 20 1 14 7 14"></polyline>
                                <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                            </svg>
                            <span>Auto</span>
                        </button>
                        <button type="button" class="btn-opcion-control" data-aire-fan="bajo">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="2" y1="12" x2="8" y2="12"></line>
                            </svg>
                            <span>Bajo</span>
                        </button>
                        <button type="button" class="btn-opcion-control" data-aire-fan="medio">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="2" y1="12" x2="14" y2="12"></line>
                            </svg>
                            <span>Medio</span>
                        </button>
                        <button type="button" class="btn-opcion-control" data-aire-fan="alto">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="2" y1="12" x2="22" y2="12"></line>
                                <polyline points="16 6 22 12 16 18"></polyline>
                            </svg>
                            <span>Alto / Turbo</span>
                        </button>
                    </div>
                </div>

                <!-- Deflector (Swing) y Presets Rápidos -->
                <div class="subbloque-control">
                    <div class="subbloque-label">
                        <span>Deflector (Swing) y Presets Rápidos</span>
                    </div>
                    <div class="grilla-botones-opciones cols-4">
                        <button type="button" id="btnAireSwing" class="btn-opcion-control activo" title="Activar o desactivar oscilación de aletas">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="7 15 12 20 17 15"></polyline>
                                <polyline points="7 9 12 4 17 9"></polyline>
                                <line x1="12" y1="4" x2="12" y2="20"></line>
                            </svg>
                            <span>Swing</span>
                        </button>
                        <button type="button" id="btnPresetEco" class="btn-opcion-control" title="24°C Frío - Bajo consumo">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                            </svg>
                            <span>24°C Eco</span>
                        </button>
                        <button type="button" id="btnPresetMaxFrio" class="btn-opcion-control" title="18°C Frío - Potencia máxima">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="12" y1="2" x2="12" y2="22"></line>
                                <line x1="2" y1="12" x2="22" y2="12"></line>
                                <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>
                                <line x1="19.07" y1="4.93" x2="4.93" y2="19.07"></line>
                            </svg>
                            <span>18°C Max</span>
                        </button>
                        <button type="button" id="btnPresetCalor" class="btn-opcion-control" title="26°C Calor confortable">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="5"></circle>
                                <line x1="12" y1="1" x2="12" y2="3"></line>
                                <line x1="12" y1="21" x2="12" y2="23"></line>
                                <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                                <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                                <line x1="1" y1="12" x2="3" y2="12"></line>
                                <line x1="21" y1="12" x2="23" y2="12"></line>
                                <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                                <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
                            </svg>
                            <span>26°C Calor</span>
                        </button>
                    </div>
                </div>
            </article>

            <!-- ===============================================================
                 TARJETA 2: ILUMINACIÓN DEL AULA
                 =============================================================== -->
            <article class="tarjeta-control">
                <div class="control-header">
                    <div class="control-icono-titulo">
                        <div class="icono-dispositivo" id="iconoLuces">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M9 18h6"></path>
                                <path d="M10 22h4"></path>
                                <path d="M15.09 14c.18-.98.65-1.74 1.41-2.5A4.65 4.65 0 0 0 18 8 6 6 0 0 0 6 8c0 1 .23 2.23 1.5 3.5A4.61 4.61 0 0 1 8.91 14"></path>
                            </svg>
                        </div>
                        <div class="control-textos">
                            <h2>Iluminación</h2>
                            <p>Circuito general de luminarias del aula</p>
                        </div>
                    </div>
                    <span id="pastillaLuces" class="estado-pastilla off">APAGADAS</span>
                </div>

                <div class="control-botones">
                    <button type="button" id="btnLucesOn" class="btn btn-grande btn-exito">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 2v10"></path>
                            <path d="M18.4 6.6a9 9 0 1 1-12.77.01"></path>
                        </svg>
                        <span>Encender Luces</span>
                    </button>
                    <button type="button" id="btnLucesOff" class="btn btn-grande btn-peligro">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                        <span>Apagar Luces</span>
                    </button>
                </div>
            </article>

            <!-- ===============================================================
                 TARJETA 3: PROYECTOR MULTIMEDIA AVANZADO
                 =============================================================== -->
            <article class="tarjeta-control">
                <div class="control-header">
                    <div class="control-icono-titulo">
                        <div class="icono-dispositivo" id="iconoProyector">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="2" y="7" width="20" height="10" rx="2" ry="2"></rect>
                                <line x1="7" y1="17" x2="5" y2="21"></line>
                                <line x1="17" y1="17" x2="19" y2="21"></line>
                                <circle cx="16" cy="12" r="2"></circle>
                            </svg>
                        </div>
                        <div class="control-textos">
                            <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                                <h2>Proyector Multimedia</h2>
                                <span class="hw-chip hw-chip-epson" id="hwChipProyector" title="Proyector Epson con protocolo NEC 38kHz y doble pulso de apagado">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                                        <line x1="8" y1="21" x2="16" y2="21"></line>
                                        <line x1="12" y1="17" x2="12" y2="21"></line>
                                    </svg>
                                    <span id="hwChipProyectorTexto">Epson · Protocolo NEC</span>
                                </span>
                            </div>
                            <p>Alimentación, fuente de video y funciones de clase</p>
                            <span id="colaProyectorInfo" style="display:none; font-size:11px; color:var(--text-gold); margin-top:2px;"></span>
                        </div>
                    </div>
                    <span id="pastillaProyector" class="estado-pastilla off">APAGADO</span>
                </div>

                <!-- Encendido y Apagado -->
                <div class="control-botones">
                    <button type="button" id="btnProyectorOn" class="btn btn-grande btn-primario">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 2v10"></path>
                            <path d="M18.4 6.6a9 9 0 1 1-12.77.01"></path>
                        </svg>
                        <span>Encender</span>
                    </button>
                    <button type="button" id="btnProyectorOff" class="btn btn-grande btn-peligro">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                        </svg>
                        <span>Apagar</span>
                    </button>
                </div>

                <!-- Selector de Fuente de Entrada -->
                <div class="subbloque-control">
                    <div class="subbloque-label">
                        <span>Fuente de Entrada (Source)</span>
                        <span id="labelProyFuente">HDMI 1</span>
                    </div>
                    <div class="grilla-botones-opciones cols-3">
                        <button type="button" class="btn-opcion-control horizontal activo" data-proy-fuente="hdmi1">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="2" y="7" width="20" height="10" rx="2" ry="2"></rect>
                                <line x1="6" y1="17" x2="6" y2="21"></line>
                                <line x1="18" y1="17" x2="18" y2="21"></line>
                            </svg>
                            <span>HDMI 1</span>
                        </button>
                        <button type="button" class="btn-opcion-control horizontal" data-proy-fuente="hdmi2">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="2" y="7" width="20" height="10" rx="2" ry="2"></rect>
                                <line x1="6" y1="17" x2="6" y2="21"></line>
                                <line x1="18" y1="17" x2="18" y2="21"></line>
                            </svg>
                            <span>HDMI 2</span>
                        </button>
                        <button type="button" class="btn-opcion-control horizontal" data-proy-fuente="vga">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                                <line x1="8" y1="21" x2="16" y2="21"></line>
                                <line x1="12" y1="17" x2="12" y2="21"></line>
                            </svg>
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
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="6" y="4" width="4" height="16"></rect>
                                <rect x="14" y="4" width="4" height="16"></rect>
                            </svg>
                            <span>Congelar Imagen</span>
                        </button>
                        <button type="button" id="btnProyMute" class="btn-opcion-control horizontal" title="Poner pantalla en negro (AV Mute) sin apagar la lámpara">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                            </svg>
                            <span>Pantalla en Negro</span>
                        </button>
                    </div>
                </div>

                <!-- Control de Volumen Ergonómico del Proyector -->
                <div class="subbloque-control">
                    <div class="subbloque-label">
                        <span>Audio del Proyector</span>
                        <span id="proyVolTexto" class="volumen-indicador">50%</span>
                    </div>

                    <!-- Fila integrada de control de volumen -->
                    <div class="proyector-volumen-fila">
                        <button type="button" id="btnProyVolMute" class="btn-vol-mute" title="Silenciar audio (Mute)" aria-label="Silenciar audio">
                            <svg class="icono-parlante" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon>
                                <path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"></path>
                            </svg>
                            <svg class="icono-parlante-mute" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;">
                                <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon>
                                <line x1="23" y1="9" x2="17" y2="15"></line>
                                <line x1="17" y1="9" x2="23" y2="15"></line>
                            </svg>
                        </button>

                        <button type="button" id="btnProyVolMenos" class="btn-vol-step" title="Bajar volumen (-5%)" aria-label="Bajar volumen">&minus;</button>

                        <div class="slider-volumen-contenedor">
                            <input type="range" id="sliderProyVol" class="slider-rango slider-volumen" min="0" max="100" step="5" value="50" aria-label="Barra de volumen del proyector">
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
            <div class="admin-toggle-header" id="btnToggleAdminPanel" role="button" tabindex="0" aria-expanded="true">
                <div class="admin-toggle-titulo">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                    </svg>
                    <span>Panel de Administración, Usuarios y Configuración Técnica</span>
                </div>
                <span class="btn btn-secundario btn-pequeno" id="textoToggleAdmin">Ocultar herramientas</span>
            </div>

            <section id="contenedorAdminTecnico" class="seccion-tabs">
                <!-- Barra de Telemetría ESP32 -->
                <div class="barra-telemetria" aria-label="Telemetría del dispositivo">
                    <div class="telemetria-item">
                        <span class="etiqueta">IP Local ESP32</span>
                        <span class="valor" id="telIp">--</span>
                    </div>
                    <div class="telemetria-item">
                        <span class="etiqueta">Señal WiFi</span>
                        <span class="valor" id="telRssi">--</span>
                    </div>
                    <div class="telemetria-item">
                        <span class="etiqueta">Tiempo Activo</span>
                        <span class="valor" id="telUptime">--</span>
                    </div>
                    <div class="telemetria-item">
                        <span class="etiqueta">Firmware</span>
                        <span class="valor" id="telFw">--</span>
                    </div>
                    <div class="telemetria-item">
                        <span class="etiqueta">Último Contacto</span>
                        <span class="valor" id="telContacto">--</span>
                    </div>
                </div>

                <nav class="tabs-navegacion" aria-label="Pestañas de administración">
                    <button type="button" class="tab-btn activo" data-target="tab-aulas">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                            <polyline points="9 22 9 12 15 12 15 22"></polyline>
                        </svg>
                        <span>Gestión de Aulas</span>
                    </button>
                    <button type="button" class="tab-btn" data-target="tab-usuarios">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                        </svg>
                        <span>Gestionar Usuarios</span>
                    </button>
                    <button type="button" class="tab-btn" data-target="tab-codigos">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
                            <polyline points="2 17 12 22 22 17"></polyline>
                            <polyline points="2 12 12 17 22 12"></polyline>
                        </svg>
                        <span>Códigos IR</span>
                    </button>
                    <button type="button" class="tab-btn" data-target="tab-capturas">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                            <circle cx="12" cy="13" r="4"></circle>
                        </svg>
                        <span>Capturas Receptor (KY-022)</span>
                    </button>
                    <button type="button" class="tab-btn" data-target="tab-log">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="4 17 10 11 4 5"></polyline>
                            <line x1="12" y1="19" x2="20" y2="19"></line>
                        </svg>
                        <span>Registro de Eventos</span>
                    </button>
                </nav>

                <!-- Pestaña 0: Tablero y Gestión de Aulas (Multi-Aula) -->
                <div id="tab-aulas" class="tab-contenido activo">
                    <!-- Tablero Multi-Aula en Vivo -->
                    <div class="tarjeta-seccion">
                        <div class="seccion-encabezado" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                            <div>
                                <h3>Tablero Central Multi-Aula en Tiempo Real</h3>
                                <p style="font-size:12px; color:var(--text-secondary); margin-top:2px;">Monitoreo y control simultáneo de todas las aulas de la institución escolar.</p>
                            </div>
                            <button type="button" id="btnApagarTodasAulas" class="btn btn-peligro btn-pequeno" title="Apagado general de emergencia en todas las aulas">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polygon points="7.86 2 16.14 2 22 7.86 22 16.14 16.14 22 7.86 22 2 16.14 2 7.86 7.86 2"></polygon>
                                    <line x1="12" y1="8" x2="12" y2="12"></line>
                                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                                </svg>
                                <span>Apagar Todas las Aulas</span>
                            </button>
                        </div>
                        <div id="tableroAulasFeedback" style="margin-bottom:10px;"></div>
                        <div id="tableroAulasGrid" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:16px; margin-top:14px;">
                            <div style="grid-column:1/-1; text-align:center; padding:20px; color:var(--text-secondary);">Cargando estado de las aulas...</div>
                        </div>
                    </div>

                    <!-- Tabla de Catálogo y Administración de Espacios Físicos -->
                    <div class="tarjeta-seccion" style="margin-top:20px;">
                        <div class="seccion-encabezado">
                            <div>
                                <h3>Catálogo de Espacios Físicos y Cuentas</h3>
                                <p style="font-size:12px; color:var(--text-secondary); margin-top:2px;">Configure aulas, laboratorios, talleres, cuentas de acceso exclusivas y tokens de comunicación con el hardware ESP32.</p>
                            </div>
                        </div>
                        <div id="aulaFormFeedback"></div>
                        <div class="tabla-contenedor">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Espacio / Nombre</th>
                                        <th>Tipo</th>
                                        <th>Ubicación</th>
                                        <th>Cuenta Acceso</th>
                                        <th>API Key ESP32</th>
                                        <th>Estado</th>
                                        <th>Códigos IR</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tablaAulasBody">
                                    <tr><td colspan="9" style="text-align:center;">Cargando listado de espacios...</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Formulario de Alta y Edición de Espacios -->
                        <div style="margin-top:28px; border-top:1px solid var(--border-subtle); padding-top:20px;">
                            <h4 id="tituloFormAula" style="font-size:14px; margin-bottom:12px; color:var(--text-secondary);">Dar de alta nuevo espacio físico</h4>
                            <form id="formAula">
                                <input type="hidden" id="aulaIdInput" value="0">
                                <div class="formulario-grilla">
                                    <div class="campo-grupo col-span-2">
                                        <label for="aulaNombre">Nombre del Espacio</label>
                                        <input type="text" id="aulaNombre" placeholder="ej. Aula 102, Laboratorio 2, Taller..." required>
                                    </div>
                                    <div class="campo-grupo">
                                        <label for="aulaTipo">Tipo de Espacio</label>
                                        <select id="aulaTipo" required>
                                            <option value="Aula">Aula Estándar</option>
                                            <option value="Laboratorio">Laboratorio</option>
                                            <option value="Taller">Taller</option>
                                            <option value="Otro">Auditorio / Otro</option>
                                        </select>
                                    </div>
                                    <div class="campo-grupo">
                                        <label for="aulaUbicacion">Ubicación / Sector</label>
                                        <input type="text" id="aulaUbicacion" placeholder="ej. Planta Baja, 1er Piso">
                                    </div>
                                    <div class="campo-grupo col-span-2">
                                        <label for="aulaUsuario">Usuario de Acceso para este Salón</label>
                                        <input type="text" id="aulaUsuario" placeholder="ej. aula102, lab2" pattern="[a-zA-Z0-9_.\-]{3,50}">
                                    </div>
                                    <div class="campo-grupo col-span-2">
                                        <label for="aulaPassword" id="labelAulaPassword">Contraseña de Acceso</label>
                                        <input type="password" id="aulaPassword" placeholder="Dejar en blanco para mantener o default aula123">
                                    </div>
                                    <div class="campo-grupo">
                                        <label for="aulaMarcaAire">Marca / Perfil Aire</label>
                                        <select id="aulaMarcaAire">
                                            <option value="Star (OEM)" selected>Star (OEM Universal)</option>
                                            <option value="Star-Trak">Star-Trak</option>
                                            <option value="StarVision">StarVision</option>
                                            <option value="CoolStar">CoolStar</option>
                                            <option value="Universal">Universal / Otra</option>
                                        </select>
                                    </div>
                                    <div class="campo-grupo">
                                        <label for="aulaProtocoloAire">Protocolo Aire</label>
                                        <select id="aulaProtocoloAire">
                                            <option value="COOLIX" selected>COOLIX (24 bits)</option>
                                            <option value="NEC">NEC (32 bits)</option>
                                            <option value="GREE">GREE</option>
                                            <option value="TCL112AC">TCL112AC</option>
                                            <option value="RAW">RAW Personalizado</option>
                                        </select>
                                    </div>
                                    <div class="campo-grupo">
                                        <label for="aulaMarcaProyector">Marca Proyector</label>
                                        <select id="aulaMarcaProyector">
                                            <option value="Epson" selected>Epson</option>
                                            <option value="Universal">Universal / Otro</option>
                                        </select>
                                    </div>
                                    <div class="campo-grupo">
                                        <label for="aulaProtocoloProyector">Protocolo Proyector</label>
                                        <select id="aulaProtocoloProyector">
                                            <option value="EPSON_NEC" selected>EPSON_NEC (Doble pulso)</option>
                                            <option value="NEC">NEC Estándar</option>
                                            <option value="RAW">RAW Personalizado</option>
                                        </select>
                                    </div>
                                    <div class="campo-grupo col-span-4" id="contenedorClonarCodigos">
                                        <label for="aulaClonarCodigos">Clonar Códigos Infrarrojos (opcional al dar de alta)</label>
                                        <select id="aulaClonarCodigos">
                                            <option value="0" selected>Clonar desde Plantilla Global</option>
                                            <option value="-1">No clonar (comenzar en blanco)</option>
                                        </select>
                                    </div>
                                </div>
                                <div style="display:flex; gap:12px; margin-top:12px;">
                                    <button type="submit" class="btn btn-primario">
                                        <span id="textoBtnGuardarAula">Guardar Espacio</span>
                                    </button>
                                    <button type="button" id="btnLimpiarFormAula" class="btn btn-secundario">Cancelar / Limpiar</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Pestaña 1: Gestión de Usuarios (Profesores y Administradores) -->
                <div id="tab-usuarios" class="tab-contenido">
                    <div class="tarjeta-seccion">
                        <div class="seccion-encabezado">
                            <div>
                                <h3>Gestión de Usuarios del Sistema</h3>
                                <p style="font-size:12px; color:var(--text-secondary); margin-top:2px;">Administre las cuentas de profesores y administradores que pueden operar el aula.</p>
                            </div>
                        </div>

                        <div class="tabla-contenedor">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Usuario</th>
                                        <th>Nombre Completo</th>
                                        <th>Rol</th>
                                        <th>Estado</th>
                                        <th>Aulas Asignadas</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tablaUsuariosBody">
                                    <tr><td colspan="6" style="text-align:center;">Cargando usuarios...</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Formulario de Alta y Edición de Usuarios -->
                        <div style="margin-top:28px; border-top:1px solid var(--border-subtle); padding-top:20px;">
                            <h4 id="tituloFormUsuario" style="font-size:14px; margin-bottom:12px; color:var(--text-secondary);">Dar de alta nuevo usuario (Profesor o Administrador)</h4>
                            <div id="usuarioFormFeedback"></div>
                            <form id="formUsuario">
                                <input type="hidden" id="usrId" value="0">
                                <div class="formulario-grilla">
                                    <div class="campo-grupo">
                                        <label for="usrUsuario">Nombre de Usuario (login)</label>
                                        <input type="text" id="usrUsuario" placeholder="ej. prof_garcia" required pattern="[a-zA-Z0-9_.\-]{3,50}">
                                    </div>
                                    <div class="campo-grupo">
                                        <label for="usrNombre">Nombre y Apellido</label>
                                        <input type="text" id="usrNombre" placeholder="ej. Prof. Laura García" required>
                                    </div>
                                    <div class="campo-grupo">
                                        <label for="usrRol">Rol de Acceso</label>
                                        <select id="usrRol" required>
                                            <option value="profesor">Profesor (Solo controles del aula)</option>
                                            <option value="admin">Administrador (Acceso total)</option>
                                        </select>
                                    </div>
                                    <div class="campo-grupo">
                                        <label for="usrPassword" id="labelUsrPassword">Contraseña</label>
                                        <input type="password" id="usrPassword" placeholder="Mínimo 4 caracteres">
                                    </div>
                                    <div class="campo-grupo col-span-4" id="contenedorGrupoAulas" style="background:var(--bg-input, rgba(0,0,0,0.3)); padding:10px 14px; border-radius:var(--radius-sm, 8px); border:1px solid var(--border-subtle, rgba(255,255,255,0.1));">
                                        <label style="font-weight:600; margin-bottom:8px; display:block; color:var(--text-gold, #cdbf6a);">Aulas Permitidas para este Docente</label>
                                        <div id="checkAulasUsuarioContainer" style="display:flex; flex-wrap:wrap; gap:14px; font-size:13px;">
                                            <!-- Checkboxes de aulas generados dinámicamente -->
                                        </div>
                                    </div>
                                </div>
                                <div style="display:flex; gap:12px; margin-top:8px;">
                                    <button type="submit" class="btn btn-primario">
                                        <span id="textoBtnGuardarUsuario">Guardar Usuario</span>
                                    </button>
                                    <button type="button" id="btnLimpiarFormUsuario" class="btn btn-secundario">Cancelar / Limpiar</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Pestaña 2: Catálogo de Códigos IR -->
                <div id="tab-codigos" class="tab-contenido">
                    <div class="tarjeta-seccion">
                        <div class="seccion-encabezado" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                            <h3>Catálogo de Códigos Infrarrojos</h3>
                            <div style="display:flex; align-items:center; gap:8px;">
                                <label for="filtroCodigosAula" style="font-size:12px; color:var(--text-secondary); margin:0;">Filtrar:</label>
                                <select id="filtroCodigosAula" style="background:var(--bg-input, #222); color:var(--text-primary, #fff); border:1px solid var(--border-subtle, #444); border-radius:6px; padding:4px 8px; font-size:12px;">
                                    <option value="">Todas las Aulas y Globales</option>
                                    <option value="0">Solo Plantilla Global</option>
                                </select>
                            </div>
                        </div>

                        <div class="tabla-contenedor">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Ámbito / Aula</th>
                                        <th>Dispositivo</th>
                                        <th>Acción</th>
                                        <th>Protocolo</th>
                                        <th>Datos de Transmisión</th>
                                        <th>Parámetros</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tablaCodigosBody">
                                    <tr><td colspan="7" style="text-align:center;">Cargando catálogo...</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Formulario de Alta y Edición de Códigos -->
                        <div style="margin-top:28px; border-top:1px solid var(--border-subtle); padding-top:20px;">
                            <h4 style="font-size:14px; margin-bottom:12px; color:var(--text-secondary);">Dar de alta o editar comando IR</h4>
                            <div id="formFeedback"></div>
                            <form id="formCodigo">
                                <div class="formulario-grilla">
                                    <div class="campo-grupo">
                                        <label for="codAulaId">Ámbito / Aula</label>
                                        <select id="codAulaId">
                                            <option value="0">Plantilla Global (Todas las aulas)</option>
                                        </select>
                                    </div>
                                    <div class="campo-grupo">
                                        <label for="codDispositivo">Dispositivo</label>
                                        <select id="codDispositivo" required>
                                            <option value="aire">Aire acondicionado</option>
                                            <option value="proyector">Proyector</option>
                                        </select>
                                    </div>
                                    <div class="campo-grupo">
                                        <label for="codAccion">Acción (identificador)</label>
                                        <input type="text" id="codAccion" placeholder="prender, apagar, frio_24..." required pattern="[a-zA-Z0-9_-]+">
                                    </div>
                                    <div class="campo-grupo">
                                        <label for="codProtocolo">Protocolo</label>
                                        <input type="text" id="codProtocolo" placeholder="NEC, SONY, RC5, RAW..." required>
                                    </div>
                                    <div class="campo-grupo">
                                        <label for="codCodigo">Código Hexadecimal</label>
                                        <input type="text" id="codCodigo" placeholder="0xFF629D">
                                    </div>
                                    <div class="campo-grupo">
                                        <label for="codBits">Bits</label>
                                        <input type="number" id="codBits" value="32" min="1" max="256">
                                    </div>
                                    <div class="campo-grupo">
                                        <label for="codRepeticiones">Repeticiones</label>
                                        <input type="number" id="codRepeticiones" value="1" min="1" max="10">
                                    </div>
                                    <div class="campo-grupo">
                                        <label for="codPausaMs">Pausa entre envíos (ms)</label>
                                        <input type="number" id="codPausaMs" value="100" min="0" max="5000">
                                    </div>
                                    <div class="campo-grupo">
                                        <label for="codFrecuenciaKhz">Frecuencia (kHz)</label>
                                        <input type="number" id="codFrecuenciaKhz" value="38" min="10" max="60">
                                    </div>
                                    <div class="campo-grupo col-span-4">
                                        <label for="codEstadoHex">Trama de Estado HEX (opcional para A/C de paquete largo)</label>
                                        <input type="text" id="codEstadoHex" placeholder="Ej. 0x23CB26010020... o bytes continuos">
                                    </div>
                                    <div class="campo-grupo col-span-4">
                                        <label for="codRaw">Datos RAW en microsegundos (opcional)</label>
                                        <textarea id="codRaw" rows="2" placeholder="Ej. 8950, 4420, 580, 520, 580, 1620..."></textarea>
                                    </div>
                                    <div class="campo-grupo col-span-4">
                                        <label for="codDescripcion">Descripción (opcional)</label>
                                        <input type="text" id="codDescripcion" placeholder="Ej. Encender proyector Epson HDMI 1">
                                    </div>
                                </div>
                                <div style="display:flex; gap:12px; margin-top:8px;">
                                    <button type="submit" class="btn btn-primario">
                                        <span>Guardar Código IR</span>
                                    </button>
                                    <button type="button" id="btnLimpiarFormCodigo" class="btn btn-secundario">Limpiar Formulario</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Pestaña 3: Capturas del Receptor KY-022 -->
                <div id="tab-capturas" class="tab-contenido">
                    <div class="tarjeta-seccion">
                        <div class="seccion-encabezado">
                            <div>
                                <h3>Lecturas Capturadas por KY-022</h3>
                                <p style="font-size:12px; color:var(--text-secondary); margin-top:2px;">Apunta el control remoto del aula al receptor para clonar códigos simples, tramas de estado o secuencias RAW.</p>
                            </div>
                        </div>
                        <div id="capturaAsignarFeedback"></div>
                        <div class="tabla-contenedor">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Hora</th>
                                        <th>Aula</th>
                                        <th>Protocolo</th>
                                        <th>Datos Capturados</th>
                                        <th>Bits</th>
                                        <th>Acción</th>
                                    </tr>
                                </thead>
                                <tbody id="tablaCapturasBody">
                                    <tr><td colspan="6" style="text-align:center;">Esperando capturas del ESP32...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Pestaña 4: Registro de Eventos -->
                <div id="tab-log" class="tab-contenido">
                    <div class="tarjeta-seccion">
                        <div class="seccion-encabezado">
                            <h3>Auditoría de Eventos del Sistema</h3>
                        </div>
                        <div class="tabla-contenedor">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Hora</th>
                                        <th>Aula</th>
                                        <th>Origen</th>
                                        <th>Evento</th>
                                        <th>Detalle</th>
                                    </tr>
                                </thead>
                                <tbody id="tablaLogBody">
                                    <tr><td colspan="5" style="text-align:center;">Cargando eventos...</td></tr>
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
        <div class="modal-panel">
            <div class="modal-header">
                <h3 id="tituloModalApariencia">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="3"></circle>
                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                    </svg>
                    <span>Centro de Configuración</span>
                </h3>
                <button type="button" id="btnCerrarConfig" class="modal-cerrar" aria-label="Cerrar">&times;</button>
            </div>

            <!-- Navegación de las 3 Pestañas del Modal -->
            <div class="modal-tabs-nav" role="tablist">
                <button type="button" class="modal-tab-btn activo" data-cfg-tab="cfg-tab-apariencia">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <path d="M12 2a7 7 0 0 0 0 14 2 2 0 0 1 0 4 10 10 0 0 0 0-18z"></path>
                    </svg>
                    <span>Apariencia y Accesibilidad</span>
                </button>
                <button type="button" class="modal-tab-btn" data-cfg-tab="cfg-tab-aula">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                        <polyline points="9 22 9 12 15 12 15 22"></polyline>
                    </svg>
                    <span>Preferencias de Aula</span>
                </button>
                <button type="button" class="modal-tab-btn" data-cfg-tab="cfg-tab-perfil">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
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
                            <button type="button" class="pref-btn" data-pref-tema="oscuro">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                                </svg>
                                <span>Oscuro</span>
                            </button>
                            <button type="button" class="pref-btn" data-pref-tema="claro">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="5"></circle>
                                    <line x1="12" y1="1" x2="12" y2="3"></line>
                                    <line x1="12" y1="21" x2="12" y2="23"></line>
                                    <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                                    <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                                    <line x1="1" y1="12" x2="3" y2="12"></line>
                                    <line x1="21" y1="12" x2="23" y2="12"></line>
                                    <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                                    <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
                                </svg>
                                <span>Claro</span>
                            </button>
                            <button type="button" class="pref-btn" data-pref-tema="auto">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                                    <line x1="8" y1="21" x2="16" y2="21"></line>
                                    <line x1="12" y1="17" x2="12" y2="21"></line>
                                </svg>
                                <span>Automático</span>
                            </button>
                        </div>
                    </div>

                    <!-- 2. Paleta de Colores (6 Predefinidos + Selector Personalizado) -->
                    <div class="pref-grupo">
                        <div class="pref-grupo-titulo">
                            <span>Paleta de Colores</span>
                        </div>
                        <div class="pref-opciones-grid cols-3">
                            <button type="button" class="pref-btn" data-pref-acento="dorado">
                                <span class="color-circulo" style="background:#897c37;"></span>
                                <span>Dorado / Oliva</span>
                            </button>
                            <button type="button" class="pref-btn" data-pref-acento="azul">
                                <span class="color-circulo" style="background:#2563eb;"></span>
                                <span>Azul</span>
                            </button>
                            <button type="button" class="pref-btn" data-pref-acento="esmeralda">
                                <span class="color-circulo" style="background:#059669;"></span>
                                <span>Esmeralda</span>
                            </button>
                            <button type="button" class="pref-btn" data-pref-acento="violeta">
                                <span class="color-circulo" style="background:#7c3aed;"></span>
                                <span>Violeta</span>
                            </button>
                            <button type="button" class="pref-btn" data-pref-acento="coral">
                                <span class="color-circulo" style="background:#e11d48;"></span>
                                <span>Rojo Coral</span>
                            </button>
                            <button type="button" class="pref-btn" data-pref-acento="cian">
                                <span class="color-circulo" style="background:#0891b2;"></span>
                                <span>Cian / Turquesa</span>
                            </button>
                        </div>
                        <div class="color-custom-fila">
                            <span>Selector de Color Personalizado:</span>
                            <div style="display:flex; align-items:center; gap:8px;">
                                <input type="color" id="inputColorCustom" class="input-color-picker" value="#897c37" title="Elegir color personalizado">
                                <button type="button" id="btnAplicarColorCustom" class="btn btn-secundario btn-pequeno">Aplicar color</button>
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
                                <span>Moderna / Sans</span>
                            </button>
                            <button type="button" class="pref-btn" data-pref-fuente="serif">
                                <span>Clásica / Serif</span>
                            </button>
                            <button type="button" class="pref-btn" data-pref-fuente="mono">
                                <span>Monoespaciada</span>
                            </button>
                        </div>
                    </div>

                    <!-- 4. Escala de Texto y Botones (Slider 85% a 130%) -->
                    <div class="pref-grupo">
                        <div class="pref-grupo-titulo">
                            <span>Escala de Texto y Botones</span>
                            <strong id="labelEscalaPorcentaje" style="color:var(--text-gold);">100%</strong>
                        </div>
                        <div class="termostato-slider-wrap">
                            <span>85%</span>
                            <input type="range" id="sliderEscalaTexto" class="slider-rango" min="85" max="130" step="5" value="100">
                            <span>130%</span>
                        </div>
                    </div>

                    <!-- 5. Densidad de las Tarjetas -->
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

                    <!-- 6. Accesibilidad -->
                    <div class="pref-grupo" style="margin-bottom:0;">
                        <div class="pref-grupo-titulo">
                            <span>Accesibilidad Visual</span>
                        </div>
                        <label class="switch-fila" for="chkAltoContraste">
                            <div>
                                <strong>Modo Alto Contraste</strong>
                                <small>Bordes y textos más definidos para ver mejor con el reflejo del proyector</small>
                            </div>
                            <input type="checkbox" id="chkAltoContraste" class="switch-check">
                        </label>
                        <label class="switch-fila" for="chkReducirAnim">
                            <div>
                                <strong>Reducir Animaciones</strong>
                                <small>Desactiva efectos de transición y pulsos visuales</small>
                            </div>
                            <input type="checkbox" id="chkReducirAnim" class="switch-check">
                        </label>
                    </div>
                </div>

                <!-- ===========================================================
                     PESTAÑA 2: PREFERENCIAS DE AULA
                     =========================================================== -->
                <div id="cfg-tab-aula" class="cfg-tab-pane">
                    <!-- 1. Preset Inicial de Aire Acondicionado -->
                    <div class="pref-grupo">
                        <div class="pref-grupo-titulo">
                            <span>Preset Inicial de Aire Acondicionado</span>
                        </div>
                        <p style="font-size:12px; color:var(--text-secondary); margin-bottom:10px;">
                            Elegí en qué temperatura y modo querés que arranque el aire al tocar <strong>"Encender Aire"</strong> o <strong>"Iniciar Clase"</strong>.
                        </p>
                        <div class="pref-opciones-grid">
                            <div class="campo-grupo" style="margin-bottom:0;">
                                <label for="prefAireTemp">Temperatura Preferida</label>
                                <select id="prefAireTemp">
                                    <?php for ($t = 16; $t <= 30; $t++): ?>
                                    <option value="<?php echo $t; ?>" <?php echo ($t === 24) ? 'selected' : ''; ?>><?php echo $t; ?>°C</option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="campo-grupo" style="margin-bottom:0;">
                                <label for="prefAireModo">Modo Preferido</label>
                                <select id="prefAireModo">
                                    <option value="frio">Frío (Cool)</option>
                                    <option value="calor">Calor (Heat)</option>
                                    <option value="auto">Automático (Auto)</option>
                                    <option value="ventilacion">Ventilación (Fan)</option>
                                    <option value="seco">Deshumidificador / Seco (Dry)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Confirmación de Seguridad y Sonido -->
                    <div class="pref-grupo">
                        <div class="pref-grupo-titulo">
                            <span>Comportamiento de los Controles</span>
                        </div>
                        <label class="switch-fila" for="chkConfirmarApagado">
                            <div>
                                <strong>Confirmación de Seguridad antes de Apagar</strong>
                                <small>Pedir confirmación antes de apagar el proyector o ejecutar "Cerrar Aula"</small>
                            </div>
                            <input type="checkbox" id="chkConfirmarApagado" class="switch-check" checked>
                        </label>

                        <label class="switch-fila" for="chkSonidoBeep">
                            <div>
                                <strong>Sonido / Beep de Confirmación</strong>
                                <small>Emitir un tono suave al pulsar botones del control remoto web</small>
                            </div>
                            <input type="checkbox" id="chkSonidoBeep" class="switch-check" checked>
                        </label>
                    </div>

                    <!-- 3. Frecuencia de Sincronización -->
                    <div class="pref-grupo" style="margin-bottom:0;">
                        <div class="pref-grupo-titulo">
                            <span>Frecuencia de Sincronización del Panel</span>
                        </div>
                        <div class="campo-grupo" style="margin-bottom:0;">
                            <label for="prefIntervaloSync">Intervalo de actualización automática</label>
                            <select id="prefIntervaloSync">
                                <option value="3000">Cada 3 segundos (Tiempo real rápido)</option>
                                <option value="5000">Cada 5 segundos (Balanceado)</option>
                                <option value="10000">Cada 10 segundos (Ahorro de datos)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- ===========================================================
                     PESTAÑA 3: MI PERFIL Y SEGURIDAD
                     =========================================================== -->
                <div id="cfg-tab-perfil" class="cfg-tab-pane">
                    <div class="pref-grupo">
                        <div class="pref-grupo-titulo">
                            <span>Datos de la Cuenta Actual</span>
                        </div>
                        <div class="switch-fila">
                            <div>
                                <strong>Usuario: <?php echo htmlspecialchars($usuario, ENT_QUOTES, 'UTF-8'); ?></strong>
                                <small>Rol asignado: <?php echo $esAdmin ? 'Administrador del Sistema' : 'Profesor / Docente'; ?></small>
                            </div>
                            <span class="rol-tag"><?php echo $esAdmin ? 'Admin' : 'Profesor'; ?></span>
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
                                <label for="inputPerfilNombre">Nombre Completo (visible en la barra superior)</label>
                                <input type="text" id="inputPerfilNombre" value="<?php echo htmlspecialchars($nombreCompleto, ENT_QUOTES, 'UTF-8'); ?>" required maxlength="100">
                            </div>
                            <button type="submit" class="btn btn-primario btn-pequeno">
                                <span>Guardar Nombre</span>
                            </button>
                        </form>
                    </div>

                    <!-- Formulario 2: Cambiar mi Contraseña -->
                    <div class="pref-grupo" style="margin-bottom:0; border-top:1px solid var(--border-subtle); padding-top:14px;">
                        <div class="pref-grupo-titulo">
                            <span>Cambiar mi Contraseña</span>
                        </div>
                        <div id="perfilPasswordFeedback"></div>
                        <form id="formPerfilPassword">
                            <div class="campo-grupo">
                                <label for="inputPassActual">Contraseña Actual</label>
                                <input type="password" id="inputPassActual" required autocomplete="current-password" placeholder="Ingrese su contraseña actual">
                            </div>
                            <div class="pref-opciones-grid">
                                <div class="campo-grupo">
                                    <label for="inputPassNueva">Nueva Contraseña</label>
                                    <input type="password" id="inputPassNueva" required minlength="4" autocomplete="new-password" placeholder="Mínimo 4 caracteres">
                                </div>
                                <div class="campo-grupo">
                                    <label for="inputPassConfirmar">Confirmar Nueva Contraseña</label>
                                    <input type="password" id="inputPassConfirmar" required minlength="4" autocomplete="new-password" placeholder="Repita la contraseña">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primario btn-pequeno">
                                <span>Actualizar Contraseña</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" id="btnResetApariencia" class="btn btn-secundario btn-pequeno">
                    <span>Restablecer valores por defecto</span>
                </button>
                <button type="button" id="btnCerrarConfigFooter" class="btn btn-primario btn-pequeno">
                    <span>Listo</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Script Principal del Panel -->
    <script src="assets/js/panel.js"></script>
</body>
</html>
