<?php

declare(strict_types=1);

session_start();
require __DIR__ . '/google_config.php';

if (!in_array($_SESSION['rol'] ?? '', ['Administrador', 'Supervisor'], true)) {
    http_response_code(403);
    exit('Sin permisos');
}

// 'state' es un token anti-CSRF: Google nos lo devuelve intacto en el
// callback, y verificamos que coincida con el que guardamos aquí, para
// asegurarnos de que la respuesta viene de un flujo que nosotros iniciamos
// y no de un enlace malicioso armado por un tercero.
$state = bin2hex(random_bytes(16));
$_SESSION['google_oauth_state'] = $state;

$params = http_build_query([
    'client_id'     => GOOGLE_CLIENT_ID,
    'redirect_uri'  => googleRedirectUri(),
    'response_type' => 'code',
    'scope'         => GOOGLE_SCOPE,
    'access_type'   => 'offline',  // necesario para recibir un refresh_token
    'prompt'        => 'consent',  // fuerza la pantalla de consentimiento cada vez, para asegurar refresh_token
    'state'         => $state,
]);

header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $params);
exit;