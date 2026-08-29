<?php

declare(strict_types=1);

include __DIR__ . '/../db/db.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$clientes = [];

try {
    $result = $conn->query('SELECT * FROM clientes ORDER BY nombre, apellido');

    // fetch_all(MYSQLI_ASSOC) reemplaza el while + fetch_assoc() manual:
    // hace lo mismo en una sola línea.
    $clientes = $result->fetch_all(MYSQLI_ASSOC);
} catch (mysqli_sql_exception $e) {
    // Este archivo se incluye dentro de una página HTML, así que no podemos
    // responder JSON aquí como en los otros endpoints. Registramos el error
    // y dejamos $clientes como array vacío para que la página no se rompa;
    // el HTML ya maneja el caso "sin clientes" con el if(!empty($clientes)).
    error_log('Error al cargar clientes: ' . $e->getMessage());
}
?>