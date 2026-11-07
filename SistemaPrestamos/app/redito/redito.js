
// ─── Helpers propios ────────────────────────────────────────────────────
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

async function apiRequest(url, options = {}) {
    const res = await fetch(url, options);
    const textoCrudo = await res.text();

    let data = null;
    try {
        data = JSON.parse(textoCrudo);
    } catch {
        throw new Error('Respuesta no válida del servidor: ' + textoCrudo.slice(0, 300));
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

function calcularCuotaRedito(saldo, tasa) {
    return Math.round((saldo / 1000) * tasa * 100) / 100;
}

// ─── Buscar en tabla ────────────────────────────────────────────────────
function buscarRedito() {
    const q = document.getElementById('buscarRedito').value.toLowerCase();
    document.querySelectorAll('#tablaRedito tbody tr').forEach((fila) => {
        fila.style.display = fila.innerText.toLowerCase().includes(q) ? '' : 'none';
    });
}

// ─── Modal: nuevo préstamo a rédito ─────────────────────────────────────
function abrirModalRedito() {
    limpiarErrores(['r_id_cliente', 'r_monto_capital', 'r_tasa_redito', 'r_fecha_inicio']);
    document.getElementById('r_id_cliente').value = '';
    document.getElementById('r_monto_capital').value = '';
    document.getElementById('r_tasa_redito').value = '200';
    document.getElementById('r_modalidad_pagos').value = 'Diario';
    document.getElementById('r_fecha_inicio').value = new Date().toISOString().split('T')[0];
    calcularVistaPreviaRedito();
    modalBootstrap('modalRedito').show();
}

function calcularVistaPreviaRedito() {
    const monto = parseFloat(document.getElementById('r_monto_capital').value) || 0;
    const tasa = parseFloat(document.getElementById('r_tasa_redito').value) || 0;
    const cuota = calcularCuotaRedito(monto, tasa);
    document.getElementById('preview-cuota-redito').innerHTML =
        `Cuota de interés estimada: <strong>$${cuota.toFixed(2)}</strong> por período`;
}

// ─── Guardar nuevo préstamo a rédito ────────────────────────────────────
async function guardarRedito() {
    limpiarErrores(['r_id_cliente', 'r_monto_capital', 'r_tasa_redito', 'r_fecha_inicio']);

    const datos = {
        id_cliente: document.getElementById('r_id_cliente').value,
        monto_capital: document.getElementById('r_monto_capital').value,
        tasa_redito: document.getElementById('r_tasa_redito').value,
        modalidad_pagos: document.getElementById('r_modalidad_pagos').value,
        fecha_inicio: document.getElementById('r_fecha_inicio').value
    };

    const camposFormulario = { id_cliente: 'r_id_cliente', monto_capital: 'r_monto_capital', tasa_redito: 'r_tasa_redito', fecha_inicio: 'r_fecha_inicio' };

    let huboError = false;
    if (!datos.id_cliente) { marcarCampoInvalido('r_id_cliente', 'Selecciona un cliente'); huboError = true; }
    if (!datos.monto_capital || parseFloat(datos.monto_capital) <= 0) { marcarCampoInvalido('r_monto_capital', 'Ingresa un monto válido'); huboError = true; }
    if (!datos.fecha_inicio) { marcarCampoInvalido('r_fecha_inicio', 'Selecciona la fecha de inicio'); huboError = true; }
    if (huboError) return;

    try {
        const data = await apiRequest('redito/redito_save.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(datos)
        });

        if (data.status === 'success') {
            modalBootstrap('modalRedito').hide();
            location.reload();
        }
    } catch (error) {
        console.error(error);
        const idCampo = error.campo ? camposFormulario[error.campo] : null;
        if (idCampo) {
            marcarCampoInvalido(idCampo, error.message);
        } else {
            alert(error.message);
        }
    }
}

// ─── Modal: registrar pago ──────────────────────────────────────────────
let redItoPrestamoActual = null;

function abrirModalPagoRedito(prestamo) {
    redItoPrestamoActual = prestamo;
    limpiarErrores(['pr_monto']);

    const cuota = calcularCuotaRedito(parseFloat(prestamo.saldo_capital), parseFloat(prestamo.tasa_redito));

    document.getElementById('pr_id_prestamo_redito').value = prestamo.id_prestamo_redito;
    document.getElementById('pr_cliente').value = prestamo.nombre_cliente;
    document.getElementById('pr_saldo_capital').value = '$' + parseFloat(prestamo.saldo_capital).toFixed(2);
    document.getElementById('pr_cuota_interes').value = '$' + cuota.toFixed(2);
    document.getElementById('pr_tipo_pago').value = 'Interes';
    document.getElementById('pr_monto').value = cuota.toFixed(2);

    // Casilla "Enviar por WhatsApp": solo se activa si el cliente tiene teléfono registrado
    const chkWA = document.getElementById('pr_enviar_whatsapp');
    if (chkWA) {
        const tieneTelefono = String(prestamo.telefono_cliente ?? '').trim() !== '';
        chkWA.checked  = tieneTelefono;
        chkWA.disabled = !tieneTelefono;
    }

    modalBootstrap('modalPagoRedito').show();
}

function actualizarMontoSugeridoRedito() {
    if (!redItoPrestamoActual) return;
    const tipo = document.getElementById('pr_tipo_pago').value;

    if (tipo === 'Interes') {
        const cuota = calcularCuotaRedito(parseFloat(redItoPrestamoActual.saldo_capital), parseFloat(redItoPrestamoActual.tasa_redito));
        document.getElementById('pr_monto').value = cuota.toFixed(2);
    } else {
        document.getElementById('pr_monto').value = '';
    }
}

async function confirmarPagoRedito() {
    limpiarErrores(['pr_monto']);

    const monto = document.getElementById('pr_monto').value;
    if (!monto || parseFloat(monto) <= 0) {
        marcarCampoInvalido('pr_monto', 'Ingresa un monto válido');
        return;
    }

    const datos = {
        id_prestamo_redito: document.getElementById('pr_id_prestamo_redito').value,
        tipo_pago: document.getElementById('pr_tipo_pago').value,
        monto: monto
    };

    try {
        const data = await apiRequest('redito/redito_pago.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(datos)
        });

        if (data.status === 'success') {
            const prestamo = redItoPrestamoActual;
            const enviarAuto = document.getElementById('pr_enviar_whatsapp')?.checked ?? false;
            modalBootstrap('modalPagoRedito').hide();
            recargarAlCerrarReciboRedito = true; // al cerrar el recibo se recargan las tablas
            mostrarReciboRedito(data.pago, prestamo.nombre_cliente);
            if (enviarAuto) {
                await enviarWhatsAppRedito(); // envío inmediato; el resultado se muestra en el recibo
            }
        }
    } catch (error) {
        console.error(error);
        marcarCampoInvalido('pr_monto', error.message);
    }
}

