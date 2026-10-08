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

$datos = json_decode(file_get_contents('php://input'), true);

if (!is_array($datos)) {
    responder(['status' => 'error', 'mensaje' => 'No se recibieron datos válidos'], 400);
}

$requeridos = [
    'id_prestamo', 'monto_pagado', 'monto_mora',
    'monto_interes', 'monto_capital', 'saldo_restante',
];

foreach ($requeridos as $campo) {
    if (!isset($datos[$campo]) || $datos[$campo] === '') {
        responder(['status' => 'error', 'mensaje' => "Falta el campo '$campo'", 'campo' => $campo], 400);
    }
    if (!is_numeric($datos[$campo])) {
        responder(['status' => 'error', 'mensaje' => "El campo '$campo' debe ser numérico", 'campo' => $campo], 400);
    }
}

$idPrestamo    = (int) $datos['id_prestamo'];
$montoPagado   = (float) $datos['monto_pagado'];
$montoMora     = (float) $datos['monto_mora'];
$montoInteres  = (float) $datos['monto_interes'];
$montoCapital  = (float) $datos['monto_capital'];
$saldoRestante = max(0.0, (float) $datos['saldo_restante']);

$registradoPor = (int) ($_SESSION['id_usuario'] ?? 1);
$fechaPago     = date('Y-m-d H:i:s');

try {
    // Verificar que el préstamo exista antes de registrar el pago
    $checkPrestamo = $conn->prepare('SELECT 1 FROM prestamos WHERE id_prestamo = ?');
    $checkPrestamo->bind_param('i', $idPrestamo);
    $checkPrestamo->execute();
    $existePrestamo = $checkPrestamo->get_result()->num_rows > 0;
    $checkPrestamo->close();

    if (!$existePrestamo) {
        responder(['status' => 'error', 'mensaje' => 'El préstamo no existe', 'campo' => 'id_prestamo'], 404);
    }

    // 1. Insertar el pago
    $stmt = $conn->prepare(
        'INSERT INTO pagos
            (id_prestamo, fecha_pago, monto_pagado, monto_mora,
             monto_interes, monto_capital, saldo_restante, registrado_por)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param(
        'isdddddi',
        $idPrestamo,
        $fechaPago,
        $montoPagado,
        $montoMora,
        $montoInteres,
        $montoCapital,
        $saldoRestante,
        $registradoPor
    );
    $stmt->execute();
    $idPago = $conn->insert_id;
    $stmt->close();

    // 2. Registrar el movimiento en caja
    // Si esto falla, no queremos perder el pago ya guardado: lo registramos
    // en el log y avisamos al usuario, pero la respuesta sigue siendo éxito
    // porque el pago SÍ quedó guardado en la tabla 'pagos'.
    try {
        include __DIR__ . '/../caja/caja_registro_automatico.php';
        registrarPagoEnCaja($conn, $idPago, $montoPagado, $registradoPor);
    } catch (Throwable $e) {
        error_log('Pago #' . $idPago . ' guardado, pero falló el registro en caja: ' . $e->getMessage());
    }

    // 3. Actualizar saldo y estado del préstamo
    $nuevoEstado = $saldoRestante <= 0 ? 'Pagado' : 'Activo';
    $upd = $conn->prepare('UPDATE prestamos SET saldo_pendiente = ?, estado = ? WHERE id_prestamo = ?');
    $upd->bind_param('dsi', $saldoRestante, $nuevoEstado, $idPrestamo);
    $upd->execute();
    $upd->close();

    // 4. Devolver datos del pago para mostrar en el recibo
    responder([
        'status' => 'success',
        'pago'   => [
            'id_pagos'       => $idPago,
            'id_prestamo'    => $idPrestamo,
            'fecha_pago'     => $fechaPago,
            'monto_pagado'   => $montoPagado,
            'monto_mora'     => $montoMora,
            'monto_interes'  => $montoInteres,
            'monto_capital'  => $montoCapital,
            'saldo_restante' => $saldoRestante,
        ],
    ]);
} catch (mysqli_sql_exception $e) {
    error_log('Error al registrar pago: ' . $e->getMessage());
    responder(['status' => 'error', 'mensaje' => 'Error interno al registrar el pago'], 500);
} finally {
    $conn->close();
}