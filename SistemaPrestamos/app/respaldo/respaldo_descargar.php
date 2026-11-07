<?php

declare(strict_types=1);

session_start();

if (!in_array($_SESSION['rol'] ?? '', ['Administrador', 'Supervisor'], true)) {
    http_response_code(403);
    exit('Sin permisos');
}

$archivo = $_GET['archivo'] ?? '';

// Validación estricta del nombre: solo el patrón exacto que genera
// generarRespaldo(), para impedir path traversal (../../etc/passwd) o
// acceder a archivos fuera de la carpeta de respaldos.
if (!preg_match('/^respaldo_\d{4}-\d{2}-\d{2}_\d{6}\.sql$/', $archivo)) {
    http_response_code(400);
    exit('Nombre de archivo no válido');
}

$ruta = __DIR__ . '/backups/' . $archivo;

if (!is_file($ruta)) {
    http_response_code(404);
    exit('Archivo no encontrado');
}

header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="' . $archivo . '"');
header('Content-Length: ' . filesize($ruta));
readfile($ruta);