<?php

declare(strict_types=1);


include(__DIR__ . '/../db/db.php');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$prestamos = [];
$clientes  = [];
$historial = [];

try {
    $sql = "SELECT pr.*, CONCAT(c.nombre, ' ', c.apellido) AS nombre_cliente, c.telefono AS telefono_cliente
            FROM prestamos_redito pr
            JOIN clientes c ON pr.id_cliente = c.id_cliente
            WHERE pr.estado = 'Activo'
            ORDER BY pr.fecha_registro DESC";
    $prestamos = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

    $clientes = $conn->query('SELECT id_cliente, nombre, apellido, cedula FROM clientes ORDER BY nombre')->fetch_all(MYSQLI_ASSOC);

    $sqlHistorial = "SELECT pg.*, CONCAT(c.nombre, ' ', c.apellido) AS nombre_cliente,
                             u.nombre_usuario AS registrado_por_nombre
                      FROM pagos_redito pg
                      JOIN prestamos_redito pr ON pg.id_prestamo_redito = pr.id_prestamo_redito
                      JOIN clientes c ON pr.id_cliente = c.id_cliente
                      LEFT JOIN usuarios u ON pg.registrado_por = u.id_usuario
                      ORDER BY pg.fecha_pago DESC
                      LIMIT 100";
    $historial = $conn->query($sqlHistorial)->fetch_all(MYSQLI_ASSOC);
} catch (mysqli_sql_exception $e) {
    error_log('Error al cargar módulo de rédito: ' . $e->getMessage());
}

$e = static fn(string $valor): string => htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');

// La cuota de interés se recalcula siempre sobre el SALDO de capital actual
// (no sobre el monto original), tal como confirmaste: cada abono a capital
// baja el interés del siguiente cobro.
$calcularCuota = static fn(float $saldo, float $tasa): float => round($saldo / 1000 * $tasa, 2);
?>

