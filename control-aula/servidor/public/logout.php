<?php
/**
 * Cierre de sesión seguro y redirección al login
 */

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';

iniciarSesionSegura();

if (!empty($_SESSION['autenticado'])) {
    $pdo = obtenerConexionDB();
    $ipCliente = obtenerIPCliente();
    registrarEvento($pdo, 'panel', 'CIERRE_SESION', "Cierre de sesion del usuario {$_SESSION['usuario']} desde IP {$ipCliente}");
}

$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

session_destroy();

header('Location: login.php');
exit;