// ─── Cancelar préstamo a rédito ─────────────────────────────────────────
async function cancelarRedito(id) {
    if (!confirm('¿Cancelar este préstamo a rédito? Esta acción no se puede deshacer.')) return;

    try {
        const data = await apiRequest('redito/redito_cancelar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_prestamo_redito: id })
        });

        if (data.status === 'success') {
            location.reload();
        }
    } catch (error) {
        console.error(error);
        alert('No se pudo cancelar: ' + error.message);
    }
}

// ─── Recibo de pago de rédito (imprimir y enviar por WhatsApp) ──────────
let reciboRedito = null;
let recargarAlCerrarReciboRedito = false;

function escaparHtml(texto) {
    const d = document.createElement('div');
    d.textContent = String(texto ?? '');
    return d.innerHTML;
}

function mostrarReciboRedito(pago, cliente) {
    reciboRedito = pago;
    const estadoWA = document.getElementById('estadoWhatsAppRedito');
    if (estadoWA) { estadoWA.textContent = ''; estadoWA.className = 'small px-3 pb-2'; }
    const btnWA = document.getElementById('btnEnviarWhatsAppRedito');
    if (btnWA) { btnWA.disabled = false; btnWA.textContent = '📲 Enviar por WhatsApp'; }
    const tipo = pago.tipo_pago === 'Interes' ? 'Interés' : 'Abono a capital';
    document.getElementById('contenidoReciboRedito').innerHTML = `
        <div id="reciboRedito" style="font-family:monospace; font-size:13px;">
            <h3 style="text-align:center;">PRESTA-APP</h3>
            <p style="text-align:center;">Recibo de Pago (Rédito)</p>
            <hr>
            <p><b>Cliente:</b> ${escaparHtml(cliente)}</p>
            <p><b>Préstamo a rédito #:</b> ${escaparHtml(pago.id_prestamo_redito)}</p>
            <p><b>Fecha:</b> ${escaparHtml(pago.fecha_pago)}</p>
            <p><b>Tipo de pago:</b> ${tipo}</p>
            <hr>
            <p><b>MONTO PAGADO:</b> $${parseFloat(pago.monto).toFixed(2)}</p>
            <p><b>Saldo de capital:</b> $${parseFloat(pago.saldo_capital_resultante).toFixed(2)}</p>
            <hr>
            <p style="text-align:center;">¡Gracias por su pago!</p>
        </div>`;
    modalBootstrap('modalReciboRedito').show();
}

