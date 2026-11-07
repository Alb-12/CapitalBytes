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

if (empty($datos['id_usuario']) || !ctype_digit((string) $datos['id_usuario'])) {
    responder(['status' => 'error', 'mensaje' => 'ID de usuario no válido'], 400);
}
$idUsuario = (int) $datos['id_usuario'];

$nombreUsuario = trim((string) ($datos['nombre_usuario'] ?? ''));
if ($nombreUsuario === '') {
    responder(['status' => 'error', 'mensaje' => 'El nombre de usuario es requerido', 'campo' => 'nombre_usuario'], 400);
}

if (empty($datos['id_rol']) || !ctype_digit((string) $datos['id_rol'])) {
    responder(['status' => 'error', 'mensaje' => 'Rol no válido', 'campo' => 'id_rol'], 400);
}
$idRol = (int) $datos['id_rol'];

$estado = $datos['estado'] ?? '';
if (!in_array($estado, ['Activo', 'Inactivo'], true)) {
    responder(['status' => 'error', 'mensaje' => 'Estado no válido', 'campo' => 'estado'], 400);
}

// La contraseña es opcional al editar: solo se valida/cambia si viene algo
$contrasena = (string) ($datos['contrasena'] ?? '');
if ($contrasena !== '' && strlen($contrasena) < 8) {
    responder(['status' => 'error', 'mensaje' => 'La contraseña debe tener al menos 8 caracteres', 'campo' => 'contrasena'], 400);
}

try {
    $checkRol = $conn->prepare('SELECT 1 FROM roles WHERE id_rol = ?');
    $checkRol->bind_param('i', $idRol);
    $checkRol->execute();
    $existeRol = $checkRol->get_result()->num_rows > 0;
    $checkRol->close();

    if (!$existeRol) {
        responder(['status' => 'error', 'mensaje' => 'El rol seleccionado no existe', 'campo' => 'id_rol'], 404);
    }

    // Estado ANTES del cambio, para saber si este usuario ya era administrador
    $checkActual = $conn->prepare('SELECT id_rol, estado FROM usuarios WHERE id_usuario = ?');
    $checkActual->bind_param('i', $idUsuario);
    $checkActual->execute();
    $actual = $checkActual->get_result()->fetch_assoc();
    $checkActual->close();

    if ($actual === null) {
        responder(['status' => 'error', 'mensaje' => 'Usuario no encontrado'], 404);
    }

    $idRolAdmin = obtenerIdRolAdministrador($conn);
    if ($idRolAdmin !== null) {
        $eraAdmin = (int) $actual['id_rol'] === $idRolAdmin;
        $seraAdmin = $idRol === $idRolAdmin;
        $seraActivo = $estado === 'Activo';

        // Regla 1: si se está PROMOVIENDO a alguien que antes no era admin,
        // respetar el máximo de 2.
        if ($seraAdmin && !$eraAdmin) {
            $totalAdmins = contarAdministradores($conn, $idRolAdmin);
            if ($totalAdmins >= MAX_ADMINISTRADORES) {
                responder([
                    'status'  => 'error',
                    'mensaje' => 'Ya existen ' . MAX_ADMINISTRADORES . ' administradores en el sistema.',
                    'campo'   => 'id_rol',
                ], 409);
            }
        }

        // Regla 2: si este usuario YA ERA un admin activo, y el cambio lo
        // dejaría de serlo (cambia de rol, o se desactiva), verificar que
        // quede al menos otro administrador activo.
        $eraAdminActivo = $eraAdmin && $actual['estado'] === 'Activo';
        $dejaDeSerAdminActivo = $eraAdminActivo && !($seraAdmin && $seraActivo);

        if ($dejaDeSerAdminActivo) {
            $otrosAdminsActivos = contarAdministradores($conn, $idRolAdmin, soloActivos: true, excluirId: $idUsuario);
            if ($otrosAdminsActivos === 0) {
                responder([
                    'status'  => 'error',
                    'mensaje' => 'No puedes quitar el rol o desactivar al único administrador activo del sistema.',
                    'campo'   => 'id_rol',
                ], 409);
            }
        }
    }

    if ($contrasena !== '') {
        $hash = password_hash($contrasena, PASSWORD_DEFAULT);
        $stmt = $conn->prepare('UPDATE usuarios SET nombre_usuario=?, contrasena=?, id_rol=?, estado=? WHERE id_usuario=?');
        $stmt->bind_param('ssisi', $nombreUsuario, $hash, $idRol, $estado, $idUsuario);
    } else {
        $stmt = $conn->prepare('UPDATE usuarios SET nombre_usuario=?, id_rol=?, estado=? WHERE id_usuario=?');
        $stmt->bind_param('sisi', $nombreUsuario, $idRol, $estado, $idUsuario);
    }
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
        // Existe pero los datos enviados eran idénticos a los guardados: se
        // trata igual como éxito.
    }

    $stmt->close();
    responder(['status' => 'success']);
} catch (mysqli_sql_exception $e) {
    if ($e->getCode() === 1062) {
        responder(['status' => 'error', 'mensaje' => 'Ya existe otro usuario con ese nombre', 'campo' => 'nombre_usuario'], 409);
    }
    error_log('Error al actualizar usuario: ' . $e->getMessage());
    responder(['status' => 'error', 'mensaje' => 'Error interno al actualizar el usuario'], 500);
} finally {
    $conn->close();
}