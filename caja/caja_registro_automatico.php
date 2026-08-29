<?php

declare(strict_types=1);

/**
 * Registra automáticamente en caja cuando se recibe un pago.
 * Llamar desde pago_save.php después de insertar el pago.
 *
 * @param mysqli $conn
 * @param int    $idPago
 * @param float  $monto
 * @param int    $idUsuario
 */
function registrarPagoEnCaja(mysqli $conn, int $idPago, float $monto, int $idUsuario): bool
{
    $fecha       = date('Y-m-d H:i:s');
    $descripcion = "Pago recibido — ID Pago #$idPago";

    $stmt = $conn->prepare(
        "INSERT INTO caja (tipo_movimiento, origen, id_referencia, monto, descripcion, fecha, id_usuario)
         VALUES ('Entrada', 'Pago', ?, ?, ?, ?, ?)"
    );
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('idssi', $idPago, $monto, $descripcion, $fecha, $idUsuario);
    $resultado = $stmt->execute();
    $stmt->close();

    return $resultado;
}