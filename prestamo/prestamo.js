// prestamo.js — lógica del módulo de Préstamos.
// Autocontenido: como common.js solo se carga en el módulo de clientes,
// este archivo incluye sus propias copias de los helpers de validación
// (marcarCampoInvalido, apiRequest, etc.) en vez de depender de common.js.

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

// ─── Modales ──────────────────────────────────────────────────────────
function abrirModalPrestamo() {
    // Limpiar datos y errores de un uso anterior del formulario
    limpiarErrores(Object.values(CAMPOS_PRESTAMO));
    limpiarErrorGeneral('modalPrestamo');
    document.getElementById('p_id_cliente').value = '';
    document.getElementById('p_monto').value = '';
    document.getElementById('p_tasa_interes').value = '';
    document.getElementById('p_tipo_interes').value = 'Simple';
    document.getElementById('p_modalidad_pagos').value = 'Diario';
    document.getElementById('p_plazo').value = '';
    document.getElementById('p_mora_porcentaje').value = '';
    document.getElementById('p_cuota_monto').value = '';

    // Fecha de inicio: hoy por defecto (el usuario puede cambiarla si el
    // préstamo arranca en otra fecha).
    document.getElementById('p_fecha_inicio').value = new Date().toISOString().split('T')[0];
    calcularFechaFin();

    document.getElementById('modalPrestamo').style.display = 'block';
}
function cerrarModalPrestamo() {
    document.getElementById('modalPrestamo').style.display = 'none';
}
function cerrarModalEditarPrestamo() {
    document.getElementById('modalEditarPrestamo').style.display = 'none';
}

// ─── Buscar en tabla ──────────────────────────────────────────────────
function buscarPrestamo() {
    const query = document.getElementById('buscarPrestamo').value.toLowerCase();
    document.querySelectorAll('#tablaPrestamos tbody tr').forEach((fila) => {
        fila.style.display = fila.innerText.toLowerCase().includes(query) ? '' : 'none';
    });
}

// ─── Calcular cuota automáticamente ──────────────────────────────────
function calcularCuota() {
    // La fecha fin depende solo de fecha_inicio + plazo + modalidad, no de
    // monto/tasa — la calculamos primero y de forma independiente, para que
    // se actualice apenas cambies la modalidad aunque el monto aún esté vacío.
    calcularFechaFin();

    const monto = parseFloat(document.getElementById('p_monto').value) || 0;
    const tasa  = parseFloat(document.getElementById('p_tasa_interes').value) || 0;
    const tipo  = document.getElementById('p_tipo_interes').value;
    const plazo = parseInt(document.getElementById('p_plazo').value, 10) || 0;

    if (monto <= 0 || plazo <= 0) {
        document.getElementById('p_cuota_monto').value = '';
        return;
    }

    let cuota = 0;
    const tasaDecimal = tasa / 100;

    if (tipo === 'Simple') {
        const totalAPagar = monto * tasaDecimal * plazo;
        cuota = totalAPagar / plazo;
    } 
    if (tipo==='Compuesto') {
        cuota = tasaDecimal === 0
            ? monto / plazo
            : (monto * (tasaDecimal * Math.pow(1 + tasaDecimal, plazo)))
                / (Math.pow(1 + tasaDecimal, plazo) - 1);
    }
    else{
      const totalApagar = tasaDecimal * monto;
      cuota = totalApagar + monto;

    }

    document.getElementById('p_cuota_monto').value = cuota.toFixed(2);
}

// ─── Calcular fecha fin automáticamente ──────────────────────────────
function calcularFechaFin() {
    const fechaInicio = document.getElementById('p_fecha_inicio').value;
    const plazo       = parseInt(document.getElementById('p_plazo').value, 10) || 0;
    const modalidad   = document.getElementById('p_modalidad_pagos').value;

    if (!fechaInicio || plazo <= 0) {
        document.getElementById('p_fecha_fin').value = '';
        return;
    }

    const dias = { Diario: 1, Semanal: 7, Quincenal: 15, Mensual: 30 };
    const totalDias = plazo * (dias[modalidad] ?? 30);

    const fecha = new Date(fechaInicio);
    fecha.setDate(fecha.getDate() + totalDias);

    document.getElementById('p_fecha_fin').value = fecha.toISOString().split('T')[0];
}

