<?php

declare(strict_types=1);


include(__DIR__ . '/../db/db.php');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$esAdmin = in_array($_SESSION['rol'] ?? '', ['Administrador', 'Supervisor'], true);

if (!$esAdmin) {
    echo '<p class="text-muted">Solo el administrador puede acceder al módulo de Respaldo.</p>';
    return;
}

$config = ['mora_porcentaje_default' => 5, 'dias_gracia' => 0, 'backup_automatico' => 0,
           'backup_frecuencia' => 'Diario', 'backup_hora' => '02:00', 'backup_destino' => 'Local',
           'nombre_empresa' => 'PRESTA-APP', 'whatsapp_token' => '', 'whatsapp_phone_id' => '',
           'whatsapp_api_version' => 'v19.0'];
$respaldos = [];

try {
    $config = $conn->query('SELECT * FROM configuracion_sistema WHERE id = 1')->fetch_assoc() ?? $config;
    $respaldos = $conn->query('
        SELECT r.*, u.nombre_usuario
        FROM respaldos r
        LEFT JOIN usuarios u ON r.generado_por = u.id_usuario
        ORDER BY r.fecha DESC
        LIMIT 30
    ')->fetch_all(MYSQLI_ASSOC);
} catch (mysqli_sql_exception $e) {
    error_log('Error al cargar módulo de respaldo: ' . $e->getMessage());
}

$e = static fn(string $valor): string => htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');

function formatearTamano(?int $bytes): string
{
    if ($bytes === null) return '—';
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1048576, 2) . ' MB';
}
?>

