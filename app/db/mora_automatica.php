<?php
<<<<<<< HEAD

declare(strict_types=1);


function aplicarMoraAutomatica(mysqli $conn): void
{
      if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $hoy = date('Y-m-d');

    // Solo ejecutar una vez por día por sesión
    if (($_SESSION['mora_verificada'] ?? null) === $hoy) {
        return;
=======
/**
 * mora_automatica.php
 * Incluir este archivo en cualquier módulo donde quieras
 * que se verifique la mora automáticamente.
 * Usa la sesión para no ejecutarse más de una vez por día,
 * evitando sobrecarga en la base de datos.
 */
function aplicarMoraAutomatica($conn) {
    $hoy = date('Y-m-d');
    // Solo ejecutar una vez por día por sesión
    if (isset($_SESSION['mora_verificada']) && $_SESSION['mora_verificada'] === $hoy) {
        return; // Ya se ejecutó hoy
>>>>>>> 4a61193eab241d7dfebf3dae54399c65e6f95608
    }

    $diasPlazo = [
        'Diario'    => 1,
        'Semanal'   => 7,
        'Quincenal' => 15,
<<<<<<< HEAD
        'Mensual'   => 30,
    ];

    $res = $conn->query(
        "SELECT id_prestamo, fecha_inicio, modalidad_pagos
         FROM prestamos
         WHERE estado = 'Activo'"
    );

    $idsParaMora = [];

    while ($p = $res->fetch_assoc()) {
        // Contar pagos ya realizados
        $contStmt = $conn->prepare('SELECT COUNT(*) AS total FROM pagos WHERE id_prestamo = ?');
        $contStmt->bind_param('i', $p['id_prestamo']);
        $contStmt->execute();
        $pagosHechos = (int) $contStmt->get_result()->fetch_assoc()['total'];
=======
        'Mensual'   => 30
    ];

    $sql = "SELECT id_prestamo, fecha_inicio, modalidad_pagos
            FROM prestamos
            WHERE estado = 'Activo'";

    $res = $conn->query($sql);
    if (!$res) return;

    while ($p = $res->fetch_assoc()) {
        // Contar pagos ya realizados
        $contStmt = $conn->prepare(
            "SELECT COUNT(*) as total FROM pagos WHERE id_prestamo = ?"
        );
        $contStmt->bind_param("i", $p['id_prestamo']);
        $contStmt->execute();
        $pagosHechos = $contStmt->get_result()->fetch_assoc()['total'];
>>>>>>> 4a61193eab241d7dfebf3dae54399c65e6f95608
        $contStmt->close();

        // Calcular fecha esperada del próximo pago
        $dias        = $diasPlazo[$p['modalidad_pagos']] ?? 30;
        $proximoPago = date('Y-m-d', strtotime(
            $p['fecha_inicio'] . ' + ' . (($pagosHechos + 1) * $dias) . ' days'
        ));

<<<<<<< HEAD
        if ($proximoPago < $hoy) {
            $idsParaMora[] = (int) $p['id_prestamo'];
        }
    }

     if (!empty($idsParaMora)) {
        $marcadores = implode(',', array_fill(0, count($idsParaMora), '?'));
        $tipos      = str_repeat('i', count($idsParaMora));

        $updStmt = $conn->prepare("UPDATE prestamos SET estado = 'Mora' WHERE id_prestamo IN ($marcadores)");
        $updStmt->bind_param($tipos, ...$idsParaMora);
        $updStmt->execute();
        $updStmt->close();
    }

    // Marcar que ya se ejecutó hoy
    $_SESSION['mora_verificada'] = $hoy;
}
=======
        // Si ya venció → marcar como Mora
        if ($proximoPago < $hoy) {
            $updStmt = $conn->prepare(
                "UPDATE prestamo SET estado = 'Mora' WHERE id_prestamo = ?"
            );
            $updStmt->bind_param("i", $p['id_prestamo']);
            $updStmt->execute();
            $updStmt->close();
        }
    }

    // Marcar que ya se ejecutó hoy
    $_SESSION['mora_verificada'] = $hoy;
}
?>
>>>>>>> 4a61193eab241d7dfebf3dae54399c65e6f95608
