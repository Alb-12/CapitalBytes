<?php

declare(strict_types=1);

$host = 'localhost';
$user = 'root';
$pass = 'Albeiro12@#';
$db   = 'db';

// mysqli_report ya está configurado como excepciones en cada endpoint, pero
// la conexión inicial ocurre ANTES de esa línea en cada archivo, así que
// aquí sí hace falta un manejo explícito: antes, si la conexión fallaba,
// el script solo hacía `echo` del error y seguía ejecutándose con un $conn
// roto, lo que provocaba errores en cascada mucho más confusos más abajo
// (ej. "Call to a member function prepare() on null/bool").
$conn = @new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    error_log('Error de conexión a la base de datos: ' . $conn->connect_error);
    http_response_code(500);

    // Detecta si el archivo que incluyó este ya envió Content-Type: application/json,
    // para responder consistente con el resto de los endpoints. Si no, muestra un
    // mensaje genérico sin filtrar detalles técnicos al usuario final.
    $headers = headers_list();
    $esJson  = false;
    foreach ($headers as $h) {
        if (stripos($h, 'application/json') !== false) {
            $esJson = true;
            break;
        }
    }

    if ($esJson) {
        echo json_encode(['status' => 'error', 'mensaje' => 'No se pudo conectar a la base de datos']);
    } else {
        echo 'No se pudo conectar a la base de datos. Intenta de nuevo más tarde.';
    }
    exit;
}

$conn->set_charset('utf8mb4');