<?php

declare(strict_types=1);

session_start();

// ═══════════════════════════════════════════════════════════════════
// GUARDA: si ya existe db/db.php y conecta correctamente, el sistema
// ya está instalado — no dejamos correr el instalador de nuevo (podría
// borrar/duplicar datos de un cliente que ya está usando el sistema).
// ═══════════════════════════════════════════════════════════════════
$rutaDbPhp = __DIR__ . '/db/db.php';
if (file_exists($rutaDbPhp)) {
    $yaFunciona = false;
    try {
        ob_start();
        include $rutaDbPhp;
        ob_end_clean();
        $yaFunciona = isset($conn) && $conn instanceof mysqli && !$conn->connect_error;
    } catch (Throwable $e) {
        $yaFunciona = false;
    }

    if ($yaFunciona) {
        header('Location: login/login.php');
        exit;
    }
    // Si db.php existe pero NO conecta (ej. credenciales viejas de una
    // instalación fallida a medias), dejamos seguir con el instalador.
}

$errores = [];
$exito   = false;

// Si este archivo se está ejecutando dentro del paquete de escritorio
// (ver el launcher .bat / setup.iss), hay un archivo marcador junto a
// install.php. En ese caso, la conexión a la base de datos SIEMPRE es la
// MariaDB local empaquetada — no tiene sentido pedirle esos datos
// técnicos a un cliente final sin conocimientos de servidores.
$esVersionEscritorio = file_exists(__DIR__ . '/.desktop_package');
$dbHostDefault = $esVersionEscritorio ? '127.0.0.1' : 'localhost';
$dbPortDefault = $esVersionEscritorio ? '3307' : '3306';
$dbUserDefault = 'root';
$dbNameDefault = 'presta_app';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost = trim($_POST['db_host'] ?? '');
    $dbPort = trim($_POST['db_port'] ?? '3306');
    $dbUser = trim($_POST['db_user'] ?? '');
    $dbPass = $_POST['db_pass'] ?? '';
    $dbName = trim($_POST['db_name'] ?? '');

    $nombreEmpresa = trim($_POST['nombre_empresa'] ?? '');
    $adminUsuario  = trim($_POST['admin_usuario'] ?? '');
    $adminPass     = $_POST['admin_pass'] ?? '';
    $adminPassConf = $_POST['admin_pass_confirmar'] ?? '';

    // ── Validaciones ────────────────────────────────────────────────
    if ($dbHost === '' || $dbUser === '' || $dbName === '') {
        $errores[] = 'Completa los datos de conexión a la base de datos (host, usuario, nombre de BD).';
    }
    if ($nombreEmpresa === '') {
        $errores[] = 'El nombre de la empresa es requerido.';
    }
    if ($adminUsuario === '' || strlen($adminUsuario) < 3) {
        $errores[] = 'El usuario administrador debe tener al menos 3 caracteres.';
    }
    if (strlen($adminPass) < 8) {
        $errores[] = 'La contraseña del administrador debe tener al menos 8 caracteres.';
    }
    if ($adminPass !== $adminPassConf) {
        $errores[] = 'Las contraseñas no coinciden.';
    }
    if (!ctype_digit($dbPort)) {
        $errores[] = 'El puerto debe ser un número.';
    }
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $dbName)) {
        $errores[] = 'El nombre de la base de datos solo puede tener letras, números y guiones bajos.';
    }

    if (empty($errores)) {
        try {
            // 1. Conectar SIN seleccionar base de datos todavía, para poder crearla
            $conexionInicial = @new mysqli($dbHost, $dbUser, $dbPass, '', (int) $dbPort);
            if ($conexionInicial->connect_error) {
                throw new RuntimeException('No se pudo conectar al servidor de MySQL: ' . $conexionInicial->connect_error);
            }

            $conexionInicial->query(
                "CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
            );
            $conexionInicial->close();

            // 2. Conectar YA a la base de datos recién creada
            $conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName, (int) $dbPort);
            if ($conn->connect_error) {
                throw new RuntimeException('No se pudo conectar a la base de datos: ' . $conn->connect_error);
            }
            $conn->set_charset('utf8mb4');
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

            // 3. Crear todas las tablas (schema embebido aquí mismo, no
            // depende de encontrar un archivo .sql aparte en el disco)
            $schema = obtenerSchemaCompleto();
            foreach (explode(';', $schema) as $sentencia) {
                $sentencia = trim($sentencia);
                if ($sentencia !== '') {
                    $conn->query($sentencia);
                }
            }

            // 4. Crear el usuario administrador inicial
            $checkAdmin = $conn->query("SELECT id_rol FROM roles WHERE nombre_rol = 'Administrador'");
            $idRolAdmin = (int) $checkAdmin->fetch_assoc()['id_rol'];

            $hashAdmin = password_hash($adminPass, PASSWORD_DEFAULT);
            $ahora     = date('Y-m-d H:i:s');

            $stmt = $conn->prepare(
                'INSERT INTO usuarios (nombre_usuario, contrasena, id_rol, estado, intentos_fallidos, bloqueado, fecha_registro)
                 VALUES (?, ?, ?, "Activo", 0, 0, ?)'
            );
            $stmt->bind_param('ssis', $adminUsuario, $hashAdmin, $idRolAdmin, $ahora);
            $stmt->execute();
            $stmt->close();

            // 5. Guardar el nombre de la empresa en configuracion_sistema
            $stmt = $conn->prepare('UPDATE configuracion_sistema SET nombre_empresa = ? WHERE id = 1');
            $stmt->bind_param('s', $nombreEmpresa);
            $stmt->execute();
            $stmt->close();

            $conn->close();

            // 6. Escribir db/db.php con las credenciales reales
            if (!is_dir(__DIR__ . '/db')) {
                mkdir(__DIR__ . '/db', 0755, true);
            }
            escribirDbPhp($rutaDbPhp, $dbHost, $dbUser, $dbPass, $dbName, $dbPort);

            // 7. Generar y escribir la clave de cifrado para
            // respaldo/google_config.php (Google Drive / OneDrive quedan
            // sin conectar hasta que el cliente configure sus propias
            // credenciales OAuth desde el módulo de Respaldo).
            if (is_dir(__DIR__ . '/respaldo') && !file_exists(__DIR__ . '/respaldo/google_config.php')) {
                escribirGoogleConfig(__DIR__ . '/respaldo/google_config.php');
            }

            // 8. Preparar la carpeta de respaldos
            if (is_dir(__DIR__ . '/respaldo') && !is_dir(__DIR__ . '/respaldo/backups')) {
                mkdir(__DIR__ . '/respaldo/backups', 0755, true);
            }

            $exito = true;
        } catch (Throwable $e) {
            $errores[] = 'Error durante la instalación: ' . $e->getMessage();
        }
    }
}

