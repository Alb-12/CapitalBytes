<?php

declare(strict_types=1);

include __DIR__ . '/../db/db.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$cedula = trim((string) ($_GET['cedula'] ?? ''));

if ($cedula === '' || !ctype_digit($cedula)) {
    echo '<p>Cédula no válida.</p>';
    exit;
}

try {
    $stmt = $conn->prepare('SELECT * FROM clientes WHERE cedula = ?');
    $stmt->bind_param('s', $cedula);
    $stmt->execute();
    $cliente = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    error_log('Error al cargar cliente para edición: ' . $e->getMessage());
    echo '<p>Ocurrió un error al cargar los datos del cliente.</p>';
    exit;
} finally {
    $conn->close();
}

if ($cliente === null) {
    echo '<p>Cliente no encontrado.</p>';
    exit;
}

// Helper local para no repetir htmlspecialchars(..., ENT_QUOTES, 'UTF-8') diez veces
$e = static fn(string $valor): string => htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
?>
<!-- Sin DOCTYPE ni html/head/body porque este archivo
     se carga DENTRO de otro modal con fetch() -->

<div id="modalEditar">

    <h3>Editar Cliente</h3>

    <!-- ID oculto del cliente -->
    <input type="hidden" id="edit_id" value="<?= (int) $cliente['id_cliente'] ?>">

    <label for="edit_nombre">Nombre</label>
    <input type="text" id="edit_nombre" placeholder="Nombre" value="<?= $e($cliente['nombre'] ?? '') ?>"><br>

    <label for="edit_apellido">Apellido</label>
    <input type="text" id="edit_apellido" placeholder="Apellido" value="<?= $e($cliente['apellido'] ?? '') ?>"><br>

    <label for="edit_cedula">Cédula</label>
    <input type="text" id="edit_cedula" placeholder="Cédula (sin guiones)" value="<?= $e($cliente['cedula'] ?? '') ?>"><br>

    <label for="edit_direccion">Dirección</label>
    <input type="text" id="edit_direccion" placeholder="Dirección" value="<?= $e($cliente['direccion'] ?? '') ?>"><br>

    <label for="edit_telefono">Teléfono</label>
    <input type="text" id="edit_telefono" placeholder="Teléfono" value="<?= $e($cliente['telefono'] ?? '') ?>"><br>

    <label for="edit_correo">Correo</label>
    <input type="email" id="edit_correo" placeholder="Correo" value="<?= $e($cliente['correo'] ?? '') ?>"><br>

    <label for="edit_nombre_garante">Nombre Garante</label>
    <input type="text" id="edit_nombre_garante" placeholder="Nombre del garante" value="<?= $e($cliente['nombre_garante'] ?? '') ?>"><br>

    <label for="edit_telefono_garante">Teléfono Garante</label>
    <input type="text" id="edit_telefono_garante" placeholder="Teléfono del garante" value="<?= $e($cliente['telefono_garante'] ?? '') ?>"><br>

    <label for="edit_cedula_garante">Cédula Garante</label>
    <input type="text" id="edit_cedula_garante" placeholder="Cédula del garante" value="<?= $e($cliente['cedula_garante'] ?? '') ?>"><br><br>

    <button type="button" onclick="actualizarCliente()">
        Guardar Cambios
    </button>

    <button type="button" onclick="cerrarModal()">
        Cancelar
    </button>

</div>
