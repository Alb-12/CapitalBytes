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

if (!in_array($_SESSION['rol'] ?? '', ['Administrador', 'Supervisor'], true)) {
    responder(['status' => 'error', 'mensaje' => 'Sin permisos'], 403);
}

$datos  = json_decode(file_get_contents('php://input'), true) ?? [];
$tipo   = $datos['tipo']   ?? '';
$desde  = $datos['desde']  ?? date('Y-m-01');
$hasta  = $datos['hasta']  ?? date('Y-m-d');
$estado = $datos['estado'] ?? '';

$desdeDt = $desde . ' 00:00:00';
$hastaDt = $hasta . ' 23:59:59';

$tiposPermitidos = ['prestamos', 'pagos', 'clientes', 'caja', 'cartera', 'ingresos_mensuales'];
if (!in_array($tipo, $tiposPermitidos, true)) {
    responder(['status' => 'error', 'mensaje' => 'Tipo de reporte no válido'], 400);
}

try {
    switch ($tipo) {

        // ── Reporte de préstamos ────────────────────────────────────────
        case 'prestamos':
            $where  = 'WHERE p.fecha_registro BETWEEN ? AND ?';
            $params = [$desdeDt, $hastaDt];
            $types  = 'ss';

            if ($estado) {
                $where   .= ' AND p.estado = ?';
                $params[] = $estado;
                $types   .= 's';
            }

            $sql = "SELECT p.id_prestamo,
                           CONCAT(c.nombre,' ',c.apellido) AS cliente,
                           c.cedula,
                           p.monto, p.tasa_interes, p.tipo_interes,
                           p.modalidad_pagos, p.plazo, p.cuota_monto,
                           p.saldo_pendiente, p.mora_porcentaje,
                           p.fecha_inicio, p.fecha_fin, p.estado
                    FROM prestamos p
                    JOIN clientes c ON p.id_cliente = c.id_cliente
                    $where
                    ORDER BY p.fecha_registro DESC";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            $totales = array_reduce($filas, function ($carry, $f) {
                $carry['monto'] += (float) $f['monto'];
                $carry['saldo'] += (float) $f['saldo_pendiente'];
                $carry['count']++;
                return $carry;
            }, ['monto' => 0.0, 'saldo' => 0.0, 'count' => 0]);

            $resultado = [
                'status'   => 'success',
                'titulo'   => 'Reporte de Préstamos',
                'datos'    => $filas,
                'resumen'  => [
                    'Total préstamos' => $totales['count'],
                    'Monto total'     => '$' . number_format($totales['monto'], 2),
                    'Saldo pendiente' => '$' . number_format($totales['saldo'], 2),
                ],
                'columnas' => [
                    ['key' => 'id_prestamo', 'label' => 'ID'],
                    ['key' => 'cliente', 'label' => 'Cliente'],
                    ['key' => 'cedula', 'label' => 'Cédula'],
                    ['key' => 'monto', 'label' => 'Monto'],
                    ['key' => 'tasa_interes', 'label' => 'Tasa %'],
                    ['key' => 'tipo_interes', 'label' => 'Tipo'],
                    ['key' => 'modalidad_pagos', 'label' => 'Modalidad'],
                    ['key' => 'plazo', 'label' => 'Plazo'],
                    ['key' => 'cuota_monto', 'label' => 'Cuota'],
                    ['key' => 'saldo_pendiente', 'label' => 'Saldo'],
                    ['key' => 'fecha_inicio', 'label' => 'Inicio'],
                    ['key' => 'fecha_fin', 'label' => 'Fin'],
                    ['key' => 'estado', 'label' => 'Estado'],
                ],
            ];
            break;

        // ── Reporte de pagos ────────────────────────────────────────────
        case 'pagos':
            $sql = 'SELECT pg.id_pagos,
                           CONCAT(c.nombre,\' \',c.apellido) AS cliente,
                           pg.fecha_pago, pg.monto_capital,
                           pg.monto_interes, pg.monto_mora,
                           pg.monto_pagado, pg.saldo_restante,
                           u.nombre_usuario AS registrado_por
                    FROM pagos pg
                    JOIN prestamos p ON pg.id_prestamo = p.id_prestamo
                    JOIN clientes c  ON p.id_cliente    = c.id_cliente
                    JOIN usuarios u  ON pg.registrado_por = u.id_usuario
                    WHERE pg.fecha_pago BETWEEN ? AND ?
                    ORDER BY pg.fecha_pago DESC';

            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ss', $desdeDt, $hastaDt);
            $stmt->execute();
            $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            $totalCobrado = array_sum(array_column($filas, 'monto_pagado'));
            $totalMora    = array_sum(array_column($filas, 'monto_mora'));

            $resultado = [
                'status'   => 'success',
                'titulo'   => 'Reporte de Pagos',
                'datos'    => $filas,
                'resumen'  => [
                    'Total pagos'   => count($filas),
                    'Total cobrado' => '$' . number_format($totalCobrado, 2),
                    'Total mora'    => '$' . number_format($totalMora, 2),
                ],
                'columnas' => [
                    ['key' => 'id_pagos', 'label' => 'ID'],
                    ['key' => 'cliente', 'label' => 'Cliente'],
                    ['key' => 'fecha_pago', 'label' => 'Fecha'],
                    ['key' => 'monto_capital', 'label' => 'Capital'],
                    ['key' => 'monto_interes', 'label' => 'Interés'],
                    ['key' => 'monto_mora', 'label' => 'Mora'],
                    ['key' => 'monto_pagado', 'label' => 'Total Pagado'],
                    ['key' => 'saldo_restante', 'label' => 'Saldo Restante'],
                    ['key' => 'registrado_por', 'label' => 'Registrado por'],
                ],
            ];
            break;

        // ── Reporte de clientes ─────────────────────────────────────────
        case 'clientes':
            $sql = "SELECT c.cedula,
                           CONCAT(c.nombre,' ',c.apellido) AS cliente,
                           c.telefono, c.correo, c.direccion,
                           COUNT(p.id_prestamo)               AS total_prestamos,
                           COALESCE(SUM(p.monto),0)           AS monto_total,
                           COALESCE(SUM(p.saldo_pendiente),0) AS saldo_total
                    FROM clientes c
                    LEFT JOIN prestamos p ON c.id_cliente = p.id_cliente
                    GROUP BY c.id_cliente
                    ORDER BY c.nombre";

            $filas = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

            $resultado = [
                'status'   => 'success',
                'titulo'   => 'Reporte de Clientes',
                'datos'    => $filas,
                'resumen'  => [
                    'Total clientes' => count($filas),
                    'Con préstamos'  => count(array_filter($filas, fn ($f) => $f['total_prestamos'] > 0)),
                ],
                'columnas' => [
                    ['key' => 'cedula', 'label' => 'Cédula'],
                    ['key' => 'cliente', 'label' => 'Nombre'],
                    ['key' => 'telefono', 'label' => 'Teléfono'],
                    ['key' => 'correo', 'label' => 'Correo'],
                    ['key' => 'total_prestamos', 'label' => 'Préstamos'],
                    ['key' => 'monto_total', 'label' => 'Monto Total'],
                    ['key' => 'saldo_total', 'label' => 'Saldo Total'],
                ],
            ];
            break;

        // ── Reporte de caja ─────────────────────────────────────────────
        case 'caja':
            $sql = 'SELECT c.fecha, c.tipo_movimiento, c.origen,
                           c.monto, c.descripcion,
                           u.nombre_usuario AS usuario
                    FROM caja c
                    JOIN usuarios u ON c.id_usuario = u.id_usuario
                    WHERE c.fecha BETWEEN ? AND ?
                    ORDER BY c.fecha DESC';

            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ss', $desdeDt, $hastaDt);
            $stmt->execute();
            $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            $entradas = array_sum(array_map(fn ($f) => $f['tipo_movimiento'] === 'Entrada' ? $f['monto'] : 0, $filas));
            $salidas  = array_sum(array_map(fn ($f) => $f['tipo_movimiento'] === 'Salida' ? $f['monto'] : 0, $filas));

            $resultado = [
                'status'   => 'success',
                'titulo'   => 'Reporte de Caja',
                'datos'    => $filas,
                'resumen'  => [
                    'Entradas' => '$' . number_format($entradas, 2),
                    'Salidas'  => '$' . number_format($salidas, 2),
                    'Neto'     => '$' . number_format($entradas - $salidas, 2),
                ],
                'columnas' => [
                    ['key' => 'fecha', 'label' => 'Fecha'],
                    ['key' => 'tipo_movimiento', 'label' => 'Tipo'],
                    ['key' => 'origen', 'label' => 'Origen'],
                    ['key' => 'monto', 'label' => 'Monto'],
                    ['key' => 'descripcion', 'label' => 'Descripción'],
                    ['key' => 'usuario', 'label' => 'Usuario'],
                ],
            ];
            break;

        // ── Cartera en riesgo ───────────────────────────────────────────
        case 'cartera':
            $sql = "SELECT p.id_prestamo,
                           CONCAT(c.nombre,' ',c.apellido) AS cliente,
                           c.cedula, c.telefono,
                           p.monto, p.saldo_pendiente,
                           p.mora_porcentaje, p.cuota_monto,
                           p.fecha_inicio, p.fecha_fin,
                           DATEDIFF(CURDATE(), p.fecha_fin) AS dias_vencido
                    FROM prestamos p
                    JOIN clientes c ON p.id_cliente = c.id_cliente
                    WHERE p.estado = 'Mora'
                    ORDER BY dias_vencido DESC";

            $filas = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

            $saldoRiesgo = array_sum(array_column($filas, 'saldo_pendiente'));

            $resultado = [
                'status'   => 'success',
                'titulo'   => 'Cartera en Riesgo',
                'datos'    => $filas,
                'resumen'  => [
                    'Préstamos en mora' => count($filas),
                    'Saldo en riesgo'   => '$' . number_format($saldoRiesgo, 2),
                ],
                'columnas' => [
                    ['key' => 'id_prestamo', 'label' => 'ID'],
                    ['key' => 'cliente', 'label' => 'Cliente'],
                    ['key' => 'cedula', 'label' => 'Cédula'],
                    ['key' => 'telefono', 'label' => 'Teléfono'],
                    ['key' => 'monto', 'label' => 'Monto'],
                    ['key' => 'saldo_pendiente', 'label' => 'Saldo'],
                    ['key' => 'mora_porcentaje', 'label' => 'Mora %'],
                    ['key' => 'fecha_fin', 'label' => 'Venció'],
                    ['key' => 'dias_vencido', 'label' => 'Días vencido'],
                ],
            ];
            break;

        // ── Ingresos mensuales (nuevo, para la pestaña del mismo nombre) ─
        case 'ingresos_mensuales':
            $sql = "SELECT DATE_FORMAT(fecha_pago, '%Y-%m') AS mes,
                           COUNT(*)                     AS total_pagos,
                           COALESCE(SUM(monto_capital),0)  AS capital,
                           COALESCE(SUM(monto_interes),0)  AS interes,
                           COALESCE(SUM(monto_mora),0)     AS mora,
                           COALESCE(SUM(monto_pagado),0)   AS total
                    FROM pagos
                    GROUP BY mes
                    ORDER BY mes DESC
                    LIMIT 12";

            $filas = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

            $totalGeneral = array_sum(array_column($filas, 'total'));

            $resultado = [
                'status'   => 'success',
                'titulo'   => 'Ingresos Mensuales',
                'datos'    => $filas,
                'resumen'  => [
                    'Meses con ingresos' => count($filas),
                    'Total recaudado'    => '$' . number_format($totalGeneral, 2),
                ],
                'columnas' => [
                    ['key' => 'mes', 'label' => 'Mes'],
                    ['key' => 'total_pagos', 'label' => 'Pagos'],
                    ['key' => 'capital', 'label' => 'Capital'],
                    ['key' => 'interes', 'label' => 'Interés'],
                    ['key' => 'mora', 'label' => 'Mora'],
                    ['key' => 'total', 'label' => 'Total'],
                ],
            ];
            break;

        default:
            $resultado = ['status' => 'error', 'mensaje' => 'Tipo no válido'];
    }

    responder($resultado);
} catch (mysqli_sql_exception $e) {
    error_log('Error al generar reporte (' . $tipo . '): ' . $e->getMessage());
    responder(['status' => 'error', 'mensaje' => 'Error interno al generar el reporte'], 500);
} finally {
    $conn->close();
}