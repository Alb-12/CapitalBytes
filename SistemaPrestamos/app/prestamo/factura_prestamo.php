<?php

declare(strict_types=1);

session_start();
require __DIR__ . '/../db/db.php';
/** @var mysqli $conn */

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Solo usuarios con sesión iniciada pueden ver facturas.
if (empty($_SESSION['id_usuario'])) {
    header('Location: ../login/login.php');
    exit;
}

$id = $_GET['id'] ?? '';
if (!ctype_digit((string) $id)) {
    http_response_code(400);
    exit('Préstamo no válido.');
}
$idPrestamo = (int) $id;

try {
    $stmt = $conn->prepare(
        'SELECT p.*, c.nombre, c.apellido, c.cedula, c.telefono
         FROM prestamos p
         JOIN clientes c ON p.id_cliente = c.id_cliente
         WHERE p.id_prestamo = ?
         LIMIT 1'
    );
    $stmt->bind_param('i', $idPrestamo);
    $stmt->execute();
    $p = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} catch (mysqli_sql_exception $ex) {
    error_log('Error al cargar la factura: ' . $ex->getMessage());
    http_response_code(500);
    exit('No se pudo cargar la factura. Intenta más tarde.');
}

if ($p === null) {
    http_response_code(404);
    exit('Préstamo no encontrado.');
}

$e      = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$dinero = static fn($v): string => '$' . number_format((float) $v, 2);
$numeroFactura = 'FAC-C-' . str_pad((string) $idPrestamo, 6, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Factura <?= $e($numeroFactura) ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 30px; color: #222; }
        .factura { max-width: 720px; margin: 0 auto; border: 1px solid #999; padding: 24px; }
        h1 { margin: 0; font-size: 22px; }
        .cabecera { display: flex; justify-content: space-between; border-bottom: 2px solid #2F5496; padding-bottom: 10px; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { text-align: left; padding: 6px 8px; border-bottom: 1px solid #ddd; font-size: 14px; }
        th { width: 40%; background: #f2f2f2; }
        .pie { margin-top: 24px; font-size: 12px; color: #555; text-align: center; }
        .no-imprimir { text-align: center; margin-top: 20px; }
        @media print { .no-imprimir { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
<div class="factura">
    <div class="cabecera">
        <div>
            <h1>Presta_APP</h1>
            <div>Factura de préstamo a crédito</div>
        </div>
        <div style="text-align:right;">
            <strong><?= $e($numeroFactura) ?></strong><br>
            Fecha de emisión: <?= $e(date('d/m/Y')) ?>
        </div>
    </div>

    <h3>Datos del cliente</h3>
    <table>
        <tr><th>Nombre</th><td><?= $e($p['nombre'] . ' ' . $p['apellido']) ?></td></tr>
        <tr><th>Cédula</th><td><?= $e($p['cedula']) ?></td></tr>
        <tr><th>Teléfono</th><td><?= $e($p['telefono'] ?? '') ?></td></tr>
    </table>

    <h3>Detalle del préstamo</h3>
    <table>
        <tr><th>Monto prestado</th><td><?= $e($dinero($p['monto'])) ?></td></tr>
        <tr><th>Tasa de interés</th><td><?= $e($p['tasa_interes']) ?>% (<?= $e($p['tipo_interes']) ?>)</td></tr>
        <tr><th>Modalidad de pagos</th><td><?= $e($p['modalidad_pagos']) ?></td></tr>
        <tr><th>Plazo</th><td><?= (int) $p['plazo'] ?> cuotas</td></tr>
        <tr><th>Monto de la cuota</th><td><?= $e($dinero($p['cuota_monto'])) ?></td></tr>
        <tr><th>Saldo pendiente</th><td><?= $e($dinero($p['saldo_pendiente'])) ?></td></tr>
        <tr><th>Porcentaje de mora</th><td><?= $e($p['mora_porcentaje']) ?>%</td></tr>
        <tr><th>Fecha de inicio</th><td><?= $e($p['fecha_inicio']) ?></td></tr>
        <tr><th>Fecha de fin</th><td><?= $e($p['fecha_fin']) ?></td></tr>
        <tr><th>Estado</th><td><?= $e($p['estado']) ?></td></tr>
    </table>

    <div class="pie">Gracias por su confianza. Conserve esta factura como comprobante.</div>
</div>

<div class="no-imprimir">
    <button type="button" onclick="window.print()">Imprimir factura</button>
</div>
</body>
</html>
