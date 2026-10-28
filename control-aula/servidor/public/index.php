<?php
/**
 * Panel de Control Principal - Control de Aula
 * Vistas diferenciadas por rol:
 *  - Profesor: Interfaz limpia y directa con controles de Luces, Aire Acondicionado, Proyector y Configuración visual.
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
?>
<!DOCTYPE html>
<html lang="es" data-theme="oscuro" data-accent="dorado" data-scale="normal">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1c1818">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="user-role" content="<?php echo htmlspecialchars($rol, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="user-id" content="<?php echo $usuarioIdActual; ?>">
    <title>Panel de Aula | Control de Aula</title>
    <script>
        (function() {
            try {
                var prefs = JSON.parse(localStorage.getItem('control_aula_prefs') || '{}');
                var root = document.documentElement;
                if (prefs.tema) root.setAttribute('data-theme', prefs.tema);
                if (prefs.acento) root.setAttribute('data-accent', prefs.acento);
                if (prefs.escala) root.setAttribute('data-scale', prefs.escala);
            } catch (e) {}
        })();
    </script>
    <link rel="stylesheet" href="assets/css/estilos.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
                    <span>Gestión de Iluminación, Climatización y Proyector</span>
                </div>
            </div>

            <div class="cabecera-acciones">
                <div class="usuario-badge" title="Usuario con sesión iniciada">
                    <span><?php echo htmlspecialchars($nombreCompleto, ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="rol-tag"><?php echo $esAdmin ? 'Admin' : 'Profesor'; ?></span>
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

                <button type="button" id="btnAbrirConfig" class="btn btn-secundario btn-pequeno" title="Configuración de apariencia y tamaño de letra">
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

        <!-- Grilla de Controles Principales del Aula (Luces, Aire y Proyector) -->
        <main class="grilla-controles">
            <!-- Tarjeta 1: Iluminación -->
            <article class="tarjeta-control">
                <div>
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
                                <p>Control general de luces del aula</p>
                            </div>
                        </div>
                        <span id="pastillaLuces" class="estado-pastilla off">APAGADAS</span>
                    </div>
                </div>

                <div class="control-botones">
                    <button type="button" id="btnLucesOn" class="btn btn-grande btn-exito">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 2v10"></path>
                            <path d="M18.4 6.6a9 9 0 1 1-12.77.01"></path>
                        </svg>
                        <span>Encender</span>
                    </button>
                    <button type="button" id="btnLucesOff" class="btn btn-grande btn-peligro">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                        <span>Apagar</span>
                    </button>
                </div>
            </article>

            <!-- Tarjeta 2: Aire Acondicionado -->
            <article class="tarjeta-control">
                <div>
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
                                <h2>Aire Acondicionado</h2>
                                <p>Climatización del aula</p>
                                <span id="colaAireInfo" style="display:none; font-size:11px; color:var(--text-gold); margin-top:2px;"></span>
                            </div>
                        </div>
                        <span id="pastillaAire" class="estado-pastilla off">NINGUNA</span>
                    </div>
                </div>

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
            </article>

            <!-- Tarjeta 3: Proyector -->
            <article class="tarjeta-control">
                <div>
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
                                <h2>Proyector</h2>
                                <p>Proyección multimedia del aula</p>
                                <span id="colaProyectorInfo" style="display:none; font-size:11px; color:var(--text-gold); margin-top:2px;"></span>
                            </div>
                        </div>
                        <span id="pastillaProyector" class="estado-pastilla off">NINGUNA</span>
                    </div>
                </div>

                <div class="control-botones">
                    <button type="button" id="btnProyectorOn" class="btn btn-grande btn-primario">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 2v10"></path>
                            <path d="M18.4 6.6a9 9 0 1 1-12.77.01"></path>
                        </svg>
                        <span>Encender Proyector</span>
                    </button>
                    <button type="button" id="btnProyectorOff" class="btn btn-grande btn-peligro">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                        </svg>
                        <span>Apagar Proyector</span>
                    </button>
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
                    <button type="button" class="tab-btn activo" data-target="tab-usuarios">
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

                <!-- Pestaña 1: Gestión de Usuarios (Profesores y Administradores) -->
                <div id="tab-usuarios" class="tab-contenido activo">
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
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tablaUsuariosBody">
                                    <tr><td colspan="5" style="text-align:center;">Cargando usuarios...</td></tr>
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
                        <div class="seccion-encabezado">
                            <h3>Catálogo de Códigos Infrarrojos</h3>
                        </div>

                        <div class="tabla-contenedor">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Dispositivo</th>
                                        <th>Acción</th>
                                        <th>Protocolo</th>
                                        <th>Datos de Transmisión</th>
                                        <th>Parámetros</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tablaCodigosBody">
                                    <tr><td colspan="6" style="text-align:center;">Cargando catálogo...</td></tr>
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
                                        <label for="codDispositivo">Dispositivo</label>
                                        <select id="codDispositivo" required>
                                            <option value="aire">Aire acondicionado</option>
                                            <option value="proyector">Proyector</option>
                                        </select>
                                    </div>
                                    <div class="campo-grupo">
                                        <label for="codAccion">Acción (identificador)</label>
                                        <input type="text" id="codAccion" placeholder="prender, apagar, menu..." required pattern="[a-zA-Z0-9_-]+">
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
                                        <th>Protocolo</th>
                                        <th>Datos Capturados</th>
                                        <th>Bits</th>
                                        <th>Acción</th>
                                    </tr>
                                </thead>
                                <tbody id="tablaCapturasBody">
                                    <tr><td colspan="5" style="text-align:center;">Esperando capturas del ESP32...</td></tr>
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
                                        <th>Origen</th>
                                        <th>Evento</th>
                                        <th>Detalle</th>
                                    </tr>
                                </thead>
                                <tbody id="tablaLogBody">
                                    <tr><td colspan="4" style="text-align:center;">Cargando eventos...</td></tr>
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
         MODAL DE CONFIGURACIÓN DE APARIENCIA Y ACCESIBILIDAD (AMBOS ROLES)
         ======================================================================= -->
    <div id="modalConfigApariencia" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="tituloModalApariencia">
        <div class="modal-panel">
            <div class="modal-header">
                <h3 id="tituloModalApariencia">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="3"></circle>
                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                    </svg>
                    <span>Apariencia y Accesibilidad</span>
                </h3>
                <button type="button" id="btnCerrarConfig" class="modal-cerrar" aria-label="Cerrar">&times;</button>
            </div>

            <!-- 1. Tema de Fondo -->
            <div class="pref-grupo">
                <span class="pref-grupo-titulo">Tema de Fondo</span>
                <div class="pref-opciones-grid">
                    <button type="button" class="pref-btn" data-pref-tema="oscuro">
                        <span>Modo Oscuro</span>
                    </button>
                    <button type="button" class="pref-btn" data-pref-tema="claro">
                        <span>Modo Claro</span>
                    </button>
                </div>
            </div>

            <!-- 2. Color de Acento -->
            <div class="pref-grupo">
                <span class="pref-grupo-titulo">Color de Acento</span>
                <div class="pref-opciones-grid">
                    <button type="button" class="pref-btn" data-pref-acento="dorado">
                        <span class="color-circulo" style="background:#897c37;"></span>
                        <span>Dorado / Oliva</span>
                    </button>
                    <button type="button" class="pref-btn" data-pref-acento="azul">
                        <span class="color-circulo" style="background:#2563eb;"></span>
                        <span>Azul Institucional</span>
                    </button>
                    <button type="button" class="pref-btn" data-pref-acento="esmeralda">
                        <span class="color-circulo" style="background:#059669;"></span>
                        <span>Verde Esmeralda</span>
                    </button>
                    <button type="button" class="pref-btn" data-pref-acento="violeta">
                        <span class="color-circulo" style="background:#7c3aed;"></span>
                        <span>Violeta / Índigo</span>
                    </button>
                </div>
            </div>

            <!-- 3. Tamaño de Letra -->
            <div class="pref-grupo">
                <span class="pref-grupo-titulo">Tamaño de Letra y Controles</span>
                <div class="pref-opciones-grid cols-4">
                    <button type="button" class="pref-btn" data-pref-escala="pequeno">
                        <span>Pequeño</span>
                    </button>
                    <button type="button" class="pref-btn" data-pref-escala="normal">
                        <span>Normal (100%)</span>
                    </button>
                    <button type="button" class="pref-btn" data-pref-escala="grande">
                        <span>Grande</span>
                    </button>
                    <button type="button" class="pref-btn" data-pref-escala="extragrande">
                        <span>Extra Grande</span>
                    </button>
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

    <!-- Script del Panel -->
    <script src="assets/js/panel.js"></script>
</body>
</html>
