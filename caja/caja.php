<?php

declare(strict_types=1);


include(__DIR__ . '/../db/db.php');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$puedeGestionarCaja = in_array($_SESSION['rol'] ?? '', ['Administrador', 'Supervisor'], true);
$hoy     = date('Y-m-d');

$balance     = ['total_entradas' => 0, 'total_salidas' => 0];
$hoyData     = ['entradas_hoy' => 0, 'salidas_hoy' => 0];
$movimientos = [];

try {
    // ── Balance total acumulado ───────────────────────────────────────
    $resBalance = $conn->query("
        SELECT
            COALESCE(SUM(CASE WHEN tipo_movimiento='Entrada' THEN monto ELSE 0 END),0) AS total_entradas,
            COALESCE(SUM(CASE WHEN tipo_movimiento='Salida'  THEN monto ELSE 0 END),0) AS total_salidas
        FROM caja
    ");
    $balance = $resBalance->fetch_assoc();

    // ── Movimientos de hoy ─────────────────────────────────────────────
    $resHoy = $conn->prepare("
        SELECT
            COALESCE(SUM(CASE WHEN tipo_movimiento='Entrada' THEN monto ELSE 0 END),0) AS entradas_hoy,
            COALESCE(SUM(CASE WHEN tipo_movimiento='Salida'  THEN monto ELSE 0 END),0) AS salidas_hoy
        FROM caja
        WHERE DATE(fecha) = ?
    ");
    $resHoy->bind_param('s', $hoy);
    $resHoy->execute();
    $hoyData = $resHoy->get_result()->fetch_assoc();
    $resHoy->close();

    // ── Historial de movimientos ────────────────────────────────────────
    $resMovs = $conn->query('
        SELECT c.*, u.nombre_usuario
        FROM caja c
        JOIN usuarios u ON c.id_usuario = u.id_usuario
        ORDER BY c.fecha DESC
        LIMIT 200
    ');
    $movimientos = $resMovs->fetch_all(MYSQLI_ASSOC);
} catch (mysqli_sql_exception $ex) {
    error_log('Error al cargar módulo de caja: ' . $ex->getMessage());
}

$balanceActual = (float) $balance['total_entradas'] - (float) $balance['total_salidas'];
$netoHoy       = (float) $hoyData['entradas_hoy'] - (float) $hoyData['salidas_hoy'];

// Helper local para no repetir htmlspecialchars(..., ENT_QUOTES, 'UTF-8')
$e = static fn(string $valor): string => htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
?>

<div class="modulo-caja">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h2 class="mb-0">Gestión de Caja</h2>
        <?php if ($puedeGestionarCaja): ?>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-success" onclick="abrirModalArqueo()">
                    📄 Arqueo de Caja
                </button>
                <button type="button" class="btn btn-primary" onclick="abrirModalTransaccion()">
                    + Nueva Transacción
                </button>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!$puedeGestionarCaja): ?>
        <p class="text-muted small">
            Solo el administrador puede registrar transacciones y hacer arqueo.
        </p>
    <?php endif; ?>

    <!-- ── Panel de resumen ─────────────────────────────────────────── -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card text-white bg-primary h-100 border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="caja-icon bg-white bg-opacity-25">$</div>
                    <div>
                        <div class="small opacity-75">Balance Actual</div>
                        <div class="fs-5 fw-bold">$<?= number_format($balanceActual, 2) ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card h-100 border-0 shadow-sm bg-success-subtle">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="caja-icon bg-success text-white">↑</div>
                    <div>
                        <div class="small text-muted">Entradas Hoy</div>
                        <div class="fs-5 fw-bold text-success">$<?= number_format((float) $hoyData['entradas_hoy'], 2) ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card h-100 border-0 shadow-sm bg-danger-subtle">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="caja-icon bg-danger text-white">↓</div>
                    <div>
                        <div class="small text-muted">Salidas Hoy</div>
                        <div class="fs-5 fw-bold text-danger">$<?= number_format((float) $hoyData['salidas_hoy'], 2) ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card h-100 border-0 shadow-sm bg-info-subtle">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="caja-icon bg-info text-white">🧮</div>
                    <div>
                        <div class="small text-muted">Neto del Día</div>
                        <div class="fs-5 fw-bold <?= $netoHoy >= 0 ? 'text-success' : 'text-danger' ?>">
                            $<?= number_format($netoHoy, 2) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Modal: Nueva transacción (Bootstrap) ────────────────────── -->
    <div class="modal fade" id="modalTransaccion" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Nueva Transacción Manual</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <label for="tx_tipo">Tipo</label>
                    <select id="tx_tipo" class="form-select mb-2">
                        <option value="Entrada">Entrada</option>
                        <option value="Salida">Salida</option>
                    </select>

                    <label for="tx_origen">Origen</label>
                    <select id="tx_origen" class="form-select mb-2">
                        <option value="Gasto">Gasto</option>
                        <option value="Ajuste">Ajuste</option>
                    </select>

                    <label for="tx_monto">Monto</label>
                    <input type="number" id="tx_monto" class="form-control mb-2" step="0.01" min="0.01" placeholder="0.00">

                    <label for="tx_descripcion">Descripción</label>
                    <input type="text" id="tx_descripcion" class="form-control" placeholder="Descripción del movimiento">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="guardarTransaccion()">Registrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Modal: Arqueo de caja (Bootstrap) ───────────────────────── -->
    <div class="modal fade" id="modalArqueo" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Arqueo de Caja</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2 align-items-end mb-2">
                        <div class="col">
                            <label for="arq_desde">Desde</label>
                            <input type="date" id="arq_desde" class="form-control" value="<?= $e($hoy) ?>">
                        </div>
                        <div class="col">
                            <label for="arq_hasta">Hasta</label>
                            <input type="date" id="arq_hasta" class="form-control" value="<?= $e($hoy) ?>">
                        </div>
                        <div class="col-auto">
                            <button type="button" class="btn btn-primary" onclick="generarArqueo()">Generar</button>
                        </div>
                    </div>

                    <div id="resultadoArqueo" class="mt-3"></div>
                </div>
                <div class="modal-footer" id="botonesArqueo" style="display:none;">
                    <button type="button" class="btn btn-outline-secondary" onclick="imprimirArqueo()">🖨 Imprimir</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Historial de movimientos ──────────────────────────────────── -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white">
            <h5 class="mb-0">Historial de Transacciones</h5>
        </div>
        <div class="card-body">
            <input type="search" id="buscarCaja" class="form-control mb-3" onkeyup="buscarCaja()"
                   placeholder="Buscar por descripción, origen...">

            <div class="table-responsive">
                <table id="tablaCaja" class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Descripción</th>
                            <th>Referencia</th>
                            <th>Monto</th>
                            <th>Usuario</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($movimientos)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    No hay transacciones registradas
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($movimientos as $m): ?>
                            <tr>
                                <td><?= $e((string) $m['fecha']) ?></td>
                                <td>
                                    <span class="badge <?= $m['tipo_movimiento'] === 'Entrada' ? 'text-bg-success' : 'text-bg-danger' ?>">
                                        <?= $e($m['tipo_movimiento']) ?>
                                    </span>
                                </td>
                                <td><?= $e($m['descripcion']) ?></td>
                                <td><?= $m['id_referencia'] !== null ? $e($m['origen'] . ' #' . $m['id_referencia']) : '—' ?></td>
                                <td>$<?= number_format((float) $m['monto'], 2) ?></td>
                                <td><?= $e($m['nombre_usuario']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
    .caja-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        flex-shrink: 0;
    }
</style>