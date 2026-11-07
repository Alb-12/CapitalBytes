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

if (!in_array($_SESSION['rol'] ?? '', ['Administrador', 'Supervisor'], true)) {
    responder(['status' => 'error', 'mensaje' => 'Sin permisos'], 403);
}

try {
    $stmt = $conn->prepare('UPDATE configuracion_sistema SET onedrive_refresh_token = NULL, onedrive_email = NULL WHERE id = 1');
    $stmt->execute();
    $stmt->close();

    responder(['status' => 'success']);
} catch (mysqli_sql_exception $e) {
    error_log('Error al desconectar OneDrive: ' . $e->getMessage());
    responder(['status' => 'error', 'mensaje' => 'Error interno al desconectar'], 500);
} finally {
    $conn->close();
}
