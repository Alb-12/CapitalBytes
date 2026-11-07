<?php

declare(strict_types=1);

/**
 * Las credenciales NO se duplican aquí: se reciben como parámetros desde
 * el archivo que llama a generarRespaldo(), tomadas directamente de las
 * variables $host/$user/$pass/$db que ya define tu db/db.php al incluirlo.
 * Así solo existen en un lugar (db.php), como debe ser.
 *
 * El respaldo se genera en PHP puro (sin exec() ni mysqldump.exe) — así
 * evitamos por completo los problemas de rutas, PATH, y plugins de
 * autenticación que dieron tantas vueltas, y de paso funciona igual en
 * cualquier hosting, incluso los que bloquean exec() por seguridad.
 */

// Carpeta donde se guardan los .sql generados. Está DENTRO de este módulo
// pero protegida con un .htaccess (ver respaldo/backups/.htaccess) para
// que nadie pueda descargarla directo por URL — solo a través de
// respaldo_descargar.php, que exige sesión de administrador.
define('RESPALDO_DIR', __DIR__ . '/backups');

/**
 * Genera un dump de la base de datos, lo guarda en RESPALDO_DIR, registra
 * el resultado en la tabla `respaldos` y devuelve un array con el resultado.
 *
 * @param mysqli                $conn
 * @param string                $dbName     Nombre de la base de datos ($db en db.php)
 * @param 'Manual'|'Automatico' $tipo
 * @param int|null              $idUsuario  id del usuario que lo generó, o null si fue el cron
 * @param string                $destino    'Local' | 'GoogleDrive' | 'OneDrive'
 */
