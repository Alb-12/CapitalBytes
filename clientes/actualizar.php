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

if (!in_array($_SESSION['rol'] ?? '', ['Administrador', 'Supervisor'], true)) {
    responder(['status' => 'error', 'mensaje' => 'Sin permisos'], 403);
}

$cliente = json_decode(file_get_contents('php://input'), true);

// 1. Verificar que se recibieron datos y que decodificó correctamente
if (!is_array($cliente)) {
    responder(['status' => 'error', 'mensaje' => 'No se recibieron datos válidos'], 400);
}

// 2. Validar que el ID exista y sea numérico
if (empty($cliente['id']) || !ctype_digit((string) $cliente['id'])) {
    responder(['status' => 'error', 'mensaje' => 'ID del cliente no proporcionado o inválido'], 400);
}
$id = (int) $cliente['id'];

// 3. Validar campos requeridos (incluyo 'direccion', que faltaba en el original
// pero sí se usa más abajo en el bind_param)
$camposRequeridos = [
    'nombre', 'apellido', 'cedula', 'direccion',
    'telefono', 'correo', 'nombre_garante',
    'telefono_garante', 'cedula_garante',
];

$datos = [];
foreach ($camposRequeridos as $campo) {
    $valor = trim((string) ($cliente[$campo] ?? ''));
    if ($valor === '') {
        responder(['status' => 'error', 'mensaje' => 'Este campo es requerido', 'campo' => $campo], 400);
    }
    $datos[$campo] = $valor;
}

if (!filter_var($datos['correo'], FILTER_VALIDATE_EMAIL)) {
    responder(['status' => 'error', 'mensaje' => 'El correo no es válido', 'campo' => 'correo'], 400);
}

if (!ctype_digit($datos['cedula'])) {
    responder(['status' => 'error', 'mensaje' => 'La cédula debe contener solo números', 'campo' => 'cedula'], 400);
}

if (!ctype_digit($datos['cedula_garante'])) {
    responder(['status' => 'error', 'mensaje' => 'La cédula debe contener solo números', 'campo' => 'cedula_garante'], 400);
}

try {
    $sql = "UPDATE clientes SET
                nombre           = ?,
                apellido         = ?,
                cedula           = ?,
                direccion        = ?,
                telefono         = ?,
                correo           = ?,
                nombre_garante   = ?,
                telefono_garante = ?,
                cedula_garante   = ?
            WHERE id_cliente = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        'sssssssssi',
        $datos['nombre'],
        $datos['apellido'],
        $datos['cedula'],
        $datos['direccion'],
        $datos['telefono'],
        $datos['correo'],
        $datos['nombre_garante'],
        $datos['telefono_garante'],
        $datos['cedula_garante'],
        $id
    );
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        // No es necesariamente un error: puede que el ID no exista,
        // o que los datos enviados sean idénticos a los ya guardados.
        // Verificamos si el cliente existe para dar un mensaje preciso.
        $check = $conn->prepare('SELECT 1 FROM clientes WHERE id_cliente = ?');
        $check->bind_param('i', $id);
        $check->execute();
        $existe = $check->get_result()->num_rows > 0;
        $check->close();

        if (!$existe) {
            $stmt->close();
            responder(['status' => 'error', 'mensaje' => 'Cliente no encontrado'], 404);
        }
        // Si existe pero no hubo cambios, lo tratamos igual como éxito.
    }

    $stmt->close();
    responder(['status' => 'success']);
} catch (mysqli_sql_exception $e) {
    if ($e->getCode() === 1062) {
        responder(['status' => 'error', 'mensaje' => 'Ya existe otro cliente con esa cédula', 'campo' => 'cedula'], 409);
    }

    error_log('Error al actualizar cliente: ' . $e->getMessage());
    responder(['status' => 'error', 'mensaje' => 'Error interno al actualizar el cliente'], 500);
} finally {
    $conn->close();
}