<?php

declare(strict_types=1);

require_once __DIR__ . '/Cliente.php';


final class ClienteRepository
{
    public function __construct(private readonly mysqli $conn)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function obtenerTodos(): array
    {
        $result = $this->conn->query('SELECT * FROM clientes ORDER BY nombre, apellido');

        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /** @return array<string, mixed>|null */
    public function buscarPorCedula(string $cedula): ?array
    {
        $stmt = $this->conn->prepare('SELECT * FROM clientes WHERE cedula = ?');
        $stmt->bind_param('s', $cedula);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $fila ?: null;
    }

    public function existeId(int $id): bool
    {
        $stmt = $this->conn->prepare('SELECT 1 FROM clientes WHERE id_cliente = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $existe = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        return $existe;
    }

    public function idClientePorCedula(string $cedula): ?int
    {
        $stmt = $this->conn->prepare('SELECT id_cliente FROM clientes WHERE cedula = ?');
        $stmt->bind_param('s', $cedula);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $fila ? (int) $fila['id_cliente'] : null;
    }

    public function tienePrestamosActivos(int $idCliente): bool
    {
        $stmt = $this->conn->prepare(
            "SELECT COUNT(*) AS total FROM prestamos WHERE cliente_id = ? AND estado = 'Activo'"
        );
        $stmt->bind_param('i', $idCliente);
        $stmt->execute();
        $total = (int) $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();

        return $total > 0;
    }

    public function insertar(Cliente $cliente): void
    {
        $sql = "INSERT INTO clientes (
                    nombre, apellido, cedula, direccion,
                    telefono, correo, fotografia,
                    nombre_garante, telefono_garante, cedula_garante
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param(
            'ssssssssss',
            $cliente->nombre,
            $cliente->apellido,
            $cliente->cedula,
            $cliente->direccion,
            $cliente->telefono,
            $cliente->correo,
            $cliente->fotografia,
            $cliente->nombreGarante,
            $cliente->telefonoGarante,
            $cliente->cedulaGarante
        );
        $stmt->execute();
        $stmt->close();
    }

    /** @return int Filas afectadas, igual que $stmt->affected_rows antes */
    public function actualizar(Cliente $cliente): int
    {
        $sql = "UPDATE clientes SET
                    nombre           = ?,
                    apellido         = ?,
                    cedula           = ?,
                    direccion        = ?,
                    telefono         = ?,
                    correo           = ?,
                    nombre_garante   = ?,
                    telefono_garante = ?,
                    cedula_garante   = ?
                WHERE id_cliente = ?";

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param(
            'sssssssssi',
            $cliente->nombre,
            $cliente->apellido,
            $cliente->cedula,
            $cliente->direccion,
            $cliente->telefono,
            $cliente->correo,
            $cliente->nombreGarante,
            $cliente->telefonoGarante,
            $cliente->cedulaGarante,
            $cliente->id
        );
        $stmt->execute();
        $filasAfectadas = $stmt->affected_rows;
        $stmt->close();

        return $filasAfectadas;
    }

    public function eliminarPorId(int $id): void
    {
        $stmt = $this->conn->prepare('DELETE FROM clientes WHERE id_cliente = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    }
}
