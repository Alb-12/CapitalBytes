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

// Solo administrador
if (!in_array($_SESSION['rol'] ?? '', ['Administrador', 'Supervisor'], true)) {
    responder(['status' => 'error', 'mensaje' => 'Sin permisos'], 403);
}

$datos = json_decode(file_get_contents('php://input'), true);

if (!is_array($datos)) {
    responder(['status' => 'error', 'mensaje' => 'No se recibieron datos válidos'], 400);
}

if (empty($datos['tipo_movimiento'])) {
    responder(['status' => 'error', 'mensaje' => 'El tipo es requerido', 'campo' => 'tipo_movimiento'], 400);
}
if (!in_array($datos['tipo_movimiento'], ['Entrada', 'Salida'], true)) {
    responder(['status' => 'error', 'mensaje' => 'Tipo de movimiento no válido', 'campo' => 'tipo_movimiento'], 400);
}

if (empty($datos['origen'])) {
    responder(['status' => 'error', 'mensaje' => 'El origen es requerido', 'campo' => 'origen'], 400);
}
if (!in_array($datos['origen'], ['Gasto', 'Ajuste'], true)) {
    responder(['status' => 'error', 'mensaje' => 'Origen no válido', 'campo' => 'origen'], 400);
}

if (!isset($datos['monto']) || !is_numeric($datos['monto']) || (float) $datos['monto'] <= 0) {
    responder(['status' => 'error', 'mensaje' => 'Ingresa un monto válido', 'campo' => 'monto'], 400);
}

$descripcion = trim((string) ($datos['descripcion'] ?? ''));
if ($descripcion === '') {
    responder(['status' => 'error', 'mensaje' => 'La descripción es requerida', 'campo' => 'descripcion'], 400);
}

$tipoMovimiento = $datos['tipo_movimiento'];
$origen         = $datos['origen'];
$monto          = (float) $datos['monto'];
$idUsuario      = (int) ($_SESSION['id_usuario'] ?? 0);
$fecha          = date('Y-m-d H:i:s');

try {
    $stmt = $conn->prepare(
        "INSERT INTO caja (tipo_movimiento, origen, id_referencia, monto, descripcion, fecha, id_usuario)
         VALUES (?, ?, NULL, ?, ?, ?, ?)"
    );
    // Antes: "ssdss i" (con un espacio de más) → cadena de tipos inválida.
    // Correcta: 6 letras para 6 parámetros (tipo, origen, monto, descripcion, fecha, id_usuario).
    $stmt->bind_param('ssdssi', $tipoMovimiento, $origen, $monto, $descripcion, $fecha, $idUsuario);
    $stmt->execute();
    $stmt->close();

    responder(['status' => 'success']);
} catch (mysqli_sql_exception $e) {
    error_log('Error al registrar transacción de caja: ' . $e->getMessage());
    responder(['status' => 'error', 'mensaje' => 'Error interno al registrar la transacción'], 500);
} finally {
    $conn->close();
}