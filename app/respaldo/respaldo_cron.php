<?php

declare(strict_types=1);

/**
 * Este script NO se llama desde el navegador. Se ejecuta por una tarea
 * programada (cron) del servidor. Ejemplo de entrada de crontab que lo
 * corre cada hora (el script decide internamente si es momento de respaldar
 * según la configuración guardada en configuracion_sistema):
 *
 *   0 * * * * php /ruta/completa/a/SistemaPrestamo/respaldo/respaldo_cron.php >> /ruta/logs/respaldo_cron.log 2>&1
 *
 * Por seguridad, se bloquea si alguien intenta ejecutarlo vía HTTP.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo puede ejecutarse desde la línea de comandos (cron).');
}

require __DIR__ . '/../db/db.php';
require __DIR__ . '/backup_helper.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $config = $conn->query('SELECT * FROM configuracion_sistema WHERE id = 1')->fetch_assoc();

    if (!$config || !$config['backup_automatico']) {
        echo "[" . date('Y-m-d H:i:s') . "] Respaldo automático desactivado. Nada que hacer.\n";
        exit(0);
    }

    $ultima = $config['backup_ultima_ejecucion'] ? new DateTime($config['backup_ultima_ejecucion']) : null;
    $ahora  = new DateTime();

    $tocaEjecutar = match ($config['backup_frecuencia']) {
        'Diario'   => $ultima === null || $ultima->format('Y-m-d') !== $ahora->format('Y-m-d'),
        'Semanal'  => $ultima === null || $ultima->diff($ahora)->days >= 7,
        'Mensual'  => $ultima === null || $ultima->format('Y-m') !== $ahora->format('Y-m'),
        default    => false,
    };

    // También respeta la hora configurada: solo corre si ya pasó esa hora
    // hoy (asumiendo que el cron corre con más frecuencia que la config,
    // ej. cada hora, para poder acercarse a la hora exacta configurada).
    if ($tocaEjecutar) {
        $horaConfigurada = $config['backup_hora']; // 'HH:MM:SS'
        if ($ahora->format('H:i') < substr((string) $horaConfigurada, 0, 5)) {
            $tocaEjecutar = false;
        }
    }

    if (!$tocaEjecutar) {
        echo "[" . date('Y-m-d H:i:s') . "] Todavía no toca respaldo según la frecuencia configurada.\n";
        exit(0);
    }

    $resultado = generarRespaldo($conn, $db, 'Automatico', null, $config['backup_destino']);

    $upd = $conn->prepare('UPDATE configuracion_sistema SET backup_ultima_ejecucion = ? WHERE id = 1');
    $fechaAhora = $ahora->format('Y-m-d H:i:s');
    $upd->bind_param('s', $fechaAhora);
    $upd->execute();
    $upd->close();

    if ($resultado['exitoso']) {
        echo "[" . date('Y-m-d H:i:s') . "] Respaldo automático generado: {$resultado['archivo']}\n";
    } else {
        echo "[" . date('Y-m-d H:i:s') . "] ERROR al generar respaldo automático: {$resultado['error']}\n";
    }
} catch (Throwable $e) {
    error_log('Error en respaldo_cron.php: ' . $e->getMessage());
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: {$e->getMessage()}\n";
    exit(1);
} finally {
    $conn->close();
}