<?php
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
    }

    $diasPlazo = [
        'Diario'    => 1,
        'Semanal'   => 7,
        'Quincenal' => 15,
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
        $contStmt->close();

        // Calcular fecha esperada del próximo pago
        $dias        = $diasPlazo[$p['modalidad_pagos']] ?? 30;
        $proximoPago = date('Y-m-d', strtotime(
            $p['fecha_inicio'] . ' + ' . (($pagosHechos + 1) * $dias) . ' days'
        ));

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