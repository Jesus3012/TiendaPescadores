<?php
// includes/session.php
if (session_status() == PHP_SESSION_NONE) {
    // estás trabajando localmente, así que no uses HTTPS
    $secure = false; 
    $httponly = true;

    // Configurar cookies de sesión de forma tradicional
    session_set_cookie_params(0, '/', null, $secure, $httponly);
    session_start();
}

// Timeout por inactividad (30 minutos)
$timeout_seconds = 1800;

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_seconds) {
    // destruir sesión por inactividad
    session_unset();
    session_destroy();
    session_start();
    $_SESSION['timeout_message'] = "Tu sesión expiró por inactividad.";
}

$_SESSION['last_activity'] = time();
?>