<div class="modulo-redito">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="mb-0">Préstamos a Rédito</h2>
            <p class="text-muted small mb-0">Módulo independiente — interés fijo por cada 1000 de saldo, sin importar la modalidad de cobro.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="?mod=prestamo" class="btn btn-outline-secondary">← Volver a Préstamos</a>
            <button type="button" class="btn btn-primary" onclick="abrirModalRedito()">+ Nuevo Préstamo a Rédito</button>
        </div>
    </div>

    <!-- Modal: Registrar préstamo a rédito -->
    <div class="modal fade" id="modalRedito" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Nuevo Préstamo a Rédito</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <label for="r_id_cliente">Cliente</label>
                    <select id="r_id_cliente" class="form-select mb-2">
                        <option value="">-- Selecciona un cliente --</option>
                        <?php foreach ($clientes as $c): ?>
                            <option value="<?= (int) $c['id_cliente'] ?>">
                                <?= $e($c['nombre'] . ' ' . $c['apellido'] . ' — ' . $c['cedula']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label for="r_monto_capital">Monto del préstamo</label>
                    <input type="number" id="r_monto_capital" class="form-control mb-2" step="0.01" min="1" placeholder="0.00" oninput="calcularVistaPreviaRedito()">

                    <label for="r_tasa_redito">Rédito por cada 1000 (%)</label>
                    <input type="number" id="r_tasa_redito" class="form-control mb-2" step="1" min="0" value="200" oninput="calcularVistaPreviaRedito()">
                    <small class="text-muted d-block mb-2">Por defecto: 200 — es decir, por cada 1000 prestados se cobran 200 de interés por período.</small>

                    <label for="r_modalidad_pagos">Modalidad de cobro</label>
                    <select id="r_modalidad_pagos" class="form-select mb-2">
                        <option value="Diario">Diario</option>
                        <option value="Semanal">Semanal</option>
                        <option value="Quincenal">Quincenal</option>
                        <option value="Mensual">Mensual</option>
                    </select>

                    <label for="r_fecha_inicio">Fecha de inicio</label>
                    <input type="date" id="r_fecha_inicio" class="form-control mb-2">

                    <div class="alert alert-info small mb-0" id="preview-cuota-redito">
                        Cuota de interés estimada: <strong>$0.00</strong> por período
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="guardarRedito()">Registrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Registrar pago (interés o abono a capital) -->
    <div class="modal fade" id="modalPagoRedito" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Registrar Pago</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="pr_id_prestamo_redito">

                    <label for="pr_cliente">Cliente</label>
                    <input type="text" id="pr_cliente" class="form-control mb-2" readonly>

                    <label for="pr_saldo_capital">Saldo de capital actual</label>
                    <input type="text" id="pr_saldo_capital" class="form-control mb-2" readonly>

                    <label for="pr_cuota_interes">Cuota de interés (este período)</label>
                    <input type="text" id="pr_cuota_interes" class="form-control mb-3" readonly>

                    <label for="pr_tipo_pago">Tipo de pago</label>
                    <select id="pr_tipo_pago" class="form-select mb-2" onchange="actualizarMontoSugeridoRedito()">
                        <option value="Interes">Solo interés (cuota normal)</option>
                        <option value="Abono_Capital">Abono a capital</option>
                    </select>

                    <label for="pr_monto">Monto recibido</label>
                    <input type="number" id="pr_monto" class="form-control" step="0.01" min="0.01" placeholder="0.00">
                    <small class="text-muted">Si eliges "Abono a capital", este monto se descuenta del saldo (no se cobra interés sobre esa parte en este pago).</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="confirmarPagoRedito()">Confirmar Pago</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla de préstamos a rédito activos -->
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <input type="search" id="buscarRedito" class="form-control mb-3" onkeyup="buscarRedito()" placeholder="Buscar por cliente...">

            <div class="table-responsive">
                <table id="tablaRedito" class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Capital original</th>
                            <th>Saldo de capital</th>
                            <th>Rédito</th>
                            <th>Cuota de interés</th>
                            <th>Modalidad</th>
                            <th>Inicio</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($prestamos)): ?>
                            <tr><td colspan="8" class="text-center text-muted py-4">No hay préstamos a rédito activos</td></tr>
                        <?php endif; ?>
                        <?php foreach ($prestamos as $p): ?>
                            <?php
                                $cuota = $calcularCuota((float) $p['saldo_capital'], (float) $p['tasa_redito']);
                                $prestamoJson = json_encode($p, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
                            ?>
                            <tr>
                                <td><?= $e($p['nombre_cliente']) ?></td>
                                <td>$<?= number_format((float) $p['monto_capital'], 2) ?></td>
                                <td>$<?= number_format((float) $p['saldo_capital'], 2) ?></td>
                                <td><?= $e((string) $p['tasa_redito']) ?> / 1000</td>
                                <td>$<?= number_format($cuota, 2) ?></td>
                                <td><?= $e($p['modalidad_pagos']) ?></td>
                                <td><?= $e($p['fecha_inicio']) ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick='abrirModalPagoRedito(<?= $prestamoJson ?>)'>Registrar Pago</button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="cancelarRedito(<?= (int) $p['id_prestamo_redito'] ?>)">Cancelar</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Historial de pagos de rédito -->
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-body">
            <h5 class="mb-3">Historial de Pagos</h5>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Cliente</th>
                            <th>Tipo</th>
                            <th>Monto</th>
                            <th>Saldo de capital después del pago</th>
                            <th>Registrado por</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($historial)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">Aún no se ha registrado ningún pago</td></tr>
                        <?php endif; ?>
                        <?php foreach ($historial as $h): ?>
                            <tr>
                                <td><?= $e((string) $h['fecha_pago']) ?></td>
                                <td><?= $e($h['nombre_cliente']) ?></td>
                                <td>
                                    <span class="badge <?= $h['tipo_pago'] === 'Interes' ? 'text-bg-info' : 'text-bg-success' ?>">
                                        <?= $h['tipo_pago'] === 'Interes' ? 'Interés' : 'Abono a capital' ?>
                                    </span>
                                </td>
                                <td>$<?= number_format((float) $h['monto'], 2) ?></td>
                                <td>$<?= number_format((float) $h['saldo_capital_resultante'], 2) ?></td>
                                <td><?= $e($h['registrado_por_nombre'] ?? '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>