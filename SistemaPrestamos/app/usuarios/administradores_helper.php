<?php

declare(strict_types=1);

/**
 * Reglas de negocio para el rol de Administrador:
 *   - Máximo 2 cuentas con rol Administrador en todo el sistema (cuenta
 *     cuentas Activas e Inactivas, para no permitir "acumular" más de 2).
 *   - Nunca puede quedar el sistema con 0 administradores ACTIVOS (una
 *     cuenta Inactiva no cuenta como funcional, porque no puede iniciar sesión).
 */
const MAX_ADMINISTRADORES = 2;

/**
 * Devuelve el id_rol correspondiente a 'Administrador', o null si no existe
 * ese rol en la tabla roles (lo cual sería un problema de configuración).
 */
function obtenerIdRolAdministrador(mysqli $conn): ?int
{
    $res = $conn->query("SELECT id_rol FROM roles WHERE nombre_rol = 'Administrador' LIMIT 1");
    $fila = $res->fetch_assoc();
    return $fila ? (int) $fila['id_rol'] : null;
}

/**
 * Cuenta cuántos usuarios tienen el rol de administrador.
 *
 * @param bool     $soloActivos Si true, cuenta solo los que tienen estado = 'Activo'.
 * @param int|null $excluirId   Id de usuario a excluir del conteo (útil al editar/eliminar
 *                              ese mismo usuario, para saber cuántos OTROS admins quedan).
 */
function contarAdministradores(mysqli $conn, int $idRolAdmin, bool $soloActivos = false, ?int $excluirId = null): int
{
    $sql = 'SELECT COUNT(*) AS total FROM usuarios WHERE id_rol = ?';
    $types = 'i';
    $params = [$idRolAdmin];

    if ($soloActivos) {
        $sql .= " AND estado = 'Activo'";
    }
    if ($excluirId !== null) {
        $sql .= ' AND id_usuario != ?';
        $types .= 'i';
        $params[] = $excluirId;
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $total = (int) $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    return $total;
}