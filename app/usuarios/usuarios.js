// usuarios.js — lógica del módulo de Usuarios.
// Autocontenido: no depende de common.js (que solo se carga en clientes).

// ─── Helpers propios de este módulo ───────────────────────────────────
function marcarCampoInvalido(id, mensaje) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.add('campo-invalido');
    el.setAttribute('aria-invalid', 'true');

    let msgEl = document.getElementById(`error-${id}`);
    if (!msgEl) {
        msgEl = document.createElement('small');
        msgEl.id = `error-${id}`;
        msgEl.className = 'mensaje-error';
        el.insertAdjacentElement('afterend', msgEl);
    }
    msgEl.textContent = mensaje;
}

function limpiarCampoInvalido(id) {
    const el = document.getElementById(id);
    el?.classList.remove('campo-invalido');
    el?.removeAttribute('aria-invalid');
    document.getElementById(`error-${id}`)?.remove();
}

function limpiarErrores(ids) {
    ids.forEach(limpiarCampoInvalido);
}

function mostrarErrorGeneral(contenedorId, mensaje) {
    const contenedor = document.getElementById(contenedorId);
    if (!contenedor) {
        alert(mensaje);
        return;
    }
    let banner = contenedor.querySelector('.banner-error');
    if (!banner) {
        banner = document.createElement('div');
        banner.className = 'banner-error';
        contenedor.prepend(banner);
    }
    banner.textContent = mensaje;
}

function limpiarErrorGeneral(contenedorId) {
    document.getElementById(contenedorId)?.querySelector('.banner-error')?.remove();
}

async function apiRequest(url, options = {}) {
    const res = await fetch(url, options);
    let data = null;
    try {
        data = await res.json();
    } catch {
        // La respuesta no era JSON (ej. error 500 crudo de PHP)
    }
    if (!res.ok) {
        const error = new Error(data?.message ?? data?.mensaje ?? `Error del servidor (${res.status})`);
        error.campo = data?.campo ?? null;
        throw error;
    }
    return data;
}

function modalBootstrap(id) {
    return bootstrap.Modal.getOrCreateInstance(document.getElementById(id));
}

// ─── Modales ─────────────────────────────────────────────────────────
function abrirModalRegistroUsuario() {
    limpiarErrores(['u_nombre_usuario', 'u_contrasena']);
    limpiarErrorGeneral('modalUsuario');
    document.getElementById('u_nombre_usuario').value = '';
    document.getElementById('u_contrasena').value = '';
    document.getElementById('u_estado').value = 'Activo';
    modalBootstrap('modalUsuario').show();
}
function cerrarModalRegistroUsuario() { modalBootstrap('modalUsuario').hide(); }
function cerrarModalEditarUsuario()   { modalBootstrap('modalEditarUsuario').hide(); }

// ─── Buscar en tabla ─────────────────────────────────────────────────
function buscarUsuario() {
    const query = document.getElementById('buscarUsuario').value.toLowerCase();
    document.querySelectorAll('#tablaUsuarios tbody tr').forEach((fila) => {
        fila.style.display = fila.innerText.toLowerCase().includes(query) ? '' : 'none';
    });
}

