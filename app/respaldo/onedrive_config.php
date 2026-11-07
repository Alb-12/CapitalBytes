<?php

declare(strict_types=1);

/**
 * ⚠️ REEMPLAZA estos 2 valores con los que te dio Azure Portal
 * (App registrations → tu app → Overview / Certificates & secrets).
 *
 * Este archivo NUNCA debe subirse a un repositorio público con las
 * credenciales reales puestas.
 */
define('ONEDRIVE_CLIENT_ID', 'TU_CLIENT_ID_AQUI');
define('ONEDRIVE_CLIENT_SECRET', 'TU_CLIENT_SECRET_AQUI');

// Files.ReadWrite: solo archivos, no acceso administrativo a la cuenta.
// offline_access: necesario para recibir un refresh_token.
define('ONEDRIVE_SCOPE', 'Files.ReadWrite offline_access User.Read');

/**
 * 'common' permite tanto cuentas personales de Microsoft (Outlook/Hotmail)
 * como cuentas empresariales/educativas — es lo que quieres para que
 * cualquier cliente pueda conectar la cuenta que use.
 */
define('ONEDRIVE_AUTHORITY', 'https://login.microsoftonline.com/common');

/**
 * Redirect URI calculado automáticamente según el host actual — mismo
 * patrón que google_config.php, funciona igual en localhost y en producción
 * sin cambiar código, sin importar en qué subcarpeta (o en la raíz)
 * esté desplegado el proyecto — no hay que ajustar ninguna ruta a mano.
 * ⚠️ Debes registrar esta URL exacta (con https:// cuando tengas dominio)
 * en Azure Portal → tu app → Authentication → Redirect URIs.
 */
function oneDriveRedirectUri(): string
{
    $protocolo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $carpeta = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    return "$protocolo://$host$carpeta/onedrive_callback.php";
}
