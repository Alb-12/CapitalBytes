<?php

declare(strict_types=1);

session_start();
include(__DIR__ . '/../db/db.php');
require __DIR__ . '/administradores_helper.php';

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

// Evitar que un administrador se elimine a sí mismo por accidente y se
// quede fuera del sistema sin poder revertirlo.
if ($idUsuario === (int) ($_SESSION['id_usuario'] ?? 0)) {
    responder(['status' => 'error', 'mensaje' => 'No puedes eliminar tu propio usuario'], 400);
}

try {
    $checkUsuario = $conn->prepare('SELECT id_rol, estado FROM usuarios WHERE id_usuario = ?');
    $checkUsuario->bind_param('i', $idUsuario);
    $checkUsuario->execute();
    $usuario = $checkUsuario->get_result()->fetch_assoc();
    $checkUsuario->close();

    if ($usuario === null) {
        responder(['status' => 'error', 'mensaje' => 'Usuario no encontrado'], 404);
    }

    $idRolAdmin = obtenerIdRolAdministrador($conn);
    if ($idRolAdmin !== null && (int) $usuario['id_rol'] === $idRolAdmin && $usuario['estado'] === 'Activo') {
        $otrosAdminsActivos = contarAdministradores($conn, $idRolAdmin, soloActivos: true, excluirId: $idUsuario);
        if ($otrosAdminsActivos === 0) {
            responder(['status' => 'error', 'mensaje' => 'No puedes eliminar al único administrador activo del sistema'], 409);
        }
    }

    $stmt = $conn->prepare('DELETE FROM usuarios WHERE id_usuario = ?');
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        $stmt->close();
        responder(['status' => 'error', 'mensaje' => 'Usuario no encontrado'], 404);
    }

    $stmt->close();
    responder(['status' => 'success']);
} catch (mysqli_sql_exception $e) {
    error_log('Error al eliminar usuario: ' . $e->getMessage());
    responder(['status' => 'error', 'mensaje' => 'Error interno al eliminar el usuario'], 500);
} finally {
    $conn->close();
}