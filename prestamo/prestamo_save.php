<?php

declare(strict_types=1);

include __DIR__ . '/../db/db.php';

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

// 1. Validar campos requeridos
$requeridos = [
    'id_cliente', 'monto', 'tasa_interes', 'tipo_interes',
    'modalidad_pagos', 'plazo', 'fecha_inicio', 'cuota_monto',
];

foreach ($requeridos as $campo) {
    if (!isset($datos[$campo]) || $datos[$campo] === '') {
        responder(['status' => 'error', 'mensaje' => 'Este campo es requerido', 'campo' => $campo], 400);
    }
}

// 2. Validar tipos y rangos (antes solo se comprobaba que no estuvieran vacíos)
if (!ctype_digit((string) $datos['id_cliente'])) {
    responder(['status' => 'error', 'mensaje' => 'Cliente no válido', 'campo' => 'id_cliente'], 400);
}

if (!is_numeric($datos['monto']) || (float) $datos['monto'] <= 0) {
    responder(['status' => 'error', 'mensaje' => 'El monto debe ser mayor que 0', 'campo' => 'monto'], 400);
}

if (!is_numeric($datos['tasa_interes']) || (float) $datos['tasa_interes'] < 0) {
    responder(['status' => 'error', 'mensaje' => 'La tasa de interés no es válida', 'campo' => 'tasa_interes'], 400);
}

if (!in_array($datos['tipo_interes'], ['Simple', 'Compuesto'], true)) {
    responder(['status' => 'error', 'mensaje' => 'Tipo de interés no válido', 'campo' => 'tipo_interes'], 400);
}

if (!in_array($datos['modalidad_pagos'], ['Diario', 'Semanal', 'Quincenal', 'Mensual'], true)) {
    responder(['status' => 'error', 'mensaje' => 'Modalidad de pago no válida', 'campo' => 'modalidad_pagos'], 400);
}

if (!ctype_digit((string) $datos['plazo']) || (int) $datos['plazo'] <= 0) {
    responder(['status' => 'error', 'mensaje' => 'El plazo debe ser un número entero mayor que 0', 'campo' => 'plazo'], 400);
}

if (!is_numeric($datos['cuota_monto']) || (float) $datos['cuota_monto'] <= 0) {
    responder(['status' => 'error', 'mensaje' => 'La cuota calculada no es válida', 'campo' => 'cuota_monto'], 400);
}

$fechaInicio = DateTime::createFromFormat('Y-m-d', (string) $datos['fecha_inicio']);
if ($fechaInicio === false) {
    responder(['status' => 'error', 'mensaje' => 'Fecha de inicio no válida', 'campo' => 'fecha_inicio'], 400);
}

$idCliente      = (int) $datos['id_cliente'];
$monto          = (float) $datos['monto'];
$tasaInteres    = (float) $datos['tasa_interes'];
$plazo          = (int) $datos['plazo'];
$cuotaMonto     = (float) $datos['cuota_monto'];
$moraPorcentaje = is_numeric($datos['mora_porcentaje'] ?? null) ? (float) $datos['mora_porcentaje'] : 0.0;
$fechaFin       = !empty($datos['fecha_fin']) ? (string) $datos['fecha_fin'] : null;
$fechaRegistro  = date('Y-m-d H:i:s');

// El saldo pendiente inicial = monto total a pagar (cuota * plazo)
$saldoPendiente = round($cuotaMonto * $plazo, 2);

try {
    // Verificar que el cliente exista antes de crear el préstamo
    $checkCliente = $conn->prepare('SELECT 1 FROM clientes WHERE id_cliente = ?');
    $checkCliente->bind_param('i', $idCliente);
    $checkCliente->execute();
    $existeCliente = $checkCliente->get_result()->num_rows > 0;
    $checkCliente->close();

    if (!$existeCliente) {
        responder(['status' => 'error', 'mensaje' => 'El cliente seleccionado no existe', 'campo' => 'id_cliente'], 404);
    }

    $sql = "INSERT INTO prestamos (
                id_cliente, monto, tasa_interes, tipo_interes,
                modalidad_pagos, plazo, cuota_monto, saldo_pendiente,
                mora_porcentaje, fecha_inicio, fecha_fin,
                fecha_registro, estado
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Activo')";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        'idssssdddsss',
        $idCliente,
        $monto,
        $tasaInteres,
        $datos['tipo_interes'],
        $datos['modalidad_pagos'],
        $plazo,
        $cuotaMonto,
        $saldoPendiente,
        $moraPorcentaje,
        $datos['fecha_inicio'],
        $fechaFin,
        $fechaRegistro
    );
    $stmt->execute();

    $idPrestamo = $conn->insert_id;
    $stmt->close();

    responder(['status' => 'success', 'id_prestamo' => $idPrestamo]);
} catch (mysqli_sql_exception $e) {
    error_log('Error al guardar préstamo: ' . $e->getMessage());
    responder(['status' => 'error', 'mensaje' => 'Error interno al guardar el préstamo'], 500);
} finally {
    $conn->close();
}