/**
 * El schema completo, como string SQL. Vive aquí embebido (no en un
 * archivo .sql aparte) para que el instalador no dependa de encontrar
 * ningún otro archivo en el disco — una sola pieza autocontenida.
 */
function obtenerSchemaCompleto(): string
{
    return <<<'SQL'
CREATE TABLE IF NOT EXISTS roles (
    id_rol     INT AUTO_INCREMENT PRIMARY KEY,
    nombre_rol VARCHAR(50) NOT NULL UNIQUE
);

INSERT INTO roles (nombre_rol) SELECT 'Administrador' WHERE NOT EXISTS (SELECT 1 FROM roles WHERE nombre_rol = 'Administrador');
INSERT INTO roles (nombre_rol) SELECT 'Supervisor' WHERE NOT EXISTS (SELECT 1 FROM roles WHERE nombre_rol = 'Supervisor');
INSERT INTO roles (nombre_rol) SELECT 'Cobrador' WHERE NOT EXISTS (SELECT 1 FROM roles WHERE nombre_rol = 'Cobrador');

CREATE TABLE IF NOT EXISTS usuarios (
    id_usuario        INT AUTO_INCREMENT PRIMARY KEY,
    nombre_usuario    VARCHAR(100) NOT NULL UNIQUE,
    contrasena        VARCHAR(255) NOT NULL,
    id_rol            INT NOT NULL,
    estado            ENUM('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
    intentos_fallidos INT NOT NULL DEFAULT 0,
    bloqueado         TINYINT(1) NOT NULL DEFAULT 0,
    ultimo_login      DATETIME NULL,
    fecha_registro    DATETIME NOT NULL,
    FOREIGN KEY (id_rol) REFERENCES roles(id_rol)
);

CREATE TABLE IF NOT EXISTS clientes (
    id_cliente        INT AUTO_INCREMENT PRIMARY KEY,
    cedula            VARCHAR(20) NOT NULL UNIQUE,
    nombre            VARCHAR(100) NOT NULL,
    apellido          VARCHAR(100) NOT NULL,
    direccion         VARCHAR(255) NOT NULL,
    telefono          VARCHAR(30) NOT NULL,
    correo            VARCHAR(150) NOT NULL,
    fotografia        VARCHAR(255) NULL,
    nombre_garante    VARCHAR(150) NOT NULL,
    telefono_garante  VARCHAR(30) NOT NULL,
    cedula_garante    VARCHAR(20) NOT NULL
);

CREATE TABLE IF NOT EXISTS prestamos (
    id_prestamo      INT AUTO_INCREMENT PRIMARY KEY,
    id_cliente       INT NOT NULL,
    monto            DECIMAL(12,2) NOT NULL,
    tasa_interes     DECIMAL(6,2) NOT NULL,
    tipo_interes     ENUM('Simple','Compuesto') NOT NULL,
    modalidad_pagos  ENUM('Diario','Semanal','Quincenal','Mensual') NOT NULL,
    plazo            INT NOT NULL,
    cuota_monto      DECIMAL(12,2) NOT NULL,
    saldo_pendiente  DECIMAL(12,2) NOT NULL,
    mora_porcentaje  DECIMAL(6,2) NOT NULL DEFAULT 0,
    fecha_inicio     DATE NOT NULL,
    fecha_fin        DATE NULL,
    fecha_registro   DATETIME NOT NULL,
    estado           ENUM('Activo','Pagado','Mora','Cancelado') NOT NULL DEFAULT 'Activo',
    FOREIGN KEY (id_cliente) REFERENCES clientes(id_cliente)
);

CREATE TABLE IF NOT EXISTS pagos (
    id_pagos         INT AUTO_INCREMENT PRIMARY KEY,
    id_prestamo      INT NOT NULL,
    fecha_pago       DATETIME NOT NULL,
    monto_pagado     DECIMAL(12,2) NOT NULL,
    monto_mora       DECIMAL(12,2) NOT NULL DEFAULT 0,
    monto_interes    DECIMAL(12,2) NOT NULL DEFAULT 0,
    monto_capital    DECIMAL(12,2) NOT NULL DEFAULT 0,
    saldo_restante   DECIMAL(12,2) NOT NULL,
    registrado_por   INT NOT NULL,
    FOREIGN KEY (id_prestamo) REFERENCES prestamos(id_prestamo),
    FOREIGN KEY (registrado_por) REFERENCES usuarios(id_usuario)
);

CREATE TABLE IF NOT EXISTS caja (
    id_caja          INT AUTO_INCREMENT PRIMARY KEY,
    tipo_movimiento  ENUM('Entrada','Salida') NOT NULL,
    origen           VARCHAR(50) NOT NULL,
    id_referencia    INT NULL,
    monto            DECIMAL(12,2) NOT NULL,
    descripcion      VARCHAR(255) NOT NULL,
    fecha            DATETIME NOT NULL,
    id_usuario       INT NOT NULL,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
);

CREATE TABLE IF NOT EXISTS prestamos_redito (
    id_prestamo_redito INT AUTO_INCREMENT PRIMARY KEY,
    id_cliente          INT NOT NULL,
    monto_capital        DECIMAL(12,2) NOT NULL,
    saldo_capital         DECIMAL(12,2) NOT NULL,
    tasa_redito           DECIMAL(6,2)  NOT NULL DEFAULT 200.00,
    modalidad_pagos       ENUM('Diario','Semanal','Quincenal','Mensual') NOT NULL,
    estado                ENUM('Activo','Pagado','Cancelado') NOT NULL DEFAULT 'Activo',
    fecha_inicio           DATE NOT NULL,
    fecha_registro         DATETIME NOT NULL,
    registrado_por         INT NULL,
    FOREIGN KEY (id_cliente) REFERENCES clientes(id_cliente),
    FOREIGN KEY (registrado_por) REFERENCES usuarios(id_usuario) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS pagos_redito (
    id_pago_redito      INT AUTO_INCREMENT PRIMARY KEY,
    id_prestamo_redito   INT NOT NULL,
    tipo_pago             ENUM('Interes','Abono_Capital') NOT NULL,
    monto                  DECIMAL(12,2) NOT NULL,
    saldo_capital_resultante DECIMAL(12,2) NOT NULL,
    fecha_pago             DATETIME NOT NULL,
    registrado_por         INT NULL,
    FOREIGN KEY (id_prestamo_redito) REFERENCES prestamos_redito(id_prestamo_redito),
    FOREIGN KEY (registrado_por) REFERENCES usuarios(id_usuario) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS configuracion_sistema (
    id                       INT PRIMARY KEY DEFAULT 1,
    mora_porcentaje_default DECIMAL(5,2)  NOT NULL DEFAULT 5.00,
    dias_gracia              INT           NOT NULL DEFAULT 0,
    backup_automatico        TINYINT(1)    NOT NULL DEFAULT 0,
    backup_frecuencia        ENUM('Diario','Semanal','Mensual') NOT NULL DEFAULT 'Diario',
    backup_hora               TIME          NOT NULL DEFAULT '02:00:00',
    backup_destino            ENUM('Local','GoogleDrive','OneDrive') NOT NULL DEFAULT 'Local',
    backup_ultima_ejecucion   DATETIME      NULL,
    google_refresh_token      TEXT          NULL,
    google_email              VARCHAR(255)  NULL,
    onedrive_refresh_token    TEXT          NULL,
    onedrive_email            VARCHAR(255)  NULL,
    nombre_empresa            VARCHAR(150)  NOT NULL DEFAULT 'PRESTA-APP',
    whatsapp_token            TEXT          NULL,
    whatsapp_phone_id         VARCHAR(100)  NULL,
    whatsapp_api_version      VARCHAR(20)   NOT NULL DEFAULT 'v19.0',
    actualizado_por           INT           NULL,
    actualizado_en            DATETIME      NULL,
    CONSTRAINT chk_config_singleton CHECK (id = 1)
);

INSERT INTO configuracion_sistema (id) SELECT 1 WHERE NOT EXISTS (SELECT 1 FROM configuracion_sistema WHERE id = 1);

CREATE TABLE IF NOT EXISTS respaldos (
    id_respaldo    INT AUTO_INCREMENT PRIMARY KEY,
    archivo        VARCHAR(255) NOT NULL,
    tamano_bytes   BIGINT NULL,
    destino        ENUM('Local','GoogleDrive','OneDrive') NOT NULL DEFAULT 'Local',
    tipo           ENUM('Manual','Automatico') NOT NULL,
    estado         ENUM('Exitoso','Fallido') NOT NULL,
    mensaje_error  TEXT NULL,
    generado_por   INT NULL,
    fecha          DATETIME NOT NULL,
    FOREIGN KEY (generado_por) REFERENCES usuarios(id_usuario) ON DELETE SET NULL
);
SQL;
}

function escribirDbPhp(string $ruta, string $host, string $user, string $pass, string $dbName, string $port): void
{
    $hostEsc = addslashes($host);
    $userEsc = addslashes($user);
    $passEsc = addslashes($pass);
    $dbEsc   = addslashes($dbName);
    $portInt = (int) $port;

    $contenido = <<<PHP
<?php

declare(strict_types=1);

\$host = '$hostEsc';
\$user = '$userEsc';
\$pass = '$passEsc';
\$db   = '$dbEsc';
\$port = $portInt;

\$conn = @new mysqli(\$host, \$user, \$pass, \$db, \$port);

if (\$conn->connect_error) {
    error_log('Error de conexión a la base de datos: ' . \$conn->connect_error);
    http_response_code(500);

    \$headers = headers_list();
    \$esJson  = false;
    foreach (\$headers as \$h) {
        if (stripos(\$h, 'application/json') !== false) {
            \$esJson = true;
            break;
        }
    }

    if (\$esJson) {
        echo json_encode(['status' => 'error', 'mensaje' => 'No se pudo conectar a la base de datos']);
    } else {
        echo 'No se pudo conectar a la base de datos. Intenta de nuevo más tarde.';
    }
    exit;
}

\$conn->set_charset('utf8mb4');

PHP;

    file_put_contents($ruta, $contenido);
}

function escribirGoogleConfig(string $ruta): void
{
    $claveGenerada = bin2hex(random_bytes(32));

    $contenido = <<<PHP
<?php

declare(strict_types=1);

// ⚠️ Reemplaza estos 2 valores cuando conectes Google Drive desde el
// módulo de Respaldo (Google Cloud Console → Google Auth Platform → Clients).
define('GOOGLE_CLIENT_ID', 'TU_CLIENT_ID_AQUI.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'TU_CLIENT_SECRET_AQUI');

// Generada automáticamente por el instalador — no la cambies a menos que
// sepas que vas a perder acceso a cualquier token ya guardado.
define('APP_ENCRYPTION_KEY', '$claveGenerada');

define('GOOGLE_SCOPE', 'https://www.googleapis.com/auth/drive.file https://www.googleapis.com/auth/userinfo.email');
define('ONEDRIVE_CLIENT_ID', 'TU_CLIENT_ID_AQUI');
define('ONEDRIVE_CLIENT_SECRET', 'TU_CLIENT_SECRET_AQUI');
define('ONEDRIVE_SCOPE', 'Files.ReadWrite offline_access User.Read');
define('ONEDRIVE_AUTHORITY', 'https://login.microsoftonline.com/common');

function googleRedirectUri(): string
{
    \$protocolo = (!empty(\$_SERVER['HTTPS']) && \$_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    \$host = \$_SERVER['HTTP_HOST'] ?? 'localhost';
    return "\$protocolo://\$host/SistemaPrestamo/respaldo/google_callback.php";
}

function oneDriveRedirectUri(): string
{
    \$protocolo = (!empty(\$_SERVER['HTTPS']) && \$_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    \$host = \$_SERVER['HTTP_HOST'] ?? 'localhost';
    return "\$protocolo://\$host/SistemaPrestamo/respaldo/onedrive_callback.php";
}

PHP;

    file_put_contents($ruta, $contenido);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalación — PRESTA-APP</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
          integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
</head>
<body class="bg-light">
<div class="container py-5" style="max-width: 640px;">
    <div class="card shadow-sm border-0">
        <div class="card-body p-4">
            <h2 class="mb-1">Instalación de PRESTA-APP</h2>
            <p class="text-muted">Este asistente configura tu base de datos y crea el primer usuario administrador.</p>
            <hr>

            <?php if ($exito): ?>
                <div class="alert alert-success">
                    ✔ Instalación completada correctamente. Ya puedes iniciar sesión.
                </div>
                <a href="login/login.php" class="btn btn-primary">Ir a Iniciar Sesión</a>
                <p class="text-muted small mt-3">
                    ⚠️ Por seguridad, borra o renombra este archivo (<code>install.php</code>) ahora
                    que la instalación terminó — así nadie más puede volver a ejecutarlo.
                </p>
            <?php else: ?>

                <?php if (!empty($errores)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errores as $err): ?>
                                <li><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <?php if ($esVersionEscritorio): ?>
                        <input type="hidden" name="db_host" value="<?= htmlspecialchars($dbHostDefault, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="db_port" value="<?= htmlspecialchars($dbPortDefault, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="db_user" value="<?= htmlspecialchars($dbUserDefault, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="db_pass" value="">
                        <input type="hidden" name="db_name" value="<?= htmlspecialchars($dbNameDefault, ENT_QUOTES, 'UTF-8') ?>">
                        <div class="alert alert-light border small">
                            💾 Base de datos local configurada automáticamente. No necesitas tocar nada aquí.
                        </div>
                    <?php else: ?>
                        <h5 class="mt-3">Base de datos</h5>
                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Host</label>
                                <input type="text" name="db_host" class="form-control" value="<?= htmlspecialchars($_POST['db_host'] ?? $dbHostDefault, ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Puerto</label>
                                <input type="text" name="db_port" class="form-control" value="<?= htmlspecialchars($_POST['db_port'] ?? $dbPortDefault, ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Nombre de la base de datos</label>
                                <input type="text" name="db_name" class="form-control" value="<?= htmlspecialchars($_POST['db_name'] ?? $dbNameDefault, ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Usuario de MySQL</label>
                                <input type="text" name="db_user" class="form-control" value="<?= htmlspecialchars($_POST['db_user'] ?? $dbUserDefault, ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Contraseña de MySQL</label>
                                <input type="password" name="db_pass" class="form-control" value="<?= htmlspecialchars($_POST['db_pass'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                        </div>
                    <?php endif; ?>

                    <h5>Tu empresa</h5>
                    <div class="mb-3">
                        <label class="form-label">Nombre de la empresa</label>
                        <input type="text" name="nombre_empresa" class="form-control" value="<?= htmlspecialchars($_POST['nombre_empresa'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <h5>Usuario administrador</h5>
                    <div class="row g-2 mb-3">
                        <div class="col-md-12">
                            <label class="form-label">Nombre de usuario</label>
                            <input type="text" name="admin_usuario" class="form-control" value="<?= htmlspecialchars($_POST['admin_usuario'] ?? 'admin', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contraseña</label>
                            <input type="password" name="admin_pass" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Confirmar contraseña</label>
                            <input type="password" name="admin_pass_confirmar" class="form-control">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">Instalar</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>