<?php

declare(strict_types=1);

session_start();

// Vaciar los datos de la sesión y borrar también la cookie de sesión del navegador.
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

// Evita que el botón "atrás" muestre páginas guardadas en caché después de cerrar sesión.
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Location: login.php');
exit;
