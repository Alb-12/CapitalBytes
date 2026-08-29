// pagos.js — lógica del módulo de Pagos.
// Autocontenido: no depende de common.js (que solo se carga en clientes),
// así que trae sus propias copias de los helpers de validación.

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

// Atajo para obtener/mostrar los modales de Bootstrap por id
function modalBootstrap(id) {
    return bootstrap.Modal.getOrCreateInstance(document.getElementById(id));
}

// ─── Estado global del pago activo ─────────────────────────────────────
let pagoActual = {};

// ─── Modales ──────────────────────────────────────────────────────────
function cerrarModalPago()   { modalBootstrap('modalPago').hide(); }
function cerrarModalRecibo() { modalBootstrap('modalRecibo').hide(); }

// ─── Buscar en tabla ──────────────────────────────────────────────────
function buscarPago() {
    const q = document.getElementById('buscarPago').value.toLowerCase();
    document.querySelectorAll('#tablaPagos tbody tr').forEach((f) => {
        f.style.display = f.innerText.toLowerCase().includes(q) ? '' : 'none';
    });
}

// ─── Abrir modal de pago y calcular mora si aplica ────────────────────
function abrirModalPago(prestamo) {
    pagoActual = prestamo;

    limpiarCampoInvalido('pg_monto_pagado');
    limpiarErrorGeneral('modalPago');

    const cuota   = parseFloat(prestamo.cuota_monto);
    const saldo   = parseFloat(prestamo.saldo_pendiente);
    const moraPct = (parseFloat(prestamo.mora_porcentaje) || 0) / 100;
    const tasa    = (parseFloat(prestamo.tasa_interes) || 0) / 100;

    // Calcular interés y capital de la cuota según tipo
    let interes;
    if (prestamo.tipo_interes === 'Simple') {
        interes = parseFloat(prestamo.monto_original || saldo) * tasa;
    } else {
        // Compuesto: interés sobre saldo pendiente
        interes = saldo * tasa;
    }
    let capital = cuota - interes;
    if (capital < 0) capital = 0;
    if (interes < 0) interes = 0;

    // Mora: solo si el préstamo está marcado en atraso
    const mora = prestamo.estado === 'Mora' ? cuota * moraPct : 0;

    const total = capital + interes + mora;

    // Guardar en estado global para usarlo al confirmar el pago
    pagoActual._interes = interes.toFixed(2);
    pagoActual._capital = capital.toFixed(2);
    pagoActual._mora    = mora.toFixed(2);
    pagoActual._total   = total.toFixed(2);

    // Llenar el modal con la información del "pagaré" que se va a registrar
    document.getElementById('pg_id_prestamo').value  = prestamo.id_prestamo;
    document.getElementById('pg_telefono').value     = prestamo.telefono_cliente ?? '';
    document.getElementById('pg_cliente').value      = prestamo.nombre_cliente;
    document.getElementById('pg_cuota').value        = '$' + cuota.toFixed(2);
    document.getElementById('pg_saldo').value         = '$' + saldo.toFixed(2);
    document.getElementById('pg_mora').value          = '$' + mora.toFixed(2);
    document.getElementById('pg_capital').value       = '$' + capital.toFixed(2);
    document.getElementById('pg_interes').value       = '$' + interes.toFixed(2);
    document.getElementById('pg_total').value         = '$' + total.toFixed(2);
    document.getElementById('pg_monto_pagado').value = '';
    document.getElementById('pg_vuelto').value        = '';

    modalBootstrap('modalPago').show();
}

// ─── Calcular vuelto ──────────────────────────────────────────────────
function calcularVuelto() {
    const recibido = parseFloat(document.getElementById('pg_monto_pagado').value) || 0;
    const total    = parseFloat(pagoActual._total) || 0;
    const vuelto   = recibido - total;
    document.getElementById('pg_vuelto').value = vuelto >= 0 ? '$' + vuelto.toFixed(2) : 'Monto insuficiente';
}

