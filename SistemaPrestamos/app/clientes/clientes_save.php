<?php

declare(strict_types=1);

session_start();
require __DIR__ . '/../db/db.php';

header('Content-Type: application/json; charset=utf-8');

/**
 * Responde en JSON con el código HTTP correcto y termina la ejecución.
 * Antes el script siempre devolvía 200 OK incluso en errores de validación;
 * ahora el status HTTP refleja lo que realmente pasó (400 = error del cliente,
 * 500 = error del servidor), lo cual es importante si el frontend (u otro
 * consumidor de la API) revisa response.ok / res.status.
 */
function responder(array $payload, int $httpCode = 200): never
{
    http_response_code($httpCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

// mysqli lanza excepciones en vez de que tengamos que chequear cada
// resultado manualmente (comportamiento por defecto desde PHP 8.1).
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!in_array($_SESSION['rol'] ?? '', ['Administrador', 'Supervisor'], true)) {
    responder(['status' => 'error', 'message' => 'Sin permisos'], 403);
}

// 1. Validar y sanear campos requeridos
$camposRequeridos = [
    'cedula', 'nombre', 'apellido', 'direccion',
    'telefono', 'correo', 'nombre_garante',
    'telefono_garante', 'cedula_garante',
];

$datos = [];
foreach ($camposRequeridos as $campo) {
    $valor = trim((string) ($_POST[$campo] ?? ''));
    if ($valor === '') {
        responder([
            'status'  => 'error',
            'message' => "Este campo es requerido",
            'campo'   => $campo,
        ], 400);
    }
    $datos[$campo] = $valor;
}

// Validaciones de formato adicionales
if (!filter_var($datos['correo'], FILTER_VALIDATE_EMAIL)) {
    responder(['status' => 'error', 'message' => 'El correo no es válido', 'campo' => 'correo'], 400);
}

if (!ctype_digit($datos['cedula'])) {
    responder(['status' => 'error', 'message' => 'La cédula debe contener solo números', 'campo' => 'cedula'], 400);
}

if (!ctype_digit($datos['cedula_garante'])) {
    responder(['status' => 'error', 'message' => 'La cédula debe contener solo números', 'campo' => 'cedula_garante'], 400);
}

// 2. Manejar la foto solo si fue enviada, con validaciones de seguridad
$rutaDestino = null;

if (isset($_FILES['foto']) && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE) {
    if ($_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
        responder(['status' => 'error', 'message' => 'Error al subir la fotografía', 'campo' => 'foto'], 400);
    }

    // Límite de tamaño (5 MB)
    if ($_FILES['foto']['size'] > 5 * 1024 * 1024) {
        responder(['status' => 'error', 'message' => 'La fotografía no debe superar 5 MB', 'campo' => 'foto'], 400);
    }

    // Validar que sea realmente una imagen (no basta con confiar en el nombre
    // o el mime type enviado por el navegador, ambos se pueden falsificar).
    $tipoReal = getimagesize($_FILES['foto']['tmp_name']);
    $extensionesPermitidas = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    if ($tipoReal === false || !isset($extensionesPermitidas[$tipoReal['mime']])) {
        responder(['status' => 'error', 'message' => 'El archivo debe ser una imagen JPG, PNG o WEBP', 'campo' => 'foto'], 400);
    }

    // Nombre de archivo generado por el servidor (no el original) para evitar
    // path traversal, colisiones y caracteres inválidos.
    $extension    = $extensionesPermitidas[$tipoReal['mime']];
    $nombreUnico  = bin2hex(random_bytes(16)) . '.' . $extension;
    $directorio   = __DIR__ . '/uploads/';
    $rutaAbsoluta = $directorio . $nombreUnico;

    if (!is_dir($directorio) && !mkdir($directorio, 0755, true) && !is_dir($directorio)) {
        responder(['status' => 'error', 'message' => 'No se pudo preparar el directorio de subida'], 500);
    }

    if (!move_uploaded_file($_FILES['foto']['tmp_name'], $rutaAbsoluta)) {
        responder(['status' => 'error', 'message' => 'Error al subir la fotografía'], 500);
    }

    // Ruta relativa que se guarda en la BD (la misma que usaba tu código original)
    $rutaDestino = 'uploads/' . $nombreUnico;
}

// 3. Insertar en la base de datos
try {
    $sql = "INSERT INTO clientes (
                nombre, apellido, cedula, direccion,
                telefono, correo, fotografia,
                nombre_garante, telefono_garante, cedula_garante
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        'ssssssssss',
        $datos['nombre'],
        $datos['apellido'],
        $datos['cedula'],
        $datos['direccion'],
        $datos['telefono'],
        $datos['correo'],
        $rutaDestino,
        $datos['nombre_garante'],
        $datos['telefono_garante'],
        $datos['cedula_garante']
    );
    $stmt->execute();
    $stmt->close();

    responder(['status' => 'success']);
} catch (mysqli_sql_exception $e) {
    // Cédula duplicada u otra violación de restricción única
    if ($e->getCode() === 1062) {
        responder(['status' => 'error', 'message' => 'Ya existe un cliente con esa cédula', 'campo' => 'cedula'], 409);
    }

    // No exponemos $e->getMessage() al cliente: podría revelar detalles
    // internos del esquema de la BD. Se registra en el log del servidor.
    error_log('Error al guardar cliente: ' . $e->getMessage());
    responder(['status' => 'error', 'message' => 'Error interno al guardar el cliente'], 500);
} finally {
    $conn->close();
}