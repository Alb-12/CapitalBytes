<?php

declare(strict_types=1);

require_once __DIR__ . '/ValidationException.php';


final class FotografiaUploader
{
    private const TAMANO_MAXIMO = 5 * 1024 * 1024; // 5 MB

    private const EXTENSIONES_PERMITIDAS = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(private readonly string $directorioDestino)
    {
    }

    /**
     * @param array<string, 
     * @return string|null
     */
    public function subir(array $archivo): ?string
    {
        if ($archivo['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ($archivo['error'] !== UPLOAD_ERR_OK) {
            throw new ValidationException('Error al subir la fotografía', 'foto');
        }

        if ($archivo['size'] > self::TAMANO_MAXIMO) {
            throw new ValidationException('La fotografía no debe superar 5 MB', 'foto');
        }

        $tipoReal = getimagesize($archivo['tmp_name']);
        if ($tipoReal === false || !isset(self::EXTENSIONES_PERMITIDAS[$tipoReal['mime']])) {
            throw new ValidationException('El archivo debe ser una imagen JPG, PNG o WEBP', 'foto');
        }

        $extension    = self::EXTENSIONES_PERMITIDAS[$tipoReal['mime']];
        $nombreUnico  = bin2hex(random_bytes(16)) . '.' . $extension;
        $rutaAbsoluta = rtrim($this->directorioDestino, '/') . '/' . $nombreUnico;

        if (!is_dir($this->directorioDestino) && !mkdir($this->directorioDestino, 0755, true) && !is_dir($this->directorioDestino)) {
            throw new \RuntimeException('No se pudo preparar el directorio de subida');
        }

        if (!move_uploaded_file($archivo['tmp_name'], $rutaAbsoluta)) {
            throw new \RuntimeException('Error al subir la fotografía');
        }

        return 'uploads/' . $nombreUnico;
    }
}
