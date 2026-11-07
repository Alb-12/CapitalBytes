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

if (empty($datos['id_cliente']) || !ctype_digit((string) $datos['id_cliente'])) {
    responder(['status' => 'error', 'mensaje' => 'Selecciona un cliente', 'campo' => 'id_cliente'], 400);
}
$idCliente = (int) $datos['id_cliente'];

if (!is_numeric($datos['monto_capital'] ?? null) || (float) $datos['monto_capital'] <= 0) {
    responder(['status' => 'error', 'mensaje' => 'El monto debe ser mayor que 0', 'campo' => 'monto_capital'], 400);
}
$montoCapital = (float) $datos['monto_capital'];

// El rédito por cada 1000 tiene un valor por defecto (200) pero se puede
// ajustar por préstamo, tal como lo dejamos en el formulario.
$tasaRedito = is_numeric($datos['tasa_redito'] ?? null) ? (float) $datos['tasa_redito'] : 200.0;
if ($tasaRedito < 0) {
    responder(['status' => 'error', 'mensaje' => 'La tasa de rédito no puede ser negativa', 'campo' => 'tasa_redito'], 400);
}

$modalidadesValidas = ['Diario', 'Semanal', 'Quincenal', 'Mensual'];
if (!in_array($datos['modalidad_pagos'] ?? '', $modalidadesValidas, true)) {
    responder(['status' => 'error', 'mensaje' => 'Modalidad de pago no válida', 'campo' => 'modalidad_pagos'], 400);
}
$modalidad = $datos['modalidad_pagos'];

$fechaInicioObj = DateTime::createFromFormat('Y-m-d', (string) ($datos['fecha_inicio'] ?? ''));
if ($fechaInicioObj === false) {
    responder(['status' => 'error', 'mensaje' => 'Fecha de inicio no válida', 'campo' => 'fecha_inicio'], 400);
}
$fechaInicio = $datos['fecha_inicio'];

$idUsuario = (int) $_SESSION['id_usuario'];
$fechaRegistro = date('Y-m-d H:i:s');

try {
    // Verificar que el cliente exista realmente
    $checkCliente = $conn->prepare('SELECT 1 FROM clientes WHERE id_cliente = ?');
    $checkCliente->bind_param('i', $idCliente);
    $checkCliente->execute();
    $existeCliente = $checkCliente->get_result()->num_rows > 0;
    $checkCliente->close();

    if (!$existeCliente) {
        responder(['status' => 'error', 'mensaje' => 'El cliente seleccionado no existe', 'campo' => 'id_cliente'], 404);
    }

    // saldo_capital arranca igual al monto_capital; baja con cada abono
    $stmt = $conn->prepare(
        "INSERT INTO prestamos_redito
            (id_cliente, monto_capital, saldo_capital, tasa_redito, modalidad_pagos, estado, fecha_inicio, fecha_registro, registrado_por)
         VALUES (?, ?, ?, ?, ?, 'Activo', ?, ?, ?)"
    );
    $stmt->bind_param(
        'idddsssi',
        $idCliente,
        $montoCapital,
        $montoCapital, // saldo_capital inicial = monto_capital
        $tasaRedito,
        $modalidad,
        $fechaInicio,
        $fechaRegistro,
        $idUsuario
    );
    $stmt->execute();
    $idPrestamoRedito = $conn->insert_id;
    $stmt->close();

    responder(['status' => 'success', 'id_prestamo_redito' => $idPrestamoRedito]);
} catch (mysqli_sql_exception $e) {
    error_log('Error al guardar préstamo a rédito: ' . $e->getMessage());
    responder(['status' => 'error', 'mensaje' => 'Error interno al guardar el préstamo'], 500);
} finally {
    $conn->close();
}
