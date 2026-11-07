// caja.js — lógica del módulo de Caja.
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

// ─── Modales ──────────────────────────────────────────────────────────
function abrirModalTransaccion() {
    limpiarErrores(['tx_monto', 'tx_descripcion']);
    limpiarErrorGeneral('modalTransaccion');
    document.getElementById('tx_tipo').value = 'Entrada';
    document.getElementById('tx_origen').value = 'Gasto';
    document.getElementById('tx_monto').value = '';
    document.getElementById('tx_descripcion').value = '';
    modalBootstrap('modalTransaccion').show();
}
function cerrarModalTransaccion() { modalBootstrap('modalTransaccion').hide(); }

function abrirModalArqueo() {
    limpiarErrorGeneral('modalArqueo');
    document.getElementById('resultadoArqueo').innerHTML = '';
    document.getElementById('botonesArqueo').style.display = 'none';
    modalBootstrap('modalArqueo').show();
}
function cerrarModalArqueo() { modalBootstrap('modalArqueo').hide(); }

// ─── Buscar movimientos ───────────────────────────────────────────────
function buscarCaja() {
    const q = document.getElementById('buscarCaja').value.toLowerCase();
    document.querySelectorAll('#tablaCaja tbody tr').forEach((f) => {
        f.style.display = f.innerText.toLowerCase().includes(q) ? '' : 'none';
    });
}

// ─── Registrar transacción manual ─────────────────────────────────────
async function guardarTransaccion() {
    limpiarErrores(['tx_monto', 'tx_descripcion']);
    limpiarErrorGeneral('modalTransaccion');

    const datos = {
        tipo_movimiento: document.getElementById('tx_tipo').value,
        origen: document.getElementById('tx_origen').value,
        monto: document.getElementById('tx_monto').value,
        descripcion: document.getElementById('tx_descripcion').value.trim()
    };

    let huboError = false;
    if (!datos.monto || parseFloat(datos.monto) <= 0) {
        marcarCampoInvalido('tx_monto', 'Ingresa un monto válido');
        huboError = true;
    }
    if (!datos.descripcion) {
        marcarCampoInvalido('tx_descripcion', 'La descripción es requerida');
        huboError = true;
    }
    if (huboError) return;

    try {
        const data = await apiRequest('caja/caja_save.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(datos)
        });

        if (data.status === 'success') {
            cerrarModalTransaccion();
            location.reload();
        }
    } catch (error) {
        console.error(error);
        const camposFormulario = { monto: 'tx_monto', descripcion: 'tx_descripcion', origen: 'tx_origen', tipo_movimiento: 'tx_tipo' };
        const idCampo = error.campo ? camposFormulario[error.campo] : null;
        if (idCampo) {
            marcarCampoInvalido(idCampo, error.message);
        } else {
            mostrarErrorGeneral('modalTransaccion', error.message);
        }
    }
}

// ─── Generar arqueo de caja ───────────────────────────────────────────
async function generarArqueo() {
    limpiarErrorGeneral('modalArqueo');

    const desde = document.getElementById('arq_desde').value;
    const hasta = document.getElementById('arq_hasta').value;

    if (!desde || !hasta) {
        mostrarErrorGeneral('modalArqueo', 'Selecciona el rango de fechas');
        return;
    }
    if (desde > hasta) {
        mostrarErrorGeneral('modalArqueo', 'La fecha "desde" no puede ser mayor a "hasta"');
        return;
    }

    try {
        const data = await apiRequest('caja/caja_arqueo.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ desde, hasta })
        });

        if (data.status === 'success') {
            mostrarResultadoArqueo(data.arqueo, desde, hasta);
        }
    } catch (error) {
        console.error(error);
        mostrarErrorGeneral('modalArqueo', error.message);
    }
}

// ─── Mostrar resultado del arqueo ─────────────────────────────────────
function mostrarResultadoArqueo(a, desde, hasta) {
    const neto = parseFloat(a.total_entradas) - parseFloat(a.total_salidas);

    const html = `
        <div id="arqueoImprimible">
            <h4 class="text-center">PRESTA-APP — Arqueo de Caja</h4>
            <p class="text-center">Del ${desde} al ${hasta}</p>
            <hr>
            <table class="table table-sm">
                <tr>
                    <th>Concepto</th>
                    <th class="text-end">Monto</th>
                </tr>
                <tr><td>Entradas por Préstamos</td>
                    <td class="text-end text-success">$${parseFloat(a.entradas_prestamo).toFixed(2)}</td></tr>
                <tr><td>Entradas por Pagos</td>
                    <td class="text-end text-success">$${parseFloat(a.entradas_pago).toFixed(2)}</td></tr>
                <tr><td>Entradas por Ajuste</td>
                    <td class="text-end text-success">$${parseFloat(a.entradas_ajuste).toFixed(2)}</td></tr>
                <tr><td>Salidas por Gastos</td>
                    <td class="text-end text-danger">$${parseFloat(a.salidas_gasto).toFixed(2)}</td></tr>
                <tr><td>Salidas por Ajuste</td>
                    <td class="text-end text-danger">$${parseFloat(a.salidas_ajuste).toFixed(2)}</td></tr>
                <tr class="fw-bold border-top">
                    <td>Total Entradas</td>
                    <td class="text-end text-success">$${parseFloat(a.total_entradas).toFixed(2)}</td>
                </tr>
                <tr class="fw-bold">
                    <td>Total Salidas</td>
                    <td class="text-end text-danger">$${parseFloat(a.total_salidas).toFixed(2)}</td>
                </tr>
                <tr class="fw-bold fs-5 border-top">
                    <td>NETO</td>
                    <td class="text-end ${neto >= 0 ? 'text-success' : 'text-danger'}">
                        $${neto.toFixed(2)}
                    </td>
                </tr>
            </table>
            <p class="text-muted small mt-2">
                Total movimientos: ${a.total_movimientos} |
                Generado: ${new Date().toLocaleString()}
            </p>
        </div>
    `;

    document.getElementById('resultadoArqueo').innerHTML = html;
    document.getElementById('botonesArqueo').style.display = 'flex';
}

// ─── Imprimir arqueo ──────────────────────────────────────────────────
function imprimirArqueo() {
    const contenido = document.getElementById('arqueoImprimible').innerHTML;
    const ventana = window.open('', '_blank');
    ventana.document.write(`
        <html><head>
        <style>
            body { font-family: Arial, sans-serif; font-size:13px;
                   margin:2cm; width:21cm; }
            table { width:100%; border-collapse:collapse; }
            th, td { padding:6px 8px; }
            hr { border:1px solid #000; }
        </style>
        </head><body>${contenido}</body></html>
    `);
    ventana.document.close();
    ventana.focus();
    ventana.print();
    ventana.close();
}