// ─── Guardar pago ─────────────────────────────────────────────────────
async function guardarPago() {
    limpiarCampoInvalido('pg_monto_pagado');
    limpiarErrorGeneral('modalPago');

    const montoPagado = parseFloat(document.getElementById('pg_monto_pagado').value) || 0;
    const total       = parseFloat(pagoActual._total);

    if (montoPagado <= 0) {
        marcarCampoInvalido('pg_monto_pagado', 'Ingresa el monto recibido');
        document.getElementById('pg_monto_pagado').focus();
        return;
    }
    if (montoPagado < total) {
        marcarCampoInvalido('pg_monto_pagado', `El monto recibido es menor al total a pagar ($${total.toFixed(2)})`);
        document.getElementById('pg_monto_pagado').focus();
        return;
    }

    const datos = {
        id_prestamo: pagoActual.id_prestamo,
        monto_pagado: total, // se registra lo que corresponde pagar
        monto_mora: pagoActual._mora,
        monto_interes: pagoActual._interes,
        monto_capital: pagoActual._capital,
        saldo_restante: (parseFloat(pagoActual.saldo_pendiente) - parseFloat(pagoActual._capital)).toFixed(2)
    };

    try {
        const data = await apiRequest('pagos/pagos_save.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(datos)
        });

        if (data.status === 'success') {
            cerrarModalPago();
            mostrarRecibo(data.pago, pagoActual.nombre_cliente, pagoActual.telefono_cliente);
        }
    } catch (error) {
        console.error(error);
        mostrarErrorGeneral('modalPago', error.message);
    }
}

// ─── Mostrar recibo (información del pagaré ya registrado) ───────────
function mostrarRecibo(pago, cliente, telefono) {
    pagoActual._reciboTelefono = telefono;
    pagoActual._reciboPago     = pago;

    const html = `
        <div id="reciboContenido" style="font-family:monospace; font-size:13px;">
            <h3 style="text-align:center;">PRESTA-APP</h3>
            <p style="text-align:center;">Recibo de Pago</p>
            <hr>
            <p><b>Cliente:</b> ${cliente}</p>
            <p><b>Préstamo #:</b> ${pago.id_prestamo}</p>
            <p><b>Fecha:</b> ${pago.fecha_pago}</p>
            <hr>
            <p><b>Capital:</b> $${parseFloat(pago.monto_capital).toFixed(2)}</p>
            <p><b>Interés:</b> $${parseFloat(pago.monto_interes).toFixed(2)}</p>
            <p><b>Mora:</b> $${parseFloat(pago.monto_mora).toFixed(2)}</p>
            <hr>
            <p><b>TOTAL PAGADO:</b> $${parseFloat(pago.monto_pagado).toFixed(2)}</p>
            <p><b>Saldo restante:</b> $${parseFloat(pago.saldo_restante).toFixed(2)}</p>
            <hr>
            <p style="text-align:center;">¡Gracias por su pago!</p>
        </div>
    `;
    document.getElementById('contenidoRecibo').innerHTML = html;
    modalBootstrap('modalRecibo').show();
}

// ─── Ver recibo desde historial ───────────────────────────────────────
function verRecibo(pago) {
    mostrarRecibo(pago, pago.nombre_cliente, '');
}

// ─── Imprimir térmica (80mm) ──────────────────────────────────────────
function imprimirTermica() {
    const contenido = document.getElementById('reciboContenido').innerHTML;
    const ventana = window.open('', '_blank', 'width=302,height=600');
    ventana.document.write(`
        <html><head>
        <style>
            body { width:80mm; font-family:monospace; font-size:12px; margin:0; padding:4px; }
            h3,p { margin:2px 0; }
            hr { border:1px dashed #000; }
        </style>
        </head><body>${contenido}</body></html>
    `);
    ventana.document.close();
    ventana.focus();
    ventana.print();
    ventana.close();
}

// ─── Imprimir hoja carta ──────────────────────────────────────────────
function imprimirCarta() {
    const contenido = document.getElementById('reciboContenido').innerHTML;
    const ventana = window.open('', '_blank');
    ventana.document.write(`
        <html><head>
        <style>
            body { width:21cm; font-family:Arial,sans-serif; font-size:14px;
                   margin:2cm; padding:0; }
            h3 { font-size:20px; }
            hr { border:1px solid #000; }
        </style>
        </head><body>${contenido}</body></html>
    `);
    ventana.document.close();
    ventana.focus();
    ventana.print();
    ventana.close();
}

// ─── Enviar por WhatsApp Business API ────────────────────────────────
async function enviarWhatsApp() {
    const pago     = pagoActual._reciboPago;
    const telefono = pagoActual._reciboTelefono;

    if (!telefono) {
        mostrarErrorGeneral('modalRecibo', 'Este cliente no tiene teléfono registrado');
        return;
    }

    try {
        const data = await apiRequest('pagos/whatsapp_enviar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                telefono: telefono,
                id_pago: pago.id_pagos,
                id_prestamo: pago.id_prestamo
            })
        });

        if (data.status === 'success') {
            mostrarErrorGeneral('modalRecibo', ''); // limpia cualquier banner previo
            limpiarErrorGeneral('modalRecibo');
            alert('Recibo enviado por WhatsApp correctamente');
        }
    } catch (error) {
        console.error(error);
        mostrarErrorGeneral('modalRecibo', 'Error al enviar por WhatsApp: ' + error.message);
    }
}