// ─── Registrar usuario ───────────────────────────────────────────────
async function guardarUsuario() {
    limpiarErrores(['u_nombre_usuario', 'u_contrasena']);
    limpiarErrorGeneral('modalUsuario');

    const datos = {
        nombre_usuario: document.getElementById('u_nombre_usuario').value.trim(),
        contrasena: document.getElementById('u_contrasena').value,
        id_rol: document.getElementById('u_id_rol').value,
        estado: document.getElementById('u_estado').value
    };

    let huboError = false;
    if (!datos.nombre_usuario) {
        marcarCampoInvalido('u_nombre_usuario', 'El nombre de usuario es requerido');
        huboError = true;
    }
    if (!datos.contrasena) {
        marcarCampoInvalido('u_contrasena', 'La contraseña es requerida');
        huboError = true;
    } else if (datos.contrasena.length < 8) {
        marcarCampoInvalido('u_contrasena', 'La contraseña debe tener al menos 8 caracteres');
        huboError = true;
    }
    if (huboError) return;

    try {
        const data = await apiRequest('usuarios/usuario_save.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(datos)
        });

        if (data.status === 'success') {
            cerrarModalRegistroUsuario();
            location.reload();
        }
    } catch (error) {
        console.error(error);
        const camposFormulario = { nombre_usuario: 'u_nombre_usuario', contrasena: 'u_contrasena' };
        const idCampo = error.campo ? camposFormulario[error.campo] : null;
        if (idCampo) {
            marcarCampoInvalido(idCampo, error.message);
        } else {
            mostrarErrorGeneral('modalUsuario', error.message);
        }
    }
}

// ─── Preparar edición ────────────────────────────────────────────────
function editarUsuario(u) {
    limpiarErrores(['eu_nombre_usuario', 'eu_contrasena']);
    limpiarErrorGeneral('modalEditarUsuario');

    document.getElementById('eu_id').value             = u.id_usuario;
    document.getElementById('eu_nombre_usuario').value = u.nombre_usuario;
    document.getElementById('eu_contrasena').value     = '';
    document.getElementById('eu_id_rol').value         = u.id_rol;
    document.getElementById('eu_estado').value         = u.estado;

    modalBootstrap('modalEditarUsuario').show();
}

// ─── Actualizar usuario ──────────────────────────────────────────────
async function actualizarUsuario() {
    limpiarErrores(['eu_nombre_usuario', 'eu_contrasena']);
    limpiarErrorGeneral('modalEditarUsuario');

    const datos = {
        id_usuario: document.getElementById('eu_id').value,
        nombre_usuario: document.getElementById('eu_nombre_usuario').value.trim(),
        contrasena: document.getElementById('eu_contrasena').value,
        id_rol: document.getElementById('eu_id_rol').value,
        estado: document.getElementById('eu_estado').value
    };

    if (!datos.nombre_usuario) {
        marcarCampoInvalido('eu_nombre_usuario', 'El nombre de usuario es requerido');
        return;
    }
    if (datos.contrasena && datos.contrasena.length < 8) {
        marcarCampoInvalido('eu_contrasena', 'La contraseña debe tener al menos 8 caracteres');
        return;
    }

    try {
        const data = await apiRequest('usuarios/usuario_actualizar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(datos)
        });

        if (data.status === 'success') {
            cerrarModalEditarUsuario();
            location.reload();
        }
    } catch (error) {
        console.error(error);
        const camposFormulario = { nombre_usuario: 'eu_nombre_usuario', contrasena: 'eu_contrasena' };
        const idCampo = error.campo ? camposFormulario[error.campo] : null;
        if (idCampo) {
            marcarCampoInvalido(idCampo, error.message);
        } else {
            mostrarErrorGeneral('modalEditarUsuario', error.message);
        }
    }
}

// ─── Eliminar usuario ────────────────────────────────────────────────
async function eliminarUsuario(id) {
    if (!confirm('¿Estás seguro de eliminar este usuario?')) return;

    try {
        const data = await apiRequest('usuarios/usuario_eliminar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_usuario: id })
        });

        if (data.status === 'success') {
            location.reload();
        }
    } catch (error) {
        console.error(error);
        alert('No se pudo eliminar: ' + error.message);
    }
}

// ─── Desbloquear usuario ─────────────────────────────────────────────
async function desbloquearUsuario(id) {
    if (!confirm('¿Desbloquear este usuario?')) return;

    try {
        const data = await apiRequest('usuarios/usuario_desbloquear.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_usuario: id })
        });

        if (data.status === 'success') {
            location.reload();
        }
    } catch (error) {
        console.error(error);
        alert('No se pudo desbloquear: ' + error.message);
    }
}