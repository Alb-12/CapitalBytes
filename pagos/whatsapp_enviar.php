<?php

declare(strict_types=1);

include(__DIR__ . '/../db/db.php');

header('Content-Type: application/json; charset=utf-8');

function responder(array $payload, int $httpCode = 200): never
{
    http_response_code($httpCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$datos = json_decode(file_get_contents('php://input'), true);

if (empty($datos['telefono']) || empty($datos['id_pago'])) {
    responder(['status' => 'error', 'mensaje' => 'Datos incompletos'], 400);
}

$idPago = (int) $datos['id_pago'];

try {
    // Credenciales de WhatsApp propias de ESTA instalación/empresa —
    // configuradas desde el módulo de Respaldo, no fijas en el código.
    // Así, cada prestamista que use el sistema manda sus recibos desde su
    // propio número de WhatsApp Business, sin que nadie tenga que tocar
    // el código fuente.
    $config = $conn->query('SELECT nombre_empresa, whatsapp_token, whatsapp_phone_id, whatsapp_api_version FROM configuracion_sistema WHERE id = 1')->fetch_assoc();

    if (empty($config['whatsapp_token']) || empty($config['whatsapp_phone_id'])) {
        responder(['status' => 'error', 'mensaje' => 'WhatsApp Business no está configurado. Ve al módulo de Respaldo y configura tu token y Phone Number ID.'], 400);
    }

    $nombreEmpresa = $config['nombre_empresa'] ?: 'PRESTA-APP';
    $waToken       = $config['whatsapp_token'];
    $waPhoneId     = $config['whatsapp_phone_id'];
    $waVersion     = $config['whatsapp_api_version'] ?: 'v19.0';

    // Cargar datos del pago para el mensaje
    $stmt = $conn->prepare(
        "SELECT pg.*, CONCAT(c.nombre,' ',c.apellido) AS cliente,
                p.cuota_monto, p.saldo_pendiente
         FROM pagos pg
         JOIN prestamos p ON pg.id_prestamo = p.id_prestamo
         JOIN clientes c  ON p.id_cliente   = c.id_cliente
         WHERE pg.id_pagos = ?"
    );
    $stmt->bind_param('i', $idPago);
    $stmt->execute();
    $pago = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    error_log('Error al cargar pago para WhatsApp: ' . $e->getMessage());
    responder(['status' => 'error', 'mensaje' => 'Error interno al cargar el pago'], 500);
} finally {
    $conn->close();
}

if ($pago === null) {
    responder(['status' => 'error', 'mensaje' => 'Pago no encontrado'], 404);
}

// Formatear teléfono (quitar guiones/espacios, agregar código país si falta)
$telefono = preg_replace('/[^0-9]/', '', (string) $datos['telefono']);
if (strlen($telefono) === 10) {
    $telefono = '1' . $telefono; // ajusta tu código de país
}

if ($telefono === '' || strlen($telefono) < 10) {
    responder(['status' => 'error', 'mensaje' => 'El número de teléfono no es válido'], 400);
}

// Mensaje del recibo — usa el nombre de la empresa configurado, no un
// texto fijo, para que el recibo salga con la marca de cada prestamista.
$mensaje = "🧾 *Recibo de Pago - " . strtoupper($nombreEmpresa) . "*\n\n"
    . "Cliente: {$pago['cliente']}\n"
    . "Préstamo #: {$pago['id_prestamo']}\n"
    . "Fecha: {$pago['fecha_pago']}\n\n"
    . 'Capital: $' . number_format((float) $pago['monto_capital'], 2) . "\n"
    . 'Interés: $' . number_format((float) $pago['monto_interes'], 2) . "\n"
    . 'Mora: $' . number_format((float) $pago['monto_mora'], 2) . "\n"
    . "─────────────────\n"
    . '*TOTAL PAGADO: $' . number_format((float) $pago['monto_pagado'], 2) . "*\n"
    . 'Saldo restante: $' . number_format((float) $pago['saldo_restante'], 2) . "\n\n"
    . '¡Gracias por su pago!';

// Enviar vía WhatsApp Business API
$url     = 'https://graph.facebook.com/' . $waVersion . '/' . $waPhoneId . '/messages';
$payload = json_encode([
    'messaging_product' => 'whatsapp',
    'to'                => $telefono,
    'type'              => 'text',
    'text'              => ['body' => $mensaje],
]);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $waToken,
        'Content-Type: application/json',
    ],
]);
$respuesta = curl_exec($ch);
$curlErrno = curl_errno($ch);
$curlError = curl_error($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($curlErrno !== 0) {
    error_log("Error de curl al enviar WhatsApp (pago #$idPago): $curlError");
    responder(['status' => 'error', 'mensaje' => 'No se pudo conectar con WhatsApp: ' . $curlError], 502);
}

$resultado = json_decode((string) $respuesta, true);

if ($httpCode === 200 && isset($resultado['messages'])) {
    responder(['status' => 'success']);
}

error_log("Error de la API de WhatsApp (pago #$idPago, HTTP $httpCode): " . $respuesta);
responder([
    'status'  => 'error',
    'mensaje' => $resultado['error']['message'] ?? 'Error desconocido al enviar el mensaje',
], 502);