<?php

declare(strict_types=1);

/**
 * Componente de integración con la API de WhatsApp Business (Cloud API de Meta).
 *
 * Reúne en una sola clase la llamada HTTP que antes estaba escrita dentro de
 * whatsapp_enviar.php, para reutilizarla en pagos y en la factura de préstamos.
 * La configuración (token, Phone Number ID y versión) se lee de la tabla
 * configuracion_sistema, así cada empresa usa su propia cuenta de WhatsApp.
 *
 * Endpoint probado en Postman:
 *   POST https://graph.facebook.com/{version}/{phone-number-id}/messages
 *   Header Authorization: Bearer {token}
 *   Body JSON: {"messaging_product":"whatsapp","to":"1809...","type":"text","text":{"body":"..."}}
 */
final class WhatsAppService
{
    public function __construct(
        private string $token,
        private string $phoneId,
        private string $version = 'v19.0'
    ) {
    }

    /**
     * Crea el servicio con la configuración guardada en la base de datos.
     * Devuelve null si la empresa todavía no configuró WhatsApp.
     * (Con MYSQLI_REPORT_STRICT activo puede lanzar mysqli_sql_exception: el llamador debe capturarla.)
     */
    public static function desdeConfiguracion(mysqli $conn): ?self
    {
        $cfg = $conn->query(
            'SELECT whatsapp_token, whatsapp_phone_id, whatsapp_api_version
             FROM configuracion_sistema WHERE id = 1'
        )->fetch_assoc();

        if (empty($cfg['whatsapp_token']) || empty($cfg['whatsapp_phone_id'])) {
            return null;
        }
        return new self($cfg['whatsapp_token'], $cfg['whatsapp_phone_id'], $cfg['whatsapp_api_version'] ?: 'v19.0');
    }

    /**
     * Envía un mensaje de texto.
     *
     * @return array{ok: bool, http: int, error: ?string}
     */
    public function enviarTexto(string $telefono, string $mensaje): array
    {
        $telefono = $this->normalizarTelefono($telefono);
        if (strlen($telefono) < 10) {
            return ['ok' => false, 'http' => 0, 'error' => 'El número de teléfono no es válido'];
        }

        $url     = 'https://graph.facebook.com/' . $this->version . '/' . $this->phoneId . '/messages';
        $payload = json_encode([
            'messaging_product' => 'whatsapp',
            'to'                => $telefono,
            'type'              => 'text',
            'text'              => ['body' => $mensaje],
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->token,
                'Content-Type: application/json',
            ],
        ]);
        $respuesta = curl_exec($ch);
        $errno     = curl_errno($ch);
        $errorCurl = curl_error($ch);
        $http      = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            error_log('WhatsAppService (cURL): ' . $errorCurl);
            return ['ok' => false, 'http' => 0, 'error' => 'No se pudo conectar con WhatsApp'];
        }

        $json = json_decode((string) $respuesta, true) ?? [];
        if ($http === 200 && isset($json['messages'])) {
            return ['ok' => true, 'http' => $http, 'error' => null];
        }

        // El detalle técnico va al log; al usuario se le devuelve un mensaje genérico.
        error_log('WhatsAppService (HTTP ' . $http . '): ' . ($json['error']['message'] ?? 'sin detalle'));
        return ['ok' => false, 'http' => $http, 'error' => 'WhatsApp rechazó el mensaje. Revisa la configuración.'];
    }

    /** Deja solo dígitos; los números de 10 dígitos (RD) llevan el código de país 1. */
    private function normalizarTelefono(string $telefono): string
    {
        $digitos = preg_replace('/[^0-9]/', '', $telefono) ?? '';
        return strlen($digitos) === 10 ? '1' . $digitos : $digitos;
    }
}
