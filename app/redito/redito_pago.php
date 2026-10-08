<?php

declare(strict_types=1);

session_start();
include(__DIR__ . '/../db/db.php');

header('Content-Type: application/json; charset=utf-8');

function responder(array $payload, int $httpCode = 200): never
{
    http_response_code($httpCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['id_usuario'])) {
    responder(['status' => 'error', 'mensaje' => 'Sesión no válida'], 401);
}

$datos = json_decode(file_get_contents('php://input'), true);
if (!is_array($datos)) {
    responder(['status' => 'error', 'mensaje' => 'No se recibieron datos válidos'], 400);
}

if (empty($datos['id_prestamo_redito']) || !ctype_digit((string) $datos['id_prestamo_redito'])) {
    responder(['status' => 'error', 'mensaje' => 'Préstamo no válido'], 400);
}
$idPrestamoRedito = (int) $datos['id_prestamo_redito'];

if (!in_array($datos['tipo_pago'] ?? '', ['Interes', 'Abono_Capital'], true)) {
    responder(['status' => 'error', 'mensaje' => 'Tipo de pago no válido'], 400);
}
$tipoPago = $datos['tipo_pago'];

if (!is_numeric($datos['monto'] ?? null) || (float) $datos['monto'] <= 0) {
    responder(['status' => 'error', 'mensaje' => 'Ingresa un monto válido', 'campo' => 'monto'], 400);
}
$monto = (float) $datos['monto'];

$idUsuario = (int) $_SESSION['id_usuario'];
$fechaPago = date('Y-m-d H:i:s');

try {
    $checkPrestamo = $conn->prepare('SELECT saldo_capital, estado FROM prestamos_redito WHERE id_prestamo_redito = ?');
    $checkPrestamo->bind_param('i', $idPrestamoRedito);
    $checkPrestamo->execute();
    $prestamo = $checkPrestamo->get_result()->fetch_assoc();
    $checkPrestamo->close();

    if ($prestamo === null) {
        responder(['status' => 'error', 'mensaje' => 'Préstamo no encontrado'], 404);
    }
    if ($prestamo['estado'] !== 'Activo') {
        responder(['status' => 'error', 'mensaje' => 'Este préstamo ya no está activo'], 409);
    }

    $saldoActual = (float) $prestamo['saldo_capital'];

    // Un pago de "solo interés" NO reduce el saldo de capital — el rédito
    // se sigue cobrando sobre el mismo saldo en el siguiente período.
    // Un "abono a capital" sí lo reduce, y por eso el siguiente cobro de
    // interés será menor (tal como confirmaste).
    if ($tipoPago === 'Abono_Capital') {
        if ($monto > $saldoActual) {
            responder(['status' => 'error', 'mensaje' => 'El abono no puede ser mayor al saldo de capital pendiente ($' . number_format($saldoActual, 2) . ')', 'campo' => 'monto'], 400);
        }
        $nuevoSaldo = round($saldoActual - $monto, 2);
    } else {
        $nuevoSaldo = $saldoActual;
    }

    $stmt = $conn->prepare(
        'INSERT INTO pagos_redito (id_prestamo_redito, tipo_pago, monto, saldo_capital_resultante, fecha_pago, registrado_por)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('isddsi', $idPrestamoRedito, $tipoPago, $monto, $nuevoSaldo, $fechaPago, $idUsuario);
    $stmt->execute();
    $stmt->close();

    $nuevoEstado = $nuevoSaldo <= 0 ? 'Pagado' : 'Activo';
    $upd = $conn->prepare('UPDATE prestamos_redito SET saldo_capital = ?, estado = ? WHERE id_prestamo_redito = ?');
    $upd->bind_param('dsi', $nuevoSaldo, $nuevoEstado, $idPrestamoRedito);
    $upd->execute();
    $upd->close();

    responder(['status' => 'success', 'saldo_capital' => $nuevoSaldo, 'estado' => $nuevoEstado]);
} catch (mysqli_sql_exception $e) {
    error_log('Error al registrar pago de rédito: ' . $e->getMessage());
    responder(['status' => 'error', 'mensaje' => 'Error interno al registrar el pago'], 500);
} finally {
    $conn->close();
}
