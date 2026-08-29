<?php

declare(strict_types=1);

session_start();
include(__DIR__ . '/../db/db.php');

header('Content-Type: application/json; charset=utf-8');

function responder(array $payload, int $httpCode = 200): never
{
    http_response_code($httpCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!in_array($_SESSION['rol'] ?? '', ['Administrador', 'Supervisor'], true)) {
    responder(['status' => 'error', 'mensaje' => 'Sin permisos'], 403);
}

$datos = json_decode(file_get_contents('php://input'), true);
if (!is_array($datos)) {
    responder(['status' => 'error', 'mensaje' => 'No se recibieron datos válidos'], 400);
}

$idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
$ahora     = date('Y-m-d H:i:s');

try {
    if (($datos['tipo'] ?? '') === 'mora') {
        if (!is_numeric($datos['mora_porcentaje_default'] ?? null) || (float) $datos['mora_porcentaje_default'] < 0) {
            responder(['status' => 'error', 'mensaje' => 'El porcentaje de mora no es válido', 'campo' => 'mora_porcentaje'], 400);
        }
        if (!ctype_digit((string) ($datos['dias_gracia'] ?? '')) ) {
            responder(['status' => 'error', 'mensaje' => 'Los días de gracia deben ser un número entero', 'campo' => 'dias_gracia'], 400);
        }

        $moraPct    = (float) $datos['mora_porcentaje_default'];
        $diasGracia = (int) $datos['dias_gracia'];

        $stmt = $conn->prepare(
            'UPDATE configuracion_sistema
             SET mora_porcentaje_default = ?, dias_gracia = ?, actualizado_por = ?, actualizado_en = ?
             WHERE id = 1'
        );
        $stmt->bind_param('diis', $moraPct, $diasGracia, $idUsuario, $ahora);
        $stmt->execute();
        $stmt->close();

        responder(['status' => 'success']);
    }

    if (($datos['tipo'] ?? '') === 'respaldo') {
        $frecuenciasValidas = ['Diario', 'Semanal', 'Mensual'];
        $destinosValidos    = ['Local', 'GoogleDrive', 'OneDrive'];

        if (!in_array($datos['backup_frecuencia'] ?? '', $frecuenciasValidas, true)) {
            responder(['status' => 'error', 'mensaje' => 'Frecuencia no válida', 'campo' => 'backup_frecuencia'], 400);
        }
        if (!in_array($datos['backup_destino'] ?? '', $destinosValidos, true)) {
            responder(['status' => 'error', 'mensaje' => 'Destino no válido', 'campo' => 'backup_destino'], 400);
        }
        if (!preg_match('/^\d{2}:\d{2}$/', (string) ($datos['backup_hora'] ?? ''))) {
            responder(['status' => 'error', 'mensaje' => 'Hora no válida', 'campo' => 'backup_hora'], 400);
        }

        $automatico = !empty($datos['backup_automatico']) ? 1 : 0;
        $frecuencia = $datos['backup_frecuencia'];
        $hora       = $datos['backup_hora'] . ':00';
        $destino    = $datos['backup_destino'];

        $stmt = $conn->prepare(
            'UPDATE configuracion_sistema
             SET backup_automatico = ?, backup_frecuencia = ?, backup_hora = ?, backup_destino = ?,
                 actualizado_por = ?, actualizado_en = ?
             WHERE id = 1'
        );
        $stmt->bind_param('isssis', $automatico, $frecuencia, $hora, $destino, $idUsuario, $ahora);
        $stmt->execute();
        $stmt->close();

        responder(['status' => 'success']);
    }

    if (($datos['tipo'] ?? '') === 'empresa') {
        $nombreEmpresa = trim((string) ($datos['nombre_empresa'] ?? ''));
        if ($nombreEmpresa === '') {
            responder(['status' => 'error', 'mensaje' => 'El nombre de la empresa es requerido', 'campo' => 'nombre_empresa'], 400);
        }

        // El token y el phone_id son opcionales al guardar (puedes guardar
        // solo el nombre de la empresa sin tener WhatsApp configurado
        // todavía), pero si escribes uno, el otro también se vuelve
        // obligatorio — no tiene sentido guardar uno sin el otro.
        $waToken   = trim((string) ($datos['whatsapp_token'] ?? ''));
        $waPhoneId = trim((string) ($datos['whatsapp_phone_id'] ?? ''));
        $waVersion = trim((string) ($datos['whatsapp_api_version'] ?? '')) ?: 'v19.0';

        if (($waToken === '') !== ($waPhoneId === '')) {
            responder(['status' => 'error', 'mensaje' => 'Completa tanto el Token como el Phone Number ID, o deja ambos vacíos', 'campo' => 'whatsapp_token'], 400);
        }

        $stmt = $conn->prepare(
            'UPDATE configuracion_sistema
             SET nombre_empresa = ?, whatsapp_token = ?, whatsapp_phone_id = ?, whatsapp_api_version = ?,
                 actualizado_por = ?, actualizado_en = ?
             WHERE id = 1'
        );
        $stmt->bind_param('ssssis', $nombreEmpresa, $waToken, $waPhoneId, $waVersion, $idUsuario, $ahora);
        $stmt->execute();
        $stmt->close();

        responder(['status' => 'success']);
    }

    responder(['status' => 'error', 'mensaje' => 'Tipo de configuración no reconocido'], 400);
} catch (mysqli_sql_exception $e) {
    error_log('Error al guardar configuración: ' . $e->getMessage());
    responder(['status' => 'error', 'mensaje' => 'Error interno al guardar la configuración'], 500);
} finally {
    $conn->close();
}