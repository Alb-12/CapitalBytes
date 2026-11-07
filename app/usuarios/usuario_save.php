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

if (!is_array($datos)) {
    responder(['status' => 'error', 'mensaje' => 'No se recibieron datos válidos'], 400);
}

$nombreUsuario = trim((string) ($datos['nombre_usuario'] ?? ''));
if ($nombreUsuario === '') {
    responder(['status' => 'error', 'mensaje' => 'El nombre de usuario es requerido', 'campo' => 'nombre_usuario'], 400);
}

$contrasena = (string) ($datos['contrasena'] ?? '');
if ($contrasena === '') {
    responder(['status' => 'error', 'mensaje' => 'La contraseña es requerida', 'campo' => 'contrasena'], 400);
}
if (strlen($contrasena) < 8) {
    responder(['status' => 'error', 'mensaje' => 'La contraseña debe tener al menos 8 caracteres', 'campo' => 'contrasena'], 400);
}

if (empty($datos['id_rol']) || !ctype_digit((string) $datos['id_rol'])) {
    responder(['status' => 'error', 'mensaje' => 'Rol no válido', 'campo' => 'id_rol'], 400);
}

$estado = $datos['estado'] ?? '';
if (!in_array($estado, ['Activo', 'Inactivo'], true)) {
    responder(['status' => 'error', 'mensaje' => 'Estado no válido', 'campo' => 'estado'], 400);
}

$idRol = (int) $datos['id_rol'];
$hash  = password_hash($contrasena, PASSWORD_DEFAULT);
$ahora = date('Y-m-d H:i:s');

try {
    // Verificar que el rol exista realmente
    $checkRol = $conn->prepare('SELECT 1 FROM roles WHERE id_rol = ?');
    $checkRol->bind_param('i', $idRol);
    $checkRol->execute();
    $existeRol = $checkRol->get_result()->num_rows > 0;
    $checkRol->close();

    if (!$existeRol) {
        responder(['status' => 'error', 'mensaje' => 'El rol seleccionado no existe', 'campo' => 'id_rol'], 404);
    }

    // Regla de negocio: máximo 2 administradores en todo el sistema.
    $idRolAdmin = obtenerIdRolAdministrador($conn);
    if ($idRolAdmin !== null && $idRol === $idRolAdmin) {
        $totalAdmins = contarAdministradores($conn, $idRolAdmin);
        if ($totalAdmins >= MAX_ADMINISTRADORES) {
            responder([
                'status'  => 'error',
                'mensaje' => 'Ya existen ' . MAX_ADMINISTRADORES . ' administradores en el sistema. Elimina o cambia el rol de uno antes de agregar otro.',
                'campo'   => 'id_rol',
            ], 409);
        }
    }

    $stmt = $conn->prepare(
        'INSERT INTO usuarios (nombre_usuario, contrasena, id_rol, estado, intentos_fallidos, bloqueado, fecha_registro)
         VALUES (?, ?, ?, ?, 0, 0, ?)'
    );
    $stmt->bind_param('ssiss', $nombreUsuario, $hash, $idRol, $estado, $ahora);
    $stmt->execute();
    $stmt->close();

    responder(['status' => 'success']);
} catch (mysqli_sql_exception $e) {
    if ($e->getCode() === 1062) {
        responder(['status' => 'error', 'mensaje' => 'Ya existe un usuario con ese nombre', 'campo' => 'nombre_usuario'], 409);
    }
    error_log('Error al registrar usuario: ' . $e->getMessage());
    responder(['status' => 'error', 'mensaje' => 'Error interno al registrar el usuario'], 500);
} finally {
    $conn->close();
}