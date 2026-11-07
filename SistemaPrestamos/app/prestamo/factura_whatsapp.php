<?php

declare(strict_types=1);

session_start();
// El encabezado JSON va antes de incluir db.php para que, si la conexión falla, responda en JSON.
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/../db/db.php';
require __DIR__ . '/../servicios/WhatsAppService.php';
/** @var mysqli $conn */

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function responder(string $estado, string $mensaje, int $http = 200): never
{
    http_response_code($http);
    echo json_encode(['status' => $estado, 'mensaje' => $mensaje], JSON_UNESCAPED_UNICODE);
    exit;
}

// 1. Sesión, método y token CSRF (el envío de mensajes es una acción con costo).
if (empty($_SESSION['id_usuario'])) {
    responder('error', 'Sesión no válida', 401);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder('error', 'Método no permitido', 405);
}
$tokenRecibido = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!hash_equals((string) ($_SESSION['csrf_factura'] ?? ''), $tokenRecibido)) {
    responder('error', 'Token de seguridad inválido. Recarga la página.', 403);
}

// 2. Validar el dato de entrada.
$datos = json_decode(file_get_contents('php://input'), true);
$id    = $datos['id_prestamo'] ?? null;
if ($id === null || !ctype_digit((string) $id)) {
    responder('error', 'ID de préstamo no proporcionado o inválido', 400);
}
$idPrestamo = (int) $id;

try {
    // 3. Buscar el préstamo y el cliente (consulta preparada).
    $stmt = $conn->prepare(
        'SELECT p.*, c.nombre, c.apellido, c.telefono
         FROM prestamos p
         JOIN clientes c ON p.id_cliente = c.id_cliente
         WHERE p.id_prestamo = ?
         LIMIT 1'
    );
    $stmt->bind_param('i', $idPrestamo);
    $stmt->execute();
    $p = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($p === null) {
        responder('error', 'Préstamo no encontrado', 404);
    }
    $telefono = trim((string) ($p['telefono'] ?? ''));
    if ($telefono === '') {
        responder('error', 'El cliente no tiene teléfono registrado', 400);
    }

    // 4. Servicio de WhatsApp con la configuración de la empresa (tabla configuracion_sistema).
    $servicio = WhatsAppService::desdeConfiguracion($conn);
    if ($servicio === null) {
        responder('error', 'WhatsApp Business no está configurado. Ve al módulo de Respaldo y configura tu token y Phone Number ID.', 400);
    }

    // 5. Armar el mensaje (texto; el envío del PDF queda para E4, ver riesgo R5).
    $numeroFactura = 'FAC-C-' . str_pad((string) $idPrestamo, 6, '0', STR_PAD_LEFT);
    $texto = "Presta_APP - Factura {$numeroFactura}\n"
           . 'Cliente: ' . $p['nombre'] . ' ' . $p['apellido'] . "\n"
           . 'Monto prestado: $' . number_format((float) $p['monto'], 2) . "\n"
           . 'Cuota ' . $p['modalidad_pagos'] . ': $' . number_format((float) $p['cuota_monto'], 2)
           . ' x ' . (int) $p['plazo'] . " cuotas\n"
           . 'Saldo pendiente: $' . number_format((float) $p['saldo_pendiente'], 2) . "\n"
           . 'Inicio: ' . $p['fecha_inicio'] . ' | Fin: ' . $p['fecha_fin'] . "\n"
           . 'Gracias por su confianza.';

    // 6. Llamar al servicio externo a través de la clase de integración.
    $resultado = $servicio->enviarTexto($telefono, $texto);

    if (!$resultado['ok']) {
        responder('error', $resultado['error'] ?? 'No se pudo enviar el mensaje', 502);
    }
    responder('success', 'Factura enviada por WhatsApp');
} catch (mysqli_sql_exception $ex) {
    error_log('Error al enviar factura por WhatsApp: ' . $ex->getMessage());
    responder('error', 'Error interno. Intenta más tarde.', 500);
}
