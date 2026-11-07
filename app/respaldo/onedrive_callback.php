<?php

declare(strict_types=1);

session_start();
require __DIR__ . '/onedrive_config.php';
require __DIR__ . '/cifrado_helper.php';
require __DIR__ . '/../db/db.php';

if (!in_array($_SESSION['rol'] ?? '', ['Administrador', 'Supervisor'], true)) {
    http_response_code(403);
    exit('Sin permisos');
}

if (isset($_GET['error'])) {
    $descripcion = htmlspecialchars($_GET['error_description'] ?? $_GET['error'], ENT_QUOTES, 'UTF-8');
    exit("Microsoft reportó un error: $descripcion. Vuelve al módulo de Respaldo e inténtalo de nuevo.");
}

$stateEsperado = $_SESSION['onedrive_oauth_state'] ?? '';
unset($_SESSION['onedrive_oauth_state']);

if (empty($_GET['code']) || empty($_GET['state']) || !hash_equals($stateEsperado, $_GET['state'])) {
    exit('Solicitud inválida o expirada. Vuelve al módulo de Respaldo e intenta conectar de nuevo.');
}

// 1. Intercambiar el código por tokens
$ch = curl_init(ONEDRIVE_AUTHORITY . '/oauth2/v2.0/token');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_POSTFIELDS     => http_build_query([
        'client_id'     => ONEDRIVE_CLIENT_ID,
        'client_secret' => ONEDRIVE_CLIENT_SECRET,
        'code'          => $_GET['code'],
        'redirect_uri'  => oneDriveRedirectUri(),
        'grant_type'    => 'authorization_code',
        'scope'         => ONEDRIVE_SCOPE,
    ]),
]);
$respuestaCruda = curl_exec($ch);
$curlError = curl_error($ch);
curl_close($ch);

if ($respuestaCruda === false) {
    exit('No se pudo contactar a Microsoft: ' . htmlspecialchars($curlError, ENT_QUOTES, 'UTF-8'));
}

$tokens = json_decode((string) $respuestaCruda, true);

if (empty($tokens['refresh_token'])) {
    $motivo = $tokens['error_description'] ?? 'Microsoft no devolvió un refresh_token.';
    exit('Error al conectar OneDrive: ' . htmlspecialchars($motivo, ENT_QUOTES, 'UTF-8'));
}

// 2. Obtener el email de la cuenta conectada (solo para mostrarlo en pantalla)
$chInfo = curl_init('https://graph.microsoft.com/v1.0/me');
curl_setopt_array($chInfo, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $tokens['access_token']],
]);
$infoCruda = curl_exec($chInfo);
curl_close($chInfo);
$info = json_decode((string) $infoCruda, true);
$email = $info['mail'] ?? $info['userPrincipalName'] ?? null;

// 3. Guardar el refresh_token CIFRADO
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $tokenCifrado = cifrar($tokens['refresh_token']);

    $stmt = $conn->prepare('UPDATE configuracion_sistema SET onedrive_refresh_token = ?, onedrive_email = ? WHERE id = 1');
    $stmt->bind_param('ss', $tokenCifrado, $email);
    $stmt->execute();
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    error_log('Error al guardar token de OneDrive: ' . $e->getMessage());
    exit('Se conectó con Microsoft, pero hubo un error al guardar la conexión. Revisa el log del servidor.');
} finally {
    $conn->close();
}

header('Location: ../dashboard.php?mod=respaldo&onedrive=conectado');
exit;
