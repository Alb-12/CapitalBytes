<?php

declare(strict_types=1);

require_once __DIR__ . '/google_config.php';

/**
 * Cifra un texto (ej. un refresh_token) usando AES-256-CBC con la clave
 * definida en APP_ENCRYPTION_KEY. El IV se genera aleatorio en cada
 * llamada y se guarda junto con el texto cifrado (es seguro hacerlo así,
 * el IV no es secreto, solo debe ser único por cada cifrado).
 */
function cifrar(string $texto): string
{
    $iv = random_bytes(16);
    $cifrado = openssl_encrypt($texto, 'aes-256-cbc', hex2bin(APP_ENCRYPTION_KEY), OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $cifrado);
}

/**
 * Descifra un texto generado por cifrar(). Devuelve null si el texto está
 * corrupto, vacío, o fue cifrado con una clave distinta a la actual.
 */
function descifrar(?string $textoCifrado): ?string
{
    if ($textoCifrado === null || $textoCifrado === '') {
        return null;
    }

    $datos = base64_decode($textoCifrado, true);
    if ($datos === false || strlen($datos) < 17) {
        return null;
    }

    $iv = substr($datos, 0, 16);
    $cifrado = substr($datos, 16);
    $resultado = openssl_decrypt($cifrado, 'aes-256-cbc', hex2bin(APP_ENCRYPTION_KEY), OPENSSL_RAW_DATA, $iv);

    return $resultado === false ? null : $resultado;
}