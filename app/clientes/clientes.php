<?php

declare(strict_types=1);


require __DIR__ . '/../db/db.php';
<<<<<<< HEAD
require __DIR__ . '/ClienteRepository.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);


=======

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Módulo de Clientes: solo Administrador y Supervisor. El Cobrador no
// gestiona clientes directamente (aunque sí los ve en los selectores al
// registrar un préstamo o un pago, que es parte de otros módulos).
>>>>>>> 4a61193eab241d7dfebf3dae54399c65e6f95608
if (!in_array($_SESSION['rol'] ?? '', ['Administrador', 'Supervisor'], true)) {
    echo '<p class="text-muted">No tienes permiso para acceder a este módulo.</p>';
    return;
}

$clientes = [];

try {
<<<<<<< HEAD
    $repositorio = new ClienteRepository($conn);
    $clientes    = $repositorio->obtenerTodos();
=======
    $result   = $conn->query('SELECT * FROM clientes ORDER BY nombre, apellido');
    $clientes = $result->fetch_all(MYSQLI_ASSOC);
>>>>>>> 4a61193eab241d7dfebf3dae54399c65e6f95608
} catch (mysqli_sql_exception $e) {
    error_log('Error al cargar clientes: ' . $e->getMessage());
}

<<<<<<< HEAD
// Helper local 
=======
// Helper local para no repetir htmlspecialchars(..., ENT_QUOTES, 'UTF-8')
>>>>>>> 4a61193eab241d7dfebf3dae54399c65e6f95608
$e = static fn(string $valor): string => htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
?>
<div class="modulo-cliente">
    <h2>Gestión de Clientes</h2>
    <hr>
    <button type="button" onclick="abrirModalRegistro()">+ Agregar cliente</button>

<<<<<<< HEAD
    <!-- Registrar cliente (Bootstrap) -->
    <div class="modal fade" id="miModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Registrar Cliente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <form id="formRegistrarCliente" action="clientes/clientes_save.php" method="POST" enctype="multipart/form-data">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label for="cedula" class="form-label">Cédula</label>
                                <input type="text" id="cedula" name="cedula" class="form-control"
                                    placeholder="Cédula (sin guiones)" required>
                            </div>
                            <div class="col-md-6">
                                <label for="telefono" class="form-label">Teléfono</label>
                                <input type="tel" id="telefono" name="telefono" class="form-control"
                                    placeholder="Teléfono" required>
                            </div>
                            <div class="col-md-6">
                                <label for="nombre" class="form-label">Nombre</label>
                                <input type="text" id="nombre" name="nombre" class="form-control"
                                    placeholder="Nombre" required>
                            </div>
                            <div class="col-md-6">
                                <label for="apellido" class="form-label">Apellido</label>
                                <input type="text" id="apellido" name="apellido" class="form-control"
                                    placeholder="Apellido" required>
                            </div>
                            <div class="col-md-6">
                                <label for="direccion" class="form-label">Dirección</label>
                                <input type="text" id="direccion" name="direccion" class="form-control"
                                    placeholder="Dirección" required>
                            </div>
                            <div class="col-md-6">
                                <label for="correo" class="form-label">Correo</label>
                                <input type="email" id="correo" name="correo" class="form-control"
                                    placeholder="Correo" required>
                            </div>
                            <div class="col-12">
                                <label for="foto" class="form-label">Fotografía del cliente</label>
                                <input type="file" id="foto" name="foto" class="form-control" accept="image/*">
                            </div>
                        </div>

                        <hr>
                        <h6 class="text-muted">Datos del garante</h6>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label for="cedula_garante" class="form-label">Cédula del garante</label>
                                <input type="text" id="cedula_garante" name="cedula_garante" class="form-control"
                                    placeholder="Cédula del garante (sin guiones)" required>
                            </div>
                            <div class="col-md-6">
                                <label for="telefono_garante" class="form-label">Teléfono del garante</label>
                                <input type="tel" id="telefono_garante" name="telefono_garante" class="form-control"
                                    placeholder="Teléfono del garante" required>
                            </div>
                            <div class="col-12">
                                <label for="nombre_garante" class="form-label">Nombre del garante</label>
                                <input type="text" id="nombre_garante" name="nombre_garante" class="form-control"
                                    placeholder="Nombre del garante" required>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="guardarCliente()">Registrar Cliente</button>
                </div>
            </div>