// ─── Guardar préstamo ─────────────────────────────────────────────────
const CAMPOS_PRESTAMO = {
    id_cliente: 'p_id_cliente',
    monto: 'p_monto',
    tasa_interes: 'p_tasa_interes',
    tipo_interes: 'p_tipo_interes',
    modalidad_pagos: 'p_modalidad_pagos',
    plazo: 'p_plazo',
    fecha_inicio: 'p_fecha_inicio',
    cuota_monto: 'p_cuota_monto'
};

async function guardarPrestamo() {
    limpiarErrores(Object.values(CAMPOS_PRESTAMO));
    limpiarErrorGeneral('modalPrestamo');

    const datos = {
        id_cliente: document.getElementById('p_id_cliente').value,
        monto: document.getElementById('p_monto').value,
        tasa_interes: document.getElementById('p_tasa_interes').value,
        tipo_interes: document.getElementById('p_tipo_interes').value,
        modalidad_pagos: document.getElementById('p_modalidad_pagos').value,
        plazo: document.getElementById('p_plazo').value,
        mora_porcentaje: document.getElementById('p_mora_porcentaje').value || 0,
        fecha_inicio: document.getElementById('p_fecha_inicio').value,
        fecha_fin: document.getElementById('p_fecha_fin').value,
        cuota_monto: document.getElementById('p_cuota_monto').value
    };

    let primerCampoInvalido = null;
    for (const [campo, id] of Object.entries(CAMPOS_PRESTAMO)) {
        if (!datos[campo]) {
            marcarCampoInvalido(id, 'Este campo es requerido');
            primerCampoInvalido ??= id;
        }
    }
    if (primerCampoInvalido) {
        document.getElementById(primerCampoInvalido).focus();
        return;
    }

    try {
        const data = await apiRequest('prestamo/prestamo_save.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(datos)
        });

        if (data.status === 'success') {
            cerrarModalPrestamo();
            location.reload();
        }
    } catch (error) {
        console.error(error);
        const idCampo = error.campo ? CAMPOS_PRESTAMO[error.campo] : null;
        if (idCampo) {
            marcarCampoInvalido(idCampo, error.message);
            document.getElementById(idCampo)?.focus();
        } else {
            mostrarErrorGeneral('modalPrestamo', error.message);
        }
    }
}

// ─── Preparar edición ─────────────────────────────────────────────────
function editarPrestamo(p) {
    document.getElementById('ep_id').value = p.id_prestamo;
    document.getElementById('ep_estado').value = p.estado;
    document.getElementById('modalEditarPrestamo').style.display = 'block';
}

// ─── Actualizar estado del préstamo ───────────────────────────────────
async function actualizarPrestamo() {
    limpiarCampoInvalido('ep_estado');
    limpiarErrorGeneral('modalEditarPrestamo');

    const datos = {
        id_prestamo: document.getElementById('ep_id').value,
        estado: document.getElementById('ep_estado').value
    };

    try {
        const data = await apiRequest('prestamo/prestamo_actualizar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(datos)
        });

        if (data.status === 'success') {
            cerrarModalEditarPrestamo();
            location.reload();
        }
    } catch (error) {
        console.error(error);
        if (error.campo === 'estado') {
            marcarCampoInvalido('ep_estado', error.message);
        } else {
            mostrarErrorGeneral('modalEditarPrestamo', error.message);
        }
    }
}

// ─── Eliminar préstamo ────────────────────────────────────────────────
async function eliminarPrestamo(id) {
    if (!confirm('¿Eliminar este préstamo? Esta acción no se puede deshacer.')) return;

    try {
        const data = await apiRequest('prestamo/prestamo_eliminar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_prestamo: id })
        });

        if (data.status === 'success') {
            location.reload();
        }
    } catch (error) {
        console.error(error);
        alert('No se pudo eliminar: ' + error.message);
    }
}