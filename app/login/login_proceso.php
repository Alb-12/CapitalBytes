<?php

declare(strict_types=1);

session_start();
include('../db/db.php');
/** @var mysqli $conn */
// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// ── Detectar el modo: JSON (AJAX, con JS) o form-urlencoded (sin JS) ────
$esJson = str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json');

if ($esJson) {
    $datos = json_decode(file_get_contents('php://input'), true) ?? [];
    header('Content-Type: application/json; charset=utf-8');
} else {
    $datos = $_POST;
}

/**
 * Responde según el modo: JSON si vino por AJAX, o guarda el mensaje en
 * sesión + redirige si fue un envío de formulario normal (sin JS).
 */
function responderLogin(bool $esJson, bool $exito, string $mensaje): never
{
    if ($esJson) {
        http_response_code($exito ? 200 : 400);
        echo json_encode(['status' => $exito ? 'success' : 'error', 'message' => $mensaje], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!$exito) {
        $_SESSION['login_error'] = $mensaje;
    }
    header('Location: ' . ($exito ? '../dashboard.php' : 'login.php'));
    exit;
}

$nombreUsuario = trim((string) ($datos['nombre_usuario'] ?? ''));
$contrasena    = (string) ($datos['contrasena'] ?? '');
$csrfRecibido  = (string) ($datos['csrf_token'] ?? '');

// ── Validar CSRF antes que nada ──────────────────────────────────────────
// hash_equals() en vez de === : compara en tiempo constante, para que un
// atacante no pueda medir por cuánto tarda la respuesta cuál caracter del
// token acertó (ataque de "timing attack" — poco probable aquí, pero es la
// forma correcta de comparar tokens/secrets siempre).
if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrfRecibido)) {
    responderLogin($esJson, false, 'Tu sesión de login expiró. Recarga la página e intenta de nuevo.');
}

if ($nombreUsuario === '' || $contrasena === '') {
    responderLogin($esJson, false, 'Completa todos los campos');
}

try {
    $stmt = $conn->prepare(
        'SELECT u.*, r.nombre_rol
         FROM usuarios u
         JOIN roles r ON u.id_rol = r.id_rol
         WHERE u.nombre_usuario = ?
         LIMIT 1'
    );
    $stmt->bind_param('s', $nombreUsuario);
    $stmt->execute();
    $usuario = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Mensaje genérico a propósito: no revelar si el usuario existe o no,
    // para no ayudar a un atacante a enumerar nombres de usuario válidos.
    if ($usuario === null) {
        responderLogin($esJson, false, 'Usuario o contraseña incorrectos');
    }

    if ($usuario['bloqueado']) {
        responderLogin($esJson, false, 'Tu cuenta está bloqueada. Contacta al administrador.');
    }

    if ($usuario['estado'] === 'Inactivo') {
        responderLogin($esJson, false, 'Tu cuenta está inactiva. Contacta al administrador.');
    }

    // Verificar contraseña — SIEMPRE con password_verify(), nunca comparación
    // directa (las contraseñas se guardan con password_hash()).
    if (!password_verify($contrasena, $usuario['contrasena'])) {
        $intentos  = (int) $usuario['intentos_fallidos'] + 1;
        $bloqueado = $intentos >= 5 ? 1 : 0;

        $upd = $conn->prepare('UPDATE usuarios SET intentos_fallidos = ?, bloqueado = ? WHERE id_usuario = ?');
        $upd->bind_param('iii', $intentos, $bloqueado, $usuario['id_usuario']);
        $upd->execute();
        $upd->close();

        if ($bloqueado) {
            responderLogin($esJson, false, 'Demasiados intentos. Cuenta bloqueada.');
        }
        $restantes = 5 - $intentos;
        responderLogin($esJson, false, "Usuario o contraseña incorrectos. Intentos restantes: $restantes");
    }

    // Login exitoso — resetear intentos y registrar último login
    $ahora = date('Y-m-d H:i:s');
    $upd = $conn->prepare('UPDATE usuarios SET intentos_fallidos = 0, bloqueado = 0, ultimo_login = ? WHERE id_usuario = ?');
    $upd->bind_param('si', $ahora, $usuario['id_usuario']);
    $upd->execute();
    $upd->close();

    // Regenerar el ID de sesión — evita "session fixation".
    session_regenerate_id(true);
    $_SESSION['usuario']    = $usuario['nombre_usuario'];
    $_SESSION['id_usuario'] = $usuario['id_usuario'];
    $_SESSION['rol']        = $usuario['nombre_rol'];
    $_SESSION['id_rol']     = $usuario['id_rol'];

    // El token CSRF ya cumplió su función para esta sesión de login; se
    // limpia para que no quede reutilizable después de autenticado.
    unset($_SESSION['csrf_token']);

    $conn->close();
    responderLogin($esJson, true, 'Sesión iniciada correctamente');
} catch (mysqli_sql_exception $e) {
    error_log('Error en login: ' . $e->getMessage());
    responderLogin($esJson, false, 'Error interno. Intenta más tarde.');
}