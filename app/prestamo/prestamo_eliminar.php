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

if (empty($datos['id_prestamo']) || !ctype_digit((string) $datos['id_prestamo'])) {
    responder(['status' => 'error', 'mensaje' => 'ID no proporcionado o inválido'], 400);
}

$idPrestamo = (int) $datos['id_prestamo'];

try {
    // Verificar que el préstamo no tenga pagos registrados antes de eliminar
    $check = $conn->prepare('SELECT COUNT(*) AS total FROM pagos WHERE id_prestamo = ?');
    $check->bind_param('i', $idPrestamo);
    $check->execute();
    $pagosRegistrados = (int) $check->get_result()->fetch_assoc()['total'];
    $check->close();

    if ($pagosRegistrados > 0) {
        responder(['status' => 'error', 'mensaje' => 'No se puede eliminar: el préstamo tiene pagos registrados'], 409);
    }

    $stmt = $conn->prepare('DELETE FROM prestamos WHERE id_prestamo = ?');
    $stmt->bind_param('i', $idPrestamo);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        $stmt->close();
        responder(['status' => 'error', 'mensaje' => 'Préstamo no encontrado'], 404);
    }

    $stmt->close();
    responder(['status' => 'success']);
} catch (mysqli_sql_exception $e) {
    error_log('Error al eliminar préstamo: ' . $e->getMessage());
    responder(['status' => 'error', 'mensaje' => 'Error interno al eliminar el préstamo'], 500);
} finally {
    $conn->close();
}