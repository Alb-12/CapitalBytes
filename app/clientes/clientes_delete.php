<?php

declare(strict_types=1);

session_start();
<<<<<<< HEAD
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/ClienteRepository.php';
=======
require __DIR__ . '/../db/db.php';
>>>>>>> 4a61193eab241d7dfebf3dae54399c65e6f95608

header('Content-Type: application/json; charset=utf-8');

function responder(array $payload, int $httpCode = 200): never
{
<<<<<<< HEAD
    global $conn;

    http_response_code($httpCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);

    // exit no ejecuta bloques finally, así que cerramos la conexión aquí
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
=======
    http_response_code($httpCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
>>>>>>> 4a61193eab241d7dfebf3dae54399c65e6f95608
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!in_array($_SESSION['rol'] ?? '', ['Administrador', 'Supervisor'], true)) {
<<<<<<< HEAD
    responder(['status' => 'error', 'message' => 'Sin permisos'], 403);
}

=======
    responder(['status' => 'error', 'mensaje' => 'Sin permisos'], 403);
}

// El JS (eliminarCliente) envía POST con Content-Type: application/json
// y body { cedula: "..." } — leemos el body crudo, no $_POST ni $_GET.
>>>>>>> 4a61193eab241d7dfebf3dae54399c65e6f95608
$body   = json_decode(file_get_contents('php://input'), true);
$cedula = trim((string) ($body['cedula'] ?? ''));

if ($cedula === '' || !ctype_digit($cedula)) {
    responder(['status' => 'error', 'message' => 'Cédula no válida'], 400);
}

<<<<<<< HEAD
$repositorio = new ClienteRepository($conn);

try {
    $idCliente = $repositorio->idClientePorCedula($cedula);

    if ($idCliente === null) {
        responder(['status' => 'error', 'message' => 'Cliente no encontrado'], 404);
    }

    if ($repositorio->tienePrestamosActivos($idCliente)) {
=======
try {
    // 1. Buscar el id_cliente a partir de la cédula
    $stmt = $conn->prepare('SELECT id_cliente FROM clientes WHERE cedula = ?');
    $stmt->bind_param('s', $cedula);
    $stmt->execute();
    $cliente = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($cliente === null) {
        responder(['status' => 'error', 'message' => 'Cliente no encontrado'], 404);
    }

    $idCliente = (int) $cliente['id_cliente'];

    // 2. Verificar préstamos activos antes de permitir el borrado
    // ⚠️ AJUSTA "prestamos" si el nombre real de tu tabla de préstamos es distinto.
    $check = $conn->prepare(
        "SELECT COUNT(*) AS total
         FROM prestamos
         WHERE cliente_id = ?
         AND estado = 'Activo'"
    );
    $check->bind_param('i', $idCliente);
    $check->execute();
    $prestamosActivos = (int) $check->get_result()->fetch_assoc()['total'];
    $check->close();

    if ($prestamosActivos > 0) {
>>>>>>> 4a61193eab241d7dfebf3dae54399c65e6f95608
        responder([
            'status'  => 'error',
            'message' => 'No se puede eliminar un cliente con préstamos activos.',
        ], 409);
    }

<<<<<<< HEAD
    $repositorio->eliminarPorId($idCliente);
=======
    // 3. Eliminar el cliente
    $stmt = $conn->prepare('DELETE FROM clientes WHERE id_cliente = ?');
    $stmt->bind_param('i', $idCliente);
    $stmt->execute();
    $stmt->close();

>>>>>>> 4a61193eab241d7dfebf3dae54399c65e6f95608
    responder(['status' => 'success']);
} catch (mysqli_sql_exception $e) {
    error_log('Error al eliminar cliente: ' . $e->getMessage());
    responder(['status' => 'error', 'message' => 'Error interno al eliminar el cliente'], 500);
<<<<<<< HEAD
}
=======
} finally {
    $conn->close();
}
>>>>>>> 4a61193eab241d7dfebf3dae54399c65e6f95608
