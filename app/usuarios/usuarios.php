<?php

declare(strict_types=1);


include(__DIR__ . '/../db/db.php');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$esAdmin = ($_SESSION['rol'] ?? '') === 'Administrador';

$usuarios = [];
$roles    = [];

try {
    $sql = "SELECT u.id_usuario, u.nombre_usuario, u.estado,
                   u.bloqueado, u.intentos_fallidos,
                   u.ultimo_login, u.fecha_registro,
                   r.nombre_rol, r.id_rol
            FROM usuarios u
            JOIN roles r ON u.id_rol = r.id_rol
            ORDER BY u.fecha_registro DESC";
    $usuarios = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

    $roles = $conn->query('SELECT * FROM roles ORDER BY nombre_rol')->fetch_all(MYSQLI_ASSOC);
} catch (mysqli_sql_exception $e) {
    error_log('Error al cargar módulo de usuarios: ' . $e->getMessage());
}

$e = static fn(string $valor): string => htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
?>

<div class="modulo-usuario">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">Gestión de Usuarios</h2>
        <?php if ($esAdmin): ?>
            <button type="button" class="btn btn-primary" onclick="abrirModalRegistroUsuario()">
                + Agregar usuario
            </button>
        <?php endif; ?>
    </div>

    <?php if (!$esAdmin): ?>
        <p class="text-muted small">Solo el administrador puede gestionar usuarios.</p>
    <?php endif; ?>

    <!-- Modal: Registrar usuario (Bootstrap) -->
    <div class="modal fade" id="modalUsuario" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Registrar Usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <label for="u_nombre_usuario">Nombre de usuario</label>
                    <input type="text" id="u_nombre_usuario" class="form-control mb-2" placeholder="Nombre de usuario">

                    <label for="u_contrasena">Contraseña</label>
                    <input type="password" id="u_contrasena" class="form-control mb-2" placeholder="Mínimo 8 caracteres">

                    <label for="u_id_rol">Rol</label>
                    <select id="u_id_rol" class="form-select mb-2">
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= (int) $r['id_rol'] ?>"><?= $e($r['nombre_rol']) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label for="u_estado">Estado</label>
                    <select id="u_estado" class="form-select">
                        <option value="Activo">Activo</option>
                        <option value="Inactivo">Inactivo</option>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="guardarUsuario()">Registrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Editar usuario (Bootstrap) -->
    <div class="modal fade" id="modalEditarUsuario" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="eu_id">

                    <label for="eu_nombre_usuario">Nombre de usuario</label>
                    <input type="text" id="eu_nombre_usuario" class="form-control mb-2" placeholder="Nombre de usuario">

                    <label for="eu_contrasena">Nueva contraseña</label>
                    <input type="password" id="eu_contrasena" class="form-control mb-2" placeholder="Dejar vacío para no cambiar">

                    <label for="eu_id_rol">Rol</label>
                    <select id="eu_id_rol" class="form-select mb-2">
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= (int) $r['id_rol'] ?>"><?= $e($r['nombre_rol']) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label for="eu_estado">Estado</label>
                    <select id="eu_estado" class="form-select">
                        <option value="Activo">Activo</option>
                        <option value="Inactivo">Inactivo</option>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="actualizarUsuario()">Guardar Cambios</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla de usuarios -->
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <input type="search" id="buscarUsuario" class="form-control mb-3" onkeyup="buscarUsuario()"
                   placeholder="Buscar por nombre o rol...">

            <div class="table-responsive">
                <table id="tablaUsuarios" class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th>Bloqueado</th>
                            <th>Último login</th>
                            <th>Registro</th>
                            <?php if ($esAdmin): ?><th>Acciones</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $u): ?>
                            <?php $usuarioJson = json_encode($u, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>
                            <tr>
                                <td><?= $e($u['nombre_usuario']) ?></td>
                                <td><?= $e($u['nombre_rol']) ?></td>
                                <td>
                                    <span class="badge <?= $u['estado'] === 'Activo' ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                        <?= $e($u['estado']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($u['bloqueado']): ?>
                                        <span class="badge text-bg-danger">Sí</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-light text-dark">No</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $e((string) ($u['ultimo_login'] ?? '—')) ?></td>
                                <td><?= $e((string) ($u['fecha_registro'] ?? '—')) ?></td>
                                <?php if ($esAdmin): ?>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                        onclick='editarUsuario(<?= $usuarioJson ?>)'>Editar</button>
                                    <button type="button" class="btn btn-sm btn-outline-danger"
                                        onclick="eliminarUsuario(<?= (int) $u['id_usuario'] ?>)">Eliminar</button>
                                    <?php if ($u['bloqueado']): ?>
                                        <button type="button" class="btn btn-sm btn-outline-warning"
                                            onclick="desbloquearUsuario(<?= (int) $u['id_usuario'] ?>)">Desbloquear</button>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>