<div class="modulo-respaldo">
    <h2>Respaldo y Configuración</h2>
    <hr>

    <?php if (($_GET['google'] ?? '') === 'conectado'): ?>
        <div class="alert alert-success">✔ Cuenta de Google Drive conectada correctamente.</div>
    <?php endif; ?>

    <?php if (($_GET['onedrive'] ?? '') === 'conectado'): ?>
        <div class="alert alert-success">✔ Cuenta de OneDrive conectada correctamente.</div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <!-- ── Respaldo manual ─────────────────────────────────────── -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h5 class="mb-3">Respaldo Manual</h5>
                    <p class="text-muted small">
                        Genera un respaldo completo de la base de datos ahora mismo.
                    </p>
                    <button type="button" class="btn btn-primary" id="btn-respaldo-manual" onclick="generarRespaldoManual()">
                        💾 Generar Respaldo Ahora
                    </button>
                    <div id="resultado-respaldo-manual" class="mt-2"></div>
                </div>
            </div>
        </div>

        <!-- ── Configuración automática ─────────────────────────────── -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h5 class="mb-3">Respaldo Automático</h5>

                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" id="cfg_backup_automatico"
                               <?= $config['backup_automatico'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="cfg_backup_automatico">Activar respaldo automático</label>
                    </div>

                    <label for="cfg_backup_frecuencia">Frecuencia</label>
                    <select id="cfg_backup_frecuencia" class="form-select mb-2">
                        <?php foreach (['Diario', 'Semanal', 'Mensual'] as $f): ?>
                            <option value="<?= $f ?>" <?= $config['backup_frecuencia'] === $f ? 'selected' : '' ?>><?= $f ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label for="cfg_backup_hora">Hora de ejecución</label>
                    <input type="time" id="cfg_backup_hora" class="form-control mb-2"
                           value="<?= $e(substr((string) $config['backup_hora'], 0, 5)) ?>">

                    <label for="cfg_backup_destino">Destino</label>
                    <select id="cfg_backup_destino" class="form-select mb-3">
                        <option value="Local" <?= $config['backup_destino'] === 'Local' ? 'selected' : '' ?>>Solo servidor local</option>
                        <option value="GoogleDrive" <?= $config['backup_destino'] === 'GoogleDrive' ? 'selected' : '' ?>>Google Drive</option>
                        <option value="OneDrive" <?= $config['backup_destino'] === 'OneDrive' ? 'selected' : '' ?>>OneDrive</option>
                    </select>

                    <!-- ── Conexión con Google Drive ─────────────────── -->
                    <div class="border rounded p-2 mb-3">
                        <div class="small fw-bold mb-1">Cuenta de Google Drive</div>
                        <?php if (!empty($config['google_email'])): ?>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-success small">✔ Conectado: <?= $e($config['google_email']) ?></span>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="desconectarGoogle()">Desconectar</button>
                            </div>
                        <?php else: ?>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted small">Sin conectar</span>
                                <a href="respaldo/google_conectar.php" class="btn btn-sm btn-outline-primary">Conectar con Google Drive</a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- ── Conexión con OneDrive ─────────────────────── -->
                    <div class="border rounded p-2 mb-3">
                        <div class="small fw-bold mb-1">Cuenta de OneDrive</div>
                        <?php if (!empty($config['onedrive_email'])): ?>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-success small">✔ Conectado: <?= $e($config['onedrive_email']) ?></span>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="desconectarOneDrive()">Desconectar</button>
                            </div>
                        <?php else: ?>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted small">Sin conectar</span>
                                <a href="respaldo/onedrive_conectar.php" class="btn btn-sm btn-outline-primary">Conectar con OneDrive</a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <p class="text-muted small">
                        ⚠️ La ejecución automática real requiere una tarea programada (cron) en el
                        servidor — esta pantalla solo guarda la configuración que el cron va a leer.
                        Pídele a tu hosting/DevOps que agregue una entrada de <code>crontab</code>
                        apuntando a <code>respaldo/respaldo_cron.php</code>.
                    </p>

                    <button type="button" class="btn btn-outline-primary" onclick="guardarConfigRespaldo()">
                        Guardar Configuración
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Configuración de mora ───────────────────────────────────── -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <h5 class="mb-3">Configuración de Mora</h5>
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="cfg_mora_porcentaje">Mora % por defecto</label>
                    <input type="number" id="cfg_mora_porcentaje" class="form-control" step="0.01" min="0"
                           value="<?= $e((string) $config['mora_porcentaje_default']) ?>">
                    <small class="text-muted">Se usa como valor inicial al crear un préstamo nuevo (editable por préstamo).</small>
                </div>
                <div class="col-md-4">
                    <label for="cfg_dias_gracia">Días de gracia</label>
                    <input type="number" id="cfg_dias_gracia" class="form-control" step="1" min="0"
                           value="<?= $e((string) $config['dias_gracia']) ?>">
                    <small class="text-muted">Días después del vencimiento antes de marcar el préstamo en Mora.</small>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="button" class="btn btn-outline-primary" onclick="guardarConfigMora()">
                        Guardar Configuración de Mora
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Datos de la empresa y WhatsApp Business ─────────────────── -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <h5 class="mb-3">Empresa y WhatsApp Business API</h5>
            <p class="text-muted small">
                Cada instalación del sistema configura aquí su propio nombre y sus propias
                credenciales de WhatsApp — así los recibos se envían desde el número de
                WhatsApp Business de tu empresa, no de una cuenta compartida.
            </p>
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="cfg_nombre_empresa">Nombre de la empresa</label>
                    <input type="text" id="cfg_nombre_empresa" class="form-control" maxlength="150"
                           value="<?= $e($config['nombre_empresa']) ?>">
                    <small class="text-muted">Aparece en el encabezado de los recibos.</small>
                </div>
                <div class="col-md-6">
                    <label for="cfg_whatsapp_phone_id">Phone Number ID</label>
                    <input type="text" id="cfg_whatsapp_phone_id" class="form-control"
                           value="<?= $e((string) $config['whatsapp_phone_id']) ?>" placeholder="Ej: 123456789012345">
                </div>
                <div class="col-md-8">
                    <label for="cfg_whatsapp_token">Access Token</label>
                    <input type="password" id="cfg_whatsapp_token" class="form-control"
                           value="<?= $e((string) $config['whatsapp_token']) ?>" placeholder="Token de acceso de WhatsApp Business API">
                </div>
                <div class="col-md-4">
                    <label for="cfg_whatsapp_version">Versión de la API</label>
                    <input type="text" id="cfg_whatsapp_version" class="form-control"
                           value="<?= $e($config['whatsapp_api_version']) ?>" placeholder="v19.0">
                </div>
                <div class="col-12">
                    <button type="button" class="btn btn-outline-primary" onclick="guardarConfigEmpresa()">
                        Guardar Configuración de Empresa
                    </button>
                </div>
            </div>
            <p class="text-muted small mt-3 mb-0">
                ¿No tienes credenciales de WhatsApp Business todavía? Se obtienen en
                <a href="https://developers.facebook.com/apps/" target="_blank" rel="noopener">developers.facebook.com/apps</a>
                — cada empresa necesita registrar su propia app de Meta.
            </p>
        </div>
    </div>

    <!-- ── Historial de respaldos ───────────────────────────────────── -->
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <h5 class="mb-3">Historial de Respaldos</h5>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Archivo</th>
                            <th>Tamaño</th>
                            <th>Destino</th>
                            <th>Tipo</th>
                            <th>Estado</th>
                            <th>Generado por</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($respaldos)): ?>
                            <tr><td colspan="8" class="text-center text-muted py-4">Aún no se ha generado ningún respaldo</td></tr>
                        <?php endif; ?>
                        <?php foreach ($respaldos as $r): ?>
                            <tr>
                                <td><?= $e((string) $r['fecha']) ?></td>
                                <td><code><?= $e($r['archivo']) ?></code></td>
                                <td><?= formatearTamano($r['tamano_bytes'] !== null ? (int) $r['tamano_bytes'] : null) ?></td>
                                <td><?= $e($r['destino']) ?></td>
                                <td><?= $e($r['tipo']) ?></td>
                                <td>
                                    <span class="badge <?= $r['estado'] === 'Exitoso' ? 'text-bg-success' : 'text-bg-danger' ?>">
                                        <?= $e($r['estado']) ?>
                                    </span>
                                </td>
                                <td><?= $e($r['nombre_usuario'] ?? 'Sistema (cron)') ?></td>
                                <td>
                                    <?php if ($r['estado'] === 'Exitoso' && $r['destino'] === 'Local'): ?>
                                        <a class="btn btn-sm btn-outline-secondary"
                                           href="respaldo/respaldo_descargar.php?archivo=<?= urlencode($r['archivo']) ?>">
                                            ⬇ Descargar
                                        </a>
                                    <?php elseif ($r['estado'] === 'Fallido'): ?>
                                        <span class="text-danger small" title="<?= $e((string) $r['mensaje_error']) ?>">Ver error</span>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>