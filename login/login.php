<?php
session_start();
// Si ya está logueado, redirigir al dashboard
if (isset($_SESSION['usuario'])) {
    header('Location: ../dashboard.php');
    exit;
}
$error = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión — PRESTA-APP</title>
    <link rel="stylesheet" href="../db/style.css">
</head>
<body>

<div class="login-wrapper">
    <div class="login-card">
        <h2>PRESTA-APP</h2>
        <p>Ingresa tus credenciales para continuar</p>

        <?php if ($error): ?>
            <div class="login-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form action="login_proceso.php" method="POST">
            
            <input type="text" name="nombre_usuario" placeholder="usuario" required autofocus>
            <input type="password" name="contrasena" placeholder="contraseña" required>
            <button type="submit">Iniciar Sesión</button>
        </form>
    </div>
</div>
<script src="login.js"></script>
</body>
</html>