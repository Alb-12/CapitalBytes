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

if (($_SESSION['rol'] ?? '') !== 'Administrador') {
    responder(['status' => 'error', 'mensaje' => 'Sin permisos'], 403);
}

$datos = json_decode(file_get_contents('php://input'), true);

if (empty($datos['id_usuario']) || !ctype_digit((string) $datos['id_usuario'])) {
    responder(['status' => 'error', 'mensaje' => 'ID no proporcionado o inválido'], 400);
}
$idUsuario = (int) $datos['id_usuario'];

try {
    $stmt = $conn->prepare('UPDATE usuarios SET bloqueado = 0, intentos_fallidos = 0 WHERE id_usuario = ?');
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        $check = $conn->prepare('SELECT 1 FROM usuarios WHERE id_usuario = ?');
        $check->bind_param('i', $idUsuario);
        $check->execute();
        $existe = $check->get_result()->num_rows > 0;
        $check->close();

        if (!$existe) {
            $stmt->close();
            responder(['status' => 'error', 'mensaje' => 'Usuario no encontrado'], 404);
        }
        // Existe pero ya estaba desbloqueado: se trata igual como éxito.
    }

    $stmt->close();
    responder(['status' => 'success']);
} catch (mysqli_sql_exception $e) {
    error_log('Error al desbloquear usuario: ' . $e->getMessage());
    responder(['status' => 'error', 'mensaje' => 'Error interno al desbloquear el usuario'], 500);
} finally {
    $conn->close();
}