<?php
session_start();
define('BASE_URL', '/SistemaPrestamo/');

// 1. Proteger el dashboard — redirigir si no hay sesión activa
if (!isset($_SESSION['usuario'])) {
    header('Location: ../login/login.php');
    exit;
}

// 2. Matriz de permisos por rol — el único lugar donde se define qué
// puede ver cada rol. Si agregas un módulo nuevo, solo tienes que
// agregarlo aquí (y a $permitidos) para que el menú y el acceso directo
// por URL respeten el permiso automáticamente.
$permisosPorModulo = [
    'clientes' => ['Administrador', 'Supervisor'],
    'prestamo' => ['Administrador', 'Supervisor', 'Cobrador'],
    'redito'   => ['Administrador', 'Supervisor', 'Cobrador'],
    'pagos'    => ['Administrador', 'Supervisor', 'Cobrador'],
    'caja'     => ['Administrador', 'Supervisor'],
    'reportes' => ['Administrador', 'Supervisor'],
    'respaldo' => ['Administrador', 'Supervisor'],
    'usuarios' => ['Administrador'],
];

$rolActual  = $_SESSION['rol'] ?? '';
$permitidos = array_keys($permisosPorModulo);
$modulo     = $_GET['mod'] ?? '';

// Módulos que el rol actual sí puede ver (para el menú y para el fallback).
// 'redito' se excluye a propósito: no es una página de aterrizaje por sí
// sola, solo se llega ahí desde el botón dentro de Préstamos.
$modulosVisibles = array_keys(array_filter(
    $permisosPorModulo,
    fn ($roles, $mod) => $mod !== 'redito' && in_array($rolActual, $roles, true),
    ARRAY_FILTER_USE_BOTH
));

// Si el módulo pedido no existe, o el rol actual no tiene permiso para
// verlo, cae al primer módulo que SÍ le esté permitido a ese rol (en vez
// de siempre "clientes", que un Cobrador ni siquiera puede ver).
if (!in_array($modulo, $permitidos, true) || !in_array($rolActual, $permisosPorModulo[$modulo] ?? [], true)) {
    $modulo = $modulosVisibles[0] ?? null;
}

if ($modulo === null) {
    // Ningún módulo permitido para este rol — no debería pasar con los 3
    // roles actuales, pero por si acaso se crea un rol nuevo sin permisos.
    exit('Tu usuario no tiene acceso a ningún módulo. Contacta al administrador.');
}

// Etiquetas y emojis del menú, para no repetir esto en 2 lugares
$etiquetasModulo = [
    'clientes' => '👥 Clientes',
    'prestamo' => '💰 Préstamos',
    'redito'   => '💰 Préstamos a Rédito',
    'pagos'    => '💸 Pagos',
    'caja'     => '🏧 Caja',
    'reportes' => '📊 Reportes',
    'respaldo' => '💾 Respaldo',
    'usuarios' => '⚙️ Usuarios',
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestión de Préstamos</title>
    <link rel="stylesheet" href="db/style.css">
    <!-- Bootstrap CSS 5.3.8, necesario para el modal de edición de clientes -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
          integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
          crossorigin="anonymous">

    <!-- ═══════════════════════════════════════════════════════════════
         CSS del menú hamburguesa, puesto DIRECTO aquí (no en db/style.css)
         a propósito: usa selectores por #id, que tienen más prioridad que
         los selectores genéricos (nav, nav ul) del archivo externo, así
         que esta parte SIEMPRE funciona sin importar el estado de ese
         archivo. En escritorio no cambia nada del diseño original.
         ═══════════════════════════════════════════════════════════════ -->
    <style>
        #menuToggle {
            display: none;
        }

        .nav-header-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        @media (max-width: 768px) {
            #menuToggle {
                display: inline-block;
                width: auto;
                padding: 8px 14px;
                font-size: 1.3rem;
                margin: 0;
            }

            /* El menú (solo la lista de enlaces) se oculta por defecto en
               móvil, y se muestra al agregarle la clase .abierto al <nav>
               con el botón hamburguesa. El encabezado con el botón sigue
               siempre visible. */
            #appNav ul {
                display: none;
            }

            #appNav.abierto ul {
                display: flex;
                flex-direction: column;
                gap: 10px;
            }

            #appNav.abierto ul li {
                display: block;
            }
        }
    </style>
</head>
<body>

<div style="display:flex; min-height:100vh;">

    <!-- MENÚ -->
    <nav id="appNav" style="width:250px; background:#fcfbfb; color:black; padding:20px;">
        <div class="nav-header-row">
            <h2 style="margin:0;">PRESTA-APP</h2>
            <button type="button" id="menuToggle" aria-label="Abrir menú" aria-expanded="false" aria-controls="appNav">☰</button>
        </div>
        <hr>
        <ul style="list-style:none; padding:0;">
            <?php foreach ($modulosVisibles as $m): ?>
                <li><a href="?mod=<?= $m ?>"><?= $etiquetasModulo[$m] ?? $m ?></a></li>
            <?php endforeach; ?>
            <br>
            <li><a href="../login/logout.php" style="color:red;">🔒 Cerrar Sesión</a></li>
        </ul>
    </nav>

    <!-- CONTENIDO -->
    <main style="flex:1; padding:20px;">
        <header>
            <span>Bienvenido, <strong><?= htmlspecialchars($_SESSION['rol'] ?? 'Usuario', ENT_QUOTES, 'UTF-8') ?></strong></span>
            <hr>
        </header>

        <section>
            <?php
                // Ruta consistente — misma carpeta para PHP y JS: "$modulo/$modulo.php"
                include __DIR__ . "/$modulo/$modulo.php";
            ?>
        </section>
    </main>

</div>

<script>
    // Toggle del menú hamburguesa en móvil.
    document.getElementById('menuToggle')?.addEventListener('click', function () {
        const nav = document.getElementById('appNav');
        const abierto = nav.classList.toggle('abierto');
        this.setAttribute('aria-expanded', abierto ? 'true' : 'false');
        this.textContent = abierto ? '✕' : '☰';
    });
</script>

<!-- Bootstrap JS 5.3.8 (antes de common.js, que lo usa para el modal de clientes) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
        crossorigin="anonymous"></script>

<!-- JS: common.js SIEMPRE primero (todas las páginas dependen de sus helpers:
     apiRequest, marcarCampoInvalido, etc.), luego el JS específico del módulo -->
<script src="<?= BASE_URL ?>common.js" defer></script>
<?php
$rutaFisica = __DIR__ . "/$modulo/$modulo.js";
$rutaWeb    = BASE_URL . "$modulo/$modulo.js";

if (file_exists($rutaFisica)) {
    echo '<script src="' . htmlspecialchars($rutaWeb, ENT_QUOTES, 'UTF-8') . '" defer></script>';
}
?>
</body>
</html>