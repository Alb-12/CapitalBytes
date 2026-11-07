<?php

declare(strict_types=1);

session_start();
require __DIR__ . '/google_config.php';
require __DIR__ . '/cifrado_helper.php';
require __DIR__ . '/../db/db.php';

if (!in_array($_SESSION['rol'] ?? '', ['Administrador', 'Supervisor'], true)) {
    http_response_code(403);
    exit('Sin permisos');
}

if (isset($_GET['error'])) {
    exit('Google reportó un error: ' . htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8') . '. Vuelve al módulo de Respaldo e inténtalo de nuevo.');
}

$stateEsperado = $_SESSION['google_oauth_state'] ?? '';
unset($_SESSION['google_oauth_state']);

if (empty($_GET['code']) || empty($_GET['state']) || !hash_equals($stateEsperado, $_GET['state'])) {
    exit('Solicitud inválida o expirada. Vuelve al módulo de Respaldo e intenta conectar de nuevo.');
}

// 1. Intercambiar el código de autorización por tokens
$ch = curl_init('https://oauth2.googleapis.com/token');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_POSTFIELDS     => http_build_query([
        'code'          => $_GET['code'],
        'client_id'     => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri'  => googleRedirectUri(),
        'grant_type'    => 'authorization_code',
    ]),
]);
$respuestaCruda = curl_exec($ch);
$curlError = curl_error($ch);
curl_close($ch);

if ($respuestaCruda === false) {
    exit('No se pudo contactar a Google: ' . htmlspecialchars($curlError, ENT_QUOTES, 'UTF-8'));
}

$tokens = json_decode((string) $respuestaCruda, true);

if (empty($tokens['refresh_token'])) {
    exit(
        'Google no devolvió un token de larga duración (refresh_token). ' .
        'Esto suele pasar si ya habías conectado esta cuenta antes. Ve a ' .
        '<a href="https://myaccount.google.com/permissions" target="_blank">myaccount.google.com/permissions</a>, ' .
        'busca esta app, quita el acceso, y vuelve a intentar conectar desde el módulo de Respaldo.'
    );
}

// 2. Obtener el email de la cuenta conectada (solo para mostrarlo en pantalla)
$chInfo = curl_init('https://www.googleapis.com/oauth2/v2/userinfo?access_token=' . urlencode($tokens['access_token']));
curl_setopt_array($chInfo, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
$infoCruda = curl_exec($chInfo);
curl_close($chInfo);
$info = json_decode((string) $infoCruda, true);
$email = $info['email'] ?? null;

// 3. Guardar el refresh_token CIFRADO en la base de datos
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $tokenCifrado = cifrar($tokens['refresh_token']);

    $stmt = $conn->prepare('UPDATE configuracion_sistema SET google_refresh_token = ?, google_email = ? WHERE id = 1');
    $stmt->bind_param('ss', $tokenCifrado, $email);
    $stmt->execute();
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    error_log('Error al guardar token de Google: ' . $e->getMessage());
    exit('Se conectó con Google, pero hubo un error al guardar la conexión. Revisa el log del servidor.');
} finally {
    $conn->close();
}

header('Location: ../dashboard.php?mod=respaldo&google=conectado');
exit;