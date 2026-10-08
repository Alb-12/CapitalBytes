<?php

declare(strict_types=1);

session_start();
include(__DIR__ . '/../db/db.php');
require __DIR__ . '/helper.php';

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

$idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);

try {
    $resultado = generarRespaldo($conn, $db, 'Manual', $idUsuario, 'Local');

    if (!$resultado['exitoso']) {
        responder(['status' => 'error', 'mensaje' => 'Falló la generación del respaldo: ' . $resultado['error']], 500);
    }

    responder(['status' => 'success', 'archivo' => $resultado['archivo'], 'tamano' => $resultado['tamano']]);
} catch (Throwable $e) {
    error_log('Error al generar respaldo manual: ' . $e->getMessage());

    // Mientras estamos en localhost, mostramos el error técnico real en
    // vez de un mensaje genérico, para no tener que ir a buscar el log
    // del servidor. ⚠️ QUITAR esta condición antes de pasar a producción
    // (o simplemente que dependa de que el host YA NO sea localhost).
    $esLocalhost = str_contains($_SERVER['HTTP_HOST'] ?? '', 'localhost');
    $mensaje = $esLocalhost
        ? 'Error interno: ' . $e->getMessage() . ' (archivo: ' . $e->getFile() . ':' . $e->getLine() . ')'
        : 'Error interno al generar el respaldo';

    responder(['status' => 'error', 'mensaje' => $mensaje], 500);
} finally {
    $conn->close();
}