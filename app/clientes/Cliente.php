<?php

declare(strict_types=1);

require_once __DIR__ . '/ValidationException.php';


final class Cliente
{
    public function __construct(
        public ?int $id = null,
        public string $nombre = '',
        public string $apellido = '',
        public string $cedula = '',
        public string $direccion = '',
        public string $telefono = '',
        public string $correo = '',
        public ?string $fotografia = null,
        public string $nombreGarante = '',
        public string $telefonoGarante = '',
        public string $cedulaGarante = '',
    ) {
    }

    /**
     * @param array<string, mixed> $datos
     */
    public static function fromArray(array $datos, bool $requiereId = false): self
    {
        $cliente = new self();

        if ($requiereId) {
            if (empty($datos['id']) || !ctype_digit((string) $datos['id'])) {
                throw new ValidationException('ID del cliente no proporcionado o inválido', 'id');
            }
            $cliente->id = (int) $datos['id'];
        }

        
        $camposRequeridos = [
            'nombre'           => 'nombre',
            'apellido'         => 'apellido',
            'cedula'           => 'cedula',
            'direccion'        => 'direccion',
            'telefono'         => 'telefono',
            'correo'           => 'correo',
            'nombre_garante'   => 'nombreGarante',
            'telefono_garante' => 'telefonoGarante',
            'cedula_garante'   => 'cedulaGarante',
        ];

        foreach ($camposRequeridos as $campoOrigen => $propiedad) {
            $valor = trim((string) ($datos[$campoOrigen] ?? ''));
            if ($valor === '') {
                throw new ValidationException('Este campo es requerido', $campoOrigen);
            }
            $cliente->$propiedad = $valor;
        }

        if (!filter_var($cliente->correo, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException('El correo no es válido', 'correo');
        }

        if (!ctype_digit($cliente->cedula)) {
            throw new ValidationException('La cédula debe contener solo números', 'cedula');
        }

        if (!ctype_digit($cliente->cedulaGarante)) {
            throw new ValidationException('La cédula debe contener solo números', 'cedula_garante');
        }

        return $cliente;
    }
}
