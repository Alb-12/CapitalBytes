<?php

declare(strict_types=1);

session_start();
require __DIR__ . '/../db/db.php';

header('Content-Type: application/json; charset=utf-8');

function responder(array $payload, int $httpCode = 200): never
{
    http_response_code($httpCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (($_SESSION['rol'] ?? '') !== 'Administrador') {
    responder(['status' => 'error', 'mensaje' => 'Sin permisos'], 403);
}

$datos = json_decode(file_get_contents('php://input'), true);

if (!is_array($datos)) {
    responder(['status' => 'error', 'mensaje' => 'No se recibieron datos válidos'], 400);
}

if (empty($datos['id_prestamo']) || !ctype_digit((string) $datos['id_prestamo'])) {
    responder(['status' => 'error', 'mensaje' => 'ID del préstamo no válido', 'campo' => 'id_prestamo'], 400);
}

if (empty($datos['estado'])) {
    responder(['status' => 'error', 'mensaje' => 'El estado es requerido', 'campo' => 'estado'], 400);
}

$estadosPermitidos = ['Activo', 'Pagado', 'Mora', 'Cancelado'];
if (!in_array($datos['estado'], $estadosPermitidos, true)) {
    responder(['status' => 'error', 'mensaje' => 'Estado no válido', 'campo' => 'estado'], 400);
}

$idPrestamo = (int) $datos['id_prestamo'];

try {
    $stmt = $conn->prepare('UPDATE prestamos SET estado = ? WHERE id_prestamo = ?');
    $stmt->bind_param('si', $datos['estado'], $idPrestamo);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        $check = $conn->prepare('SELECT 1 FROM prestamos WHERE id_prestamo = ?');
        $check->bind_param('i', $idPrestamo);
        $check->execute();
        $existe = $check->get_result()->num_rows > 0;
        $check->close();

        if (!$existe) {
            $stmt->close();
            responder(['status' => 'error', 'mensaje' => 'Préstamo no encontrado'], 404);
        }
        // Existe pero el estado ya era el mismo: lo tratamos como éxito.
    }

    $stmt->close();
    responder(['status' => 'success']);
} catch (mysqli_sql_exception $e) {
    error_log('Error al actualizar préstamo: ' . $e->getMessage());
    responder(['status' => 'error', 'mensaje' => 'Error interno al actualizar el préstamo'], 500);
} finally {
    $conn->close();
}
