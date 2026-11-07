<?php
 
declare(strict_types=1);
 
session_start();
include(__DIR__ . '/../db/db.php');
require __DIR__ . '/../servicios/WhatsAppService.php';
 
header('Content-Type: application/json; charset=utf-8');
 
function responder(array $payload, int $httpCode = 200): never
{
    http_response_code($httpCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
 
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
 
// Nuevo: solo usuarios con sesión iniciada pueden enviar mensajes.
if (empty($_SESSION['id_usuario'])) {
    responder(['status' => 'error', 'mensaje' => 'Sesión no válida'], 401);
}
 
// Token CSRF generado en pagos.php (el envío de mensajes es una acción con costo).
$tokenRecibido = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!hash_equals((string) ($_SESSION['csrf_pagos'] ?? ''), $tokenRecibido)) {
    responder(['status' => 'error', 'mensaje' => 'Token de seguridad inválido. Recarga la página.'], 403);
}

$datos = json_decode(file_get_contents('php://input'), true);
 
if (empty($datos['telefono']) || empty($datos['id_pago'])) {
    responder(['status' => 'error', 'mensaje' => 'Datos incompletos'], 400);
}
 
$idPago = (int) $datos['id_pago'];
 
try {
    $servicio = WhatsAppService::desdeConfiguracion($conn);
    if ($servicio === null) {
        responder(['status' => 'error', 'mensaje' => 'WhatsApp Business no está configurado. Ve al módulo de Respaldo y configura tu token y Phone Number ID.'], 400);
    }
 
    $nombreEmpresa = $conn->query('SELECT nombre_empresa FROM configuracion_sistema WHERE id = 1')->fetch_assoc()['nombre_empresa'] ?? '';
    $nombreEmpresa = $nombreEmpresa ?: 'PRESTA-APP';
 
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
 
// La llamada a la API ahora vive en el componente WhatsAppService.
$resultado = $servicio->enviarTexto((string) $datos['telefono'], $mensaje);
 
if ($resultado['ok']) {
    responder(['status' => 'success']);
}
responder(['status' => 'error', 'mensaje' => $resultado['error'] ?? 'Error desconocido al enviar el mensaje'], $resultado['http'] === 0 ? 400 : 502);