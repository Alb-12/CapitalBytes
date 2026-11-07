<?php

declare(strict_types=1);

session_start();
// El encabezado JSON va antes de incluir db.php para que, si la conexión falla, responda en JSON.
header('Content-Type: application/json; charset=utf-8');
include(__DIR__ . '/../db/db.php');
require __DIR__ . '/../servicios/WhatsAppService.php';
/** @var mysqli $conn */

function responder(array $payload, int $httpCode = 200): never
{
    http_response_code($httpCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// 1. Sesión, método y token CSRF.
if (empty($_SESSION['id_usuario'])) {
    responder(['status' => 'error', 'mensaje' => 'Sesión no válida'], 401);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(['status' => 'error', 'mensaje' => 'Método no permitido'], 405);
}
$tokenRecibido = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!hash_equals((string) ($_SESSION['csrf_redito'] ?? ''), $tokenRecibido)) {
    responder(['status' => 'error', 'mensaje' => 'Token de seguridad inválido. Recarga la página.'], 403);
}

// 2. Validar los datos de entrada.
$datos = json_decode(file_get_contents('php://input'), true);
$id    = $datos['id_prestamo_redito'] ?? null;
$fecha = (string) ($datos['fecha_pago'] ?? '');

if ($id === null || !ctype_digit((string) $id)) {
    responder(['status' => 'error', 'mensaje' => 'Préstamo no válido'], 400);
}
$fechaObj = DateTime::createFromFormat('Y-m-d H:i:s', $fecha);
if ($fechaObj === false || $fechaObj->format('Y-m-d H:i:s') !== $fecha) {
    responder(['status' => 'error', 'mensaje' => 'Fecha de pago no válida'], 400);
}
$idPrestamoRedito = (int) $id;

try {
    $servicio = WhatsAppService::desdeConfiguracion($conn);
    if ($servicio === null) {
        responder(['status' => 'error', 'mensaje' => 'WhatsApp Business no está configurado. Ve al módulo de Respaldo y configura tu token y Phone Number ID.'], 400);
    }

    $nombreEmpresa = $conn->query('SELECT nombre_empresa FROM configuracion_sistema WHERE id = 1')->fetch_assoc()['nombre_empresa'] ?? '';
    $nombreEmpresa = $nombreEmpresa ?: 'PRESTA-APP';

    // 3. El recibo y el teléfono salen de la base de datos, no de lo que envía el navegador.
    $stmt = $conn->prepare(
        "SELECT pg.tipo_pago, pg.monto, pg.saldo_capital_resultante, pg.fecha_pago,
                pr.id_prestamo_redito,
                CONCAT(c.nombre, ' ', c.apellido) AS cliente, c.telefono
         FROM pagos_redito pg
         JOIN prestamos_redito pr ON pg.id_prestamo_redito = pr.id_prestamo_redito
         JOIN clientes c ON pr.id_cliente = c.id_cliente
         WHERE pg.id_prestamo_redito = ? AND pg.fecha_pago = ?
         LIMIT 1"
    );
    $stmt->bind_param('is', $idPrestamoRedito, $fecha);
    $stmt->execute();
    $pago = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    error_log('Error al cargar pago de rédito para WhatsApp: ' . $e->getMessage());
    responder(['status' => 'error', 'mensaje' => 'Error interno al cargar el pago'], 500);
}

if ($pago === null) {
    responder(['status' => 'error', 'mensaje' => 'Pago no encontrado'], 404);
}
$telefono = trim((string) ($pago['telefono'] ?? ''));
if ($telefono === '') {
    responder(['status' => 'error', 'mensaje' => 'Este cliente no tiene teléfono registrado'], 400);
}

$tipo = $pago['tipo_pago'] === 'Interes' ? 'Interés' : 'Abono a capital';
$mensaje = '🧾 *Recibo de Pago (Rédito) - ' . strtoupper($nombreEmpresa) . "*\n\n"
    . "Cliente: {$pago['cliente']}\n"
    . "Préstamo a rédito #: {$pago['id_prestamo_redito']}\n"
    . "Fecha: {$pago['fecha_pago']}\n"
    . "Tipo de pago: {$tipo}\n"
    . "─────────────────\n"
    . '*MONTO PAGADO: $' . number_format((float) $pago['monto'], 2) . "*\n"
    . 'Saldo de capital: $' . number_format((float) $pago['saldo_capital_resultante'], 2) . "\n\n"
    . '¡Gracias por su pago!';

$resultado = $servicio->enviarTexto($telefono, $mensaje);

if ($resultado['ok']) {
    responder(['status' => 'success']);
}
responder(['status' => 'error', 'mensaje' => $resultado['error'] ?? 'Error desconocido al enviar el mensaje'], $resultado['http'] === 0 ? 400 : 502);
