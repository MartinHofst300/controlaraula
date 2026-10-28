<?php
/**
 * Página de autenticación para el Panel de Control
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

        if ($usuario === PANEL_USUARIO && password_verify($password, PANEL_PASSWORD_HASH)) {
            // Login exitoso
            limpiarIntentosLogin($pdo, $ipCliente);
            session_regenerate_id(true);
            $_SESSION['autenticado'] = true;
            $_SESSION['usuario'] = PANEL_USUARIO;
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            registrarEvento($pdo, 'panel', 'INICIO_SESION', "Inicio de sesion exitoso desde IP {$ipCliente}");

            header('Location: index.php');
            exit;
        } else {
            // Credenciales incorrectas
            registrarFalloLogin($pdo, $ipCliente);
            registrarEvento($pdo, 'seguridad', 'FALLO_LOGIN', "Intento fallido de login con usuario '{$usuario}' desde IP {$ipCliente}");
            $error = 'Credenciales incorrectas. Verifique usuario y contraseña.';
        }
    }
}

$csrfToken = obtenerTokenCSRF();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1c1818">
    <title>Acceso | Control de Aula</title>
    <link rel="stylesheet" href="assets/css/estilos.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="login-body">
    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-header">
                <div class="login-icon">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                        <line x1="8" y1="21" x2="16" y2="21"></line>
                        <line x1="12" y1="17" x2="12" y2="21"></line>
                    </svg>
                </div>
                <h1>Control de Aula</h1>
                <p>Servidor Central de Gestión Embebida</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alerta alerta-error">
                    <span><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php" class="login-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

                <div class="campo-grupo">
                    <label for="usuario">Usuario</label>
                    <div class="input-contenedor">
                        <input type="text" id="usuario" name="usuario" required autocomplete="username" autofocus placeholder="admin">
                    </div>
                </div>

                <div class="campo-grupo">
                    <label for="password">Contraseña</label>
                    <div class="input-contenedor">
                        <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="••••••••">
                    </div>
                </div>

                <button type="submit" class="btn btn-primario btn-bloque">
                    <span>Ingresar al Sistema</span>
                </button>
            </form>

            <div class="login-footer">
                <span>Acceso restringido para personal autorizado</span>
            </div>
        </div>
    </div>
</body>
</html>