// Reabrir un recibo desde el historial (no recarga la página al cerrar)
function verReciboRedito(pago) {
    recargarAlCerrarReciboRedito = false;
    mostrarReciboRedito(pago, pago.nombre_cliente);
}

function imprimirReciboRedito() {
    const contenido = document.getElementById('reciboRedito').innerHTML;
    const ventana = window.open('', '_blank', 'width=400,height=600');
    ventana.document.write(`<html><head><style>
        body { font-family:monospace; font-size:13px; margin:12px; }
        h3,p { margin:2px 0; } hr { border:1px dashed #000; }
        </style></head><body>${contenido}</body></html>`);
    ventana.document.close();
    ventana.focus();
    ventana.print();
    ventana.close();
}

async function enviarWhatsAppRedito() {
    if (!reciboRedito) return;
    const estado = document.getElementById('estadoWhatsAppRedito');
    const boton  = document.getElementById('btnEnviarWhatsAppRedito');
    const mostrarEstado = (texto, clase) => {
        if (!estado) return;
        estado.textContent = texto;
        estado.className = 'small px-3 pb-2 ' + clase;
    };

    if (boton) boton.disabled = true;
    mostrarEstado('Enviando por WhatsApp...', 'text-muted');

    try {
        const data = await apiRequest('redito/redito_whatsapp.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_REDITO },
            body: JSON.stringify({
                id_prestamo_redito: reciboRedito.id_prestamo_redito,
                fecha_pago: reciboRedito.fecha_pago
            })
        });
        if (data.status === 'success') {
            mostrarEstado('✔ Recibo enviado por WhatsApp', 'text-success');
            if (boton) { boton.disabled = false; boton.textContent = '📲 Reenviar por WhatsApp'; }
        }
    } catch (error) {
        console.error(error);
        mostrarEstado('✖ No se pudo enviar: ' + error.message, 'text-danger');
        if (boton) boton.disabled = false;
    }
}

// Al cerrar el recibo de un pago recién registrado, se recargan las tablas.
document.addEventListener('hidden.bs.modal', (ev) => {
    if (ev.target.id === 'modalReciboRedito' && recargarAlCerrarReciboRedito) {
        recargarAlCerrarReciboRedito = false;
        location.reload();
    }
});