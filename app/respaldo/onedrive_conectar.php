<?php

declare(strict_types=1);

session_start();
require __DIR__ . '/onedrive_config.php';

if (!in_array($_SESSION['rol'] ?? '', ['Administrador', 'Supervisor'], true)) {
    http_response_code(403);
    exit('Sin permisos');
}

$state = bin2hex(random_bytes(16));
$_SESSION['onedrive_oauth_state'] = $state;

$params = http_build_query([
    'client_id'     => ONEDRIVE_CLIENT_ID,
    'redirect_uri'  => oneDriveRedirectUri(),
    'response_type' => 'code',
    'response_mode' => 'query',
    'scope'         => ONEDRIVE_SCOPE,
    'state'         => $state,
    'prompt'        => 'consent', // fuerza la pantalla de consentimiento, para asegurar el refresh_token
]);

header('Location: ' . ONEDRIVE_AUTHORITY . '/oauth2/v2.0/authorize?' . $params);
exit;
