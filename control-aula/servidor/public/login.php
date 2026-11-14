<?php
/**
 * Página de autenticación para el Panel de Control (Roles: admin y profesor)
 */

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/funciones.php';

iniciarSesionSegura();

// Si ya tiene sesión activa, redirigir al panel principal
if (!empty($_SESSION['autenticado']) && $_SESSION['autenticado'] === true) {
    header('Location: index.php');
    exit;
}

$pdo = obtenerConexionDB();
$ipCliente = obtenerIPCliente();
$error = '';

// Verificar si la IP está bloqueada por demasiados intentos
$estadoBloqueo = verificarBloqueoIP($pdo, $ipCliente);
if ($estadoBloqueo['bloqueado']) {
    $error = $estadoBloqueo['mensaje'];
}

// Procesar formulario de login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$estadoBloqueo['bloqueado']) {
    $tokenCSRF = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $tokenCSRF)) {
        $error = 'Token de seguridad inválido. Recargue la página e intente nuevamente.';
    } else {
        $usuario = trim($_POST['usuario'] ?? '');
        $password = $_POST['password'] ?? '';

        $authResult = autenticarCredencialesUsuario($pdo, $usuario, $password);

        if ($authResult['exito']) {
            // Login exitoso
            limpiarIntentosLogin($pdo, $ipCliente);
            session_regenerate_id(true);
            $_SESSION['autenticado'] = true;
            $_SESSION['usuario_id'] = (int) $authResult['id'];
            $_SESSION['usuario'] = $authResult['usuario'];
            $_SESSION['nombre_completo'] = $authResult['nombre_completo'];
            $_SESSION['rol'] = $authResult['rol'];
            $_SESSION['aula_id'] = $authResult['aula_id'] ?? null;
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            registrarEvento(
                $pdo,
                'panel',
                'INICIO_SESION',
                "Inicio de sesion exitoso de '{$authResult['usuario']}' (rol: {$authResult['rol']}) desde IP {$ipCliente}"
            );

            header('Location: index.php');
            exit;
        } else {
            // Credenciales incorrectas o cuenta inactiva
            registrarFalloLogin($pdo, $ipCliente);
            registrarEvento(
                $pdo,
                'seguridad',
                'FALLO_LOGIN',
                "Intento fallido de login con usuario '{$usuario}' desde IP {$ipCliente}"
            );
            $error = $authResult['error'] ?? 'Credenciales incorrectas. Verifique usuario y contraseña.';
        }
    }
}

$csrfToken = obtenerTokenCSRF();
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
    <title>Iniciar Sesión | Control de Aula</title>
    <!-- Bootstrap 5.3 CSS & Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script>
        (function() {
            try {
                var prefs = JSON.parse(localStorage.getItem('control_aula_prefs_bs5') || '{}');
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
    <link rel="stylesheet" href="assets/css/estilos.css">
</head>
<body class="login-body bg-body-tertiary d-flex align-items-center py-4 min-vh-100">
    <div class="container login-wrapper" style="max-width: 420px;">
        <div class="card shadow-sm border login-card">
            <div class="card-header bg-primary text-white text-center py-3 border-0">
                <i class="bi bi-building-gear fs-2 d-block mb-1"></i>
                <h1 class="h5 fw-bold mb-0">Sistema de Control de Aula</h1>
                <small class="opacity-75">Climatización, Iluminación y Proyección (ESP32)</small>
            </div>

            <div class="card-body p-4">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger d-flex align-items-center py-2 px-3 small mb-3" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2 flex-shrink-0"></i>
                        <div><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="login.php" class="login-form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

                    <div class="mb-3">
                        <label for="usuario" class="form-label fw-semibold small mb-1">Usuario</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text" class="form-control" id="usuario" name="usuario" required autocomplete="username" autofocus placeholder="Ej: aula101 o admin">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="password" class="form-label fw-semibold small mb-1">Contraseña</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password" placeholder="••••••••">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                        <i class="bi bi-box-arrow-in-right me-1"></i>
                        <span>Ingresar al Aula</span>
                    </button>
                </form>
            </div>

            <div class="card-footer bg-body-tertiary text-center py-2">
                <small class="text-body-secondary"><i class="bi bi-shield-lock me-1"></i>Acceso exclusivo para docentes y administradores</small>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
