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

if (empty($datos['id_prestamo_redito']) || !ctype_digit((string) $datos['id_prestamo_redito'])) {
    responder(['status' => 'error', 'mensaje' => 'ID no proporcionado o inválido'], 400);
}
$idPrestamoRedito = (int) $datos['id_prestamo_redito'];

try {
    $stmt = $conn->prepare("UPDATE prestamos_redito SET estado = 'Cancelado' WHERE id_prestamo_redito = ? AND estado = 'Activo'");
    $stmt->bind_param('i', $idPrestamoRedito);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        $stmt->close();
        responder(['status' => 'error', 'mensaje' => 'El préstamo no existe o ya no está activo'], 404);
    }

    $stmt->close();
    responder(['status' => 'success']);
} catch (mysqli_sql_exception $e) {
    error_log('Error al cancelar préstamo a rédito: ' . $e->getMessage());
    responder(['status' => 'error', 'mensaje' => 'Error interno al cancelar el préstamo'], 500);
} finally {
    $conn->close();
}
