<?php

declare(strict_types=1);

require __DIR__ . '/../db/db.php';
require __DIR__ . '/../db/mora_automatica.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Cualquier rol con sesión puede crear préstamos, pero editar el estado o
// eliminar un préstamo existente queda solo para Administrador.
$puedeEditarEliminar = ($_SESSION['rol'] ?? '') === 'Administrador';

$prestamos = [];
$clientes  = [];

try {
    aplicarMoraAutomatica($conn);

    // Cargar préstamos con nombre del cliente
    $sql = "SELECT p.*, CONCAT(c.nombre, ' ', c.apellido) AS nombre_cliente
            FROM prestamos p
            JOIN clientes c ON p.id_cliente = c.id_cliente
            ORDER BY p.fecha_registro DESC";
    $res       = $conn->query($sql);
    $prestamos = $res->fetch_all(MYSQLI_ASSOC);

    // Cargar clientes para el selector del formulario
    $resC     = $conn->query('SELECT id_cliente, nombre, apellido, cedula FROM clientes ORDER BY nombre');
    $clientes = $resC->fetch_all(MYSQLI_ASSOC);
} catch (mysqli_sql_exception $e) {
    // Igual que en cargar_cliente.php: esta página devuelve HTML, no JSON,
    // así que registramos el error y dejamos los arrays vacíos en vez de
    // romper toda la página. Las tablas ya manejan el caso "sin datos".
    error_log('Error al cargar préstamos/clientes: ' . $e->getMessage());
}

// Helper local para no repetir htmlspecialchars(..., ENT_QUOTES, 'UTF-8')
$e = static fn(string $valor): string => htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
?>

<div class="modulo-prestamo">
    <h2>Gestión de Préstamos</h2>
    <hr>
    <button type="button" onclick="abrirModalPrestamo()">+ Nuevo préstamo</button>
    <a href="?mod=redito" class="btn btn-outline-dark ms-2">💰 Préstamo a Rédito</a>

    <!-- Modal: Registrar préstamo -->
    <div id="modalPrestamo" class="modal" style="display:none;">
        <div class="modal-content">
            <button type="button" onclick="cerrarModalPrestamo()">&times; Cerrar</button>
            <h3>Registrar Préstamo</h3>

            <label for="p_id_cliente">Cliente</label>
            <select id="p_id_cliente">
                <option value="">-- Selecciona un cliente --</option>
                <?php foreach ($clientes as $c): ?>
                    <option value="<?= (int) $c['id_cliente'] ?>">
                        <?= $e($c['nombre'] . ' ' . $c['apellido'] . ' — ' . $c['cedula']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="p_monto">Monto</label>
            <input type="number" id="p_monto" placeholder="0.00" step="0.01" min="1" oninput="calcularCuota()">

            <label for="p_tasa_interes">Tasa de interés (%)</label>
            <input type="number" id="p_tasa_interes" placeholder="0.00" step="0.01" min="0" oninput="calcularCuota()">

            <label for="p_tipo_interes">Tipo de interés</label>
            <select id="p_tipo_interes" onchange="calcularCuota()">
                <option value="Simple">Simple</option>
                <option value="Compuesto">Compuesto</option>
            </select>

            <label for="p_modalidad_pagos">Modalidad de pagos</label>
            <select id="p_modalidad_pagos" onchange="calcularCuota()">
                <option value="Diario">Diario</option>
                <option value="Semanal">Semanal</option>
                <option value="Quincenal">Quincenal</option>
                <option value="Mensual">Mensual</option>
            </select>

            <label for="p_plazo">Plazo (número de cuotas)</label>
            <input type="number" id="p_plazo" placeholder="Ej: 12" min="1" oninput="calcularCuota()">

            <label for="p_mora_porcentaje">% Mora</label>
            <input type="number" id="p_mora_porcentaje" placeholder="0.00" step="0.01" min="0">

            <label for="p_fecha_inicio">Fecha de inicio</label>
            <input type="date" id="p_fecha_inicio" onchange="calcularFechaFin()">

            <!-- Calculado automáticamente -->
            <label for="p_fecha_fin">Fecha de fin (calculada)</label>
            <input type="text" id="p_fecha_fin" readonly placeholder="Se calcula automáticamente">

            <label for="p_cuota_monto">Cuota estimada (calculada)</label>
            <input type="text" id="p_cuota_monto" readonly placeholder="Se calcula automáticamente">

            <button type="button" onclick="guardarPrestamo()">Registrar Préstamo</button>
        </div>
    </div>

    <!-- Modal: Editar estado del préstamo -->
    <div id="modalEditarPrestamo" class="modal" style="display:none;">
        <div class="modal-content">
            <button type="button" onclick="cerrarModalEditarPrestamo()">&times; Cerrar</button>
            <h3>Cambiar Estado del Préstamo</h3>

            <input type="hidden" id="ep_id">

            <label for="ep_estado">Estado</label>
            <select id="ep_estado">
                <option value="Activo">Activo</option>
                <option value="Pagado">Pagado</option>
                <option value="Mora">Mora</option>
                <option value="Cancelado">Cancelado</option>
            </select>

            <button type="button" onclick="actualizarPrestamo()">Guardar Cambios</button>
        </div>
    </div>

    <!-- Buscador y tabla -->
    <input type="search" id="buscarPrestamo" onkeyup="buscarPrestamo()" placeholder="Buscar por cliente, estado...">

    <table id="tablaPrestamos">
        <thead>
            <tr>
                <th>Cliente</th>
                <th>Monto</th>
                <th>Tasa</th>
                <th>Tipo</th>
                <th>Modalidad</th>
                <th>Plazo</th>
                <th>Cuota</th>
                <th>Saldo</th>
                <th>Mora %</th>
                <th>Inicio</th>
                <th>Fin</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($prestamos as $p): ?>
                <?php
                    // JSON_HEX_APOS/QUOT evitan que un apóstrofo en el nombre del
                    // cliente (ej. "O'Brien") rompa el atributo onclick, igual que
                    // hicimos con las tarjetas de clientes.
                    $prestamoJson = json_encode($p, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
                ?>
                <tr>
                    <td><?= $e($p['nombre_cliente']) ?></td>
                    <td>$<?= number_format((float) $p['monto'], 2) ?></td>
                    <td><?= $e((string) $p['tasa_interes']) ?>%</td>
                    <td><?= $e($p['tipo_interes']) ?></td>
                    <td><?= $e($p['modalidad_pagos']) ?></td>
                    <td><?= (int) $p['plazo'] ?></td>
                    <td>$<?= number_format((float) $p['cuota_monto'], 2) ?></td>
                    <td>$<?= number_format((float) $p['saldo_pendiente'], 2) ?></td>
                    <td><?= $e((string) $p['mora_porcentaje']) ?>%</td>
                    <td><?= $e($p['fecha_inicio']) ?></td>
                    <td><?= $e($p['fecha_fin']) ?></td>
                    <td><?= $e($p['estado']) ?></td>
                    <td>
                        <?php if ($puedeEditarEliminar): ?>
                            <button type="button" onclick='editarPrestamo(<?= $prestamoJson ?>)'>Editar</button>
                            <button type="button" onclick="eliminarPrestamo(<?= (int) $p['id_prestamo'] ?>)">Eliminar</button>
                        <?php else: ?>
                            <span class="text-muted small">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
