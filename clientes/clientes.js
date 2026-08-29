// clientes.js — lógica específica del módulo de Clientes.
// Depende de common.js (apiRequest, marcarCampoInvalido, limpiarErrores,
// mostrarErrorGeneral, limpiarErrorGeneral, val) — asegúrate de que
// dashboard.php cargue common.js SIEMPRE, antes que este archivo.

// ─── Guardar nuevo cliente ───────────────────────────────────────────
async function guardarCliente() {
    const requeridos = [
        'cedula', 'nombre', 'apellido', 'telefono',
        'direccion', 'correo', 'nombre_garante',
        'telefono_garante', 'cedula_garante'
    ];

    limpiarErrores(requeridos);
    limpiarErrorGeneral('miModal');

    // Marcamos TODOS los campos vacíos a la vez (no solo el primero),
    // así el usuario ve de un vistazo todo lo que falta por llenar.
    let primerCampoInvalido = null;
    for (const campo of requeridos) {
        if (!val(campo)) {
            marcarCampoInvalido(campo, 'Este campo es requerido');
            primerCampoInvalido ??= campo;
        }
    }
    if (primerCampoInvalido) {
        document.getElementById(primerCampoInvalido).focus();
        return;
    }

    const formData = new FormData();
    requeridos.forEach((campo) => formData.append(campo, val(campo)));

    const fotoInput = document.getElementById('foto');
    const foto = fotoInput?.files?.[0];
    if (foto) {
        formData.append('foto', foto);
    }

    try {
        const data = await apiRequest('clientes/clientes_save.php', {
            method: 'POST',
            body: formData
        });

        if (data.status === 'success') {
            cerrarModalRegistro();
            location.reload();
        }
    } catch (error) {
        console.error(error);
        if (error.campo) {
            marcarCampoInvalido(error.campo, error.message);
            document.getElementById(error.campo)?.focus();
        } else {
            mostrarErrorGeneral('miModal', error.message);
        }
    }
}

// ─── Buscar cliente ──────────────────────────────────────────────────
function buscarCliente() {
    const query = val('buscar').toLowerCase();
    const tarjetas = document.querySelectorAll('.cliente-card');

    tarjetas.forEach((card) => {
        const texto = card.innerText.toLowerCase();
        card.style.display = texto.includes(query) ? 'block' : 'none';
    });
}

// ─── Abrir modal de edición (Bootstrap) con datos del cliente ────────
async function abrirModalEdicion(cedula) {
    try {
        const response = await fetch(
            '/SistemaPrestamo/clientes/editar.php?cedula=' + encodeURIComponent(cedula)
        );
        if (!response.ok) throw new Error('No se pudo cargar el formulario');

        const html = await response.text();
        document.getElementById('contenidoModal').innerHTML = html;

        if (typeof bootstrap === 'undefined') {
            alert('Bootstrap no está cargado');
            return;
        }

        const modalEl = document.getElementById('modalActualizar');
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    } catch (error) {
        console.error(error);
        alert('Error al abrir el editor: ' + error.message);
    }
}

// ─── Preparar edición desde la tarjeta del cliente ───────────────────
function prepararEdicion(cliente) {
    abrirModalEdicion(cliente.cedula);
}

// ─── Eliminar cliente ────────────────────────────────────────────────
async function eliminarCliente(cedula) {
    if (!confirm('¿Estás seguro de eliminar este cliente?')) return;

    try {
        const data = await apiRequest('/SistemaPrestamo/clientes/eliminar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ cedula })
        });

        if (data.status === 'success') {
            location.reload();
        }
    } catch (error) {
        console.error(error);
        alert('No se pudo eliminar: ' + error.message);
    }
}

// ─── Cerrar modal de edición (Bootstrap) ──────────────────────────────
function cerrarModal() {
    const modalEl = document.getElementById('modalActualizar');
    const modal = bootstrap.Modal.getInstance(modalEl);
    modal?.hide();
}

// ─── Actualizar cliente ──────────────────────────────────────────────
async function actualizarCliente() {
    const camposFormulario = {
        nombre: 'edit_nombre',
        apellido: 'edit_apellido',
        cedula: 'edit_cedula',
        direccion: 'edit_direccion',
        telefono: 'edit_telefono',
        correo: 'edit_correo',
        nombre_garante: 'edit_nombre_garante',
        telefono_garante: 'edit_telefono_garante',
        cedula_garante: 'edit_cedula_garante'
    };

    limpiarErrores(Object.values(camposFormulario));
    limpiarErrorGeneral('contenidoModal');

    const datos = { id: val('edit_id') };
    for (const [campo, id] of Object.entries(camposFormulario)) {
        datos[campo] = val(id);
    }

    const requeridos = ['nombre', 'apellido', 'cedula', 'telefono', 'correo'];
    let primerCampoInvalido = null;
    for (const campo of requeridos) {
        if (!datos[campo]) {
            marcarCampoInvalido(camposFormulario[campo], 'Este campo es requerido');
            primerCampoInvalido ??= camposFormulario[campo];
        }
    }
    if (primerCampoInvalido) {
        document.getElementById(primerCampoInvalido).focus();
        return;
    }

    try {
        const data = await apiRequest('/SistemaPrestamo/clientes/actualizar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(datos)
        });

        if (data.status === 'success') {
            cerrarModal();
            location.reload();
        }
    } catch (error) {
        console.error(error);
        const idCampo = error.campo ? camposFormulario[error.campo] : null;
        if (idCampo) {
            marcarCampoInvalido(idCampo, error.message);
            document.getElementById(idCampo)?.focus();
        } else {
            mostrarErrorGeneral('contenidoModal', error.message);
        }
    }
}