function generarRespaldo(mysqli $conn, string $dbName, string $tipo, ?int $idUsuario, string $destino = 'Local'): array
{
    if (!is_dir(RESPALDO_DIR)) {
        mkdir(RESPALDO_DIR, 0755, true);
    }

    $nombreArchivo = 'respaldo_' . date('Y-m-d_His') . '.sql';
    $rutaCompleta  = RESPALDO_DIR . '/' . $nombreArchivo;

    try {
        volcarBaseDeDatos($conn, $dbName, $rutaCompleta);
        $exitoso = file_exists($rutaCompleta) && filesize($rutaCompleta) > 0;
        $tamano  = $exitoso ? filesize($rutaCompleta) : null;
        $error   = null;
    } catch (Throwable $e) {
        $exitoso = false;
        $tamano  = null;
        $error   = $e->getMessage();
        if (file_exists($rutaCompleta)) {
            unlink($rutaCompleta);
        }
    }

    // Subida a la nube: punto de extensión, ver nota al final del archivo.
    if ($exitoso && $destino !== 'Local') {
        $resultadoNube = subirRespaldoANube($conn, $rutaCompleta, $nombreArchivo, $destino);
        if (!$resultadoNube['exitoso']) {
            $exitoso = false;
            $error   = $resultadoNube['error'];
        }
    }

    $stmt = $conn->prepare(
        'INSERT INTO respaldos (archivo, tamano_bytes, destino, tipo, estado, mensaje_error, generado_por, fecha)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $estado = $exitoso ? 'Exitoso' : 'Fallido';
    $fecha  = date('Y-m-d H:i:s');
    // 8 parámetros: archivo(s), tamano_bytes(i), destino(s), tipo(s), estado(s), mensaje_error(s), generado_por(i), fecha(s)
    $stmt->bind_param('sissssis', $nombreArchivo, $tamano, $destino, $tipo, $estado, $error, $idUsuario, $fecha);
    $stmt->execute();
    $stmt->close();

    return [
        'exitoso' => $exitoso,
        'archivo' => $nombreArchivo,
        'tamano'  => $tamano,
        'error'   => $error,
    ];
}

/**
 * Genera un volcado SQL completo (estructura + datos) de $dbName y lo
 * escribe en $rutaDestino. Lanza una excepción si algo falla — el llamador
 * (generarRespaldo) ya lo atrapa con try/catch.
 *
 * Escribe con fwrite() directo al archivo (no arma un string gigante en
 * memoria), y usa consultas sin buffer para los datos, así no se queda sin
 * memoria aunque una tabla tenga muchísimas filas.
 */
function volcarBaseDeDatos(mysqli $conn, string $dbName, string $rutaDestino): void
{
    $handle = fopen($rutaDestino, 'wb');
    if ($handle === false) {
        throw new RuntimeException("No se pudo crear el archivo de respaldo en $rutaDestino (revisa permisos de escritura en la carpeta backups/).");
    }

    try {
        fwrite($handle, "-- Respaldo generado por PRESTA-APP\n");
        fwrite($handle, "-- Base de datos: $dbName\n");
        fwrite($handle, '-- Fecha: ' . date('Y-m-d H:i:s') . "\n\n");
        fwrite($handle, "SET NAMES utf8mb4;\n");
        fwrite($handle, "SET FOREIGN_KEY_CHECKS = 0;\n");

        $tablas = [];
        $resTablas = $conn->query('SHOW TABLES');
        while ($fila = $resTablas->fetch_row()) {
            $tablas[] = $fila[0];
        }

        foreach ($tablas as $tabla) {
            // ── Estructura de la tabla ──────────────────────────────────
            $resCreate = $conn->query('SHOW CREATE TABLE `' . $tabla . '`');
            $filaCreate = $resCreate->fetch_row();

            fwrite($handle, "\n-- Estructura de la tabla `$tabla`\n");
            fwrite($handle, "DROP TABLE IF EXISTS `$tabla`;\n");
            fwrite($handle, $filaCreate[1] . ";\n\n");

            // ── Datos de la tabla (consulta sin buffer, fila por fila) ──
            $resDatos = $conn->query('SELECT * FROM `' . $tabla . '`', MYSQLI_USE_RESULT);
            if ($resDatos === false) {
                continue;
            }

            $columnas = null;
            $loteFilas = [];
            $tamanoLote = 200; // filas por INSERT, para no hacer un INSERT gigante ni uno por fila

            while ($fila = $resDatos->fetch_assoc()) {
                if ($columnas === null) {
                    $columnas = array_keys($fila);
                    fwrite($handle, "-- Datos de la tabla `$tabla`\n");
                }

                $valores = array_map(
                    fn ($valor) => $valor === null ? 'NULL' : "'" . $conn->real_escape_string((string) $valor) . "'",
                    array_values($fila)
                );
                $loteFilas[] = '(' . implode(', ', $valores) . ')';

                if (count($loteFilas) >= $tamanoLote) {
                    escribirLoteInsert($handle, $tabla, $columnas, $loteFilas);
                    $loteFilas = [];
                }
            }

            if (!empty($loteFilas)) {
                escribirLoteInsert($handle, $tabla, $columnas, $loteFilas);
            }

            $resDatos->free();
        }

        fwrite($handle, "\nSET FOREIGN_KEY_CHECKS = 1;\n");
    } finally {
        fclose($handle);
    }
}

/**
 * Escribe un único INSERT INTO con varias filas de golpe (más rápido de
 * restaurar después que un INSERT por fila).
 */
function escribirLoteInsert($handle, string $tabla, array $columnas, array $filas): void
{
    $columnasSql = '`' . implode('`, `', $columnas) . '`';
    fwrite($handle, "INSERT INTO `$tabla` ($columnasSql) VALUES\n" . implode(",\n", $filas) . ";\n");
}

/**
 * Sube el archivo de respaldo a la nube conectada. Por ahora solo Google
 * Drive está implementado de verdad — OneDrive sigue siendo un
 * placeholder (ver nota más abajo).
 *
 * @param mysqli $conn Se usa para leer el refresh_token guardado
 */
function subirRespaldoANube(mysqli $conn, string $rutaLocal, string $nombreArchivo, string $destino): array
{
    if ($destino === 'GoogleDrive') {
        return subirRespaldoAGoogleDrive($conn, $rutaLocal, $nombreArchivo);
    }

    return [
        'exitoso' => false,
        'error'   => "La subida a $destino todavía no está implementada. El respaldo se generó localmente pero no se subió a la nube.",
    ];
}

/**
 * Sube el archivo a Google Drive usando el refresh_token guardado al
 * conectar la cuenta (ver google_conectar.php / google_callback.php).
 */
function subirRespaldoAGoogleDrive(mysqli $conn, string $rutaLocal, string $nombreArchivo): array
{
    require_once __DIR__ . '/google_config.php';
    require_once __DIR__ . '/cifrado_helper.php';

    $config = $conn->query('SELECT google_refresh_token FROM configuracion_sistema WHERE id = 1')->fetch_assoc();
    $refreshTokenCifrado = $config['google_refresh_token'] ?? null;

    if ($refreshTokenCifrado === null) {
        return ['exitoso' => false, 'error' => 'No hay ninguna cuenta de Google Drive conectada. Ve al módulo de Respaldo y conéctala primero.'];
    }

    $refreshToken = descifrar($refreshTokenCifrado);
    if ($refreshToken === null) {
        return ['exitoso' => false, 'error' => 'El token guardado está corrupto o fue cifrado con una clave distinta. Vuelve a conectar la cuenta de Google Drive.'];
    }

    // 1. Cambiar el refresh_token por un access_token de corta duración
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_POSTFIELDS     => http_build_query([
            'client_id'     => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'refresh_token' => $refreshToken,
            'grant_type'    => 'refresh_token',
        ]),
    ]);
    $respuesta = json_decode((string) curl_exec($ch), true);
    curl_close($ch);

    $accessToken = $respuesta['access_token'] ?? null;
    if ($accessToken === null) {
        $motivo = $respuesta['error_description'] ?? $respuesta['error'] ?? 'motivo desconocido';
        return ['exitoso' => false, 'error' => "No se pudo renovar el acceso a Google Drive: $motivo. Puede que hayas revocado el permiso — reconéctala."];
    }

    // 2. Subir el archivo (multipart: metadata + contenido en una sola petición)
    $metadata = json_encode(['name' => $nombreArchivo]);
    $boundary = 'respaldo_' . uniqid();
    $contenidoArchivo = file_get_contents($rutaLocal);

    $body = "--$boundary\r\n"
        . "Content-Type: application/json; charset=UTF-8\r\n\r\n"
        . $metadata . "\r\n"
        . "--$boundary\r\n"
        . "Content-Type: application/sql\r\n\r\n"
        . $contenidoArchivo . "\r\n"
        . "--$boundary--";

    $chUpload = curl_init('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart');
    curl_setopt_array($chUpload, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: multipart/related; boundary=' . $boundary,
        ],
        CURLOPT_POSTFIELDS => $body,
    ]);
    $respuestaSubida = curl_exec($chUpload);
    $httpCode = curl_getinfo($chUpload, CURLINFO_HTTP_CODE);
    curl_close($chUpload);

    if ($httpCode >= 200 && $httpCode < 300) {
        return ['exitoso' => true];
    }

    return ['exitoso' => false, 'error' => "Google Drive respondió HTTP $httpCode: $respuestaSubida"];
}

/**
 * ⚠️ OneDrive — sigue sin implementar (mismo alcance que antes):
 *   1. Registrar una app en https://portal.azure.com/ (Azure AD)
 *   2. Dar permisos "Files.ReadWrite" de Microsoft Graph
 *   3. Implementar el flujo OAuth 2.0 (muy similar a lo que ya hicimos con Google:
 *      un endpoint que redirige a Microsoft, un callback que intercambia el
 *      código por tokens, y guardar el refresh_token cifrado igual que aquí)
 *   4. Subir el archivo vía PUT a la API de Microsoft Graph
 *      (/me/drive/root:/ruta:/content)
 *
 * Si quieres, seguimos con esto usando el mismo patrón que ya armamos
 * para Google — solo necesitas crear la app en Azure Portal primero.
 */