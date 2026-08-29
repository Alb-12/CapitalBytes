<?php

declare(strict_types=1);


include(__DIR__ . '/../db/db.php');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// ── Ejecutar mora automática al cargar el módulo ──────────────────────
include(__DIR__ . '/../db/mora_automatica.php');

$prestamos = [];
$historial = [];

try {
    aplicarMoraAutomatica($conn);

    // Cargar préstamos activos con datos del cliente
    $sql = "SELECT p.id_prestamo, p.cuota_monto, p.saldo_pendiente,
                   p.mora_porcentaje, p.tasa_interes, p.tipo_interes,
                   p.modalidad_pagos, p.fecha_inicio, p.fecha_fin,
                   p.estado, p.plazo,
                   CONCAT(c.nombre, ' ', c.apellido) AS nombre_cliente,
                   c.telefono AS telefono_cliente
            FROM prestamos p
            JOIN clientes c ON p.id_cliente = c.id_cliente
            WHERE p.estado IN ('Activo','Mora')
            ORDER BY p.id_prestamo DESC";
    $res       = $conn->query($sql);
    $prestamos = $res->fetch_all(MYSQLI_ASSOC);

    // Cargar historial de pagos
    $sqlH = "SELECT pg.*, p2.id_prestamo,
                    CONCAT(c2.nombre,' ',c2.apellido) AS nombre_cliente,
                    u.nombre_usuario AS registrado_por_nombre
             FROM pagos pg
             JOIN prestamos p2 ON pg.id_prestamo = p2.id_prestamo
             JOIN clientes c2  ON p2.id_cliente  = c2.id_cliente
             JOIN usuarios u   ON pg.registrado_por = u.id_usuario
             ORDER BY pg.fecha_pago DESC
             LIMIT 100";
    $resH      = $conn->query($sqlH);
    $historial = $resH->fetch_all(MYSQLI_ASSOC);
} catch (mysqli_sql_exception $e) {
    error_log('Error al cargar módulo de pagos: ' . $e->getMessage());
}

// Helper local para no repetir htmlspecialchars(..., ENT_QUOTES, 'UTF-8')
$e = static fn(string $valor): string => htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
?>

<div class="modulo-pagos">
    <h2>Registro de Pagos</h2>
    <hr>

    <!-- Modal: Registrar pago (Bootstrap) -->
    <div class="modal fade" id="modalPago" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Registrar Pago</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="pg_id_prestamo">
                    <input type="hidden" id="pg_telefono">

                    <label for="pg_cliente">Cliente</label>
                    <input type="text" id="pg_cliente" readonly>

                    <label for="pg_cuota">Cuota del préstamo</label>
                    <input type="text" id="pg_cuota" readonly>

                    <label for="pg_saldo">Saldo pendiente</label>
                    <input type="text" id="pg_saldo" readonly>

                    <label for="pg_mora">Mora acumulada (calculada)</label>
                    <input type="text" id="pg_mora" readonly>

                    <label for="pg_capital">Monto capital (calculado)</label>
                    <input type="text" id="pg_capital" readonly>

                    <label for="pg_interes">Monto interés (calculado)</label>
                    <input type="text" id="pg_interes" readonly>

                    <label for="pg_total">Total a pagar</label>
                    <input type="text" id="pg_total" readonly style="font-weight:bold;">

                    <label for="pg_monto_pagado">Monto recibido</label>
                    <input type="number" id="pg_monto_pagado" step="0.01" min="0"
                           oninput="calcularVuelto()" placeholder="0.00">

                    <label for="pg_vuelto">Vuelto</label>
                    <input type="text" id="pg_vuelto" readonly>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="guardarPago()">✔ Confirmar Pago</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Recibo (Bootstrap) -->
    <div class="modal fade" id="modalRecibo" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Recibo de Pago</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body" id="contenidoRecibo"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" onclick="imprimirTermica()">🖨 Térmica (80mm)</button>
                    <button type="button" class="btn btn-outline-secondary" onclick="imprimirCarta()">🖨 Hoja Carta</button>
                    <button type="button" class="btn btn-success" onclick="enviarWhatsApp()">📲 Enviar por WhatsApp</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Lista de préstamos activos -->
    <h3>Préstamos Activos</h3>
    <input type="search" id="buscarPago" onkeyup="buscarPago()"
           placeholder="Buscar por cliente o ID...">

    <table id="tablaPagos">
        <thead>
            <tr>
                <th>ID</th>
                <th>Cliente</th>
                <th>Cuota</th>
                <th>Saldo</th>
                <th>Mora %</th>
                <th>Modalidad</th>
                <th>Estado</th>
                <th>Acción</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($prestamos as $p): ?>
                <?php $prestamoJson = json_encode($p, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>
                <tr>
                    <td><?= (int) $p['id_prestamo'] ?></td>
                    <td><?= $e($p['nombre_cliente']) ?></td>
                    <td>$<?= number_format((float) $p['cuota_monto'], 2) ?></td>
                    <td>$<?= number_format((float) $p['saldo_pendiente'], 2) ?></td>
                    <td><?= $e((string) $p['mora_porcentaje']) ?>%</td>
                    <td><?= $e($p['modalidad_pagos']) ?></td>
                    <td><?= $e($p['estado']) ?></td>
                    <td>
                        <button type="button" onclick='abrirModalPago(<?= $prestamoJson ?>)'>
                            Registrar Pago
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Historial de pagos -->
    <h3 style="margin-top:2rem;">Historial de Pagos</h3>
    <table id="tablaHistorial">
        <thead>
            <tr>
                <th>ID Pago</th>
                <th>Cliente</th>
                <th>Fecha</th>
                <th>Capital</th>
                <th>Interés</th>
                <th>Mora</th>
                <th>Total Pagado</th>
                <th>Saldo Restante</th>
                <th>Registrado por</th>
                <th>Recibo</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($historial as $h): ?>
                <?php $historialJson = json_encode($h, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>
                <tr>
                    <td><?= (int) $h['id_pagos'] ?></td>
                    <td><?= $e($h['nombre_cliente']) ?></td>
                    <td><?= $e($h['fecha_pago']) ?></td>
                    <td>$<?= number_format((float) $h['monto_capital'], 2) ?></td>
                    <td>$<?= number_format((float) $h['monto_interes'], 2) ?></td>
                    <td>$<?= number_format((float) $h['monto_mora'], 2) ?></td>
                    <td>$<?= number_format((float) $h['monto_pagado'], 2) ?></td>
                    <td>$<?= number_format((float) $h['saldo_restante'], 2) ?></td>
                    <td><?= $e($h['registrado_por_nombre']) ?></td>
                    <td>
                        <button type="button" onclick='verRecibo(<?= $historialJson ?>)'>
                            🧾 Ver
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>