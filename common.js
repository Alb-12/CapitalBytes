// common.js — helpers compartidos entre todos los módulos (clientes,
// préstamos, pagos, etc.). Cárgalo SIEMPRE en dashboard.php, sin importar
// el módulo activo, porque los demás archivos JS dependen de estas
// funciones.

// Atajo para obtener el valor de un input por id (evita repetir document.getElementById(...).value)
function val(id) {
    return document.getElementById(id)?.value?.trim() ?? '';
}

// Marca un input como inválido: le agrega la clase 'campo-invalido' (borde
// rojo, ver validacion.css) y muestra un mensaje justo debajo del campo.
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

// Quita la marca de error de un campo puntual
function limpiarCampoInvalido(id) {
    const el = document.getElementById(id);
    el?.classList.remove('campo-invalido');
    el?.removeAttribute('aria-invalid');
    document.getElementById(`error-${id}`)?.remove();
}

// Limpia varios campos a la vez (se llama al inicio de cada intento de guardar/actualizar)
function limpiarErrores(ids) {
    ids.forEach(limpiarCampoInvalido);
}

// Para errores que no corresponden a un campo puntual (fallo de red, error
// interno del servidor, etc.) mostramos un banner arriba del formulario en
// vez de un alert(). contenedorId es el id del <div> donde insertar el banner.
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

// Wrapper centralizado para todas las llamadas fetch: maneja errores HTTP,
// parseo de JSON y evita repetir try/catch en cada función.
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