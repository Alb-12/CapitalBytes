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

if (empty($datos['desde']) || empty($datos['hasta'])) {
    responder(['status' => 'error', 'mensaje' => 'Fechas requeridas'], 400);
}

$fechaDesde = DateTime::createFromFormat('Y-m-d', (string) $datos['desde']);
$fechaHasta = DateTime::createFromFormat('Y-m-d', (string) $datos['hasta']);

if ($fechaDesde === false || $fechaHasta === false) {
    responder(['status' => 'error', 'mensaje' => 'Formato de fecha no válido'], 400);
}

if ($fechaDesde > $fechaHasta) {
    responder(['status' => 'error', 'mensaje' => 'La fecha "desde" no puede ser mayor a "hasta"'], 400);
}

$desde = $datos['desde'] . ' 00:00:00';
$hasta = $datos['hasta'] . ' 23:59:59';

try {
    $sql = "SELECT
        COALESCE(SUM(CASE WHEN tipo_movimiento='Entrada' AND origen='Prestamo' THEN monto ELSE 0 END),0) AS entradas_prestamo,
        COALESCE(SUM(CASE WHEN tipo_movimiento='Entrada' AND origen='Pago'     THEN monto ELSE 0 END),0) AS entradas_pago,
        COALESCE(SUM(CASE WHEN tipo_movimiento='Entrada' AND origen='Ajuste'   THEN monto ELSE 0 END),0) AS entradas_ajuste,
        COALESCE(SUM(CASE WHEN tipo_movimiento='Salida'  AND origen='Gasto'    THEN monto ELSE 0 END),0) AS salidas_gasto,
        COALESCE(SUM(CASE WHEN tipo_movimiento='Salida'  AND origen='Ajuste'   THEN monto ELSE 0 END),0) AS salidas_ajuste,
        COALESCE(SUM(CASE WHEN tipo_movimiento='Entrada' THEN monto ELSE 0 END),0) AS total_entradas,
        COALESCE(SUM(CASE WHEN tipo_movimiento='Salida'  THEN monto ELSE 0 END),0) AS total_salidas,
        COUNT(*) AS total_movimientos
    FROM caja
    WHERE fecha BETWEEN ? AND ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $desde, $hasta);
    $stmt->execute();
    $arqueo = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    responder(['status' => 'success', 'arqueo' => $arqueo]);
} catch (mysqli_sql_exception $e) {
    error_log('Error al generar arqueo de caja: ' . $e->getMessage());
    responder(['status' => 'error', 'mensaje' => 'Error interno al generar el arqueo'], 500);
} finally {
    $conn->close();
}