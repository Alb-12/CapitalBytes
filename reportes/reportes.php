<?php

declare(strict_types=1);

include(__DIR__ . '/../db/db.php');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Reportes financieros: solo Administrador y Supervisor.
if (!in_array($_SESSION['rol'] ?? '', ['Administrador', 'Supervisor'], true)) {
    echo '<p class="text-muted">No tienes permiso para acceder a este módulo.</p>';
    return;
}

$hoy = date('Y-m-d');
$mes = date('Y-m-01');

$stats = [
    'total_clientes'         => 0,
    'prestamos_activos'      => 0,
    'prestamos_vencidos'     => 0,
    'prestamos_cancelados'   => 0,
    'total_pagos'            => 0,
    'total_desembolsado'     => 0.0,
    'total_recaudado'        => 0.0,
    'ganancias_interes'      => 0.0,
    'moras_cobradas'         => 0.0,
];

try {
    $stats['total_clientes'] = (int) $conn->query('SELECT COUNT(*) AS n FROM clientes')->fetch_assoc()['n'];

    $resPrestamos = $conn->query("
        SELECT
            COALESCE(SUM(CASE WHEN estado = 'Activo'    THEN 1 ELSE 0 END), 0) AS activos,
            COALESCE(SUM(CASE WHEN estado = 'Mora'      THEN 1 ELSE 0 END), 0) AS vencidos,
            COALESCE(SUM(CASE WHEN estado = 'Cancelado' THEN 1 ELSE 0 END), 0) AS cancelados,
            COALESCE(SUM(monto), 0) AS desembolsado
        FROM prestamos
    ")->fetch_assoc();

    $stats['prestamos_activos']    = (int) $resPrestamos['activos'];
    $stats['prestamos_vencidos']   = (int) $resPrestamos['vencidos'];
    $stats['prestamos_cancelados'] = (int) $resPrestamos['cancelados'];
    $stats['total_desembolsado']   = (float) $resPrestamos['desembolsado'];

    $resPagos = $conn->query('
        SELECT
            COUNT(*) AS total_pagos,
            COALESCE(SUM(monto_pagado), 0)  AS recaudado,
            COALESCE(SUM(monto_interes), 0) AS interes,
            COALESCE(SUM(monto_mora), 0)    AS mora
        FROM pagos
    ')->fetch_assoc();

    $stats['total_pagos']       = (int) $resPagos['total_pagos'];
    $stats['total_recaudado']   = (float) $resPagos['recaudado'];
    $stats['ganancias_interes'] = (float) $resPagos['interes'];
    $stats['moras_cobradas']    = (float) $resPagos['mora'];
} catch (mysqli_sql_exception $e) {
    error_log('Error al cargar resumen de reportes: ' . $e->getMessage());
}

$tasaRecuperacion = $stats['total_desembolsado'] > 0
    ? ($stats['total_recaudado'] / $stats['total_desembolsado']) * 100
    : 0.0;
$balanceTotal = $stats['total_recaudado'] - $stats['total_desembolsado'];
?>

<div class="modulo-reportes">
    <h2>Reportes</h2>
    <hr>

    <!-- ── Cards de resumen ─────────────────────────────────────────── -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card h-100 border-0 shadow-sm bg-primary-subtle">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="reporte-icon bg-primary text-white">$</div>
                    <div>
                        <div class="small text-muted">Total Desembolsado</div>
                        <div class="fs-5 fw-bold">RD$<?= number_format($stats['total_desembolsado'], 2) ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card h-100 border-0 shadow-sm bg-success-subtle">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="reporte-icon bg-success text-white">↑</div>
                    <div>
                        <div class="small text-muted">Total Recaudado</div>
                        <div class="fs-5 fw-bold text-success">RD$<?= number_format($stats['total_recaudado'], 2) ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card h-100 border-0 shadow-sm" style="background:#f2e9fb;">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="reporte-icon" style="background:#8e44ad;color:#fff;">📈</div>
                    <div>
                        <div class="small text-muted">Ganancias por Interés</div>
                        <div class="fs-5 fw-bold" style="color:#8e44ad;">RD$<?= number_format($stats['ganancias_interes'], 2) ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card h-100 border-0 shadow-sm bg-warning-subtle">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="reporte-icon bg-warning text-white">🕐</div>
                    <div>
                        <div class="small text-muted">Moras Cobradas</div>
                        <div class="fs-5 fw-bold text-warning-emphasis">RD$<?= number_format($stats['moras_cobradas'], 2) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Pestañas ─────────────────────────────────────────────────── -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-light tab-reporte" data-tab="activos" onclick="cambiarTab('activos')">Préstamos Activos</button>
            <button type="button" class="btn btn-light tab-reporte" data-tab="vencidos" onclick="cambiarTab('vencidos')">Préstamos Vencidos</button>
            <button type="button" class="btn btn-light tab-reporte" data-tab="ingresos" onclick="cambiarTab('ingresos')">Ingresos Mensuales</button>
            <button type="button" class="btn btn-primary tab-reporte active" data-tab="resumen" onclick="cambiarTab('resumen')">Resumen General</button>
        </div>
    </div>

    <!-- ── Panel: Resumen General (renderizado directo por PHP) ───────── -->
    <div id="panel-resumen" class="panel-reporte">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <h5 class="mb-3">Resumen General del Sistema</h5>
                <div class="row g-4">
                    <div class="col-6 col-md-4">
                        <div class="small text-muted">Total de Clientes</div>
                        <div class="fs-4 fw-bold"><?= $stats['total_clientes'] ?></div>
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="small text-muted">Préstamos Activos</div>
                        <div class="fs-4 fw-bold text-success"><?= $stats['prestamos_activos'] ?></div>
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="small text-muted">Préstamos Vencidos</div>
                        <div class="fs-4 fw-bold text-danger"><?= $stats['prestamos_vencidos'] ?></div>
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="small text-muted">Préstamos Cancelados</div>
                        <div class="fs-4 fw-bold"><?= $stats['prestamos_cancelados'] ?></div>
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="small text-muted">Total Pagos Registrados</div>
                        <div class="fs-4 fw-bold"><?= $stats['total_pagos'] ?></div>
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="small text-muted">Tasa de Recuperación</div>
                        <div class="fs-4 fw-bold text-primary"><?= number_format($tasaRecuperacion, 0) ?>%</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h5 class="mb-3">Análisis Financiero</h5>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Total Desembolsado:</span>
                        <strong>RD$<?= number_format($stats['total_desembolsado'], 2) ?></strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Total Recaudado:</span>
                        <strong class="text-success">RD$<?= number_format($stats['total_recaudado'], 2) ?></strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Ganancias por Interés:</span>
                        <strong style="color:#8e44ad;">RD$<?= number_format($stats['ganancias_interes'], 2) ?></strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Moras Cobradas:</span>
                        <strong class="text-warning-emphasis">RD$<?= number_format($stats['moras_cobradas'], 2) ?></strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between fs-5">
                        <span class="fw-bold">Balance Total:</span>
                        <strong class="<?= $balanceTotal >= 0 ? 'text-primary' : 'text-danger' ?>">
                            RD$<?= number_format($balanceTotal, 2) ?>
                        </strong>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- ── Panel: Préstamos Activos / Vencidos / Ingresos (vía AJAX) ──── -->
    <div id="panel-activos" class="panel-reporte" style="display:none;">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="mb-0">Préstamos Activos</h5>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportarPDF()">📄 PDF</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportarExcel()">📊 Excel</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="imprimirReporte()">🖨 Imprimir</button>
                    </div>
                </div>
                <div id="tabla-activos" class="table-responsive"></div>
            </div>
        </div>
    </div>

    <div id="panel-vencidos" class="panel-reporte" style="display:none;">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="mb-0">Préstamos Vencidos</h5>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportarPDF()">📄 PDF</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportarExcel()">📊 Excel</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="imprimirReporte()">🖨 Imprimir</button>
                    </div>
                </div>
                <div id="tabla-vencidos" class="table-responsive"></div>
            </div>
        </div>
    </div>

    <div id="panel-ingresos" class="panel-reporte" style="display:none;">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="mb-0">Ingresos Mensuales</h5>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportarPDF()">📄 PDF</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportarExcel()">📊 Excel</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="imprimirReporte()">🖨 Imprimir</button>
                    </div>
                </div>
                <div id="tabla-ingresos" class="table-responsive"></div>
            </div>
        </div>
    </div>
</div>

<style>
    .reporte-icon {
        width: 40px; height: 40px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.1rem; flex-shrink: 0;
    }
</style>

<!-- jsPDF + AutoTable para exportar PDF -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
<!-- SheetJS para Excel -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>