=======
    <!-- Modal: Registrar cliente -->
    <div id="miModal" class="modal">
        <div class="modal-content">
            <button type="button" onclick="cerrarModalRegistro()">&times; Cerrar</button>

            <h2>Registrar Cliente</h2>

            <form action="clientes/clientes_save.php" method="POST" enctype="multipart/form-data">
                <input type="text" id="cedula" name="cedula"
                    placeholder="Cédula (sin guiones)" required>
                <input type="text" id="nombre" name="nombre"
                    placeholder="Nombre" required>
                <input type="text" id="apellido" name="apellido"
                    placeholder="Apellido" required>
                <input type="tel" id="telefono" name="telefono"
                    placeholder="Teléfono" required>
                <input type="text" id="direccion" name="direccion"
                    placeholder="Dirección" required>
                <input type="email" id="correo" name="correo"
                    placeholder="Correo" required>

                <label for="foto">Fotografía del cliente:</label>
                <input type="file" id="foto" name="foto" accept="image/*">

                <label>Datos del garante</label>
                <input type="text" id="cedula_garante" name="cedula_garante"
                    placeholder="Cédula del garante (sin guiones)" required>
                <input type="text" id="nombre_garante" name="nombre_garante"
                    placeholder="Nombre del garante" required>
                <input type="tel" id="telefono_garante" name="telefono_garante"
                    placeholder="Teléfono del garante" required>

                <button type="button" onclick="guardarCliente()">Registrar Cliente</button>
            </form>
>>>>>>> 4a61193eab241d7dfebf3dae54399c65e6f95608
        </div>
    </div>

    <!-- Lista de clientes -->
    <div class="contenedor-clientes">
        <input type="search" id="buscar" onkeyup="buscarCliente()"
            placeholder="Buscar por nombre o cédula...">

        <?php if (!empty($clientes)): ?>
            <?php foreach ($clientes as $c): ?>
                <?php
                    $clienteJson = json_encode($c, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
                    $cedulaJs    = json_encode($c['cedula'] ?? '', JSON_HEX_APOS | JSON_HEX_QUOT);
                ?>
                <div class="cliente-card" onclick='verCliente(<?= $clienteJson ?>)'>

                    <h3><?= $e($c['cedula'] ?? '') ?></h3>
                    <h3><?= $e($c['nombre'] ?? '') ?></h3>

                    <button type="button"
                        onclick='event.stopPropagation(); prepararEdicion(<?= $clienteJson ?>)'>
                        Editar
                    </button>

                    <button type="button"
                        onclick='event.stopPropagation(); eliminarCliente(<?= $cedulaJs ?>)'>
                        Eliminar
                    </button>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<<<<<<< HEAD

=======
<!-- Modal: Editar cliente (Bootstrap) -->
>>>>>>> 4a61193eab241d7dfebf3dae54399c65e6f95608
<div class="modal fade" id="modalActualizar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar Cliente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body" id="contenidoModal"></div>
        </div>
    </div>
</div>

<script>
<<<<<<< HEAD
  
    function abrirModalRegistro() {
        bootstrap.Modal.getOrCreateInstance(document.getElementById('miModal')).show();
    }

    function cerrarModalRegistro() {
        bootstrap.Modal.getInstance(document.getElementById('miModal'))?.hide();
    }
</script>
=======
    // Renombradas para no chocar con abrirModal() de otros módulos
    function abrirModalRegistro() {
        document.getElementById("miModal").style.display = "block";
    }

    function cerrarModalRegistro() {
        document.getElementById("miModal").style.display = "none";
    }
</script>
>>>>>>> 4a61193eab241d7dfebf3dae54399c65e6f95608
